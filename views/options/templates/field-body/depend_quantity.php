<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_depend_quantity">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.depend_quantity)">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Depend quantity breaks', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('Set a different additional price for each quantity break tier.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div class="v2-segmented" role="radiogroup" aria-label="<?php esc_attr_e('Depend quantity breaks', 'storelly-product-builder-for-woocommerce'); ?>">
            <button type="button"
                    ng-repeat="op in field.general.depend_quantity.options"
                    class="v2-segmented__btn"
                    ng-class="{'is-active': field.general.depend_quantity.value === op.key}"
                    ng-click="field.general.depend_quantity.value = op.key">
                {{ op.text }}
            </button>
        </div>
        <select class="v2-hidden-legacy" name="options[fields][{{fieldIndex}}][general][depend_quantity]" ng-model="field.general.depend_quantity.value">
            <option ng-repeat="op in field.general.depend_quantity.options" value="{{op.key}}">{{op.text}}</option>
        </select>
    </div>
</div>
<?php echo '</script>';
