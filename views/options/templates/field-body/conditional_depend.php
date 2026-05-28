<?php if (!defined('ABSPATH')) exit; ?>
<!-- Conditional Logic — show this field only when another field has a
     matching value. Data model: field.general.conditional_depend is an
     array of { id, operator, val } rules. ALL rules must pass (AND). -->
<?php echo '<script type="text/ng-template" id="field_body_conditional_depend">'; ?>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Conditional logic', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('Show this field only when another field in this option matches a value. All rules below must pass (AND).', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div class="v2-cond-rules" ng-init="field.general.conditional_depend = field.general.conditional_depend || []">
            <div class="v2-cond-empty" ng-show="!field.general.conditional_depend || field.general.conditional_depend.length === 0">
                <span class="v2-cond-empty__icon" aria-hidden="true">∅</span>
                <span><?php esc_html_e('No rules — this field is always shown.', 'storelly-product-builder-for-woocommerce'); ?></span>
            </div>

            <div class="v2-cond-rule"
                 ng-repeat="rule in field.general.conditional_depend track by $index">
                <span class="v2-cond-rule__label"><?php esc_html_e('Show when', 'storelly-product-builder-for-woocommerce'); ?></span>

                <!-- Field picker — list every OTHER field in the option -->
                <select class="v2-select v2-cond-rule__field"
                        ng-model="rule.id"
                        name="options[fields][{{fieldIndex}}][general][conditional_depend][{{$index}}][id]">
                    <option value=""><?php esc_html_e('— pick a field —', 'storelly-product-builder-for-woocommerce'); ?></option>
                    <option ng-repeat="otherField in options.fields"
                            ng-if="otherField.id !== field.id"
                            value="{{otherField.id}}">
                        {{ otherField.general.title.value || '(unnamed)' }}
                    </option>
                </select>

                <!-- Operator -->
                <select class="v2-select v2-cond-rule__op"
                        ng-model="rule.operator"
                        name="options[fields][{{fieldIndex}}][general][conditional_depend][{{$index}}][operator]">
                    <option value="="><?php esc_html_e('equals', 'storelly-product-builder-for-woocommerce'); ?></option>
                    <option value="!"><?php esc_html_e('does not equal', 'storelly-product-builder-for-woocommerce'); ?></option>
                    <option value="i"><?php esc_html_e('contains', 'storelly-product-builder-for-woocommerce'); ?></option>
                    <option value="!i"><?php esc_html_e('does not contain', 'storelly-product-builder-for-woocommerce'); ?></option>
                </select>

                <!-- Value input -->
                <input type="text" class="v2-input v2-cond-rule__val"
                       ng-model="rule.val"
                       name="options[fields][{{fieldIndex}}][general][conditional_depend][{{$index}}][val]"
                       placeholder="<?php esc_attr_e('Value (e.g. Matte, Glossy…)', 'storelly-product-builder-for-woocommerce'); ?>" />

                <button type="button" class="v2-cond-rule__remove"
                        ng-click="field.general.conditional_depend.splice($index, 1)"
                        title="<?php esc_attr_e('Remove rule', 'storelly-product-builder-for-woocommerce'); ?>"
                        aria-label="<?php esc_attr_e('Remove rule', 'storelly-product-builder-for-woocommerce'); ?>">✕</button>
            </div>

            <button type="button" class="v2-cond-add"
                    ng-click="field.general.conditional_depend.push({ id: '', operator: '=', val: '' })">
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                <?php esc_html_e('Add condition rule', 'storelly-product-builder-for-woocommerce'); ?>
            </button>
        </div>
    </div>
</div>
<?php echo '</script>';
