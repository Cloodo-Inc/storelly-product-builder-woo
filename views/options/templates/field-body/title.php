<?php if (!defined('ABSPATH')) exit; ?>
<?php echo '<script type="text/ng-template" id="field_body_title">'; ?>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <div><label><b><?php esc_html_e('Option name', 'pc-product-builder'); ?></b></label></div>
    </div>
    <div class="pcpb-field-info-2">
        <div>
            <input required type="text" name="options[fields][{{fieldIndex}}][general][title]" ng-model="field.general.title.value">
        </div>
    </div>
</div>
<?php echo '</script>';
