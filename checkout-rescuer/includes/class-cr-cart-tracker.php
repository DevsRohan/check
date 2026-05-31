<?php
/**
 * Cart tracker - captures and stores cart data.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Cart_Tracker {

    /**
     * Initialize cart tracking.
     */
    public function init() {
        add_action( 'wp_ajax_cr_save_phone', array( $this, 'ajax_save_phone' ) );
        add_action( 'wp_ajax_nopriv_cr_save_phone', array( $this, 'ajax_save_phone' ) );
        add_action( 'wp_ajax_cr_heartbeat', array( $this, 'ajax_heartbeat' ) );
        add_action( 'wp_ajax_nopriv_cr_heartbeat', array( $this, 'ajax_heartbeat' ) );
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'mark_cart_converted' ), 10, 3 );
    }

    /**
     * AJAX: Save phone number and cart data.
     */
    public function ajax_save_phone() {
        check_ajax_referer( 'cr_public_nonce', 'nonce' );

        $phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $consent = isset( $_POST['consent'] ) ? absint( $_POST['consent'] ) : 0;

        if ( empty( $phone ) ) {
            wp_send_json_error( array( 'message' => 'Phone number is required.' ) );
        }

        // Validate phone format
        $phone = $this->sanitize_phone( $phone );
        if ( empty( $phone ) ) {
            wp_send_json_error( array( 'message' => 'Invalid phone number format.' ) );
        }

        // Check consent requirement
        $require_consent = Checkout_Rescuer::get_setting( 'require_consent', 'yes' );
        if ( 'yes' === $require_consent && ! $consent ) {
            wp_send_json_error( array( 'message' => 'Consent is required.' ) );
        }

        $session_id = $this->get_session_id();
        $cart_data  = $this->get_cart_data();

        if ( empty( $cart_data['items'] ) ) {
            wp_send_json_error( array( 'message' => 'Cart is empty.' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cr_abandoned_carts';

        // Check if cart already exists for this session
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status FROM {$table} WHERE session_id = %s AND status IN ('active', 'abandoned') ORDER BY id DESC LIMIT 1",
                $session_id
            )
        );

        $encrypted_phone = CR_Encryption::encrypt( $phone );
        $now = current_time( 'mysql' );

        if ( $existing ) {
            $wpdb->update(
                $table,
                array(
                    'customer_name'  => $name,
                    'customer_email' => $email,
                    'customer_phone' => $encrypted_phone,
                    'cart_contents'  => wp_json_encode( $cart_data['items'] ),
                    'cart_total'     => $cart_data['total'],
                    'currency'       => get_woocommerce_currency(),
                    'last_activity'  => $now,
                    'updated_at'     => $now,
                    'consent_given'  => $consent,
                ),
                array( 'id' => $existing->id ),
                array( '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%d' ),
                array( '%d' )
            );
        } else {
            $recovery_token = wp_generate_password( 32, false );

            $wpdb->insert(
                $table,
                array(
                    'session_id'     => $session_id,
                    'user_id'        => get_current_user_id(),
                    'customer_name'  => $name,
                    'customer_email' => $email,
                    'customer_phone' => $encrypted_phone,
                    'cart_contents'  => wp_json_encode( $cart_data['items'] ),
                    'cart_total'     => $cart_data['total'],
                    'currency'       => get_woocommerce_currency(),
                    'recovery_token' => $recovery_token,
                    'status'         => 'active',
                    'last_activity'  => $now,
                    'consent_given'  => $consent,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ),
                array( '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
            );
        }

        wp_send_json_success( array( 'message' => 'Phone saved.' ) );
    }

    /**
     * AJAX: Cart activity heartbeat.
     */
    public function ajax_heartbeat() {
        check_ajax_referer( 'cr_public_nonce', 'nonce' );

        $session_id = $this->get_session_id();
        $cart_data  = $this->get_cart_data();

        if ( empty( $cart_data['items'] ) ) {
            wp_send_json_success();
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cr_abandoned_carts';
        $now   = current_time( 'mysql' );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET last_activity = %s, cart_contents = %s, cart_total = %f, updated_at = %s WHERE session_id = %s AND status = 'active' ORDER BY id DESC LIMIT 1",
                $now,
                wp_json_encode( $cart_data['items'] ),
                $cart_data['total'],
                $now,
                $session_id
            )
        );

        wp_send_json_success();
    }

    /**
     * Mark cart as converted when order is placed.
     *
     * @param int      $order_id Order ID.
     * @param array    $posted_data Posted data.
     * @param WC_Order $order Order object.
     */
    public function mark_cart_converted( $order_id, $posted_data, $order ) {
        $session_id = $this->get_session_id();

        global $wpdb;
        $table = $wpdb->prefix . 'cr_abandoned_carts';

        $cart = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status, recovery_token FROM {$table} WHERE session_id = %s AND status IN ('active', 'abandoned') ORDER BY id DESC LIMIT 1",
                $session_id
            )
        );

        if ( ! $cart ) {
            return;
        }

        $now = current_time( 'mysql' );

        // Check if this was a recovery
        if ( 'abandoned' === $cart->status ) {
            $wpdb->update(
                $table,
                array(
                    'status'       => 'recovered',
                    'recovered_at' => $now,
                    'updated_at'   => $now,
                ),
                array( 'id' => $cart->id ),
                array( '%s', '%s', '%s' ),
                array( '%d' )
            );

            // Record recovery
            $recovery_table = $wpdb->prefix . 'cr_recoveries';
            $channel = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT channel FROM {$wpdb->prefix}cr_messages WHERE cart_id = %d ORDER BY id DESC LIMIT 1",
                    $cart->id
                )
            );

            $wpdb->insert(
                $recovery_table,
                array(
                    'cart_id'          => $cart->id,
                    'order_id'         => $order_id,
                    'recovered_amount' => $order->get_total(),
                    'currency'         => $order->get_currency(),
                    'channel'          => $channel ? $channel : 'whatsapp',
                    'coupon_used'      => implode( ', ', $order->get_coupon_codes() ),
                    'discount_amount'  => $order->get_discount_total(),
                    'recovered_at'     => $now,
                ),
                array( '%d', '%d', '%f', '%s', '%s', '%s', '%f', '%s' )
            );

            // Add order meta for attribution
            $order->update_meta_data( '_cr_recovered', 'yes' );
            $order->update_meta_data( '_cr_cart_id', $cart->id );
            $order->update_meta_data( '_cr_channel', $channel ? $channel : 'whatsapp' );
            $order->save();
        } else {
            $wpdb->update(
                $table,
                array(
                    'status'     => 'converted',
                    'updated_at' => $now,
                ),
                array( 'id' => $cart->id ),
                array( '%s', '%s' ),
                array( '%d' )
            );
        }
    }

    /**
     * Get or create session ID.
     *
     * @return string Session ID.
     */
    private function get_session_id() {
        if ( is_user_logged_in() ) {
            return 'user_' . get_current_user_id();
        }

        if ( ! empty( $_COOKIE['cr_session_id'] ) ) {
            return sanitize_text_field( wp_unslash( $_COOKIE['cr_session_id'] ) );
        }

        $session_id = 'cr_' . wp_generate_password( 20, false );
        setcookie( 'cr_session_id', $session_id, time() + DAY_IN_SECONDS * 7, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

        return $session_id;
    }

    /**
     * Get current cart data.
     *
     * @return array Cart items and total.
     */
    private function get_cart_data() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return array( 'items' => array(), 'total' => 0 );
        }

        $items = array();
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product = $cart_item['data'];
            $items[] = array(
                'product_id'   => $cart_item['product_id'],
                'variation_id' => isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0,
                'name'         => $product->get_name(),
                'quantity'     => $cart_item['quantity'],
                'price'        => $product->get_price(),
                'image'        => wp_get_attachment_url( $product->get_image_id() ),
            );
        }

        return array(
            'items' => $items,
            'total' => WC()->cart->get_total( 'edit' ),
        );
    }

    /**
     * Sanitize phone number.
     *
     * @param string $phone Raw phone number.
     * @return string Sanitized phone or empty string.
     */
    private function sanitize_phone( $phone ) {
        // Remove all non-digit characters except +
        $phone = preg_replace( '/[^\d+]/', '', $phone );

        // Must be at least 10 digits
        $digits = preg_replace( '/\D/', '', $phone );
        if ( strlen( $digits ) < 10 || strlen( $digits ) > 15 ) {
            return '';
        }

        // Ensure starts with +
        if ( strpos( $phone, '+' ) !== 0 ) {
            $country_code = Checkout_Rescuer::get_setting( 'country_code', '+1' );
            $phone = $country_code . $digits;
        }

        return $phone;
    }
}
