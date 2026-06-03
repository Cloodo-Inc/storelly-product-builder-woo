<?php
/**
 * Visual Builder — Edit screen.
 *
 * Mounts the same AngularJS controller (optionApp/optionCtrl) the classic
 * editor uses, on the same option data, but exposes only the visual surface:
 *   • Views grid (options.views[])
 *   • Add-component chips (nbpb_com / nbpb_text / nbpb_image)
 *   • Field list filtered to nbpb-only cards (non-nbpb cards are still in the
 *     DOM but CSS-hidden so all their inputs round-trip on save)
 *
 * Top-level option data not in the visible UI (title, apply targeting, display
 * mode, quantity breaks, design output) is round-tripped through a block of
 * hidden inputs bound via {{ }} interpolation — that pattern reliably updates
 * the DOM `value` attribute (see existing memory note on ng-model + hidden
 * input pitfalls).
 *
 * Rendered by SPBWC_Visual_Builder_Admin::render_edit(). Receives:
 *   - $options      : full option array as built by spbwc_build_options().
 *   - $current_id   : option id (>0).
 *   - $form_action  : URL the form posts to (Visual Builder edit URL).
 *   - $back_url     : Visual Builder listing URL.
 *   - $classic_url  : classic editor URL (escape hatch for pricing edits).
 *   - $notice       : flash-notice payload or null.
 *
 * @package Storelly_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<?php /*
    The `spbwc-vb` class is REQUIRED for the design-token aliases
    (--vb-card-radius, --vb-text-mute, --vb-link, …) declared in
    static/css/visual-builder.css under the `.spbwc-vb { }` rule. Without
    it, all child component cards lose their custom-property scope and
    fall back to undefined values (broken paddings, borders, colors).
*/ ?>
<div class="wrap spbwc-edit-v2 spbwc-vb spbwc-vb-edit" ng-app="optionApp" ng-cloak>
    <?php /* Transient toast container — populated by visual-builder.js
           vbAddField() / vbShowToast() helpers. Binds to $rootScope so
           it doesn't need optionCtrl (avoids double-instantiating the
           heavy classic controller). Auto-clears after ~3s. */ ?>
    <div class="spbwc-vb-toast"
         ng-show="vbToast.visible"
         ng-class="'spbwc-vb-toast--' + (vbToast.type || 'info')"
         role="status" aria-live="polite">
        <span class="spbwc-vb-toast__icon" aria-hidden="true">{{vbToast.icon || '✨'}}</span>
        <span class="spbwc-vb-toast__msg">{{vbToast.message}}</span>
    </div>
    <div ng-controller="optionCtrl">

        <!-- ──────────────── Breadcrumb ──────────────── -->
        <nav class="spbwc-vb__backbar" aria-label="<?php esc_attr_e( 'Breadcrumb', 'storelly-product-builder-for-woocommerce' ); ?>">
            <a href="<?php echo esc_url( $back_url ); ?>">
                <span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
                <?php esc_html_e( 'Visual Builder', 'storelly-product-builder-for-woocommerce' ); ?>
            </a>
            <span class="spbwc-vb__backbar-sep">/</span>
            <span class="spbwc-vb__backbar-current">
                <?php
                /* translators: %s: option title */
                printf( esc_html__( 'Edit · %s', 'storelly-product-builder-for-woocommerce' ), esc_html( '' !== trim( (string) $options['title'] ) ? $options['title'] : '#' . absint( $current_id ) ) );
                ?>
            </span>
        </nav>

        <?php /* Switch to the shared .spbwc-page-hero block so VB inherits
               the brand gradient + token-driven typography all other
               Storelly admin pages use. Buttons inside auto-invert to the
               "on-dark" treatment defined in storelly-admin-ui.css. */ ?>
        <header class="spbwc-page-hero spbwc-vb-edit__hero">
            <div class="spbwc-page-hero__grid">
                <div class="spbwc-page-hero__body">
                    <div class="spbwc-page-hero__eyebrow">
                        <span class="dashicons dashicons-art" aria-hidden="true"></span>
                        <?php esc_html_e( 'Visual Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                    </div>
                    <h1 class="spbwc-page-hero__title">
                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                        {{ options.title || '<?php echo esc_js( __( 'Untitled visual', 'storelly-product-builder-for-woocommerce' ) ); ?>' }}
                    </h1>
                    <p class="spbwc-page-hero__subtitle">
                        <?php esc_html_e( 'Configure the visual surface for this option: add views (Front / Back / …), add designer components and decorate them with attribute images.', 'storelly-product-builder-for-woocommerce' ); ?>
                        <?php /* In-app help: anchor jumps to the Pricing
                               context section below which explains the
                               ownership split. The spec doc URL is shown
                               as a tooltip in case the merchant wants the
                               full reference (lives in docs/, only useful
                               on local dev or open-source consumers). */ ?>
                        <a class="spbwc-vb-help-link"
                           href="#spbwc-vb-context"
                           title="<?php esc_attr_e( 'See docs/SPEC_VB_PO_RELATIONSHIP.md for the full reference.', 'storelly-product-builder-for-woocommerce' ); ?>">
                            <span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
                            <?php esc_html_e( 'Visual Builder vs Pricing Options — what\'s the difference?', 'storelly-product-builder-for-woocommerce' ); ?>
                        </a>
                    </p>
                    <?php /* Dirty / saved indicator. vbDirty/vbSavedLabel exposed by visual-builder.js. */ ?>
                    <p class="spbwc-vb-edit__save-status">
                        <span class="spbwc-vb-edit__save-status-dot"
                              ng-class="{'is-dirty': vbDirty, 'is-clean': !vbDirty && vbSavedLabel}"
                              aria-hidden="true"></span>
                        <span ng-if="vbDirty">
                            <?php esc_html_e( 'Unsaved changes', 'storelly-product-builder-for-woocommerce' ); ?>
                        </span>
                        <span ng-if="!vbDirty && vbSavedLabel">{{ vbSavedLabel }}</span>
                    </p>
                </div>
                <div class="spbwc-page-hero__actions">
                    <?php if ( ! empty( $preview_url ) ) : ?>
                    <a class="spbwc-cta-btn spbwc-cta-btn--ghost"
                       href="<?php echo esc_url( $preview_url ); ?>"
                       target="_blank" rel="noopener noreferrer"
                       ng-click="vbOpenPreview($event, '<?php echo esc_js( $preview_url ); ?>')"
                       title="<?php esc_attr_e( 'Preview the buyer-facing product page in a new window (cache-busted on each click)', 'storelly-product-builder-for-woocommerce' ); ?>">
                        <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                        <?php esc_html_e( 'Preview', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                    <?php endif; ?>
                    <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="<?php echo esc_url( $classic_url ); ?>"
                       title="<?php esc_attr_e( 'Edit pricing fields, title, applied products and quantity tiers in the classic editor', 'storelly-product-builder-for-woocommerce' ); ?>">
                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                        <?php esc_html_e( 'Edit pricing', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                    <button type="button" ng-click="vbSaveWithValidation()" class="spbwc-cta-btn spbwc-cta-btn--solid">
                        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                        <?php esc_html_e( 'Save Visual', 'storelly-product-builder-for-woocommerce' ); ?>
                    </button>
                </div>
            </div>
        </header>

        <?php if ( ! empty( $notice ) ) : ?>
            <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> inline">
                <p><?php echo esc_html( $notice['text'] ); ?></p>
            </div>
        <?php endif; ?>

        <?php /* ════════════════════════════════════════════════════════
             PRICING CONTEXT — explain to the merchant that this Visual
             shares its source option with the classic Pricing Options
             editor. Counts are computed server-side (read-only), CTA
             jumps to the classic editor for the same option.
             ════════════════════════════════════════════════════════ */
        $price_field_count = 0;
        $qty_break_count   = 0;
        if ( isset( $options['fields'] ) && is_array( $options['fields'] ) ) {
            foreach ( $options['fields'] as $f ) {
                if ( is_array( $f ) && empty( $f['nbpb_type'] ) ) {
                    $price_field_count++;
                }
            }
        }
        if ( isset( $options['quantity_breaks'] ) && is_array( $options['quantity_breaks'] ) ) {
            $qty_break_count = count( $options['quantity_breaks'] );
        }
        $product_count = isset( $options['product_ids'] ) && is_array( $options['product_ids'] )
            ? count( array_filter( $options['product_ids'] ) )
            : 0;
        $cat_count = isset( $options['product_cats'] ) && is_array( $options['product_cats'] )
            ? count( array_filter( $options['product_cats'] ) )
            : 0;
        ?>
        <section class="spbwc-vb-context" id="spbwc-vb-context">
            <div class="spbwc-vb-context__head">
                <span class="spbwc-vb-context__icon" aria-hidden="true">🏷️</span>
                <div class="spbwc-vb-context__body">
                    <h2 class="spbwc-vb-context__title">
                        <?php esc_html_e( 'Pricing & rules live in the source option', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h2>
                    <p class="spbwc-vb-context__hint">
                        <?php esc_html_e( 'This Visual is anchored to a Pricing Option. Visual Builder owns the canvas / views / per-attribute images. The classic editor owns price calculations, conditional rules, and product targeting — edit those there.', 'storelly-product-builder-for-woocommerce' ); ?>
                    </p>
                </div>
                <a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm spbwc-vb-context__cta"
                   href="<?php echo esc_url( $classic_url ); ?>">
                    <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                    <?php esc_html_e( 'Open in classic editor', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
            </div>
            <div class="spbwc-vb-context__chips">
                <span class="spbwc-vb-context__chip">
                    <span class="spbwc-vb-context__chip-icon" aria-hidden="true">💰</span>
                    <strong><?php echo absint( $price_field_count ); ?></strong>
                    <?php
                    echo esc_html( _n( 'pricing field', 'pricing fields', $price_field_count, 'storelly-product-builder-for-woocommerce' ) );
                    ?>
                </span>
                <span class="spbwc-vb-context__chip">
                    <span class="spbwc-vb-context__chip-icon" aria-hidden="true">📊</span>
                    <strong><?php echo absint( $qty_break_count ); ?></strong>
                    <?php
                    echo esc_html( _n( 'quantity break', 'quantity breaks', $qty_break_count, 'storelly-product-builder-for-woocommerce' ) );
                    ?>
                </span>
                <span class="spbwc-vb-context__chip">
                    <span class="spbwc-vb-context__chip-icon" aria-hidden="true">📦</span>
                    <?php if ( 'c' === ( $options['apply_for'] ?? 'p' ) ) : ?>
                        <strong><?php echo absint( $cat_count ); ?></strong>
                        <?php
                        echo esc_html( _n( 'category', 'categories', $cat_count, 'storelly-product-builder-for-woocommerce' ) );
                        ?>
                    <?php else : ?>
                        <strong><?php echo absint( $product_count ); ?></strong>
                        <?php
                        echo esc_html( _n( 'product', 'products', $product_count, 'storelly-product-builder-for-woocommerce' ) );
                        ?>
                    <?php endif; ?>
                </span>
                <span class="spbwc-vb-context__chip spbwc-vb-context__chip--ghost">
                    <span class="spbwc-vb-context__chip-icon" aria-hidden="true">🎨</span>
                    {{ (options.fields | filter:{nbpb_type:'nbpb'}).length }}
                    <?php esc_html_e( 'visual components (this view)', 'storelly-product-builder-for-woocommerce' ); ?>
                </span>
            </div>
        </section>

        <!-- ──────────────── Form ──────────────── -->
        <form id="spbwc-vb-form" name="nboForm" method="post" action="<?php echo esc_url( $form_action ); ?>" data-spbwc-edit-form>
            <?php wp_nonce_field( 'spbwc_save_option_action', '_wpnonce' ); ?>
            <input type="hidden" name="spbwc_vb_save" value="1" />
            <input type="hidden" name="option_id" value="<?php echo esc_attr( (string) absint( $current_id ) ); ?>" />
            <input type="hidden" name="options[version]" value="<?php echo esc_attr( SPBWC_PB_VERSION ); ?>" />
            <?php /*
                jsonFields round-trip: the classic save flow flattens the full
                $scope.options.fields array into JSON via $scope.getJsonFields()
                and writes it to this hidden input. spbwc_save_option() in PHP
                then decodes options[jsonFields] and uses it as options[fields],
                preserving nested structure (general.title.value, attributes,
                pb_config, …) that the per-field DOM inputs cannot round-trip
                on their own. Without this, pricing fields lose their nested
                .value sub-keys on save.
            */ ?>
            <input type="hidden" name="options[jsonFields]" ng-value="jsonFields" value="{{jsonFields}}" />

            <!-- ════════════════════════════════════════════════════════
                 HIDDEN ROUND-TRIP BLOCK
                 -------------------------------------------------------
                 Top-level POST keys and options.* keys not shown in the
                 Visual Builder UI but required for save round-trip with
                 the classic save handler. Values bound via {{ }} so they
                 stay in sync with the AngularJS model.
                 ════════════════════════════════════════════════════════ -->
            <div class="spbwc-vb-roundtrip" hidden aria-hidden="true">
                <!-- Top-level POST keys -->
                <input type="hidden" name="title" value="{{ options.title }}" />
                <input type="hidden" name="apply_for" value="{{ options.apply_for }}" />
                <input type="hidden" ng-repeat="pid in options.product_ids track by $index"
                       name="product_ids[]" value="{{ pid }}" />
                <input type="hidden" ng-repeat="cid in options.product_cats track by $index"
                       name="product_cats[]" value="{{ cid }}" />

                <!-- Display mode -->
                <input type="hidden" name="options[display_mode]" value="{{ options.display_mode }}" />

                <!-- Quantity & bulk pricing -->
                <input type="hidden" name="options[quantity_enable]" value="{{ options.quantity_enable }}" />
                <input type="hidden" name="options[quantity_type]" value="{{ options.quantity_type }}" />
                <input type="hidden" name="options[quantity_discount_type]" value="{{ options.quantity_discount_type }}" />
                <input type="hidden" name="options[quantity_min]" value="{{ options.quantity_min }}" />
                <input type="hidden" name="options[quantity_max]" value="{{ options.quantity_max }}" />
                <input type="hidden" name="options[quantity_step]" value="{{ options.quantity_step }}" />
                <input type="hidden" name="options[quantity_breaks_default]" value="{{ options.quantity_breaks_default }}" />
                <input type="hidden" ng-repeat="b in options.quantity_breaks track by $index"
                       name="options[quantity_breaks][{{ $index }}][val]" value="{{ b.val }}" />
                <input type="hidden" ng-repeat="b in options.quantity_breaks track by $index"
                       name="options[quantity_breaks][{{ $index }}][dis]" value="{{ b.dis }}" />
                <input type="hidden" ng-repeat="b in options.quantity_breaks track by $index"
                       name="options[quantity_breaks][{{ $index }}][default]" value="{{ b.default }}" />

                <!-- Design output (flat key/value, mirrors classic Output PDF tab) -->
                <input type="hidden" ng-repeat="(k, v) in options.design_output"
                       name="options[design_output][{{ k }}]" value="{{ v }}" />
            </div>

            <!-- ════════════════════════════════════════════════════════
                 SECTION 1 — Views
                 Markup mirrors the classic Designer tab (edit-option.php
                 lines 884-1008) so the same AngularJS handlers (addView,
                 set_view_base, removeView) wire up unchanged.
                 ════════════════════════════════════════════════════════ -->
            <section class="v2-card spbwc-vb-edit__section">
                <div class="v2-card__head v2-card__head--brand">
                    <h2 class="v2-card__title">
                        <span class="v2-card__title-icon">🖼️</span>
                        <?php esc_html_e( 'Views', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h2>
                    <span class="v2-card__sub"><?php esc_html_e( 'Front, Back, Inside — each side customers can decorate', 'storelly-product-builder-for-woocommerce' ); ?></span>
                </div>
                <div class="v2-card__body">
                    <p class="v2-form-row__help" style="margin:0 0 var(--nbd-space-3);">
                        <?php esc_html_e( 'Each view is a separate canvas with its own base image. Component layers placed on the canvas will be customisable per view.', 'storelly-product-builder-for-woocommerce' ); ?>
                    </p>

                    <!-- Empty state -->
                    <div class="v2-designer-empty" ng-show="!options.views || options.views.length === 0">
                        <span class="v2-designer-empty__icon" aria-hidden="true">🖼</span>
                        <h3 class="v2-designer-empty__title"><?php esc_html_e( 'No views yet', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                        <p class="v2-designer-empty__body">
                            <?php esc_html_e( 'Add a view to start configuring this visual. Each view holds the canvas base image and the designer fields placed on it.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                        <button type="button" class="v2-btn v2-btn--primary" ng-click="addView()">
                            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                            <?php esc_html_e( 'Add first view', 'storelly-product-builder-for-woocommerce' ); ?>
                        </button>
                    </div>

                    <!-- View grid -->
                    <div class="v2-views-grid" ng-show="options.views && options.views.length > 0">
                        <div class="v2-view-card" ng-repeat="view in options.views track by $index">
                            <div class="v2-view-card__thumb" ng-click="set_view_base($index)">
                                <img ng-if="view.base_url" ng-src="{{view.base_url}}" alt="{{view.name}}" />
                                <div ng-if="!view.base_url" class="v2-view-card__placeholder">
                                    <span aria-hidden="true">🖼</span>
                                    <small><?php esc_html_e( 'Click to upload base image', 'storelly-product-builder-for-woocommerce' ); ?></small>
                                </div>
                                <button type="button" class="v2-view-card__edit-btn"
                                        ng-click="$event.stopPropagation(); set_view_base($index);"
                                        title="<?php esc_attr_e( 'Change base image', 'storelly-product-builder-for-woocommerce' ); ?>">
                                    <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                </button>
                            </div>
                            <div class="v2-view-card__body">
                                <input class="v2-input v2-view-card__name"
                                       type="text"
                                       ng-model="view.name"
                                       name="options[views][{{$index}}][name]"
                                       placeholder="<?php esc_attr_e( 'View name (e.g. Front)', 'storelly-product-builder-for-woocommerce' ); ?>" />
                                <div class="v2-view-card__dims">
                                    <label>
                                        <span><?php esc_html_e( 'W', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                        <input class="v2-input" type="number" string-to-number min="0" step="1"
                                               ng-model="view.base_width"
                                               name="options[views][{{$index}}][base_width]" />
                                    </label>
                                    <span class="v2-view-card__times" aria-hidden="true">×</span>
                                    <label>
                                        <span><?php esc_html_e( 'H', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                        <input class="v2-input" type="number" string-to-number min="0" step="1"
                                               ng-model="view.base_height"
                                               name="options[views][{{$index}}][base_height]" />
                                    </label>
                                </div>
                                <input type="hidden" name="options[views][{{$index}}][base]" value="{{view.base}}" />
                            </div>
                            <button type="button" class="v2-view-card__remove"
                                    ng-click="removeView($index)"
                                    ng-show="options.views.length > 1"
                                    title="<?php esc_attr_e( 'Remove view', 'storelly-product-builder-for-woocommerce' ); ?>">✕</button>
                        </div>

                        <!-- Add view tile -->
                        <button type="button" class="v2-view-card v2-view-card--add" ng-click="addView()">
                            <span class="v2-view-card__plus" aria-hidden="true">+</span>
                            <strong><?php esc_html_e( 'Add view', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                            <small><?php esc_html_e( 'Back / Inside / 3rd angle…', 'storelly-product-builder-for-woocommerce' ); ?></small>
                        </button>
                    </div>
                </div>
            </section>

            <!-- ════════════════════════════════════════════════════════
                 SECTION 2 — Add components
                 ════════════════════════════════════════════════════════ -->
            <section class="v2-palette spbwc-vb-edit__section" ng-show="options.views && options.views.length > 0">
                <div class="v2-palette__head">
                    <p class="v2-palette__title"><?php esc_html_e( 'Add designer component', 'storelly-product-builder-for-woocommerce' ); ?></p>
                    <p class="v2-palette__sub"><?php esc_html_e( 'A component is a layer customers can swap (e.g. Frame, Wheels, Logo). Add one, then upload its attribute images below.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                </div>
                <?php /* vbAddField() wraps the classic add_field() handler with
                       (1) toast notification, (2) auto-expand the new card,
                       (3) smooth scroll-to + flash highlight. Defined in
                       static/js/visual-builder.js. */ ?>
                <div class="v2-palette__grid">
                    <button type="button" class="v2-chip" ng-click="vbAddField('nbpb_com')">
                        <span class="v2-chip__icon v2-chip__icon--com">⚛</span>
                        <span class="v2-chip__label">
                            <strong><?php esc_html_e( 'Designer Component', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                            <small><?php esc_html_e( 'Layered swatches the buyer picks — e.g. wheels, fabric, frame style. You upload the per-view images.', 'storelly-product-builder-for-woocommerce' ); ?></small>
                        </span>
                    </button>
                    <button type="button" class="v2-chip" ng-click="vbAddField('nbpb_text')">
                        <span class="v2-chip__icon v2-chip__icon--dt">Tx</span>
                        <span class="v2-chip__label">
                            <strong><?php esc_html_e( 'Designer Text', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                            <small><?php esc_html_e( 'Free text the buyer types (monogram, name, custom message). You set fonts and colors.', 'storelly-product-builder-for-woocommerce' ); ?></small>
                        </span>
                    </button>
                    <button type="button" class="v2-chip" ng-click="vbAddField('nbpb_image')">
                        <span class="v2-chip__icon v2-chip__icon--di">Im</span>
                        <span class="v2-chip__label">
                            <strong><?php esc_html_e( 'Designer Image', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                            <small><?php esc_html_e( 'Image the buyer uploads (their photo, logo, artwork). You set allowed formats and size.', 'storelly-product-builder-for-woocommerce' ); ?></small>
                        </span>
                    </button>
                </div>
            </section>

            <!-- ════════════════════════════════════════════════════════
                 SECTION 3 — Component cards (M6.5 — image-centric)
                 -------------------------------------------------------
                 We render ONLY nbpb_* fields directly, each as an image-
                 centric card with main image + attribute swatches grid.
                 Pricing fields stay in $scope.options.fields but are not
                 in the DOM — they still round-trip on save via
                 getJsonFields() which reads from $scope, not from the
                 form's DOM inputs.

                 Templates registered at the bottom of this file:
                   • nbd.nbpb_com   — legacy per-view matrix (used inside
                                       our card's "Advanced" section).
                   • nbd.nbpb_text  — text-block editor (fallback for
                                       nbpb_text card body until M6.5b).
                   • nbd.nbpb_image — image-placeholder editor (fallback
                                       until M6.5c).
                 Plus the field-body sub-templates that nbpb_text/image
                 reference (title etc).
                 ════════════════════════════════════════════════════════ -->
            <?php /* Sticky component nav — appears when there are ≥2 nbpb
                   fields. Click a chip → expand the corresponding card and
                   scroll it into view. Saves scrolling through a long stack
                   of expanded cards. Helper defined in visual-builder.js. */ ?>
            <nav class="spbwc-vb-edit__compnav"
                 ng-show="(options.fields | filter:{nbpb_type:'nbpb'}).length > 1"
                 aria-label="<?php esc_attr_e( 'Jump to component', 'storelly-product-builder-for-woocommerce' ); ?>">
                <span class="spbwc-vb-edit__compnav-label">
                    <span class="dashicons dashicons-menu-alt2" aria-hidden="true"></span>
                    <?php esc_html_e( 'Jump to:', 'storelly-product-builder-for-woocommerce' ); ?>
                </span>
                <button type="button"
                        class="spbwc-vb-edit__compnav-chip"
                        ng-repeat="(navFieldIdx, navField) in options.fields track by $index"
                        ng-if="navField.nbpb_type"
                        ng-click="vbFocusComponent(navFieldIdx)"
                        ng-class="{'is-active': navField.isExpand}"
                        title="{{ navField.general.title.value || 'Untitled' }}">
                    <span class="spbwc-vb-edit__compnav-icon"
                          ng-switch="navField.nbpb_type" aria-hidden="true">
                        <span ng-switch-when="nbpb_text">Tx</span>
                        <span ng-switch-when="nbpb_image">Im</span>
                        <span ng-switch-default>⚛</span>
                    </span>
                    <span class="spbwc-vb-edit__compnav-name">{{ navField.general.title.value || '—' }}</span>
                </button>
            </nav>

            <section class="spbwc-vb-edit__section" ng-show="options.fields && options.fields.length > 0">
                <div class="v2-section-head">
                    <span class="v2-section-head__icon">🧩</span>
                    <?php esc_html_e( 'Components', 'storelly-product-builder-for-woocommerce' ); ?>
                    <span class="spbwc-vb-edit__section-hint" style="margin-left:auto; font-weight:var(--font-normal); color:var(--nbd-st-text-mute);">
                        <?php esc_html_e( 'Pricing fields stay in the classic editor.', 'storelly-product-builder-for-woocommerce' ); ?>
                    </span>
                </div>

                <!-- Empty state — substring match on 'nbpb' counts nbpb_com /
                     nbpb_text / nbpb_image without needing a scope method. -->
                <div class="v2-designer-empty"
                     ng-show="(options.fields | filter:{nbpb_type:'nbpb'}).length === 0">
                    <span class="v2-designer-empty__icon" aria-hidden="true">🧩</span>
                    <h3 class="v2-designer-empty__title"><?php esc_html_e( 'No designer components yet', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                    <p class="v2-designer-empty__body">
                        <?php esc_html_e( 'Use the chips above to add your first Designer Component, Text, or Image. The card will appear here with attribute settings.', 'storelly-product-builder-for-woocommerce' ); ?>
                    </p>
                </div>

                <div class="spbwc-vb-comp-cards">
                    <?php /*
                        ng-repeat + ng-if on the same element: ng-repeat
                        (priority 1000) iterates the FULL options.fields[]
                        array so `fieldIndex` is the real array index —
                        which is what every classic handler (set_component_
                        icon, set_attribute_image, copy_field, delete_field,
                        sort_field) expects. ng-if (priority 600) then
                        removes the DOM for non-nbpb fields, so only the
                        designer components actually render. Pricing fields
                        stay in $scope.options.fields and round-trip on
                        save via getJsonFields() (which reads from $scope,
                        not from DOM).
                    */ ?>
                    <article class="spbwc-vb-comp-card"
                             ng-repeat="(fieldIndex, field) in options.fields track by field.id"
                             ng-if="field.nbpb_type"
                             ng-class="{'is-expanded': field.isExpand, 'is-disabled': field.general.enabled.value === 'n'}"
                             data-field-index="{{fieldIndex}}">

                        <!-- Header (shared across all 3 nbpb types) -->
                        <header class="spbwc-vb-comp-card__head" ng-click="onHeaderClick(fieldIndex, $event)">
                            <span class="spbwc-vb-comp-card__icon" aria-hidden="true">
                                <img ng-if="field.general.component_icon && field.general.component_icon_url"
                                     ng-src="{{field.general.component_icon_url}}" alt="" />
                                <span ng-if="!field.general.component_icon || !field.general.component_icon_url"
                                      class="spbwc-vb-comp-card__icon-placeholder"
                                      ng-switch="field.nbpb_type">
                                    <span ng-switch-when="nbpb_text">Tx</span>
                                    <span ng-switch-when="nbpb_image">Im</span>
                                    <span ng-switch-default>⚛</span>
                                </span>
                            </span>
                            <input class="spbwc-vb-comp-card__title"
                                   type="text"
                                   ng-model="field.general.title.value"
                                   placeholder="<?php esc_attr_e( 'Component name', 'storelly-product-builder-for-woocommerce' ); ?>"
                                   ng-click="$event.stopPropagation()" />
                            <span class="spbwc-vb-comp-card__meta" ng-hide="field.isExpand">
                                <span class="spbwc-vb-comp-card__type-pill"
                                      ng-switch="field.nbpb_type">
                                    <span ng-switch-when="nbpb_com"><?php esc_html_e( 'Component', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                    <span ng-switch-when="nbpb_text"><?php esc_html_e( 'Text', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                    <span ng-switch-when="nbpb_image"><?php esc_html_e( 'Image', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                </span>
                                <span ng-if="field.nbpb_type === 'nbpb_com'" class="spbwc-vb-comp-card__attr-count">
                                    {{ (field.general.attributes.options || []).length }} <?php esc_html_e( 'options', 'storelly-product-builder-for-woocommerce' ); ?>
                                </span>
                            </span>
                            <?php /* Collapsed-state mini-strip of attribute swatches (#6).
                                   Quickly identifies which component is which when many
                                   cards are stacked. Only shown for nbpb_com (text/image
                                   don't have swatches). Limit to 5 thumbs + "+N" more. */ ?>
                            <span class="spbwc-vb-comp-card__strip"
                                  ng-if="!field.isExpand && field.nbpb_type === 'nbpb_com' && (field.general.attributes.options || []).length > 0">
                                <span ng-repeat="op in (field.general.attributes.options || []) | limitTo: 5 track by $index"
                                      class="spbwc-vb-comp-card__strip-swatch"
                                      ng-class="{'has-image': !!op.image_url}"
                                      ng-style="op.image_url ? {'background-image': 'url(' + op.image_url + ')'} : {'background-color': op.color || '#f3f4f6'}"
                                      title="{{op.name || '—'}}"></span>
                                <span ng-if="(field.general.attributes.options || []).length > 5"
                                      class="spbwc-vb-comp-card__strip-more">
                                    +{{ field.general.attributes.options.length - 5 }}
                                </span>
                            </span>
                            <?php /* Action arrangement (left → right):
                                   1. Expand/collapse — PRIMARY, blue-filled,
                                      surfaced FIRST since it's the most-used.
                                   2-3. Sort up/down — secondary
                                   4. Duplicate — secondary
                                   5. Delete — danger, last.
                                   All buttons have explicit tooltips. */ ?>
                            <span class="spbwc-vb-comp-card__actions" ng-click="$event.stopPropagation()">
                                <button type="button"
                                        class="spbwc-vb-comp-card__action spbwc-vb-comp-card__action--primary"
                                        ng-click="toggleExpandField(fieldIndex, $event)"
                                        ng-attr-aria-expanded="{{field.isExpand ? 'true' : 'false'}}"
                                        ng-attr-title="{{ field.isExpand ? '<?php echo esc_js( __( 'Collapse component settings', 'storelly-product-builder-for-woocommerce' ) ); ?>' : '<?php echo esc_js( __( 'Expand component settings to edit attributes and per-view images', 'storelly-product-builder-for-woocommerce' ) ); ?>' }}">
                                    <span ng-show="field.isExpand" class="dashicons dashicons-arrow-up" aria-hidden="true"></span>
                                    <span ng-hide="field.isExpand" class="dashicons dashicons-arrow-down" aria-hidden="true"></span>
                                </button>
                                <span class="spbwc-vb-comp-card__action-sep" aria-hidden="true"></span>
                                <button type="button" class="spbwc-vb-comp-card__action"
                                        ng-click="sort_field(fieldIndex, 'up')"
                                        title="<?php esc_attr_e( 'Move component up in the list', 'storelly-product-builder-for-woocommerce' ); ?>">
                                    <span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
                                </button>
                                <button type="button" class="spbwc-vb-comp-card__action"
                                        ng-click="sort_field(fieldIndex, 'down')"
                                        title="<?php esc_attr_e( 'Move component down in the list', 'storelly-product-builder-for-woocommerce' ); ?>">
                                    <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                                </button>
                                <button type="button" class="spbwc-vb-comp-card__action"
                                        ng-click="copy_field(fieldIndex)"
                                        title="<?php esc_attr_e( 'Duplicate this component (clones attributes, images and settings)', 'storelly-product-builder-for-woocommerce' ); ?>">
                                    <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                                </button>
                                <?php /* 2-stage inline confirm — replaces native window.confirm.
                                       First click sets field._confirmDelete and the button
                                       morphs (CSS .is-confirming) to "click again to delete".
                                       Second click within 3s actually removes the field. */ ?>
                                <button type="button"
                                        class="spbwc-vb-comp-card__action spbwc-vb-comp-card__action--danger"
                                        ng-class="{'is-confirming': field._confirmDelete}"
                                        ng-click="vbDeleteComponent(fieldIndex)"
                                        ng-attr-title="{{ field._confirmDelete ? '<?php echo esc_js( __( 'Click again to confirm deletion', 'storelly-product-builder-for-woocommerce' ) ); ?>' : '<?php echo esc_js( __( 'Delete this component (click twice to confirm)', 'storelly-product-builder-for-woocommerce' ) ); ?>' }}">
                                    <span ng-if="!field._confirmDelete" class="dashicons dashicons-trash" aria-hidden="true"></span>
                                    <span ng-if="field._confirmDelete" class="spbwc-vb-comp-card__action-confirm">
                                        <?php esc_html_e( 'Click again', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </span>
                                </button>
                            </span>
                        </header>

                        <!-- Body (only when expanded) -->
                        <div class="spbwc-vb-comp-card__body" ng-show="field.isExpand">

                            <!-- nbpb_com — primary work: per-attribute card with inline
                                 per-view image cells. Component icon + per-attribute layered
                                 images are surfaced together because uploading "what each
                                 attribute looks like on each view" IS the Visual Builder's
                                 reason to exist. -->
                            <div ng-if="field.nbpb_type === 'nbpb_com'" class="spbwc-vb-comp-card__body-com">

                                <!-- Compact component-icon strip (tiny — secondary; the per-view
                                     attribute images below are the actual layering work). -->
                                <section class="spbwc-vb-comp-iconbar">
                                    <button type="button"
                                            class="spbwc-vb-comp-iconbar__thumb"
                                            ng-class="{'has-image': !!field.general.component_icon}"
                                            ng-click="set_component_icon(fieldIndex)"
                                            title="<?php esc_attr_e( 'Component icon — shown in the buyer-side component picker. Click to upload.', 'storelly-product-builder-for-woocommerce' ); ?>">
                                        <img ng-if="field.general.component_icon" ng-src="{{field.general.component_icon_url}}" alt="" />
                                        <span ng-if="!field.general.component_icon" class="dashicons dashicons-format-image" aria-hidden="true"></span>
                                    </button>
                                    <div class="spbwc-vb-comp-iconbar__meta">
                                        <span class="spbwc-vb-comp-iconbar__label">
                                            <?php esc_html_e( 'Component icon', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </span>
                                        <small class="spbwc-vb-comp-iconbar__hint">
                                            <?php esc_html_e( 'Optional tiny picker thumbnail (64×64 PNG). The real visual work lives per-attribute below.', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </small>
                                    </div>
                                    <input type="hidden"
                                           name="options[fields][{{fieldIndex}}][general][component_icon]"
                                           value="{{field.general.component_icon}}" />
                                </section>

                                <!-- Attributes — each attribute is a self-contained row with
                                     name / price / swatch + per-view image cells inline.
                                     This collapses the old "Attribute options grid" + the
                                     sprawled "Advanced matrix" into ONE coherent unit. -->
                                <section class="spbwc-vb-comp-section">
                                    <header class="spbwc-vb-comp-section__head">
                                        <label class="spbwc-vb-comp-section__label">
                                            <?php esc_html_e( 'Attributes & per-view images', 'storelly-product-builder-for-woocommerce' ); ?>
                                            <span class="spbwc-vb-comp-section__count">
                                                ({{ (field.general.attributes.options || []).length }})
                                            </span>
                                        </label>
                                        <button type="button" class="spbwc-vb-comp-section__add"
                                                ng-click="add_attribute(fieldIndex, 'attributes')">
                                            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                            <?php esc_html_e( 'Add option', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </button>
                                    </header>
                                    <p class="spbwc-vb-comp-section__hint">
                                        <?php esc_html_e( 'Each option = one swatch the buyer picks. Per-view images get layered on top of the matching base view. Tip: drag multiple image files onto the area below to create attributes in bulk.', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </p>

                                    <div class="spbwc-vb-attr-rows spbwc-vb-attr-drop-zone"
                                         ng-show="field.general.attributes.options.length > 0"
                                         vb-drop-zone="vbHandleBulkAttributeDrop($event, fieldIndex)">
                                        <div class="spbwc-vb-attr-row"
                                             ng-repeat="op in field.general.attributes.options track by $index"
                                             ng-init="attrIdx = $index; op.price = (op.price && op.price.length) ? op.price : ['']">

                                            <!-- Row head: swatch + name + price + actions -->
                                            <div class="spbwc-vb-attr-row__head">
                                                <button type="button"
                                                        class="spbwc-vb-attr-row__swatch"
                                                        ng-class="{'has-image': !!op.image_url}"
                                                        ng-click="set_attribute_image(fieldIndex, attrIdx, 'image', 'image_url')"
                                                        title="<?php esc_attr_e( 'Swatch (buyer-side picker preview). Click to upload.', 'storelly-product-builder-for-woocommerce' ); ?>">
                                                    <img ng-if="op.image_url" ng-src="{{op.image_url}}" alt="" />
                                                    <span ng-if="!op.image_url" class="dashicons dashicons-format-image" aria-hidden="true"></span>
                                                    <input type="hidden"
                                                           name="options[fields][{{fieldIndex}}][general][attributes][options][{{attrIdx}}][image]"
                                                           value="{{op.image}}" />
                                                </button>
                                                <input class="spbwc-vb-attr-row__name"
                                                       type="text"
                                                       ng-model="op.name"
                                                       name="options[fields][{{fieldIndex}}][general][attributes][options][{{attrIdx}}][name]"
                                                       placeholder="<?php esc_attr_e( 'Option name', 'storelly-product-builder-for-woocommerce' ); ?>" />
                                                <label class="spbwc-vb-attr-row__price">
                                                    <span class="spbwc-vb-attr-row__price-prefix">+$</span>
                                                    <input type="text"
                                                           ng-model="op.price[0]"
                                                           name="options[fields][{{fieldIndex}}][general][attributes][options][{{attrIdx}}][price][0]"
                                                           placeholder="0" />
                                                </label>
                                                <?php /* Duplicate — deep-clones the attribute including
                                                       its pb_config slot so per-view images carry over.
                                                       Helper defined in static/js/visual-builder.js. */ ?>
                                                <button type="button"
                                                        class="spbwc-vb-attr-row__dup"
                                                        ng-click="vbDuplicateAttribute(field, attrIdx)"
                                                        title="<?php esc_attr_e( 'Duplicate option (clones name, price and per-view images)', 'storelly-product-builder-for-woocommerce' ); ?>">
                                                    <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                                                </button>
                                                <?php /* 2-stage inline confirm same as component delete. */ ?>
                                                <button type="button"
                                                        class="spbwc-vb-attr-row__delete"
                                                        ng-class="{'is-confirming': op._confirmDelete}"
                                                        ng-click="vbDeleteAttribute(field, attrIdx)"
                                                        ng-attr-title="{{ op._confirmDelete ? '<?php echo esc_js( __( 'Click again to confirm', 'storelly-product-builder-for-woocommerce' ) ); ?>' : '<?php echo esc_js( __( 'Remove option (click twice to confirm)', 'storelly-product-builder-for-woocommerce' ) ); ?>' }}">
                                                    <span ng-if="!op._confirmDelete" class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                                                    <span ng-if="op._confirmDelete" class="spbwc-vb-attr-row__delete-confirm">
                                                        <?php esc_html_e( 'Click again', 'storelly-product-builder-for-woocommerce' ); ?>
                                                    </span>
                                                </button>
                                            </div>

                                            <!-- Per-view image cells. pb_config[attrIdx][0]
                                                 (sub-attr index 0 — flat attributes only,
                                                 sub_attributes still use classic editor).
                                                 buildPbConfigFlat() auto-creates the slots
                                                 after add_attribute() → safe to address. -->
                                            <div class="spbwc-vb-attr-row__views"
                                                 ng-show="options.views && options.views.length > 0">
                                                <div class="spbwc-vb-attr-row__view"
                                                     ng-repeat="view in options.views track by $index"
                                                     ng-init="viewIdx = $index; cfg = (field.general.pb_config && field.general.pb_config[attrIdx] && field.general.pb_config[attrIdx][0] && field.general.pb_config[attrIdx][0].views && field.general.pb_config[attrIdx][0].views[viewIdx]) ? field.general.pb_config[attrIdx][0].views[viewIdx] : {}">
                                                    <span class="spbwc-vb-attr-row__view-name">{{view.name}}</span>
                                                    <?php /* Empty cell shows the base view as a faded
                                                           background so the admin orients to which view
                                                           they're populating. ng-style sets bg-image
                                                           only when no per-attr image is uploaded. */ ?>
                                                    <?php /* vb-drop-zone: drag a file from desktop onto this
                                                           cell → vbHandleViewDrop uploads + sets image. The
                                                           click handler stays for picker fallback. The
                                                           cfg._uploading flag toggles the .is-uploading CSS. */ ?>
                                                    <button type="button"
                                                            class="spbwc-vb-attr-row__view-img"
                                                            ng-class="{'has-image': !!cfg.image && cfg.image != 0, 'has-base-hint': (!cfg.image || cfg.image == 0) && view.base_url, 'is-uploading': cfg._uploading}"
                                                            ng-style="(!cfg.image || cfg.image == 0) && view.base_url ? {'background-image': 'url(' + view.base_url + ')'} : null"
                                                            ng-click="set_view_config_image(fieldIndex, attrIdx, 0, viewIdx)"
                                                            vb-drop-zone="vbHandleViewDrop($event, fieldIndex, attrIdx, viewIdx)"
                                                            title="<?php esc_attr_e( 'Click to upload, or drag-drop an image file here.', 'storelly-product-builder-for-woocommerce' ); ?>">
                                                        <img ng-if="cfg.image && cfg.image != 0" ng-src="{{cfg.image_url}}" alt="" />
                                                        <span ng-if="!cfg.image || cfg.image == 0" class="dashicons dashicons-upload" aria-hidden="true"></span>
                                                        <span ng-if="cfg.image && cfg.image != 0"
                                                              class="spbwc-vb-attr-row__view-remove dashicons dashicons-no"
                                                              ng-click="$event.stopPropagation(); remove_view_config_image(fieldIndex, attrIdx, 0, viewIdx);"
                                                              role="button"
                                                              title="<?php esc_attr_e( 'Remove image', 'storelly-product-builder-for-woocommerce' ); ?>"></span>
                                                        <input type="hidden"
                                                               name="options[fields][{{fieldIndex}}][general][pb_config][{{attrIdx}}][0][views][{{viewIdx}}][image]"
                                                               value="{{cfg.image}}" />
                                                    </button>
                                                    <div class="spbwc-vb-attr-row__view-bottom">
                                                        <label class="spbwc-vb-attr-row__view-show">
                                                            <input type="checkbox"
                                                                   ng-model="cfg.display"
                                                                   ng-true-value="'on'"
                                                                   ng-false-value="''"
                                                                   name="options[fields][{{fieldIndex}}][general][pb_config][{{attrIdx}}][0][views][{{viewIdx}}][display]" />
                                                            <span><?php esc_html_e( 'Show', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                                        </label>
                                                        <?php /* "Apply to all views" — copies this view's
                                                               image + display flag to every other view.
                                                               Visible only when this cell has an image AND
                                                               there are at least 2 views. */ ?>
                                                        <button type="button"
                                                                class="spbwc-vb-attr-row__view-apply"
                                                                ng-show="cfg.image && cfg.image != 0 && options.views.length > 1"
                                                                ng-click="vbApplyImageToAllViews(field, attrIdx, viewIdx)"
                                                                title="<?php esc_attr_e( 'Apply this image to all other views of this option', 'storelly-product-builder-for-woocommerce' ); ?>">
                                                            <span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span>
                                                            <?php esc_html_e( 'Apply to all', 'storelly-product-builder-for-woocommerce' ); ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Empty state when no attributes yet — also a bulk drop zone. -->
                                    <div class="spbwc-vb-attr-empty spbwc-vb-attr-drop-zone"
                                         ng-show="!field.general.attributes.options || field.general.attributes.options.length === 0"
                                         vb-drop-zone="vbHandleBulkAttributeDrop($event, fieldIndex)">
                                        <span class="spbwc-vb-attr-empty__icon" aria-hidden="true">📦</span>
                                        <p>
                                            <?php esc_html_e( 'No options yet. Add the first one — e.g. "Leather", "Cotton", "Suede".', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </p>
                                        <p class="spbwc-vb-attr-empty__drop-hint">
                                            <?php esc_html_e( 'Or drag multiple image files here to create attributes in one go.', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </p>
                                        <button type="button" class="spbwc-cta-btn spbwc-cta-btn--solid spbwc-cta-btn--sm"
                                                ng-click="add_attribute(fieldIndex, 'attributes')">
                                            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                            <?php esc_html_e( 'Add first option', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </button>
                                    </div>
                                </section>

                                <!-- Sub-attributes / classic matrix escape hatch for power users.
                                     Kept collapsed because flat attributes (the 95% case) work
                                     fully via the rows above. -->
                                <section class="spbwc-vb-comp-section spbwc-vb-comp-section--advanced"
                                         ng-show="options.views && options.views.length > 0 && field.general.attributes.options.length > 0">
                                    <button type="button"
                                            class="spbwc-vb-comp-section__advanced-toggle"
                                            ng-click="field._advancedOpen = !field._advancedOpen">
                                        <span ng-show="field._advancedOpen" class="dashicons dashicons-arrow-down" aria-hidden="true"></span>
                                        <span ng-hide="field._advancedOpen" class="dashicons dashicons-arrow-right" aria-hidden="true"></span>
                                        <?php esc_html_e( 'Sub-attributes & classic matrix', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </button>
                                    <div class="spbwc-vb-comp-section__advanced-body" ng-show="field._advancedOpen">
                                        <p class="spbwc-vb-comp-section__hint">
                                            <?php esc_html_e( 'Use when an option has nested sub-options (e.g. "Cotton" with "Plain / Striped"). Falls back to the legacy matrix table.', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </p>
                                        <ng-include src="'nbd.nbpb_com'"></ng-include>
                                    </div>
                                </section>
                            </div>

                            <!-- nbpb_text — M6.5b compact card body. Replaces the
                                 sprawled classic table template with grouped
                                 toggle rows + curated color palette + per-view
                                 visibility chips. Same data binding so the
                                 jsonFields round-trip is preserved. -->
                            <div ng-if="field.nbpb_type === 'nbpb_text'" class="spbwc-vb-comp-card__body-text spbwc-vb-comp-card__body-com">

                                <section class="spbwc-vb-comp-section">
                                    <header class="spbwc-vb-comp-section__head">
                                        <label class="spbwc-vb-comp-section__label">
                                            <?php esc_html_e( 'Default text', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </label>
                                    </header>
                                    <p class="spbwc-vb-comp-section__hint">
                                        <?php esc_html_e( 'Pre-filled text shown when the buyer opens the designer. They can overwrite it.', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </p>
                                    <input type="text"
                                           class="spbwc-vb-text-input"
                                           ng-model="field.general.nbpb_text_configs.default_text"
                                           name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][default_text]"
                                           placeholder="<?php esc_attr_e( 'e.g. Your name here', 'storelly-product-builder-for-woocommerce' ); ?>" />
                                </section>

                                <section class="spbwc-vb-comp-section">
                                    <header class="spbwc-vb-comp-section__head">
                                        <label class="spbwc-vb-comp-section__label">
                                            <?php esc_html_e( 'Fonts', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </label>
                                    </header>
                                    <label class="spbwc-vb-toggle-row">
                                        <span class="spbwc-vb-toggle-row__label">
                                            <?php esc_html_e( 'Buyer can pick a font', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </span>
                                        <input type="checkbox"
                                               class="spbwc-vb-toggle-row__input"
                                               ng-model="field.general.nbpb_text_configs.allow_font_family"
                                               ng-true-value="'y'" ng-false-value="'n'"
                                               name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][allow_font_family]" />
                                    </label>
                                    <label class="spbwc-vb-toggle-row"
                                           ng-show="field.general.nbpb_text_configs.allow_font_family === 'y'">
                                        <span class="spbwc-vb-toggle-row__label">
                                            <?php esc_html_e( 'Allow all fonts', 'storelly-product-builder-for-woocommerce' ); ?>
                                            <small><?php esc_html_e( 'When off, only the lists below are offered.', 'storelly-product-builder-for-woocommerce' ); ?></small>
                                        </span>
                                        <input type="checkbox"
                                               class="spbwc-vb-toggle-row__input"
                                               ng-model="field.general.nbpb_text_configs.allow_all_font"
                                               ng-true-value="'y'" ng-false-value="'n'"
                                               name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][allow_all_font]" />
                                    </label>
                                    <?php /* Hidden inputs round-trip the multi-select selections.
                                           Picking actual fonts still happens via the classic editor
                                           link below (select2 dropdown is heavy + rarely used). */ ?>
                                    <div class="spbwc-vb-multi-row"
                                         ng-show="field.general.nbpb_text_configs.allow_font_family === 'y' && field.general.nbpb_text_configs.allow_all_font === 'n'">
                                        <input type="hidden"
                                               ng-repeat="f in field.general.nbpb_text_configs.custom_fonts track by $index"
                                               name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][custom_fonts][]"
                                               value="{{f}}" />
                                        <input type="hidden"
                                               ng-repeat="g in field.general.nbpb_text_configs.google_fonts track by $index"
                                               name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][google_fonts][]"
                                               value="{{g}}" />
                                        <p class="spbwc-vb-multi-row__count">
                                            <?php esc_html_e( 'Selected:', 'storelly-product-builder-for-woocommerce' ); ?>
                                            <strong>{{ (field.general.nbpb_text_configs.custom_fonts || []).length }}</strong>
                                            <?php esc_html_e( 'custom,', 'storelly-product-builder-for-woocommerce' ); ?>
                                            <strong>{{ (field.general.nbpb_text_configs.google_fonts || []).length }}</strong>
                                            <?php esc_html_e( 'Google.', 'storelly-product-builder-for-woocommerce' ); ?>
                                            <a href="<?php echo esc_url( add_query_arg( array( 'page' => SPBWC_PB_BUILDER_SLUG, 'action' => 'edit', 'id' => absint( $current_id ) ), admin_url( 'admin.php' ) ) ); ?>">
                                                <?php esc_html_e( 'Pick fonts in classic →', 'storelly-product-builder-for-woocommerce' ); ?>
                                            </a>
                                        </p>
                                    </div>
                                </section>

                                <section class="spbwc-vb-comp-section">
                                    <header class="spbwc-vb-comp-section__head">
                                        <label class="spbwc-vb-comp-section__label">
                                            <?php esc_html_e( 'Colors', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </label>
                                    </header>
                                    <label class="spbwc-vb-toggle-row">
                                        <span class="spbwc-vb-toggle-row__label">
                                            <?php esc_html_e( 'Buyer can change color', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </span>
                                        <input type="checkbox"
                                               class="spbwc-vb-toggle-row__input"
                                               ng-model="field.general.nbpb_text_configs.allow_change_color"
                                               ng-true-value="'y'" ng-false-value="'n'"
                                               name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][allow_change_color]" />
                                    </label>
                                    <label class="spbwc-vb-toggle-row"
                                           ng-show="field.general.nbpb_text_configs.allow_change_color === 'y'">
                                        <span class="spbwc-vb-toggle-row__label">
                                            <?php esc_html_e( 'Allow full color picker', 'storelly-product-builder-for-woocommerce' ); ?>
                                            <small><?php esc_html_e( 'When off, offer only the swatches below.', 'storelly-product-builder-for-woocommerce' ); ?></small>
                                        </span>
                                        <input type="checkbox"
                                               class="spbwc-vb-toggle-row__input"
                                               ng-model="field.general.nbpb_text_configs.allow_all_color"
                                               ng-true-value="'y'" ng-false-value="'n'"
                                               name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][allow_all_color]" />
                                    </label>
                                    <?php /* Curated swatch list. Reuses classic handlers
                                           add_text_configs_color / remove_text_configs_color
                                           so add/remove works without new JS. */ ?>
                                    <div class="spbwc-vb-color-list"
                                         ng-show="field.general.nbpb_text_configs.allow_change_color === 'y' && field.general.nbpb_text_configs.allow_all_color === 'n'">
                                        <div class="spbwc-vb-color-row"
                                             ng-repeat="(clIndex, color) in field.general.nbpb_text_configs.colors track by $index">
                                            <span class="spbwc-vb-color-row__swatch"
                                                  ng-style="{'background-color': color.color || '#ffffff'}"></span>
                                            <input type="text"
                                                   class="spbwc-vb-color-row__name"
                                                   ng-model="color.name"
                                                   name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][colors][{{clIndex}}][name]"
                                                   placeholder="<?php esc_attr_e( 'Color name', 'storelly-product-builder-for-woocommerce' ); ?>" />
                                            <input type="text"
                                                   class="spbwc-vb-color-row__hex"
                                                   ng-model="color.color"
                                                   name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][colors][{{clIndex}}][color]"
                                                   placeholder="#000000" />
                                            <button type="button"
                                                    class="spbwc-vb-color-row__remove"
                                                    ng-click="remove_text_configs_color(fieldIndex, clIndex)"
                                                    title="<?php esc_attr_e( 'Remove color', 'storelly-product-builder-for-woocommerce' ); ?>">
                                                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                                            </button>
                                        </div>
                                        <button type="button"
                                                class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm"
                                                ng-click="add_text_configs_color(fieldIndex)">
                                            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                            <?php esc_html_e( 'Add color', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </button>
                                    </div>
                                </section>

                                <section class="spbwc-vb-comp-section"
                                         ng-show="options.views && options.views.length > 0">
                                    <header class="spbwc-vb-comp-section__head">
                                        <label class="spbwc-vb-comp-section__label">
                                            <?php esc_html_e( 'Visible on views', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </label>
                                    </header>
                                    <p class="spbwc-vb-comp-section__hint">
                                        <?php esc_html_e( 'Pick which views show this text. Position is set later on the buyer-side canvas.', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </p>
                                    <div class="spbwc-vb-view-toggles">
                                        <label class="spbwc-vb-view-toggle"
                                               ng-repeat="view in options.views track by $index"
                                               ng-init="vTextIdx = $index; vTextCfg = (field.general.nbpb_text_configs.views && field.general.nbpb_text_configs.views[vTextIdx]) ? field.general.nbpb_text_configs.views[vTextIdx] : {}">
                                            <input type="checkbox"
                                                   ng-model="vTextCfg.display"
                                                   ng-true-value="'on'" ng-false-value="''"
                                                   name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][views][{{vTextIdx}}][display]" />
                                            <span class="spbwc-vb-view-toggle__thumb"
                                                  ng-style="view.base_url ? {'background-image': 'url(' + view.base_url + ')'} : null"></span>
                                            <span class="spbwc-vb-view-toggle__name">{{view.name}}</span>
                                        </label>
                                    </div>
                                </section>
                            </div>

                            <!-- nbpb_image — M6.5b compact card body. Image
                                 placeholder where the buyer uploads their own
                                 artwork at runtime. Only per-view visibility
                                 lives in admin data; position is on canvas. -->
                            <div ng-if="field.nbpb_type === 'nbpb_image'" class="spbwc-vb-comp-card__body-image spbwc-vb-comp-card__body-com">

                                <section class="spbwc-vb-comp-section">
                                    <header class="spbwc-vb-comp-section__head">
                                        <label class="spbwc-vb-comp-section__label">
                                            <?php esc_html_e( 'Image placeholder', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </label>
                                    </header>
                                    <p class="spbwc-vb-comp-section__hint">
                                        <?php esc_html_e( 'A slot the buyer fills with their own image (photo, logo, artwork). Pick which views this placeholder appears on; position is set on the buyer-side canvas.', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </p>
                                </section>

                                <section class="spbwc-vb-comp-section"
                                         ng-show="options.views && options.views.length > 0">
                                    <header class="spbwc-vb-comp-section__head">
                                        <label class="spbwc-vb-comp-section__label">
                                            <?php esc_html_e( 'Visible on views', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </label>
                                    </header>
                                    <div class="spbwc-vb-view-toggles">
                                        <label class="spbwc-vb-view-toggle"
                                               ng-repeat="view in options.views track by $index"
                                               ng-init="vImgIdx = $index; vImgCfg = (field.general.nbpb_image_configs.views && field.general.nbpb_image_configs.views[vImgIdx]) ? field.general.nbpb_image_configs.views[vImgIdx] : {}">
                                            <input type="checkbox"
                                                   ng-model="vImgCfg.display"
                                                   ng-true-value="'on'" ng-false-value="''"
                                                   name="options[fields][{{fieldIndex}}][general][nbpb_image_configs][views][{{vImgIdx}}][display]" />
                                            <span class="spbwc-vb-view-toggle__thumb"
                                                  ng-style="view.base_url ? {'background-image': 'url(' + view.base_url + ')'} : null"></span>
                                            <span class="spbwc-vb-view-toggle__name">{{view.name}}</span>
                                        </label>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <?php /* ════════════════════════════════════════════════════════
                 SECTION 4 — Output (PDF)
                 -------------------------------------------------------
                 Print output settings. Migrated from the classic edit
                 screen tab (edit-option.php:1013-1046) so Visual Builder
                 owns the entire "buyer-visible artwork" surface — views,
                 components and how the final print is rendered live
                 here together.
                 ════════════════════════════════════════════════════════ */ ?>
            <section class="spbwc-vb-edit__section spbwc-vb-output">
                <div class="v2-section-head">
                    <span class="v2-section-head__icon">📄</span>
                    <?php esc_html_e( 'PDF output for invoicing & production', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
                <?php /* Explain what this section is FOR — admins seeing a bare
                       "DPI / Dimension unit" form often don't realise the
                       downstream impact on invoice PDFs and the print shop. */ ?>
                <div class="spbwc-vb-output__intro">
                    <p>
                        <?php esc_html_e( 'When a buyer customises this product and checks out, Storelly renders the configured composition (base view + attribute layers) as a print-ready PDF. That PDF is:', 'storelly-product-builder-for-woocommerce' ); ?>
                    </p>
                    <ul class="spbwc-vb-output__use-cases">
                        <li>
                            <span class="spbwc-vb-output__use-icon" aria-hidden="true">🧾</span>
                            <strong><?php esc_html_e( 'Attached to the order invoice / confirmation', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                            <span><?php esc_html_e( 'so the buyer sees exactly what they ordered.', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        </li>
                        <li>
                            <span class="spbwc-vb-output__use-icon" aria-hidden="true">🏭</span>
                            <strong><?php esc_html_e( 'Handed to your production team', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                            <span><?php esc_html_e( '(or the print shop / fulfilment partner) to manufacture the real product.', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        </li>
                    </ul>
                    <p class="spbwc-vb-output__intro-tail">
                        <?php esc_html_e( 'The two settings below control the render quality and how dimensions are interpreted.', 'storelly-product-builder-for-woocommerce' ); ?>
                    </p>
                </div>
                <div class="spbwc-vb-output__grid">
                    <div class="spbwc-vb-output__field">
                        <label class="spbwc-vb-output__label" for="spbwc-vb-dpi">
                            <?php esc_html_e( 'Resolution (DPI)', 'storelly-product-builder-for-woocommerce' ); ?>
                        </label>
                        <p class="spbwc-vb-output__hint">
                            <?php esc_html_e( '300 = print-quality, ready for production. 72 = lighter PDFs fine for invoice attachments / on-screen review. Higher DPI → sharper print but bigger file.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                        <input id="spbwc-vb-dpi"
                               type="text" string-to-number
                               class="spbwc-vb-text-input"
                               ng-model="options.design_output.dpi"
                               name="options[design_output][dpi]"
                               placeholder="300"
                               autocomplete="off" />
                    </div>
                    <div class="spbwc-vb-output__field">
                        <label class="spbwc-vb-output__label" for="spbwc-vb-unit">
                            <?php esc_html_e( 'Dimension unit', 'storelly-product-builder-for-woocommerce' ); ?>
                        </label>
                        <p class="spbwc-vb-output__hint">
                            <?php esc_html_e( 'The unit (cm / in / mm / px) used to interpret each view\'s W×H. Match what your production team measures in — wrong unit = wrong physical print size.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                        <select id="spbwc-vb-unit"
                                class="spbwc-vb-output__select"
                                ng-model="options.design_output.dimension_unit"
                                name="options[design_output][dimension_unit]">
                            <option value="cm"><?php esc_html_e( 'cm — Centimeters (most common for production)', 'storelly-product-builder-for-woocommerce' ); ?></option>
                            <option value="in"><?php esc_html_e( 'in — Inches', 'storelly-product-builder-for-woocommerce' ); ?></option>
                            <option value="mm"><?php esc_html_e( 'mm — Millimeters', 'storelly-product-builder-for-woocommerce' ); ?></option>
                            <option value="px"><?php esc_html_e( 'px — Pixels (digital / screen only)', 'storelly-product-builder-for-woocommerce' ); ?></option>
                        </select>
                    </div>
                </div>
            </section>

            <?php /*
                Template registrations needed for ng-include above. nbpb_com
                stays available for the "Sub-attributes & classic matrix"
                escape hatch. nbpb_text/nbpb_image classic templates are no
                longer referenced (M6.5b renders custom card bodies) but we
                keep them registered for backward-compat with any extension
                or partial that still ng-includes them.
            */ ?>
            <?php include SPBWC_PB_PLUGIN_DIR . 'views/options/templates/nbpb_com.php'; ?>
            <?php include SPBWC_PB_PLUGIN_DIR . 'views/options/templates/nbpb_text.php'; ?>
            <?php include SPBWC_PB_PLUGIN_DIR . 'views/options/templates/nbpb_image.php'; ?>

            <!-- ──────────────── Sticky save bar ──────────────── -->
            <div class="spbwc-vb-edit__savebar">
                <span class="spbwc-vb-edit__savebar-hint">
                    <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                    <?php esc_html_e( 'Saving here updates the same pricing option used by the classic editor.', 'storelly-product-builder-for-woocommerce' ); ?>
                </span>
                <a class="button" href="<?php echo esc_url( $back_url ); ?>">
                    <?php esc_html_e( 'Cancel', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <button type="button" ng-click="vbSaveWithValidation()" class="button button-primary">
                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    <?php esc_html_e( 'Save Visual', 'storelly-product-builder-for-woocommerce' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>
