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
    <header class="spbwc-vb__hero">
        <div class="spbwc-vb__hero-left">
            <h1 class="spbwc-vb__title">
                <span class="dashicons dashicons-art" aria-hidden="true"></span>
                <?php esc_html_e( 'Visual Builder', 'storelly-product-builder-for-woocommerce' ); ?>
            </h1>
            <p class="spbwc-vb__subtitle">
                <?php esc_html_e( 'Build product configurators with real product images — views, components and visual attributes. Each Visual is a Pricing Option you have explicitly promoted here; targeting (products / categories) is inherited from the option itself.', 'storelly-product-builder-for-woocommerce' ); ?>
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

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> inline">
            <p><?php echo esc_html( $notice['text'] ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( empty( $options ) ) : ?>
        <div class="spbwc-vb__empty">
            <div class="spbwc-vb__empty-icon" aria-hidden="true">🖼️</div>
            <h2 class="spbwc-vb__empty-title">
                <?php esc_html_e( 'No visuals yet', 'storelly-product-builder-for-woocommerce' ); ?>
            </h2>
            <p class="spbwc-vb__empty-body">
                <?php esc_html_e( 'Promote an existing Pricing Option to start building its visual configurator. Nothing here will appear automatically — you decide which options become Visuals.', 'storelly-product-builder-for-woocommerce' ); ?>
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
                <article class="spbwc-vb__card" data-option-id="<?php echo esc_attr( (string) $oid ); ?>">
                    <a class="spbwc-vb__card-thumb" href="<?php echo esc_url( $edit_url ); ?>" aria-label="<?php echo esc_attr( sprintf(
                        /* translators: %s: option title */
                        __( 'Edit visual for %s', 'storelly-product-builder-for-woocommerce' ),
                        $title_text
                    ) ); ?>">
                        <img src="<?php echo esc_url( $m['thumb_url'] ); ?>" alt="" loading="lazy" />
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
                        <a class="button button-primary" href="<?php echo esc_url( $edit_url ); ?>">
                            <?php esc_html_e( 'Edit', 'storelly-product-builder-for-woocommerce' ); ?>
                        </a>
                        <a class="button" href="<?php echo esc_url( $classic_url ); ?>"
                           title="<?php esc_attr_e( 'Open the classic Pricing Options editor for this option', 'storelly-product-builder-for-woocommerce' ); ?>">
                            <?php esc_html_e( 'Pricing', 'storelly-product-builder-for-woocommerce' ); ?>
                        </a>
                        <form method="post" action="<?php echo esc_url( $unlink_url ); ?>"
                              class="spbwc-vb__card-unlink"
                              onsubmit="return confirm('<?php echo esc_js( sprintf(
                                /* translators: %s: option title */
                                __( 'Unlink "%s" from Visual Builder? The pricing option itself will be preserved.', 'storelly-product-builder-for-woocommerce' ),
                                $title_text
                              ) ); ?>');">
                            <?php
                            wp_nonce_field(
                                SPBWC_Visual_Builder_Admin::NONCE_UNLINK,
                                SPBWC_Visual_Builder_Admin::NONCE_UNLINK_FIELD
                            );
                            ?>
                            <input type="hidden" name="id" value="<?php echo esc_attr( (string) $oid ); ?>" />
                            <button type="submit" class="button button-link-delete spbwc-vb__card-unlink-btn"
                                    title="<?php esc_attr_e( 'Unlink from Visual Builder (option is preserved)', 'storelly-product-builder-for-woocommerce' ); ?>">
                                <?php esc_html_e( 'Unlink', 'storelly-product-builder-for-woocommerce' ); ?>
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
