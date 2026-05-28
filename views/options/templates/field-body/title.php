<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_title">'; ?>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Option name', 'storelly-product-builder-for-woocommerce'); ?></b><span class="v2-required" aria-hidden="true">*</span></label>
        <p class="v2-form-help"><?php esc_html_e('Label shown to the customer above the swatches / input (e.g. "Paper", "Size").', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div>
            <input required type="text" name="options[fields][{{fieldIndex}}][general][title]" ng-model="field.general.title.value" placeholder="<?php esc_attr_e('e.g. Paper, Size, Quantity…', 'storelly-product-builder-for-woocommerce'); ?>" />
            <p class="v2-form-error" ng-show="!field.general.title.value || field.general.title.value === ''">
                <?php esc_html_e('Field name is required.', 'storelly-product-builder-for-woocommerce'); ?>
            </p>
        </div>
    </div>
</div>
<?php echo '</script>';
