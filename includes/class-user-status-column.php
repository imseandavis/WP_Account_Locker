<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages the lock status column in the users list
 *
 * @since 1.0.0
 */
class Account_Locker_Status_Column {

    /**
     * Initialize status column functionality
     */
    public function __construct() {
        add_filter( 'manage_users_columns', array( $this, 'add_status_column' ) );
        add_filter( 'manage_users_custom_column', array( $this, 'display_status_column' ), 10, 3 );
        add_filter( 'manage_users_sortable_columns', array( $this, 'make_status_column_sortable' ) );
        add_action( 'pre_get_users', array( $this, 'sort_by_lock_status' ) );
        
        // Add new filters for views
        add_filter( 'views_users', array( $this, 'add_status_views' ) );
        add_action( 'pre_get_users', array( $this, 'filter_users_by_status' ) );
    }

    /**
     * Add the lock status column
     *
     * @param array $columns The current columns
     * @return array Modified columns
     */
    public function add_status_column( $columns ) {
        $columns['account_status'] = __( 'Account Status', 'account-locker' );
        return $columns;
    }

    /**
     * Display the lock status
     *
     * @param string $output Custom column output
     * @param string $column_name Column name
     * @param int    $user_id User ID
     * @return string Modified output
     */
    public function display_status_column( $output, $column_name, $user_id ) {
        if ( $column_name !== 'account_status' ) {
            return $output;
        }

        $is_locked = get_user_meta( $user_id, 'account_locked', true );
        
        if ( $is_locked === '1' ) {
            return sprintf(
                '<span class="account-locked">%s</span>',
                esc_html__( 'Locked', 'account-locker' )
            );
        }

        return sprintf(
            '<span class="account-unlocked">%s</span>',
            esc_html__( 'Active', 'account-locker' )
        );
    }

    /**
     * Make the status column sortable
     *
     * @param array $columns Sortable columns
     * @return array Modified sortable columns
     */
    public function make_status_column_sortable( $columns ) {
        $columns['account_status'] = 'account_locked';
        return $columns;
    }

    /**
     * Handle sorting by lock status
     *
     * @param WP_User_Query $query The users query
     */
    public function sort_by_lock_status( $query ) {
        if ( ! is_admin() ) {
            return;
        }

        $orderby = $query->get( 'orderby' );

        if ( $orderby === 'account_status' ) {
            $query->set( 'meta_key', 'account_locked' );
            $query->set( 'orderby', 'meta_value' );
        }
    }

    /**
     * Add status filter views
     *
     * @param array $views Current view links
     * @return array Modified view links
     */
    public function add_status_views( $views ) {
        global $wpdb;

        // Get current filter
        $status = isset( $_GET['account_status'] ) ? sanitize_text_field( $_GET['account_status'] ) : '';
        
        // Count locked accounts
        $locked_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
            WHERE meta_key = %s AND meta_value = %s",
            'account_locked', '1'
        ) );

        // Get total users
        $total_users = count_users();
        $active_count = $total_users['total_users'] - $locked_count;

        // Add Active filter
        $active_class = empty( $status ) || $status === 'active' ? 'current' : '';
        $views['active_accounts'] = sprintf(
            '<a href="%s" class="active-accounts %s">%s <span class="count">(%s)</span></a>',
            esc_url( add_query_arg( 'account_status', 'active', admin_url( 'users.php' ) ) ),
            esc_attr( $active_class ),
            esc_html__( 'Active', 'account-locker' ),
            number_format_i18n( $active_count )
        );

        // Add Locked filter
        $locked_class = $status === 'locked' ? 'current' : '';
        $views['locked_accounts'] = sprintf(
            '<a href="%s" class="locked-accounts %s">%s <span class="count">(%s)</span></a>',
            esc_url( add_query_arg( 'account_status', 'locked', admin_url( 'users.php' ) ) ),
            esc_attr( $locked_class ),
            esc_html__( 'Locked', 'account-locker' ),
            number_format_i18n( $locked_count )
        );

        return $views;
    }

    /**
     * Filter users query based on status selection
     *
     * @param WP_User_Query $query The users query
     */
    public function filter_users_by_status( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $status = isset( $_GET['account_status'] ) ? sanitize_text_field( $_GET['account_status'] ) : '';
        
        if ( $status === 'active' ) {
            $query->set( 'meta_query', array(
                'key'     => 'account_locked',
                'value'   => '0',
                'compare' => '!='
            ) );
        } elseif ( $status === 'locked' ) {
            $query->set( 'meta_query', array(
                'key'     => 'account_locked',
                'value'   => '1',
                'compare' => '='
            ) );
        }
    }
} 