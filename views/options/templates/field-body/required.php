<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_required">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.required)">
    <div class="pcpb-field-info-1">
        <div><label><b><?php esc_html_e('Required', 'spbwc-product-builder'); ?></b> <nbd-tip data-tip="<?php esc_html_e('Choose whether the option is required or not.', 'spbwc-product-builder'); ?>"></nbd-tip></label></div>
    </div>
    <div class="pcpb-field-info-2">
        <div>
            <select name="options[fields][{{fieldIndex}}][general][required]" ng-model="field.general.required.value">
                <option ng-repeat="op in field.general.required.options" value="{{op.key}}">{{op.text}}</option>
            </select>
        </div>
    </div>
</div>
<?php echo '</script>';
