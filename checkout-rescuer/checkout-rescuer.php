<?php
/**
 * Plugin Name: Checkout Rescuer
 * Plugin URI: https://checkoutrescuer.com
 * Description: Recover abandoned WooCommerce carts via WhatsApp. 95% open rate vs 20% email. 3-5x higher recovery. No Twilio needed.
 * Version: 2.0.0
 * Author: Checkout Rescuer
 * Author URI: https://checkoutrescuer.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: checkout-rescuer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CR_VERSION', '2.0.0' );
define( 'CR_PLUGIN_FILE', __FILE__ );
define( 'CR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check WooCommerce dependency
 */
function cr_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'cr_woocommerce_missing_notice' );
        return false;
    }
    return true;
}

function cr_woocommerce_missing_notice() {
    printf(
        '<div class="notice notice-error"><p>%s</p></div>',
        wp_kses_post(
            sprintf(
                __( 'Checkout Rescuer requires %s to be installed and active.', 'checkout-rescuer' ),
                '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
            )
        )
    );
}

/**
 * Activation
 */
function cr_activate() {
    require_once CR_PLUGIN_DIR . 'includes/class-cr-activator.php';
    CR_Activator::activate();
}
register_activation_hook( __FILE__, 'cr_activate' );

/**
 * Deactivation
 */
function cr_deactivate() {
    // Nothing to do - backend handles its own state
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
    $plugin = Checkout_Rescuer::get_instance();
    $plugin->run();
}
add_action( 'plugins_loaded', 'cr_init' );

/**
 * HPOS Compatibility
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});
