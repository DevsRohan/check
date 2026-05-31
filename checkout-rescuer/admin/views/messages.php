<?php
/**
 * Messages log view.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$filter_status = isset( $_GET['msg_status'] ) ? sanitize_text_field( wp_unslash( $_GET['msg_status'] ) ) : '';
$filter_channel = isset( $_GET['channel'] ) ? sanitize_text_field( wp_unslash( $_GET['channel'] ) ) : '';

$results = CR_Admin_Messages::get_messages( array(
    'status'   => $filter_status,
    'channel'  => $filter_channel,
    'page'     => $current_page,
    'per_page' => 20,
) );

$messages    = $results['items'];
$total       = $results['total'];
$total_pages = $results['pages'];
$stats       = CR_Admin_Messages::get_stats();
$currency    = get_woocommerce_currency_symbol();
?>
<div class="cr-app" id="cr-messages">
    <div class="cr-header">
        <div class="cr-header-left">
            <h1 class="cr-title"><?php esc_html_e( 'Message Log', 'checkout-rescuer' ); ?></h1>
            <p class="cr-subtitle"><?php printf( esc_html__( '%d messages total', 'checkout-rescuer' ), $total ); ?></p>
        </div>
    </div>

    <!-- Message Stats -->
    <div class="cr-stats-grid cr-stats-grid-sm">
        <div class="cr-stat-card-sm">
            <span class="cr-stat-sm-value"><?php echo esc_html( $stats['sent'] ); ?></span>
            <span class="cr-stat-sm-label"><?php esc_html_e( 'Sent', 'checkout-rescuer' ); ?></span>
        </div>
        <div class="cr-stat-card-sm">
            <span class="cr-stat-sm-value"><?php echo esc_html( $stats['delivered'] ); ?></span>
            <span class="cr-stat-sm-label"><?php esc_html_e( 'Delivered', 'checkout-rescuer' ); ?></span>
        </div>
        <div class="cr-stat-card-sm">
            <span class="cr-stat-sm-value"><?php echo esc_html( $stats['read'] ); ?></span>
            <span class="cr-stat-sm-label"><?php esc_html_e( 'Read', 'checkout-rescuer' ); ?></span>
        </div>
        <div class="cr-stat-card-sm">
            <span class="cr-stat-sm-value cr-text-danger"><?php echo esc_html( $stats['failed'] ); ?></span>
            <span class="cr-stat-sm-label"><?php esc_html_e( 'Failed', 'checkout-rescuer' ); ?></span>
        </div>
    </div>

    <!-- Filters -->
    <div class="cr-filters">
        <div class="cr-filter-tabs">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=checkout-rescuer-messages' ) ); ?>" class="cr-tab<?php echo empty( $filter_status ) ? ' cr-tab-active' : ''; ?>"><?php esc_html_e( 'All', 'checkout-rescuer' ); ?></a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=checkout-rescuer-messages&msg_status=sent' ) ); ?>" class="cr-tab<?php echo 'sent' === $filter_status ? ' cr-tab-active' : ''; ?>"><?php esc_html_e( 'Sent', 'checkout-rescuer' ); ?></a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=checkout-rescuer-messages&msg_status=delivered' ) ); ?>" class="cr-tab<?php echo 'delivered' === $filter_status ? ' cr-tab-active' : ''; ?>"><?php esc_html_e( 'Delivered', 'checkout-rescuer' ); ?></a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=checkout-rescuer-messages&msg_status=failed' ) ); ?>" class="cr-tab<?php echo 'failed' === $filter_status ? ' cr-tab-active' : ''; ?>"><?php esc_html_e( 'Failed', 'checkout-rescuer' ); ?></a>
        </div>
        <div class="cr-filter-group">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=checkout-rescuer-messages&channel=whatsapp' ) ); ?>" class="cr-btn cr-btn-sm<?php echo 'whatsapp' === $filter_channel ? ' cr-btn-primary' : ' cr-btn-secondary'; ?>"><?php esc_html_e( 'WhatsApp', 'checkout-rescuer' ); ?></a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=checkout-rescuer-messages&channel=sms' ) ); ?>" class="cr-btn cr-btn-sm<?php echo 'sms' === $filter_channel ? ' cr-btn-primary' : ' cr-btn-secondary'; ?>"><?php esc_html_e( 'SMS', 'checkout-rescuer' ); ?></a>
        </div>
    </div>

    <!-- Messages Table -->
    <div class="cr-card">
        <div class="cr-card-body cr-card-body-flush">
            <?php if ( empty( $messages ) ) : ?>
                <div class="cr-empty-state cr-empty-state-large">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <h3><?php esc_html_e( 'No messages yet', 'checkout-rescuer' ); ?></h3>
                    <p><?php esc_html_e( 'Recovery messages will appear here once sent.', 'checkout-rescuer' ); ?></p>
                </div>
            <?php else : ?>
                <table class="cr-table cr-table-full">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Customer', 'checkout-rescuer' ); ?></th>
                            <th><?php esc_html_e( 'Channel', 'checkout-rescuer' ); ?></th>
                            <th><?php esc_html_e( 'Step', 'checkout-rescuer' ); ?></th>
                            <th><?php esc_html_e( 'Cart Value', 'checkout-rescuer' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'checkout-rescuer' ); ?></th>
                            <th><?php esc_html_e( 'Sent', 'checkout-rescuer' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $messages as $msg ) : ?>
                            <tr>
                                <td><?php echo esc_html( $msg->customer_name ? $msg->customer_name : $msg->customer_email ); ?></td>
                                <td>
                                    <span class="cr-badge cr-badge-<?php echo esc_attr( $msg->channel ); ?>">
                                        <?php echo esc_html( ucfirst( $msg->channel ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( '#' . $msg->step_number ); ?></td>
                                <td><?php echo esc_html( $currency . number_format( $msg->cart_total, 2 ) ); ?></td>
                                <td>
                                    <span class="cr-badge cr-badge-msg-<?php echo esc_attr( $msg->status ); ?>">
                                        <?php echo esc_html( ucfirst( $msg->status ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $msg->sent_at ? esc_html( human_time_diff( strtotime( $msg->sent_at ) ) . ' ago' ) : '—'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
        <div class="cr-pagination">
            <?php
            $base_url = admin_url( 'admin.php?page=checkout-rescuer-messages' );
            for ( $i = 1; $i <= $total_pages; $i++ ) :
                $active = ( $i === $current_page ) ? ' cr-page-active' : '';
            ?>
                <a href="<?php echo esc_url( $base_url . '&paged=' . $i ); ?>" class="cr-page-link<?php echo esc_attr( $active ); ?>"><?php echo esc_html( $i ); ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
