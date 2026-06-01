<?php
    /**
     * Designer Customizer — V3 (Tab Nav + Accordion + Summary).
     *
     * Pattern reference: Printcart Canva v2.0 — a 5-zone editor layout.
     *
     *   ┌────────────────────── TOPBAR ───────────────────────────────────┐
     *   │ Brand · product spec line · base price        Undo Redo  Close  │
     *   ├──────┬────────────────┬─────────────────────┬─────────────────────┤
     *   │ TAB  │  ACCORDION     │                     │  SUMMARY            │
     *   │ NAV  │  PANEL 280px   │     CANVAS          │  340px              │
     *   │ 64px │                │                     │   design summary    │
     *   │      │  Components    │                     │   breakdown rows    │
     *   │ 🖌️  │  ▾ Frame Color │                     │   live total        │
     *   │ T   │   • Solid Blue │                     │   Add to cart CTA   │
     *   │ 📷  │   • Solid Green│                     │   Reset all (ghost) │
     *   │ 🎨  │   ↻ Reset part │                     │                     │
     *   │ ⚡  │  ▸ Decals      │                     │                     │
     *   │     │  ▸ Logos       │                     │                     │
     *   │     │  Progress: ▓▓▒ │                     │                     │
     *   └──────┴────────────────┴─────────────────────┴─────────────────────┘
     *
     * Why Tab Nav vertical (not just accordion):
     *   Future expansion — extra tabs (Design by AI, FAQ, Templates,
     *   Cliparts) can be added without restructuring the layout. Today
     *   we ship only the "Customize" tab + placeholders for "AI" and
     *   "Help" so the affordance is visible.
     *
     * All Angular bindings, DOM IDs (#nbdpb-app, #nbpb-container, #canvas-N,
     * .nbpb-stage-loading, .nbdpb-carousel, .close-popup, .nbdpb-load-page)
     * and ng-* directives are preserved verbatim so app-product-builder.js
     * works without changes to bootstrapping or popup logic. Two earlier
     * wrappers remain available for rollback via the
     * `spbwc_use_legacy_customizer` filter or `SPBWC_USE_LEGACY_CUSTOMIZER`
     * constant — wrapper-legacy.php (single-pane) and wrapper-v2-1.php
     * (V2 3-column without summary).
     */
    if ( ! defined( 'ABSPATH' ) ) exit;
    if( !(isset($is_nbpb_creating_task) && $is_nbpb_creating_task) ){
        $is_creating_task = 0; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable defined by parent template.
        include 'js_config.php';
    }

    /* Compose product display data for the V3 TOPBAR + Summary header. The
     * customizer modal lives in wp_footer on single-product pages, so we can
     * safely call WC product helpers here. Fall back to neutral strings if
     * outside a product context (admin create-task flow). */
    $spbwc_v3_product_name = '';
    $spbwc_v3_product_specs = '';
    $spbwc_v3_base_price_html = ''; // Single current price formatted via wc_price() — no del/ins strike markup. JS overwrites with live total via $scope.formatMoney().
    $spbwc_v3_base_price_raw = 0;
    $spbwc_v3_regular_price_raw = 0;
    $spbwc_v3_is_on_sale = false;
    $spbwc_v3_product_thumb = '';
    if ( $is_creating_task == 0 && function_exists( 'wc_get_product' ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable defined by parent template.
        $spbwc_v3_product = wc_get_product( get_the_ID() );
        if ( $spbwc_v3_product ) {
            $spbwc_v3_product_name      = $spbwc_v3_product->get_name();
            $spbwc_v3_base_price_raw    = (float) $spbwc_v3_product->get_price();
            $spbwc_v3_regular_price_raw = (float) $spbwc_v3_product->get_regular_price();
            $spbwc_v3_is_on_sale        = $spbwc_v3_product->is_on_sale();
            /* Use wc_price() to get just the current price formatted with the
             * store currency — no strikethrough markup. The sale-vs-regular
             * is surfaced as a small "Save $X" badge elsewhere, not as two
             * stacked amounts inside the customizer. */
            $spbwc_v3_base_price_html   = wc_price( $spbwc_v3_base_price_raw );
            $spbwc_v3_product_thumb     = get_the_post_thumbnail_url( $spbwc_v3_product->get_id(), 'thumbnail' );
            /* Spec line — short categorical hint (e.g. "Bicycles · Frame builder"). */
            $cats = wp_get_post_terms( $spbwc_v3_product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
            if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
                $spbwc_v3_product_specs = implode( ' · ', array_slice( $cats, 0, 2 ) ) . ' · ' . esc_html__( 'Custom builder', 'storelly-product-builder-for-woocommerce' );
            } else {
                $spbwc_v3_product_specs = esc_html__( 'Custom builder', 'storelly-product-builder-for-woocommerce' );
            }
        }
    }
?>
<?php if($is_creating_task == 1): // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable defined by parent template. ?>
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
<div class="nbdpb-popup popup-design spbwc-cust-v3 <?php echo esc_attr($is_creating_task == 0 && is_admin_bar_showing()) ? 'is-admin-bar' : ''; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable defined by parent template. ?>" data-animate="scale">
    <?php if( $is_creating_task == 0 ): // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable defined by parent template. ?>
    <!-- Loader (same multi-step progress card; driven by $scope.setBuilderProgress). -->
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

    <!-- ======================== TOPBAR ============================== -->
    <header class="spbwc-cust-topbar">
        <div class="spbwc-cust-topbar__brand">
            <?php if ( $spbwc_v3_product_thumb ) : ?>
                <img class="spbwc-cust-topbar__thumb" src="<?php echo esc_url( $spbwc_v3_product_thumb ); ?>" alt="" />
            <?php else : ?>
                <span class="spbwc-cust-topbar__thumb spbwc-cust-topbar__thumb--ph" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V3h12v6"/><rect x="4" y="9" width="16" height="9" rx="2"/><rect x="7" y="14" width="10" height="8"/></svg>
                </span>
            <?php endif; ?>
            <div class="spbwc-cust-topbar__title">
                <div class="spbwc-cust-topbar__name"><?php echo esc_html( $spbwc_v3_product_name ?: esc_html__( 'Customize', 'storelly-product-builder-for-woocommerce' ) ); ?></div>
                <div class="spbwc-cust-topbar__sub">
                    <?php if ( $spbwc_v3_product_specs ) : ?>
                        <span class="spbwc-cust-topbar__spec"><?php echo esc_html( $spbwc_v3_product_specs ); ?></span>
                    <?php endif; ?>
                    <?php if ( $spbwc_v3_base_price_html ) : ?>
                        <span class="spbwc-cust-topbar__sep">·</span>
                        <span class="spbwc-cust-topbar__base"><?php /* translators: %s: base product price */ printf( esc_html__( 'From %s base', 'storelly-product-builder-for-woocommerce' ), wp_kses_post( $spbwc_v3_base_price_html ) ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="spbwc-cust-topbar__actions">
            <button type="button" class="spbwc-cust-iconbtn" data-spbwc-action="reset-all" title="<?php esc_attr_e( 'Reset all customizations', 'storelly-product-builder-for-woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Reset all customizations', 'storelly-product-builder-for-woocommerce' ); ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 9"/><path d="M3 3v6h6"/></svg>
            </button>
            <button class="close-popup spbwc-cust-iconbtn spbwc-cust-iconbtn--close" type="button" aria-label="<?php esc_attr_e( 'Close', 'storelly-product-builder-for-woocommerce' ); ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
    </header>
    <?php endif; ?>

    <div id="nbdpb-app" class="nbdpb-product-builder spbwc-cust-app">
        <div ng-controller="nbpbCtrl" class="spbwc-cust-body">
            <div id="nbpb-container" class="spbwc-cust-container">

                <!-- ========== LEFT TAB NAV 64px ========== -->
                <nav class="spbwc-cust-tabnav" role="tablist" aria-label="<?php esc_attr_e( 'Editor tools', 'storelly-product-builder-for-woocommerce' ); ?>">
                    <button type="button" class="spbwc-cust-tabbtn is-active" role="tab" aria-selected="true" data-spbwc-tab="customize" title="<?php esc_attr_e( 'Customize', 'storelly-product-builder-for-woocommerce' ); ?>">
                        <span class="spbwc-cust-tabbtn__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19 7-7 3 3-7 7-3-3Z"/><path d="m18 13-1.5-7.5L2 2l3.5 14.5L13 18Z"/><path d="m2 2 7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
                        </span>
                        <span class="spbwc-cust-tabbtn__label"><?php esc_html_e( 'Customize', 'storelly-product-builder-for-woocommerce' ); ?></span>
                    </button>
                    <button type="button" class="spbwc-cust-tabbtn" role="tab" aria-selected="false" data-spbwc-tab="ai" data-spbwc-coming-soon="1" title="<?php esc_attr_e( 'Design with AI — coming soon', 'storelly-product-builder-for-woocommerce' ); ?>">
                        <span class="spbwc-cust-tabbtn__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="m4.93 4.93 2.83 2.83"/><path d="M2 12h4"/><path d="m4.93 19.07 2.83-2.83"/><path d="M12 18v4"/><path d="m19.07 19.07-2.83-2.83"/><path d="M22 12h-4"/><path d="m19.07 4.93-2.83 2.83"/><circle cx="12" cy="12" r="3"/></svg>
                        </span>
                        <span class="spbwc-cust-tabbtn__label"><?php esc_html_e( 'AI', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        <span class="spbwc-cust-tabbtn__badge"><?php esc_html_e( 'Soon', 'storelly-product-builder-for-woocommerce' ); ?></span>
                    </button>
                    <button type="button" class="spbwc-cust-tabbtn" role="tab" aria-selected="false" data-spbwc-tab="templates" data-spbwc-coming-soon="1" title="<?php esc_attr_e( 'Templates — coming soon', 'storelly-product-builder-for-woocommerce' ); ?>">
                        <span class="spbwc-cust-tabbtn__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                        </span>
                        <span class="spbwc-cust-tabbtn__label"><?php esc_html_e( 'Templates', 'storelly-product-builder-for-woocommerce' ); ?></span>
                    </button>
                    <button type="button" class="spbwc-cust-tabbtn" role="tab" aria-selected="false" data-spbwc-tab="help" title="<?php esc_attr_e( 'Help & FAQ', 'storelly-product-builder-for-woocommerce' ); ?>">
                        <span class="spbwc-cust-tabbtn__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                        </span>
                        <span class="spbwc-cust-tabbtn__label"><?php esc_html_e( 'Help', 'storelly-product-builder-for-woocommerce' ); ?></span>
                    </button>
                </nav>

                <!-- ========== ACCORDION PANEL 280px ========== -->
                <aside class="spbwc-cust-panel">
                    <!-- Tab content: Customize (default) -->
                    <div class="spbwc-cust-tabpanel" data-spbwc-tabpanel="customize">
                        <header class="spbwc-cust-panel__head">
                            <div class="spbwc-cust-panel__title"><?php esc_html_e( 'Customize parts', 'storelly-product-builder-for-woocommerce' ); ?></div>
                            <div class="spbwc-cust-panel__meta" data-spbwc-progress-label>0 / 0 <?php esc_html_e( 'configured', 'storelly-product-builder-for-woocommerce' ); ?></div>
                        </header>
                        <div class="spbwc-cust-panel__progress" aria-hidden="true">
                            <span class="spbwc-cust-panel__progress-fill" data-spbwc-progress-fill style="width:0"></span>
                        </div>

                        <div class="spbwc-cust-acc">
                            <!-- One accordion item per component. ng-click toggles via $scope.showAttribute($index). -->
                            <div ng-repeat="component in resource.components" ng-show="component.enable" class="spbwc-cust-acc-item" ng-class="{'is-open': resource.showValue && $index == resource.currentComponent, 'is-done': isComponentConfigured(component)}">
                                <button type="button" class="spbwc-cust-acc-head" ng-click="showAttribute($index)" aria-expanded="{{resource.showValue && $index == resource.currentComponent ? 'true' : 'false'}}">
                                    <span class="spbwc-cust-acc-step" aria-hidden="true">
                                        <svg ng-if="isComponentConfigured(component)" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        <span ng-if="!isComponentConfigured(component)" class="spbwc-cust-acc-step__dot"></span>
                                    </span>
                                    <span class="spbwc-cust-acc-info">
                                        <span class="spbwc-cust-acc-name">{{component.general.title}}</span>
                                        <span class="spbwc-cust-acc-value" ng-switch="component.nbpb_type">
                                            <span ng-switch-when="nbpb_com">{{(component.current_pb_configs[component.currentConfig].sattr_name) || (component.current_pb_configs[component.currentConfig].attr_name) || ('— ' + 'pick' )}}</span>
                                            <span ng-switch-when="nbpb_text">{{component.currentContent || ('— ' + 'add text')}}</span>
                                            <span ng-switch-when="nbpb_image"><span ng-if="resource.uploaded.length"><?php esc_html_e( 'Image uploaded', 'storelly-product-builder-for-woocommerce' ); ?></span><span ng-if="!resource.uploaded.length"><?php esc_html_e( '— upload image', 'storelly-product-builder-for-woocommerce' ); ?></span></span>
                                        </span>
                                    </span>
                                    <span class="spbwc-cust-acc-chevron" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                                    </span>
                                </button>

                                <div class="spbwc-cust-acc-body" ng-if="resource.showValue && $index == resource.currentComponent">

                                    <!-- nbpb_com: grid of option cards with prices -->
                                    <div class="spbwc-cust-val-grid" ng-if="component.nbpb_type == 'nbpb_com'">
                                        <button type="button" ng-repeat="sattr in component.current_pb_configs" ng-click="selectAttribute($index)" ng-class="{'is-active': $index == component.currentConfig}" class="spbwc-cust-val">
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
                                    <div class="spbwc-cust-form spbwc-cust-form--text" ng-if="component.nbpb_type == 'nbpb_text'">
                                        <div class="spbwc-cust-field">
                                            <label class="spbwc-cust-field__label"><?php esc_html_e( 'Content', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                            <input class="spbwc-cust-field__input" ng-change="updateText()" maxlength="{{component.general.text_option.max}}" placeholder="{{component.general.nbpb_text_configs.default_text}}" ng-model="component.currentContent" />
                                        </div>
                                        <div class="spbwc-cust-field" ng-if="resource.currentComponentObj.general.nbpb_text_configs.allow_font_family == 'y' || settings.is_creating_task == 1">
                                            <label class="spbwc-cust-field__label"><?php esc_html_e( 'Font family', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                            <select class="spbwc-cust-field__select" ng-change="updateText()" ng-model="component.currentFontId" ng-if="resource.currentComponentObj.general.nbpb_text_configs.allow_all_font == 'y'">
                                                <?php foreach($fonts as $font): // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable defined by parent template. ?>
                                                    <?php
                                                        $font_prefix = ($font->type == 'google') ? 'g' : 'c'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
                                                        $font_value  = $font_prefix . $font->id; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
                                                    ?>
                                                <option value="<?php echo esc_attr( $font_value ); ?>"><?php echo esc_html($font->name ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select class="spbwc-cust-field__select" ng-change="updateText()" ng-model="component.currentFontId" ng-if="resource.currentComponentObj.general.nbpb_text_configs.allow_all_font == 'n'">
                                                <option ng-if="settings.custom_fonts[font]" ng-repeat="font in resource.currentComponentObj.general.nbpb_text_configs.custom_fonts" value="{{'c' + font }}">{{settings.custom_fonts[font].name}}</option>
                                                <option ng-if="settings.google_fonts[font]" ng-repeat="font in resource.currentComponentObj.general.nbpb_text_configs.google_fonts" value="{{'g' + font }}">{{settings.google_fonts[font].name}}</option>
                                            </select>
                                        </div>
                                        <div class="spbwc-cust-field" ng-if="resource.currentComponentObj.general.nbpb_text_configs.allow_change_color == 'y' || settings.is_creating_task == 1">
                                            <label class="spbwc-cust-field__label"><?php esc_html_e( 'Colour', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                            <div class="spbwc-cust-color-row" ng-if="resource.currentComponentObj.general.nbpb_text_configs.allow_all_color == 'n'">
                                                <span class="spbwc-cust-color" ng-click="component.currentColor = color.color;updateText()" ng-class="{'is-active': component.currentColor == color.color}" ng-repeat="color in resource.currentComponentObj.general.nbpb_text_configs.colors" ng-style="{'background': color.color}" title="{{color.name}}"></span>
                                            </div>
                                            <div ng-show="resource.currentComponentObj.general.nbpb_text_configs.allow_all_color == 'y' || settings.is_creating_task == 1">
                                                <input class="nbpb-color-picker" on-change="selectColor(color)" options="resource.colorOptions" />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- nbpb_image: upload zone -->
                                    <div class="spbwc-cust-form spbwc-cust-form--image" ng-if="component.nbpb_type == 'nbpb_image'">
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
                                    </div>

                                    <!-- Per-part reset + delete affordances -->
                                    <div class="spbwc-cust-acc-foot">
                                        <button type="button" class="spbwc-cust-linkbtn" data-spbwc-action="reset-part" data-spbwc-part-index="{{$index}}" title="<?php esc_attr_e( 'Reset this part to its default', 'storelly-product-builder-for-woocommerce' ); ?>">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 9"/><path d="M3 3v6h6"/></svg>
                                            <?php esc_html_e( 'Reset this part', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </button>
                                        <button ng-if="component.nbpb_type == 'nbpb_text'" ng-click="deleteLayer('text')" class="spbwc-cust-linkbtn spbwc-cust-linkbtn--danger" type="button"><?php esc_html_e( 'Delete text', 'storelly-product-builder-for-woocommerce' ); ?></button>
                                        <button ng-if="component.nbpb_type == 'nbpb_image'" ng-click="deleteLayer('image')" class="spbwc-cust-linkbtn spbwc-cust-linkbtn--danger" type="button"><?php esc_html_e( 'Delete image', 'storelly-product-builder-for-woocommerce' ); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Legacy "save layer" affordance kept for compat -->
                        <div class="product-value-act" style="display:none">
                            <div class="value-act-finish value-act-item" ng-click="saveLayer()"></div>
                        </div>
                    </div>

                    <!-- Tab content: AI (placeholder) -->
                    <div class="spbwc-cust-tabpanel" data-spbwc-tabpanel="ai" hidden>
                        <div class="spbwc-cust-comingsoon">
                            <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3v4M3 5h4M6 17v4M4 19h4M13 3l1.5 4.5L19 9l-4.5 1.5L13 15l-1.5-4.5L7 9l4.5-1.5z"/></svg>
                            <h3><?php esc_html_e( 'Design with AI', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                            <p><?php esc_html_e( 'Describe what you want and AI will suggest a starting design. Coming soon.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                        </div>
                    </div>

                    <!-- Tab content: Templates (placeholder) -->
                    <div class="spbwc-cust-tabpanel" data-spbwc-tabpanel="templates" hidden>
                        <div class="spbwc-cust-comingsoon">
                            <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                            <h3><?php esc_html_e( 'Pre-made templates', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                            <p><?php esc_html_e( 'Start from a designer-curated configuration. Coming soon.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                        </div>
                    </div>

                    <!-- Tab content: Help -->
                    <div class="spbwc-cust-tabpanel" data-spbwc-tabpanel="help" hidden>
                        <header class="spbwc-cust-panel__head">
                            <div class="spbwc-cust-panel__title"><?php esc_html_e( 'How it works', 'storelly-product-builder-for-woocommerce' ); ?></div>
                        </header>
                        <div class="spbwc-cust-help">
                            <details class="spbwc-cust-faq" open>
                                <summary><?php esc_html_e( 'How do I customize?', 'storelly-product-builder-for-woocommerce' ); ?></summary>
                                <p><?php esc_html_e( 'Open each part on the left, pick your option, and watch the preview update on the right. The total updates in real time.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                            </details>
                            <details class="spbwc-cust-faq">
                                <summary><?php esc_html_e( 'Will my design be saved?', 'storelly-product-builder-for-woocommerce' ); ?></summary>
                                <p><?php esc_html_e( 'Click "Add to cart" and we lock in your configuration with the order. You can re-order the exact same design from your account.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                            </details>
                            <details class="spbwc-cust-faq">
                                <summary><?php esc_html_e( 'Can I undo a change?', 'storelly-product-builder-for-woocommerce' ); ?></summary>
                                <p><?php esc_html_e( 'Use "Reset this part" inside each option, or "Reset all" in the top right to start over.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                            </details>
                        </div>
                    </div>
                </aside>

                <!-- ========== CANVAS ========== -->
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
                        <!-- View switcher (Printcart Canva Front/Back pattern).
                             Shown only when product has ≥2 stages/views — uses the
                             existing scope.changeStage(idx) handler. -->
                        <div class="spbwc-cust-viewswitch" ng-if="stages.length > 1">
                            <button type="button" class="spbwc-cust-viewswitch__btn" ng-click="changeStage(($index - 1 + stages.length) % stages.length)" ng-disabled="stages.length < 2" title="<?php esc_attr_e( 'Previous view', 'storelly-product-builder-for-woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Previous view', 'storelly-product-builder-for-woocommerce' ); ?>">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                            </button>
                            <div class="spbwc-cust-viewswitch__pills" role="tablist">
                                <button type="button" ng-repeat="stage in stages" ng-click="changeStage($index)" ng-class="{'is-active': $index == currentStage}" class="spbwc-cust-viewswitch__pill" role="tab" aria-selected="{{$index == currentStage ? 'true' : 'false'}}">
                                    <span ng-bind="resource.views[$index].name || ('View ' + ($index + 1))"></span>
                                </button>
                            </div>
                            <button type="button" class="spbwc-cust-viewswitch__btn" ng-click="changeStage((currentStage + 1) % stages.length)" ng-disabled="stages.length < 2" title="<?php esc_attr_e( 'Next view', 'storelly-product-builder-for-woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Next view', 'storelly-product-builder-for-woocommerce' ); ?>">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                        </div>

                        <!-- Auto-saved pill (Printcart Canva pattern): bottom-left affordance
                             that signals draft persistence; remains static for now (no
                             autosave backend) but reads honestly as "Live preview". -->
                        <div class="spbwc-cust-autosave" aria-hidden="true">
                            <span class="spbwc-cust-autosave__dot"></span>
                            <span><?php esc_html_e( 'Live preview', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        </div>

                        <!-- Admin tools (layer transform): only when a layer is selected. -->
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
                                <div class="tool-item" title="<?php esc_html_e('Clear all layer', 'storelly-product-builder-for-woocommerce'); ?>" ng-click="clearAllStages()"><i class="icon-nbd icon-nbd-clear"></i></div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========== SUMMARY 300px (Printcart Canva pattern) ========== -->
                <aside class="spbwc-cust-summary">
                    <!-- ORDER SUMMARY · 1 ITEM section -->
                    <div class="spbwc-cust-summary__section">
                        <div class="spbwc-cust-summary__caption"><?php esc_html_e( 'Order summary · 1 item', 'storelly-product-builder-for-woocommerce' ); ?></div>
                        <div class="spbwc-cust-summary__item">
                            <?php if ( $spbwc_v3_product_thumb ) : ?>
                                <img class="spbwc-cust-summary__item-thumb" src="<?php echo esc_url( $spbwc_v3_product_thumb ); ?>" alt="" />
                            <?php else : ?>
                                <span class="spbwc-cust-summary__item-thumb spbwc-cust-summary__item-thumb--ph" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                </span>
                            <?php endif; ?>
                            <div class="spbwc-cust-summary__item-body">
                                <div class="spbwc-cust-summary__item-name"><?php echo esc_html( $spbwc_v3_product_name ?: esc_html__( 'Custom item', 'storelly-product-builder-for-woocommerce' ) ); ?> #1</div>
                                <div class="spbwc-cust-summary__item-spec" data-spbwc-summary-spec>
                                    <span ng-repeat="component in resource.components" ng-show="component.enable && component.nbpb_type == 'nbpb_com' && component.current_pb_configs[component.currentConfig]"><span ng-if="!$first"> · </span>{{(component.current_pb_configs[component.currentConfig].sattr_name) || (component.current_pb_configs[component.currentConfig].attr_name)}}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Price breakdown — Printcart Canva pattern -->
                    <div class="spbwc-cust-summary__breakdown">
                        <div class="spbwc-cust-summary__row spbwc-cust-summary__row--base">
                            <span class="spbwc-cust-summary__label"><?php esc_html_e( 'Base price', 'storelly-product-builder-for-woocommerce' ); ?></span>
                            <span class="spbwc-cust-summary__val" data-spbwc-base-price><?php echo wp_kses_post( $spbwc_v3_base_price_html ); ?></span>
                        </div>
                        <div class="spbwc-cust-summary__row" ng-repeat="component in resource.components" ng-show="component.enable">
                            <span class="spbwc-cust-summary__label" ng-bind="component.general.title"></span>
                            <span class="spbwc-cust-summary__val" ng-switch="component.nbpb_type">
                                <span ng-switch-when="nbpb_com">
                                    <span class="spbwc-cust-summary__choice" ng-if="component.current_pb_configs[component.currentConfig]" ng-bind="(component.current_pb_configs[component.currentConfig].sattr_name) || (component.current_pb_configs[component.currentConfig].attr_name)"></span>
                                    <span class="spbwc-cust-summary__price spbwc-cust-summary__price--inc" ng-if="component.current_pb_configs[component.currentConfig] && !component.current_pb_configs[component.currentConfig].price"><?php esc_html_e( 'Included', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                    <span class="spbwc-cust-summary__price spbwc-cust-summary__price--add" ng-if="component.current_pb_configs[component.currentConfig] && component.current_pb_configs[component.currentConfig].price > 0" ng-bind="formatPrice(component.current_pb_configs[component.currentConfig].price)"></span>
                                    <span class="spbwc-cust-summary__choice spbwc-cust-summary__choice--missing" ng-if="!component.current_pb_configs[component.currentConfig]"><?php esc_html_e( '— pick one', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                </span>
                                <span ng-switch-when="nbpb_text">
                                    <span class="spbwc-cust-summary__choice" ng-if="component.currentContent">"{{component.currentContent}}"</span>
                                    <span class="spbwc-cust-summary__choice spbwc-cust-summary__choice--missing" ng-if="!component.currentContent"><?php esc_html_e( '— add text', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                </span>
                                <span ng-switch-when="nbpb_image">
                                    <span class="spbwc-cust-summary__choice" ng-if="resource.uploaded.length"><?php esc_html_e( 'Uploaded', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                    <span class="spbwc-cust-summary__choice spbwc-cust-summary__choice--missing" ng-if="!resource.uploaded.length"><?php esc_html_e( '— upload image', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                </span>
                            </span>
                        </div>
                        <div class="spbwc-cust-summary__row spbwc-cust-summary__row--meta">
                            <span class="spbwc-cust-summary__label"><?php esc_html_e( 'Shipping', 'storelly-product-builder-for-woocommerce' ); ?></span>
                            <span class="spbwc-cust-summary__val spbwc-cust-summary__val--muted"><?php esc_html_e( 'at checkout', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        </div>
                    </div>

                    <!-- YOUR PRICE — Printcart Canva pattern: large bold total -->
                    <div class="spbwc-cust-summary__total">
                        <span class="spbwc-cust-summary__total-label"><?php esc_html_e( 'Your price', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        <span class="spbwc-cust-summary__total-val" data-spbwc-grand-total><?php echo wp_kses_post( $spbwc_v3_base_price_html ); ?></span>
                    </div>

                    <button class="spbwc-cust-cta" type="button" data-spbwc-action="add-to-cart" ng-click="saveData()" aria-live="polite">
                        <span class="spbwc-cust-cta__content">
                            <span class="spbwc-cust-cta__label"><?php esc_html_e( 'Add to cart', 'storelly-product-builder-for-woocommerce' ); ?></span>
                            <span class="spbwc-cust-cta__price" data-spbwc-cta-price><?php echo wp_kses_post( $spbwc_v3_base_price_html ); ?></span>
                        </span>
                        <svg class="spbwc-cust-cta__arrow" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </button>

                    <div class="spbwc-cust-summary__actions">
                        <button type="button" class="spbwc-cust-linkbtn" data-spbwc-action="reset-all">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 9"/><path d="M3 3v6h6"/></svg>
                            <?php esc_html_e( 'Reset all', 'storelly-product-builder-for-woocommerce' ); ?>
                        </button>
                        <button type="button" class="close-popup spbwc-cust-linkbtn">
                            <?php esc_html_e( 'Cancel', 'storelly-product-builder-for-woocommerce' ); ?>
                        </button>
                    </div>

                    <div class="spbwc-cust-summary__trust">
                        <span class="spbwc-cust-summary__trust-item">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php esc_html_e( 'Free design preview', 'storelly-product-builder-for-woocommerce' ); ?>
                        </span>
                        <span class="spbwc-cust-summary__trust-item">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            <?php esc_html_e( 'Quick turnaround', 'storelly-product-builder-for-woocommerce' ); ?>
                        </span>
                    </div>
                </aside>

            </div>
        </div>
    </div>

</div>
<?php if( $is_creating_task == 0 ): // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable defined by parent template. ?>
<!-- Teaching toast (Printcart Canva pattern). Rendered as a sibling of
     the .nbdpb-popup wrapper — NOT inside it — because the legacy
     `.nbdpb-carousel.nbdpbCarousel()` plugin DOM-rewrites the popup's
     subtree on init and strips out any non-managed elements. Sitting at
     body-level keeps the toast safe; it stays hidden until the modal is
     active via the `.spbwc-cust-v3.nbdpb-show ~ .spbwc-cust-teachtoast`
     sibling selector in app-product-builder.css. -->
<div class="spbwc-cust-teachtoast" data-spbwc-teachtoast role="status" aria-live="polite">
    <span class="spbwc-cust-teachtoast__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
    </span>
    <span class="spbwc-cust-teachtoast__body">
        <strong><?php esc_html_e( 'Live pricing', 'storelly-product-builder-for-woocommerce' ); ?></strong>
        <span><?php esc_html_e( 'Pick any option — the total on the right updates instantly.', 'storelly-product-builder-for-woocommerce' ); ?></span>
    </span>
    <button type="button" class="spbwc-cust-teachtoast__close" data-spbwc-teachtoast-close aria-label="<?php esc_attr_e( 'Dismiss', 'storelly-product-builder-for-woocommerce' ); ?>">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
    </button>
</div>
<?php endif; ?>
