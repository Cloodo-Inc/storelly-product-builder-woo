<?php
    if (!defined('ABSPATH')) exit;
?>
<?php if( count( $designs ) ): ?>
<?php if( isset( $nbdl_edit ) ): ?>
<div data-design-id="<?php echo esc_attr( $nbdl_edit ); ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>" class="nbdl-table-wrapper">
<?php else: ?>
<div>
<?php endif; ?>
    <table class="shop_table shop_table_responsive my_account_orders">
        <thead>
            <tr>
                <th><?php esc_html_e('Preview', 'storelly-product-builder-for-woocommerce'); ?></th>
                <th><?php esc_html_e('Status', 'storelly-product-builder-for-woocommerce'); ?></th>
                <th><?php esc_html_e('Product', 'storelly-product-builder-for-woocommerce'); ?></th>
                <th><?php esc_html_e('Date', 'storelly-product-builder-for-woocommerce'); ?></th>
                <th><?php esc_html_e('Action', 'storelly-product-builder-for-woocommerce'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $designs as $design ) { ?>
                <tr class="order">
                    <td data-title="<?php esc_html_e('Preview', 'storelly-product-builder-for-woocommerce'); ?>">
                        <?php foreach( $design['previews'] as $preview ): ?>
                            <img src="<?php echo esc_url( $preview ); ?>" class="nbd-preview" />
                        <?php endforeach; ?>
                    </td>
                    <td data-title="<?php esc_html_e('Status', 'storelly-product-builder-for-woocommerce'); ?>">
                        <?php
                            if ( $design['status'] == 0 ) {
                                echo '<span class="label label-danger">' . esc_html__( 'Pending', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                            } elseif ( $design['status'] == 1 ) {
                                echo '<span class="label label-warning">' . esc_html__( 'Approved', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                            }
                        ?>
                    </td>
                    <td data-title="<?php esc_html_e('Date', 'storelly-product-builder-for-woocommerce'); ?>">
                        <a href="<?php echo esc_url( get_permalink( $design['product']['product_id'] ) ); ?>"><?php echo esc_html( $design['product']['name'] ); ?></a>
                    </td>
                    <td data-title="<?php esc_html_e('Date', 'storelly-product-builder-for-woocommerce'); ?>"><?php echo esc_html( nbd_format_time( $design['date'] ) ); ?></td>
                    <td data-title="<?php esc_html_e('Action', 'storelly-product-builder-for-woocommerce'); ?>">
                        <?php 
                            $link_edit_design = $design['type'] == 'solid' ? '#' : add_query_arg(array(
                                'product_id'        => $design['product']['product_id'],
                                'nbd_item_key'      => $design['folder'],
                                'current_page'      => $current_page,
                                'task'              => 'edit',
                                'design_type'       => 'template',
                                'rd'                => 'my_store_design'
                            ), getUrlPageNBD('create'));
                        ?>
                        <a class="woocommerce-button button edit <?php echo esc_attr( $design['type'] == 'solid' ? 'nbdl-edit' : '' ); ?>" data-design-id="<?php echo esc_attr( $design['id'] ); ?>" data-product-id="<?php echo esc_attr( $design['product']['product_id'] ); ?>" href="<?php echo esc_url( $link_edit_design ); ?>"><?php esc_html_e('Edit', 'storelly-product-builder-for-woocommerce'); ?></a>
                        <?php 
                            $delete_url = add_query_arg( array(
                                'tab'       => 'design',
                                'id'        => $design['id'],
                                'action'    => 'spbwc_marketplace_delete_design'
                            ), wc_get_endpoint_url( 'my-store', '', wc_get_page_permalink( 'myaccount' ) ));
                            $delete_url = wp_nonce_url( $delete_url, 'spbwc_marketplace_delete_design' );
                        ?>
                        <a class="woocommerce-button button nbdl-delete-design delete" href="<?php echo esc_url( $delete_url ); ?>"><?php esc_html_e('Delete', 'storelly-product-builder-for-woocommerce'); ?></a>
                        <?php 
                            $design_url = add_query_arg(array(
                                'design_id' => nbd_encode_design_id( $design['id'] )
                            ), get_permalink( $design['product']['product_id'] ) );
                        ?>
                        <a class="woocommerce-button button nbdl-delete-design delete" href="<?php echo esc_url( $design_url ); ?>"><?php esc_html_e('View', 'storelly-product-builder-for-woocommerce'); ?></a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    <!-- No inline scripts or styles unless dynamic. -->
    <script>
        jQuery( document ).ready(function(){
            jQuery('.nbdl-delete-design').on('click', function () {
                return confirm('<?php esc_html_e('Are you sure?', 'storelly-product-builder-for-woocommerce'); ?>');
            });
        });
    </script>
</div>
<?php else: ?>
<div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
    <?php esc_html_e( 'No design has been made yet.', 'storelly-product-builder-for-woocommerce' ); ?>
</div>
<?php endif;