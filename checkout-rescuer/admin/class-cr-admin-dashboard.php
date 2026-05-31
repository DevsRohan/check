<?php
/**
 * Admin Dashboard helper.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Admin_Dashboard {

    /**
     * Get recent abandoned carts for dashboard widget.
     *
     * @param int $limit Number of carts.
     * @return array Recent carts.
     */
    public static function get_recent_carts( $limit = 5 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cr_abandoned_carts';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, customer_name, customer_email, cart_total, currency, status, messages_sent, abandoned_at
                FROM {$table}
                WHERE status = 'abandoned'
                ORDER BY abandoned_at DESC
                LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Get recent recoveries for dashboard.
     *
     * @param int $limit Number of recoveries.
     * @return array Recent recoveries.
     */
    public static function get_recent_recoveries( $limit = 5 ) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, c.customer_name, c.customer_email
                FROM {$wpdb->prefix}cr_recoveries r
                LEFT JOIN {$wpdb->prefix}cr_abandoned_carts c ON r.cart_id = c.id
                ORDER BY r.recovered_at DESC
                LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Get live stats (real-time, not from analytics table).
     *
     * @return array Live stats.
     */
    public static function get_live_stats() {
        global $wpdb;
        $carts_table = $wpdb->prefix . 'cr_abandoned_carts';

        $active = $wpdb->get_var( "SELECT COUNT(*) FROM {$carts_table} WHERE status = 'active'" );
        $abandoned = $wpdb->get_var( "SELECT COUNT(*) FROM {$carts_table} WHERE status = 'abandoned'" );
        $pending_value = $wpdb->get_var( "SELECT COALESCE(SUM(cart_total), 0) FROM {$carts_table} WHERE status = 'abandoned'" );

        return array(
            'active_carts'    => absint( $active ),
            'abandoned_carts' => absint( $abandoned ),
            'pending_value'   => floatval( $pending_value ),
        );
    }
}
