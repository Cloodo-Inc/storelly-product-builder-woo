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
    $spbwc_v3_product_short_description = '';
    $spbwc_v3_product_description = '';
    $spbwc_v3_product_sku = '';
    $spbwc_v3_product_categories = '';
    $spbwc_v3_product_image_url = '';       // medium_large hero (Details tab)
    $spbwc_v3_product_gallery_urls = array(); // gallery thumbs (Details tab)
    if ( $is_creating_task == 0 && function_exists( 'wc_get_product' ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable defined by parent template.
        $spbwc_v3_product = wc_get_product( get_the_ID() );
        if ( $spbwc_v3_product ) {
            $spbwc_v3_product_name              = $spbwc_v3_product->get_name();
            $spbwc_v3_base_price_raw            = (float) $spbwc_v3_product->get_price();
            $spbwc_v3_regular_price_raw         = (float) $spbwc_v3_product->get_regular_price();
            $spbwc_v3_is_on_sale                = $spbwc_v3_product->is_on_sale();
            /* Use wc_price() to get just the current price formatted with the
             * store currency — no strikethrough markup. The sale-vs-regular
             * is surfaced as a small "Save $X" badge elsewhere, not as two
             * stacked amounts inside the customizer. */
            $spbwc_v3_base_price_html           = wc_price( $spbwc_v3_base_price_raw );
            $spbwc_v3_product_thumb             = get_the_post_thumbnail_url( $spbwc_v3_product->get_id(), 'thumbnail' );
            /* Content tabs (Details + Shipping). short_description = the
             * concise tagline shown above the WC tab block on the product
             * page; description = the long body. SKU + categories + weight
             * + dimensions fill out the Details tab. */
            $spbwc_v3_product_short_description = $spbwc_v3_product->get_short_description();
            $spbwc_v3_product_description       = $spbwc_v3_product->get_description();
            $spbwc_v3_product_sku               = $spbwc_v3_product->get_sku();
            $spbwc_v3_cats                      = wp_get_post_terms( $spbwc_v3_product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
            $spbwc_v3_product_categories        = ( ! is_wp_error( $spbwc_v3_cats ) && ! empty( $spbwc_v3_cats ) ) ? implode( ', ', $spbwc_v3_cats ) : '';
            $spbwc_v3_product_weight            = $spbwc_v3_product->get_weight();
            $spbwc_v3_product_dimensions        = $spbwc_v3_product->has_dimensions() ? wc_format_dimensions( $spbwc_v3_product->get_dimensions( false ) ) : '';
            /* Details tab — bigger hero + gallery thumbs. */
            $spbwc_v3_image_id = $spbwc_v3_product->get_image_id();
            if ( $spbwc_v3_image_id ) {
                $spbwc_v3_product_image_url = wp_get_attachment_image_url( $spbwc_v3_image_id, 'medium_large' );
            }
            $spbwc_v3_gallery_ids = $spbwc_v3_product->get_gallery_image_ids();
            if ( ! empty( $spbwc_v3_gallery_ids ) ) {
                foreach ( $spbwc_v3_gallery_ids as $spbwc_v3_g_id ) {
                    $url = wp_get_attachment_image_url( $spbwc_v3_g_id, 'medium_large' );
                    $thumb = wp_get_attachment_image_url( $spbwc_v3_g_id, 'thumbnail' );
                    if ( $url ) {
                        $spbwc_v3_product_gallery_urls[] = array( 'full' => $url, 'thumb' => $thumb ?: $url );
                    }
                }
            }

            /* Spec line under the product name in the summary header.
             * "Uncategorized" is the WP default for products without a
             * real category, so we skip it. Falls back to SKU when the
             * product has no useful category — and finally to a generic
             * "Made to order" tag if neither exists. */
            $spbwc_v3_specs_bits = array();
            if ( ! is_wp_error( $spbwc_v3_cats ) && ! empty( $spbwc_v3_cats ) ) {
                $spbwc_v3_real_cats = array_filter( $spbwc_v3_cats, function ( $name ) {
                    return strtolower( $name ) !== 'uncategorized';
                } );
                if ( ! empty( $spbwc_v3_real_cats ) ) {
                    $spbwc_v3_specs_bits[] = implode( ' · ', array_slice( $spbwc_v3_real_cats, 0, 2 ) );
                }
            }
            if ( ! empty( $spbwc_v3_product_sku ) ) {
                /* translators: %s: product SKU */
                $spbwc_v3_specs_bits[] = sprintf( esc_html__( 'SKU %s', 'storelly-product-builder-for-woocommerce' ), $spbwc_v3_product_sku );
            }
            if ( empty( $spbwc_v3_specs_bits ) ) {
                $spbwc_v3_specs_bits[] = esc_html__( 'Made to order', 'storelly-product-builder-for-woocommerce' );
            }
            $spbwc_v3_product_specs = implode( ' · ', $spbwc_v3_specs_bits );
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
                    <button type="button" class="spbwc-cust-tabbtn" role="tab" aria-selected="false" data-spbwc-tab="details" title="<?php esc_attr_e( 'Product details', 'storelly-product-builder-for-woocommerce' ); ?>">
                        <span class="spbwc-cust-tabbtn__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
                        </span>
                        <span class="spbwc-cust-tabbtn__label"><?php esc_html_e( 'Details', 'storelly-product-builder-for-woocommerce' ); ?></span>
                    </button>
                    <button type="button" class="spbwc-cust-tabbtn" role="tab" aria-selected="false" data-spbwc-tab="shipping" title="<?php esc_attr_e( 'Shipping & returns', 'storelly-product-builder-for-woocommerce' ); ?>">
                        <span class="spbwc-cust-tabbtn__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 18h14V8H5z"/><path d="M5 8 7 4h10l2 4"/><circle cx="8" cy="18" r="2"/><circle cx="16" cy="18" r="2"/></svg>
                        </span>
                        <span class="spbwc-cust-tabbtn__label"><?php esc_html_e( 'Shipping', 'storelly-product-builder-for-woocommerce' ); ?></span>
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
                            <!-- View filter toggle — show only parts that affect the
                                 currently-shown view. Only rendered when product has
                                 ≥2 stages. Defaults to "current view only" so the
                                 buyer doesn't see options that don't visually change
                                 what they're looking at. -->
                            <div class="spbwc-cust-viewfilter" ng-if="stages.length > 1" role="tablist" aria-label="<?php esc_attr_e( 'Filter parts by view', 'storelly-product-builder-for-woocommerce' ); ?>">
                                <button type="button" class="spbwc-cust-viewfilter__btn" ng-click="viewFilter = 'current'" ng-class="{'is-active': viewFilter == 'current'}" role="tab" aria-selected="{{viewFilter == 'current'}}">
                                    <?php esc_html_e( 'This view', 'storelly-product-builder-for-woocommerce' ); ?>
                                </button>
                                <button type="button" class="spbwc-cust-viewfilter__btn" ng-click="viewFilter = 'all'" ng-class="{'is-active': viewFilter == 'all'}" role="tab" aria-selected="{{viewFilter == 'all'}}">
                                    <?php esc_html_e( 'All', 'storelly-product-builder-for-woocommerce' ); ?>
                                </button>
                            </div>
                        </header>

                        <div class="spbwc-cust-acc">
                            <!-- One accordion item per component. ng-click toggles via $scope.showAttribute($index). -->
                            <div ng-repeat="component in resource.components" ng-show="component.enable && componentVisibleInFilter(component)" class="spbwc-cust-acc-item" ng-class="{'is-open': resource.showValue && $index == resource.currentComponent, 'is-done': isComponentConfigured(component)}">
                                <button type="button" class="spbwc-cust-acc-head" ng-click="toggleAccordion($index)" aria-expanded="{{resource.showValue && $index == resource.currentComponent ? 'true' : 'false'}}">
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

                    <!-- Tab content: Details — product hero + gallery + meta -->
                    <div class="spbwc-cust-tabpanel" data-spbwc-tabpanel="details" hidden>
                        <header class="spbwc-cust-panel__head">
                            <div class="spbwc-cust-panel__title"><?php esc_html_e( 'Product details', 'storelly-product-builder-for-woocommerce' ); ?></div>
                        </header>
                        <div class="spbwc-cust-content spbwc-cust-details">
                            <?php if ( ! empty( $spbwc_v3_product_image_url ) ) : ?>
                                <div class="spbwc-cust-details__hero">
                                    <img class="spbwc-cust-details__hero-img" data-spbwc-hero src="<?php echo esc_url( $spbwc_v3_product_image_url ); ?>" alt="<?php echo esc_attr( $spbwc_v3_product_name ); ?>" />
                                </div>
                                <?php if ( ! empty( $spbwc_v3_product_gallery_urls ) ) : ?>
                                    <div class="spbwc-cust-details__gallery">
                                        <button type="button" class="spbwc-cust-details__thumb is-active" data-spbwc-thumb data-full="<?php echo esc_url( $spbwc_v3_product_image_url ); ?>" aria-label="<?php esc_attr_e( 'Main image', 'storelly-product-builder-for-woocommerce' ); ?>">
                                            <img src="<?php echo esc_url( $spbwc_v3_product_thumb ?: $spbwc_v3_product_image_url ); ?>" alt="" />
                                        </button>
                                        <?php foreach ( $spbwc_v3_product_gallery_urls as $spbwc_v3_g_idx => $spbwc_v3_g ) : ?>
                                            <button type="button" class="spbwc-cust-details__thumb" data-spbwc-thumb data-full="<?php echo esc_url( $spbwc_v3_g['full'] ); ?>" aria-label="<?php /* translators: %d gallery image number */ echo esc_attr( sprintf( __( 'Gallery image %d', 'storelly-product-builder-for-woocommerce' ), $spbwc_v3_g_idx + 2 ) ); ?>">
                                                <img src="<?php echo esc_url( $spbwc_v3_g['thumb'] ); ?>" alt="" />
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <div class="spbwc-cust-details__heading">
                                <h2 class="spbwc-cust-details__name"><?php echo esc_html( $spbwc_v3_product_name ?: esc_html__( 'Custom product', 'storelly-product-builder-for-woocommerce' ) ); ?></h2>
                                <?php if ( $spbwc_v3_base_price_html ) : ?>
                                    <div class="spbwc-cust-details__price"><?php
                                        /* translators: %s: base product price */
                                        printf( esc_html__( 'From %s base', 'storelly-product-builder-for-woocommerce' ), wp_kses_post( $spbwc_v3_base_price_html ) );
                                    ?></div>
                                <?php endif; ?>
                            </div>

                            <?php if ( ! empty( $spbwc_v3_product_short_description ) ) : ?>
                                <div class="spbwc-cust-details__section">
                                    <h3 class="spbwc-cust-details__h"><?php esc_html_e( 'About this product', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                                    <div class="spbwc-cust-content__lede"><?php echo wp_kses_post( wpautop( $spbwc_v3_product_short_description ) ); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ( ! empty( $spbwc_v3_product_description ) ) : ?>
                                <div class="spbwc-cust-details__section">
                                    <h3 class="spbwc-cust-details__h"><?php esc_html_e( 'Full description', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                                    <div class="spbwc-cust-content__body"><?php echo wp_kses_post( wpautop( $spbwc_v3_product_description ) ); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ( empty( $spbwc_v3_product_short_description ) && empty( $spbwc_v3_product_description ) ) : ?>
                                <p class="spbwc-cust-content__empty"><?php esc_html_e( 'No product description has been added yet.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                            <?php endif; ?>

                            <?php if ( ! empty( $spbwc_v3_product_sku ) || ! empty( $spbwc_v3_product_categories ) || ! empty( $spbwc_v3_product_weight ) || ! empty( $spbwc_v3_product_dimensions ) ) : ?>
                                <div class="spbwc-cust-details__section">
                                    <h3 class="spbwc-cust-details__h"><?php esc_html_e( 'Specifications', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                                    <dl class="spbwc-cust-spec">
                                        <?php if ( ! empty( $spbwc_v3_product_sku ) ) : ?>
                                            <div class="spbwc-cust-spec__row">
                                                <dt><?php esc_html_e( 'SKU', 'storelly-product-builder-for-woocommerce' ); ?></dt>
                                                <dd><?php echo esc_html( $spbwc_v3_product_sku ); ?></dd>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $spbwc_v3_product_categories ) ) : ?>
                                            <div class="spbwc-cust-spec__row">
                                                <dt><?php esc_html_e( 'Category', 'storelly-product-builder-for-woocommerce' ); ?></dt>
                                                <dd><?php echo esc_html( $spbwc_v3_product_categories ); ?></dd>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $spbwc_v3_product_weight ) ) : ?>
                                            <div class="spbwc-cust-spec__row">
                                                <dt><?php esc_html_e( 'Weight', 'storelly-product-builder-for-woocommerce' ); ?></dt>
                                                <dd><?php echo esc_html( $spbwc_v3_product_weight . ' ' . get_option( 'woocommerce_weight_unit' ) ); ?></dd>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $spbwc_v3_product_dimensions ) ) : ?>
                                            <div class="spbwc-cust-spec__row">
                                                <dt><?php esc_html_e( 'Dimensions', 'storelly-product-builder-for-woocommerce' ); ?></dt>
                                                <dd><?php echo esc_html( $spbwc_v3_product_dimensions ); ?></dd>
                                            </div>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tab content: Shipping — generic Printcart-style sales-policy card.
                         Merchants can override the copy via the `spbwc_customizer_shipping_html`
                         filter without touching the template. -->
                    <div class="spbwc-cust-tabpanel" data-spbwc-tabpanel="shipping" hidden>
                        <header class="spbwc-cust-panel__head">
                            <div class="spbwc-cust-panel__title"><?php esc_html_e( 'Shipping & returns', 'storelly-product-builder-for-woocommerce' ); ?></div>
                        </header>
                        <div class="spbwc-cust-content">
                            <?php
                                $spbwc_v3_shipping_default = ''
                                    . '<div class="spbwc-cust-policy">'
                                    . '  <div class="spbwc-cust-policy__row">'
                                    . '    <span class="spbwc-cust-policy__icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>'
                                    . '    <div><strong>' . esc_html__( 'Production lead time', 'storelly-product-builder-for-woocommerce' ) . '</strong><br><span>' . esc_html__( 'Your design enters production within 24 hours of order confirmation.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>'
                                    . '  </div>'
                                    . '  <div class="spbwc-cust-policy__row">'
                                    . '    <span class="spbwc-cust-policy__icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>'
                                    . '    <div><strong>' . esc_html__( 'Delivery options', 'storelly-product-builder-for-woocommerce' ) . '</strong><br><span>' . esc_html__( 'Standard 3–7 business days · Express available at checkout · Free over $80.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>'
                                    . '  </div>'
                                    . '  <div class="spbwc-cust-policy__row">'
                                    . '    <span class="spbwc-cust-policy__icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></span>'
                                    . '    <div><strong>' . esc_html__( 'Custom-print returns', 'storelly-product-builder-for-woocommerce' ) . '</strong><br><span>' . esc_html__( 'Personalised items are made-to-order. Defects or print errors qualify for a free reprint or refund within 30 days.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>'
                                    . '  </div>'
                                    . '  <div class="spbwc-cust-policy__row">'
                                    . '    <span class="spbwc-cust-policy__icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>'
                                    . '    <div><strong>' . esc_html__( 'Secure checkout', 'storelly-product-builder-for-woocommerce' ) . '</strong><br><span>' . esc_html__( 'All payments are processed by WooCommerce over a TLS 1.3 connection — we never store card data.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>'
                                    . '  </div>'
                                    . '</div>';
                                echo wp_kses_post( apply_filters( 'spbwc_customizer_shipping_html', $spbwc_v3_shipping_default ) );
                            ?>
                        </div>
                    </div>

                    <!-- Tab content: Help / FAQ — card-style accordion list -->
                    <div class="spbwc-cust-tabpanel" data-spbwc-tabpanel="help" hidden>
                        <header class="spbwc-cust-panel__head">
                            <div class="spbwc-cust-panel__title"><?php esc_html_e( 'How it works', 'storelly-product-builder-for-woocommerce' ); ?></div>
                        </header>
                        <div class="spbwc-cust-help">
                            <!-- Quick-help banner — sets the tone before the FAQ list. -->
                            <div class="spbwc-cust-help__intro">
                                <div class="spbwc-cust-help__intro-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                                </div>
                                <div class="spbwc-cust-help__intro-body">
                                    <strong><?php esc_html_e( 'Need a hand?', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                                    <span><?php esc_html_e( 'Quick answers to the most common questions when customizing this product.', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                </div>
                            </div>

                            <details class="spbwc-cust-faq" open>
                                <summary>
                                    <span class="spbwc-cust-faq__q"><?php esc_html_e( 'How do I customize this product?', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                    <svg class="spbwc-cust-faq__chev" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                                </summary>
                                <div class="spbwc-cust-faq__a">
                                    <p><?php esc_html_e( 'Open each part on the left, pick the option you want, and the preview in the centre updates in real time. The "Your price" total on the right also updates as you go — so you always know what you\'re paying.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                                </div>
                            </details>

                            <details class="spbwc-cust-faq">
                                <summary>
                                    <span class="spbwc-cust-faq__q"><?php esc_html_e( 'Will my design be saved?', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                    <svg class="spbwc-cust-faq__chev" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                                </summary>
                                <div class="spbwc-cust-faq__a">
                                    <p><?php esc_html_e( 'Yes. Click "Add to cart" and we lock your full configuration in with the order. You can re-order the same design any time from your account — just look for "Reorder this design" on the order detail page.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                                </div>
                            </details>

                            <details class="spbwc-cust-faq">
                                <summary>
                                    <span class="spbwc-cust-faq__q"><?php esc_html_e( 'Can I undo a change?', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                    <svg class="spbwc-cust-faq__chev" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                                </summary>
                                <div class="spbwc-cust-faq__a">
                                    <p><?php esc_html_e( 'Inside any open step you\'ll see a "Reset this part" link to undo just that part. To start over completely, click the ↻ icon in the top-right of the modal, or "Reset all" under the Add-to-cart button.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                                </div>
                            </details>

                            <details class="spbwc-cust-faq">
                                <summary>
                                    <span class="spbwc-cust-faq__q"><?php esc_html_e( 'How is the final price calculated?', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                    <svg class="spbwc-cust-faq__chev" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                                </summary>
                                <div class="spbwc-cust-faq__a">
                                    <p><?php esc_html_e( 'Your final price = base product price + any per-option upcharges. The summary on the right shows each line item ("Included" means no extra cost, "+$X" means it adds to the total). Shipping is calculated at checkout based on your address.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                                </div>
                            </details>

                            <details class="spbwc-cust-faq">
                                <summary>
                                    <span class="spbwc-cust-faq__q"><?php esc_html_e( 'Still need help?', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                    <svg class="spbwc-cust-faq__chev" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                                </summary>
                                <div class="spbwc-cust-faq__a">
                                    <p><?php
                                        /* translators: %s: HTML link */
                                        printf(
                                            wp_kses(
                                                __( 'Contact our support team via the %s — we typically reply within one business day.', 'storelly-product-builder-for-woocommerce' ),
                                                array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ) )
                                            ),
                                            '<a href="' . esc_url( apply_filters( 'spbwc_customizer_contact_url', '/contact' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Contact page', 'storelly-product-builder-for-woocommerce' ) . '</a>'
                                        );
                                    ?></p>
                                </div>
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

                    <!-- Canvas toolbar row — DEDICATED 48px row below the stage so
                         the artwork never gets hidden under floating bars. Contains:
                         live-preview pill (left), Front/Back view switcher (centre),
                         zoom controls (right). All inline-flex children of the row. -->
                    <div class="spbwc-cust-canvas__toolbar">
                        <div class="spbwc-cust-canvas__toolbar-left">
                            <span class="spbwc-cust-autosave" aria-hidden="true">
                                <span class="spbwc-cust-autosave__dot"></span>
                                <span><?php esc_html_e( 'Live preview', 'storelly-product-builder-for-woocommerce' ); ?></span>
                            </span>
                        </div>
                        <div class="spbwc-cust-canvas__toolbar-center">
                            <div class="spbwc-cust-viewswitch" ng-if="stages.length > 1">
                                <button type="button" class="spbwc-cust-viewswitch__btn" ng-click="changeStage((currentStage - 1 + stages.length) % stages.length)" ng-disabled="stages.length < 2" title="<?php esc_attr_e( 'Previous view', 'storelly-product-builder-for-woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Previous view', 'storelly-product-builder-for-woocommerce' ); ?>">
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
                        </div>
                        <div class="spbwc-cust-canvas__toolbar-right">
                            <div class="spbwc-cust-zoom" aria-label="<?php esc_attr_e( 'Zoom', 'storelly-product-builder-for-woocommerce' ); ?>">
                                <button type="button" class="spbwc-cust-zoom__btn" ng-click="zoomCanvas(-0.1)" aria-label="<?php esc_attr_e( 'Zoom out', 'storelly-product-builder-for-woocommerce' ); ?>" title="<?php esc_attr_e( 'Zoom out', 'storelly-product-builder-for-woocommerce' ); ?>">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/><path d="M8 11h6"/></svg>
                                </button>
                                <span class="spbwc-cust-zoom__value" data-spbwc-zoom-value>100%</span>
                                <button type="button" class="spbwc-cust-zoom__btn" ng-click="zoomCanvas(0.1)" aria-label="<?php esc_attr_e( 'Zoom in', 'storelly-product-builder-for-woocommerce' ); ?>" title="<?php esc_attr_e( 'Zoom in', 'storelly-product-builder-for-woocommerce' ); ?>">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/><path d="M8 11h6"/><path d="M11 8v6"/></svg>
                                </button>
                                <button type="button" class="spbwc-cust-zoom__btn spbwc-cust-zoom__btn--reset" ng-click="zoomCanvas(0, true)" aria-label="<?php esc_attr_e( 'Fit', 'storelly-product-builder-for-woocommerce' ); ?>" title="<?php esc_attr_e( 'Fit to screen', 'storelly-product-builder-for-woocommerce' ); ?>">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h6"/><path d="M3 3v6"/><path d="M21 3h-6"/><path d="M21 3v6"/><path d="M3 21h6"/><path d="M3 21v-6"/><path d="M21 21h-6"/><path d="M21 21v-6"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ============== SUMMARY — Printcart `.summary-col` 1:1 ============== -->
                <aside class="spbwc-cust-summary">

                  <!-- STICKY TOP — product head pins so the customer always sees
                       which product + how many parts they've configured. -->
                  <div class="spbwc-cust-summary__sticky-top">
                    <!-- Product hero block (Printcart `.summary-product-head`) -->
                    <div class="spbwc-cust-summary__head">
                        <div class="spbwc-cust-summary__head-row">
                            <div class="spbwc-cust-summary__head-name-block">
                                <h1 class="spbwc-cust-summary__product-name"><?php echo esc_html( $spbwc_v3_product_name ?: esc_html__( 'Custom product', 'storelly-product-builder-for-woocommerce' ) ); ?></h1>
                                <?php if ( $spbwc_v3_product_specs ) : ?>
                                    <div class="spbwc-cust-summary__product-variant"><?php echo esc_html( $spbwc_v3_product_specs ); ?></div>
                                <?php endif; ?>
                            </div>
                            <!-- Live progress pill (moved here from left panel) -->
                            <span class="spbwc-cust-summary__progress-pill" data-spbwc-progress-pill>
                                <span class="spbwc-cust-summary__progress-dot" data-spbwc-progress-dot></span>
                                <span data-spbwc-progress-label>0 / 0</span>
                            </span>
                        </div>
                        <div class="spbwc-cust-summary__progress-track" aria-hidden="true">
                            <span class="spbwc-cust-summary__progress-fill" data-spbwc-progress-fill style="width:0"></span>
                        </div>
                    </div>
                  </div>

                  <!-- SCROLLABLE MIDDLE — order summary + price breakdown.
                       If the customer has 10+ components, this is where the
                       price block grows; the customer scrolls inside this band
                       while the product head and CTA stay visible. -->
                  <div class="spbwc-cust-summary__scroll">
                    <!-- ORDER SUMMARY · 1 ITEM — Printcart `.summary-section` + `.summary-item` -->
                    <div class="spbwc-cust-summary__section">
                        <div class="spbwc-cust-summary__title"><?php esc_html_e( 'Order summary · 1 item', 'storelly-product-builder-for-woocommerce' ); ?></div>
                        <div class="spbwc-cust-summary__item">
                            <?php if ( $spbwc_v3_product_thumb ) : ?>
                                <img class="spbwc-cust-summary__thumb" src="<?php echo esc_url( $spbwc_v3_product_thumb ); ?>" alt="" />
                            <?php else : ?>
                                <span class="spbwc-cust-summary__thumb spbwc-cust-summary__thumb--ph" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                </span>
                            <?php endif; ?>
                            <div class="spbwc-cust-summary__item-info">
                                <div class="spbwc-cust-summary__item-title"><?php echo esc_html( $spbwc_v3_product_name ?: esc_html__( 'Custom item', 'storelly-product-builder-for-woocommerce' ) ); ?> #1</div>
                                <div class="spbwc-cust-summary__item-specs" data-spbwc-summary-spec>
                                    <span ng-repeat="component in resource.components" ng-show="component.enable && component.nbpb_type == 'nbpb_com' && component.current_pb_configs[component.currentConfig]"><span ng-if="!$first"> · </span>{{(component.current_pb_configs[component.currentConfig].sattr_name) || (component.current_pb_configs[component.currentConfig].attr_name)}}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Price block — Printcart `.price-block` + `.price-row` -->
                    <div class="spbwc-cust-summary__price-block">
                        <div class="spbwc-cust-summary__price-row">
                            <span class="lbl"><?php esc_html_e( 'Base price', 'storelly-product-builder-for-woocommerce' ); ?></span>
                            <span class="val" data-spbwc-base-price><?php echo wp_kses_post( $spbwc_v3_base_price_html ); ?></span>
                        </div>
                        <div class="spbwc-cust-summary__price-row" ng-repeat="component in resource.components" ng-show="component.enable">
                            <span class="lbl">{{component.general.title}}</span>
                            <span class="val" ng-switch="component.nbpb_type">
                                <span ng-switch-when="nbpb_com">
                                    <span class="val-sub" ng-if="component.current_pb_configs[component.currentConfig] && !component.current_pb_configs[component.currentConfig].price"><?php esc_html_e( 'Included', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                    <span class="val-add" ng-if="component.current_pb_configs[component.currentConfig] && component.current_pb_configs[component.currentConfig].price > 0" ng-bind="formatPrice(component.current_pb_configs[component.currentConfig].price)"></span>
                                    <span class="val-sub val-sub--missing" ng-if="!component.current_pb_configs[component.currentConfig]"><?php esc_html_e( '— pick one', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                </span>
                                <span ng-switch-when="nbpb_text">
                                    <span class="val-sub" ng-if="component.currentContent">{{component.currentContent}}</span>
                                    <span class="val-sub val-sub--missing" ng-if="!component.currentContent"><?php esc_html_e( '— add text', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                </span>
                                <span ng-switch-when="nbpb_image">
                                    <span class="val-sub" ng-if="resource.uploaded.length"><?php esc_html_e( 'Uploaded', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                    <span class="val-sub val-sub--missing" ng-if="!resource.uploaded.length"><?php esc_html_e( '— upload', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                </span>
                            </span>
                        </div>
                        <div class="spbwc-cust-summary__price-row spbwc-cust-summary__price-row--subtle">
                            <span class="lbl"><?php esc_html_e( 'Shipping', 'storelly-product-builder-for-woocommerce' ); ?></span>
                            <span class="val"><?php esc_html_e( 'at checkout', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        </div>
                        <!-- Volume discount row — shown by refreshSummary() only when the
                             selected quantity reaches a quantity_breaks tier. Mirrors the
                             cart-side discount so the customizer price stays honest. -->
                        <div class="spbwc-cust-summary__price-row spbwc-cust-summary__price-row--discount" data-spbwc-volume-row style="display:none;">
                            <span class="lbl"><?php esc_html_e( 'Volume discount', 'storelly-product-builder-for-woocommerce' ); ?> <span data-spbwc-volume-label></span></span>
                            <span class="val" data-spbwc-volume-val></span>
                        </div>
                        <!-- "Your price" total row — Printcart `.price-row.total` -->
                        <div class="spbwc-cust-summary__price-row spbwc-cust-summary__price-row--total">
                            <span class="lbl"><?php esc_html_e( 'Your price', 'storelly-product-builder-for-woocommerce' ); ?></span>
                            <span class="val" data-spbwc-grand-total><?php echo wp_kses_post( $spbwc_v3_base_price_html ); ?></span>
                        </div>
                    </div>
                  </div>

                  <!-- STICKY BOTTOM — CTA + Reset/Cancel + trust note pin to
                       the bottom so the Add to cart button is ALWAYS visible
                       no matter how tall the price breakdown grows. -->
                  <div class="spbwc-cust-summary__sticky-bottom">
                    <!-- BIG primary Add to cart — Printcart `.summary-cta-big` -->
                    <button class="spbwc-cust-cta" type="button" data-spbwc-action="add-to-cart" ng-click="saveData()" aria-live="polite">
                        <span class="spbwc-cust-cta__content">
                            <span class="spbwc-cust-cta__label"><?php esc_html_e( 'Add to cart', 'storelly-product-builder-for-woocommerce' ); ?></span>
                            <span class="spbwc-cust-cta__price" data-spbwc-cta-price><?php echo wp_kses_post( $spbwc_v3_base_price_html ); ?></span>
                        </span>
                        <svg class="spbwc-cust-cta__arrow" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </button>

                    <!-- Reset / Cancel link row -->
                    <div class="spbwc-cust-summary__actions">
                        <button type="button" class="spbwc-cust-linkbtn" data-spbwc-action="reset-all">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 9"/><path d="M3 3v6h6"/></svg>
                            <?php esc_html_e( 'Reset all', 'storelly-product-builder-for-woocommerce' ); ?>
                        </button>
                        <button type="button" class="close-popup spbwc-cust-linkbtn"><?php esc_html_e( 'Cancel', 'storelly-product-builder-for-woocommerce' ); ?></button>
                    </div>

                    <!-- Trust note — Printcart `.prod-note` blue info card -->
                    <div class="spbwc-cust-summary__note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <span><strong><?php esc_html_e( 'Free design preview', 'storelly-product-builder-for-woocommerce' ); ?></strong> — <?php esc_html_e( 'no charge until your design is locked in.', 'storelly-product-builder-for-woocommerce' ); ?></span>
                    </div>
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
