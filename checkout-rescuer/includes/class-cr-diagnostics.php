<?php
/**
 * Diagnostics - System Status & Health Check.
 * Runs a full self-test and outputs a copy-paste report for verification.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CR_Diagnostics {

    /**
     * Run all checks and return a structured result.
     *
     * @return array
     */
    public static function run_all() {
        $checks = array();

        $checks[] = self::check_php();
        $checks[] = self::check_woocommerce();
        $checks[] = self::check_checkout_type();
        $checks[] = self::check_settings();
        $checks[] = self::check_consent_field();
        $checks[] = self::check_backend_health();
        $checks[] = self::check_whatsapp();
        $checks[] = self::check_carts();
        $checks[] = self::check_cron();

        return $checks;
    }

    private static function result( $label, $status, $detail ) {
        return array(
            'label'  => $label,
            'status' => $status, // pass | warn | fail
            'detail' => $detail,
        );
    }

    private static function check_php() {
        $ok = version_compare( PHP_VERSION, '7.4', '>=' );
        return self::result(
            'PHP Version',
            $ok ? 'pass' : 'fail',
            'PHP ' . PHP_VERSION . ( $ok ? ' (OK)' : ' (need 7.4+)' )
        );
    }

    private static function check_woocommerce() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return self::result( 'WooCommerce', 'fail', 'WooCommerce is NOT active.' );
        }
        $ver = defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown';
        return self::result( 'WooCommerce', 'pass', 'Active, version ' . $ver );
    }


    private static function check_checkout_type() {
        if ( ! function_exists( 'wc_get_page_id' ) ) {
            return self::result( 'Checkout Type', 'warn', 'Cannot detect (WC not ready).' );
        }
        $checkout_id = wc_get_page_id( 'checkout' );
        if ( $checkout_id <= 0 ) {
            return self::result( 'Checkout Type', 'fail', 'No checkout page set in WooCommerce.' );
        }
        $content    = get_post_field( 'post_content', $checkout_id );
        $is_blocks  = function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $content );
        $type       = $is_blocks ? 'Blocks (React)' : 'Classic (shortcode)';
        return self::result( 'Checkout Type', 'pass', $type . ' detected. Both supported.' );
    }

    private static function check_settings() {
        $backend = Checkout_Rescuer::get_setting( 'backend_url', '' );
        $apikey  = Checkout_Rescuer::get_setting( 'api_key', '' );
        $enabled = Checkout_Rescuer::get_setting( 'enabled', 'yes' );

        $issues = array();
        if ( empty( $backend ) ) $issues[] = 'Backend URL is EMPTY';
        if ( empty( $apikey ) )  $issues[] = 'API Key is EMPTY';
        if ( 'yes' !== $enabled ) $issues[] = 'Plugin is DISABLED';

        if ( $issues ) {
            return self::result( 'Plugin Settings', 'fail', implode( '; ', $issues ) );
        }
        return self::result( 'Plugin Settings', 'pass', 'Backend URL set, API key set, enabled.' );
    }

    private static function check_consent_field() {
        $require = Checkout_Rescuer::get_setting( 'require_consent', 'yes' );
        if ( 'yes' !== $require ) {
            return self::result( 'Consent', 'warn', 'Consent NOT required - tracking all customers (good for testing).' );
        }
        $blocks_api = function_exists( 'woocommerce_register_additional_checkout_field' );
        $detail = $blocks_api
            ? 'Required. Blocks checkout field API available (consent box auto-added).'
            : 'Required. Using classic consent box (WC < 8.2).';
        return self::result( 'Consent', 'pass', $detail );
    }


    private static function check_backend_health() {
        $backend = Checkout_Rescuer::get_setting( 'backend_url', '' );
        if ( empty( $backend ) ) {
            return self::result( 'Backend Health', 'fail', 'Backend URL not configured.' );
        }
        $url = rtrim( $backend, '/' ) . '/api/health';
        $res = wp_remote_get( $url, array( 'timeout' => 20 ) );

        if ( is_wp_error( $res ) ) {
            return self::result( 'Backend Health', 'fail', 'Cannot reach backend: ' . $res->get_error_message() );
        }
        $code = wp_remote_retrieve_response_code( $res );
        $body = json_decode( wp_remote_retrieve_body( $res ), true );

        if ( 200 === $code && isset( $body['status'] ) && 'ok' === $body['status'] ) {
            return self::result( 'Backend Health', 'pass', 'Backend reachable (HTTP 200, status ok).' );
        }
        return self::result( 'Backend Health', 'fail', 'Backend returned HTTP ' . $code . '.' );
    }

    private static function check_whatsapp() {
        $res = CR_API::get( 'whatsapp/status' );
        if ( empty( $res['success'] ) && empty( $res['data'] ) ) {
            $err = isset( $res['error'] ) ? $res['error'] : 'No response';
            return self::result( 'WhatsApp', 'fail', 'Status check failed: ' . $err );
        }
        $data   = isset( $res['data'] ) ? $res['data'] : $res;
        $status = isset( $data['status'] ) ? $data['status'] : 'unknown';

        if ( 'connected' === $status ) {
            $phone = isset( $data['info']['phone'] ) ? $data['info']['phone'] : '';
            return self::result( 'WhatsApp', 'pass', 'CONNECTED' . ( $phone ? ' (' . $phone . ')' : '' ) );
        }
        if ( 'qr_ready' === $status ) {
            return self::result( 'WhatsApp', 'warn', 'QR ready - scan it from WhatsApp tab to connect.' );
        }
        return self::result( 'WhatsApp', 'fail', 'Status: ' . $status . ' - not connected. Scan QR code.' );
    }

    private static function check_carts() {
        $res = CR_API::get( 'carts', array( 'status' => 'all', 'per_page' => 5 ) );
        if ( empty( $res['success'] ) ) {
            $err = isset( $res['error'] ) ? $res['error'] : 'No response';
            return self::result( 'Tracked Carts', 'fail', 'Cannot fetch carts: ' . $err );
        }
        $total = isset( $res['data']['total'] ) ? (int) $res['data']['total'] : 0;
        if ( 0 === $total ) {
            return self::result( 'Tracked Carts', 'warn', '0 carts tracked yet. Add to cart + enter phone on checkout to test.' );
        }
        return self::result( 'Tracked Carts', 'pass', $total . ' cart(s) tracked in backend.' );
    }

    private static function check_cron() {
        // Backend runs its own cron; just inform.
        return self::result( 'Backend Cron', 'pass', 'Abandonment check runs every 5 min, message dispatch every 2 min (on backend).' );
    }


    /**
     * Build a plain-text report (copy-paste friendly for verification).
     *
     * @param array $checks Result of run_all().
     * @return string
     */
    public static function build_report( $checks ) {
        $lines   = array();
        $lines[] = '===== CHECKOUT RESCUER - SYSTEM STATUS REPORT =====';
        $lines[] = 'Generated: ' . current_time( 'mysql' );
        $lines[] = 'Site: ' . home_url();
        $lines[] = 'Plugin Version: ' . ( defined( 'CR_VERSION' ) ? CR_VERSION : 'unknown' );
        $lines[] = str_repeat( '-', 50 );

        foreach ( $checks as $c ) {
            $icon = 'pass' === $c['status'] ? '[PASS]' : ( 'warn' === $c['status'] ? '[WARN]' : '[FAIL]' );
            $lines[] = $icon . ' ' . $c['label'] . ': ' . $c['detail'];
        }

        $lines[] = str_repeat( '-', 50 );

        $fails = array_filter( $checks, function( $c ) { return 'fail' === $c['status']; } );
        if ( empty( $fails ) ) {
            $lines[] = 'RESULT: All critical checks passed. Plugin is ready.';
        } else {
            $lines[] = 'RESULT: ' . count( $fails ) . ' critical issue(s) found - see [FAIL] items above.';
        }
        $lines[] = '===== END REPORT =====';

        return implode( "\n", $lines );
    }
}
