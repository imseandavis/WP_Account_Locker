<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin deactivation and cleanup
 *
 * @since 1.0.3
 */
class Account_Locker_Deactivation {

    /**
     * Initialize deactivation functionality
     */
    public function __construct() {
        add_action( 'admin_footer', array( $this, 'deactivation_modal' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_account_locker_cleanup', array( $this, 'handle_cleanup' ) );
    }

    /**
     * Add deactivation modal HTML
     */
    public function deactivation_modal() {
        $screen = get_current_screen();
        if ( $screen->id !== 'plugins' ) {
            return;
        }
        ?>
        <div id="account-locker-deactivate-modal" style="display:none;">
            <div class="account-locker-modal-content">
                <h2><?php esc_html_e( 'Deactivate Account Locker', 'account-locker' ); ?></h2>
                <p><?php esc_html_e( 'Would you like to remove all plugin data from the database?', 'account-locker' ); ?></p>
                <p class="description">
                    <?php esc_html_e( 'This will remove all lock statuses, activity logs, and plugin settings.', 'account-locker' ); ?>
                </p>
                <div class="account-locker-modal-footer">
                    <button class="button" id="account-locker-cancel">
                        <?php esc_html_e( 'Cancel', 'account-locker' ); ?>
                    </button>
                    <button class="button" id="account-locker-deactivate">
                        <?php esc_html_e( 'Deactivate Only', 'account-locker' ); ?>
                    </button>
                    <button class="button button-primary" id="account-locker-cleanup">
                        <?php esc_html_e( 'Remove Data & Deactivate', 'account-locker' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue deactivation scripts and styles
     */
    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'plugins.php' ) {
            return;
        }

        wp_enqueue_style(
            'account-locker-deactivate',
            plugin_dir_url( dirname( __FILE__ ) ) . 'css/deactivate.css',
            array(),
            '1.0.3'
        );

        wp_enqueue_script(
            'account-locker-deactivate',
            plugin_dir_url( dirname( __FILE__ ) ) . 'js/deactivate.js',
            array( 'jquery' ),
            '1.0.3',
            true
        );

        wp_localize_script( 'account-locker-deactivate', 'accountLockerDeactivate', array(
            'nonce' => wp_create_nonce( 'account_locker_cleanup' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'pluginSlug' => 'account-locker/account-locker.php'
        ));
    }

    /**
     * Handle the cleanup AJAX request
     */
    public function handle_cleanup() {
        check_ajax_referer( 'account_locker_cleanup', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        global $wpdb;

        // Remove user meta
        $wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'account_locked' ) );
        $wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'account_locker_log' ) );

        // Remove plugin options
        delete_option( 'account_locker_message' );

        wp_send_json_success();
    }
} 