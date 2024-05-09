<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_text_option">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.text_option)">
    <div class="pcpb-field-info-1">
        <div><label><b><?php esc_html_e('Text option', 'pc-product-builder'); ?></b></label></div>
    </div>
    <div class="pcpb-field-info-2">
        <div>
            <table class="nbd-table">
                <tr>
                    <th><?php esc_html_e('Min length', 'pc-product-builder'); ?></th>
                    <th><?php esc_html_e('Max length', 'pc-product-builder'); ?></th>
                </tr>
                <tr>
                    <td>
                        <input class="nbd-short-ip" type="text" string-to-number ng-model="field.general.text_option.value.min" name="options[fields][{{fieldIndex}}][general][text_option][min]" />
                    </td>
                    <td>
                        <input class="nbd-short-ip" type="text" string-to-number ng-model="field.general.text_option.value.max" name="options[fields][{{fieldIndex}}][general][text_option][max]" />
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
<?php echo '</script>';
