<?php
/**
 * Abandonment detection engine.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Abandonment {

    /**
     * Process active carts and mark as abandoned.
     */
    public function process() {
        $enabled = Checkout_Rescuer::get_setting( 'enabled', 'yes' );
        if ( 'yes' !== $enabled ) {
            return;
        }

        $timeout = absint( Checkout_Rescuer::get_setting( 'abandonment_timeout', 30 ) );
        if ( $timeout < 5 ) {
            $timeout = 30;
        }

        global $wpdb;
        $table     = $wpdb->prefix . 'cr_abandoned_carts';
        $threshold = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $timeout * MINUTE_IN_SECONDS ) );
        $now       = current_time( 'mysql' );

        // Find active carts that haven't had activity within the timeout
        $carts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, cart_total FROM {$table}
                WHERE status = 'active'
                AND customer_phone != ''
                AND consent_given = 1
                AND last_activity < %s
                AND cart_total > 0
                ORDER BY last_activity ASC
                LIMIT 50",
                $threshold
            )
        );

        if ( empty( $carts ) ) {
            return;
        }

        $cart_ids     = array();
        $total_lost   = 0;

        foreach ( $carts as $cart ) {
            $cart_ids[]  = $cart->id;
            $total_lost += floatval( $cart->cart_total );
        }

        // Batch update status to abandoned
        $ids_placeholder = implode( ',', array_fill( 0, count( $cart_ids ), '%d' ) );
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = 'abandoned', abandoned_at = %s, updated_at = %s WHERE id IN ($ids_placeholder)",
                array_merge( array( $now, $now ), $cart_ids )
            )
        );

        // Update daily analytics
        $this->update_analytics( count( $cart_ids ), $total_lost );
    }

    /**
     * Update daily analytics for abandoned carts.
     *
     * @param int   $count Number of abandoned carts.
     * @param float $total Total value lost.
     */
    private function update_analytics( $count, $total ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cr_analytics';
        $today = current_time( 'Y-m-d' );

        $existing = $wpdb->get_row(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE date = %s", $today )
        );

        if ( $existing ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET carts_abandoned = carts_abandoned + %d, revenue_lost = revenue_lost + %f WHERE date = %s",
                    $count,
                    $total,
                    $today
                )
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'date'            => $today,
                    'carts_abandoned' => $count,
                    'revenue_lost'    => $total,
                ),
                array( '%s', '%d', '%f' )
            );
        }
    }
}
