<?php
/**
 * Dashboard view.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$analytics  = new CR_Analytics();
$summary    = $analytics->get_summary( '30days' );
$chart_data = $analytics->get_chart_data( '30days' );
$live_stats = CR_Admin_Dashboard::get_live_stats();
$recent_carts = CR_Admin_Dashboard::get_recent_carts( 5 );
$recent_recoveries = CR_Admin_Dashboard::get_recent_recoveries( 5 );
$currency = get_woocommerce_currency_symbol();
?>
<div class="cr-app" id="cr-dashboard">
    <div class="cr-header">
        <div class="cr-header-left">
            <h1 class="cr-title"><?php esc_html_e( 'Dashboard', 'checkout-rescuer' ); ?></h1>
            <p class="cr-subtitle"><?php esc_html_e( 'Your cart recovery performance at a glance', 'checkout-rescuer' ); ?></p>
        </div>
        <div class="cr-header-right">
            <select id="cr-period-select" class="cr-select">
                <option value="7days"><?php esc_html_e( 'Last 7 Days', 'checkout-rescuer' ); ?></option>
                <option value="30days" selected><?php esc_html_e( 'Last 30 Days', 'checkout-rescuer' ); ?></option>
                <option value="90days"><?php esc_html_e( 'Last 90 Days', 'checkout-rescuer' ); ?></option>
                <option value="all"><?php esc_html_e( 'All Time', 'checkout-rescuer' ); ?></option>
            </select>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="cr-stats-grid">
        <div class="cr-stat-card cr-stat-revenue">
            <div class="cr-stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div class="cr-stat-content">
                <span class="cr-stat-label"><?php esc_html_e( 'Revenue Recovered', 'checkout-rescuer' ); ?></span>
                <span class="cr-stat-value" id="cr-stat-revenue"><?php echo esc_html( $currency . number_format( $summary['revenue_recovered'], 2 ) ); ?></span>
            </div>
        </div>
        <div class="cr-stat-card cr-stat-rate">
            <div class="cr-stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </div>
            <div class="cr-stat-content">
                <span class="cr-stat-label"><?php esc_html_e( 'Recovery Rate', 'checkout-rescuer' ); ?></span>
                <span class="cr-stat-value" id="cr-stat-rate"><?php echo esc_html( $summary['recovery_rate'] . '%' ); ?></span>
            </div>
        </div>
        <div class="cr-stat-card cr-stat-carts">
            <div class="cr-stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </div>
            <div class="cr-stat-content">
                <span class="cr-stat-label"><?php esc_html_e( 'Carts Recovered', 'checkout-rescuer' ); ?></span>
                <span class="cr-stat-value" id="cr-stat-recovered"><?php echo esc_html( $summary['recovered'] ); ?></span>
            </div>
        </div>
        <div class="cr-stat-card cr-stat-messages">
            <div class="cr-stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
            <div class="cr-stat-content">
                <span class="cr-stat-label"><?php esc_html_e( 'Messages Sent', 'checkout-rescuer' ); ?></span>
                <span class="cr-stat-value" id="cr-stat-messages"><?php echo esc_html( $summary['messages_sent'] ); ?></span>
            </div>
        </div>
    </div>

    <!-- Live Status Bar -->
    <div class="cr-live-bar">
        <div class="cr-live-item">
            <span class="cr-live-dot cr-live-dot-active"></span>
            <span><?php echo esc_html( $live_stats['active_carts'] ); ?> <?php esc_html_e( 'active carts', 'checkout-rescuer' ); ?></span>
        </div>
        <div class="cr-live-item">
            <span class="cr-live-dot cr-live-dot-warning"></span>
            <span><?php echo esc_html( $live_stats['abandoned_carts'] ); ?> <?php esc_html_e( 'awaiting recovery', 'checkout-rescuer' ); ?></span>
        </div>
        <div class="cr-live-item">
            <span class="cr-live-dot cr-live-dot-danger"></span>
            <span><?php echo esc_html( $currency . number_format( $live_stats['pending_value'], 0 ) ); ?> <?php esc_html_e( 'recoverable value', 'checkout-rescuer' ); ?></span>
        </div>
    </div>

    <!-- Chart -->
    <div class="cr-card cr-chart-card">
        <div class="cr-card-header">
            <h3><?php esc_html_e( 'Recovery Trend', 'checkout-rescuer' ); ?></h3>
        </div>
        <div class="cr-card-body">
            <canvas id="cr-recovery-chart" height="280"></canvas>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="cr-two-col">
        <!-- Recent Abandoned Carts -->
        <div class="cr-card">
            <div class="cr-card-header">
                <h3><?php esc_html_e( 'Recent Abandoned Carts', 'checkout-rescuer' ); ?></h3>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=checkout-rescuer-carts' ) ); ?>" class="cr-link"><?php esc_html_e( 'View All', 'checkout-rescuer' ); ?></a>
            </div>
            <div class="cr-card-body">
                <?php if ( empty( $recent_carts ) ) : ?>
                    <div class="cr-empty-state">
                        <p><?php esc_html_e( 'No abandoned carts yet. They will appear here once detected.', 'checkout-rescuer' ); ?></p>
                    </div>
                <?php else : ?>
                    <table class="cr-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Customer', 'checkout-rescuer' ); ?></th>
                                <th><?php esc_html_e( 'Value', 'checkout-rescuer' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'checkout-rescuer' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $recent_carts as $cart ) : ?>
                                <tr>
                                    <td>
                                        <span class="cr-customer-name"><?php echo esc_html( $cart->customer_name ? $cart->customer_name : $cart->customer_email ); ?></span>
                                    </td>
                                    <td><strong><?php echo esc_html( $currency . number_format( $cart->cart_total, 2 ) ); ?></strong></td>
                                    <td>
                                        <span class="cr-badge cr-badge-<?php echo esc_attr( $cart->messages_sent > 0 ? 'sent' : 'pending' ); ?>">
                                            <?php echo $cart->messages_sent > 0 ? esc_html( $cart->messages_sent . ' sent' ) : esc_html__( 'Pending', 'checkout-rescuer' ); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Recoveries -->
        <div class="cr-card">
            <div class="cr-card-header">
                <h3><?php esc_html_e( 'Recent Recoveries', 'checkout-rescuer' ); ?></h3>
            </div>
            <div class="cr-card-body">
                <?php if ( empty( $recent_recoveries ) ) : ?>
                    <div class="cr-empty-state">
                        <p><?php esc_html_e( 'No recoveries yet. Once carts are recovered, they appear here.', 'checkout-rescuer' ); ?></p>
                    </div>
                <?php else : ?>
                    <table class="cr-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Customer', 'checkout-rescuer' ); ?></th>
                                <th><?php esc_html_e( 'Recovered', 'checkout-rescuer' ); ?></th>
                                <th><?php esc_html_e( 'Channel', 'checkout-rescuer' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $recent_recoveries as $recovery ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $recovery->customer_name ? $recovery->customer_name : 'Guest' ); ?></td>
                                    <td><strong class="cr-text-success"><?php echo esc_html( $currency . number_format( $recovery->recovered_amount, 2 ) ); ?></strong></td>
                                    <td>
                                        <span class="cr-badge cr-badge-<?php echo esc_attr( $recovery->channel ); ?>">
                                            <?php echo esc_html( ucfirst( $recovery->channel ) ); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    var crChartData = <?php echo wp_json_encode( $chart_data ); ?>;
</script>
