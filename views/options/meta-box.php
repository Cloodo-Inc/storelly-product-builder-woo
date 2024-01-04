<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="storelly-wraper">
    <?php wp_nonce_field('pc_box', 'pc_box_nonce'); ?>
    <div style="overflow: hidden;">
        <div class="storelly_options_panel" id="storelly-options">
            <p class="storelly-form-field">
                <label for="_storelly_pb_enable"><?php _e('Enable Product builder', 'pc-product-builder'); ?></label>
                <span class="storelly-option-val">
                    <input type="hidden" value="0" name="_storelly_pb_enable" />
                    <input type="checkbox" value="1" name="_storelly_pb_enable" id="_storelly_pb_enable" <?php checked($nbdpb_enable); ?> class="short" />
                </span>
            </p>
            <p class="storelly-form-field">
                <label>
                    <a href="<?php echo($link_edit_option); ?>" target="_blank" class="button">
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