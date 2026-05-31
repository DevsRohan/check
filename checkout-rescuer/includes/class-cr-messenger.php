<?php
/**
 * Message dispatcher - sends WhatsApp/SMS via Twilio.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Messenger {

    /**
     * Twilio API base URL.
     */
    const TWILIO_API_URL = 'https://api.twilio.com/2010-04-01/Accounts/';

    /**
     * Process and send recovery messages.
     */
    public function process() {
        $enabled = Checkout_Rescuer::get_setting( 'enabled', 'yes' );
        if ( 'yes' !== $enabled ) {
            return;
        }

        $max_messages = absint( Checkout_Rescuer::get_setting( 'max_messages', 2 ) );

        global $wpdb;
        $table = $wpdb->prefix . 'cr_abandoned_carts';
        $now   = current_time( 'timestamp' );

        // Get abandoned carts that need messages
        $carts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE status = 'abandoned'
                AND consent_given = 1
                AND messages_sent < %d
                ORDER BY abandoned_at ASC
                LIMIT 25",
                $max_messages
            )
        );

        if ( empty( $carts ) ) {
            return;
        }

        foreach ( $carts as $cart ) {
            $next_step = $cart->messages_sent + 1;
            $delay_key = 'message_' . $next_step . '_delay';
            $delay     = absint( Checkout_Rescuer::get_setting( $delay_key, 30 ) );

            $abandoned_time = strtotime( $cart->abandoned_at );
            $send_after     = $abandoned_time + ( $delay * MINUTE_IN_SECONDS );

            if ( $now < $send_after ) {
                continue;
            }

            $this->send_message( $cart, $next_step );
        }
    }

    /**
     * Send a recovery message.
     *
     * @param object $cart      Cart data.
     * @param int    $step      Message step number.
     * @return bool Success or failure.
     */
    public function send_message( $cart, $step = 1 ) {
        $phone = CR_Encryption::decrypt( $cart->customer_phone );
        if ( empty( $phone ) ) {
            return false;
        }

        $channel = Checkout_Rescuer::get_setting( 'primary_channel', 'whatsapp' );

        // Generate coupon if needed
        $coupon_code = '';
        $discount_step = absint( Checkout_Rescuer::get_setting( 'discount_message_step', 2 ) );
        if ( $step >= $discount_step && 'yes' === Checkout_Rescuer::get_setting( 'enable_discount', 'yes' ) ) {
            $coupon = new CR_Coupon();
            $coupon_code = $coupon->generate_for_cart( $cart );

            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'cr_abandoned_carts',
                array( 'coupon_code' => $coupon_code ),
                array( 'id' => $cart->id ),
                array( '%s' ),
                array( '%d' )
            );
        }

        // Build message content
        $message = $this->build_message( $cart, $step, $coupon_code );

        // Attempt to send
        $result = $this->dispatch( $phone, $message, $channel );

        // If WhatsApp fails, fallback to SMS
        if ( ! $result['success'] && 'whatsapp' === $channel ) {
            $fallback = Checkout_Rescuer::get_setting( 'fallback_to_sms', 'yes' );
            if ( 'yes' === $fallback ) {
                $channel = 'sms';
                $result  = $this->dispatch( $phone, $message, $channel );
            }
        }

        // Log the message
        $this->log_message( $cart, $phone, $message, $channel, $step, $result );

        // Update cart messages_sent count
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}cr_abandoned_carts SET messages_sent = messages_sent + 1, updated_at = %s WHERE id = %d",
                current_time( 'mysql' ),
                $cart->id
            )
        );

        // Update analytics
        $this->update_analytics_sent();

        return $result['success'];
    }

    /**
     * Dispatch message via Twilio.
     *
     * @param string $phone   Phone number.
     * @param string $message Message content.
     * @param string $channel Channel (whatsapp or sms).
     * @return array Result with success and message_sid.
     */
    private function dispatch( $phone, $message, $channel ) {
        $sid   = get_option( 'cr_twilio_sid', '' );
        $token = get_option( 'cr_twilio_token', '' );

        if ( empty( $sid ) || empty( $token ) ) {
            return array(
                'success'     => false,
                'message_sid' => '',
                'error'       => 'Twilio credentials not configured.',
            );
        }

        // Decrypt stored credentials
        $sid   = CR_Encryption::decrypt( $sid );
        $token = CR_Encryption::decrypt( $token );

        if ( empty( $sid ) || empty( $token ) ) {
            return array(
                'success'     => false,
                'message_sid' => '',
                'error'       => 'Failed to decrypt Twilio credentials.',
            );
        }

        $from = '';
        if ( 'whatsapp' === $channel ) {
            $whatsapp_number = get_option( 'cr_whatsapp_number', '' );
            $from = 'whatsapp:' . CR_Encryption::decrypt( $whatsapp_number );
            $phone = 'whatsapp:' . $phone;
        } else {
            $from = CR_Encryption::decrypt( get_option( 'cr_twilio_phone', '' ) );
        }

        if ( empty( $from ) ) {
            return array(
                'success'     => false,
                'message_sid' => '',
                'error'       => 'Sender number not configured.',
            );
        }

        $url = self::TWILIO_API_URL . $sid . '/Messages.json';

        $body = array(
            'To'   => $phone,
            'From' => $from,
            'Body' => $message,
        );

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body'    => $body,
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success'     => false,
                'message_sid' => '',
                'error'       => $response->get_error_message(),
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $response_code >= 200 && $response_code < 300 ) {
            return array(
                'success'     => true,
                'message_sid' => isset( $response_body['sid'] ) ? $response_body['sid'] : '',
                'error'       => '',
            );
        }

        $error_msg = isset( $response_body['message'] ) ? $response_body['message'] : 'Unknown Twilio error.';

        return array(
            'success'     => false,
            'message_sid' => '',
            'error'       => $error_msg,
        );
    }

    /**
     * Build message from template.
     *
     * @param object $cart        Cart data.
     * @param int    $step        Message step.
     * @param string $coupon_code Coupon code (if any).
     * @return string Formatted message.
     */
    private function build_message( $cart, $step, $coupon_code = '' ) {
        $template_key = 'message_template_' . $step;
        $template     = Checkout_Rescuer::get_setting( $template_key, '' );

        if ( empty( $template ) ) {
            $template = Checkout_Rescuer::get_setting( 'message_template_1', 'Your cart is waiting! {{recovery_link}}' );
        }

        $cart_items = json_decode( $cart->cart_contents, true );
        $items_text = '';
        if ( is_array( $cart_items ) ) {
            foreach ( $cart_items as $item ) {
                $items_text .= '- ' . $item['name'] . ' x' . $item['quantity'] . "\n";
            }
        }

        $recovery_link = home_url( '?cr_recover=' . $cart->recovery_token );

        $discount_text = '';
        if ( 'yes' === Checkout_Rescuer::get_setting( 'enable_discount', 'yes' ) && $step >= absint( Checkout_Rescuer::get_setting( 'discount_message_step', 2 ) ) ) {
            $discount_type   = Checkout_Rescuer::get_setting( 'discount_type', 'percent' );
            $discount_amount = Checkout_Rescuer::get_setting( 'discount_amount', 10 );
            $discount_text   = ( 'percent' === $discount_type ) ? $discount_amount . '%' : wc_price( $discount_amount );
        }

        $store_name = get_bloginfo( 'name' );

        $replacements = array(
            '{{customer_name}}'  => $cart->customer_name ? $cart->customer_name : 'there',
            '{{store_name}}'     => $store_name,
            '{{cart_total}}'     => wc_price( $cart->cart_total ),
            '{{cart_items}}'     => trim( $items_text ),
            '{{recovery_link}}'  => $recovery_link,
            '{{coupon_code}}'    => $coupon_code,
            '{{discount_amount}}' => $discount_text,
            '{{currency}}'       => $cart->currency,
        );

        $message = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

        return $message;
    }

    /**
     * Log a sent message.
     *
     * @param object $cart    Cart data.
     * @param string $phone   Phone number.
     * @param string $message Message content.
     * @param string $channel Channel.
     * @param int    $step    Step number.
     * @param array  $result  Send result.
     */
    private function log_message( $cart, $phone, $message, $channel, $step, $result ) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'cr_messages',
            array(
                'cart_id'         => $cart->id,
                'channel'         => $channel,
                'phone_number'    => CR_Encryption::encrypt( $phone ),
                'message_content' => $message,
                'message_sid'     => $result['message_sid'],
                'status'          => $result['success'] ? 'sent' : 'failed',
                'step_number'     => $step,
                'error_message'   => $result['success'] ? null : $result['error'],
                'sent_at'         => $result['success'] ? current_time( 'mysql' ) : null,
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );
    }

    /**
     * Update daily analytics for messages sent.
     */
    private function update_analytics_sent() {
        global $wpdb;
        $table = $wpdb->prefix . 'cr_analytics';
        $today = current_time( 'Y-m-d' );

        $existing = $wpdb->get_row(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE date = %s", $today )
        );

        if ( $existing ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET messages_sent = messages_sent + 1 WHERE date = %s",
                    $today
                )
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'date'          => $today,
                    'messages_sent' => 1,
                ),
                array( '%s', '%d' )
            );
        }
    }

    /**
     * Send a test message (for onboarding/settings).
     *
     * @param string $phone   Phone number.
     * @param string $channel Channel.
     * @return array Result.
     */
    public function send_test( $phone, $channel = 'whatsapp' ) {
        $message = "This is a test message from Checkout Rescuer on " . get_bloginfo( 'name' ) . ". Your cart recovery is set up correctly!";
        return $this->dispatch( $phone, $message, $channel );
    }
}
