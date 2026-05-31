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

        // Apply coupon if provided
        if ( ! empty( $coupon ) && WC()->cart ) {
            WC()->cart->apply_coupon( $coupon );
        }

        // Set welcome back flag
        WC()->session->set( 'cr_welcome_back', true );

        // Redirect to checkout without the params (clean URL)
        wp_safe_redirect( wc_get_checkout_url() );
        exit;
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
