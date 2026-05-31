<?php
/**
 * Recovery handler - restores cart when customer clicks recovery link
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CR_Recovery {

    public function init() {
        add_action( 'template_redirect', array( $this, 'handle_restore' ) );
        add_action( 'woocommerce_before_checkout_form', array( $this, 'welcome_back_banner' ) );
    }

    /**
     * Handle ?cr_restore=true&cr_token=xxx&cr_session=xxx
     */
    public function handle_restore() {
        if ( ! isset( $_GET['cr_restore'] ) || 'true' !== $_GET['cr_restore'] ) {
            return;
        }

        $token   = sanitize_text_field( $_GET['cr_token'] ?? '' );
        $session = sanitize_text_field( $_GET['cr_session'] ?? '' );
        $coupon  = sanitize_text_field( $_GET['cr_coupon'] ?? '' );

        if ( empty( $token ) ) return;

        // Set session cookie for conversion tracking
        if ( ! empty( $session ) ) {
            setcookie( 'cr_session_id', $session, time() + WEEK_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
            $_COOKIE['cr_session_id'] = $session;
        }

        // Fetch cart contents from backend and restore items
        $this->restore_cart_items( $token );

        // Create and apply coupon if provided
        if ( ! empty( $coupon ) && WC()->cart ) {
            $this->create_wc_coupon( $coupon );
            WC()->cart->apply_coupon( $coupon );
        }

        // Set welcome back flag
        if ( WC()->session ) {
            WC()->session->set( 'cr_welcome_back', true );
        }

        // Redirect to checkout without the params (clean URL)
        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }

    /**
     * Fetch cart contents from backend by token and add items to WC cart.
     *
     * @param string $token Recovery token.
     */
    private function restore_cart_items( $token ) {
        // Fetch cart detail from backend
        $result = CR_API::get( 'carts/by-token/' . $token );

        if ( empty( $result['success'] ) || empty( $result['data']['cart_contents'] ) ) {
            return;
        }

        $items = $result['data']['cart_contents'];

        // Clear existing cart to avoid duplicates
        WC()->cart->empty_cart();

        foreach ( $items as $item ) {
            $product_id   = absint( $item['product_id'] ?? 0 );
            $variation_id = absint( $item['variation_id'] ?? 0 );
            $quantity     = absint( $item['quantity'] ?? 1 );

            if ( ! $product_id ) {
                continue;
            }

            // Verify product still exists and is purchasable
            $product = wc_get_product( $variation_id ? $variation_id : $product_id );
            if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
                continue;
            }

            WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
        }
    }

    /**
     * Create a WooCommerce coupon from the backend-generated code.
     *
     * @param string $code Coupon code.
     */
    private function create_wc_coupon( $code ) {
        // Check if coupon already exists
        $existing = wc_get_coupon_id_by_code( $code );
        if ( $existing ) {
            return;
        }

        // Fetch discount settings from backend
        $settings_result = CR_API::get( 'settings' );
        $settings = ! empty( $settings_result['data'] ) ? $settings_result['data'] : array();

        $discount_type   = isset( $settings['discount_type'] ) ? $settings['discount_type'] : 'percent';
        $discount_amount = isset( $settings['discount_amount'] ) ? floatval( $settings['discount_amount'] ) : 10;

        $wc_type = ( 'percent' === $discount_type ) ? 'percent' : 'fixed_cart';

        $coupon = new WC_Coupon();
        $coupon->set_code( $code );
        $coupon->set_discount_type( $wc_type );
        $coupon->set_amount( $discount_amount );
        $coupon->set_individual_use( true );
        $coupon->set_usage_limit( 1 );
        $coupon->set_date_expires( strtotime( '+48 hours' ) );
        $coupon->set_description( 'Checkout Rescuer auto-generated recovery coupon' );
        $coupon->add_meta_data( '_cr_generated', 'yes' );
        $coupon->save();
    }

    /**
     * Show welcome back banner
     */
    public function welcome_back_banner() {
        if ( ! WC()->session || ! WC()->session->get( 'cr_welcome_back' ) ) {
            return;
        }

        WC()->session->set( 'cr_welcome_back', false );
        ?>
        <div class="cr-welcome-back">
            <div class="cr-welcome-back-content">
                <span class="cr-welcome-back-icon">&#x1F44B;</span>
                <p><?php esc_html_e( 'Welcome back! Your cart has been restored. Complete your order below.', 'checkout-rescuer' ); ?></p>
            </div>
        </div>
        <?php
    }
}
