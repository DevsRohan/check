<?php
/**
 * Plugin activator.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Activator {

    /**
     * Database version.
     */
    const DB_VERSION = '1.0.0';

    /**
     * Activate the plugin.
     */
    public static function activate() {
        self::create_tables();
        self::create_encryption_key();
        self::set_default_settings();
        self::schedule_cron_events();
        update_option( 'cr_db_version', self::DB_VERSION );
        flush_rewrite_rules();
    }

    /**
     * Create database tables.
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        // Abandoned carts table
        $sql[] = "CREATE TABLE {$wpdb->prefix}cr_abandoned_carts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(100) NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT 0,
            customer_name VARCHAR(200) DEFAULT '',
            customer_email VARCHAR(200) DEFAULT '',
            customer_phone VARCHAR(255) NOT NULL,
            cart_contents LONGTEXT NOT NULL,
            cart_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(10) NOT NULL DEFAULT 'USD',
            recovery_token VARCHAR(64) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            messages_sent TINYINT NOT NULL DEFAULT 0,
            last_activity DATETIME NOT NULL,
            abandoned_at DATETIME DEFAULT NULL,
            recovered_at DATETIME DEFAULT NULL,
            coupon_code VARCHAR(50) DEFAULT NULL,
            utm_source VARCHAR(100) DEFAULT '',
            consent_given TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_recovery_token (recovery_token),
            KEY idx_status (status),
            KEY idx_last_activity (last_activity),
            KEY idx_session (session_id),
            KEY idx_abandoned_at (abandoned_at)
        ) $charset_collate;";

        // Messages table
        $sql[] = "CREATE TABLE {$wpdb->prefix}cr_messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            cart_id BIGINT UNSIGNED NOT NULL,
            channel VARCHAR(20) NOT NULL DEFAULT 'whatsapp',
            phone_number VARCHAR(255) NOT NULL,
            message_content TEXT NOT NULL,
            message_sid VARCHAR(100) DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'queued',
            step_number TINYINT NOT NULL DEFAULT 1,
            error_message TEXT DEFAULT NULL,
            sent_at DATETIME DEFAULT NULL,
            delivered_at DATETIME DEFAULT NULL,
            read_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_cart_id (cart_id),
            KEY idx_status (status),
            KEY idx_sent_at (sent_at)
        ) $charset_collate;";

        // Recoveries table
        $sql[] = "CREATE TABLE {$wpdb->prefix}cr_recoveries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            cart_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            recovered_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(10) NOT NULL DEFAULT 'USD',
            channel VARCHAR(20) NOT NULL DEFAULT 'whatsapp',
            coupon_used VARCHAR(50) DEFAULT NULL,
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            recovered_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_cart_id (cart_id),
            KEY idx_order_id (order_id),
            KEY idx_recovered_at (recovered_at)
        ) $charset_collate;";

        // Analytics table
        $sql[] = "CREATE TABLE {$wpdb->prefix}cr_analytics (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            date DATE NOT NULL,
            carts_abandoned INT NOT NULL DEFAULT 0,
            messages_sent INT NOT NULL DEFAULT 0,
            messages_delivered INT NOT NULL DEFAULT 0,
            carts_recovered INT NOT NULL DEFAULT 0,
            revenue_recovered DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            revenue_lost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            recovery_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            UNIQUE KEY idx_date (date)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ( $sql as $query ) {
            dbDelta( $query );
        }
    }

    /**
     * Create encryption key for phone number storage.
     */
    private static function create_encryption_key() {
        if ( ! get_option( 'cr_encryption_key' ) ) {
            $key = wp_generate_password( 64, true, true );
            update_option( 'cr_encryption_key', $key );
        }
    }

    /**
     * Set default plugin settings.
     */
    private static function set_default_settings() {
        $defaults = array(
            'enabled'                  => 'yes',
            'abandonment_timeout'      => 30, // minutes
            'primary_channel'          => 'whatsapp',
            'fallback_to_sms'          => 'yes',
            'max_messages'             => 2,
            'message_1_delay'          => 30, // minutes after abandonment
            'message_2_delay'          => 1440, // minutes (24 hours)
            'enable_discount'          => 'yes',
            'discount_type'            => 'percent',
            'discount_amount'          => 10,
            'discount_message_step'    => 2, // Send discount on 2nd message
            'message_template_1'       => "Hey {{customer_name}}! 👋\n\nYou left some items in your cart at {{store_name}}.\n\n🛒 Your cart ({{cart_total}}):\n{{cart_items}}\n\n👉 Complete your order: {{recovery_link}}\n\nNeed help? Just reply to this message!",
            'message_template_2'       => "Hi {{customer_name}}! 🎁\n\nYour cart is still waiting! As a special offer, use code {{coupon_code}} for {{discount_amount}} off.\n\n🛒 Cart value: {{cart_total}}\n👉 Grab your items: {{recovery_link}}\n\nOffer expires in 24 hours! ⏰",
            'phone_field_label'        => 'Phone (for order updates)',
            'consent_text'             => 'I agree to receive order-related messages via WhatsApp/SMS',
            'require_consent'          => 'yes',
            'data_retention_days'      => 90,
            'track_guest_carts'        => 'yes',
            'country_code'             => '+1',
        );

        $existing = get_option( 'cr_settings', array() );
        if ( empty( $existing ) ) {
            update_option( 'cr_settings', $defaults );
        }
    }

    /**
     * Schedule cron events.
     */
    private static function schedule_cron_events() {
        if ( ! wp_next_scheduled( 'cr_process_abandoned_carts' ) ) {
            wp_schedule_event( time(), 'cr_every_fifteen_minutes', 'cr_process_abandoned_carts' );
        }
        if ( ! wp_next_scheduled( 'cr_send_recovery_messages' ) ) {
            wp_schedule_event( time(), 'cr_every_five_minutes', 'cr_send_recovery_messages' );
        }
        if ( ! wp_next_scheduled( 'cr_aggregate_analytics' ) ) {
            wp_schedule_event( time(), 'daily', 'cr_aggregate_analytics' );
        }
        if ( ! wp_next_scheduled( 'cr_cleanup_old_data' ) ) {
            wp_schedule_event( time(), 'daily', 'cr_cleanup_old_data' );
        }
    }
}
