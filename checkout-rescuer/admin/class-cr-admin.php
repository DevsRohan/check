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
        add_submenu_page( 'checkout-rescuer', 'System Status', 'System Status', 'manage_woocommerce', 'checkout-rescuer-status', array( $this, 'render_status' ) );
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

    /**
     * Render System Status / diagnostics page.
     */
    public function render_status() {
        $checks = CR_Diagnostics::run_all();
        $report = CR_Diagnostics::build_report( $checks );
        ?>
        <div class="wrap cr-status-wrap" style="max-width:900px;font-family:Inter,-apple-system,sans-serif;">
            <h1 style="font-size:24px;font-weight:700;margin:20px 0 6px;">System Status</h1>
            <p style="color:#64748b;margin:0 0 24px;">Health check for Checkout Rescuer. Send the report below for verification.</p>

            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                <?php foreach ( $checks as $c ) :
                    $color = 'pass' === $c['status'] ? '#10b981' : ( 'warn' === $c['status'] ? '#f59e0b' : '#ef4444' );
                    $bg    = 'pass' === $c['status'] ? '#ecfdf5' : ( 'warn' === $c['status'] ? '#fffbeb' : '#fef2f2' );
                    $txt   = strtoupper( $c['status'] );
                ?>
                <div style="display:flex;align-items:center;gap:16px;padding:16px 20px;border-bottom:1px solid #f1f5f9;">
                    <span style="flex-shrink:0;width:64px;text-align:center;font-size:11px;font-weight:700;color:<?php echo esc_attr( $color ); ?>;background:<?php echo esc_attr( $bg ); ?>;padding:5px 0;border-radius:6px;"><?php echo esc_html( $txt ); ?></span>
                    <div style="flex:1;">
                        <strong style="display:block;font-size:14px;color:#0f172a;"><?php echo esc_html( $c['label'] ); ?></strong>
                        <span style="font-size:13px;color:#64748b;"><?php echo esc_html( $c['detail'] ); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <h2 style="font-size:16px;font-weight:600;margin:28px 0 10px;">Copy-Paste Report</h2>
            <textarea readonly onclick="this.select()" style="width:100%;height:300px;font-family:monospace;font-size:12px;line-height:1.6;padding:16px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;color:#334155;resize:vertical;"><?php echo esc_textarea( $report ); ?></textarea>

            <p style="margin-top:16px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=checkout-rescuer-status' ) ); ?>" class="button button-primary" style="background:#10b981;border-color:#10b981;">Re-run Checks</a>
            </p>
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
            // Auto-set recovery_base_url to backend_url if not provided
            if ( empty( $remote['recovery_base_url'] ) && ! empty( $current['backend_url'] ) ) {
                $remote['recovery_base_url'] = rtrim( $current['backend_url'], '/' );
            }
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
