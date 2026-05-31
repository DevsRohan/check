<?php
/**
 * Analytics engine.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CR_Analytics {

    /**
     * Get dashboard summary stats.
     *
     * @param string $period Period: 7days, 30days, 90days, all.
     * @return array Stats array.
     */
    public function get_summary( $period = '30days' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cr_analytics';

        $where = $this->get_date_where( $period );

        $row = $wpdb->get_row(
            "SELECT
                COALESCE(SUM(carts_abandoned), 0) as total_abandoned,
                COALESCE(SUM(messages_sent), 0) as total_messages,
                COALESCE(SUM(messages_delivered), 0) as total_delivered,
                COALESCE(SUM(carts_recovered), 0) as total_recovered,
                COALESCE(SUM(revenue_recovered), 0) as total_revenue_recovered,
                COALESCE(SUM(revenue_lost), 0) as total_revenue_lost
            FROM {$table}
            {$where}"
        );

        $recovery_rate = 0;
        if ( $row->total_abandoned > 0 ) {
            $recovery_rate = round( ( $row->total_recovered / $row->total_abandoned ) * 100, 1 );
        }

        return array(
            'abandoned'        => absint( $row->total_abandoned ),
            'messages_sent'    => absint( $row->total_messages ),
            'recovered'        => absint( $row->total_recovered ),
            'revenue_recovered' => floatval( $row->total_revenue_recovered ),
            'revenue_lost'     => floatval( $row->total_revenue_lost ),
            'recovery_rate'    => $recovery_rate,
        );
    }

    /**
     * Get chart data for dashboard.
     *
     * @param string $period Period.
     * @return array Chart data.
     */
    public function get_chart_data( $period = '30days' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cr_analytics';

        $where = $this->get_date_where( $period );

        $results = $wpdb->get_results(
            "SELECT date, carts_abandoned, carts_recovered, revenue_recovered, messages_sent
            FROM {$table}
            {$where}
            ORDER BY date ASC"
        );

        $labels     = array();
        $abandoned  = array();
        $recovered  = array();
        $revenue    = array();
        $messages   = array();

        foreach ( $results as $row ) {
            $labels[]    = gmdate( 'M j', strtotime( $row->date ) );
            $abandoned[] = absint( $row->carts_abandoned );
            $recovered[] = absint( $row->carts_recovered );
            $revenue[]   = floatval( $row->revenue_recovered );
            $messages[]  = absint( $row->messages_sent );
        }

        return array(
            'labels'    => $labels,
            'abandoned' => $abandoned,
            'recovered' => $recovered,
            'revenue'   => $revenue,
            'messages'  => $messages,
        );
    }

    /**
     * Get channel breakdown.
     *
     * @param string $period Period.
     * @return array Channel stats.
     */
    public function get_channel_breakdown( $period = '30days' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cr_messages';

        $date_condition = '';
        $days = $this->get_days_from_period( $period );
        if ( $days > 0 ) {
            $date_condition = $wpdb->prepare( "AND sent_at >= DATE_SUB(%s, INTERVAL %d DAY)", current_time( 'mysql' ), $days );
        }

        $results = $wpdb->get_results(
            "SELECT channel, COUNT(*) as total, SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered, SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count
            FROM {$table}
            WHERE status != 'queued'
            {$date_condition}
            GROUP BY channel"
        );

        $data = array(
            'whatsapp' => array( 'sent' => 0, 'delivered' => 0, 'read' => 0 ),
            'sms'      => array( 'sent' => 0, 'delivered' => 0, 'read' => 0 ),
        );

        foreach ( $results as $row ) {
            if ( isset( $data[ $row->channel ] ) ) {
                $data[ $row->channel ] = array(
                    'sent'      => absint( $row->total ),
                    'delivered' => absint( $row->delivered ),
                    'read'      => absint( $row->read_count ),
                );
            }
        }

        return $data;
    }

    /**
     * Aggregate daily analytics (run by cron).
     */
    public function aggregate_daily() {
        global $wpdb;
        $today = current_time( 'Y-m-d' );

        // Recalculate recovery rate for today
        $analytics_table = $wpdb->prefix . 'cr_analytics';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT carts_abandoned, carts_recovered FROM {$analytics_table} WHERE date = %s",
                $today
            )
        );

        if ( $row && $row->carts_abandoned > 0 ) {
            $rate = round( ( $row->carts_recovered / $row->carts_abandoned ) * 100, 2 );
            $wpdb->update(
                $analytics_table,
                array( 'recovery_rate' => $rate ),
                array( 'date' => $today ),
                array( '%f' ),
                array( '%s' )
            );
        }

        // Update delivery status from message log
        $messages_table = $wpdb->prefix . 'cr_messages';
        $delivered_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$messages_table} WHERE DATE(sent_at) = %s AND status IN ('delivered', 'read')",
                $today
            )
        );

        if ( $delivered_count > 0 ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$analytics_table} SET messages_delivered = %d WHERE date = %s",
                    $delivered_count,
                    $today
                )
            );
        }
    }

    /**
     * Get WHERE clause for date filtering.
     *
     * @param string $period Period.
     * @return string SQL WHERE clause.
     */
    private function get_date_where( $period ) {
        $days = $this->get_days_from_period( $period );
        if ( $days <= 0 ) {
            return '';
        }

        global $wpdb;
        return $wpdb->prepare( "WHERE date >= DATE_SUB(%s, INTERVAL %d DAY)", current_time( 'Y-m-d' ), $days );
    }

    /**
     * Get number of days from period string.
     *
     * @param string $period Period string.
     * @return int Days.
     */
    private function get_days_from_period( $period ) {
        switch ( $period ) {
            case '7days':
                return 7;
            case '30days':
                return 30;
            case '90days':
                return 90;
            case 'all':
                return 0;
            default:
                return 30;
        }
    }
}
