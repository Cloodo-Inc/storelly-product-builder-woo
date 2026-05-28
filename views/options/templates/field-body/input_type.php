<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_input_type">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.input_type)">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Input type', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('How the customer provides their value. Text = single-line, Textarea = multi-line, Upload = file picker.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div class="v2-segmented" role="radiogroup" aria-label="<?php esc_attr_e('Input type', 'storelly-product-builder-for-woocommerce'); ?>">
            <button type="button"
                    ng-repeat="op in field.general.input_type.options"
                    class="v2-segmented__btn v2-segmented__btn--with-icon"
                    ng-class="{'is-active': field.general.input_type.value === op.key}"
                    ng-click="field.general.input_type.value = op.key">
                <span class="v2-segmented__icon" ng-switch="op.key">
                    <span ng-switch-when="t" aria-hidden="true">T</span>
                    <span ng-switch-when="n" aria-hidden="true">#</span>
                    <span ng-switch-when="r" aria-hidden="true">⇔</span>
                    <span ng-switch-when="u" aria-hidden="true">⬆</span>
                    <span ng-switch-when="a" aria-hidden="true">¶</span>
                    <span ng-switch-default aria-hidden="true">·</span>
                </span>
                {{ op.text }}
            </button>
        </div>
        <select class="v2-hidden-legacy" name="options[fields][{{fieldIndex}}][general][input_type]" ng-model="field.general.input_type.value">
            <option ng-repeat="op in field.general.input_type.options" value="{{op.key}}">{{op.text}}</option>
        </select>
    </div>
</div>
<?php echo '</script>';
