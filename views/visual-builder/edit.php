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
<div class="wrap spbwc-edit-v2 spbwc-vb-edit" ng-app="optionApp" ng-cloak>
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

        <!-- ──────────────── Hero ──────────────── -->
        <header class="spbwc-vb__hero spbwc-vb-edit__hero">
            <div class="spbwc-vb__hero-left">
                <h1 class="spbwc-vb__title">
                    <span class="dashicons dashicons-art" aria-hidden="true"></span>
                    {{ options.title || '<?php echo esc_js( __( 'Untitled visual', 'storelly-product-builder-for-woocommerce' ) ); ?>' }}
                </h1>
                <p class="spbwc-vb__subtitle">
                    <?php esc_html_e( 'Configure the visual surface for this option: add views (Front / Back / …), add designer components and decorate them with attribute images.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
            </div>
            <div class="spbwc-vb__hero-right spbwc-vb-edit__hero-actions">
                <a class="button" href="<?php echo esc_url( $classic_url ); ?>"
                   title="<?php esc_attr_e( 'Edit pricing fields, title, applied products and quantity tiers in the classic editor', 'storelly-product-builder-for-woocommerce' ); ?>">
                    <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                    <?php esc_html_e( 'Edit pricing', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <?php /* getJsonFields() flattens fields[], writes options[jsonFields],
                       then submits the nboForm. type="button" so a stray Enter doesn't
                       submit the raw POST (which would lose pricing nested data). */ ?>
                <button type="button" ng-click="getJsonFields()" class="button button-primary">
                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    <?php esc_html_e( 'Save Visual', 'storelly-product-builder-for-woocommerce' ); ?>
                </button>
            </div>
        </header>

        <?php if ( ! empty( $notice ) ) : ?>
            <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> inline">
                <p><?php echo esc_html( $notice['text'] ); ?></p>
            </div>
        <?php endif; ?>

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
                <div class="v2-palette__grid">
                    <button type="button" class="v2-chip" ng-click="add_field('nbpb_com', 'nbpb_com')">
                        <span class="v2-chip__icon v2-chip__icon--com">⚛</span>
                        <span class="v2-chip__label">
                            <strong><?php esc_html_e( 'Designer Component', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                            <small><?php esc_html_e( 'Background / shape / part', 'storelly-product-builder-for-woocommerce' ); ?></small>
                        </span>
                    </button>
                    <button type="button" class="v2-chip" ng-click="add_field('nbpb_text', 'nbpb_text')">
                        <span class="v2-chip__icon v2-chip__icon--dt">Tx</span>
                        <span class="v2-chip__label">
                            <strong><?php esc_html_e( 'Designer Text', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                            <small><?php esc_html_e( 'Editable text block', 'storelly-product-builder-for-woocommerce' ); ?></small>
                        </span>
                    </button>
                    <button type="button" class="v2-chip" ng-click="add_field('nbpb_image', 'nbpb_image')">
                        <span class="v2-chip__icon v2-chip__icon--di">Im</span>
                        <span class="v2-chip__label">
                            <strong><?php esc_html_e( 'Designer Image', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                            <small><?php esc_html_e( 'Image placeholder', 'storelly-product-builder-for-woocommerce' ); ?></small>
                        </span>
                    </button>
                </div>
            </section>

            <!-- ════════════════════════════════════════════════════════
                 SECTION 3 — Component cards
                 -------------------------------------------------------
                 We render the FULL field list (so non-nbpb pricing fields
                 round-trip via their existing hidden + visible inputs in
                 the DOM) but CSS hides any .pcpb-field-wrap that does
                 NOT carry the `is-nbpb` class added by views/options/
                 field.php. Pricing fields stay editable in the classic
                 editor; here they exist only to round-trip on save.
                 ════════════════════════════════════════════════════════ -->
            <section class="spbwc-vb-edit__section" ng-show="options.fields && options.fields.length > 0">
                <div class="v2-section-head">
                    <span class="v2-section-head__icon">🧩</span>
                    <?php esc_html_e( 'Components', 'storelly-product-builder-for-woocommerce' ); ?>
                    <span class="spbwc-vb-edit__section-hint" style="margin-left:auto; font-weight:var(--font-normal); color:var(--nbd-st-text-mute);">
                        <?php esc_html_e( 'Pricing fields stay in the classic editor.', 'storelly-product-builder-for-woocommerce' ); ?>
                    </span>
                </div>

                <!-- Empty state when option has no nbpb fields yet.
                     Uses AngularJS `filter` to count nbpb-only fields without
                     requiring a custom scope method. All three nbpb types
                     (nbpb_com / nbpb_text / nbpb_image) contain the literal
                     substring 'nbpb', so this substring match is safe and
                     ignores pricing fields whose nbpb_type is '' or undefined. -->
                <div class="v2-designer-empty"
                     ng-show="(options.fields | filter:{nbpb_type:'nbpb'}).length === 0">
                    <span class="v2-designer-empty__icon" aria-hidden="true">🧩</span>
                    <h3 class="v2-designer-empty__title"><?php esc_html_e( 'No designer components yet', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                    <p class="v2-designer-empty__body">
                        <?php esc_html_e( 'Use the chips above to add your first Designer Component, Text, or Image. The card will appear here with attribute settings.', 'storelly-product-builder-for-woocommerce' ); ?>
                    </p>
                </div>

                <div class="pcpb-fields-builder">
                    <?php include SPBWC_PB_PLUGIN_DIR . 'views/options/field.php'; ?>
                </div>
            </section>

            <!-- ──────────────── Sticky save bar ──────────────── -->
            <div class="spbwc-vb-edit__savebar">
                <span class="spbwc-vb-edit__savebar-hint">
                    <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                    <?php esc_html_e( 'Saving here updates the same pricing option used by the classic editor.', 'storelly-product-builder-for-woocommerce' ); ?>
                </span>
                <a class="button" href="<?php echo esc_url( $back_url ); ?>">
                    <?php esc_html_e( 'Cancel', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <button type="button" ng-click="getJsonFields()" class="button button-primary">
                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    <?php esc_html_e( 'Save Visual', 'storelly-product-builder-for-woocommerce' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>
