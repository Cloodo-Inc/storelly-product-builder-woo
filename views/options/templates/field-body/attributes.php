<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body_attributes">'; ?>
<div class="pcpb-field-info" ng-show="check_depend(field.general, field.general.attributes)">
    <div class="pcpb-field-info-1 v2-attrs-intro">
        <p class="v2-form-help"><?php esc_html_e('Each attribute is one choice the customer can pick (e.g. paper finish, color, size variant). Add a swatch or image and an optional price surcharge.', 'storelly-product-builder-for-woocommerce'); ?></p>
    </div>
    <div class="pcpb-field-info-2">
        <div>
            <div ng-repeat="(opIndex, op) in field.general.attributes.options" class="nbd-attribute-wrap" ng-class="{'is-expanded': op.isExpand, 'is-default': op.selected}">
                <!-- COLLAPSED preview: swatch + name + price + default badge -->
                <div ng-show="!op.isExpand" class="nbd-attribute-collapsed">
                    <div class="nbd-attribute-collapsed__swatch"
                         ng-style="op.image_url ? { 'background-image': 'url(' + op.image_url + ')' } : { 'background': op.color || '#e5e7eb' }">
                    </div>
                    <div class="nbd-attribute-collapsed__body">
                        <strong class="nbd-attribute-collapsed__name">{{ op.name || '<?php echo esc_js(__('(unnamed)', 'storelly-product-builder-for-woocommerce')); ?>' }}</strong>
                        <div class="nbd-attribute-collapsed__meta">
                            <span class="nbd-attribute-collapsed__price" ng-show="attr_price_label(op)">{{ attr_price_label(op) }}</span>
                            <span class="nbd-attribute-collapsed__free" ng-hide="attr_price_label(op)"><?php esc_html_e('Free', 'storelly-product-builder-for-woocommerce'); ?></span>
                            <span class="nbd-attribute-collapsed__default" ng-show="op.selected"><?php esc_html_e('★ Default', 'storelly-product-builder-for-woocommerce'); ?></span>
                            <span class="nbd-attribute-collapsed__sub" ng-show="op.enable_subattr === 'on' || op.enable_subattr === true">
                                <?php esc_html_e('+ sub-attrs', 'storelly-product-builder-for-woocommerce'); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <!-- v2 titlebar: name + Default + actions, FULL WIDTH top row -->
                <div ng-show="op.isExpand" class="v2-attr-titlebar">
                    <div class="v2-attr-titlebar__main">
                        <label class="v2-attr-titlebar__label"><?php esc_html_e('Attribute name', 'storelly-product-builder-for-woocommerce'); ?> <span class="v2-required" aria-hidden="true">*</span></label>
                        <div class="v2-attr-titlebar__row">
                            <input required type="text" value="{{op.name}}"
                                   ng-model="op.name"
                                   name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][name]"
                                   placeholder="<?php esc_attr_e('e.g. Matte 250gsm, Glossy 300gsm', 'storelly-product-builder-for-woocommerce'); ?>"
                                   class="v2-attr-titlebar__input" />
                            <label class="v2-attr-default-toggle" ng-class="{'is-on': op.selected}">
                                <input type="checkbox"
                                       name="options[fields][{{fieldIndex}}][general][attributes][options][{{$index}}][selected]"
                                       ng-checked="op.selected"
                                       ng-click="seleted_attribute(fieldIndex, 'attributes', $index)" />
                                <span class="v2-attr-default-toggle__icon" aria-hidden="true">★</span>
                                <span class="v2-attr-default-toggle__label"><?php esc_html_e('Default', 'storelly-product-builder-for-woocommerce'); ?></span>
                            </label>
                        </div>
                    </div>
                </div>
                <!-- 1) Description + Additional Price (moved UP — primary metadata) -->
                <div ng-show="op.isExpand" class="nbd-attribute-content-wrap nbd-attribute-content-wrap--main">
                    <div class="v2-attr-field">
                        <div class="v2-attr-section-label"><?php esc_html_e('Description', 'storelly-product-builder-for-woocommerce'); ?> <span class="v2-attr-section-label__opt">(<?php esc_html_e('optional', 'storelly-product-builder-for-woocommerce'); ?>)</span></div>
                        <div class="nbd-attribute-name">
                            <textarea placeholder="<?php esc_attr_e('e.g. Premium matte finish, 250gsm — best for high-end packaging.', 'storelly-product-builder-for-woocommerce'); ?>" value="{{op.des}}" ng-model="op.des" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][des]"></textarea>
                        </div>
                        <small class="v2-attr-field__hint"><?php esc_html_e('Shown next to the option on the product page — helps the customer pick the right one.', 'storelly-product-builder-for-woocommerce'); ?></small>
                    </div>
                    <div class="v2-attr-field">
                        <div class="v2-attr-section-label"><?php esc_html_e('Additional price', 'storelly-product-builder-for-woocommerce'); ?></div>
                        <div class="v2-attr-price-input">
                            <span class="v2-input-prefix">$</span>
                            <input autocomplete="off" string-to-number name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][price][0]" type="number" step="0.01" placeholder="0.00" ng-model="op.price[0]" />
                        </div>
                        <small class="v2-attr-field__hint"><?php esc_html_e('Added to the base price when the customer picks this option. Leave 0.00 for no surcharge.', 'storelly-product-builder-for-woocommerce'); ?></small>
                    </div>
                </div>

                <!-- 2) Swatch type — vertical tabs (Image / Color) on the LEFT, content panel on the RIGHT -->
                <div ng-show="op.isExpand" class="nbd-attribute-img-wrap v2-swatch-tabs">
                    <div class="v2-attr-section-label"><?php esc_html_e('Swatch type', 'storelly-product-builder-for-woocommerce'); ?></div>
                    <div class="v2-swatch-tabs__layout">
                        <div class="v2-swatch-tabs__nav" role="tablist" aria-label="<?php esc_attr_e('Swatch type', 'storelly-product-builder-for-woocommerce'); ?>">
                            <button type="button" role="tab"
                                    class="v2-swatch-tabs__tab"
                                    ng-class="{'is-active': op.preview_type === 'i'}"
                                    ng-click="op.preview_type = 'i'"
                                    aria-selected="{{op.preview_type === 'i' ? 'true' : 'false'}}">
                                <span class="v2-swatch-tabs__tab-icon" aria-hidden="true"><span class="dashicons dashicons-format-image"></span></span>
                                <span class="v2-swatch-tabs__tab-body">
                                    <strong><?php esc_html_e('Image', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                    <small><?php esc_html_e('Photo — paper, texture, fabric…', 'storelly-product-builder-for-woocommerce'); ?></small>
                                </span>
                            </button>
                            <button type="button" role="tab"
                                    class="v2-swatch-tabs__tab"
                                    ng-class="{'is-active': op.preview_type === 'c'}"
                                    ng-click="op.preview_type = 'c'"
                                    aria-selected="{{op.preview_type === 'c' ? 'true' : 'false'}}">
                                <span class="v2-swatch-tabs__tab-icon" aria-hidden="true"><span class="dashicons dashicons-art"></span></span>
                                <span class="v2-swatch-tabs__tab-body">
                                    <strong><?php esc_html_e('Color', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                    <small><?php esc_html_e('Solid color or gradient.', 'storelly-product-builder-for-woocommerce'); ?></small>
                                </span>
                            </button>
                        </div>
                        <select class="v2-hidden-legacy" ng-model="op.preview_type" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][preview_type]">
                            <option value="i"><?php esc_html_e('Image', 'storelly-product-builder-for-woocommerce'); ?></option>
                            <option value="c"><?php esc_html_e('Color', 'storelly-product-builder-for-woocommerce'); ?></option>
                        </select>
                        <div class="v2-swatch-tabs__panel" role="tabpanel">
                            <!-- Image panel -->
                            <div class="v2-img-upload" ng-show="op.preview_type == 'i'" ng-class="{'is-empty': !op.image || op.image == 0}">
                                <div class="nbd-attribute-img-inner v2-img-upload__thumb"
                                     ng-click="set_attribute_image(fieldIndex, $index, 'image', 'image_url')"
                                     title="<?php esc_attr_e('Click to upload or change image', 'storelly-product-builder-for-woocommerce'); ?>">
                                    <input ng-hide="true" ng-model="op.image" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][image]" />
                                    <img ng-src="{{op.image != 0 ? op.image_url : '<?php echo esc_url(SPBWC_PB_ASSETS_URL . 'images/placeholder.png'); ?>'}}" alt="" />
                                    <span class="v2-img-upload__empty" ng-show="!op.image || op.image == 0" aria-hidden="true">
                                        <span class="dashicons dashicons-plus-alt2"></span>
                                        <small><?php esc_html_e('Click to upload', 'storelly-product-builder-for-woocommerce'); ?></small>
                                    </span>
                                    <span class="v2-img-upload__overlay" ng-show="op.image != 0">
                                        <button type="button" class="v2-img-upload__icon-btn"
                                                ng-click="$event.stopPropagation(); set_attribute_image(fieldIndex, $index, 'image', 'image_url')"
                                                title="<?php esc_attr_e('Replace image', 'storelly-product-builder-for-woocommerce'); ?>"
                                                aria-label="<?php esc_attr_e('Replace image', 'storelly-product-builder-for-woocommerce'); ?>">
                                            <span class="dashicons dashicons-update" aria-hidden="true"></span>
                                        </button>
                                        <button type="button" class="v2-img-upload__icon-btn v2-img-upload__icon-btn--danger"
                                                ng-click="$event.stopPropagation(); remove_attribute_image(fieldIndex, $index, 'image', 'image_url')"
                                                title="<?php esc_attr_e('Remove image', 'storelly-product-builder-for-woocommerce'); ?>"
                                                aria-label="<?php esc_attr_e('Remove image', 'storelly-product-builder-for-woocommerce'); ?>">
                                            <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                        </button>
                                    </span>
                                </div>
                                <small class="v2-img-upload__hint"><?php esc_html_e('Recommended 600×600px, ≤ 200KB. JPG or PNG.', 'storelly-product-builder-for-woocommerce'); ?></small>
                            </div>
                            <!-- Color panel -->
                            <div class="nbd-attribute-color-inner v2-color-row" ng-show="op.preview_type == 'c'">
                                <input type="text" name="options[fields][{{fieldIndex}}][general][attributes][options][{{$index}}][color]" ng-model="op.color" class="nbd-color-picker" nbd-color-picker="op.color" />
                                <span class="add-color2" ng-click="add_remove_second_color(fieldIndex, $index)" title="<?php esc_attr_e('Add a second color for a gradient swatch', 'storelly-product-builder-for-woocommerce'); ?>"><span ng-show="!op.color2">+</span><span ng-show="op.color2">−</span></span>
                                <input ng-if="op.color2" type="text" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][color2]" ng-model="op.color2" class="nbd-color-picker" nbd-color-picker="op.color2" />
                                <small class="v2-color-row__hint" ng-show="!op.color2"><?php esc_html_e('Click + to add a second color and create a gradient swatch.', 'storelly-product-builder-for-woocommerce'); ?></small>
                                <small class="v2-color-row__hint" ng-show="op.color2"><?php esc_html_e('Two-color gradient — front swatch on the product page.', 'storelly-product-builder-for-woocommerce'); ?></small>
                            </div>
                            <!-- Product image (conditional, legacy feature) -->
                            <div ng-if="field.appearance.change_image_product.value == 'y'" class="v2-product-image-row">
                                <div class="v2-attr-section-label"><?php esc_html_e('Product image', 'storelly-product-builder-for-woocommerce'); ?></div>
                                <div class="nbd-attribute-img-inner">
                                    <span class="dashicons dashicons-no remove-attribute-img" ng-click="remove_attribute_image(fieldIndex, $index, 'product_image', 'product_image_url')"></span>
                                    <input ng-hide="true" ng-model="op.product_image" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][product_image]" />
                                    <img title="<?php esc_attr_e('Click to change image', 'storelly-product-builder-for-woocommerce'); ?>" ng-click="set_attribute_image(fieldIndex, $index, 'product_image', 'product_image_url')" ng-src="{{op.product_image_url ? op.product_image_url : '<?php echo esc_url(SPBWC_PB_ASSETS_URL . 'images/placeholder.png'); ?>'}}" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3) Sub-attributes (toggle + nested list) — stays at the bottom -->
                <div ng-show="op.isExpand" class="nbd-attribute-content-wrap nbd-attribute-content-wrap--sub">
                    <div class="v2-attr-divider" ng-hide="field.nbd_type != '' && field.nbd_type != null"></div>
                    <div class="nbd-enable-subattribute v2-subattr-toggle" ng-hide="field.nbd_type != '' && field.nbd_type != null">
                        <label class="v2-subattr-toggle__label">
                            <input ng-click="toggle_enable_subattr(fieldIndex, $index)" type="checkbox" name="options[fields][{{fieldIndex}}][general][attributes][options][{{$index}}][enable_subattr]" ng-true-value="'on'" ng-false-value="'off'" ng-model="op.enable_subattr" />
                            <span class="v2-subattr-toggle__text">
                                <strong><?php esc_html_e('Enable sub-attributes', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                <small><?php esc_html_e('Show extra choices that only appear when this option is selected (e.g. Size → Color).', 'storelly-product-builder-for-woocommerce'); ?></small>
                            </span>
                        </label>
                    </div>
                    <div class="nbd-margin-10"></div>
                    <div class="nbd-subattributes-wrapper" ng-if="op.enable_subattr === true || op.enable_subattr == 'on'">
                        <div class="pcpb-field-info">
                            <div class="pcpb-field-info-1">
                                <label><b><?php esc_html_e('Sub attributes display type', 'storelly-product-builder-for-woocommerce'); ?></b></label>
                                <p class="v2-form-help"><?php esc_html_e('How sub-attributes appear on the product page when the parent attribute is selected.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </div>
                            <div class="pcpb-field-info-2">
                                <div class="v2-display-cards v2-display-cards--sub" role="radiogroup" aria-label="<?php esc_attr_e('Sub attributes display type', 'storelly-product-builder-for-woocommerce'); ?>">
                                    <button type="button"
                                            class="v2-display-card v2-display-card--d"
                                            ng-class="{'is-active': op.sattr_display_type === 'd'}"
                                            ng-click="op.sattr_display_type = 'd'">
                                        <span class="v2-display-card__preview" aria-hidden="true"></span>
                                        <span class="v2-display-card__label"><?php esc_html_e('Dropdown', 'storelly-product-builder-for-woocommerce'); ?></span>
                                        <small class="v2-display-card__hint"><?php esc_html_e('Compact pick-one list. Best for many options.', 'storelly-product-builder-for-woocommerce'); ?></small>
                                    </button>
                                    <button type="button"
                                            class="v2-display-card v2-display-card--r"
                                            ng-class="{'is-active': op.sattr_display_type === 'r'}"
                                            ng-click="op.sattr_display_type = 'r'">
                                        <span class="v2-display-card__preview" aria-hidden="true"></span>
                                        <span class="v2-display-card__label"><?php esc_html_e('Radio', 'storelly-product-builder-for-woocommerce'); ?></span>
                                        <small class="v2-display-card__hint"><?php esc_html_e('All options visible. Best for 2–5 choices.', 'storelly-product-builder-for-woocommerce'); ?></small>
                                    </button>
                                    <button type="button"
                                            class="v2-display-card v2-display-card--s"
                                            ng-class="{'is-active': op.sattr_display_type === 's'}"
                                            ng-click="op.sattr_display_type = 's'">
                                        <span class="v2-display-card__preview" aria-hidden="true"></span>
                                        <span class="v2-display-card__label"><?php esc_html_e('Swatch', 'storelly-product-builder-for-woocommerce'); ?></span>
                                        <small class="v2-display-card__hint"><?php esc_html_e('Color / image tiles. Best for visual variants.', 'storelly-product-builder-for-woocommerce'); ?></small>
                                    </button>
                                    <button type="button"
                                            class="v2-display-card v2-display-card--l"
                                            ng-class="{'is-active': op.sattr_display_type === 'l'}"
                                            ng-click="op.sattr_display_type = 'l'">
                                        <span class="v2-display-card__preview" aria-hidden="true"></span>
                                        <span class="v2-display-card__label"><?php esc_html_e('Label', 'storelly-product-builder-for-woocommerce'); ?></span>
                                        <small class="v2-display-card__hint"><?php esc_html_e('Plain text labels. Minimal, no graphics.', 'storelly-product-builder-for-woocommerce'); ?></small>
                                    </button>
                                </div>
                                <select class="v2-hidden-legacy" name="options[fields][{{fieldIndex}}][general][attributes][options][{{$index}}][sattr_display_type]" ng-model="op.sattr_display_type">
                                    <option value="d"><?php esc_html_e('Dropdown', 'storelly-product-builder-for-woocommerce'); ?></option>
                                    <option value="r"><?php esc_html_e('Radio button', 'storelly-product-builder-for-woocommerce'); ?></option>
                                    <option value="s"><?php esc_html_e('Swatch', 'storelly-product-builder-for-woocommerce'); ?></option>
                                    <option value="l"><?php esc_html_e('Label', 'storelly-product-builder-for-woocommerce'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="nbd-margin-10"></div>
                        <div ng-repeat="(sopIndex, sop) in op.sub_attributes" class="nbd-subattributes-wrap" ng-class="{'is-expanded': sop.isExpand, 'is-default': sop.selected}">
                            <!-- v2 titlebar (same pattern as parent attribute) -->
                            <div ng-show="sop.isExpand" class="v2-attr-titlebar">
                                <div class="v2-attr-titlebar__main">
                                    <label class="v2-attr-titlebar__label"><?php esc_html_e('Sub attribute name', 'storelly-product-builder-for-woocommerce'); ?> <span class="v2-required" aria-hidden="true">*</span></label>
                                    <div class="v2-attr-titlebar__row">
                                        <input required type="text" value="{{sop.name}}"
                                               ng-model="sop.name"
                                               name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][sub_attributes][{{sopIndex}}][name]"
                                               placeholder="<?php esc_attr_e('e.g. Glossy black, Brushed steel', 'storelly-product-builder-for-woocommerce'); ?>"
                                               class="v2-attr-titlebar__input" />
                                        <label class="v2-attr-default-toggle" ng-class="{'is-on': sop.selected}">
                                            <input type="checkbox"
                                                   name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][sub_attributes][{{sopIndex}}][selected]"
                                                   ng-checked="sop.selected"
                                                   ng-click="seleted_sub_attribute(fieldIndex, 'attributes', opIndex, sopIndex)" />
                                            <span class="v2-attr-default-toggle__icon" aria-hidden="true">★</span>
                                            <span class="v2-attr-default-toggle__label"><?php esc_html_e('Default', 'storelly-product-builder-for-woocommerce'); ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- 1) Description + Additional Price (moved UP — primary metadata) -->
                            <div ng-show="sop.isExpand" class="nbd-attribute-content-wrap">
                                <div class="v2-attr-field">
                                    <div class="v2-attr-section-label"><?php esc_html_e('Description', 'storelly-product-builder-for-woocommerce'); ?> <span class="v2-attr-section-label__opt">(<?php esc_html_e('optional', 'storelly-product-builder-for-woocommerce'); ?>)</span></div>
                                    <div class="nbd-attribute-name">
                                        <textarea placeholder="<?php esc_attr_e('Optional context shown next to this option', 'storelly-product-builder-for-woocommerce'); ?>" value="{{sop.des}}" ng-model="sop.des" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][sub_attributes][{{sopIndex}}][des]"></textarea>
                                    </div>
                                </div>
                                <div class="v2-attr-field">
                                    <div class="v2-attr-section-label"><?php esc_html_e('Additional price', 'storelly-product-builder-for-woocommerce'); ?></div>
                                    <div class="v2-attr-price-input">
                                        <span class="v2-input-prefix">$</span>
                                        <input autocomplete="off" string-to-number name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][sub_attributes][{{sopIndex}}][price][0]" type="number" step="0.01" placeholder="0.00" ng-model="sop.price[0]" />
                                    </div>
                                    <small class="v2-attr-field__hint"><?php esc_html_e('Added on top of the parent option price when this sub-option is picked.', 'storelly-product-builder-for-woocommerce'); ?></small>
                                </div>
                            </div>

                            <!-- 2) Swatch type — vertical tabs (compact) -->
                            <div ng-show="sop.isExpand" class="nbd-attribute-img-wrap v2-swatch-tabs v2-swatch-tabs--compact">
                                <div class="v2-attr-section-label"><?php esc_html_e('Swatch type', 'storelly-product-builder-for-woocommerce'); ?></div>
                                <div class="v2-swatch-tabs__layout">
                                    <div class="v2-swatch-tabs__nav" role="tablist" aria-label="<?php esc_attr_e('Swatch type', 'storelly-product-builder-for-woocommerce'); ?>">
                                        <button type="button" role="tab"
                                                class="v2-swatch-tabs__tab"
                                                ng-class="{'is-active': sop.preview_type === 'i'}"
                                                ng-click="sop.preview_type = 'i'"
                                                aria-selected="{{sop.preview_type === 'i' ? 'true' : 'false'}}">
                                            <span class="v2-swatch-tabs__tab-icon" aria-hidden="true"><span class="dashicons dashicons-format-image"></span></span>
                                            <span class="v2-swatch-tabs__tab-body">
                                                <strong><?php esc_html_e('Image', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                                <small><?php esc_html_e('Photo', 'storelly-product-builder-for-woocommerce'); ?></small>
                                            </span>
                                        </button>
                                        <button type="button" role="tab"
                                                class="v2-swatch-tabs__tab"
                                                ng-class="{'is-active': sop.preview_type === 'c'}"
                                                ng-click="sop.preview_type = 'c'"
                                                aria-selected="{{sop.preview_type === 'c' ? 'true' : 'false'}}">
                                            <span class="v2-swatch-tabs__tab-icon" aria-hidden="true"><span class="dashicons dashicons-art"></span></span>
                                            <span class="v2-swatch-tabs__tab-body">
                                                <strong><?php esc_html_e('Color', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                                <small><?php esc_html_e('Solid color', 'storelly-product-builder-for-woocommerce'); ?></small>
                                            </span>
                                        </button>
                                    </div>
                                    <select class="v2-hidden-legacy" ng-model="sop.preview_type" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][sub_attributes][{{sopIndex}}][preview_type]">
                                        <option value="i"><?php esc_html_e('Image', 'storelly-product-builder-for-woocommerce'); ?></option>
                                        <option value="c"><?php esc_html_e('Color', 'storelly-product-builder-for-woocommerce'); ?></option>
                                    </select>
                                    <div class="v2-swatch-tabs__panel" role="tabpanel">
                                        <!-- Image panel -->
                                        <div class="v2-img-upload v2-img-upload--compact" ng-show="sop.preview_type == 'i'" ng-class="{'is-empty': !sop.image || sop.image == 0}">
                                            <div class="nbd-attribute-img-inner v2-img-upload__thumb"
                                                 ng-click="set_sub_attribute_image(fieldIndex, opIndex, sopIndex)"
                                                 title="<?php esc_attr_e('Click to upload or change image', 'storelly-product-builder-for-woocommerce'); ?>">
                                                <input ng-hide="true" ng-model="sop.image" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][sub_attributes][{{sopIndex}}][image]" />
                                                <img ng-src="{{sop.image != 0 ? sop.image_url : '<?php echo esc_url(SPBWC_PB_ASSETS_URL . 'images/placeholder.png'); ?>'}}" alt="" />
                                                <span class="v2-img-upload__empty" ng-show="!sop.image || sop.image == 0" aria-hidden="true">
                                                    <span class="dashicons dashicons-plus-alt2"></span>
                                                    <small><?php esc_html_e('Upload', 'storelly-product-builder-for-woocommerce'); ?></small>
                                                </span>
                                                <span class="v2-img-upload__overlay" ng-show="sop.image != 0">
                                                    <button type="button" class="v2-img-upload__icon-btn"
                                                            ng-click="$event.stopPropagation(); set_sub_attribute_image(fieldIndex, opIndex, sopIndex)"
                                                            title="<?php esc_attr_e('Replace image', 'storelly-product-builder-for-woocommerce'); ?>"
                                                            aria-label="<?php esc_attr_e('Replace image', 'storelly-product-builder-for-woocommerce'); ?>">
                                                        <span class="dashicons dashicons-update" aria-hidden="true"></span>
                                                    </button>
                                                    <button type="button" class="v2-img-upload__icon-btn v2-img-upload__icon-btn--danger"
                                                            ng-click="$event.stopPropagation(); remove_sub_attribute_image(fieldIndex, opIndex, sopIndex)"
                                                            title="<?php esc_attr_e('Remove image', 'storelly-product-builder-for-woocommerce'); ?>"
                                                            aria-label="<?php esc_attr_e('Remove image', 'storelly-product-builder-for-woocommerce'); ?>">
                                                        <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                                    </button>
                                                </span>
                                            </div>
                                        </div>
                                        <!-- Color panel -->
                                        <div class="nbd-attribute-color-inner v2-color-row" ng-show="sop.preview_type == 'c'">
                                            <input type="text" name="options[fields][{{fieldIndex}}][general][attributes][options][{{opIndex}}][sub_attributes][{{sopIndex}}][color]" ng-model="sop.color" class="nbd-color-picker" nbd-color-picker="sop.color" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div ng-show="!sop.isExpand" class="nbd-attribute-name-preview">{{sop.name}}</div>
                            <div class="nbd-attribute-action">
                                <span class="nbo-sort-group">
                                    <span ng-click="sort_sub_attribute(fieldIndex, opIndex, sopIndex, 'up')" class="dashicons dashicons-arrow-up nbo-sort-up nbo-sort" title="<?php esc_html_e('Up', 'storelly-product-builder-for-woocommerce') ?>"></span>
                                    <span ng-click="sort_sub_attribute(fieldIndex, opIndex, sopIndex, 'down')" class="dashicons dashicons-arrow-down nbo-sort-down nbo-sort" title="<?php esc_html_e('Down', 'storelly-product-builder-for-woocommerce') ?>"></span>
                                </span>
                                <a class="button nbd-mini-btn" ng-click="remove_sub_attribute(fieldIndex, opIndex, sopIndex)" title="<?php esc_html_e('Delete', 'storelly-product-builder-for-woocommerce'); ?>"><span class="dashicons dashicons-no-alt"></span></a>
                                <a class="button nbd-mini-btn" ng-click="toggle_expand_sub_attribute(fieldIndex, opIndex, sopIndex)" title="<?php esc_html_e('Expend', 'storelly-product-builder-for-woocommerce'); ?>">
                                    <span ng-show="sop.isExpand" class="dashicons dashicons-arrow-up"></span>
                                    <span ng-show="!sop.isExpand" class="dashicons dashicons-arrow-down"></span>
                                </a>
                            </div>
                        </div>
                        <div class="v2-attr-addrow v2-attr-addrow--sub">
                            <a class="button v2-attr-add-btn" ng-click="add_sub_attribute(fieldIndex, opIndex)">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <?php esc_html_e('Add sub-attribute', 'storelly-product-builder-for-woocommerce'); ?>
                            </a>
                            <small class="v2-attr-addrow__hint"><?php esc_html_e('Appears only when the parent option above is selected.', 'storelly-product-builder-for-woocommerce'); ?></small>
                        </div>
                    </div>
                </div>
                <div ng-show="!op.isExpand" class="nbd-attribute-name-preview" style="display:none;">{{op.name}}</div>
                <div class="nbd-attribute-action">
                    <span class="nbo-sort-group">
                        <span ng-click="sort_attribute(fieldIndex, $index, 'up')" class="dashicons dashicons-arrow-up nbo-sort-up nbo-sort" title="<?php esc_html_e('Up', 'storelly-product-builder-for-woocommerce') ?>"></span>
                        <span ng-click="sort_attribute(fieldIndex, $index, 'down')" class="dashicons dashicons-arrow-down nbo-sort-down nbo-sort" title="<?php esc_html_e('Down', 'storelly-product-builder-for-woocommerce') ?>"></span>
                    </span>
                    <a class="button nbd-mini-btn" ng-click="remove_attribute(fieldIndex, 'attributes', $index)" title="<?php esc_html_e('Delete', 'storelly-product-builder-for-woocommerce'); ?>"><span class="dashicons dashicons-no-alt"></span></a>
                    <a class="button nbd-mini-btn" ng-click="toggle_expand_attribute(fieldIndex, opIndex)" title="<?php esc_html_e('Expend', 'storelly-product-builder-for-woocommerce'); ?>">
                        <span ng-show="op.isExpand" class="dashicons dashicons-arrow-up"></span>
                        <span ng-show="!op.isExpand" class="dashicons dashicons-arrow-down"></span>
                    </a>
                </div>
                <div class="clear"></div>
            </div>
            <div class="v2-attr-addrow">
                <a class="button button-primary v2-attr-add-btn" ng-click="add_attribute(fieldIndex, 'attributes')">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e('Add attribute', 'storelly-product-builder-for-woocommerce'); ?>
                </a>
                <small class="v2-attr-addrow__hint"><?php esc_html_e('Add another choice the customer can pick (e.g. another paper size, color, or finish).', 'storelly-product-builder-for-woocommerce'); ?></small>
            </div>
        </div>
    </div>
</div>
<?php echo '</script>';
