<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_price_type">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.price_type)">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Price type', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('How the additional price is calculated. Fixed = flat amount, Percent = % of base price, Per char = each character costs the price.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div class="v2-segmented v2-segmented--wrap" role="radiogroup" aria-label="<?php esc_attr_e('Price type', 'storelly-product-builder-for-woocommerce'); ?>">
            <button type="button"
                    ng-repeat="op in field.general.price_type.options"
                    ng-if="check_option_depend(fieldIndex, op.depend)"
                    class="v2-segmented__btn"
                    ng-class="{'is-active': field.general.price_type.value === op.key}"
                    ng-click="field.general.price_type.value = op.key">
                {{ op.text }}
            </button>
        </div>
        <select class="v2-hidden-legacy" name="options[fields][{{fieldIndex}}][general][price_type]" ng-model="field.general.price_type.value">
            <option ng-repeat="op in field.general.price_type.options" ng-if="check_option_depend(fieldIndex, op.depend)" value="{{op.key}}">{{op.text}}</option>
        </select>
    </div>
</div>
<?php echo '</script>';
