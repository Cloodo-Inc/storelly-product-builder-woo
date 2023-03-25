<?php if (!defined('ABSPATH')) exit; ?>
<?php echo '<script type="text/ng-template" id="field_body_price">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.price)">
    <div class="pcpb-field-info-1">
        <div><label><b><?php esc_html_e('Additional Price', 'pc-product-builder'); ?></b> <nbd-tip data-tip="<?php esc_html_e('Enter the price for this field or leave it blank for no price.', 'pc-product-builder'); ?>"></nbd-tip></label></div>
    </div>
</div>
<?php echo '</script>';
