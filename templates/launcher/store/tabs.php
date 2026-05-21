<?php
    if (!defined('ABSPATH')) exit;
    $dashboard_url  = wc_get_endpoint_url( 'my-store', '', wc_get_page_permalink( 'myaccount' ) );
    $designs_url    = add_query_arg( array( 'tab' => 'designs' ), $dashboard_url );
    $withdraw_url   = add_query_arg( array( 'tab' => 'withdraw' ), $dashboard_url );
    $settings_url   = add_query_arg( array( 'tab' => 'settings' ), $dashboard_url );
?>
<div class="nbdl-nav-tab-wrapper">
    <a class="nbdl-nav-tab <?php echo( $tab == 'dashboard' ? 'nbdl-nav-tab-active' : '' ); ?>" href="<?php echo( $dashboard_url ); ?>">
        <?php esc_html_e('Overview', 'storelly-product-builder-for-woocommerce'); ?>
    </a>
    <a class="nbdl-nav-tab <?php echo( $tab == 'designs' ? 'nbdl-nav-tab-active' : '' ); ?>" href="<?php echo( $designs_url ); ?>">
        <?php esc_html_e('Designs', 'storelly-product-builder-for-woocommerce'); ?>
    </a>
    <a class="nbdl-nav-tab <?php echo( $tab == 'withdraw' ? 'nbdl-nav-tab-active' : '' ); ?>" href="<?php echo( $withdraw_url ); ?>">
        <?php esc_html_e('Withdraw', 'storelly-product-builder-for-woocommerce'); ?>
    </a>
    <a class="nbdl-nav-tab <?php echo( $tab == 'settings' ? 'nbdl-nav-tab-active' : '' ); ?>" href="<?php echo( $settings_url ); ?>">
        <?php esc_html_e('Settings', 'storelly-product-builder-for-woocommerce'); ?>
    </a>
</div>