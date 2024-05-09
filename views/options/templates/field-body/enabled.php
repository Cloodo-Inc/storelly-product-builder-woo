<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_enabled">'; ?>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <div><label><b><?php esc_html_e('Enabled', 'pc-product-builder'); ?></b> <nbd-tip data-tip="<?php esc_html_e('Choose whether the option is enabled or not.', 'pc-product-builder'); ?>"></nbd-tip></label></div>
    </div>
    <div class="pcpb-field-info-2">
        <div>
            <select name="options[fields][{{fieldIndex}}][general][enabled]" ng-model="field.general.enabled.value">
                <option ng-repeat="op in field.general.enabled.options" value="{{op.key}}">{{op.text}}</option>
            </select>
        </div>
    </div>
</div>
<?php echo '</script>';
