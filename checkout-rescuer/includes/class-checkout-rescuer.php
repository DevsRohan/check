<?php
/**
 * Main plugin class.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Checkout_Rescuer {

    /**
     * Plugin instance.
     *
     * @var Checkout_Rescuer
     */
    private static $instance = null;

    /**
     * Admin instance.
     *
     * @var CR_Admin
     */
    private $admin;

    /**
     * Public instance.
     *
     * @var CR_Public
     */
    private $public;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->load_dependencies();
    }

    /**
     * Get plugin instance.
     *
     * @return Checkout_Rescuer
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load required files.
     */
    private function load_dependencies() {
        require_once CR_PLUGIN_DIR . 'includes/class-cr-encryption.php';
        require_once CR_PLUGIN_DIR . 'includes/class-cr-cart-tracker.php';
        require_once CR_PLUGIN_DIR . 'includes/class-cr-abandonment.php';
        require_once CR_PLUGIN_DIR . 'includes/class-cr-messenger.php';
        require_once CR_PLUGIN_DIR . 'includes/class-cr-recovery.php';
        require_once CR_PLUGIN_DIR . 'includes/class-cr-analytics.php';
        require_once CR_PLUGIN_DIR . 'includes/class-cr-cron.php';
        require_once CR_PLUGIN_DIR . 'includes/class-cr-coupon.php';

        if ( is_admin() ) {
            require_once CR_PLUGIN_DIR . 'admin/class-cr-admin.php';
            require_once CR_PLUGIN_DIR . 'admin/class-cr-admin-dashboard.php';
            require_once CR_PLUGIN_DIR . 'admin/class-cr-admin-carts.php';
            require_once CR_PLUGIN_DIR . 'admin/class-cr-admin-messages.php';
            require_once CR_PLUGIN_DIR . 'admin/class-cr-admin-settings.php';
            require_once CR_PLUGIN_DIR . 'admin/class-cr-admin-onboarding.php';
        }

        require_once CR_PLUGIN_DIR . 'public/class-cr-public.php';
    }

    /**
     * Run the plugin.
     */
    public function run() {
        // Initialize cron jobs
        $cron = new CR_Cron();
        $cron->init();

        // Initialize recovery handler
        $recovery = new CR_Recovery();
        $recovery->init();

        // Initialize admin
        if ( is_admin() ) {
            $this->admin = new CR_Admin();
            $this->admin->init();
        }

        // Initialize public
        $this->public = new CR_Public();
        $this->public->init();

        // Load text domain
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Add settings link to plugins page
        add_filter( 'plugin_action_links_' . CR_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
    }

    /**
     * Load plugin text domain.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'checkout-rescuer', false, dirname( CR_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Add plugin action links.
     *
     * @param array $links Existing links.
     * @return array Modified links.
     */
    public function add_action_links( $links ) {
        $plugin_links = array(
            '<a href="' . esc_url( admin_url( 'admin.php?page=checkout-rescuer' ) ) . '">' . esc_html__( 'Dashboard', 'checkout-rescuer' ) . '</a>',
            '<a href="' . esc_url( admin_url( 'admin.php?page=checkout-rescuer-settings' ) ) . '">' . esc_html__( 'Settings', 'checkout-rescuer' ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
    }

    /**
     * Get plugin settings.
     *
     * @param string $key     Setting key.
     * @param mixed  $default Default value.
     * @return mixed Setting value.
     */
    public static function get_setting( $key, $default = '' ) {
        $settings = get_option( 'cr_settings', array() );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Update plugin setting.
     *
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     */
    public static function update_setting( $key, $value ) {
        $settings = get_option( 'cr_settings', array() );
        $settings[ $key ] = $value;
        update_option( 'cr_settings', $settings );
    }
}
