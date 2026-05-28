<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_upload_option">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.upload_option)">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Upload constraints', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('File size limits (MB) and accepted file types — comma-separated extensions, e.g. pdf,ai,png,jpg.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div class="v2-mini-grid v2-mini-grid--3">
            <label class="v2-mini-field">
                <span class="v2-mini-field__label"><?php esc_html_e('Min size (MB)', 'storelly-product-builder-for-woocommerce'); ?></span>
                <input type="number" min="0" string-to-number ng-model="field.general.upload_option.value.min_size" name="options[fields][{{fieldIndex}}][general][upload_option][min_size]" />
            </label>
            <label class="v2-mini-field">
                <span class="v2-mini-field__label"><?php esc_html_e('Max size (MB)', 'storelly-product-builder-for-woocommerce'); ?></span>
                <input type="number" min="0" string-to-number ng-model="field.general.upload_option.value.max_size" name="options[fields][{{fieldIndex}}][general][upload_option][max_size]" />
            </label>
            <label class="v2-mini-field v2-mini-field--wide">
                <span class="v2-mini-field__label"><?php esc_html_e('Allowed extensions', 'storelly-product-builder-for-woocommerce'); ?></span>
                <input type="text" ng-model="field.general.upload_option.value.allow_type" name="options[fields][{{fieldIndex}}][general][upload_option][allow_type]" placeholder="pdf,ai,png,jpg" />
            </label>
        </div>
    </div>
</div>
<?php echo '</script>';
