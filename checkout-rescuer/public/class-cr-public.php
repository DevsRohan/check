<?php
/**
 * Public-facing functionality.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Public {

    /**
     * Initialize public hooks.
     */
    public function init() {
        // Only on checkout pages
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_consent_field' ) );
        add_action( 'woocommerce_before_checkout_form', array( $this, 'render_welcome_back' ) );

        // Initialize cart tracker
        $tracker = new CR_Cart_Tracker();
        $tracker->init();
    }

    /**
     * Enqueue public assets.
     */
    public function enqueue_assets() {
        if ( ! is_checkout() && ! is_cart() ) {
            return;
        }

        $enabled = Checkout_Rescuer::get_setting( 'enabled', 'yes' );
        if ( 'yes' !== $enabled ) {
            return;
        }

        wp_enqueue_style(
            'cr-public',
            CR_PLUGIN_URL . 'public/css/cr-public.css',
            array(),
            CR_VERSION
        );

        wp_enqueue_script(
            'cr-public',
            CR_PLUGIN_URL . 'public/js/cr-public.js',
            array( 'jquery' ),
            CR_VERSION,
            true
        );

        wp_localize_script( 'cr-public', 'crPublic', array(
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'cr_public_nonce' ),
            'heartbeatInterval' => 30000, // 30 seconds
            'requireConsent' => Checkout_Rescuer::get_setting( 'require_consent', 'yes' ),
            'isCheckout'     => is_checkout(),
        ) );
    }

    /**
     * Render consent checkbox on checkout.
     */
    public function render_consent_field() {
        $enabled = Checkout_Rescuer::get_setting( 'enabled', 'yes' );
        if ( 'yes' !== $enabled ) {
            return;
        }

        $require_consent = Checkout_Rescuer::get_setting( 'require_consent', 'yes' );
        if ( 'yes' !== $require_consent ) {
            return;
        }

        $consent_text = Checkout_Rescuer::get_setting( 'consent_text', 'I agree to receive order-related messages via WhatsApp/SMS' );
        ?>
        <div class="cr-consent-field" id="cr-consent-wrapper">
            <label class="cr-consent-label">
                <input type="checkbox" id="cr-consent-checkbox" name="cr_consent" value="1">
                <span class="cr-consent-text"><?php echo esc_html( $consent_text ); ?></span>
            </label>
        </div>
        <?php
    }

    /**
     * Show welcome back message for recovered carts.
     */
    public function render_welcome_back() {
        if ( ! is_checkout() ) {
            return;
        }

        $session_id = '';
        if ( is_user_logged_in() ) {
            $session_id = 'user_' . get_current_user_id();
        } elseif ( ! empty( $_COOKIE['cr_session_id'] ) ) {
            $session_id = sanitize_text_field( wp_unslash( $_COOKIE['cr_session_id'] ) );
        }

        if ( empty( $session_id ) ) {
            return;
        }

        $welcome_back = get_transient( 'cr_welcome_back_' . $session_id );
        if ( ! $welcome_back ) {
            return;
        }

        // Delete transient so it only shows once
        delete_transient( 'cr_welcome_back_' . $session_id );
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
