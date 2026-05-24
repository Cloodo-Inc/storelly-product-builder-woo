<?php
/**
 * Marketplace admin — Designers tab.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Designers_List_Table' ) ) {
    require_once SPBWC_PB_PLUGIN_DIR . 'includes/marketplace/admin/class-designers-list-table.php';
}

$status   = isset( $_REQUEST['filter_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$featured = isset( $_REQUEST['filter_featured'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_featured'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

$table = new SPBWC_Designers_List_Table();
$table->prepare_items();
?>
<div class="spbwc-section__header" style="margin-bottom:var(--nbd-space-4);">
    <h2 class="spbwc-section__title">
        <span class="dashicons dashicons-businessman" aria-hidden="true"></span>
        <?php esc_html_e( 'Designers', 'storelly-product-builder-for-woocommerce' ); ?>
    </h2>
    <p class="spbwc-section__subtitle">
        <?php esc_html_e( 'Manage registered designers — enable, disable or feature their profiles.', 'storelly-product-builder-for-woocommerce' ); ?>
    </p>
</div>

<form method="get">
    <input type="hidden" name="page" value="<?php echo esc_attr( SPBWC_Marketplace_Admin::MENU_SLUG ); ?>" />
    <input type="hidden" name="tab" value="designers" />
    <div class="tablenav top">
        <div class="alignleft actions">
            <label class="screen-reader-text" for="filter_status"><?php esc_html_e( 'Filter by status', 'storelly-product-builder-for-woocommerce' ); ?></label>
            <select name="filter_status" id="filter_status">
                <option value=""><?php esc_html_e( 'All statuses', 'storelly-product-builder-for-woocommerce' ); ?></option>
                <option value="enabled" <?php selected( $status, 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'storelly-product-builder-for-woocommerce' ); ?></option>
                <option value="disabled" <?php selected( $status, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'storelly-product-builder-for-woocommerce' ); ?></option>
            </select>
            <label class="screen-reader-text" for="filter_featured"><?php esc_html_e( 'Filter by featured', 'storelly-product-builder-for-woocommerce' ); ?></label>
            <select name="filter_featured" id="filter_featured">
                <option value=""><?php esc_html_e( 'All', 'storelly-product-builder-for-woocommerce' ); ?></option>
                <option value="yes" <?php selected( $featured, 'yes' ); ?>><?php esc_html_e( 'Featured', 'storelly-product-builder-for-woocommerce' ); ?></option>
                <option value="no"  <?php selected( $featured, 'no' ); ?>><?php esc_html_e( 'Not featured', 'storelly-product-builder-for-woocommerce' ); ?></option>
            </select>
            <?php submit_button( __( 'Filter', 'storelly-product-builder-for-woocommerce' ), '', '', false ); ?>
        </div>
    </div>
    <p class="search-box">
        <label class="screen-reader-text" for="designer-search-input"><?php esc_html_e( 'Search designers', 'storelly-product-builder-for-woocommerce' ); ?></label>
        <input type="search" id="designer-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" />
        <?php submit_button( __( 'Search designers', 'storelly-product-builder-for-woocommerce' ), '', '', false, array( 'id' => 'search-submit' ) ); ?>
    </p>
    <?php $table->display(); ?>
</form>
