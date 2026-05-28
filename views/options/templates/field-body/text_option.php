<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_text_option">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.text_option)">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Character limit', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('Min and max number of characters the customer can type.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div class="v2-mini-grid v2-mini-grid--2">
            <label class="v2-mini-field">
                <span class="v2-mini-field__label"><?php esc_html_e('Min length', 'storelly-product-builder-for-woocommerce'); ?></span>
                <input type="number" string-to-number ng-model="field.general.text_option.value.min" name="options[fields][{{fieldIndex}}][general][text_option][min]" />
            </label>
            <label class="v2-mini-field">
                <span class="v2-mini-field__label"><?php esc_html_e('Max length', 'storelly-product-builder-for-woocommerce'); ?></span>
                <input type="number" string-to-number ng-model="field.general.text_option.value.max" name="options[fields][{{fieldIndex}}][general][text_option][max]" />
            </label>
        </div>
    </div>
</div>
<?php echo '</script>';
