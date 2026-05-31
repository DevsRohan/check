<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove plugin options
$options = array(
    'cr_settings',
    'cr_twilio_sid',
    'cr_twilio_token',
    'cr_twilio_phone',
    'cr_whatsapp_number',
    'cr_onboarding_complete',
    'cr_encryption_key',
    'cr_db_version',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove database tables
$tables = array(
    $wpdb->prefix . 'cr_abandoned_carts',
    $wpdb->prefix . 'cr_messages',
    $wpdb->prefix . 'cr_recoveries',
    $wpdb->prefix . 'cr_analytics',
);

foreach ( $tables as $table ) {
    $wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

// Remove scheduled cron events
wp_clear_scheduled_hook( 'cr_process_abandoned_carts' );
wp_clear_scheduled_hook( 'cr_send_recovery_messages' );
wp_clear_scheduled_hook( 'cr_aggregate_analytics' );
wp_clear_scheduled_hook( 'cr_cleanup_old_data' );

// Remove transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cr_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cr_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
