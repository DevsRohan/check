<?php
/**
 * Cron job manager.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Cron {

    /**
     * Initialize cron.
     */
    public function init() {
        add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );
        add_action( 'cr_process_abandoned_carts', array( $this, 'process_abandoned_carts' ) );
        add_action( 'cr_send_recovery_messages', array( $this, 'send_recovery_messages' ) );
        add_action( 'cr_aggregate_analytics', array( $this, 'aggregate_analytics' ) );
        add_action( 'cr_cleanup_old_data', array( $this, 'cleanup_old_data' ) );
    }

    /**
     * Add custom cron intervals.
     *
     * @param array $schedules Existing schedules.
     * @return array Modified schedules.
     */
    public function add_cron_intervals( $schedules ) {
        $schedules['cr_every_five_minutes'] = array(
            'interval' => 300,
            'display'  => esc_html__( 'Every 5 Minutes', 'checkout-rescuer' ),
        );
        $schedules['cr_every_fifteen_minutes'] = array(
            'interval' => 900,
            'display'  => esc_html__( 'Every 15 Minutes', 'checkout-rescuer' ),
        );
        return $schedules;
    }

    /**
     * Process abandoned carts cron job.
     */
    public function process_abandoned_carts() {
        $abandonment = new CR_Abandonment();
        $abandonment->process();
    }

    /**
     * Send recovery messages cron job.
     */
    public function send_recovery_messages() {
        $messenger = new CR_Messenger();
        $messenger->process();
    }

    /**
     * Aggregate analytics cron job.
     */
    public function aggregate_analytics() {
        $analytics = new CR_Analytics();
        $analytics->aggregate_daily();
    }

    /**
     * Cleanup old data based on retention setting.
     */
    public function cleanup_old_data() {
        $retention_days = absint( Checkout_Rescuer::get_setting( 'data_retention_days', 90 ) );
        if ( $retention_days < 7 ) {
            $retention_days = 90;
        }

        global $wpdb;
        $cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $retention_days * DAY_IN_SECONDS ) );

        // Delete old converted/recovered carts (keep abandoned for reference)
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}cr_abandoned_carts WHERE status IN ('converted') AND created_at < %s",
                $cutoff
            )
        );

        // Delete old message logs
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}cr_messages WHERE created_at < %s",
                $cutoff
            )
        );
    }
}
