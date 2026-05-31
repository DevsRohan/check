<?php
/**
 * Plugin Name: Checkout Rescuer
 * Plugin URI: https://checkoutrescuer.com
 * Description: Recover abandoned WooCommerce carts via WhatsApp & SMS. 95% open rate vs 20% email. 3-5x higher recovery.
 * Version: 1.0.0
 * Author: Checkout Rescuer
 * Author URI: https://checkoutrescuer.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: checkout-rescuer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 8.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin constants
 */
define( 'CR_VERSION', '1.0.0' );
define( 'CR_PLUGIN_FILE', __FILE__ );
define( 'CR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
function cr_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'cr_woocommerce_missing_notice' );
        return false;
    }
    return true;
}

/**
 * WooCommerce missing notice
 */
function cr_woocommerce_missing_notice() {
    $message = sprintf(
        /* translators: %s: WooCommerce plugin link */
        esc_html__( 'Checkout Rescuer requires %s to be installed and active.', 'checkout-rescuer' ),
        '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
    );
    printf( '<div class="notice notice-error"><p>%s</p></div>', wp_kses_post( $message ) );
}

/**
 * Plugin activation
 */
function cr_activate() {
    require_once CR_PLUGIN_DIR . 'includes/class-cr-activator.php';
    CR_Activator::activate();
}
register_activation_hook( __FILE__, 'cr_activate' );

/**
 * Plugin deactivation
 */
function cr_deactivate() {
    require_once CR_PLUGIN_DIR . 'includes/class-cr-deactivator.php';
    CR_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'cr_deactivate' );

/**
 * Initialize plugin
 */
function cr_init() {
    if ( ! cr_check_woocommerce() ) {
        return;
    }

    require_once CR_PLUGIN_DIR . 'includes/class-checkout-rescuer.php';

    $plugin = new Checkout_Rescuer();
    $plugin->run();
}
add_action( 'plugins_loaded', 'cr_init' );

/**
 * Declare WooCommerce HPOS compatibility
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});
