<?php
/**
 * Admin controller.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Admin {

    /**
     * Initialize admin.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_cr_get_dashboard_data', array( $this, 'ajax_dashboard_data' ) );
        add_action( 'wp_ajax_cr_resend_message', array( $this, 'ajax_resend_message' ) );
        add_action( 'wp_ajax_cr_delete_cart', array( $this, 'ajax_delete_cart' ) );
        add_action( 'wp_ajax_cr_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_cr_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_cr_complete_onboarding', array( $this, 'ajax_complete_onboarding' ) );

        // Check onboarding
        add_action( 'admin_init', array( $this, 'maybe_redirect_onboarding' ) );
    }

    /**
     * Add admin menu items.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Checkout Rescuer', 'checkout-rescuer' ),
            __( 'Cart Rescuer', 'checkout-rescuer' ),
            'manage_woocommerce',
            'checkout-rescuer',
            array( $this, 'render_dashboard' ),
            'dashicons-smartphone',
            56
        );

        add_submenu_page(
            'checkout-rescuer',
            __( 'Dashboard', 'checkout-rescuer' ),
            __( 'Dashboard', 'checkout-rescuer' ),
            'manage_woocommerce',
            'checkout-rescuer',
            array( $this, 'render_dashboard' )
        );

        add_submenu_page(
            'checkout-rescuer',
            __( 'Abandoned Carts', 'checkout-rescuer' ),
            __( 'Abandoned Carts', 'checkout-rescuer' ),
            'manage_woocommerce',
            'checkout-rescuer-carts',
            array( $this, 'render_carts' )
        );

        add_submenu_page(
            'checkout-rescuer',
            __( 'Messages', 'checkout-rescuer' ),
            __( 'Messages', 'checkout-rescuer' ),
            'manage_woocommerce',
            'checkout-rescuer-messages',
            array( $this, 'render_messages' )
        );

        add_submenu_page(
            'checkout-rescuer',
            __( 'Settings', 'checkout-rescuer' ),
            __( 'Settings', 'checkout-rescuer' ),
            'manage_woocommerce',
            'checkout-rescuer-settings',
            array( $this, 'render_settings' )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        $screens = array(
            'toplevel_page_checkout-rescuer',
            'cart-rescuer_page_checkout-rescuer-carts',
            'cart-rescuer_page_checkout-rescuer-messages',
            'cart-rescuer_page_checkout-rescuer-settings',
        );

        if ( ! in_array( $hook, $screens, true ) ) {
            return;
        }

        wp_enqueue_style(
            'cr-admin',
            CR_PLUGIN_URL . 'admin/css/cr-admin.css',
            array(),
            CR_VERSION
        );

        wp_enqueue_script(
            'cr-admin',
            CR_PLUGIN_URL . 'admin/js/cr-admin.js',
            array( 'jquery' ),
            CR_VERSION,
            true
        );

        wp_localize_script( 'cr-admin', 'crAdmin', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'cr_admin_nonce' ),
            'currency' => get_woocommerce_currency_symbol(),
            'strings'  => array(
                'confirmDelete' => __( 'Are you sure you want to delete this cart?', 'checkout-rescuer' ),
                'confirmResend' => __( 'Resend recovery message to this customer?', 'checkout-rescuer' ),
                'saving'        => __( 'Saving...', 'checkout-rescuer' ),
                'saved'         => __( 'Settings saved!', 'checkout-rescuer' ),
                'error'         => __( 'An error occurred. Please try again.', 'checkout-rescuer' ),
                'testing'       => __( 'Sending test message...', 'checkout-rescuer' ),
                'testSuccess'   => __( 'Test message sent successfully!', 'checkout-rescuer' ),
                'testFailed'    => __( 'Test message failed: ', 'checkout-rescuer' ),
            ),
        ) );
    }

    /**
     * Redirect to onboarding if not complete.
     */
    public function maybe_redirect_onboarding() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $onboarding_complete = get_option( 'cr_onboarding_complete', false );
        $current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        if ( ! $onboarding_complete && strpos( $current_page, 'checkout-rescuer' ) === 0 && 'checkout-rescuer-onboarding' !== $current_page ) {
            // Only redirect if accessing CR pages (not other admin pages)
            if ( ! isset( $_GET['skip_onboarding'] ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=checkout-rescuer-onboarding' ) );
                exit;
            }
        }

        // Register hidden onboarding page
        add_submenu_page(
            null,
            __( 'Setup Wizard', 'checkout-rescuer' ),
            '',
            'manage_woocommerce',
            'checkout-rescuer-onboarding',
            array( $this, 'render_onboarding' )
        );
    }

    /**
     * Render dashboard page.
     */
    public function render_dashboard() {
        include CR_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render carts page.
     */
    public function render_carts() {
        include CR_PLUGIN_DIR . 'admin/views/carts.php';
    }

    /**
     * Render messages page.
     */
    public function render_messages() {
        include CR_PLUGIN_DIR . 'admin/views/messages.php';
    }

    /**
     * Render settings page.
     */
    public function render_settings() {
        include CR_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Render onboarding page.
     */
    public function render_onboarding() {
        include CR_PLUGIN_DIR . 'admin/views/onboarding.php';
    }

    /**
     * AJAX: Get dashboard data.
     */
    public function ajax_dashboard_data() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $period    = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : '30days';
        $analytics = new CR_Analytics();

        wp_send_json_success( array(
            'summary'   => $analytics->get_summary( $period ),
            'chart'     => $analytics->get_chart_data( $period ),
            'channels'  => $analytics->get_channel_breakdown( $period ),
        ) );
    }

    /**
     * AJAX: Resend recovery message.
     */
    public function ajax_resend_message() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $cart_id = isset( $_POST['cart_id'] ) ? absint( $_POST['cart_id'] ) : 0;
        if ( ! $cart_id ) {
            wp_send_json_error( array( 'message' => 'Invalid cart ID.' ) );
        }

        global $wpdb;
        $cart = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cr_abandoned_carts WHERE id = %d AND status = 'abandoned'",
                $cart_id
            )
        );

        if ( ! $cart ) {
            wp_send_json_error( array( 'message' => 'Cart not found or not abandoned.' ) );
        }

        $messenger = new CR_Messenger();
        $result    = $messenger->send_message( $cart, $cart->messages_sent + 1 );

        if ( $result ) {
            wp_send_json_success( array( 'message' => 'Message sent successfully.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to send message. Check Twilio settings.' ) );
        }
    }

    /**
     * AJAX: Delete a cart record.
     */
    public function ajax_delete_cart() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $cart_id = isset( $_POST['cart_id'] ) ? absint( $_POST['cart_id'] ) : 0;
        if ( ! $cart_id ) {
            wp_send_json_error( array( 'message' => 'Invalid cart ID.' ) );
        }

        global $wpdb;

        // Delete related messages first
        $wpdb->delete(
            $wpdb->prefix . 'cr_messages',
            array( 'cart_id' => $cart_id ),
            array( '%d' )
        );

        // Delete the cart
        $wpdb->delete(
            $wpdb->prefix . 'cr_abandoned_carts',
            array( 'id' => $cart_id ),
            array( '%d' )
        );

        wp_send_json_success( array( 'message' => 'Cart deleted.' ) );
    }

    /**
     * AJAX: Save settings.
     */
    public function ajax_save_settings() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

        if ( empty( $settings ) || ! is_array( $settings ) ) {
            wp_send_json_error( array( 'message' => 'No settings provided.' ) );
        }

        // Sanitize each setting
        $sanitized = array();
        $allowed_keys = array(
            'enabled', 'abandonment_timeout', 'primary_channel', 'fallback_to_sms',
            'max_messages', 'message_1_delay', 'message_2_delay', 'enable_discount',
            'discount_type', 'discount_amount', 'discount_message_step',
            'message_template_1', 'message_template_2', 'phone_field_label',
            'consent_text', 'require_consent', 'data_retention_days',
            'track_guest_carts', 'country_code',
        );

        foreach ( $settings as $key => $value ) {
            if ( ! in_array( $key, $allowed_keys, true ) ) {
                continue;
            }

            switch ( $key ) {
                case 'abandonment_timeout':
                case 'max_messages':
                case 'message_1_delay':
                case 'message_2_delay':
                case 'discount_amount':
                case 'discount_message_step':
                case 'data_retention_days':
                    $sanitized[ $key ] = absint( $value );
                    break;
                case 'message_template_1':
                case 'message_template_2':
                case 'consent_text':
                    $sanitized[ $key ] = sanitize_textarea_field( $value );
                    break;
                default:
                    $sanitized[ $key ] = sanitize_text_field( $value );
                    break;
            }
        }

        $current = get_option( 'cr_settings', array() );
        $updated = array_merge( $current, $sanitized );
        update_option( 'cr_settings', $updated );

        wp_send_json_success( array( 'message' => 'Settings saved.' ) );
    }

    /**
     * AJAX: Test Twilio connection.
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $channel = isset( $_POST['channel'] ) ? sanitize_text_field( wp_unslash( $_POST['channel'] ) ) : 'whatsapp';

        if ( empty( $phone ) ) {
            wp_send_json_error( array( 'message' => 'Phone number is required.' ) );
        }

        $messenger = new CR_Messenger();
        $result    = $messenger->send_test( $phone, $channel );

        if ( $result['success'] ) {
            wp_send_json_success( array( 'message' => 'Test message sent!' ) );
        } else {
            wp_send_json_error( array( 'message' => $result['error'] ) );
        }
    }

    /**
     * AJAX: Complete onboarding.
     */
    public function ajax_complete_onboarding() {
        check_ajax_referer( 'cr_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        // Save Twilio credentials (encrypted)
        $sid      = isset( $_POST['twilio_sid'] ) ? sanitize_text_field( wp_unslash( $_POST['twilio_sid'] ) ) : '';
        $token    = isset( $_POST['twilio_token'] ) ? sanitize_text_field( wp_unslash( $_POST['twilio_token'] ) ) : '';
        $phone    = isset( $_POST['twilio_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['twilio_phone'] ) ) : '';
        $whatsapp = isset( $_POST['whatsapp_number'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp_number'] ) ) : '';

        if ( empty( $sid ) || empty( $token ) ) {
            wp_send_json_error( array( 'message' => 'Twilio SID and Token are required.' ) );
        }

        update_option( 'cr_twilio_sid', CR_Encryption::encrypt( $sid ) );
        update_option( 'cr_twilio_token', CR_Encryption::encrypt( $token ) );

        if ( ! empty( $phone ) ) {
            update_option( 'cr_twilio_phone', CR_Encryption::encrypt( $phone ) );
        }
        if ( ! empty( $whatsapp ) ) {
            update_option( 'cr_whatsapp_number', CR_Encryption::encrypt( $whatsapp ) );
        }

        update_option( 'cr_onboarding_complete', true );

        wp_send_json_success( array(
            'message'  => 'Setup complete!',
            'redirect' => admin_url( 'admin.php?page=checkout-rescuer' ),
        ) );
    }
}
