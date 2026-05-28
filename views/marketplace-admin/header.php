<?php
/**
 * Marketplace admin — tab header.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view template; variables here are local to this included view, not plugin globals.

$current_tab = isset( $tab ) ? $tab : 'designers';

$tabs = array(
    'designers' => __( 'Designers', 'storelly-product-builder-for-woocommerce' ),
    'designs'   => __( 'Designs', 'storelly-product-builder-for-woocommerce' ),
    'withdraws' => __( 'Withdraws', 'storelly-product-builder-for-woocommerce' ),
    'report'    => __( 'Report', 'storelly-product-builder-for-woocommerce' ),
    'settings'  => __( 'Settings', 'storelly-product-builder-for-woocommerce' ),
);

$admin = SPBWC_Marketplace_Admin::get_instance();
?>
<header class="spbwc-page-hero" style="margin-bottom: 0; border-radius: 12px 12px 0 0;">
    <div class="spbwc-page-hero__grid">
        <div class="spbwc-page-hero__body">
            <div class="spbwc-page-hero__eyebrow">
                <span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
                <?php esc_html_e( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ); ?>
            </div>
            <h1 class="spbwc-page-hero__title">
                <span class="dashicons dashicons-groups" aria-hidden="true"></span>
                <?php esc_html_e( 'Designer Marketplace', 'storelly-product-builder-for-woocommerce' ); ?>
            </h1>
            <p class="spbwc-page-hero__subtitle">
                <?php esc_html_e( 'Manage designers, review designs, handle withdrawals and configure marketplace settings.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
        </div>
        <div class="spbwc-page-hero__actions">
            <a href="https://storelly.com/docs/marketplace" target="_blank" rel="noopener noreferrer"
               class="spbwc-cta-btn spbwc-cta-btn--ghost">
                <span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
                <?php esc_html_e( 'Documentation', 'storelly-product-builder-for-woocommerce' ); ?>
            </a>
        </div>
    </div>
</header>
<nav class="nav-tab-wrapper" style="margin-bottom: 24px; background: #fff; border: 1px solid var(--nbd-st-border-light); border-top: none; border-radius: 0 0 8px 8px; padding: 0 16px; box-shadow: var(--shadow-card);">
<?php foreach ( $tabs as $slug => $label ) :
    $url    = $admin->get_tab_url( $slug );
    $active = ( $current_tab === $slug ) ? ' nav-tab-active' : '';
    ?>
    <a href="<?php echo esc_url( $url ); ?>" class="nav-tab<?php echo esc_attr( $active ); ?>"><?php echo esc_html( $label ); ?></a>
<?php endforeach; ?>
</nav>
