<?php
/**
 * Setup Wizard › landing.
 *
 * Two cards: the existing "Import Sample Products" plus the new
 * "Import Woo Variations" one-time seeder.
 *
 * Uses the shared admin-ui components (.spbwc-page-hero, .spbwc-quick-grid,
 * .spbwc-quick-card, .spbwc-cta-btn) so the page matches every other
 * Storelly admin screen out of the box. Tokens + admin-ui CSS are enqueued
 * by SPBWC_Woo_Seed_Controller::enqueue_assets() — no inline styles needed.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
}

$base_url   = admin_url( 'admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '/global-import' );
$sample_url = add_query_arg( 'tab', 'sample', $base_url );
$woo_url    = add_query_arg( 'tab', 'woo', $base_url );

$last_seed = null;
if ( class_exists( 'SPBWC_Woo_Seed_Scanner' ) ) {
	$last_seed = ( new SPBWC_Woo_Seed_Scanner() )->get_last_seed();
}
?>
<div class="wrap spbwc-wizard-landing">

	<section class="spbwc-page-hero">
		<div class="spbwc-page-hero__grid">
			<div class="spbwc-page-hero__body">
				<div class="spbwc-page-hero__eyebrow">
					<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
					<?php esc_html_e( 'Storelly', 'storelly-product-builder-for-woocommerce' ); ?>
				</div>
				<h1 class="spbwc-page-hero__title"><?php esc_html_e( 'Setup Wizard', 'storelly-product-builder-for-woocommerce' ); ?></h1>
				<p class="spbwc-page-hero__subtitle">
					<?php esc_html_e( 'One-time tools to get your store ready for Storelly. Pick what you need — both can be run independently.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
			</div>
		</div>
	</section>

	<div class="spbwc-section">
		<div class="spbwc-quick-grid">

			<a class="spbwc-quick-card" href="<?php echo esc_url( $sample_url ); ?>">
				<div class="spbwc-quick-card__head">
					<div class="spbwc-quick-card__icon">
						<span class="dashicons dashicons-archive" aria-hidden="true"></span>
					</div>
					<h2 class="spbwc-quick-card__title">
						<?php esc_html_e( 'Import Sample Products', 'storelly-product-builder-for-woocommerce' ); ?>
					</h2>
				</div>
				<p class="spbwc-quick-card__desc">
					<?php esc_html_e( 'Get started with ready-made product templates from the bundled library. Useful when you are evaluating Storelly on a fresh store.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
				<div class="spbwc-quick-card__footer">
					<span class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm">
						<?php esc_html_e( 'Open', 'storelly-product-builder-for-woocommerce' ); ?>
						<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
					</span>
				</div>
			</a>

			<a class="spbwc-quick-card" href="<?php echo esc_url( $woo_url ); ?>">
				<div class="spbwc-quick-card__head">
					<div class="spbwc-quick-card__icon">
						<span class="dashicons dashicons-update" aria-hidden="true"></span>
					</div>
					<h2 class="spbwc-quick-card__title">
						<?php esc_html_e( 'Import Woo Variations (one-time)', 'storelly-product-builder-for-woocommerce' ); ?>
					</h2>
				</div>
				<p class="spbwc-quick-card__desc">
					<?php esc_html_e( 'Convert existing WooCommerce variable products into Storelly pricing options in a single pass. This is a one-time migration, not a live sync — re-running is safe but skips products already linked to a Storelly option.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
				<?php if ( $last_seed ) : ?>
					<p class="spbwc-ws-last-seed">
						<?php
						printf(
							/* translators: 1: human-readable date, 2: number of imported products */
							esc_html__( 'Last run: %1$s — %2$d products', 'storelly-product-builder-for-woocommerce' ),
							esc_html( gmdate( 'Y-m-d H:i', (int) $last_seed['timestamp'] ) ),
							(int) $last_seed['count']
						);
						?>
					</p>
				<?php endif; ?>
				<div class="spbwc-quick-card__footer">
					<span class="spbwc-cta-btn spbwc-cta-btn--solid spbwc-cta-btn--sm">
						<?php esc_html_e( 'Open', 'storelly-product-builder-for-woocommerce' ); ?>
						<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
					</span>
				</div>
			</a>

		</div>
	</div>

</div>
