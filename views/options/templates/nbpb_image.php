<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="nbd.nbpb_image">'; ?>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <div><b><?php esc_html_e('Show in view', 'storelly-product-builder-for-woocommerce'); ?></b></div>
    </div>
    <div class="pcpb-field-info-2">
        <div class="nbd-table-wrap">
            <table class="nbd-table" style="text-align: center;">
                <thead>
                    <tr>
                        <th ng-repeat="view in options.views">{{view.name}}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td ng-repeat="view in options.views">
                            <input ng-model="field.general.nbpb_image_configs.views[$index].display" name="options[fields][{{fieldIndex}}][general][nbpb_image_configs][views][{{$index}}][display]" type="checkbox" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php echo '</script>';
