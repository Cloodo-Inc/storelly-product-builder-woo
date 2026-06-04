<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly  
?>
<div id="storelly_order_info">
    <?php if (is_array($order_items)) : ?>
        <?php
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
        $count_img_design = 0;
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
        $src_img = SPBWC_PB_ASSETS_URL . 'images/loading.gif';
        ?>
        <?php foreach ($order_items as $order_item_id => $order_item) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
            $folder_design = wc_get_order_item_meta($order_item_id, '_pcpb_folder', true);
            if ($folder_design) : ?>
                <?php
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
                $spbwc_order_id = is_callable( array( $order_item, 'get_order_id' ) ) ? (int) $order_item->get_order_id() : 0;
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
                $spbwc_pdf_status = wc_get_order_item_meta( $order_item_id, '_pcpb_pdf_status', true );
                ?>
                <div class="storelly_order_product_name">
                    <b>
                        <?php esc_html_e('Product:', 'storelly-product-builder-for-woocommerce'); ?>
                    </b>
                    <?php echo esc_html($order_item->get_name()); ?>
                    <?php if ( 'done' === $spbwc_pdf_status ) : ?>
                        <span style="display:inline-block;margin-left:4px;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:600;background:#dcfce7;color:#14532d;"><?php esc_html_e('PDF ready', 'storelly-product-builder-for-woocommerce'); ?></span>
                    <?php elseif ( 'failed' === $spbwc_pdf_status ) : ?>
                        <span style="display:inline-block;margin-left:4px;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:600;background:#fee2e2;color:#991b1b;"><?php esc_html_e('PDF failed', 'storelly-product-builder-for-woocommerce'); ?></span>
                    <?php elseif ( '' !== (string) $spbwc_pdf_status ) : ?>
                        <span style="display:inline-block;margin-left:4px;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;"><?php echo esc_html( ucfirst( (string) $spbwc_pdf_status ) ); ?></span>
                    <?php endif; ?>
                </div>
                <hr />
                <?php
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
                $list_images = SPBWC_Storelly_IO::spbwc_get_list_images(SPBWC_PB_CUSTOMER_DIR . '/' . $folder_design . '/preview', 1);
                asort($list_images);
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
                $link_view_detail = '';
                if (count($list_images) > 0) : ?>
                    <input type="checkbox" name="_storelly_order_item_id[]" class="storelly_order_item_id" value="<?php echo esc_attr($order_item_id); ?>" />
                    <?php foreach ($list_images as $key => $image) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable. ?>
                        <?php
                        $count_img_design++;
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
                        $src = SPBWC_Storelly_IO::spbwc_convert_path_to_url($image);
                        ?>
                        <img class="storelly_order_image_design" src="<?php echo esc_url($src); ?>" />
                    <?php endforeach; ?>
                    <?php
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
                    $link_view_detail = add_query_arg(array(
                        'nbd_item_key'   => $folder_design,
                    ), SPBWC_Storelly_PB_Util::spbwc_get_url_page('product_builder'));
                    ?>
                    <a class="nbstorelly-right button button-small button-secondary" href="<?php echo esc_url($link_view_detail); ?>"><?php esc_html_e('View detail', 'storelly-product-builder-for-woocommerce'); ?></a>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($count_img_design > 0) : ?>
            <div><input type="checkbox" class="" id="storelly_order_design_check_all" />
                <label for="storelly_order_design_check_all"><small><?php esc_html_e('Check all', 'storelly-product-builder-for-woocommerce'); ?></small></label>
            </div>
            <hr />
            <div>
                <div style="padding-bottom: 4px">
                    <select name="storelly_design_type_download" style="width: 100%">
                        <option value="png"><?php esc_html_e('png', 'storelly-product-builder-for-woocommerce'); ?></option>
                        <option value="png-preview"><?php esc_html_e('png preview', 'storelly-product-builder-for-woocommerce'); ?></option>
                        <option value="svg"><?php esc_html_e('svg', 'storelly-product-builder-for-woocommerce'); ?></option>
                        <option value="pdf"><?php esc_html_e('pdf', 'storelly-product-builder-for-woocommerce'); ?></option>
                        <option value="pdf-preview"><?php esc_html_e('pdf preview', 'storelly-product-builder-for-woocommerce'); ?></option>
                    </select>
                </div>
                <div style="padding-bottom: 4px;">
                    <img src="<?php echo esc_url($src_img); ?>" class="storelly_loaded" id="storelly_order_submit_loading" />
                </div>
                <div style="padding-bottom: 4px">
                    <a href="#" class="button button-primary" id="storelly_download_design_by_type"><?php esc_html_e('Download', 'storelly-product-builder-for-woocommerce'); ?></a>
                </div>
                <?php if ( ! empty( $spbwc_order_id ) && class_exists( 'SPBWC_Order_PDF' ) && SPBWC_Order_PDF::is_enabled() ) : ?>
                    <div style="padding-top:4px;border-top:1px solid #f0f0f1;margin-top:4px;">
                        <a href="<?php echo esc_url( SPBWC_Order_PDF::regenerate_url( $spbwc_order_id ) ); ?>" class="button button-secondary" style="width:100%;text-align:center;">
                            <?php esc_html_e('Regenerate print PDFs', 'storelly-product-builder-for-woocommerce'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <p><?php esc_html_e('No design in this order', 'storelly-product-builder-for-woocommerce'); ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>