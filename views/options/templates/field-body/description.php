<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_description">'; ?>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Description', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('Optional helper text shown below the option label on the product page.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div>
            <textarea name="options[fields][{{fieldIndex}}][general][description]" ng-model="field.general.description.value"></textarea>
        </div>
    </div>
</div>
<?php echo '</script>';
