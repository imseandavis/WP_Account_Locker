<?php
/*
* @package    account-locker
* @author     Sean Davis 2024
* @link       https://seanasaservcice.com
*
* Plugin Name: Account Locker
* Description: Allows administrators to lock and unlock user accounts
* Version: 1.1.1
* Author: Sean Davis
* Text Domain: account-locker
* Domain Path: /languages/
* Tested up to: 6.7
* License: GPL v2 or later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Account_Locker {
    public $activity_log;
    
    public function __construct() {
        // Make instance available globally
        global $account_locker;
        $account_locker = $this;

        // Add lock option to user profile
        add_action( 'show_user_profile', array( $this, 'add_lock_option' ) );
        add_action( 'edit_user_profile', array( $this, 'add_lock_option' ) );

        // Save lock option
        add_action( 'personal_options_update', array( $this, 'save_lock_option' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_lock_option' ) );

        // Check lock status during login
        add_filter( 'authenticate', array( $this, 'check_lock_status' ), 30, 3 );

        // Initialize modules
        $this->init_modules();

        // Add admin styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );

        // Add settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    private function init_modules() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-bulk-actions.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-user-status-column.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-activity-log.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-deactivation-handler.php';
        
        new Account_Locker_Bulk_Actions();
        new Account_Locker_Status_Column();
        $this->activity_log = new Account_Locker_Activity_Log();
        new Account_Locker_Deactivation();
    }

    public function add_lock_option( $user ) {
        if ( ! current_user_can( 'edit_users' ) ) {
            return;
        }

        // Don't show lock option if viewing own profile
        if ( get_current_user_id() === $user->ID ) {
            return;
        }

        wp_nonce_field( 'account_locker_action', 'account_locker_nonce' );
        $is_locked = get_user_meta( $user->ID, 'account_locked', true );
        ?>
        <h3><?php esc_html_e( 'Account Lock Status', 'account-locker' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="account_locked"><?php esc_html_e( 'Lock Account', 'account-locker' ); ?></label></th>
                <td>
                    <input type="checkbox" name="account_locked" id="account_locked" 
                           value="1" <?php checked( $is_locked, '1' ); ?>>
                    <span class="description">
                        <?php esc_html_e( 'Lock this user account', 'account-locker' ); ?>
                    </span>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Force logout of locked user
     *
     * @param int $user_id The user ID being locked
     */
    private function force_user_logout($user_id) {
        // Get all sessions for user
        $sessions = WP_Session_Tokens::get_instance($user_id);
        
        // Destroy all sessions
        $sessions->destroy_all();
    }

    /**
     * Save lock option
     */
    public function save_lock_option($user_id) {
        if (!current_user_can('edit_users')) {
            return;
        }

        if (get_current_user_id() === $user_id) {
            return;
        }

        check_admin_referer('account_locker_action', 'account_locker_nonce');
        
        $is_locked = isset($_POST['account_locked']) ? '1' : '0';
        $old_status = get_user_meta($user_id, 'account_locked', true);
        
        if ($is_locked !== $old_status) {
            update_user_meta($user_id, 'account_locked', $is_locked);
            
            // Log the activity
            $action = $is_locked === '1' ? 'Account Locked' : 'Account Unlocked';
            $this->activity_log->log_activity($user_id, $action, get_current_user_id());

            // Force logout if account is being locked
            if ($is_locked === '1') {
                $sessions = WP_Session_Tokens::get_instance($user_id);
                if ($sessions) {
                    $sessions->destroy_all();
                }
            }
        }
    }

    public function check_lock_status( $user, $username, $password ) {
        if ( $user instanceof WP_User ) {
            $is_locked = get_user_meta( $user->ID, 'account_locked', true );
            
            if ( $is_locked === '1' ) {
                $message = get_option(
                    'account_locker_message',
                    __( 'This account has been locked. Please contact an administrator.', 'account-locker' )
                );
                return new WP_Error( 'account_locked', $message );
            }
        }
        return $user;
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles( $hook ) {
        if ( 'users.php' === $hook ) {
            wp_enqueue_style(
                'account-locker-admin',
                plugin_dir_url( __FILE__ ) . 'css/admin.css',
                array(),
                '1.0.0'
            );
        }
    }

    /**
     * Register plugin settings
     *
     * @since 1.0.0
     */
    public function register_settings() {
        register_setting(
            'general',
            'account_locker_message',
            array(
                'type' => 'string',
                'description' => __( 'Message shown to locked users when they try to log in', 'account-locker' ),
                'sanitize_callback' => 'sanitize_text_field',
                'default' => __( 'This account has been locked. Please contact an administrator.', 'account-locker' )
            )
        );

        add_settings_section(
            'account_locker_section',
            __( 'Account Locker Settings', 'account-locker' ),
            array( $this, 'render_settings_section' ),
            'general'
        );

        add_settings_field(
            'account_locker_message',
            __( 'Lock Message', 'account-locker' ),
            array( $this, 'render_message_field' ),
            'general',
            'account_locker_section'
        );
    }

    /**
     * Render the settings section description
     *
     * @since 1.0.0
     */
    public function render_settings_section() {
        echo '<p>' . esc_html__( 'Configure the message displayed to users when their account is locked.', 'account-locker' ) . '</p>';
    }

    /**
     * Render the message input field
     *
     * @since 1.0.0
     */
    public function render_message_field() {
        $message = get_option(
            'account_locker_message',
            __( 'This account has been locked. Please contact an administrator.', 'account-locker' )
        );
        ?>
        <input type="text" 
               name="account_locker_message" 
               id="account_locker_message" 
               value="<?php echo esc_attr( $message ); ?>" 
               class="regular-text">
        <p class="description">
            <?php esc_html_e( 'This message will be shown when a locked user attempts to log in.', 'account-locker' ); ?>
        </p>
        <?php
    }

    /**
     * Plugin activation hook
     *
     * @since 1.0.0
     */
    public static function activate() {
        // Set default lock message if not already set
        if ( ! get_option( 'account_locker_message' ) ) {
            update_option( 'account_locker_message', 
                __( 'This account has been locked. Please contact an administrator.', 'account-locker' )
            );
        }
    }
}

// Initialize plugin
new Account_Locker();

// Register activation hook
register_activation_hook( __FILE__, array( 'Account_Locker', 'activate' ) );