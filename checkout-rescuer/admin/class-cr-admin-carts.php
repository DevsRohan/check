<?php
/**
 * Admin Abandoned Carts page helper.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Admin_Carts {

    /**
     * Get abandoned carts with pagination.
     *
     * @param array $args Query arguments.
     * @return array Results with items and total.
     */
    public static function get_carts( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cr_abandoned_carts';

        $defaults = array(
            'status'   => 'abandoned',
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'abandoned_at',
            'order'    => 'DESC',
            'search'   => '',
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( "1=1" );
        $params = array();

        if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
            $where[]  = "status = %s";
            $params[] = $args['status'];
        }

        if ( ! empty( $args['search'] ) ) {
            $where[]  = "(customer_name LIKE %s OR customer_email LIKE %s)";
            $search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $where_clause = implode( ' AND ', $where );

        // Sanitize orderby
        $allowed_orderby = array( 'id', 'cart_total', 'abandoned_at', 'messages_sent', 'created_at' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'abandoned_at';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $offset = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );

        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        if ( ! empty( $params ) ) {
            $total = $wpdb->get_var( $wpdb->prepare( $count_query, $params ) );
        } else {
            $total = $wpdb->get_var( $count_query );
        }

        // Get items
        $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
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
     * Get cart details with messages.
     *
     * @param int $cart_id Cart ID.
     * @return object|null Cart with messages.
     */
    public static function get_cart_detail( $cart_id ) {
        global $wpdb;

        $cart = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cr_abandoned_carts WHERE id = %d",
                $cart_id
            )
        );

        if ( ! $cart ) {
            return null;
        }

        $cart->messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cr_messages WHERE cart_id = %d ORDER BY created_at DESC",
                $cart_id
            )
        );

        // Decrypt phone for display (masked)
        $phone = CR_Encryption::decrypt( $cart->customer_phone );
        $cart->phone_masked = self::mask_phone( $phone );

        return $cart;
    }

    /**
     * Mask a phone number for display.
     *
     * @param string $phone Phone number.
     * @return string Masked phone.
     */
    private static function mask_phone( $phone ) {
        if ( strlen( $phone ) < 6 ) {
            return '****';
        }
        return substr( $phone, 0, 3 ) . str_repeat( '*', strlen( $phone ) - 5 ) . substr( $phone, -2 );
    }
}
