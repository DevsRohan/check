<?php
/**
 * Recovery link handler - restores carts.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Recovery {

    /**
     * Initialize recovery handler.
     */
    public function init() {
        add_action( 'template_redirect', array( $this, 'handle_recovery_link' ) );
    }

    /**
     * Handle recovery link clicks.
     */
    public function handle_recovery_link() {
        if ( ! isset( $_GET['cr_recover'] ) ) {
            return;
        }

        $token = sanitize_text_field( wp_unslash( $_GET['cr_recover'] ) );

        if ( empty( $token ) || strlen( $token ) !== 32 ) {
            wp_safe_redirect( wc_get_cart_url() );
            exit;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cr_abandoned_carts';

        $cart = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE recovery_token = %s AND status = 'abandoned' LIMIT 1",
                $token
            )
        );

        if ( ! $cart ) {
            wp_safe_redirect( wc_get_cart_url() );
            exit;
        }

        // Restore cart items
        $this->restore_cart( $cart );

        // Apply coupon if exists
        if ( ! empty( $cart->coupon_code ) ) {
            WC()->cart->apply_coupon( $cart->coupon_code );
        }

        // Track the click - mark as recovery in progress
        $wpdb->update(
            $table,
            array(
                'status'     => 'recovered',
                'recovered_at' => current_time( 'mysql' ),
                'updated_at'   => current_time( 'mysql' ),
                'utm_source'   => 'recovery_link',
            ),
            array( 'id' => $cart->id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        // Set session cookie to track conversion
        if ( ! is_user_logged_in() ) {
            setcookie( 'cr_session_id', $cart->session_id, time() + DAY_IN_SECONDS * 7, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }

        // Set a transient to show a welcome back message
        set_transient( 'cr_welcome_back_' . $cart->session_id, true, HOUR_IN_SECONDS );

        // Update analytics
        $this->update_analytics_recovered( $cart );

        // Redirect to checkout
        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }

    /**
     * Restore cart items from stored data.
     *
     * @param object $cart Cart record.
     */
    private function restore_cart( $cart ) {
        // Clear existing cart
        WC()->cart->empty_cart();

        $items = json_decode( $cart->cart_contents, true );

        if ( ! is_array( $items ) ) {
            return;
        }

        foreach ( $items as $item ) {
            $product_id   = absint( $item['product_id'] );
            $variation_id = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
            $quantity     = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;

            // Verify product still exists and is purchasable
            $product = wc_get_product( $variation_id ? $variation_id : $product_id );
            if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
                continue;
            }

            WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
        }
    }

    /**
     * Update analytics for recovery.
     *
     * @param object $cart Cart record.
     */
    private function update_analytics_recovered( $cart ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cr_analytics';
        $today = current_time( 'Y-m-d' );

        $existing = $wpdb->get_row(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE date = %s", $today )
        );

        if ( $existing ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET carts_recovered = carts_recovered + 1, revenue_recovered = revenue_recovered + %f WHERE date = %s",
                    $cart->cart_total,
                    $today
                )
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'date'              => $today,
                    'carts_recovered'   => 1,
                    'revenue_recovered' => $cart->cart_total,
                ),
                array( '%s', '%d', '%f' )
            );
        }
    }
}
