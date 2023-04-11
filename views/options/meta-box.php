<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="printcart-wraper">
    <?php wp_nonce_field('pc_box', 'pc_box_nonce'); ?>
    <div style="overflow: hidden;">
        <div class="printcart_options_panel" id="printcart-options">
            <p class="printcart-form-field">
                <label for="_printcart_pb_enable"><?php _e('Enable Product builder', 'pc-product-builder'); ?></label>
                <span class="printcart-option-val">
                    <input type="hidden" value="0" name="_printcart_pb_enable" />
                    <input type="checkbox" value="1" name="_printcart_pb_enable" id="_printcart_pb_enable" <?php checked($nbdpb_enable); ?> class="short" />
                </span>
            </p>
            <p class="printcart-form-field">
                <label>
                    <a href="<?php echo $link_edit_option; ?>" target="_blank" class="button">
                        <?php if ($option_id != 0) {
                            _e('Edit option', 'pc-product-builder');
                        } else {
                            _e('Create option', 'pc-product-builder');
                        }; ?>
                    </a>
                </label>
            </p>
        </div>
        <div class="clear"></div>
    </div>
</div>