<?php
/**
 * Marketplace admin — Designs tab.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Designs_List_Table' ) ) {
    require_once SPBWC_PB_PLUGIN_DIR . 'includes/marketplace/admin/class-designs-list-table.php';
}

$status   = isset( $_REQUEST['filter_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$designer = isset( $_REQUEST['filter_designer'] ) ? absint( wp_unslash( $_REQUEST['filter_designer'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

$table = new SPBWC_Designs_List_Table();
$table->prepare_items();
?>
<div class="spbwc-section__header" style="margin-bottom:var(--nbd-space-4);">
    <h2 class="spbwc-section__title">
        <span class="dashicons dashicons-art" aria-hidden="true"></span>
        <?php esc_html_e( 'Designs', 'storelly-product-builder-for-woocommerce' ); ?>
    </h2>
    <p class="spbwc-section__subtitle">
        <?php esc_html_e( 'Review, approve or reject designer submissions.', 'storelly-product-builder-for-woocommerce' ); ?>
    </p>
</div>

<form method="get">
    <input type="hidden" name="page" value="<?php echo esc_attr( SPBWC_Marketplace_Admin::MENU_SLUG ); ?>" />
    <input type="hidden" name="tab" value="designs" />
    <div class="tablenav top">
        <div class="alignleft actions">
            <label class="screen-reader-text" for="filter_status"><?php esc_html_e( 'Filter by status', 'storelly-product-builder-for-woocommerce' ); ?></label>
            <select name="filter_status" id="filter_status">
                <option value=""><?php esc_html_e( 'All statuses', 'storelly-product-builder-for-woocommerce' ); ?></option>
                <option value="published" <?php selected( $status, 'published' ); ?>><?php esc_html_e( 'Published', 'storelly-product-builder-for-woocommerce' ); ?></option>
                <option value="draft"     <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'storelly-product-builder-for-woocommerce' ); ?></option>
            </select>
            <label class="screen-reader-text" for="filter_designer"><?php esc_html_e( 'Filter by designer', 'storelly-product-builder-for-woocommerce' ); ?></label>
            <input type="number" name="filter_designer" id="filter_designer" placeholder="<?php esc_attr_e( 'Designer user ID', 'storelly-product-builder-for-woocommerce' ); ?>" value="<?php echo esc_attr( $designer ? $designer : '' ); ?>" min="0" />
            <?php submit_button( __( 'Filter', 'storelly-product-builder-for-woocommerce' ), '', '', false ); ?>
        </div>
    </div>
    <?php $table->display(); ?>
</form>
