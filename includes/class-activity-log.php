<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles logging of account lock/unlock activities
 *
 * @since 1.0.2
 */
class Account_Locker_Activity_Log {

    private $per_page = 15;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_activity_log_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_log_styles'));
    }

    /**
     * Add activity log to admin menu
     */
    public function add_activity_log_page() {
        add_users_page(
            __('Account Lock Activity Log', 'account-locker'),
            __('Lock Activity', 'account-locker'),
            'manage_options',
            'account-lock-activity',
            array($this, 'render_activity_page')
        );
    }

    /**
     * Enqueue styles for activity log page
     */
    public function enqueue_log_styles($hook) {
        if ('users_page_account-lock-activity' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'account-locker-admin',
            plugin_dir_url(dirname(__FILE__)) . 'css/admin.css',
            array(),
            '1.1.0'
        );
    }

    /**
     * Add activity log to user's meta
     */
    public function log_activity($user_id, $action, $performed_by) {
        $log_entry = array(
            'action' => $action,
            'performed_by' => $performed_by,
            'timestamp' => current_time('mysql')
        );

        $current_log = get_user_meta($user_id, 'account_locker_log', true);
        if (!is_array($current_log)) {
            $current_log = array();
        }

        array_unshift($current_log, $log_entry);
        
        // Keep only last 50 entries
        if (count($current_log) > 50) {
            array_pop($current_log);
        }

        update_user_meta($user_id, 'account_locker_log', $current_log);
    }

    /**
     * Render the activity log page
     */
    public function render_activity_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'account-locker'));
        }

        // Get filters
        $action_filter = isset($_GET['filter_action']) ? sanitize_text_field($_GET['filter_action']) : '';
        $user_filter = isset($_GET['filter_user']) ? intval($_GET['filter_user']) : 0;
        $performed_by_filter = isset($_GET['filter_performed_by']) ? sanitize_text_field($_GET['filter_performed_by']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

        // Get sort parameters
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';

        // Get current page
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

        // Get all activities with filters
        $all_activities = $this->get_filtered_activities($action_filter, $user_filter, $performed_by_filter, $date_from, $date_to);

        // Sort activities
        $all_activities = $this->sort_activities($all_activities, $orderby, $order);

        // Paginate results
        $total_items = count($all_activities);
        $total_pages = ceil($total_items / $this->per_page);
        $offset = ($current_page - 1) * $this->per_page;
        $activities = array_slice($all_activities, $offset, $this->per_page);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Account Lock Activity Log', 'account-locker'); ?></h1>

            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" action="">
                    <input type="hidden" name="page" value="account-lock-activity" />
                    
                    <!-- Action Filter -->
                    <select name="filter_action">
                        <option value=""><?php esc_html_e('All Actions', 'account-locker'); ?></option>
                        <option value="locked" <?php selected($action_filter, 'locked'); ?>><?php esc_html_e('Locked', 'account-locker'); ?></option>
                        <option value="unlocked" <?php selected($action_filter, 'unlocked'); ?>><?php esc_html_e('Unlocked', 'account-locker'); ?></option>
                    </select>

                    <!-- User Filter -->
                    <select name="filter_user">
                        <option value="0"><?php esc_html_e('All Users', 'account-locker'); ?></option>
                        <?php
                        $users = get_users(array('fields' => array('ID', 'display_name')));
                        foreach ($users as $user) {
                            echo '<option value="' . esc_attr($user->ID) . '" ' . selected($user_filter, $user->ID, false) . '>' . 
                                 esc_html($user->display_name) . '</option>';
                        }
                        ?>
                    </select>

                    <!-- Performed By Filter -->
                    <select name="filter_performed_by">
                        <option value=""><?php esc_html_e('All Performers', 'account-locker'); ?></option>
                        <option value="system" <?php selected($performed_by_filter, 'system'); ?>><?php esc_html_e('System', 'account-locker'); ?></option>
                        <?php
                        foreach ($users as $user) {
                            echo '<option value="' . esc_attr($user->ID) . '" ' . selected($performed_by_filter, $user->ID, false) . '>' . 
                                 esc_html($user->display_name) . '</option>';
                        }
                        ?>
                    </select>

                    <!-- Date Range -->
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="<?php esc_attr_e('From', 'account-locker'); ?>" />
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="<?php esc_attr_e('To', 'account-locker'); ?>" />

                    <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'account-locker'); ?>" />
                    <?php if ($action_filter || $user_filter || $performed_by_filter || $date_from || $date_to): ?>
                        <a href="<?php echo esc_url(admin_url('users.php?page=account-lock-activity')); ?>" class="button"><?php esc_html_e('Reset', 'account-locker'); ?></a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Activity Table -->
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <?php
                        $columns = array(
                            'date' => __('Date', 'account-locker'),
                            'user' => __('User', 'account-locker'),
                            'action' => __('Action', 'account-locker'),
                            'performed_by' => __('Performed By', 'account-locker')
                        );

                        foreach ($columns as $column_key => $column_label) {
                            $current_order = ($orderby === $column_key) ? $order : 'asc';
                            $new_order = ($current_order === 'asc') ? 'desc' : 'asc';
                            $sort_url = add_query_arg(array(
                                'orderby' => $column_key,
                                'order' => $new_order
                            ));
                            
                            echo '<th class="sortable">';
                            echo '<a href="' . esc_url($sort_url) . '" class="sort-column">';
                            echo '<span>' . esc_html($column_label) . '</span>';
                            echo '<span class="sorting-indicator"></span>';
                            if ($orderby === $column_key) {
                                echo '<span class="sorted ' . esc_attr($order) . '"></span>';
                            }
                            echo '</a></th>';
                        }
                        ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activities)): ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e('No activity found.', 'account-locker'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($activity['timestamp']))); ?></td>
                                <td><?php echo esc_html($activity['user']->display_name); ?></td>
                                <td><?php echo esc_html($activity['action']); ?></td>
                                <td><?php 
                                    if ($activity['performed_by'] === 0) {
                                        esc_html_e('System', 'account-locker');
                                    } else {
                                        $performer = get_user_by('id', $activity['performed_by']);
                                        echo esc_html($performer ? $performer->display_name : __('Unknown', 'account-locker'));
                                    }
                                ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(
                                _n('%s item', '%s items', $total_items, 'account-locker'),
                                number_format_i18n($total_items)
                            ); ?>
                        </span>
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get filtered activities
     */
    private function get_filtered_activities($action_filter, $user_filter, $performed_by_filter, $date_from, $date_to) {
        $users = get_users();
        $all_activities = array();

        foreach ($users as $user) {
            $log = get_user_meta($user->ID, 'account_locker_log', true);
            if (!empty($log) && is_array($log)) {
                foreach ($log as $entry) {
                    $entry['user'] = $user;
                    
                    // Apply action filter
                    if ($action_filter) {
                        if ($action_filter === 'locked' && !stripos($entry['action'], 'locked')) {
                            continue;
                        }
                        if ($action_filter === 'unlocked' && !stripos($entry['action'], 'unlocked')) {
                            continue;
                        }
                    }

                    // Apply user filter
                    if ($user_filter && $user->ID != $user_filter) {
                        continue;
                    }

                    // Apply performed by filter
                    if ($performed_by_filter) {
                        if ($performed_by_filter === 'system' && $entry['performed_by'] !== 0) {
                            continue;
                        } elseif ($performed_by_filter !== 'system' && $entry['performed_by'] != $performed_by_filter) {
                            continue;
                        }
                    }

                    // Apply date range filter
                    if ($date_from && strtotime($entry['timestamp']) < strtotime($date_from)) {
                        continue;
                    }
                    if ($date_to && strtotime($entry['timestamp']) > strtotime($date_to . ' 23:59:59')) {
                        continue;
                    }

                    $all_activities[] = $entry;
                }
            }
        }

        return $all_activities;
    }

    /**
     * Sort activities
     */
    private function sort_activities($activities, $orderby, $order) {
        usort($activities, function($a, $b) use ($orderby, $order) {
            $result = 0;
            
            switch ($orderby) {
                case 'date':
                    $result = strtotime($b['timestamp']) - strtotime($a['timestamp']);
                    break;
                case 'user':
                    $result = strcasecmp($a['user']->display_name, $b['user']->display_name);
                    break;
                case 'action':
                    $result = strcasecmp($a['action'], $b['action']);
                    break;
                case 'performed_by':
                    $a_name = $a['performed_by'] === 0 ? 'System' : 
                        (($a_user = get_user_by('id', $a['performed_by'])) ? $a_user->display_name : 'Unknown');
                    $b_name = $b['performed_by'] === 0 ? 'System' : 
                        (($b_user = get_user_by('id', $b['performed_by'])) ? $b_user->display_name : 'Unknown');
                    $result = strcasecmp($a_name, $b_name);
                    break;
            }

            return ($order === 'asc') ? $result : -$result;
        });

        return $activities;
    }
}