<?php
/**
 * Visual Builder — Create picker.
 *
 * Lets the admin pick an existing Pricing Option to PROMOTE into the Visual
 * Builder. Promotion is an explicit action: the option id is appended to the
 * `spbwc_vb_promoted` WP option (single row, no schema change). After promote,
 * we redirect to the edit screen for that option.
 *
 * Rendered by SPBWC_Visual_Builder_Admin::render_create_picker(). Receives:
 *   - $candidates : array of option rows (id, title, modified, …) not yet promoted.
 *   - $notice     : flash-notice payload from ?vb_notice= (or null).
 *
 * @package Storelly_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap spbwc-vb spbwc-vb--create">
    <nav class="spbwc-vb__backbar" aria-label="<?php esc_attr_e( 'Breadcrumb', 'storelly-product-builder-for-woocommerce' ); ?>">
        <a href="<?php echo esc_url( SPBWC_Visual_Builder_Admin::url() ); ?>">
            <span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
            <?php esc_html_e( 'Visual Builder', 'storelly-product-builder-for-woocommerce' ); ?>
        </a>
        <span class="spbwc-vb__backbar-sep">/</span>
        <span class="spbwc-vb__backbar-current">
            <?php esc_html_e( 'Create Visual', 'storelly-product-builder-for-woocommerce' ); ?>
        </span>
    </nav>

    <header class="spbwc-page-hero">
        <div class="spbwc-page-hero__grid">
            <div class="spbwc-page-hero__body">
                <div class="spbwc-page-hero__eyebrow">
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php esc_html_e( 'Visual Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
                <h1 class="spbwc-page-hero__title">
                    <span class="dashicons dashicons-art" aria-hidden="true"></span>
                    <?php esc_html_e( 'Create new Visual', 'storelly-product-builder-for-woocommerce' ); ?>
                </h1>
                <p class="spbwc-page-hero__subtitle">
                    <?php esc_html_e( 'Pick a Pricing Option to attach a Visual to. Only options not already in Visual Builder are listed. The Visual you build will inherit the option\'s product / category targeting.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
            </div>
        </div>
    </header>

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> inline">
            <p><?php echo esc_html( $notice['text'] ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( empty( $candidates ) ) : ?>
        <div class="spbwc-vb__empty">
            <div class="spbwc-vb__empty-icon" aria-hidden="true">✅</div>
            <h2 class="spbwc-vb__empty-title">
                <?php esc_html_e( 'No pricing options available to promote', 'storelly-product-builder-for-woocommerce' ); ?>
            </h2>
            <p class="spbwc-vb__empty-body">
                <?php esc_html_e( 'Either every published Pricing Option is already in Visual Builder, or none exists yet. Create a new Pricing Option first, then come back here.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
            <p class="spbwc-vb__empty-actions">
                <a class="spbwc-cta-btn spbwc-cta-btn--solid" href="<?php echo esc_url( add_query_arg( array( 'page' => SPBWC_PB_BUILDER_SLUG, 'action' => 'create', 'id' => 0 ), admin_url( 'admin.php' ) ) ); ?>">
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php esc_html_e( 'Create new Pricing Option', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="<?php echo esc_url( SPBWC_Visual_Builder_Admin::url() ); ?>">
                    <?php esc_html_e( '← Back', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
            </p>
        </div>
    <?php else : ?>
        <form class="spbwc-vb__picker" method="post" action="<?php echo esc_url( SPBWC_Visual_Builder_Admin::url( 'create' ) ); ?>">
            <?php
            // Nonce + capability gate is enforced server-side in
            // SPBWC_Visual_Builder_Admin::handle_create_submit().
            wp_nonce_field(
                SPBWC_Visual_Builder_Admin::NONCE_CREATE,
                SPBWC_Visual_Builder_Admin::NONCE_CREATE_FIELD
            );
            ?>

            <div class="spbwc-vb__picker-field">
                <label for="spbwc-vb-option-picker" class="spbwc-vb__picker-label">
                    <?php esc_html_e( 'Pricing Option', 'storelly-product-builder-for-woocommerce' ); ?>
                    <span class="spbwc-vb__required" aria-hidden="true">*</span>
                </label>
                <select id="spbwc-vb-option-picker" name="id" class="spbwc-vb__picker-select" required="required">
                    <option value=""><?php esc_html_e( '— Select an option —', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <?php foreach ( $candidates as $c ) :
                        $cid    = absint( $c['id'] );
                        $ctitle = '' !== trim( (string) $c['title'] ) ? $c['title'] : sprintf( '#%d', $cid );
                        ?>
                        <option value="<?php echo esc_attr( (string) $cid ); ?>">
                            <?php
                            printf(
                                /* translators: 1: option title, 2: option id */
                                esc_html__( '%1$s (#%2$d)', 'storelly-product-builder-for-woocommerce' ),
                                esc_html( $ctitle ),
                                absint( $cid )
                            );
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="spbwc-vb__picker-help">
                    <?php esc_html_e( 'After you create the Visual, you can build it (views, components, attribute images) on the next screen. You can unlink it any time — the pricing option itself stays intact.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
            </div>

            <div class="spbwc-vb__picker-actions">
                <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="<?php echo esc_url( SPBWC_Visual_Builder_Admin::url() ); ?>">
                    <?php esc_html_e( 'Cancel', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <button type="submit" class="spbwc-cta-btn spbwc-cta-btn--solid">
                    <?php esc_html_e( 'Create Visual', 'storelly-product-builder-for-woocommerce' ); ?>
                    <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>
