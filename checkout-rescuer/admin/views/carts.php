<?php
/**
 * Abandoned Carts view.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'abandoned';
$current_page   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$search         = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

$results = CR_Admin_Carts::get_carts( array(
    'status'   => $current_status,
    'page'     => $current_page,
    'per_page' => 20,
    'search'   => $search,
) );

$carts      = $results['items'];
$total      = $results['total'];
$total_pages = $results['pages'];
$currency   = get_woocommerce_currency_symbol();
?>
<div class="cr-app" id="cr-carts">
    <div class="cr-header">
        <div class="cr-header-left">
            <h1 class="cr-title"><?php esc_html_e( 'Abandoned Carts', 'checkout-rescuer' ); ?></h1>
            <p class="cr-subtitle"><?php printf( esc_html__( '%d carts total', 'checkout-rescuer' ), $total ); ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="cr-filters">
        <div class="cr-filter-tabs">
            <?php
            $statuses = array(
                'abandoned' => __( 'Abandoned', 'checkout-rescuer' ),
                'recovered' => __( 'Recovered', 'checkout-rescuer' ),
                'active'    => __( 'Active', 'checkout-rescuer' ),
                'all'       => __( 'All', 'checkout-rescuer' ),
            );
            foreach ( $statuses as $status => $label ) :
                $url = admin_url( 'admin.php?page=checkout-rescuer-carts&status=' . $status );
                $active = ( $current_status === $status ) ? ' cr-tab-active' : '';
            ?>
                <a href="<?php echo esc_url( $url ); ?>" class="cr-tab<?php echo esc_attr( $active ); ?>"><?php echo esc_html( $label ); ?></a>
            <?php endforeach; ?>
        </div>
        <form method="get" class="cr-search-form">
            <input type="hidden" name="page" value="checkout-rescuer-carts">
            <input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>">
            <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by name or email...', 'checkout-rescuer' ); ?>" class="cr-input cr-search-input">
            <button type="submit" class="cr-btn cr-btn-secondary"><?php esc_html_e( 'Search', 'checkout-rescuer' ); ?></button>
        </form>
    </div>

    <!-- Carts Table -->
    <div class="cr-card">
        <div class="cr-card-body cr-card-body-flush">
            <?php if ( empty( $carts ) ) : ?>
                <div class="cr-empty-state cr-empty-state-large">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <h3><?php esc_html_e( 'No carts found', 'checkout-rescuer' ); ?></h3>
                    <p><?php esc_html_e( 'Abandoned carts will appear here once detected.', 'checkout-rescuer' ); ?></p>
                </div>
            <?php else : ?>
                <table class="cr-table cr-table-full">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Customer', 'checkout-rescuer' ); ?></th>
                            <th><?php esc_html_e( 'Cart Value', 'checkout-rescuer' ); ?></th>
                            <th><?php esc_html_e( 'Items', 'checkout-rescuer' ); ?></th>
                            <th><?php esc_html_e( 'Messages', 'checkout-rescuer' ); ?></th>
                            <th><?php esc_html_e( 'Abandoned', 'checkout-rescuer' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'checkout-rescuer' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'checkout-rescuer' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $carts as $cart ) :
                            $items = json_decode( $cart->cart_contents, true );
                            $item_count = is_array( $items ) ? count( $items ) : 0;
                        ?>
                            <tr data-cart-id="<?php echo esc_attr( $cart->id ); ?>">
                                <td>
                                    <div class="cr-customer">
                                        <strong><?php echo esc_html( $cart->customer_name ? $cart->customer_name : __( 'Guest', 'checkout-rescuer' ) ); ?></strong>
                                        <span class="cr-text-muted"><?php echo esc_html( $cart->customer_email ); ?></span>
                                    </div>
                                </td>
                                <td><strong><?php echo esc_html( $currency . number_format( $cart->cart_total, 2 ) ); ?></strong></td>
                                <td><?php echo esc_html( $item_count . ' ' . _n( 'item', 'items', $item_count, 'checkout-rescuer' ) ); ?></td>
                                <td>
                                    <span class="cr-badge cr-badge-<?php echo $cart->messages_sent > 0 ? 'sent' : 'pending'; ?>">
                                        <?php echo esc_html( $cart->messages_sent ); ?> / <?php echo esc_html( Checkout_Rescuer::get_setting( 'max_messages', 2 ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="cr-text-muted"><?php echo $cart->abandoned_at ? esc_html( human_time_diff( strtotime( $cart->abandoned_at ) ) . ' ago' ) : '—'; ?></span>
                                </td>
                                <td>
                                    <span class="cr-badge cr-badge-status-<?php echo esc_attr( $cart->status ); ?>">
                                        <?php echo esc_html( ucfirst( $cart->status ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="cr-actions">
                                        <?php if ( 'abandoned' === $cart->status ) : ?>
                                            <button class="cr-btn cr-btn-sm cr-btn-primary cr-resend-btn" data-cart-id="<?php echo esc_attr( $cart->id ); ?>" title="<?php esc_attr_e( 'Resend Message', 'checkout-rescuer' ); ?>">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                                            </button>
                                        <?php endif; ?>
                                        <button class="cr-btn cr-btn-sm cr-btn-danger cr-delete-btn" data-cart-id="<?php echo esc_attr( $cart->id ); ?>" title="<?php esc_attr_e( 'Delete', 'checkout-rescuer' ); ?>">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </div>
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
            $base_url = admin_url( 'admin.php?page=checkout-rescuer-carts&status=' . $current_status );
            for ( $i = 1; $i <= $total_pages; $i++ ) :
                $active = ( $i === $current_page ) ? ' cr-page-active' : '';
            ?>
                <a href="<?php echo esc_url( $base_url . '&paged=' . $i ); ?>" class="cr-page-link<?php echo esc_attr( $active ); ?>"><?php echo esc_html( $i ); ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
