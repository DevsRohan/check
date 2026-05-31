<?php
/**
 * Cart Tracker - captures phone & cart data, sends to backend.
 * Works with BOTH Classic checkout AND WooCommerce Blocks checkout.
 *
 * Capture happens via TWO reliable layers:
 *  1. Server-side hooks (primary, 100% reliable - reads order/draft data)
 *  2. Frontend JS (backup - captures as user types)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CR_Tracker {

    public function init() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Consent field
        add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_consent' ) );
        add_action( 'woocommerce_init', array( $this, 'register_checkout_field' ) );

        // AJAX (frontend JS backup capture)
        add_action( 'wp_ajax_cr_track_cart', array( $this, 'ajax_track_cart' ) );
        add_action( 'wp_ajax_nopriv_cr_track_cart', array( $this, 'ajax_track_cart' ) );
        add_action( 'wp_ajax_cr_heartbeat', array( $this, 'ajax_heartbeat' ) );
        add_action( 'wp_ajax_nopriv_cr_heartbeat', array( $this, 'ajax_heartbeat' ) );

        // SERVER-SIDE capture (primary, reliable)
        add_action( 'woocommerce_checkout_update_order_review', array( $this, 'capture_classic' ) );
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'capture_blocks' ), 10, 2 );

        // Conversion tracking (classic + blocks)
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'mark_converted' ), 10, 3 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'mark_converted_blocks' ) );
    }

    /**
     * Enqueue frontend assets on checkout.
     */
    public function enqueue_scripts() {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
        if ( 'yes' !== Checkout_Rescuer::get_setting( 'enabled', 'yes' ) ) return;

        wp_enqueue_style( 'cr-public', CR_PLUGIN_URL . 'public/css/cr-public.css', array(), CR_VERSION );
        wp_enqueue_script( 'cr-public', CR_PLUGIN_URL . 'public/js/cr-public.js', array( 'jquery' ), CR_VERSION, true );

        wp_localize_script( 'cr-public', 'crPublic', array(
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'cr_public_nonce' ),
            'requireConsent' => Checkout_Rescuer::get_setting( 'require_consent', 'yes' ),
            'countryCode'    => Checkout_Rescuer::get_setting( 'country_code', '+91' ),
            'consentText'    => Checkout_Rescuer::get_setting( 'consent_text', 'I agree to receive order updates via WhatsApp' ),
        ) );
    }


    /**
     * Register consent as a WooCommerce additional checkout field (Blocks-compatible, WC 8.2+).
     */
    public function register_checkout_field() {
        if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) return;
        if ( 'yes' !== Checkout_Rescuer::get_setting( 'require_consent', 'yes' ) ) return;

        $text = Checkout_Rescuer::get_setting( 'consent_text', 'I agree to receive order updates via WhatsApp' );

        try {
            woocommerce_register_additional_checkout_field( array(
                'id'       => 'checkout-rescuer/consent',
                'label'    => $text,
                'location' => 'contact',
                'type'     => 'checkbox',
                'required' => false,
            ) );
        } catch ( Exception $e ) {
            // Field already registered or unsupported - ignore.
        }
    }

    /**
     * Classic checkout: render consent checkbox below billing form.
     */
    public function render_consent() {
        if ( 'yes' !== Checkout_Rescuer::get_setting( 'require_consent', 'yes' ) ) return;
        // Skip if Blocks checkout field API is active (avoids duplicate).
        if ( function_exists( 'woocommerce_register_additional_checkout_field' ) && WC()->is_rest_api_request() ) return;
        $text = Checkout_Rescuer::get_setting( 'consent_text', 'I agree to receive order updates via WhatsApp' );
        ?>
        <div class="cr-consent-field" id="cr-consent-wrapper">
            <label class="cr-consent-label">
                <input type="checkbox" id="cr-consent-checkbox" name="cr_consent" value="1">
                <span class="cr-consent-text"><?php echo esc_html( $text ); ?></span>
            </label>
        </div>
        <?php
    }


    /**
     * SERVER-SIDE capture for CLASSIC checkout.
     * Fires on AJAX order review update (every time fields change).
     *
     * @param string $post_data Serialized form data.
     */
    public function capture_classic( $post_data ) {
        $data = array();
        parse_str( $post_data, $data );

        $phone = sanitize_text_field( $data['billing_phone'] ?? '' );
        if ( empty( $phone ) ) return;

        $name  = trim( ( $data['billing_first_name'] ?? '' ) . ' ' . ( $data['billing_last_name'] ?? '' ) );
        $email = sanitize_email( $data['billing_email'] ?? '' );

        $consent = $this->resolve_consent( ! empty( $data['cr_consent'] ) );
        $this->send_track( $phone, $name, $email, $consent );
    }

    /**
     * SERVER-SIDE capture for BLOCKS checkout.
     * Fires when the draft order is updated from the Store API request.
     *
     * @param WC_Order $order   Draft order.
     * @param mixed    $request REST request.
     */
    public function capture_blocks( $order, $request ) {
        if ( ! is_a( $order, 'WC_Order' ) ) return;

        $phone = $order->get_billing_phone();
        if ( empty( $phone ) ) {
            $phone = $order->get_shipping_phone();
        }
        if ( empty( $phone ) ) return;

        $name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $email = $order->get_billing_email();

        $consent = $this->resolve_consent( $this->read_blocks_consent( $order ) );
        $this->send_track( $phone, $name, $email, $consent );
    }

    /**
     * Read the consent additional field value from a blocks order.
     *
     * @param WC_Order $order Order.
     * @return bool
     */
    private function read_blocks_consent( $order ) {
        $keys = array(
            '_wc_other/checkout-rescuer/consent',
            '_checkout-rescuer/consent',
            'checkout-rescuer/consent',
        );
        foreach ( $keys as $key ) {
            $val = $order->get_meta( $key );
            if ( '' !== $val && null !== $val ) {
                return ( '1' === (string) $val || 'true' === $val || 1 === $val || true === $val );
            }
        }
        return false;
    }

    /**
     * Decide final consent value based on the require_consent setting.
     *
     * @param bool $checked Whether the consent box was checked.
     * @return bool
     */
    private function resolve_consent( $checked ) {
        // If consent is NOT required, track everyone.
        if ( 'yes' !== Checkout_Rescuer::get_setting( 'require_consent', 'yes' ) ) {
            return true;
        }
        return (bool) $checked;
    }


    /**
     * Shared method: send cart data to backend.
     *
     * @param string $phone   Raw phone.
     * @param string $name    Customer name.
     * @param string $email   Customer email.
     * @param bool   $consent Consent given.
     */
    private function send_track( $phone, $name, $email, $consent ) {
        if ( 'yes' !== Checkout_Rescuer::get_setting( 'enabled', 'yes' ) ) return;

        $cart_data = $this->get_cart_data();
        if ( empty( $cart_data['items'] ) || $cart_data['total'] <= 0 ) return;

        $phone = $this->format_phone( $phone );
        if ( empty( $phone ) ) return;

        CR_API::post( 'track/cart', array(
            'session_id'     => $this->get_session_id(),
            'store_url'      => home_url(),
            'customer_name'  => $name,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'cart_contents'  => $cart_data['items'],
            'cart_total'     => $cart_data['total'],
            'currency'       => get_woocommerce_currency(),
            'consent'        => (bool) $consent,
        ) );
    }

    /**
     * Format phone to international format with country code.
     *
     * @param string $phone Raw phone.
     * @return string
     */
    private function format_phone( $phone ) {
        $phone  = trim( $phone );
        $digits = preg_replace( '/\D/', '', $phone );
        if ( strlen( $digits ) < 10 ) return '';

        // Already has + prefix
        if ( strpos( $phone, '+' ) === 0 ) {
            return '+' . $digits;
        }
        // Starts with 00 (international)
        if ( strpos( $digits, '00' ) === 0 ) {
            return '+' . substr( $digits, 2 );
        }
        // Prepend configured country code
        $cc = Checkout_Rescuer::get_setting( 'country_code', '+91' );
        $cc_digits = preg_replace( '/\D/', '', $cc );
        $local = ltrim( $digits, '0' );
        return '+' . $cc_digits . $local;
    }

    /**
     * AJAX: track cart (frontend JS backup).
     */
    public function ajax_track_cart() {
        check_ajax_referer( 'cr_public_nonce', 'nonce' );

        $phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
        $name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $consent = $this->resolve_consent( ! empty( $_POST['consent'] ) );

        if ( empty( $phone ) ) {
            wp_send_json_error( array( 'message' => 'Phone required.' ) );
        }

        $this->send_track( $phone, $name, $email, $consent );
        wp_send_json_success();
    }

    /**
     * AJAX: heartbeat (keep cart fresh).
     */
    public function ajax_heartbeat() {
        check_ajax_referer( 'cr_public_nonce', 'nonce' );

        $cart_data = $this->get_cart_data();
        CR_API::post( 'track/heartbeat', array(
            'session_id'    => $this->get_session_id(),
            'cart_contents' => $cart_data['items'],
            'cart_total'    => $cart_data['total'],
        ) );

        wp_send_json_success();
    }


    /**
     * Classic checkout: order placed -> mark converted.
     */
    public function mark_converted( $order_id, $posted_data, $order ) {
        CR_API::post( 'track/converted', array(
            'session_id'  => $this->get_session_id(),
            'order_id'    => (string) $order_id,
            'order_total' => $order->get_total(),
        ) );
    }

    /**
     * Blocks checkout: order placed -> mark converted.
     */
    public function mark_converted_blocks( $order ) {
        if ( ! is_a( $order, 'WC_Order' ) ) return;
        CR_API::post( 'track/converted', array(
            'session_id'  => $this->get_session_id(),
            'order_id'    => (string) $order->get_id(),
            'order_total' => $order->get_total(),
        ) );
    }

    /**
     * Get current cart items + total.
     */
    private function get_cart_data() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return array( 'items' => array(), 'total' => 0 );
        }

        $items = array();
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( empty( $item['data'] ) ) continue;
            $product = $item['data'];
            $items[] = array(
                'product_id'   => $item['product_id'],
                'variation_id' => $item['variation_id'] ?? 0,
                'name'         => $product->get_name(),
                'quantity'     => $item['quantity'],
                'price'        => $product->get_price(),
                'image'        => wp_get_attachment_url( $product->get_image_id() ),
            );
        }

        return array(
            'items' => $items,
            'total' => (float) WC()->cart->get_total( 'edit' ),
        );
    }

    /**
     * Get or create a stable session id.
     */
    private function get_session_id() {
        if ( is_user_logged_in() ) {
            return 'user_' . get_current_user_id();
        }
        if ( ! empty( $_COOKIE['cr_session_id'] ) ) {
            return sanitize_text_field( $_COOKIE['cr_session_id'] );
        }
        $id = 'cr_' . wp_generate_password( 20, false );
        if ( ! headers_sent() ) {
            setcookie( 'cr_session_id', $id, time() + WEEK_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }
        $_COOKIE['cr_session_id'] = $id;
        return $id;
    }
}
