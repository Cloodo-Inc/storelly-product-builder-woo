<?php
/**
 * Setup Wizard › landing.
 *
 * Two cards: the existing "Import Sample Products" plus the new
 * "Import Woo Variations" one-time seeder.
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
	<h1><?php esc_html_e( 'Setup Wizard', 'storelly-product-builder-for-woocommerce' ); ?></h1>
	<p style="max-width:720px;font-size:14px;line-height:1.6;">
		<?php esc_html_e( 'One-time tools to get your store ready for Storelly. Pick what you need — both can be run independently.', 'storelly-product-builder-for-woocommerce' ); ?>
	</p>

	<div class="spbwc-wizard-cards" style="display:flex;flex-wrap:wrap;gap:20px;margin-top:24px;">
		<div class="spbwc-block" style="flex:1 1 340px;max-width:480px;padding:24px;">
			<h2 style="margin-top:0;display:flex;align-items:center;gap:8px;">
				<span class="dashicons dashicons-archive" aria-hidden="true" style="color:#1971c2;"></span>
				<?php esc_html_e( 'Import Sample Products', 'storelly-product-builder-for-woocommerce' ); ?>
			</h2>
			<p>
				<?php esc_html_e( 'Get started with ready-made product templates from the bundled library. Useful when you are evaluating Storelly on a fresh store.', 'storelly-product-builder-for-woocommerce' ); ?>
			</p>
			<p>
				<a class="button button-primary spbwc-cta-btn" href="<?php echo esc_url( $sample_url ); ?>">
					<?php esc_html_e( 'Open', 'storelly-product-builder-for-woocommerce' ); ?>
				</a>
			</p>
		</div>

		<div class="spbwc-block" style="flex:1 1 340px;max-width:480px;padding:24px;">
			<h2 style="margin-top:0;display:flex;align-items:center;gap:8px;">
				<span class="dashicons dashicons-update" aria-hidden="true" style="color:#1971c2;"></span>
				<?php esc_html_e( 'Import Woo Variations (one-time)', 'storelly-product-builder-for-woocommerce' ); ?>
			</h2>
			<p>
				<?php esc_html_e( 'Convert existing WooCommerce variable products into Storelly pricing options in a single pass. This is a one-time migration, not a live sync — re-running is safe but skips products already linked to a Storelly option.', 'storelly-product-builder-for-woocommerce' ); ?>
			</p>
			<?php if ( $last_seed ) : ?>
				<p style="color:#666;font-size:12px;">
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
			<p>
				<a class="button button-primary spbwc-cta-btn" href="<?php echo esc_url( $woo_url ); ?>">
					<?php esc_html_e( 'Open', 'storelly-product-builder-for-woocommerce' ); ?>
				</a>
			</p>
		</div>
	</div>
</div>
