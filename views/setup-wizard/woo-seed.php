<?php
/**
 * Setup Wizard › Import Woo Variations.
 *
 * Thin shell — the wizard state machine, AJAX, progress bar and log
 * stream all live in static/js/woo-seed-app.js. PHP only renders the
 * page hero + the JS mount point.
 *
 * Tokens, admin-ui and the wizard stylesheet are queued by
 * SPBWC_Woo_Seed_Controller::enqueue_assets().
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

	<section class="spbwc-page-hero">
		<div class="spbwc-page-hero__grid">
			<div class="spbwc-page-hero__body">
				<div class="spbwc-page-hero__eyebrow">
					<a href="<?php echo esc_url( $landing_url ); ?>" style="color:inherit;text-decoration:none;">
						<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
						<?php esc_html_e( 'Setup Wizard', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
				</div>
				<h1 class="spbwc-page-hero__title"><?php esc_html_e( 'Import Woo Variations', 'storelly-product-builder-for-woocommerce' ); ?></h1>
				<p class="spbwc-page-hero__subtitle">
					<?php esc_html_e( 'One-time migration of existing variable products into Storelly pricing options. Stock and SKU stay on the Woo side.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
			</div>
		</div>
	</section>

	<div id="spbwc-woo-seed-app" class="spbwc-block spbwc-ws-app">
		<div class="spbwc-ws-block-body">
			<p class="spbwc-ws-loading">
				<span class="spinner is-active" aria-hidden="true"></span>
				<?php esc_html_e( 'Scanning your products…', 'storelly-product-builder-for-woocommerce' ); ?>
			</p>
		</div>
	</div>
</div>
