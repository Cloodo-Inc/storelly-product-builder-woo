<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly  
?>
<div id="printcart_order_info">
    <?php if (is_array($order_items)) : ?>
        <?php
        $count_img_design = 0;
        ?>
        <?php foreach ($order_items as $order_item_id => $order_item) : ?>
            <div class="printcart_order_product_name">
                <b>
                    <?php esc_html_e('Product:', 'printcart-integration'); ?>
                </b>
                <?php esc_html_e($order_item->get_name()); ?>
            </div>
            <hr />
            <?php
            $folder_design = wc_get_order_item_meta($order_item_id, '_pcpb_folder', true);
            if ($folder_design) :
                $list_images = Printcart_IO::get_list_images(PRINTCART_PB_CUSTOMER_DIR . '/' . $folder_design . '/preview', 1);
                asort($list_images);
                $link_view_detail = '';
                if (count($list_images) > 0) : ?>
                    <input type="checkbox" name="_printcart_order_item_id[]" class="printcart_order_item_id" value="<?php echo esc_attr($order_item_id); ?>" />
                    <?php foreach ($list_images as $key => $image) : ?>
                        <?php
                        $count_img_design++;
                        $src = Printcart_IO::convert_path_to_url($image);
                        ?>
                        <img class="printcart_order_image_design" src="<?php echo esc_url($src); ?>" />
                    <?php endforeach; ?>
                    <?php
                    $link_view_detail = add_query_arg(array(
                        'nbd_item_key'   => $folder_design,
                    ), Printcart_PB_Util::printcartGetUrlPage('product_builder'));
                    ?>
                    <a class="nbdesigner-right button button-small button-secondary" href="<?php echo esc_url($link_view_detail); ?>"><?php esc_html_e('View detail', 'web-to-print-online-designer'); ?></a>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($count_img_design > 0) : ?>
            <div><input type="checkbox" class="" id="printcart_order_design_check_all" />
                <label for="printcart_order_design_check_all"><small><?php esc_html_e('Check all', 'web-to-print-online-designer'); ?></small></label>
            </div>
            <hr />
            <div>
                <img src="<?php echo PRINTCART_PB_PLUGIN_URL . 'assets/images/loading.gif'; ?>" class="printcart_loaded" id="printcart_order_submit_loading" />
                <div style="text-align: right">
                    <select name="printcart_design_type_download">
                        <option value="png"><?php esc_html_e('png', 'web-to-print-online-designer'); ?></option>
                        <option value="png-preview"><?php esc_html_e('png prev', 'web-to-print-online-designer'); ?></option>
                        <option value="svg"><?php esc_html_e('svg', 'web-to-print-online-designer'); ?></option>
                        <option value="pdf"><?php esc_html_e('pdf', 'web-to-print-online-designer'); ?></option>
                        <option value="pdf-preview"><?php esc_html_e('pdf prev', 'web-to-print-online-designer'); ?></option>
                    </select>
                    <a href="#" class="button button-primary" id="printcart_download_design_by_type"><?php esc_html_e('Download', 'web-to-print-online-designer'); ?></a>
                </div>
            </div>
        <?php else : ?>
            <p><?php esc_html_e('No design in this order', 'web-to-print-online-designer'); ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>