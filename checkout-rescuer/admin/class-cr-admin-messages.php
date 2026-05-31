<?php
/**
 * Admin Messages page helper.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Admin_Messages {

    /**
     * Get message log with pagination.
     *
     * @param array $args Query arguments.
     * @return array Results with items and total.
     */
    public static function get_messages( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cr_messages';

        $defaults = array(
            'status'   => '',
            'channel'  => '',
            'per_page' => 20,
            'page'     => 1,
        );

        $args = wp_parse_args( $args, $defaults );

        $where  = array( "1=1" );
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $where[]  = "m.status = %s";
            $params[] = $args['status'];
        }

        if ( ! empty( $args['channel'] ) ) {
            $where[]  = "m.channel = %s";
            $params[] = $args['channel'];
        }

        $where_clause = implode( ' AND ', $where );
        $offset = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );

        // Count
        $count_query = "SELECT COUNT(*) FROM {$table} m WHERE {$where_clause}";
        if ( ! empty( $params ) ) {
            $total = $wpdb->get_var( $wpdb->prepare( $count_query, $params ) );
        } else {
            $total = $wpdb->get_var( $count_query );
        }

        // Items with cart info
        $query = "SELECT m.*, c.customer_name, c.customer_email, c.cart_total
                  FROM {$table} m
                  LEFT JOIN {$wpdb->prefix}cr_abandoned_carts c ON m.cart_id = c.id
                  WHERE {$where_clause}
                  ORDER BY m.created_at DESC
                  LIMIT %d OFFSET %d";

        $params[] = absint( $args['per_page'] );
        $params[] = $offset;

        $items = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

        return array(
            'items' => $items,
            'total' => absint( $total ),
            'pages' => ceil( $total / $args['per_page'] ),
        );
    }

    /**
     * Get message stats.
     *
     * @return array Message statistics.
     */
    public static function get_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'cr_messages';

        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count
            FROM {$table}"
        );

        return array(
            'total'     => absint( $stats->total ),
            'sent'      => absint( $stats->sent ),
            'delivered' => absint( $stats->delivered ),
            'failed'    => absint( $stats->failed ),
            'read'      => absint( $stats->read_count ),
        );
    }
}
