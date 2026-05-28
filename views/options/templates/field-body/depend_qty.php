<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_depend_qty">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.depend_qty)">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Depend quantity', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('How the additional price interacts with the cart quantity.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <select class="v2-select" name="options[fields][{{fieldIndex}}][general][depend_qty]" ng-model="field.general.depend_qty.value">
            <option ng-repeat="op in field.general.depend_qty.options" value="{{op.key}}">{{op.text}}</option>
        </select>
    </div>
</div>
<?php echo '</script>';
