<?php
/**
 * Marketplace admin — Withdraws tab.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Withdraws_List_Table' ) ) {
    require_once SPBWC_PB_PLUGIN_DIR . 'includes/marketplace/admin/class-withdraws-list-table.php';
}

$status = isset( $_REQUEST['filter_status'] ) ? wp_unslash( $_REQUEST['filter_status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$status = ( '' === $status ) ? '' : (string) (int) $status;

$table = new SPBWC_Withdraws_List_Table();
$table->prepare_items();
?>
<form method="get">
    <input type="hidden" name="page" value="<?php echo esc_attr( SPBWC_Marketplace_Admin::MENU_SLUG ); ?>" />
    <input type="hidden" name="tab" value="withdraws" />
    <div class="tablenav top">
        <div class="alignleft actions">
            <label class="screen-reader-text" for="filter_status"><?php esc_html_e( 'Filter by status', 'storelly-product-builder-for-woocommerce' ); ?></label>
            <select name="filter_status" id="filter_status">
                <option value=""><?php esc_html_e( 'All statuses', 'storelly-product-builder-for-woocommerce' ); ?></option>
                <option value="0" <?php selected( $status, '0' ); ?>><?php esc_html_e( 'Pending', 'storelly-product-builder-for-woocommerce' ); ?></option>
                <option value="1" <?php selected( $status, '1' ); ?>><?php esc_html_e( 'Approved', 'storelly-product-builder-for-woocommerce' ); ?></option>
                <option value="2" <?php selected( $status, '2' ); ?>><?php esc_html_e( 'Cancelled', 'storelly-product-builder-for-woocommerce' ); ?></option>
            </select>
            <?php submit_button( __( 'Filter', 'storelly-product-builder-for-woocommerce' ), '', '', false ); ?>
        </div>
    </div>
    <?php $table->display(); ?>
</form>
