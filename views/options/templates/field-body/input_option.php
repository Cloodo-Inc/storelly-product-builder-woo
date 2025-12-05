<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_input_option">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.input_option)">
    <div class="pcpb-field-info-1">
        <div><label><b><?php esc_html_e('Input option', 'storelly-product-builder-for-woocommerce'); ?></b></label></div>
    </div>
    <div class="pcpb-field-info-2">
        <div>
            <table class="nbd-table">
                <tr>
                    <th><?php esc_html_e('Min', 'storelly-product-builder-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('Max', 'storelly-product-builder-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('Step', 'storelly-product-builder-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('Default', 'storelly-product-builder-for-woocommerce'); ?></th>
                </tr>
                <tr>
                    <td>
                        <input class="nbd-short-ip" type="text" string-to-number ng-model="field.general.input_option.value.min" name="options[fields][{{fieldIndex}}][general][input_option][min]" />
                    </td>
                    <td>
                        <input class="nbd-short-ip" type="text" string-to-number ng-model="field.general.input_option.value.max" name="options[fields][{{fieldIndex}}][general][input_option][max]" />
                    </td>
                    <td>
                        <input class="nbd-short-ip" type="text" string-to-number ng-model="field.general.input_option.value.step" name="options[fields][{{fieldIndex}}][general][input_option][step]" />
                    </td>
                    <td>
                        <input class="nbd-short-ip" type="text" string-to-number ng-model="field.general.input_option.value.default" name="options[fields][{{fieldIndex}}][general][input_option][default]" />
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
<?php echo '</script>';
