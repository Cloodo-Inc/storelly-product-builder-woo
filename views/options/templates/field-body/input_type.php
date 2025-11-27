<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_input_type">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.input_type)">
    <div class="pcpb-field-info-1">
        <div><label><b><?php esc_html_e('Input type', 'spbwc-product-builder'); ?></b></label></div>
    </div>
    <div class="pcpb-field-info-2">
        <div>
            <select name="options[fields][{{fieldIndex}}][general][input_type]" ng-model="field.general.input_type.value">
                <option ng-repeat="op in field.general.input_type.options" value="{{op.key}}">{{op.text}}</option>
            </select>
        </div>
    </div>
</div>
<?php echo '</script>';
