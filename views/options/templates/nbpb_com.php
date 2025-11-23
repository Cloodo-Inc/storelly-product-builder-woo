<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="nbd.nbpb_com">'; ?>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <div><b><?php esc_html_e('Views', 'pc-product-builder'); ?></b><nbd-tip data-tip="<?php esc_html_e('Add product view/side, example: Front, Back, Top, Inside... and use them for all product components.', 'pc-product-builder'); ?>"></nbd-tip></div>
    </div>
    <div class="pcpb-field-info-2">
        <div class="nbd-table-wrap">
            <table class="nbd-table" style="text-align: center;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('View name', 'pc-product-builder'); ?></th>
                        <th><?php esc_html_e('View base', 'pc-product-builder'); ?></th>
                        <th><?php esc_html_e('Action', 'pc-product-builder'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr ng-repeat="(vIndex, view) in options.views">
                        <td><input style="width: 150px;" ng-model="view.name" type="text" /></td>
                        <td>
                            <div class="image-icon-wrap">
                                <span class="dashicons dashicons-no remove-image-icon" ng-click="remove_view_base(vIndex)"></span>
                                <img ng-click="set_view_base(vIndex)" ng-src="{{view.base != 0 ? view.base_url : '<?php echo esc_url(SPBWC_PB_ASSETS_URL . 'images/placeholder.png'); ?>'}}" />
                            </div>
                        </td>
                        <td>
                            <a class="button btn-primary nbd-mini-btn" ng-click="removeView(vIndex)" title="<?php esc_html_e('Delete View', 'pc-product-builder'); ?>"><span class="dashicons dashicons-no-alt"></span></a>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3"><a class="button btn-primary" ng-click="addView()"><?php esc_html_e('Add View', 'pc-product-builder'); ?></a></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <div><b><?php esc_html_e('Component icon', 'pc-product-builder'); ?></b></div>
    </div>
    <div class="pcpb-field-info-2">
        <div class="image-icon-wrap">
            <span class="dashicons dashicons-no remove-image-icon" ng-click="remove_component_icon(fieldIndex)"></span>
            <input ng-hide="true" ng-model="field.general.component_icon" name="options[fields][{{fieldIndex}}][general][component_icon]" />
            <img ng-click="set_component_icon(fieldIndex)" ng-src="{{field.general.component_icon != 0 ? field.general.component_icon_url : '<?php echo esc_url(SPBWC_PB_ASSETS_URL . 'images/placeholder.png'); ?>'}}" />
        </div>
    </div>
</div>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <div>
            <b><?php esc_html_e('Component configurations', 'pc-product-builder'); ?></b>
            <nbd-tip data-tip="<?php esc_html_e('All images in the same view must have the same size.', 'pc-product-builder'); ?>"></nbd-tip>
        </div>
    </div>
    <div class="pcpb-field-info-2">
        <div class="nbd-table-wrap">
            <table class="nbd-table" style="text-align: center;">
                <thead>
                    <tr>
                        <th rowspan="2"><?php esc_html_e('Attribute', 'pc-product-builder'); ?></th>
                        <th rowspan="2"><?php esc_html_e('Sub attribute', 'pc-product-builder'); ?></th>
                        <th colspan="{{options.views.length}}"><?php esc_html_e('View', 'pc-product-builder'); ?></th>
                    </tr>
                    <tr>
                        <th ng-repeat="view in options.views">{{view.name}}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr ng-repeat="pbcon in field.general.pb_config_flat">
                        <td ng-if="pbcon.attr_rowspan > 0" rowspan="{{pbcon.attr_rowspan}}">{{field.general.attributes.options[pbcon.attr_index].name}}</td>
                        <td>{{pbcon.has_sattr ? field.general.attributes.options[pbcon.attr_index].sub_attributes[pbcon.sattr_index].name : ''}}</td>
                        <td ng-repeat="view in options.views" style="text-align: left;">
                            <label class="view-config">
                                <?php esc_html_e('Show in view', 'pc-product-builder'); ?>
                                <input ng-model="field.general.pb_config[pbcon.attr_index][pbcon.sattr_index].views[$index].display" name="options[fields][{{fieldIndex}}][general][pb_config][{{pbcon.attr_index}}][{{pbcon.sattr_index}}][views][{{$index}}][display]" type="checkbox" />
                            </label>
                            <label class="view-config view-config-image">
                                <?php esc_html_e('Image', 'pc-product-builder'); ?>
                                <div class="image-icon-wrap">
                                    <input ng-model="field.general.pb_config[pbcon.attr_index][pbcon.sattr_index].views[$index].image" name="options[fields][{{fieldIndex}}][general][pb_config][{{pbcon.attr_index}}][{{pbcon.sattr_index}}][views][{{$index}}][image]" ng-hide="true" />
                                    <span class="dashicons dashicons-no remove-image-icon" ng-click="remove_view_config_image(fieldIndex, pbcon.attr_index, pbcon.sattr_index, $index)"></span>
                                    <img ng-click="set_view_config_image(fieldIndex, pbcon.attr_index, pbcon.sattr_index, $index)" ng-src="{{field.general.pb_config[pbcon.attr_index][pbcon.sattr_index].views[$index].image != 0 ? field.general.pb_config[pbcon.attr_index][pbcon.sattr_index].views[$index].image_url : '<?php echo esc_url(SPBWC_PB_ASSETS_URL . 'images/placeholder.png'); ?>'}}" />
                                </div>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php echo '</script>';
