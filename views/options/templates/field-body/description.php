<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_description">'; ?>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <div><label><b><?php esc_html_e('Description', 'pc-product-builder'); ?></b></label></div>
    </div>
    <div class="pcpb-field-info-2">
        <div>
            <textarea name="options[fields][{{fieldIndex}}][general][description]" ng-model="field.general.description.value"></textarea>
        </div>
    </div>
</div>
<?php echo '</script>';
