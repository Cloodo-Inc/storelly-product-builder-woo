<?php
    if (!defined('ABSPATH')) exit;
?>
<div>
    <table class="shop_table shop_table_responsive my_account_orders">
        <thead>
            <tr>
                <th><?php esc_html_e('Amount', 'storelly-product-builder-for-woocommerce'); ?></th>
                <th><?php esc_html_e('Status', 'storelly-product-builder-for-woocommerce'); ?></th>
                <th><?php esc_html_e('Date ', 'storelly-product-builder-for-woocommerce'); ?></th>
                <th><?php esc_html_e('Action ', 'storelly-product-builder-for-woocommerce'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $requests as $request ) { ?>
            <tr class="order">
                <td data-title="<?php esc_html_e('Amount', 'storelly-product-builder-for-woocommerce'); ?>"><?php echo wc_price( $request->amount ); ?></td>
                <td data-title="<?php esc_html_e('Status', 'storelly-product-builder-for-woocommerce'); ?>">
                    <?php
                        if ( $request->status == 0 ) {
                            echo '<span class="label label-danger">' . esc_html__( 'Pending', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                        } elseif ( $request->status == 1 ) {
                            echo '<span class="label label-warning">' . esc_html__( 'Approved', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                        } else {
                            echo '<span class="label label-warning">' . esc_html__( 'Cancelled', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                        }
                    ?>
                </td>
                <td data-title="<?php esc_html_e('Date', 'storelly-product-builder-for-woocommerce'); ?>"><?php echo esc_html( nbd_format_time( $request->date ) ); ?></td>
                <td data-title="<?php esc_html_e('Action', 'storelly-product-builder-for-woocommerce'); ?>">
                    <?php 
                        if( $request->status == 0 ):
                            $cancel_url = add_query_arg( array(
                                'tab'       => 'withdraw',
                                'id'        => $request->id,
                                'action'    => 'spbwc_marketplace_cancel_withdraw'
                            ), wc_get_endpoint_url( 'my-store', '', wc_get_page_permalink( 'myaccount' ) ));
                            $cancel_url = wp_nonce_url( $cancel_url, 'spbwc_marketplace_cancel_withdraw' );
                    ?>
                    <a href="<?php echo esc_url( $cancel_url ); ?>"><?php esc_html_e( 'Cancel', 'storelly-product-builder-for-woocommerce' ); ?></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>