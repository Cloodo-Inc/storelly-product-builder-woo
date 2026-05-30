<?php
/**
 * Visual Builder — Create picker.
 *
 * Lets the admin pick an existing Pricing Option that does NOT yet have visual
 * content. Submit redirects (via plain GET) to the Visual Builder edit screen
 * for that option id — no DB write at this stage. The "binding" is implicit:
 * adding the first view / nbpb_* field in the editor (M6.2) makes the option
 * show up in the listing on next load.
 *
 * Rendered by SPBWC_Visual_Builder_Admin::render_create_picker(). Receives:
 *   - $candidates : array of option rows (id, title, modified, …) without visual.
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

    <header class="spbwc-vb__hero">
        <div class="spbwc-vb__hero-left">
            <h1 class="spbwc-vb__title">
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                <?php esc_html_e( 'Create new Visual', 'storelly-product-builder-for-woocommerce' ); ?>
            </h1>
            <p class="spbwc-vb__subtitle">
                <?php esc_html_e( 'Pick an existing Pricing Option to attach a Visual to. Only options that do not have visual content yet are listed. The Visual will inherit the option\'s product / category targeting.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
        </div>
    </header>

    <?php if ( empty( $candidates ) ) : ?>
        <div class="spbwc-vb__empty">
            <div class="spbwc-vb__empty-icon" aria-hidden="true">✅</div>
            <h2 class="spbwc-vb__empty-title">
                <?php esc_html_e( 'All your Pricing Options already have visuals', 'storelly-product-builder-for-woocommerce' ); ?>
            </h2>
            <p class="spbwc-vb__empty-body">
                <?php esc_html_e( 'Create a new Pricing Option first, then come back here.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
            <p class="spbwc-vb__empty-actions">
                <a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => SPBWC_PB_BUILDER_SLUG, 'action' => 'create', 'id' => 0 ), admin_url( 'admin.php' ) ) ); ?>">
                    <?php esc_html_e( 'Create new Pricing Option', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <a class="button" href="<?php echo esc_url( SPBWC_Visual_Builder_Admin::url() ); ?>">
                    <?php esc_html_e( '← Back', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
            </p>
        </div>
    <?php else : ?>
        <form class="spbwc-vb__picker" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
            <?php /*
                Plain GET form — no nonce required: the destination (?action=edit)
                only renders the editor; the actual save lives in the classic
                editor (which has its own nonce). No DB write occurs from this
                form submission.
            */ ?>
            <input type="hidden" name="page" value="<?php echo esc_attr( SPBWC_PB_VISUAL_BUILDER_SLUG ); ?>" />
            <input type="hidden" name="action" value="edit" />

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
                                $cid
                            );
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="spbwc-vb__picker-help">
                    <?php esc_html_e( 'The Visual you build for this option will appear to buyers on every product (or category) the option is applied to. You can change that targeting later in the Pricing Options editor — Visual Builder will follow.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
            </div>

            <div class="spbwc-vb__picker-actions">
                <a class="button" href="<?php echo esc_url( SPBWC_Visual_Builder_Admin::url() ); ?>">
                    <?php esc_html_e( 'Cancel', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e( 'Open in Visual Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                    <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>
