<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_enabled">'; ?>
<div class="pcpb-field-info pcpb-field-info--toggle">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Enabled', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('Choose whether the option is enabled or not.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <label class="v2-toggle">
            <input type="checkbox"
                   ng-model="field.general.enabled.value"
                   ng-true-value="'y'"
                   ng-false-value="'n'" />
            <span class="v2-toggle__track"></span>
            <span class="v2-toggle__label" ng-bind="field.general.enabled.value === 'y' ? '<?php echo esc_js(__('On', 'storelly-product-builder-for-woocommerce')); ?>' : '<?php echo esc_js(__('Off', 'storelly-product-builder-for-woocommerce')); ?>'"></span>
        </label>
        <select class="v2-hidden-legacy" name="options[fields][{{fieldIndex}}][general][enabled]" ng-model="field.general.enabled.value">
            <option ng-repeat="op in field.general.enabled.options" value="{{op.key}}">{{op.text}}</option>
        </select>
    </div>
</div>
<?php echo '</script>';
