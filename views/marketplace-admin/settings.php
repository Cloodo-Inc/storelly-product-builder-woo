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
<div class="spbwc-block spbwc-block--brand" style="max-width:720px;">
    <header class="spbwc-block__head">
        <h3 class="spbwc-block__title">
            <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
            <?php esc_html_e( 'Marketplace Settings', 'storelly-product-builder-for-woocommerce' ); ?>
        </h3>
    </header>
    <div class="spbwc-block__body">
        <p style="color:var(--nbd-st-text-soft);font-size:var(--text-md);line-height:1.6;margin:0 0 var(--nbd-space-4);">
            <?php esc_html_e( 'Marketplace-wide options (enable/disable, commission rates, withdraw limits, eligible order statuses, banner sizing, color-preview generation) live on a dedicated settings page.', 'storelly-product-builder-for-woocommerce' ); ?>
        </p>
        <div class="spbwc-notice-banner spbwc-notice-banner--info" style="margin-bottom:var(--nbd-space-4);">
            <span class="dashicons dashicons-info" aria-hidden="true"></span>
            <div class="spbwc-notice-banner__body">
                <span class="spbwc-notice-banner__text">
                    <?php
                    printf(
                        /* translators: %s: option key shown as inline code */
                        esc_html__( 'Tip: the master toggle is %1$s — set it to %2$s to surface the marketplace UI for designers.', 'storelly-product-builder-for-woocommerce' ),
                        '<code>spbwc_marketplace_enabled</code>',
                        '<code>yes</code>'
                    );
                    ?>
                </span>
            </div>
        </div>
        <a href="<?php echo esc_url( $settings_url ); ?>" class="spbwc-cta-btn spbwc-cta-btn--solid">
            <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
            <?php esc_html_e( 'Open Marketplace Settings', 'storelly-product-builder-for-woocommerce' ); ?>
            <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
        </a>
    </div>
</div>
