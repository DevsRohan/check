<?php
/**
 * Coupon generator for recovery discounts.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Coupon {

    /**
     * Generate a unique coupon for a cart.
     *
     * @param object $cart Cart record.
     * @return string Coupon code.
     */
    public function generate_for_cart( $cart ) {
        $discount_type   = Checkout_Rescuer::get_setting( 'discount_type', 'percent' );
        $discount_amount = floatval( Checkout_Rescuer::get_setting( 'discount_amount', 10 ) );

        // Generate unique code
        $code = 'CR' . strtoupper( wp_generate_password( 6, false ) );

        // Map discount type to WooCommerce
        $wc_discount_type = ( 'percent' === $discount_type ) ? 'percent' : 'fixed_cart';

        // Create WooCommerce coupon
        $coupon = new WC_Coupon();
        $coupon->set_code( $code );
        $coupon->set_discount_type( $wc_discount_type );
        $coupon->set_amount( $discount_amount );
        $coupon->set_individual_use( true );
        $coupon->set_usage_limit( 1 );
        $coupon->set_date_expires( strtotime( '+48 hours' ) );
        $coupon->set_description( sprintf(
            /* translators: %d: Cart ID */
            __( 'Checkout Rescuer recovery coupon for cart #%d', 'checkout-rescuer' ),
            $cart->id
        ) );

        // Restrict to customer email if available
        if ( ! empty( $cart->customer_email ) ) {
            $coupon->set_email_restrictions( array( $cart->customer_email ) );
        }

        // Set minimum spend (10% of cart total to prevent abuse)
        $min_spend = round( $cart->cart_total * 0.5, 2 );
        $coupon->set_minimum_amount( $min_spend );

        // Add meta to identify as CR coupon
        $coupon->add_meta_data( '_cr_generated', 'yes' );
        $coupon->add_meta_data( '_cr_cart_id', $cart->id );

        $coupon->save();

        return $code;
    }

    /**
     * Cleanup expired CR coupons.
     */
    public function cleanup_expired() {
        $args = array(
            'post_type'      => 'shop_coupon',
            'posts_per_page' => 50,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'   => '_cr_generated',
                    'value' => 'yes',
                ),
            ),
            'date_query'     => array(
                array(
                    'before' => '3 days ago',
                ),
            ),
        );

        $coupons = get_posts( $args );

        foreach ( $coupons as $coupon_post ) {
            wp_trash_post( $coupon_post->ID );
        }
    }
}
