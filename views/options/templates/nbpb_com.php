<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="nbd.nbpb_com">'; ?>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Views', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('Each "view" is one side of the product the customer can look at (Front, Back, Top, Inside…). Upload a base image per view — it sits behind all component layers and stays the same across attribute changes.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div class="nbd-table-wrap">
            <table class="nbd-table nbd-table--views">
                <thead>
                    <tr>
                        <th><?php esc_html_e('View name', 'storelly-product-builder-for-woocommerce'); ?></th>
                        <th><?php esc_html_e('View base', 'storelly-product-builder-for-woocommerce'); ?></th>
                        <th><?php esc_html_e('Action', 'storelly-product-builder-for-woocommerce'); ?></th>
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
                            <a class="button btn-primary nbd-mini-btn" ng-click="removeView(vIndex)" title="<?php esc_html_e('Delete View', 'storelly-product-builder-for-woocommerce'); ?>"><span class="dashicons dashicons-no-alt"></span></a>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3"><a class="button btn-primary" ng-click="addView()"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Add View', 'storelly-product-builder-for-woocommerce'); ?></a></td>
                    </tr>
                </tfoot>
            </table>
            <small class="v2-form-hint"><?php esc_html_e('Tip: keep all view base images the same dimensions so component layers line up correctly across views.', 'storelly-product-builder-for-woocommerce'); ?></small>
        </div>
    </div>
</div>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Component icon', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('Small icon shown in the component picker / layers panel on the live builder. Helps customers recognise this part at a glance (e.g. a tiny wheel icon for "Wheels", a frame icon for "Frame").', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div class="image-icon-wrap">
            <span class="dashicons dashicons-no remove-image-icon" ng-click="remove_component_icon(fieldIndex)"></span>
            <input ng-hide="true" ng-model="field.general.component_icon" name="options[fields][{{fieldIndex}}][general][component_icon]" />
            <img ng-click="set_component_icon(fieldIndex)" ng-src="{{field.general.component_icon != 0 ? field.general.component_icon_url : '<?php echo esc_url(SPBWC_PB_ASSETS_URL . 'images/placeholder.png'); ?>'}}" />
        </div>
        <small class="v2-form-hint"><?php esc_html_e('Recommended 64×64px transparent PNG or SVG. Click the thumbnail to upload.', 'storelly-product-builder-for-woocommerce'); ?></small>
    </div>
</div>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <label><b><?php esc_html_e('Component configurations', 'storelly-product-builder-for-woocommerce'); ?></b></label>
        <p class="v2-form-help"><?php esc_html_e('For each attribute/sub-attribute, upload the part image that appears on every view. Check "Show in <view>" to display this option on that view; leave unchecked to hide it (e.g. a logo only visible on the Front view).', 'storelly-product-builder-for-woocommerce'); ?></p>
        <p class="v2-form-help"><strong><?php esc_html_e('Important:', 'storelly-product-builder-for-woocommerce'); ?></strong> <?php esc_html_e('all images uploaded in the same view column must share the same dimensions so layers stack precisely.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div class="nbd-table-wrap">
            <table class="nbd-table nbd-table--pbconfig">
                <thead>
                    <tr>
                        <th rowspan="2"><?php esc_html_e('Attribute', 'storelly-product-builder-for-woocommerce'); ?></th>
                        <th rowspan="2"><?php esc_html_e('Sub attribute', 'storelly-product-builder-for-woocommerce'); ?></th>
                        <th colspan="{{options.views.length}}"><?php esc_html_e('View', 'storelly-product-builder-for-woocommerce'); ?></th>
                    </tr>
                    <tr>
                        <th ng-repeat="view in options.views">{{view.name}}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr ng-repeat="pbcon in field.general.pb_config_flat">
                        <td ng-if="pbcon.attr_rowspan > 0" rowspan="{{pbcon.attr_rowspan}}">{{field.general.attributes.options[pbcon.attr_index].name}}</td>
                        <td>{{pbcon.has_sattr ? field.general.attributes.options[pbcon.attr_index].sub_attributes[pbcon.sattr_index].name : ''}}</td>
                        <td ng-repeat="view in options.views" class="nbpb-cell--left">
                            <label class="view-config">
                                <span><?php esc_html_e('Show in', 'storelly-product-builder-for-woocommerce'); ?> <strong>{{view.name}}</strong></span>
                                <input ng-model="field.general.pb_config[pbcon.attr_index][pbcon.sattr_index].views[$index].display" name="options[fields][{{fieldIndex}}][general][pb_config][{{pbcon.attr_index}}][{{pbcon.sattr_index}}][views][{{$index}}][display]" type="checkbox" />
                            </label>
                            <div class="view-config view-config-image">
                                <div class="image-icon-wrap">
                                    <input ng-model="field.general.pb_config[pbcon.attr_index][pbcon.sattr_index].views[$index].image" name="options[fields][{{fieldIndex}}][general][pb_config][{{pbcon.attr_index}}][{{pbcon.sattr_index}}][views][{{$index}}][image]" ng-hide="true" />
                                    <span class="dashicons dashicons-no remove-image-icon" ng-click="remove_view_config_image(fieldIndex, pbcon.attr_index, pbcon.sattr_index, $index)"></span>
                                    <img ng-click="set_view_config_image(fieldIndex, pbcon.attr_index, pbcon.sattr_index, $index)" ng-src="{{field.general.pb_config[pbcon.attr_index][pbcon.sattr_index].views[$index].image != 0 ? field.general.pb_config[pbcon.attr_index][pbcon.sattr_index].views[$index].image_url : '<?php echo esc_url(SPBWC_PB_ASSETS_URL . 'images/placeholder.png'); ?>'}}" />
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php echo '</script>';
