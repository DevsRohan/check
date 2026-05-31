<?php
/**
 * Admin Controller - handles all admin functionality
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CR_Admin {

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX handlers
        add_action( 'wp_ajax_cr_get_dashboard', array( $this, 'ajax_get_dashboard' ) );
        add_action( 'wp_ajax_cr_get_carts', array( $this, 'ajax_get_carts' ) );
        add_action( 'wp_ajax_cr_get_messages', array( $this, 'ajax_get_messages' ) );
        add_action( 'wp_ajax_cr_get_whatsapp_status', array( $this, 'ajax_get_whatsapp_status' ) );
        add_action( 'wp_ajax_cr_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_cr_resend_message', array( $this, 'ajax_resend_message' ) );
        add_action( 'wp_ajax_cr_delete_cart', array( $this, 'ajax_delete_cart' ) );
        add_action( 'wp_ajax_cr_test_message', array( $this, 'ajax_test_message' ) );
        add_action( 'wp_ajax_cr_disconnect_whatsapp', array( $this, 'ajax_disconnect_whatsapp' ) );
        add_action( 'wp_ajax_cr_restart_whatsapp', array( $this, 'ajax_restart_whatsapp' ) );
    }

    public function add_menu() {
        add_menu_page(
            'Checkout Rescuer', 'Cart Rescuer', 'manage_woocommerce',
            'checkout-rescuer', array( $this, 'render_app' ),
            'dashicons-smartphone', 56
        );
        add_submenu_page( 'checkout-rescuer', 'Dashboard', 'Dashboard', 'manage_woocommerce', 'checkout-rescuer', array( $this, 'render_app' ) );
        add_submenu_page( 'checkout-rescuer', 'Settings', 'Settings', 'manage_woocommerce', 'checkout-rescuer-settings', array( $this, 'render_app' ) );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'checkout-rescuer' ) === false ) return;

        wp_enqueue_style( 'cr-admin', CR_PLUGIN_URL . 'admin/css/cr-admin.css', array(), CR_VERSION );
        wp_enqueue_script( 'cr-admin', CR_PLUGIN_URL . 'admin/js/cr-admin.js', array( 'jquery' ), CR_VERSION, true );

        // Merge local settings with remote settings from backend
        $local_settings = get_option( 'cr_settings', array() );
        $remote_settings = CR_API::get( 'settings' );
        $merged = $local_settings;
        if ( ! empty( $remote_settings['data'] ) && is_array( $remote_settings['data'] ) ) {
            $merged = array_merge( $remote_settings['data'], $local_settings );
        }

        wp_localize_script( 'cr-admin', 'crAdmin', array(
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'cr_admin_nonce' ),
            'backendUrl' => rtrim( Checkout_Rescuer::get_setting( 'backend_url', '' ), '/' ),
            'apiKey'     => Checkout_Rescuer::get_setting( 'api_key', '' ),
            'currency'   => get_woocommerce_currency_symbol(),
            'settings'   => $merged,
            'page'       => isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : 'checkout-rescuer',
        ) );
    }

    /**
     * Render the single-page app shell
     */
    public function render_app() {
        ?>
        <div id="cr-app" class="cr-app">
            <div class="cr-loading-screen" id="cr-loading">
                <div class="cr-loader"></div>
                <p>Loading Checkout Rescuer...</p>
            </div>
        </div>
        <?php
    }

    // === AJAX HANDLERS ===

    public function ajax_get_dashboard() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized' );

        $period = sanitize_text_field( $_POST['period'] ?? '30days' );

        $summary = CR_API::get( 'analytics/summary', array( 'period' => $period ) );
        $chart   = CR_API::get( 'analytics/chart', array( 'period' => $period ) );
        $live    = CR_API::get( 'analytics/live' );
        $wa      = CR_API::get( 'whatsapp/status' );

        wp_send_json_success( array(
            'summary'  => $summary['data'] ?? array(),
            'chart'    => $chart['data'] ?? array(),
            'live'     => $live['data'] ?? array(),
            'whatsapp' => $wa['data'] ?? array(),
        ) );
    }

    public function ajax_get_carts() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized' );

        $params = array(
            'status'   => sanitize_text_field( $_POST['status'] ?? 'abandoned' ),
            'page'     => absint( $_POST['page'] ?? 1 ),
            'per_page' => 20,
            'search'   => sanitize_text_field( $_POST['search'] ?? '' ),
        );

        $result = CR_API::get( 'carts', $params );
        wp_send_json_success( $result['data'] ?? array() );
    }

    public function ajax_get_messages() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized' );

        $params = array(
            'status'  => sanitize_text_field( $_POST['status'] ?? '' ),
            'channel' => sanitize_text_field( $_POST['channel'] ?? '' ),
            'page'    => absint( $_POST['page'] ?? 1 ),
        );

        $result = CR_API::get( 'messages', $params );
        $stats  = CR_API::get( 'messages/stats' );

        wp_send_json_success( array(
            'messages' => $result['data'] ?? array(),
            'stats'    => $stats['data'] ?? array(),
        ) );
    }

    public function ajax_get_whatsapp_status() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized' );

        $result = CR_API::get( 'whatsapp/status' );
        wp_send_json_success( $result['data'] ?? array() );
    }

    public function ajax_save_settings() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized' );

        $settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
        if ( ! is_array( $settings ) ) wp_send_json_error( 'Invalid settings.' );

        // Save local WP settings
        $local_keys = array( 'backend_url', 'api_key', 'enabled', 'require_consent', 'consent_text', 'country_code' );
        $current = get_option( 'cr_settings', array() );
        foreach ( $local_keys as $key ) {
            if ( isset( $settings[ $key ] ) ) {
                $current[ $key ] = sanitize_text_field( $settings[ $key ] );
            }
        }
        update_option( 'cr_settings', $current );

        // Send remote settings to backend
        $remote_keys = array(
            'enabled', 'abandonment_timeout', 'max_messages',
            'message_1_delay', 'message_2_delay', 'enable_discount',
            'discount_type', 'discount_amount', 'discount_message_step',
            'message_template_1', 'message_template_2',
            'require_consent', 'country_code', 'store_name', 'recovery_base_url',
        );

        $remote = array();
        foreach ( $remote_keys as $key ) {
            if ( isset( $settings[ $key ] ) ) {
                $remote[ $key ] = $settings[ $key ];
            }
        }

        if ( ! empty( $remote ) ) {
            CR_API::post( 'settings', array( 'settings' => $remote ) );
        }

        wp_send_json_success( 'Settings saved.' );
    }

    public function ajax_resend_message() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized' );

        $cart_id = absint( $_POST['cart_id'] ?? 0 );
        $result = CR_API::post( "carts/{$cart_id}/resend" );

        if ( ! empty( $result['success'] ) ) {
            wp_send_json_success( $result['message'] ?? 'Sent.' );
        } else {
            wp_send_json_error( $result['error'] ?? 'Failed.' );
        }
    }

    public function ajax_delete_cart() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized' );

        $cart_id = absint( $_POST['cart_id'] ?? 0 );
        CR_API::delete( "carts/{$cart_id}" );
        wp_send_json_success( 'Deleted.' );
    }

    public function ajax_test_message() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized' );

        $phone = sanitize_text_field( $_POST['phone'] ?? '' );
        $result = CR_API::post( 'whatsapp/test', array( 'phone' => $phone ) );

        if ( ! empty( $result['success'] ) ) {
            wp_send_json_success( 'Test message sent!' );
        } else {
            wp_send_json_error( $result['error'] ?? 'Failed to send.' );
        }
    }

    public function ajax_disconnect_whatsapp() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized' );

        CR_API::post( 'whatsapp/disconnect' );
        wp_send_json_success( 'Disconnected.' );
    }

    public function ajax_restart_whatsapp() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized' );

        CR_API::post( 'whatsapp/restart' );
        wp_send_json_success( 'Restarting...' );
    }
}
