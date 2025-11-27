<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_attributes">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.attributes)">
    <div class="pcpb-field-info-1">
        <div><label><b><?php esc_html_e('Attributes', 'spbwc-product-builder'); ?></b> <nbd-tip data-tip="<?php esc_html_e('Attributes let you define extra product data, such as size or color.', 'spbwc-product-builder'); ?>"></nbd-tip></label></div>
    </div>
    <div class="pcpb-field-info-2">
        <div>
            <div ng-repeat="(opIndex, op) in field.general.attributes.options" class="nbd-attribute-wrap">
                <div ng-show="op.isExpand" class="nbd-attribute-img-wrap">
                    <div><?php esc_html_e('Swatch type', 'spbwc-product-builder'); ?></div>
                    <div>
                        <select ng-model="op.preview_type" style="width: 110px;" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][preview_type]">
                            <option value="i"><?php esc_html_e('Image', 'spbwc-product-builder'); ?></option>
                            <option value="c"><?php esc_html_e('Color', 'spbwc-product-builder'); ?></option>
                        </select>
                    </div>
                    <div class="nbd-attribute-img-inner" ng-show="op.preview_type == 'i'">
                        <span class="dashicons dashicons-no remove-attribute-img" ng-click="remove_attribute_image(fieldIndex, $index, 'image', 'image_url')"></span>
                        <input ng-hide="true" ng-model="op.image" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][image]" />
                        <img title="<?php esc_html_e('Click to change image', 'spbwc-product-builder'); ?>" ng-click="set_attribute_image(fieldIndex, $index, 'image', 'image_url')" ng-src="{{op.image != 0 ? op.image_url : '<?php echo esc_url(SPBWC_PB_ASSETS_URL . 'images/placeholder.png'); ?>'}}" />
                    </div>
                    <div class="nbd-attribute-color-inner" ng-show="op.preview_type == 'c'">
                        <input type="text" name="options[fields][{{fieldIndex}}][general][attributes][options][{{$index}}][color]" ng-model="op.color" class="nbd-color-picker" nbd-color-picker="op.color" />
                        <span class="add-color2" ng-click="add_remove_second_color(fieldIndex, $index)"><span ng-show="!op.color2">+</span><span ng-show="op.color2">-</span></span>
                        <input ng-if="op.color2" type="text" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][color2]" ng-model="op.color2" class="nbd-color-picker" nbd-color-picker="op.color2" />
                    </div>
                    <div ng-if="field.appearance.change_image_product.value == 'y'">
                        <div><?php esc_html_e('Product image', 'spbwc-product-builder'); ?></div>
                        <div class="nbd-attribute-img-inner">
                            <span class="dashicons dashicons-no remove-attribute-img" ng-click="remove_attribute_image(fieldIndex, $index, 'product_image', 'product_image_url')"></span>
                            <input ng-hide="true" ng-model="op.product_image" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][product_image]" />
                            <img title="<?php esc_html_e('Click to change image', 'spbwc-product-builder'); ?>" ng-click="set_attribute_image(fieldIndex, $index, 'product_image', 'product_image_url')" ng-src="{{op.product_image_url ? op.product_image_url : '<?php echo esc_url(SPBWC_PB_ASSETS_URL . 'images/placeholder.png'); ?>'}}" />
                        </div>
                    </div>
                </div>
                <div ng-show="op.isExpand" class="nbd-attribute-content-wrap">
                    <div><?php esc_html_e('Title', 'spbwc-product-builder'); ?></div>
                    <div class="nbd-attribute-name">
                        <input required type="text" value="{{op.name}}" ng-model="op.name" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][name]" />
                        <label><input type="checkbox" name="options[fields][{{fieldIndex}}][general][attributes][options][{{$index}}][selected]" ng-checked="op.selected" ng-click="seleted_attribute(fieldIndex, 'attributes', $index)" /> <?php esc_html_e('Default', 'spbwc-product-builder'); ?></label>
                    </div>
                    <div class="nbd-margin-10"></div>
                    <div><?php esc_html_e('Description', 'spbwc-product-builder'); ?></div>
                    <div class="nbd-attribute-name">
                        <textarea placeholder="<?php esc_html_e('Description', 'spbwc-product-builder'); ?>" value="{{op.des}}" ng-model="op.des" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][des]"></textarea>
                    </div>
                    <div class="nbd-margin-10"></div>
                    <div><?php esc_html_e('Price', 'spbwc-product-builder'); ?></div>
                    <div>
                        <div><?php esc_html_e('Additional Price', 'spbwc-product-builder'); ?></div>
                        <div>
                            <input autocomplete="off" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][price][0]" class="nbd-short-ip" type="text" ng-model="op.price[0]" />
                        </div>
                    </div>
                    <div class="nbd-margin-10"></div>
                    <hr />
                    <div class="nbd-enable-subattribute" ng-hide="field.nbd_type != '' && field.nbd_type != null">
                        <label><input ng-click="toggle_enable_subattr(fieldIndex, $index)" type="checkbox" name="options[fields][{{fieldIndex}}][general][attributes][options][{{$index}}][enable_subattr]" ng-true-value="'on'" ng-false-value="'off'" ng-model="op.enable_subattr" ng-checked="op.enable_subattr" /> <?php esc_html_e('Enable sub attributes', 'spbwc-product-builder'); ?></label>
                    </div>
                    <div class="nbd-margin-10"></div>
                    <div class="nbd-subattributes-wrapper" ng-if="op.enable_subattr === true || op.enable_subattr == 'on'">
                        <div class="pcpb-field-info">
                            <div class="pcpb-field-info-1">
                                <div><label><b><?php esc_html_e('Sub attributes type', 'spbwc-product-builder'); ?></b></label></div>
                            </div>
                            <div class="pcpb-field-info-2">
                                <div>
                                    <select style="width: 150px;" name="options[fields][{{fieldIndex}}][general][attributes][options][{{$index}}][sattr_display_type]" ng-model="op.sattr_display_type">
                                        <option value="d"><?php esc_html_e('Dropdown', 'spbwc-product-builder'); ?></option>
                                        <option value="r"><?php esc_html_e('Radio button', 'spbwc-product-builder'); ?></option>
                                        <option value="s"><?php esc_html_e('Swatch', 'spbwc-product-builder'); ?></option>
                                        <option value="l"><?php esc_html_e('Label', 'spbwc-product-builder'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="nbd-margin-10"></div>
                        <div ng-repeat="(sopIndex, sop) in op.sub_attributes" class="nbd-subattributes-wrap">
                            <div ng-show="sop.isExpand" class="nbd-attribute-img-wrap">
                                <div><?php esc_html_e('Swatch type', 'spbwc-product-builder'); ?></div>
                                <div>
                                    <select ng-model="sop.preview_type" style="width: 110px;" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][sub_attributes][{{sopIndex}}][preview_type]">
                                        <option value="i"><?php esc_html_e('Image', 'spbwc-product-builder'); ?></option>
                                        <option value="c"><?php esc_html_e('Color', 'spbwc-product-builder'); ?></option>
                                    </select>
                                </div>
                                <div class="nbd-attribute-img-inner" ng-show="sop.preview_type == 'i'">
                                    <span class="dashicons dashicons-no remove-attribute-img" ng-click="remove_sub_attribute_image(fieldIndex, opIndex, sopIndex)"></span>
                                    <input ng-hide="true" ng-model="sop.image" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][sub_attributes][{{sopIndex}}][image]" />
                                    <img title="<?php esc_html_e('Click to change image', 'spbwc-product-builder'); ?>" ng-click="set_sub_attribute_image(fieldIndex, opIndex, sopIndex)" ng-src="{{sop.image != 0 ? sop.image_url : '<?php echo esc_url(SPBWC_PB_ASSETS_URL . 'images/placeholder.png'); ?>'}}" />
                                </div>
                                <div class="nbd-attribute-color-inner" ng-show="sop.preview_type == 'c'">
                                    <input type="text" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][sub_attributes][{{sopIndex}}][color]" ng-model="sop.color" class="nbd-color-picker" nbd-color-picker="sop.color" />
                                </div>
                            </div>
                            <div ng-show="sop.isExpand" class="nbd-attribute-content-wrap">
                                <div><?php esc_html_e('Title', 'spbwc-product-builder'); ?></div>
                                <div class="nbd-attribute-name">
                                    <input required type="text" value="{{sop.name}}" ng-model="sop.name" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][sub_attributes][{{sopIndex}}][name]" />
                                    <label><input type="checkbox" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][sub_attributes][{{sopIndex}}][selected]" ng-checked="sop.selected" ng-click="seleted_sub_attribute(fieldIndex, 'attributes', opIndex, sopIndex)" /> <?php esc_html_e('Default', 'spbwc-product-builder'); ?></label>
                                </div>
                                <div class="nbd-margin-10"></div>
                                <div><?php esc_html_e('Description', 'spbwc-product-builder'); ?></div>
                                <div class="nbd-attribute-name">
                                    <textarea placeholder="<?php esc_html_e('Description', 'spbwc-product-builder'); ?>" value="{{sop.des}}" ng-model="sop.des" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][sub_attributes][{{sopIndex}}][des]"></textarea>
                                </div>
                                <div><?php esc_html_e('Price', 'spbwc-product-builder'); ?></div>
                                <div>
                                    <div><?php esc_html_e('Additional Price', 'spbwc-product-builder'); ?></div>
                                    <div>
                                        <input autocomplete="off" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][sub_attributes][{{sopIndex}}][price][0]" class="nbd-short-ip" type="text" ng-model="sop.price[0]" />
                                    </div>
                                </div>
                                <div class="nbd-margin-10"></div>
                            </div>
                            <div ng-show="!sop.isExpand" class="nbd-attribute-name-preview">{{sop.name}}</div>
                            <div class="nbd-attribute-action">
                                <span class="nbo-sort-group">
                                    <span ng-click="sort_sub_attribute(fieldIndex, opIndex, sopIndex, 'up')" class="dashicons dashicons-arrow-up nbo-sort-up nbo-sort" title="<?php esc_html_e('Up', 'spbwc-product-builder') ?>"></span>
                                    <span ng-click="sort_sub_attribute(fieldIndex, opIndex, sopIndex, 'down')" class="dashicons dashicons-arrow-down nbo-sort-down nbo-sort" title="<?php esc_html_e('Down', 'spbwc-product-builder') ?>"></span>
                                </span>
                                <a class="button nbd-mini-btn" ng-click="remove_sub_attribute(fieldIndex, opIndex, sopIndex)" title="<?php esc_html_e('Delete', 'spbwc-product-builder'); ?>"><span class="dashicons dashicons-no-alt"></span></a>
                                <a class="button nbd-mini-btn" ng-click="toggle_expand_sub_attribute(fieldIndex, opIndex, sopIndex)" title="<?php esc_html_e('Expend', 'spbwc-product-builder'); ?>">
                                    <span ng-show="sop.isExpand" class="dashicons dashicons-arrow-up"></span>
                                    <span ng-show="!sop.isExpand" class="dashicons dashicons-arrow-down"></span>
                                </a>
                            </div>
                        </div>
                        <div><a class="button" ng-click="add_sub_attribute(fieldIndex, opIndex)"><span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add sub attribute', 'spbwc-product-builder'); ?></a></div>
                        <div class="nbd-margin-10"></div>
                    </div>
                </div>
                <div ng-show="!op.isExpand" class="nbd-attribute-name-preview">{{op.name}}</div>
                <div class="nbd-attribute-action">
                    <span class="nbo-sort-group">
                        <span ng-click="sort_attribute(fieldIndex, $index, 'up')" class="dashicons dashicons-arrow-up nbo-sort-up nbo-sort" title="<?php esc_html_e('Up', 'spbwc-product-builder') ?>"></span>
                        <span ng-click="sort_attribute(fieldIndex, $index, 'down')" class="dashicons dashicons-arrow-down nbo-sort-down nbo-sort" title="<?php esc_html_e('Down', 'spbwc-product-builder') ?>"></span>
                    </span>
                    <a class="button nbd-mini-btn" ng-click="remove_attribute(fieldIndex, 'attributes', $index)" title="<?php esc_html_e('Delete', 'spbwc-product-builder'); ?>"><span class="dashicons dashicons-no-alt"></span></a>
                    <a class="button nbd-mini-btn" ng-click="toggle_expand_attribute(fieldIndex, opIndex)" title="<?php esc_html_e('Expend', 'spbwc-product-builder'); ?>">
                        <span ng-show="op.isExpand" class="dashicons dashicons-arrow-up"></span>
                        <span ng-show="!op.isExpand" class="dashicons dashicons-arrow-down"></span>
                    </a>
                </div>
                <div class="clear"></div>
            </div>
            <div><a class="button" ng-click="add_attribute(fieldIndex, 'attributes')"><span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add attribute', 'spbwc-product-builder'); ?></a></div>
        </div>
    </div>
</div>
<?php echo '</script>';
