<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles bulk locking/unlocking of user accounts
 *
 * @since 1.0.0
 */
class Account_Locker_Bulk_Actions {

    private $activity_log;

    public function __construct() {
        // Wait for plugins_loaded to ensure Account_Locker is initialized
        add_action('plugins_loaded', array($this, 'init'));
        
        add_filter('bulk_actions-users', array($this, 'register_bulk_actions'));
        add_filter('handle_bulk_actions-users', array($this, 'handle_bulk_lock_unlock'), 10, 3);
        add_action('admin_notices', array($this, 'display_bulk_action_notice'));
    }

    public function init() {
        global $account_locker;
        if (isset($account_locker) && isset($account_locker->activity_log)) {
            $this->activity_log = $account_locker->activity_log;
        }
    }

    /**
     * Add bulk actions to the user list dropdown
     *
     * @param array $actions Existing bulk actions
     * @return array Modified bulk actions
     */
    public function register_bulk_actions($actions) {
        $actions['lock_accounts'] = __('Lock Accounts', 'account-locker');
        $actions['unlock_accounts'] = __('Unlock Accounts', 'account-locker');
        return $actions;
    }

    /**
     * Handle the bulk lock/unlock actions
     *
     * @param string $redirect_url The redirect URL
     * @param string $doaction The action being performed
     * @param array  $items The items to process
     * @return string The redirect URL
     */
    public function handle_bulk_lock_unlock($redirect_url, $doaction, $items) {
        // Check if this is a bulk action from the dropdown
        $bulk_action = '';
        if (isset($_REQUEST['action']) && $_REQUEST['action'] !== '-1') {
            $bulk_action = sanitize_text_field($_REQUEST['action']);
        } elseif (isset($_REQUEST['action2']) && $_REQUEST['action2'] !== '-1') {
            $bulk_action = sanitize_text_field($_REQUEST['action2']);
        }

        // If no valid bulk action, return
        if (!in_array($bulk_action, array('lock_accounts', 'unlock_accounts'))) {
            return $redirect_url;
        }

        if (!current_user_can('edit_users')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'account-locker'));
        }

        // Get user IDs from the request
        $user_ids = isset($_REQUEST['users']) ? array_map('intval', (array)$_REQUEST['users']) : array();
        if (empty($user_ids)) {
            return $redirect_url;
        }

        $current_user_id = get_current_user_id();
        $processed = 0;
        $skipped = 0;
        $attempted_self_lock = false;
        $lock_value = ($bulk_action === 'lock_accounts') ? '1' : '0';

        foreach ($user_ids as $user_id) {
            // Check if trying to lock own account
            if ($user_id == $current_user_id && $bulk_action === 'lock_accounts') {
                $attempted_self_lock = true;
                $skipped++;
                continue;
            }

            // Check current lock status
            $current_status = get_user_meta($user_id, 'account_locked', true);
            
            // Skip if account is already in desired state
            if (($lock_value === '1' && $current_status === '1') || 
                ($lock_value === '0' && ($current_status === '0' || $current_status === ''))) {
                $skipped++;
                continue;
            }

            update_user_meta($user_id, 'account_locked', $lock_value);
            
            // Log the activity
            if (isset($this->activity_log)) {
                $action_text = $lock_value === '1' ? 'Account Locked (Bulk)' : 'Account Unlocked (Bulk)';
                $this->activity_log->log_activity($user_id, $action_text, $current_user_id);
            }

            // Force logout if account is being locked
            if ($lock_value === '1') {
                $sessions = WP_Session_Tokens::get_instance($user_id);
                if ($sessions) {
                    $sessions->destroy_all();
                }
            }
            
            $processed++;
        }

        return add_query_arg(array(
            'bulk_action' => $bulk_action,
            'processed' => $processed,
            'skipped' => $skipped,
            'self_lock_attempt' => $attempted_self_lock ? '1' : '0',
            'users' => count($user_ids)
        ), $redirect_url);
    }

    /**
     * Display admin notices for bulk actions
     */
    public function display_bulk_action_notice() {
        if (!isset($_REQUEST['bulk_action'])) {
            return;
        }

        $action = sanitize_text_field($_REQUEST['bulk_action']);
        if (!in_array($action, array('lock_accounts', 'unlock_accounts'))) {
            return;
        }

        $processed = isset($_REQUEST['processed']) ? intval($_REQUEST['processed']) : 0;
        $skipped = isset($_REQUEST['skipped']) ? intval($_REQUEST['skipped']) : 0;
        $attempted_self_lock = isset($_REQUEST['self_lock_attempt']) && $_REQUEST['self_lock_attempt'] === '1';

        $messages = array();

        if ($processed > 0) {
            $messages[] = sprintf(
                _n(
                    '%d user account %s.',
                    '%d user accounts %s.',
                    $processed,
                    'account-locker'
                ),
                $processed,
                $action === 'lock_accounts' ? 'locked' : 'unlocked'
            );
        }

        if ($attempted_self_lock) {
            $messages[] = __('Your own account was skipped as you cannot lock your own account.', 'account-locker');
        }

        if ($skipped > 0) {
            $messages[] = sprintf(
                _n(
                    '%d user account skipped (already in desired state).',
                    '%d user accounts skipped (already in desired state).',
                    $skipped,
                    'account-locker'
                ),
                $skipped
            );
        }

        if (!empty($messages)) {
            $notice_class = $attempted_self_lock ? 'notice-warning' : 'notice-info';
            echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>' . 
                 esc_html(implode(' ', $messages)) . 
                 '</p></div>';
        }
    }
} 