<?php
/**
 * Pricing Option Editor v3 — modern shell.
 *
 * v3.0 minimum-viable: brand-gradient hero, Basics card (title + display
 * mode), single-page sections, sticky save bar. Field list and the heavier
 * sections (quantity, apply, output) currently fall through to the classic
 * partials so the editor is usable end-to-end on day 1; section-by-section
 * redesign lands in v3.1-v3.4.
 *
 * Lives at ?page=…-builder&action=v3&id=N. Classic editor is unchanged at
 * action=edit. List page row action "Edit" defaults to v3; "Classic editor"
 * is available as a row sub-action.
 *
 * Save flow: identical to classic — same form name (`nboForm`), nonce
 * (`spbwc_save_option_action`), and getJsonFields() serialisation, so the
 * underlying admin save handler doesn't need any v3-specific branch.
 *
 * @package Storelly_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Mirror the link helpers classic uses so navigation back to list / unpub /
// create-new works exactly the same.
$link             = add_query_arg( array(
    'paged' => isset( $_GET['paged'] ) ? sanitize_text_field( wp_unslash( $_GET['paged'] ) ) : 1, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only paging arg for navigation link.
), admin_url( 'admin.php?page=' . SPBWC_PB_BUILDER_SLUG ) );
$link_create_new = add_query_arg( array( 'action' => 'create_v3', 'paged' => 1, 'id' => 0 ), admin_url( 'admin.php?page=' . SPBWC_PB_BUILDER_SLUG ) );
$link_classic    = add_query_arg(
    array(
        'action' => $options['id'] > 0 ? 'edit' : 'create',
        'id'     => $options['id'] > 0 ? absint( $options['id'] ) : 0,
    ),
    admin_url( 'admin.php?page=' . SPBWC_PB_BUILDER_SLUG )
);
$is_new_option   = ( $options['id'] == 0 );
$current_id      = absint( $options['id'] );

// Visual Builder counterpart link — surface so admins know visuals live there.
$vb_url = '';
if ( defined( 'SPBWC_PB_VISUAL_BUILDER_SLUG' ) && class_exists( 'SPBWC_Visual_Builder_Admin' ) && $current_id > 0 ) {
    $vb_url = SPBWC_Visual_Builder_Admin::url( 'edit', array( 'id' => $current_id ) );
}
?>
<div class="wrap spbwc-edit-v2 spbwc-vb spbwc-po-v3" ng-app="optionApp" ng-cloak>
    <div ng-controller="optionCtrl">

        <nav class="spbwc-vb__backbar" aria-label="<?php esc_attr_e( 'Breadcrumb', 'storelly-product-builder-for-woocommerce' ); ?>">
            <a href="<?php echo esc_url( $link ); ?>">
                <span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
                <?php esc_html_e( 'Pricing Options', 'storelly-product-builder-for-woocommerce' ); ?>
            </a>
            <span class="spbwc-vb__backbar-sep">/</span>
            <span class="spbwc-vb__backbar-current">
                <?php if ( $is_new_option ) :
                    esc_html_e( 'Create New Option', 'storelly-product-builder-for-woocommerce' );
                else :
                    /* translators: %s: option title */
                    printf( esc_html__( 'Edit · %s', 'storelly-product-builder-for-woocommerce' ), esc_html( $options['title'] ? $options['title'] : '#' . $current_id ) );
                endif; ?>
            </span>
        </nav>

        <!-- Hero — same brand-gradient block VB uses, so the two editors
             feel like one product. -->
        <header class="spbwc-page-hero spbwc-po-v3__hero">
            <div class="spbwc-page-hero__grid">
                <div class="spbwc-page-hero__body">
                    <div class="spbwc-page-hero__eyebrow">
                        <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                        <?php esc_html_e( 'Pricing Options', 'storelly-product-builder-for-woocommerce' ); ?>
                    </div>
                    <h1 class="spbwc-page-hero__title">
                        <span class="dashicons dashicons-<?php echo $is_new_option ? 'plus-alt2' : 'edit'; ?>" aria-hidden="true"></span>
                        <?php if ( $is_new_option ) :
                            esc_html_e( 'Create New Option', 'storelly-product-builder-for-woocommerce' );
                        else :
                            esc_html_e( 'Edit Pricing Option', 'storelly-product-builder-for-woocommerce' );
                        endif; ?>
                    </h1>
                    <p class="spbwc-page-hero__subtitle">
                        <?php esc_html_e( 'Build the printing options buyers configure (paper, size, finishing, quantity…). Pricing, conditional rules, applied products, and bulk discounts live here.', 'storelly-product-builder-for-woocommerce' ); ?>
                        <a class="spbwc-vb-help-link"
                           href="<?php echo esc_url( $vb_url ? $vb_url : '#' ); ?>"
                           <?php echo $vb_url ? '' : 'aria-disabled="true"'; ?>>
                            <span class="dashicons dashicons-art" aria-hidden="true"></span>
                            <?php esc_html_e( 'Visual Builder for views & images →', 'storelly-product-builder-for-woocommerce' ); ?>
                        </a>
                    </p>
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
                    <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="<?php echo esc_url( $link_create_new ); ?>">
                        <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                        <?php esc_html_e( 'Add new', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                    <a class="spbwc-cta-btn spbwc-cta-btn--ghost"
                       href="<?php echo esc_url( $link_classic ); ?>"
                       title="<?php esc_attr_e( 'Open the classic editor for this option (legacy fallback)', 'storelly-product-builder-for-woocommerce' ); ?>">
                        <span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
                        <?php esc_html_e( 'Classic', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                    <button type="button" ng-click="vbSaveWithValidation && vbSaveWithValidation() || getJsonFields()" class="spbwc-cta-btn spbwc-cta-btn--solid">
                        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                        <?php
                        if ( $is_new_option ) {
                            esc_html_e( 'Publish', 'storelly-product-builder-for-woocommerce' );
                        } else {
                            esc_html_e( 'Save', 'storelly-product-builder-for-woocommerce' );
                        }
                        ?>
                    </button>
                </div>
            </div>
        </header>

        <?php if ( isset( $message['flag'] ) ) :
            $rendered_message = SPBWC_Storelly_PB_Util::spbwc_custom_notices( $message['flag'], $message['content'] );
            echo wp_kses_post( $rendered_message );
        endif; ?>

        <!-- Visual Builder bridge — mirror of VB's pricing-context bridge.
             Only shown for existing options (need an id to link to VB). -->
        <?php if ( ! $is_new_option && $vb_url ) : ?>
            <section class="spbwc-vb-context spbwc-po-v3__vb-bridge">
                <div class="spbwc-vb-context__head">
                    <span class="spbwc-vb-context__icon" aria-hidden="true">🎨</span>
                    <div class="spbwc-vb-context__body">
                        <h2 class="spbwc-vb-context__title">
                            <?php esc_html_e( 'Visual configuration lives in Visual Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                        </h2>
                        <p class="spbwc-vb-context__hint">
                            <?php esc_html_e( 'Canvas views (Front / Back / …), Designer Components / Text / Image, per-attribute layered images, and the print PDF output all live in Visual Builder. Edit pricing & rules here; jump there for the visual surface.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                    </div>
                    <a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm spbwc-vb-context__cta"
                       href="<?php echo esc_url( $vb_url ); ?>">
                        <span class="dashicons dashicons-art" aria-hidden="true"></span>
                        <?php esc_html_e( 'Open in Visual Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                </div>
            </section>
        <?php endif; ?>

        <!-- ──────────────── Main form ──────────────── -->
        <!-- id="spbwc-po-v3-form" — was "post" historically (matched WP core's
             default post-edit form id), renamed to avoid global selector
             collisions. Save scripts target the form by name="nboForm" or
             the [data-spbwc-edit-form] hook so the rename is safe. -->
        <form name="nboForm" action="" method="post" id="spbwc-po-v3-form" data-spbwc-edit-form>
            <?php wp_nonce_field( 'spbwc_save_option_action', '_wpnonce' ); ?>
            <input type="hidden" name="options[version]" value="<?php echo esc_attr( SPBWC_PB_VERSION ); ?>" />
            <input type="hidden" name="option_id" value="<?php echo esc_attr( (string) $current_id ); ?>" />
            <input type="hidden" name="options[jsonFields]" ng-value="jsonFields" value="{{jsonFields}}" />

            <!-- Section 1: Basics — title + display mode -->
            <section class="spbwc-po-v3__section">
                <div class="v2-card">
                    <div class="v2-card__head v2-card__head--brand">
                        <h2 class="v2-card__title">
                            <span class="v2-card__title-icon">i</span>
                            <?php esc_html_e( 'Basics', 'storelly-product-builder-for-woocommerce' ); ?>
                        </h2>
                        <?php if ( $options['published'] == 1 ) : ?>
                            <span class="v2-pill v2-pill--active"><span class="v2-pill__dot"></span><?php esc_html_e( 'Published', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        <?php else : ?>
                            <span class="v2-pill v2-pill--draft"><?php esc_html_e( 'Draft', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="v2-card__body">
                        <div class="v2-form-row">
                            <label class="v2-form-row__label" for="title">
                                <?php esc_html_e( 'Option title', 'storelly-product-builder-for-woocommerce' ); ?>
                                <span style="color:var(--nbd-color-danger)">*</span>
                            </label>
                            <input id="title" class="v2-input spbwc-vb-text-input" required="required"
                                   ng-model="options.title" type="text" name="title"
                                   value="<?php echo esc_attr( $options['title'] ); ?>" autocomplete="off" />
                        </div>

                        <div class="v2-form-row" style="margin-top: var(--nbd-space-4);">
                            <label class="v2-form-row__label">
                                <?php esc_html_e( 'Frontend display mode', 'storelly-product-builder-for-woocommerce' ); ?>
                            </label>
                            <p class="v2-form-row__help">
                                <?php esc_html_e( 'Controls how the option group renders on the product page.', 'storelly-product-builder-for-woocommerce' ); ?>
                            </p>
                            <div class="v2-radio-cards v2-radio-cards--mode">
                                <label class="v2-radio-card" ng-class="{'is-active': options.display_mode === 'sections'}">
                                    <input type="radio" ng-model="options.display_mode" value="sections" name="options[display_mode]" />
                                    <span class="v2-radio-card__visual v2-radio-card__visual--sections" aria-hidden="true">
                                        <span></span><span></span><span></span>
                                    </span>
                                    <strong><?php esc_html_e( 'Sections', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                                    <small><?php esc_html_e( 'Vertical list, ≤6 fields.', 'storelly-product-builder-for-woocommerce' ); ?></small>
                                </label>
                                <label class="v2-radio-card" ng-class="{'is-active': options.display_mode === 'matrix'}">
                                    <input type="radio" ng-model="options.display_mode" value="matrix" name="options[display_mode]" />
                                    <span class="v2-radio-card__visual v2-radio-card__visual--matrix" aria-hidden="true">
                                        <span></span><span></span><span></span><span></span>
                                        <span></span><span></span><span></span><span></span>
                                    </span>
                                    <strong><?php esc_html_e( 'Matrix', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                                    <small><?php esc_html_e( '2D grid (paper × sides).', 'storelly-product-builder-for-woocommerce' ); ?></small>
                                </label>
                                <label class="v2-radio-card" ng-class="{'is-active': options.display_mode === 'stepper'}">
                                    <input type="radio" ng-model="options.display_mode" value="stepper" name="options[display_mode]" />
                                    <span class="v2-radio-card__visual v2-radio-card__visual--stepper" aria-hidden="true">
                                        <span class="dot is-active"></span><span class="line"></span>
                                        <span class="dot"></span><span class="line"></span>
                                        <span class="dot"></span>
                                    </span>
                                    <strong><?php esc_html_e( 'Stepper', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                                    <small><?php esc_html_e( 'One-at-a-time, &gt;5 fields.', 'storelly-product-builder-for-woocommerce' ); ?></small>
                                </label>
                            </div>
                        </div>

                        <div class="v2-meta-grid">
                            <div class="v2-meta-item">
                                <span class="v2-meta-item__label"><?php esc_html_e( 'Option ID', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                <span class="v2-meta-item__value">#<?php echo esc_html( (string) $current_id ); ?></span>
                            </div>
                            <div class="v2-meta-item">
                                <span class="v2-meta-item__label"><?php esc_html_e( 'Modified', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                <span class="v2-meta-item__value"><?php echo esc_html( $options['modified'] ? $options['modified'] : '—' ); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 2: Fields list (v3.0 — falls through to classic
                 field markup wrapped in our chrome). v3.1 redesigns the
                 per-field card while reusing field-body partials inside. -->
            <section class="spbwc-po-v3__section">
                <div class="v2-section-head">
                    <span class="v2-section-head__icon">📋</span>
                    <?php esc_html_e( 'Pricing fields', 'storelly-product-builder-for-woocommerce' ); ?>
                    <span class="spbwc-vb-edit__section-hint" style="margin-left:auto; font-weight:var(--font-normal); color:var(--nbd-st-text-mute);">
                        <?php esc_html_e( 'Each field becomes a configurable option on the product page.', 'storelly-product-builder-for-woocommerce' ); ?>
                    </span>
                </div>
                <div class="spbwc-po-v3__fields-wrap">
                    <?php include 'field.php'; ?>
                </div>
            </section>

            <!-- Section 3: Quantity & bulk pricing (mounted by classic
                 markup — v3.2 redesign). Reused via inline include block
                 to keep this file readable. -->
            <section class="spbwc-po-v3__section">
                <?php
                /*
                 * Lightweight inclusion of the classic quantity-breaks card.
                 * Same data, same handlers. Restyle in v3.2.
                 */
                $spbwc_qty_section_path = SPBWC_PB_PLUGIN_DIR . 'views/options/v3/section-quantity.php';
                if ( file_exists( $spbwc_qty_section_path ) ) {
                    include $spbwc_qty_section_path;
                }
                ?>
            </section>

            <!-- Section 4 (v3.3): Apply to products / categories. -->
            <section class="spbwc-po-v3__section">
                <?php
                $spbwc_apply_section_path = SPBWC_PB_PLUGIN_DIR . 'views/options/v3/section-apply.php';
                if ( file_exists( $spbwc_apply_section_path ) ) {
                    include $spbwc_apply_section_path;
                }
                ?>
            </section>

            <!-- Section 5 (v3.4): Output PDF — link card to Visual Builder
                 (VB owns the settings; we just round-trip the data here). -->
            <section class="spbwc-po-v3__section">
                <div class="v2-card spbwc-po-v3__output-card">
                    <div class="v2-card__head v2-card__head--brand">
                        <h2 class="v2-card__title">
                            <span class="v2-card__title-icon">📄</span>
                            <?php esc_html_e( 'PDF output', 'storelly-product-builder-for-woocommerce' ); ?>
                        </h2>
                        <span class="spbwc-vb-edit__section-hint" style="margin-left:auto;">
                            <?php
                            $cur_dpi   = isset( $options['design_output']['dpi'] ) ? absint( $options['design_output']['dpi'] ) : 300;
                            $cur_unit  = isset( $options['design_output']['dimension_unit'] ) ? $options['design_output']['dimension_unit'] : 'px';
                            /* translators: 1: DPI, 2: unit */
                            printf( esc_html__( '%1$d DPI · %2$s', 'storelly-product-builder-for-woocommerce' ),
                                absint( $cur_dpi ),
                                esc_html( $cur_unit )
                            );
                            ?>
                        </span>
                    </div>
                    <div class="v2-card__body">
                        <p class="spbwc-vb-context__hint" style="margin:0 0 var(--nbd-space-3);">
                            <?php esc_html_e( 'Print-ready PDF settings (resolution + dimension unit) used for invoicing and production. Edit them in Visual Builder where the per-view artwork lives.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                        <?php if ( $vb_url ) : ?>
                            <a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" href="<?php echo esc_url( $vb_url ); ?>">
                                <span class="dashicons dashicons-art" aria-hidden="true"></span>
                                <?php esc_html_e( 'Open PDF settings in Visual Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                            </a>
                        <?php else : ?>
                            <small style="color: var(--vb-text-mute);">
                                <?php esc_html_e( 'Save this option first to enable the Visual Builder link.', 'storelly-product-builder-for-woocommerce' ); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Section 6 (v3.4): Import / Export accordion — collapsed by default. -->
            <section class="spbwc-po-v3__section spbwc-po-v3__iox-section">
                <details class="spbwc-po-v3__iox">
                    <summary class="spbwc-po-v3__iox-summary">
                        <span class="dashicons dashicons-migrate" aria-hidden="true"></span>
                        <?php esc_html_e( 'Import / Export', 'storelly-product-builder-for-woocommerce' ); ?>
                        <small>
                            <?php esc_html_e( 'Share or back up this option as JSON', 'storelly-product-builder-for-woocommerce' ); ?>
                        </small>
                    </summary>
                    <div class="spbwc-po-v3__iox-body">
                        <p class="spbwc-vb-context__hint">
                            <?php esc_html_e( 'Need to migrate an option config across sites? Use the classic editor\'s Import / Export tab — it is the only place that has the full JSON workflow.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                        <a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" href="<?php echo esc_url( $link_classic ); ?>">
                            <span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
                            <?php esc_html_e( 'Open classic editor → Import / Export', 'storelly-product-builder-for-woocommerce' ); ?>
                        </a>
                    </div>
                </details>
            </section>

            <!-- Hidden round-trip block — surfaces top-level option data that
                 v3 does not author through visible inputs but the PHP save
                 handler nevertheless writes verbatim. WITHOUT THESE, the
                 corresponding sub-trees vanish from the blob on every Save:
                   • options[views] → wipes per-view canvas data (name +
                     base attachment + base width/height) that VB needs
                     to render the designer. Losing views also cascades
                     into pb_config being dropped on the next VB save
                     (see getJsonFields() in admin-options.js).
                   • options[design_output] → PDF print resolution / unit;
                     Save handler falls back to dpi=300/unit=px when empty,
                     so this would silently reset on each save.
                 Inputs use ng-repeat so they round-trip whatever the
                 controller currently has in scope. -->
            <div class="spbwc-vb-roundtrip" hidden aria-hidden="true">
                <input type="hidden" ng-repeat-start="(vi, view) in options.views" name="options[views][{{ vi }}][name]" value="{{ view.name }}" />
                <input type="hidden" name="options[views][{{ vi }}][base]" value="{{ view.base }}" />
                <input type="hidden" name="options[views][{{ vi }}][base_width]" value="{{ view.base_width }}" />
                <input type="hidden" ng-repeat-end name="options[views][{{ vi }}][base_height]" value="{{ view.base_height }}" />
                <input type="hidden" ng-repeat="(k, v) in options.design_output" name="options[design_output][{{ k }}]" value="{{ v }}" />
            </div>

            <!-- Sticky save bar — same pattern as VB. -->
            <div class="spbwc-vb-edit__savebar spbwc-po-v3__savebar">
                <span class="spbwc-vb-edit__savebar-hint">
                    <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                    <?php esc_html_e( 'Saving updates the same option Visual Builder uses for views & images.', 'storelly-product-builder-for-woocommerce' ); ?>
                </span>
                <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="<?php echo esc_url( $link ); ?>">
                    <?php esc_html_e( 'Cancel', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <button type="button" ng-click="vbSaveWithValidation && vbSaveWithValidation() || getJsonFields()" class="spbwc-cta-btn spbwc-cta-btn--solid">
                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    <?php
                    if ( $is_new_option ) {
                        esc_html_e( 'Publish option', 'storelly-product-builder-for-woocommerce' );
                    } else {
                        esc_html_e( 'Save option', 'storelly-product-builder-for-woocommerce' );
                    }
                    ?>
                </button>
            </div>
        </form>
    </div>
</div>
