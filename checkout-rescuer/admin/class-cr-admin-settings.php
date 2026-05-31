<?php
/**
 * Admin Settings page helper.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Admin_Settings {

    /**
     * Get all settings for display.
     *
     * @return array All settings with defaults.
     */
    public static function get_all_settings() {
        return get_option( 'cr_settings', array() );
    }

    /**
     * Check if Twilio is configured.
     *
     * @return bool True if configured.
     */
    public static function is_twilio_configured() {
        $sid   = get_option( 'cr_twilio_sid', '' );
        $token = get_option( 'cr_twilio_token', '' );
        return ! empty( $sid ) && ! empty( $token );
    }

    /**
     * Get connection status info.
     *
     * @return array Connection status.
     */
    public static function get_connection_status() {
        $configured = self::is_twilio_configured();
        $whatsapp   = ! empty( get_option( 'cr_whatsapp_number', '' ) );
        $sms        = ! empty( get_option( 'cr_twilio_phone', '' ) );

        return array(
            'twilio'   => $configured,
            'whatsapp' => $whatsapp,
            'sms'      => $sms,
        );
    }

    /**
     * Get available template variables.
     *
     * @return array Template variables and descriptions.
     */
    public static function get_template_variables() {
        return array(
            '{{customer_name}}'   => __( 'Customer first name', 'checkout-rescuer' ),
            '{{store_name}}'      => __( 'Your store name', 'checkout-rescuer' ),
            '{{cart_total}}'      => __( 'Cart total with currency', 'checkout-rescuer' ),
            '{{cart_items}}'      => __( 'List of items in cart', 'checkout-rescuer' ),
            '{{recovery_link}}'   => __( 'Cart recovery URL', 'checkout-rescuer' ),
            '{{coupon_code}}'     => __( 'Discount coupon code', 'checkout-rescuer' ),
            '{{discount_amount}}' => __( 'Discount amount/percentage', 'checkout-rescuer' ),
            '{{currency}}'        => __( 'Currency code', 'checkout-rescuer' ),
        );
    }
}
