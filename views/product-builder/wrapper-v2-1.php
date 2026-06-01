<?php
    /**
     * Designer Customizer — V2 (Cloodo-style) modal wrapper.
     *
     * Layout:
     *   ┌──────────────────────── header (title + meta + close) ────────────────┐
     *   │  parts list (left)  │       canvas + stages       │  options (right)  │
     *   ├───────────────────────────────────────────────────────────────────────┤
     *   │                              footer (cancel · save)                   │
     *   └───────────────────────────────────────────────────────────────────────┘
     *
     * Both side columns are ALWAYS visible (no more single-pane swap that hides
     * the component list when you pick a part). All Angular bindings & DOM IDs
     * (#nbdpb-app, #nbpb-container, ng-controller="nbpbCtrl", #canvas-N,
     * .nbpb-stage-loading, .close-popup, .nbdpb-load-page, etc.) are preserved
     * verbatim so app-product-builder.js works unchanged.
     *
     * The legacy single-pane UI lives at wrapper-legacy.php as a backup; the
     * modal handler can switch to it via the `spbwc_use_legacy_customizer`
     * filter or `SPBWC_USE_LEGACY_CUSTOMIZER` constant.
     *
     * Price tags on each option come from `sattr.price` (added by
     * $scope.getComponentConfigs in app-product-builder.js) and are rendered via
     * $scope.formatPrice($value).
     */
    if ( ! defined( 'ABSPATH' ) ) exit;
    if( !(isset($is_nbpb_creating_task) && $is_nbpb_creating_task) ){
        $is_creating_task = 0; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable defined by parent template.
        include 'js_config.php';
    }
    if($is_creating_task == 1): // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable defined by parent template.
?>
<div class="nbdpb-load-page nbdpb-show">
    <div class="nbpb-loader-card">
        <div class="nbpb-loader">
            <svg class="circular" viewBox="25 25 50 50">
                <circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/>
            </svg>
        </div>
        <div class="nbpb-loader-label" data-spbwc-loader-label><?php esc_html_e( 'Loading…', 'storelly-product-builder-for-woocommerce' ); ?></div>
        <div class="nbpb-loader-track"><div class="nbpb-loader-fill" data-spbwc-loader-fill></div></div>
    </div>
</div>
<?php endif; ?>
<div class="nbdpb-popup popup-design spbwc-cust-v2 <?php echo esc_attr($is_creating_task == 0 && is_admin_bar_showing()) ? 'is-admin-bar' : ''; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable defined by parent template. ?>" data-animate="scale">
    <?php if( $is_creating_task == 0 ): // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable defined by parent template. ?>
    <!-- Loader: same multi-step progress card as the storefront (driven by $scope.setBuilderProgress). -->
    <div class="nbdpb-load-page">
        <div class="nbpb-loader-card">
            <div class="nbpb-loader">
                <svg class="circular" viewBox="25 25 50 50">
                    <circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/>
                </svg>
            </div>
            <div class="nbpb-loader-label" data-spbwc-loader-label><?php esc_html_e( 'Loading…', 'storelly-product-builder-for-woocommerce' ); ?></div>
            <div class="nbpb-loader-track" aria-hidden="true"><div class="nbpb-loader-fill" data-spbwc-loader-fill></div></div>
        </div>
    </div>

    <!-- Header bar — title + close. .close-popup retained for legacy click handler. -->
    <header class="spbwc-cust-header">
        <div class="spbwc-cust-header__title">
            <span class="spbwc-cust-header__brand"><?php esc_html_e( 'Customize', 'storelly-product-builder-for-woocommerce' ); ?></span>
            <span class="spbwc-cust-header__sub" ng-show="resource.components.length"><?php esc_html_e( 'Pick a part, then choose how it looks.', 'storelly-product-builder-for-woocommerce' ); ?></span>
        </div>
        <button class="close-popup spbwc-cust-header__close" type="button" aria-label="<?php esc_attr_e( 'Close', 'storelly-product-builder-for-woocommerce' ); ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
    </header>
    <?php endif; ?>

    <div id="nbdpb-app" class="nbdpb-product-builder spbwc-cust-app">
        <div ng-controller="nbpbCtrl" class="spbwc-cust-body">
            <div id="nbpb-container" class="spbwc-cust-container">

                <!-- LEFT: parts list (always visible) -->
                <aside class="spbwc-cust-parts">
                    <h3 class="spbwc-cust-parts__title"><?php esc_html_e( 'Parts', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                    <div class="spbwc-cust-parts__list">
                        <button type="button" nbpb-hover="{{component.id}}" ng-show="component.enable" ng-repeat="component in resource.components" ng-click="showAttribute($index)" ng-class="{'is-active': resource.showValue && $index == resource.currentComponent}" class="spbwc-cust-part">
                            <span class="spbwc-cust-part__icon" ng-if="component.nbpb_type == 'nbpb_com'">
                                <img ng-src="{{component.general.component_icon_url}}" alt="">
                            </span>
                            <span class="spbwc-cust-part__icon spbwc-cust-part__icon--text" ng-if="component.nbpb_type == 'nbpb_text'" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/></svg>
                            </span>
                            <span class="spbwc-cust-part__icon spbwc-cust-part__icon--image" ng-if="component.nbpb_type == 'nbpb_image'" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                            </span>
                            <span class="spbwc-cust-part__name">{{component.general.title}}</span>
                        </button>
                    </div>
                </aside>

                <!-- MIDDLE: canvas + stages -->
                <section class="spbwc-cust-canvas">
                    <div class="design-main">
                        <div class="design-layer">
                            <div class="design-stages nbdpb-carousel-outer">
                                <div class="nbdpb-carousel">
                                    <div ng-repeat="stage in stages" ng-class="{'nbdpb-active': $index == 0}" class="nbdpb-carousel-item nbdpb-full-contain">
                                        <div class="stage nbdpb-full-contain" id='stage-{{$index}}' data-stage="{{$index}}">
                                            <div class="stage-main">
                                                <div class="nbpb-background"></div>
                                                <div class="design-zone nbdpb-full-contain" ng-style="{'width': stage.config.width + 'px', 'height': stage.config.height + 'px', 'top': stage.config.top + 'px', 'left': stage.config.left + 'px', 'background-image': 'url(' + resource.views[$index].base_url + ')'}">
                                                    <canvas nbd-canvas class="nbdpb-full-contain" stage="stage" index="{{$index}}" id="canvas-{{$index}}" last="{{$last ? 1 : 0}}"></canvas>
                                                    <div class="nbpb-overlay"></div>
                                                </div>
                                            </div>
                                            <div class="attr-name" style="display: none"><span>{{resource.components[resource.currentComponent].name}}</span></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="nbpb-stage-loading">
                                    <div class="nbpb-loader">
                                        <svg class="circular" viewBox="25 25 50 50">
                                            <circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Admin tools: only when a layer is selected. Same ng-bindings as legacy. -->
                        <div class="design-admin-tool spbwc-cust-tools nbdpb-show" ng-if="stages[currentStage].states.showAdminTool">
                            <div class="tools">
                                <div class="tool-item" title="<?php esc_html_e('Bring Forward', 'storelly-product-builder-for-woocommerce'); ?>" ng-click="setStackPosition('bring-forward')"><i class="icon-nbd icon-nbd-bring-forward"></i></div>
                                <div class="tool-item" title="<?php esc_html_e('Send To Backward', 'storelly-product-builder-for-woocommerce'); ?>" ng-click="setStackPosition('send-backward')"><i class="icon-nbd icon-nbd-sent-to-backward"></i></div>
                                <div class="tool-item" title="<?php esc_html_e('Zoom', 'storelly-product-builder-for-woocommerce'); ?>">
                                    <i class="icon-nbd nbpb-zoom-icon">
                                        <svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#666" d="M15.504 13.616l-3.79-3.223c-0.392-0.353-0.811-0.514-1.149-0.499 0.895-1.048 1.435-2.407 1.435-3.893 0-3.314-2.686-6-6-6s-6 2.686-6 6 2.686 6 6 6c1.486 0 2.845-0.54 3.893-1.435-0.016 0.338 0.146 0.757 0.499 1.149l3.223 3.79c0.552 0.613 1.453 0.665 2.003 0.115s0.498-1.452-0.115-2.003zM6 10c-2.209 0-4-1.791-4-4s1.791-4 4-4 4 1.791 4 4-1.791 4-4 4z"></path></svg>
                                    </i>
                                    <div class="nbpb-config-panel">
                                        <span style="margin-right: 10px;line-height: 30px;"><?php esc_html_e('Zoom', 'storelly-product-builder-for-woocommerce'); ?></span>
                                        <span class="nbpb-zoom-act" ng-click="updateLayerAttribute('scaleX', stages[currentStage].states.scaleX * 0.9);updateLayerAttribute('scaleY', stages[currentStage].states.scaleY * 0.9)" title="<?php esc_html_e('Zoom out', 'storelly-product-builder-for-woocommerce'); ?>">-</span>
                                        <span class="nbpb-zoom-act" ng-click="updateLayerAttribute('scaleX', stages[currentStage].states.scaleX * 1.1);updateLayerAttribute('scaleY', stages[currentStage].states.scaleY * 1.1)" title="<?php esc_html_e('Zoom in', 'storelly-product-builder-for-woocommerce'); ?>">+</span>
                                    </div>
                                </div>
                                <div class="tool-item" title="<?php esc_html_e('Rotate', 'storelly-product-builder-for-woocommerce'); ?>">
                                    <i class="icon-nbd nbpb-rotate-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#666" d="M5.46899843,18.5310016 L18.5310016,5.46899843 C19.4496661,4.55033391 20.5031311,4.20999878 21.4148051,4.58520207 C22.3264792,4.96040535 22.9395946,5.95385041 22.9395946,7.27264838 L22.9395946,16.7273516 C22.9395946,18.0461496 22.3264792,19.0395946 21.4148051,19.4147979 C20.5031311,19.7900012 19.4496661,19.4496661 18.5310016,18.5310016 L5.46899843,5.46899843 C4.55033391,4.55033391 4.20999878,5.49462891 4.58520207,4.58520207 C4.96040535,3.67577522 5.95385041,3.06265981 7.27264838,3.06265981 L16.7273516,3.06265981 C18.0461496,3.06265981 19.0395946,3.67577522 19.4147979,4.58520207 C19.7900012,5.49462891 19.4496661,6.54809456 18.5310016,7.4667591 L5.46899843,20.5287623 C4.55033391,21.4474269 3.49686826,21.7877619 2.58520207,21.4125586 C1.67577522,21.0373553 1.06265981,20.0439104 1.06265981,18.7251123 L1.06265981,9.27246094 C1.06265981,7.95366296 1.67577522,6.96021791 2.58520207,6.58501462 C3.49462891,6.20981134 4.55033391,6.55014648 5.46899843,7.4667591"></path></svg>
                                    </i>
                                    <div class="nbpb-config-panel">
                                        <span style="margin-right: 10px;line-height: 30px;"><?php esc_html_e('Rotate', 'storelly-product-builder-for-woocommerce'); ?></span>
                                        <span class="nbpb-rotate-act" ng-click="updateLayerAttribute('angle', stages[currentStage].states.angle - 10)" title="<?php esc_html_e('Rotate left', 'storelly-product-builder-for-woocommerce'); ?>">↺</span>
                                        <span class="nbpb-rotate-act" ng-click="updateLayerAttribute('angle', stages[currentStage].states.angle + 10)" title="<?php esc_html_e('Rotate right', 'storelly-product-builder-for-woocommerce'); ?>">↻</span>
                                    </div>
                                </div>
                                <div class="tool-item" title="<?php esc_html_e('Clear all layer', 'storelly-product-builder-for-woocommerce'); ?>" ng-click="clearAllStages()"><i class="icon-nbd icon-nbd-clear"></i></div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- RIGHT: options of the selected component (always visible; empty-state when none picked) -->
                <aside class="spbwc-cust-options">
                    <div class="spbwc-cust-empty" ng-if="!resource.showValue">
                        <div class="spbwc-cust-empty__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11V6a3 3 0 0 1 6 0v5"/><path d="M9 11h6a4 4 0 0 1 4 4v3a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4v-3a4 4 0 0 1 4-4Z"/></svg>
                        </div>
                        <h3 class="spbwc-cust-empty__title"><?php esc_html_e( 'Pick a part to customize', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                        <p class="spbwc-cust-empty__desc"><?php esc_html_e( 'Choose any part on the left, then customize its colour, text or image — your design updates in real-time on the canvas.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                    </div>

                    <div class="spbwc-cust-vals" ng-if="resource.showValue">
                        <header class="spbwc-cust-vals__header">
                            <h3 class="spbwc-cust-vals__title">{{resource.components[resource.currentComponent].general.title}}</h3>
                            <p class="spbwc-cust-vals__desc" ng-show="resource.components[resource.currentComponent].general.description">{{resource.components[resource.currentComponent].general.description}}</p>
                        </header>

                        <!-- nbpb_com: grid of choice cards WITH PRICES -->
                        <div class="spbwc-cust-val-grid" ng-if="resource.components[resource.currentComponent].nbpb_type == 'nbpb_com'">
                            <button type="button" ng-repeat="sattr in resource.components[resource.currentComponent].current_pb_configs" ng-click="selectAttribute($index)" ng-class="{'is-active': $index == resource.components[resource.currentComponent].currentConfig}" class="spbwc-cust-val">
                                <span class="spbwc-cust-val__swatch" ng-style="{'background': sattr.bg_type == 'i' ? 'url(' + sattr.icon_bg + ')' : sattr.icon_color}"></span>
                                <span class="spbwc-cust-val__body">
                                    <span class="spbwc-cust-val__name" ng-bind="sattr.attr_name || sattr.sattr_name"></span>
                                    <span class="spbwc-cust-val__sub" ng-show="sattr.attr_name && sattr.sattr_name">{{sattr.sattr_name}}</span>
                                </span>
                                <span class="spbwc-cust-val__price" ng-if="sattr.price > 0" ng-bind="formatPrice(sattr.price)"></span>
                                <span class="spbwc-cust-val__price spbwc-cust-val__price--free" ng-if="!sattr.price"><?php esc_html_e( 'Free', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                <span class="spbwc-cust-val__check" aria-hidden="true">✓</span>
                            </button>
                        </div>

                        <!-- nbpb_text: form fields -->
                        <div class="spbwc-cust-form spbwc-cust-form--text" ng-if="resource.components[resource.currentComponent].nbpb_type == 'nbpb_text'">
                            <div class="spbwc-cust-field">
                                <label class="spbwc-cust-field__label"><?php esc_html_e( 'Content', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                <input class="spbwc-cust-field__input" ng-change="updateText()" maxlength="{{resource.components[resource.currentComponent].general.text_option.max}}" placeholder="{{resource.components[resource.currentComponent].general.nbpb_text_configs.default_text}}" ng-model="resource.components[resource.currentComponent].currentContent" />
                            </div>
                            <div class="spbwc-cust-field" ng-if="resource.currentComponentObj.general.nbpb_text_configs.allow_font_family == 'y' || settings.is_creating_task == 1">
                                <label class="spbwc-cust-field__label"><?php esc_html_e( 'Font family', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                <select class="spbwc-cust-field__select" ng-change="updateText()" ng-model="resource.components[resource.currentComponent].currentFontId" ng-if="resource.currentComponentObj.general.nbpb_text_configs.allow_all_font == 'y'">
                                    <?php foreach($fonts as $font): // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable defined by parent template. ?>
                                        <?php
                                            $font_prefix = ($font->type == 'google') ? 'g' : 'c'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
                                            $font_value  = $font_prefix . $font->id; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
                                        ?>
                                    <option value="<?php echo esc_attr( $font_value ); ?>"><?php echo esc_html($font->name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select class="spbwc-cust-field__select" ng-change="updateText()" ng-model="resource.components[resource.currentComponent].currentFontId" ng-if="resource.currentComponentObj.general.nbpb_text_configs.allow_all_font == 'n'">
                                    <option ng-if="settings.custom_fonts[font]" ng-repeat="font in resource.currentComponentObj.general.nbpb_text_configs.custom_fonts" value="{{'c' + font }}">{{settings.custom_fonts[font].name}}</option>
                                    <option ng-if="settings.google_fonts[font]" ng-repeat="font in resource.currentComponentObj.general.nbpb_text_configs.google_fonts" value="{{'g' + font }}">{{settings.google_fonts[font].name}}</option>
                                </select>
                            </div>
                            <div class="spbwc-cust-field" ng-if="resource.currentComponentObj.general.nbpb_text_configs.allow_change_color == 'y' || settings.is_creating_task == 1">
                                <label class="spbwc-cust-field__label"><?php esc_html_e( 'Colour', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                <div class="spbwc-cust-color-row" ng-if="resource.currentComponentObj.general.nbpb_text_configs.allow_all_color == 'n'">
                                    <span class="spbwc-cust-color" ng-click="resource.components[resource.currentComponent].currentColor = color.color;updateText()" ng-class="{'is-active': resource.components[resource.currentComponent].currentColor == color.color}" ng-repeat="color in resource.currentComponentObj.general.nbpb_text_configs.colors" ng-style="{'background': color.color}" title="{{color.name}}"></span>
                                </div>
                                <div ng-show="resource.currentComponentObj.general.nbpb_text_configs.allow_all_color == 'y' || settings.is_creating_task == 1">
                                    <input class="nbpb-color-picker" on-change="selectColor(color)" options="resource.colorOptions" />
                                </div>
                            </div>
                            <button ng-click="deleteLayer('text')" class="spbwc-cust-btn spbwc-cust-btn--danger" type="button"><?php esc_html_e( 'Delete this text', 'storelly-product-builder-for-woocommerce' ); ?></button>
                        </div>

                        <!-- nbpb_image: upload zone -->
                        <div class="spbwc-cust-form spbwc-cust-form--image" ng-if="resource.components[resource.currentComponent].nbpb_type == 'nbpb_image'">
                            <div class="spbwc-cust-upload upload-zone" data-field-id="{{resource.currentComponentObj.id}}" nbd-dnd-file="uploadImage(field_id, files)">
                                <input type="file" autocomplete="off" class="inputfile" accept=".png,.jpg,.jpeg"/>
                                <label>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
                                    <span class="spbwc-cust-upload__title"><?php esc_html_e( 'Click or drop file here', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                    <span class="spbwc-cust-upload__note" ng-if="resource.currentComponentObj.general.upload_option.allow_type != ''"><?php esc_html_e( 'Allow extensions', 'storelly-product-builder-for-woocommerce' ); ?>: {{resource.currentComponentObj.general.upload_option.allow_type}}</span>
                                    <span class="spbwc-cust-upload__note" ng-if="resource.currentComponentObj.general.upload_option.min_size != ''"><?php esc_html_e( 'Min size', 'storelly-product-builder-for-woocommerce' ); ?> {{resource.currentComponentObj.general.upload_option.min_size}} MB</span>
                                    <span class="spbwc-cust-upload__note" ng-if="resource.currentComponentObj.general.upload_option.max_size != ''"><?php esc_html_e( 'Max size', 'storelly-product-builder-for-woocommerce' ); ?> {{resource.currentComponentObj.general.upload_option.max_size}} MB</span>
                                </label>
                                <svg class="nbd-upload-loading" xmlns="http://www.w3.org/2000/svg" width="50px" height="50px" viewBox="0 0 50 50"><circle fill="none" opacity="0.05" stroke="#000000" stroke-width="3" cx="25" cy="25" r="20"/><g transform="translate(25,25) rotate(-90)"><circle  style="stroke:#48B0F7; fill:none; stroke-width: 3px; stroke-linecap: round" stroke-dasharray="110" stroke-dashoffset="0"  cx="0" cy="0" r="20"><animate attributeName="stroke-dashoffset" values="360;140" dur="2.2s" keyTimes="0;1" calcMode="spline" fill="freeze" keySplines="0.41,0.314,0.8,0.54" repeatCount="indefinite" begin="0"/><animateTransform attributeName="transform" type="rotate" values="0;274;360" keyTimes="0;0.74;1" calcMode="linear" dur="2.2s" repeatCount="indefinite" begin="0"/><animate attributeName="stroke" values="#10CFBD;#48B0F7;#ff0066;#48B0F7;#10CFBD" fill="freeze" dur="3s" begin="0" repeatCount="indefinite"/></circle></g></svg>
                            </div>
                            <div class="nbpb-uploaded spbwc-cust-uploaded" ng-show="resource.uploaded.length">
                                <img ng-click="addImage(img)" ng-repeat="img in resource.uploaded" ng-src="{{img}}" />
                            </div>
                            <button ng-click="deleteLayer('image')" class="spbwc-cust-btn spbwc-cust-btn--danger" type="button"><?php esc_html_e( 'Delete this image', 'storelly-product-builder-for-woocommerce' ); ?></button>
                        </div>

                        <!-- Legacy "save layer" affordance kept for compat -->
                        <div class="product-value-act" style="display:none">
                            <div class="value-act-finish value-act-item" ng-click="saveLayer()"></div>
                        </div>
                    </div>
                </aside>

            </div>
        </div>
    </div>

    <?php if( $is_creating_task == 0 ): // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable defined by parent template. ?>
    <!-- Footer: primary action is "Save & continue"; "Cancel" closes via legacy .close-popup. -->
    <footer class="spbwc-cust-footer">
        <button class="close-popup spbwc-cust-btn spbwc-cust-btn--ghost" type="button"><?php esc_html_e( 'Cancel', 'storelly-product-builder-for-woocommerce' ); ?></button>
        <button class="spbwc-cust-btn spbwc-cust-btn--primary" ng-click="saveData()" type="button">
            <span><?php esc_html_e( 'Save &amp; continue', 'storelly-product-builder-for-woocommerce' ); ?></span>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </button>
    </footer>
    <?php endif; ?>
</div>
