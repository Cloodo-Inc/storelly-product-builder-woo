<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_data_type">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.data_type)">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Data type', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('Multiple options = customer picks one from a list (swatches/dropdown). Custom input = customer types text or uploads a file.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div class="v2-segmented" role="radiogroup" aria-label="<?php esc_attr_e('Data type', 'storelly-product-builder-for-woocommerce'); ?>">
            <button type="button"
                    ng-repeat="op in field.general.data_type.options"
                    class="v2-segmented__btn"
                    ng-class="{'is-active': field.general.data_type.value === op.key}"
                    ng-click="field.general.data_type.value = op.key; update_price_type(fieldIndex)">
                {{ op.text }}
            </button>
        </div>
        <select class="v2-hidden-legacy" name="options[fields][{{fieldIndex}}][general][data_type]" ng-model="field.general.data_type.value" ng-change="update_price_type(fieldIndex)">
            <option ng-repeat="op in field.general.data_type.options" value="{{op.key}}">{{op.text}}</option>
        </select>
    </div>
</div>
<?php echo '</script>';
