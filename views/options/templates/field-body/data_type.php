<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_data_type">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.data_type)">
    <div class="pcpb-field-info-1">
        <div><label><b><?php esc_html_e('Data type', 'storelly-product-builder-for-woocommerce'); ?></b></label></div>
    </div>
    <div class="pcpb-field-info-2">
        <div>
            <select name="options[fields][{{fieldIndex}}][general][data_type]" ng-model="field.general.data_type.value" ng-change="update_price_type(fieldIndex)">
                <option ng-repeat="op in field.general.data_type.options" value="{{op.key}}">{{op.text}}</option>
            </select>
        </div>
    </div>
</div>
<?php echo '</script>';
