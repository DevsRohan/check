<?php
/**
 * Encryption handler for sensitive data.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Encryption {

    private static $cipher = 'aes-256-cbc';

    /**
     * Encrypt a value.
     *
     * @param string $value Value to encrypt.
     * @return string Encrypted value.
     */
    public static function encrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $key = self::get_key();
        $iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::$cipher ) );

        $encrypted = openssl_encrypt( $value, self::$cipher, $key, 0, $iv );

        if ( false === $encrypted ) {
            return '';
        }

        return base64_encode( $iv . '::' . $encrypted );
    }

    /**
     * Decrypt a value.
     *
     * @param string $value Value to decrypt.
     * @return string Decrypted value.
     */
    public static function decrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $key  = self::get_key();
        $data = base64_decode( $value );

        if ( false === $data || strpos( $data, '::' ) === false ) {
            return '';
        }

        list( $iv, $encrypted ) = explode( '::', $data, 2 );

        $decrypted = openssl_decrypt( $encrypted, self::$cipher, $key, 0, $iv );

        return ( false === $decrypted ) ? '' : $decrypted;
    }

    /**
     * Get the encryption key.
     *
     * @return string Encryption key.
     */
    private static function get_key() {
        $key = get_option( 'cr_encryption_key', '' );

        if ( empty( $key ) ) {
            $key = wp_generate_password( 64, true, true );
            update_option( 'cr_encryption_key', $key );
        }

        return hash( 'sha256', $key );
    }
}
