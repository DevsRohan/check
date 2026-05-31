<?php
/**
 * Plugin deactivator.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Deactivator {

    /**
     * Deactivate the plugin.
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( 'cr_process_abandoned_carts' );
        wp_clear_scheduled_hook( 'cr_send_recovery_messages' );
        wp_clear_scheduled_hook( 'cr_aggregate_analytics' );
        wp_clear_scheduled_hook( 'cr_cleanup_old_data' );
        flush_rewrite_rules();
    }
}
