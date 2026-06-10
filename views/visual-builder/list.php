<?php
/**
 * Visual Builder — Listing screen.
 *
 * Shows only options that have been EXPLICITLY promoted into Visual Builder
 * (id present in the `spbwc_vb_promoted` WP option). Options with stray views /
 * nbpb fields added via the classic Designer tab do NOT auto-appear.
 *
 * Rendered by SPBWC_Visual_Builder_Admin::render_list(). Receives:
 *   - $options : array of option rows with attached `vb_meta`.
 *   - $notice  : flash-notice payload from ?vb_notice= (or null).
 *
 * @package Storelly_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap spbwc-vb spbwc-vb--list">
    <header class="spbwc-page-hero">
        <div class="spbwc-page-hero__grid">
            <div class="spbwc-page-hero__body">
                <div class="spbwc-page-hero__eyebrow">
                    <span class="dashicons dashicons-art" aria-hidden="true"></span>
                    <?php esc_html_e( 'Visual Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
                <h1 class="spbwc-page-hero__title">
                    <span class="dashicons dashicons-format-gallery" aria-hidden="true"></span>
                    <?php esc_html_e( 'Visual Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                </h1>
                <p class="spbwc-page-hero__subtitle">
                    <?php esc_html_e( 'Build product configurators with real product images — views, components and visual attributes. Each Visual is a Pricing Option you have explicitly promoted here; targeting (products / categories) is inherited from the option itself.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
            </div>
            <div class="spbwc-page-hero__actions">
                <a class="spbwc-cta-btn spbwc-cta-btn--solid"
                   href="<?php echo esc_url( SPBWC_Visual_Builder_Admin::url( 'create' ) ); ?>">
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php esc_html_e( 'Create Visual', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
            </div>
        </div>
    </header>

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> inline">
            <p><?php echo esc_html( $notice['text'] ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( empty( $options ) ) : ?>
        <div class="spbwc-vb__empty spbwc-vb-tutorial">
            <div class="spbwc-vb-tutorial__hero">
                <div class="spbwc-vb__empty-icon" aria-hidden="true">🖼️</div>
                <h2 class="spbwc-vb__empty-title">
                    <?php esc_html_e( 'Build your first Visual in 3 steps', 'storelly-product-builder-for-woocommerce' ); ?>
                </h2>
                <p class="spbwc-vb__empty-body">
                    <?php esc_html_e( 'Visual Builder lets buyers see real composed products — pick a colour swatch and the photo updates live. Here is the flow:', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
            </div>

            <?php /* Tutorial: 3 illustrated steps. Each step has number,
                   icon, title and concise description. Walk through the
                   buyer-first mental model so the merchant understands
                   what they are building toward. */ ?>
            <ol class="spbwc-vb-tutorial__steps">
                <li class="spbwc-vb-tutorial__step">
                    <span class="spbwc-vb-tutorial__step-num">1</span>
                    <div class="spbwc-vb-tutorial__step-body">
                        <span class="spbwc-vb-tutorial__step-icon" aria-hidden="true">🎯</span>
                        <h3><?php esc_html_e( 'Pick a Pricing Option', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                        <p>
                            <?php esc_html_e( 'Each Visual is anchored to an existing Pricing Option (a group of buyer choices). Click Create Visual and pick the option you want to add visuals to.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                    </div>
                </li>
                <li class="spbwc-vb-tutorial__step">
                    <span class="spbwc-vb-tutorial__step-num">2</span>
                    <div class="spbwc-vb-tutorial__step-body">
                        <span class="spbwc-vb-tutorial__step-icon" aria-hidden="true">🖼️</span>
                        <h3><?php esc_html_e( 'Upload views', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                        <p>
                            <?php esc_html_e( 'A view is one side of the product (Front, Back, Inside). Upload a base image for each view — the canvas behind every attribute layer.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                    </div>
                </li>
                <li class="spbwc-vb-tutorial__step">
                    <span class="spbwc-vb-tutorial__step-num">3</span>
                    <div class="spbwc-vb-tutorial__step-body">
                        <span class="spbwc-vb-tutorial__step-icon" aria-hidden="true">🧩</span>
                        <h3><?php esc_html_e( 'Add components & attributes', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                        <p>
                            <?php esc_html_e( 'A component is a swappable part (e.g. Frame, Handles). Each component has attribute options (Leather, Cotton…). Upload one image per attribute, per view. Tip: drag many files at once to seed attributes in bulk.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                    </div>
                </li>
            </ol>

            <p class="spbwc-vb__empty-actions">
                <a class="spbwc-cta-btn spbwc-cta-btn--solid"
                   href="<?php echo esc_url( SPBWC_Visual_Builder_Admin::url( 'create' ) ); ?>">
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php esc_html_e( 'Create your first Visual', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <a class="spbwc-cta-btn spbwc-cta-btn--ghost"
                   href="<?php echo esc_url( add_query_arg( array( 'page' => SPBWC_PB_BUILDER_SLUG ), admin_url( 'admin.php' ) ) ); ?>">
                    <?php esc_html_e( 'Go to Pricing Options', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
            </p>
        </div>
    <?php else : ?>
        <div class="spbwc-vb__grid">
            <?php foreach ( $options as $opt ) :
                $oid         = absint( $opt['id'] );
                $m           = $opt['vb_meta'];
                $edit_url    = SPBWC_Visual_Builder_Admin::url( 'edit', array( 'id' => $oid ) );
                $classic_url = add_query_arg(
                    array(
                        'page'   => SPBWC_PB_BUILDER_SLUG,
                        'action' => 'edit',
                        'id'     => $oid,
                    ),
                    admin_url( 'admin.php' )
                );
                $unlink_url  = SPBWC_Visual_Builder_Admin::url();
                $target_icon = ( 'c' === $m['target_type'] ) ? 'category' : 'cart';
                if ( $m['target_empty'] ) {
                    $target_icon = 'warning';
                }
                $title_text = '' !== trim( (string) $opt['title'] ) ? $opt['title'] : sprintf( '#%d', $oid );
                ?>
                <article class="spbwc-vb__card<?php echo ! empty( $m['has_issues'] ) ? ' has-issues' : ''; ?>" data-option-id="<?php echo esc_attr( (string) $oid ); ?>">
                    <a class="spbwc-vb__card-thumb" href="<?php echo esc_url( $edit_url ); ?>" aria-label="<?php echo esc_attr( sprintf(
                        /* translators: %s: option title */
                        __( 'Edit visual for %s', 'storelly-product-builder-for-woocommerce' ),
                        $title_text
                    ) ); ?>">
                        <img src="<?php echo esc_url( $m['thumb_url'] ); ?>" alt="" loading="lazy" />
                        <?php if ( ! empty( $m['has_issues'] ) ) : ?>
                            <span class="spbwc-vb__card-issue-dot"
                                  title="<?php echo esc_attr( $m['issue_summary'] ); ?>"
                                  aria-label="<?php echo esc_attr( $m['issue_summary'] ); ?>">
                                <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                            </span>
                        <?php endif; ?>
                    </a>
                    <div class="spbwc-vb__card-body">
                        <h3 class="spbwc-vb__card-title">
                            <a href="<?php echo esc_url( $edit_url ); ?>">
                                <?php echo esc_html( $title_text ); ?>
                            </a>
                        </h3>
                        <p class="spbwc-vb__card-target<?php echo $m['target_empty'] ? ' is-empty' : ''; ?>">
                            <span class="dashicons dashicons-<?php echo esc_attr( $target_icon ); ?>" aria-hidden="true"></span>
                            <span class="spbwc-vb__card-target-text"><?php echo esc_html( $m['target_label'] ); ?></span>
                        </p>
                        <p class="spbwc-vb__card-stats">
                            <span class="spbwc-vb__chip">
                                <?php
                                printf(
                                    /* translators: %d: number of components */
                                    esc_html( _n( '%d component', '%d components', (int) $m['component_count'], 'storelly-product-builder-for-woocommerce' ) ),
                                    absint( $m['component_count'] )
                                );
                                ?>
                            </span>
                            <span class="spbwc-vb__chip">
                                <?php
                                printf(
                                    /* translators: %d: number of views */
                                    esc_html( _n( '%d view', '%d views', (int) $m['view_count'], 'storelly-product-builder-for-woocommerce' ) ),
                                    absint( $m['view_count'] )
                                );
                                ?>
                            </span>
                            <?php if ( isset( $opt['published'] ) && 1 !== (int) $opt['published'] ) : ?>
                                <span class="spbwc-vb__chip spbwc-vb__chip--draft">
                                    <?php esc_html_e( 'Draft', 'storelly-product-builder-for-woocommerce' ); ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="spbwc-vb__card-actions">
                        <a class="spbwc-cta-btn spbwc-cta-btn--solid spbwc-cta-btn--sm" href="<?php echo esc_url( $edit_url ); ?>">
                            <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                            <?php esc_html_e( 'Edit', 'storelly-product-builder-for-woocommerce' ); ?>
                        </a>
                        <a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" href="<?php echo esc_url( $classic_url ); ?>"
                           title="<?php esc_attr_e( 'Open the classic Pricing Options editor for this option', 'storelly-product-builder-for-woocommerce' ); ?>">
                            <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                            <?php esc_html_e( 'Pricing', 'storelly-product-builder-for-woocommerce' ); ?>
                        </a>
                        <form method="post" action="<?php echo esc_url( $unlink_url ); ?>"
                              class="spbwc-vb__card-unlink"
                              onsubmit="return confirm('<?php echo esc_js( sprintf(
                                /* translators: %s: option title */
                                __( 'Unlink "%s" from Visual Builder? The pricing option itself will be preserved.', 'storelly-product-builder-for-woocommerce' ),
                                $title_text
                              ) ); ?>');"
                              data-spbwc-confirm="<?php echo esc_attr( sprintf(
                                /* translators: %s: option title */
                                __( 'Unlink "%s" from Visual Builder? The pricing option itself will be preserved.', 'storelly-product-builder-for-woocommerce' ),
                                $title_text
                              ) ); ?>"
                              data-spbwc-confirm-title="<?php echo esc_attr( __( 'Unlink option', 'storelly-product-builder-for-woocommerce' ) ); ?>"
                              data-spbwc-confirm-ok="<?php echo esc_attr( __( 'Unlink', 'storelly-product-builder-for-woocommerce' ) ); ?>">
                            <?php
                            wp_nonce_field(
                                SPBWC_Visual_Builder_Admin::NONCE_UNLINK,
                                SPBWC_Visual_Builder_Admin::NONCE_UNLINK_FIELD
                            );
                            ?>
                            <input type="hidden" name="id" value="<?php echo esc_attr( (string) $oid ); ?>" />
                            <button type="submit" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm spbwc-vb__card-unlink-btn"
                                    title="<?php esc_attr_e( 'Unlink from Visual Builder (option is preserved)', 'storelly-product-builder-for-woocommerce' ); ?>">
                                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                                <?php esc_html_e( 'Unlink', 'storelly-product-builder-for-woocommerce' ); ?>
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
