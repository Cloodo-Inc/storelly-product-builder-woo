<?php
/**
 * Marketplace admin — Settings tab.
 *
 * Just a launcher into SPBWC_Marketplace_Settings_Adapter so we don't
 * duplicate the settings UI here. The adapter renders its own admin
 * page under SPBWC_PB_OVERVIEW_SLUG.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$parent_slug = defined( 'SPBWC_PB_OVERVIEW_SLUG' ) ? SPBWC_PB_OVERVIEW_SLUG : 'storelly-product-builder-for-woocommerce-overview';
$settings_url = add_query_arg( array( 'page' => $parent_slug . '-marketplace-settings' ), admin_url( 'admin.php' ) );
?>
<div class="spbwc-mp-settings-link card" style="max-width:680px;padding:20px;">
    <h2><?php esc_html_e( 'Marketplace settings', 'storelly-product-builder-for-woocommerce' ); ?></h2>
    <p><?php esc_html_e( 'Marketplace-wide options (enable/disable, commission, withdraw limits, eligible order statuses, banner sizing, color-preview generation) live on a dedicated settings page.', 'storelly-product-builder-for-woocommerce' ); ?></p>
    <p>
        <a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
            <?php esc_html_e( 'Open Marketplace Settings', 'storelly-product-builder-for-woocommerce' ); ?> &rarr;
        </a>
    </p>
    <p class="description">
        <?php
        printf(
            /* translators: %s: option key shown as inline code */
            esc_html__( 'Tip: the master toggle is the option %s — set it to %2$s to surface the marketplace UI for designers.', 'storelly-product-builder-for-woocommerce' ),
            '<code>spbwc_marketplace_enabled</code>',
            '<code>yes</code>'
        );
        ?>
    </p>
</div>
