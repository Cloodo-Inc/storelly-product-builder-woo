<?php
/**
 * Marketplace admin — tab header.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
<h1 class="wp-heading-inline"><?php esc_html_e( 'Designer Marketplace', 'storelly-product-builder-for-woocommerce' ); ?></h1>
<hr class="wp-header-end" />
<nav class="nav-tab-wrapper">
<?php foreach ( $tabs as $slug => $label ) :
    $url    = $admin->get_tab_url( $slug );
    $active = ( $current_tab === $slug ) ? ' nav-tab-active' : '';
    ?>
    <a href="<?php echo esc_url( $url ); ?>" class="nav-tab<?php echo esc_attr( $active ); ?>"><?php echo esc_html( $label ); ?></a>
<?php endforeach; ?>
</nav>
