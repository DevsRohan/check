<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Checkout_Rescuer {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function run() {
        // Load includes
        require_once CR_PLUGIN_DIR . 'includes/class-cr-api.php';
        require_once CR_PLUGIN_DIR . 'includes/class-cr-tracker.php';
        require_once CR_PLUGIN_DIR . 'includes/class-cr-recovery.php';
        require_once CR_PLUGIN_DIR . 'includes/class-cr-diagnostics.php';

        // Admin
        if ( is_admin() ) {
            require_once CR_PLUGIN_DIR . 'admin/class-cr-admin.php';
            $admin = new CR_Admin();
            $admin->init();
        }

        // Frontend tracker
        $tracker = new CR_Tracker();
        $tracker->init();

        // Recovery handler
        $recovery = new CR_Recovery();
        $recovery->init();

        // Action links
        add_filter( 'plugin_action_links_' . CR_PLUGIN_BASENAME, array( $this, 'action_links' ) );
    }

    public function action_links( $links ) {
        $custom = array(
            '<a href="' . admin_url( 'admin.php?page=checkout-rescuer' ) . '">Dashboard</a>',
            '<a href="' . admin_url( 'admin.php?page=checkout-rescuer-settings' ) . '">Settings</a>',
        );
        return array_merge( $custom, $links );
    }

    public static function get_setting( $key, $default = '' ) {
        $settings = get_option( 'cr_settings', array() );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    public static function update_setting( $key, $value ) {
        $settings = get_option( 'cr_settings', array() );
        $settings[ $key ] = $value;
        update_option( 'cr_settings', $settings );
    }
}
