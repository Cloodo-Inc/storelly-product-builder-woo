<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_placeholder">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.placeholder)">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Placeholder', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('Hint text shown inside the empty input on the product page.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <input type="text" class="v2-input" ng-model="field.general.placeholder.value" name="options[fields][{{fieldIndex}}][general][placeholder]" placeholder="<?php esc_attr_e('e.g. Enter your text here…', 'storelly-product-builder-for-woocommerce'); ?>" />
    </div>
</div>
<?php echo '</script>';
