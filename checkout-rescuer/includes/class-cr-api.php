<?php
/**
 * API Client - communicates with Node.js backend on Hugging Face
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CR_API {

    /**
     * Make a GET request to the backend.
     */
    public static function get( $endpoint, $params = array() ) {
        $url = self::build_url( $endpoint );
        if ( ! empty( $params ) ) {
            $url = add_query_arg( $params, $url );
        }

        $response = wp_remote_get( $url, array(
            'headers' => self::get_headers(),
            'timeout' => 30,
        ) );

        return self::handle_response( $response );
    }

    /**
     * Make a POST request to the backend.
     */
    public static function post( $endpoint, $body = array() ) {
        $url = self::build_url( $endpoint );

        $response = wp_remote_post( $url, array(
            'headers' => self::get_headers(),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        return self::handle_response( $response );
    }

    /**
     * Make a DELETE request to the backend.
     */
    public static function delete( $endpoint ) {
        $url = self::build_url( $endpoint );

        $response = wp_remote_request( $url, array(
            'method'  => 'DELETE',
            'headers' => self::get_headers(),
            'timeout' => 30,
        ) );

        return self::handle_response( $response );
    }

    /**
     * Build full URL.
     */
    private static function build_url( $endpoint ) {
        $base = rtrim( Checkout_Rescuer::get_setting( 'backend_url', '' ), '/' );
        return $base . '/api/' . ltrim( $endpoint, '/' );
    }

    /**
     * Get request headers.
     */
    private static function get_headers() {
        return array(
            'Content-Type' => 'application/json',
            'X-Api-Key'    => Checkout_Rescuer::get_setting( 'api_key', '' ),
        );
    }

    /**
     * Handle API response.
     */
    private static function handle_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'error'   => $response->get_error_message(),
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 && $body ) {
            return $body;
        }

        return array(
            'success' => false,
            'error'   => isset( $body['error'] ) ? $body['error'] : 'API request failed (HTTP ' . $code . ')',
        );
    }

    /**
     * Check if backend is configured and reachable.
     */
    public static function is_connected() {
        $backend_url = Checkout_Rescuer::get_setting( 'backend_url', '' );
        if ( empty( $backend_url ) ) {
            return false;
        }

        $result = self::get( 'health' );
        return isset( $result['status'] ) && 'ok' === $result['status'];
    }
}
