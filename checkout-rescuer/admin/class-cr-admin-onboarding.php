<?php
/**
 * Admin Onboarding wizard helper.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Admin_Onboarding {

    /**
     * Get onboarding steps.
     *
     * @return array Steps configuration.
     */
    public static function get_steps() {
        return array(
            1 => array(
                'title'       => __( 'Welcome to Checkout Rescuer', 'checkout-rescuer' ),
                'description' => __( 'Recover abandoned carts via WhatsApp & SMS. Let\'s get you set up in under 2 minutes.', 'checkout-rescuer' ),
            ),
            2 => array(
                'title'       => __( 'Connect Twilio', 'checkout-rescuer' ),
                'description' => __( 'Enter your Twilio credentials to enable WhatsApp and SMS messaging.', 'checkout-rescuer' ),
            ),
            3 => array(
                'title'       => __( 'Configure Messaging', 'checkout-rescuer' ),
                'description' => __( 'Set your WhatsApp number and SMS number for sending recovery messages.', 'checkout-rescuer' ),
            ),
            4 => array(
                'title'       => __( 'Test & Launch', 'checkout-rescuer' ),
                'description' => __( 'Send a test message and activate your cart recovery.', 'checkout-rescuer' ),
            ),
        );
    }

    /**
     * Get Twilio setup instructions.
     *
     * @return array Instructions.
     */
    public static function get_twilio_instructions() {
        return array(
            __( 'Sign up at twilio.com (free trial available)', 'checkout-rescuer' ),
            __( 'Find your Account SID and Auth Token on the dashboard', 'checkout-rescuer' ),
            __( 'For WhatsApp: Join the Twilio WhatsApp Sandbox or set up a WhatsApp Business number', 'checkout-rescuer' ),
            __( 'For SMS: Purchase a phone number with SMS capability', 'checkout-rescuer' ),
        );
    }
}
