<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_published">'; ?>
<div class="pcpb-field-info pcpb-field-info--toggle" ng-show="check_depend(field.general, field.general.published)">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Shown in summary', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('When off, customer choice is still saved but hidden from the cart summary.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <label class="v2-toggle">
            <input type="checkbox"
                   ng-model="field.general.published.value"
                   ng-true-value="'y'"
                   ng-false-value="'n'" />
            <span class="v2-toggle__track"></span>
            <span class="v2-toggle__label" ng-bind="field.general.published.value === 'y' ? '<?php echo esc_js(__('On', 'storelly-product-builder-for-woocommerce')); ?>' : '<?php echo esc_js(__('Off', 'storelly-product-builder-for-woocommerce')); ?>'"></span>
        </label>
        <select class="v2-hidden-legacy" name="options[fields][{{fieldIndex}}][general][published]" ng-model="field.general.published.value">
            <option ng-repeat="op in field.general.published.options" value="{{op.key}}">{{op.text}}</option>
        </select>
    </div>
</div>
<?php echo '</script>';
