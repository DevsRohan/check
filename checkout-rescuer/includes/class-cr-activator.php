<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CR_Activator {

    public static function activate() {
        $defaults = array(
            'backend_url'     => '',
            'api_key'         => '',
            'enabled'         => 'yes',
            'require_consent' => 'yes',
            'consent_text'    => 'I agree to receive order updates via WhatsApp',
            'country_code'    => '+91',
        );

        if ( ! get_option( 'cr_settings' ) ) {
            update_option( 'cr_settings', $defaults );
        }

        flush_rewrite_rules();
    }
}
