<?php
/**
 * Visual Builder — Listing screen.
 *
 * Rendered by SPBWC_Visual_Builder_Admin::render_list(). Receives:
 *   - $options : array of option rows with attached `vb_meta` from
 *                derive_meta() (component_count, view_count, thumb_url,
 *                target_label, target_type).
 *
 * Read-only. Submits nothing. Links into:
 *   - Visual Builder create picker (?action=create)
 *   - Visual Builder edit stub (?action=edit&id=…) — full editor in M6.2
 *   - Classic Pricing Options editor (escape hatch)
 *
 * @package Storelly_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap spbwc-vb spbwc-vb--list">
    <header class="spbwc-vb__hero">
        <div class="spbwc-vb__hero-left">
            <h1 class="spbwc-vb__title">
                <span class="dashicons dashicons-art" aria-hidden="true"></span>
                <?php esc_html_e( 'Visual Builder', 'storelly-product-builder-for-woocommerce' ); ?>
            </h1>
            <p class="spbwc-vb__subtitle">
                <?php esc_html_e( 'Build product configurators with real product images — views, components and visual attributes. Each visual is a Pricing Option that has visual content; targeting (products / categories) is inherited from the option itself.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
        </div>
        <div class="spbwc-vb__hero-right">
            <a class="button button-primary spbwc-vb__create-btn"
               href="<?php echo esc_url( SPBWC_Visual_Builder_Admin::url( 'create' ) ); ?>">
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                <?php esc_html_e( 'Create Visual', 'storelly-product-builder-for-woocommerce' ); ?>
            </a>
        </div>
    </header>

    <?php if ( empty( $options ) ) : ?>
        <div class="spbwc-vb__empty">
            <div class="spbwc-vb__empty-icon" aria-hidden="true">🖼️</div>
            <h2 class="spbwc-vb__empty-title">
                <?php esc_html_e( 'No visuals yet', 'storelly-product-builder-for-woocommerce' ); ?>
            </h2>
            <p class="spbwc-vb__empty-body">
                <?php esc_html_e( 'A "visual" is any Pricing Option that has views or designer components added to it. Pick an existing Pricing Option to attach a visual, or create a new option first.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
            <p class="spbwc-vb__empty-actions">
                <a class="button button-primary"
                   href="<?php echo esc_url( SPBWC_Visual_Builder_Admin::url( 'create' ) ); ?>">
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php esc_html_e( 'Create your first Visual', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <a class="button"
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
                        'action' => 'update',
                        'id'     => $oid,
                    ),
                    admin_url( 'admin.php' )
                );
                $target_icon = ( 'c' === $m['target_type'] ) ? 'category' : 'cart';
                if ( $m['target_empty'] ) {
                    $target_icon = 'warning';
                }
                ?>
                <article class="spbwc-vb__card" data-option-id="<?php echo esc_attr( (string) $oid ); ?>">
                    <a class="spbwc-vb__card-thumb" href="<?php echo esc_url( $edit_url ); ?>" aria-label="<?php echo esc_attr( sprintf(
                        /* translators: %s: option title */
                        __( 'Edit visual for %s', 'storelly-product-builder-for-woocommerce' ),
                        $opt['title']
                    ) ); ?>">
                        <img src="<?php echo esc_url( $m['thumb_url'] ); ?>" alt="" loading="lazy" />
                    </a>
                    <div class="spbwc-vb__card-body">
                        <h3 class="spbwc-vb__card-title">
                            <a href="<?php echo esc_url( $edit_url ); ?>">
                                <?php echo esc_html( '' !== trim( (string) $opt['title'] ) ? $opt['title'] : sprintf( '#%d', $oid ) ); ?>
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
                        <a class="button button-primary" href="<?php echo esc_url( $edit_url ); ?>">
                            <?php esc_html_e( 'Edit', 'storelly-product-builder-for-woocommerce' ); ?>
                        </a>
                        <a class="button" href="<?php echo esc_url( $classic_url ); ?>"
                           title="<?php esc_attr_e( 'Open the classic Pricing Options editor for this option', 'storelly-product-builder-for-woocommerce' ); ?>">
                            <?php esc_html_e( 'Pricing', 'storelly-product-builder-for-woocommerce' ); ?>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
