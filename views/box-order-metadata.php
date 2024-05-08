<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly  
?>
<div id="storelly_order_info">
    <?php if (is_array($order_items)) : ?>
        <?php
        $count_img_design = 0;
        $src_img = STORELLY_PB_PLUGIN_URL . 'assets/images/loading.gif';
        ?>
        <?php foreach ($order_items as $order_item_id => $order_item) :
            $folder_design = wc_get_order_item_meta($order_item_id, '_pcpb_folder', true);
            if ($folder_design) : ?>
                <div class="storelly_order_product_name">
                    <b>
                        <?php esc_html_e('Product:', 'pc-product-builder'); ?>
                    </b>
                    <?php echo esc_html($order_item->get_name()); ?>
                </div>
                <hr />
                <?php
                $list_images = Storelly_IO::get_list_images(STORELLY_PB_CUSTOMER_DIR . '/' . $folder_design . '/preview', 1);
                asort($list_images);
                $link_view_detail = '';
                if (count($list_images) > 0) : ?>
                    <input type="checkbox" name="_storelly_order_item_id[]" class="storelly_order_item_id" value="<?php echo esc_attr($order_item_id); ?>" />
                    <?php foreach ($list_images as $key => $image) : ?>
                        <?php
                        $count_img_design++;
                        $src = Storelly_IO::convert_path_to_url($image);
                        ?>
                        <img class="storelly_order_image_design" src="<?php echo esc_url($src); ?>" />
                    <?php endforeach; ?>
                    <?php
                    $link_view_detail = add_query_arg(array(
                        'nbd_item_key'   => $folder_design,
                    ), Storelly_PB_Util::storellyGetUrlPage('product_builder'));
                    ?>
                    <a class="nbstorelly-right button button-small button-secondary" href="<?php echo esc_url($link_view_detail); ?>"><?php esc_html_e('View detail', 'pc-product-builder'); ?></a>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($count_img_design > 0) : ?>
            <div><input type="checkbox" class="" id="storelly_order_design_check_all" />
                <label for="storelly_order_design_check_all"><small><?php esc_html_e('Check all', 'pc-product-builder'); ?></small></label>
            </div>
            <hr />
            <div>
                <div style="padding-bottom: 4px">
                    <select name="storelly_design_type_download" style="width: 100%">
                        <option value="png"><?php esc_html_e('png', 'pc-product-builder'); ?></option>
                        <option value="png-preview"><?php esc_html_e('png preview', 'pc-product-builder'); ?></option>
                        <option value="svg"><?php esc_html_e('svg', 'pc-product-builder'); ?></option>
                        <option value="pdf"><?php esc_html_e('pdf', 'pc-product-builder'); ?></option>
                        <option value="pdf-preview"><?php esc_html_e('pdf preview', 'pc-product-builder'); ?></option>
                    </select>
                </div>
                <div style="padding-bottom: 4px;">
                    <img src="<?php echo esc_url($src_img); ?>" class="storelly_loaded" id="storelly_order_submit_loading" />
                </div>
                <div style="padding-bottom: 4px">
                    <a href="#" class="button button-primary" id="storelly_download_design_by_type"><?php esc_html_e('Download', 'pc-product-builder'); ?></a>
                </div>
            </div>
        <?php else : ?>
            <p><?php esc_html_e('No design in this order', 'pc-product-builder'); ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>