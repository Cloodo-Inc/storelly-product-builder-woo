<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_price_breaks">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.price_breaks)">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Price depend quantity breaks', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('Additional price per quantity-break tier. Leave blank for no surcharge at that tier.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div class="nbd-table-wrap">
            <table class="nbd-table nbpb-cell--center">
                <thead>
                    <tr>
                        <th><?php esc_html_e('From qty', 'storelly-product-builder-for-woocommerce'); ?></th>
                        <th><?php esc_html_e('Additional price', 'storelly-product-builder-for-woocommerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr ng-repeat="brk in options.quantity_breaks">
                        <td>{{ brk.val }}</td>
                        <td>
                            <div class="v2-input-with-prefix">
                                <span class="v2-input-prefix">$</span>
                                <input type="number" step="0.01" class="nbd-short-ip" placeholder="0.00"
                                       ng-model="field.general.price_breaks.value[$index]"
                                       name="options[fields][{{fieldIndex}}][general][price_breaks][{{$index}}]" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php echo '</script>';
