<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_input_option">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.input_option)">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Number range', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('Bounds for the numeric input. Step controls how much the value changes per stepper click.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div class="v2-mini-grid v2-mini-grid--4">
            <label class="v2-mini-field">
                <span class="v2-mini-field__label"><?php esc_html_e('Min', 'storelly-product-builder-for-woocommerce'); ?></span>
                <input type="number" string-to-number ng-model="field.general.input_option.value.min" name="options[fields][{{fieldIndex}}][general][input_option][min]" />
            </label>
            <label class="v2-mini-field">
                <span class="v2-mini-field__label"><?php esc_html_e('Max', 'storelly-product-builder-for-woocommerce'); ?></span>
                <input type="number" string-to-number ng-model="field.general.input_option.value.max" name="options[fields][{{fieldIndex}}][general][input_option][max]" />
            </label>
            <label class="v2-mini-field">
                <span class="v2-mini-field__label"><?php esc_html_e('Step', 'storelly-product-builder-for-woocommerce'); ?></span>
                <input type="number" string-to-number ng-model="field.general.input_option.value.step" name="options[fields][{{fieldIndex}}][general][input_option][step]" />
            </label>
            <label class="v2-mini-field">
                <span class="v2-mini-field__label"><?php esc_html_e('Default', 'storelly-product-builder-for-woocommerce'); ?></span>
                <input type="number" string-to-number ng-model="field.general.input_option.value.default" name="options[fields][{{fieldIndex}}][general][input_option][default]" />
            </label>
        </div>
    </div>
</div>
<?php echo '</script>';
