<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_price">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.price)">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Additional Price', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('Enter the price for this field, or leave blank for no surcharge.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div class="v2-input-with-prefix">
            <span class="v2-input-prefix">$</span>
            <input type="number" step="0.01" ng-model="field.general.price.value" name="options[fields][{{fieldIndex}}][general][price]" placeholder="0.00" class="nbd-short-ip" />
        </div>
    </div>
</div>
<?php echo '</script>';
