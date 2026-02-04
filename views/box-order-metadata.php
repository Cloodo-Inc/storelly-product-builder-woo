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
                <div class="storelly_order_product_name">
                    <b>
                        <?php esc_html_e('Product:', 'storelly-product-builder-for-woocommerce'); ?>
                    </b>
                    <?php echo esc_html($order_item->get_name()); ?>
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
            </div>
        <?php else : ?>
            <p><?php esc_html_e('No design in this order', 'storelly-product-builder-for-woocommerce'); ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>