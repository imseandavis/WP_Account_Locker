<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles logging of account lock/unlock activities
 *
 * @since 1.0.2
 */
class Account_Locker_Activity_Log {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_activity_log_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_log_styles' ) );
    }

    /**
     * Add activity log to user's meta
     */
    public function log_activity( $user_id, $action, $performed_by ) {
        $log_entry = array(
            'action' => $action,
            'performed_by' => $performed_by,
            'timestamp' => current_time( 'mysql' )
        );

        $current_log = get_user_meta( $user_id, 'account_locker_log', true );
        if ( ! is_array( $current_log ) ) {
            $current_log = array();
        }

        array_unshift( $current_log, $log_entry );
        
        // Keep only last 50 entries
        if ( count( $current_log ) > 50 ) {
            array_pop( $current_log );
        }

        update_user_meta( $user_id, 'account_locker_log', $current_log );
    }

    /**
     * Add activity log page to admin menu
     */
    public function add_activity_log_page() {
        add_users_page(
            __( 'Account Lock Activity', 'account-locker' ),
            __( 'Lock Activity', 'account-locker' ),
            'manage_options',
            'account-lock-activity',
            array( $this, 'render_activity_page' )
        );
    }

    /**
     * Render the activity log page
     */
    public function render_activity_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'account-locker' ) );
        }

        $users = get_users();
        $all_activities = array();

        foreach ( $users as $user ) {
            $log = get_user_meta( $user->ID, 'account_locker_log', true );
            if ( ! empty( $log ) && is_array( $log ) ) {
                foreach ( $log as $entry ) {
                    $entry['user'] = $user;
                    $all_activities[] = $entry;
                }
            }
        }

        // Sort by timestamp
        usort( $all_activities, function( $a, $b ) {
            return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
        });

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Account Lock Activity Log', 'account-locker' ); ?></h1>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Date', 'account-locker' ); ?></th>
                        <th><?php esc_html_e( 'User', 'account-locker' ); ?></th>
                        <th><?php esc_html_e( 'Action', 'account-locker' ); ?></th>
                        <th><?php esc_html_e( 'Performed By', 'account-locker' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $all_activities as $activity ) : ?>
                        <tr>
                            <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $activity['timestamp'] ) ) ); ?></td>
                            <td><?php echo esc_html( $activity['user']->display_name ); ?></td>
                            <td><?php echo esc_html( $activity['action'] ); ?></td>
                            <td><?php 
                                $performer = get_user_by( 'id', $activity['performed_by'] );
                                echo esc_html( $performer ? $performer->display_name : __( 'Unknown', 'account-locker' ) );
                            ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Enqueue styles for activity log page
     */
    public function enqueue_log_styles( $hook ) {
        if ( 'users_page_account-lock-activity' === $hook ) {
            wp_enqueue_style(
                'account-locker-admin',
                plugin_dir_url( dirname( __FILE__ ) ) . 'css/admin.css',
                array(),
                '1.0.2'
            );
        }
    }
} 