<?php
/**
 * Setup Wizard › Import Woo Variations.
 *
 * Thin shell — the wizard state machine, AJAX, progress bar and log
 * stream all live in static/js/woo-seed-app.js. PHP only renders the
 * outer container and a back-to-landing link.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
}

$landing_url = admin_url( 'admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '/global-import' );
$builder_url = admin_url( 'admin.php?page=' . SPBWC_PB_BUILDER_SLUG );
?>
<div class="wrap spbwc-woo-seed" data-builder-url="<?php echo esc_attr( $builder_url ); ?>">
	<h1 style="display:flex;align-items:center;gap:8px;">
		<a href="<?php echo esc_url( $landing_url ); ?>" class="page-title-action" style="margin-left:0;">
			<?php esc_html_e( '← Setup Wizard', 'storelly-product-builder-for-woocommerce' ); ?>
		</a>
		<?php esc_html_e( 'Import Woo Variations', 'storelly-product-builder-for-woocommerce' ); ?>
	</h1>

	<div id="spbwc-woo-seed-app" class="spbwc-block" style="padding:24px;margin-top:16px;">
		<p style="color:#666;">
			<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span>
			<?php esc_html_e( 'Scanning your products…', 'storelly-product-builder-for-woocommerce' ); ?>
		</p>
	</div>
</div>
