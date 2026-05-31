<?php
/**
 * Cart Tracker - captures phone and sends cart data to backend
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CR_Tracker {

    public function init() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_consent' ) );
        add_action( 'wp_ajax_cr_track_cart', array( $this, 'ajax_track_cart' ) );
        add_action( 'wp_ajax_nopriv_cr_track_cart', array( $this, 'ajax_track_cart' ) );
        add_action( 'wp_ajax_cr_heartbeat', array( $this, 'ajax_heartbeat' ) );
        add_action( 'wp_ajax_nopriv_cr_heartbeat', array( $this, 'ajax_heartbeat' ) );
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'mark_converted' ), 10, 3 );
    }

    public function enqueue_scripts() {
        if ( ! is_checkout() ) return;
        if ( 'yes' !== Checkout_Rescuer::get_setting( 'enabled', 'yes' ) ) return;

        wp_enqueue_style( 'cr-public', CR_PLUGIN_URL . 'public/css/cr-public.css', array(), CR_VERSION );
        wp_enqueue_script( 'cr-public', CR_PLUGIN_URL . 'public/js/cr-public.js', array( 'jquery' ), CR_VERSION, true );

        wp_localize_script( 'cr-public', 'crPublic', array(
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'cr_public_nonce' ),
            'backendUrl'     => rtrim( Checkout_Rescuer::get_setting( 'backend_url', '' ), '/' ),
            'storeUrl'       => home_url(),
            'requireConsent' => Checkout_Rescuer::get_setting( 'require_consent', 'yes' ),
            'countryCode'    => Checkout_Rescuer::get_setting( 'country_code', '+91' ),
        ) );
    }

    public function render_consent() {
        if ( 'yes' !== Checkout_Rescuer::get_setting( 'require_consent', 'yes' ) ) return;
        $text = Checkout_Rescuer::get_setting( 'consent_text', 'I agree to receive order updates via WhatsApp' );
        ?>
        <div class="cr-consent-field">
            <label class="cr-consent-label">
                <input type="checkbox" id="cr-consent-checkbox" name="cr_consent" value="1">
                <span class="cr-consent-text"><?php echo esc_html( $text ); ?></span>
            </label>
        </div>
        <?php
    }

    public function ajax_track_cart() {
        check_ajax_referer( 'cr_public_nonce', 'nonce' );

        $phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
        $name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $consent = absint( $_POST['consent'] ?? 0 );

        if ( empty( $phone ) ) {
            wp_send_json_error( array( 'message' => 'Phone required.' ) );
        }

        $cart_data = $this->get_cart_data();
        $session_id = $this->get_session_id();

        $result = CR_API::post( 'track/cart', array(
            'session_id'     => $session_id,
            'store_url'      => home_url(),
            'customer_name'  => $name,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'cart_contents'  => $cart_data['items'],
            'cart_total'     => $cart_data['total'],
            'currency'       => get_woocommerce_currency(),
            'consent'        => (bool) $consent,
        ) );

        if ( ! empty( $result['success'] ) ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( array( 'message' => $result['error'] ?? 'Failed to track.' ) );
        }
    }

    public function ajax_heartbeat() {
        check_ajax_referer( 'cr_public_nonce', 'nonce' );

        $session_id = $this->get_session_id();
        $cart_data  = $this->get_cart_data();

        CR_API::post( 'track/heartbeat', array(
            'session_id'    => $session_id,
            'cart_contents' => $cart_data['items'],
            'cart_total'    => $cart_data['total'],
        ) );

        wp_send_json_success();
    }

    public function mark_converted( $order_id, $posted_data, $order ) {
        $session_id = $this->get_session_id();

        CR_API::post( 'track/converted', array(
            'session_id'  => $session_id,
            'order_id'    => (string) $order_id,
            'order_total' => $order->get_total(),
        ) );
    }

    private function get_cart_data() {
        if ( ! WC()->cart ) {
            return array( 'items' => array(), 'total' => 0 );
        }

        $items = array();
        foreach ( WC()->cart->get_cart() as $item ) {
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

    private function get_session_id() {
        if ( is_user_logged_in() ) {
            return 'user_' . get_current_user_id();
        }

        if ( ! empty( $_COOKIE['cr_session_id'] ) ) {
            return sanitize_text_field( $_COOKIE['cr_session_id'] );
        }

        $id = 'cr_' . wp_generate_password( 20, false );
        setcookie( 'cr_session_id', $id, time() + WEEK_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        return $id;
    }
}
