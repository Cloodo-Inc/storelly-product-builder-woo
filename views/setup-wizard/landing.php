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

/*
 * Cross-links to other import / configuration screens that help finish setting
 * up the store. Each card is guarded so a disabled or absent module never
 * renders a dead link, and the whole section hides when nothing is available.
 */
$more_cards = array();

// Import existing quote requests from orders / quote plugins / contact forms.
if ( class_exists( 'SPBWC_Quote_Import' ) && method_exists( 'SPBWC_Quote_Import', 'tab_url' ) ) {
	$more_cards[] = array(
		'url'   => SPBWC_Quote_Import::tab_url(),
		'icon'  => 'dashicons-migrate',
		'title' => __( 'Import Quotes', 'storelly-product-builder-for-woocommerce' ),
		'desc'  => __( 'Pull existing quote requests from WooCommerce orders, request-a-quote plugins (YITH, ELEX, Addify, B2BKing) and contact forms into Storelly Quotes.', 'storelly-product-builder-for-woocommerce' ),
		'solid' => false,
	);
}

// Configure the request-a-quote experience.
if ( defined( 'SPBWC_PB_QUOTES_SLUG' ) ) {
	$more_cards[] = array(
		'url'   => admin_url( 'admin.php?page=' . SPBWC_PB_QUOTES_SLUG ),
		'icon'  => 'dashicons-format-status',
		'title' => __( 'Quote Settings', 'storelly-product-builder-for-woocommerce' ),
		'desc'  => __( 'Set up the request-a-quote form fields, statuses and the email notifications buyers receive.', 'storelly-product-builder-for-woocommerce' ),
		'solid' => false,
	);
}

// B2B companies, approval rules and tiered pricing.
if ( class_exists( 'SPBWC_B2B_Admin' ) ) {
	$more_cards[] = array(
		'url'   => admin_url( 'admin.php?page=' . SPBWC_B2B_Admin::PAGE_SLUG ),
		'icon'  => 'dashicons-groups',
		'title' => __( 'B2B Companies', 'storelly-product-builder-for-woocommerce' ),
		'desc'  => __( 'Create companies, approve members and apply tiered pricing for your wholesale and B2B customers.', 'storelly-product-builder-for-woocommerce' ),
		'solid' => false,
	);
}

// Global product-builder behaviour.
if ( defined( 'SPBWC_PB_OPTIONS_SLUG' ) ) {
	$more_cards[] = array(
		'url'   => admin_url( 'admin.php?page=' . SPBWC_PB_OPTIONS_SLUG ),
		'icon'  => 'dashicons-admin-generic',
		'title' => __( 'General Settings', 'storelly-product-builder-for-woocommerce' ),
		'desc'  => __( 'Currency, decimals, designer defaults and the global behaviour of the product builder.', 'storelly-product-builder-for-woocommerce' ),
		'solid' => false,
	);
}

// Demo-data maintenance: how many bundled "bag" demo products are installed, and
// the post-cleanup notice flag (set by SPBWC_Demo_Seeder::handle_cleanup redirect).
$spbwc_demo_count = class_exists( 'SPBWC_Demo_Seeder' ) ? (int) SPBWC_Demo_Seeder::count_demo_products() : 0;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only post-redirect display flag; the destructive action itself is nonce-gated.
$spbwc_demo_cleaned = isset( $_GET['demo_cleaned'] ) ? max( 0, (int) $_GET['demo_cleaned'] ) : -1;

// Custom Order sample state + post-redirect flags (the actions are nonce-gated;
// these are read-only display flags).
$spbwc_co_sample_on    = class_exists( 'SPBWC_Custom_Order_Sample' ) && SPBWC_Custom_Order_Sample::exists();
$spbwc_co_sample_avail = class_exists( 'SPBWC_Custom_Order_Sample' ) && SPBWC_Custom_Order_Sample::bundle_available();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only post-redirect display flag.
$spbwc_co_sample_flag = isset( $_GET['co_sample'] ) ? sanitize_key( wp_unslash( $_GET['co_sample'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only post-redirect display flag.
$spbwc_co_sample_view = isset( $_GET['view'] ) ? absint( wp_unslash( $_GET['view'] ) ) : 0;

// Server capability warnings (A12) — pure read, never phones home.
$spbwc_sys_warnings = class_exists( 'SPBWC_System_Status' ) ? SPBWC_System_Status::get_warnings() : array();
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

	<?php if ( $spbwc_demo_cleaned >= 0 ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				if ( $spbwc_demo_cleaned > 0 ) {
					printf(
						/* translators: %d: number of demo products removed. */
						esc_html( _n( 'Removed %d demo product and its data.', 'Removed %d demo products and their data.', $spbwc_demo_cleaned, 'storelly-product-builder-for-woocommerce' ) ),
						(int) $spbwc_demo_cleaned
					);
				} else {
					esc_html_e( 'No demo data was found to remove.', 'storelly-product-builder-for-woocommerce' );
				}
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( 'added' === $spbwc_co_sample_flag || 'exists' === $spbwc_co_sample_flag ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php esc_html_e( 'Sample custom order is ready.', 'storelly-product-builder-for-woocommerce' ); ?>
				<?php if ( $spbwc_co_sample_view && class_exists( 'SPBWC_Custom_Order_Detail' ) ) : ?>
					<a href="<?php echo esc_url( SPBWC_Custom_Order_Detail::url( $spbwc_co_sample_view ) ); ?>"><?php esc_html_e( 'Open it in the Custom Order workspace →', 'storelly-product-builder-for-woocommerce' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
	<?php elseif ( 'removed' === $spbwc_co_sample_flag ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Sample custom order removed.', 'storelly-product-builder-for-woocommerce' ); ?></p>
		</div>
	<?php elseif ( 'error' === $spbwc_co_sample_flag ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'Could not install the sample custom order. Please try again.', 'storelly-product-builder-for-woocommerce' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $spbwc_sys_warnings ) ) : ?>
		<div class="spbwc-section spbwc-sys-warnings">
			<?php foreach ( $spbwc_sys_warnings as $spbwc_warn ) : ?>
				<div class="spbwc-notice-banner spbwc-notice-banner--<?php echo esc_attr( 'error' === $spbwc_warn['level'] ? 'warn' : $spbwc_warn['level'] ); ?>">
					<span class="dashicons <?php echo esc_attr( 'error' === $spbwc_warn['level'] ? 'dashicons-warning' : 'dashicons-info-outline' ); ?>" aria-hidden="true"></span>
					<div class="spbwc-notice-banner__body">
						<div class="spbwc-notice-banner__title"><?php echo esc_html( $spbwc_warn['label'] ); ?></div>
						<div class="spbwc-notice-banner__text"><?php echo esc_html( $spbwc_warn['detail'] ); ?></div>
						<p class="spbwc-notice-banner__fix">
							<a href="#<?php echo esc_attr( $spbwc_warn['faq_anchor'] ); ?>"><?php esc_html_e( 'How to fix →', 'storelly-product-builder-for-woocommerce' ); ?></a>
						</p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

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

	<?php if ( ! empty( $more_cards ) ) : ?>
	<div class="spbwc-section">
		<header class="spbwc-section__header">
			<h2 class="spbwc-section__title">
				<?php esc_html_e( 'More setup tools', 'storelly-product-builder-for-woocommerce' ); ?>
			</h2>
			<p class="spbwc-section__subtitle">
				<?php esc_html_e( 'Other import and configuration screens that help finish setting up your store.', 'storelly-product-builder-for-woocommerce' ); ?>
			</p>
		</header>
		<div class="spbwc-quick-grid">
			<?php foreach ( $more_cards as $card ) : ?>
				<a class="spbwc-quick-card" href="<?php echo esc_url( $card['url'] ); ?>">
					<div class="spbwc-quick-card__head">
						<div class="spbwc-quick-card__icon">
							<span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
						</div>
						<h2 class="spbwc-quick-card__title"><?php echo esc_html( $card['title'] ); ?></h2>
					</div>
					<p class="spbwc-quick-card__desc"><?php echo esc_html( $card['desc'] ); ?></p>
					<div class="spbwc-quick-card__footer">
						<span class="spbwc-cta-btn <?php echo ! empty( $card['solid'] ) ? 'spbwc-cta-btn--solid' : 'spbwc-cta-btn--ghost'; ?> spbwc-cta-btn--sm">
							<?php esc_html_e( 'Open', 'storelly-product-builder-for-woocommerce' ); ?>
							<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
						</span>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( $spbwc_co_sample_avail ) : ?>
	<div class="spbwc-section">
		<header class="spbwc-section__header">
			<h2 class="spbwc-section__title">
				<?php esc_html_e( 'Custom Order sample', 'storelly-product-builder-for-woocommerce' ); ?>
			</h2>
			<p class="spbwc-section__subtitle">
				<?php esc_html_e( 'Install one labelled sample custom order — with its own design folder — so you can explore the Custom Order workspace (artwork, proofs, files, production) without waiting for a real buyer.', 'storelly-product-builder-for-woocommerce' ); ?>
			</p>
		</header>
		<div class="spbwc-quick-grid">
			<?php if ( ! $spbwc_co_sample_on ) : ?>
				<div class="spbwc-quick-card">
					<div class="spbwc-quick-card__head">
						<div class="spbwc-quick-card__icon">
							<span class="dashicons dashicons-cart" aria-hidden="true"></span>
						</div>
						<h2 class="spbwc-quick-card__title">
							<?php esc_html_e( 'Add Custom Order sample', 'storelly-product-builder-for-woocommerce' ); ?>
						</h2>
					</div>
					<p class="spbwc-quick-card__desc">
						<?php esc_html_e( 'Creates a sample personalised product, a sample order and a copy-on-write design folder. Everything is clearly tagged so you can remove it in one click.', 'storelly-product-builder-for-woocommerce' ); ?>
					</p>
					<div class="spbwc-quick-card__footer">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( SPBWC_Custom_Order_Sample::ACTION_ADD ); ?>" />
							<?php wp_nonce_field( SPBWC_Custom_Order_Sample::ACTION_ADD ); ?>
							<button type="submit" class="spbwc-cta-btn spbwc-cta-btn--solid spbwc-cta-btn--sm">
								<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
								<?php esc_html_e( 'Add Custom Order sample', 'storelly-product-builder-for-woocommerce' ); ?>
							</button>
						</form>
					</div>
				</div>
			<?php else : ?>
				<div class="spbwc-quick-card">
					<div class="spbwc-quick-card__head">
						<div class="spbwc-quick-card__icon">
							<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						</div>
						<h2 class="spbwc-quick-card__title">
							<?php esc_html_e( 'Remove Custom Order sample', 'storelly-product-builder-for-woocommerce' ); ?>
						</h2>
					</div>
					<p class="spbwc-quick-card__desc">
						<?php esc_html_e( 'A sample custom order is installed. Removing it deletes the sample order, its design folder and the sample product. Your real orders are not affected.', 'storelly-product-builder-for-woocommerce' ); ?>
					</p>
					<div class="spbwc-quick-card__footer">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Remove the sample custom order? This cannot be undone.', 'storelly-product-builder-for-woocommerce' ) ); ?>');">
							<input type="hidden" name="action" value="<?php echo esc_attr( SPBWC_Custom_Order_Sample::ACTION_REMOVE ); ?>" />
							<?php wp_nonce_field( SPBWC_Custom_Order_Sample::ACTION_REMOVE ); ?>
							<button type="submit" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm">
								<span class="dashicons dashicons-trash" aria-hidden="true"></span>
								<?php esc_html_e( 'Remove sample', 'storelly-product-builder-for-woocommerce' ); ?>
							</button>
						</form>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( $spbwc_demo_count > 0 ) : ?>
	<div class="spbwc-section">
		<header class="spbwc-section__header">
			<h2 class="spbwc-section__title">
				<?php esc_html_e( 'Demo data', 'storelly-product-builder-for-woocommerce' ); ?>
			</h2>
			<p class="spbwc-section__subtitle">
				<?php esc_html_e( 'Storelly installs a ready-made demo product so you can explore the builder. Remove it when you are done evaluating — this also clears any duplicates.', 'storelly-product-builder-for-woocommerce' ); ?>
			</p>
		</header>
		<div class="spbwc-quick-grid">
			<div class="spbwc-quick-card">
				<div class="spbwc-quick-card__head">
					<div class="spbwc-quick-card__icon">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					</div>
					<h2 class="spbwc-quick-card__title">
						<?php esc_html_e( 'Remove demo data', 'storelly-product-builder-for-woocommerce' ); ?>
					</h2>
				</div>
				<p class="spbwc-quick-card__desc">
					<?php
					printf(
						/* translators: %d: number of demo products currently installed. */
						esc_html( _n( '%d demo product is installed. Removing it deletes the demo product, its option set and bundled images. Your own products are not affected.', '%d demo products are installed. Removing them deletes the demo products, their option sets and bundled images. Your own products are not affected.', $spbwc_demo_count, 'storelly-product-builder-for-woocommerce' ) ),
						(int) $spbwc_demo_count
					);
					?>
				</p>
				<div class="spbwc-quick-card__footer">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Remove all Storelly demo data? This cannot be undone.', 'storelly-product-builder-for-woocommerce' ) ); ?>');">
						<input type="hidden" name="action" value="<?php echo esc_attr( SPBWC_Demo_Seeder::ACTION_CLEANUP ); ?>" />
						<?php wp_nonce_field( SPBWC_Demo_Seeder::ACTION_CLEANUP ); ?>
						<button type="submit" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm">
							<span class="dashicons dashicons-trash" aria-hidden="true"></span>
							<?php esc_html_e( 'Remove demo data', 'storelly-product-builder-for-woocommerce' ); ?>
						</button>
					</form>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<div class="spbwc-section spbwc-faq">
		<header class="spbwc-section__header">
			<h2 class="spbwc-section__title">
				<?php esc_html_e( 'Server requirements & troubleshooting', 'storelly-product-builder-for-woocommerce' ); ?>
			</h2>
			<p class="spbwc-section__subtitle">
				<?php esc_html_e( 'How to resolve the most common server capability warnings. Most are settings your host can change.', 'storelly-product-builder-for-woocommerce' ); ?>
			</p>
		</header>

		<details class="spbwc-faq__item" id="faq-imagick">
			<summary class="spbwc-faq__q"><?php esc_html_e( 'Image library (Imagick / GD) is missing or limited', 'storelly-product-builder-for-woocommerce' ); ?></summary>
			<div class="spbwc-faq__a">
				<p><?php esc_html_e( 'The builder renders designs with PHP’s image extensions. Imagick gives the best print quality; GD is a workable fallback.', 'storelly-product-builder-for-woocommerce' ); ?></p>
				<p><?php esc_html_e( 'Ask your host to enable the “imagick” (or at least “gd”) PHP extension. On cPanel this is under “Select PHP Version → Extensions”. On a managed host, open a support ticket asking to enable Imagick for your PHP version.', 'storelly-product-builder-for-woocommerce' ); ?></p>
			</div>
		</details>

		<details class="spbwc-faq__item" id="faq-memory">
			<summary class="spbwc-faq__q"><?php esc_html_e( 'PHP memory limit is too low', 'storelly-product-builder-for-woocommerce' ); ?></summary>
			<div class="spbwc-faq__a">
				<p><?php esc_html_e( 'Rendering large, multi-view designs needs memory. We recommend at least 128 MB; 256 MB is comfortable.', 'storelly-product-builder-for-woocommerce' ); ?></p>
				<p><?php esc_html_e( 'Add this to wp-config.php above the “stop editing” line: define( \'WP_MEMORY_LIMIT\', \'256M\' ); — or ask your host to raise PHP’s memory_limit if your plan caps it.', 'storelly-product-builder-for-woocommerce' ); ?></p>
			</div>
		</details>

		<details class="spbwc-faq__item" id="faq-upload">
			<summary class="spbwc-faq__q"><?php esc_html_e( 'Upload size limit is too small', 'storelly-product-builder-for-woocommerce' ); ?></summary>
			<div class="spbwc-faq__a">
				<p><?php esc_html_e( 'Customers uploading print-ready artwork can exceed a small upload limit. We recommend at least 8 MB.', 'storelly-product-builder-for-woocommerce' ); ?></p>
				<p><?php esc_html_e( 'Ask your host to raise both upload_max_filesize and post_max_size in PHP (post_max_size must be the same or larger than upload_max_filesize). Many hosts expose these in their control panel.', 'storelly-product-builder-for-woocommerce' ); ?></p>
			</div>
		</details>

		<details class="spbwc-faq__item" id="faq-php">
			<summary class="spbwc-faq__q"><?php esc_html_e( 'PHP version is out of date', 'storelly-product-builder-for-woocommerce' ); ?></summary>
			<div class="spbwc-faq__a">
				<p><?php esc_html_e( 'Storelly and WooCommerce both run best on a current, supported PHP version. Older versions are slower and miss security fixes.', 'storelly-product-builder-for-woocommerce' ); ?></p>
				<p><?php esc_html_e( 'Most hosts let you switch PHP version from their control panel (“Select PHP Version” / “PHP Settings”). Back up first, then pick the latest version your other plugins support.', 'storelly-product-builder-for-woocommerce' ); ?></p>
			</div>
		</details>

		<details class="spbwc-faq__item" id="faq-cron">
			<summary class="spbwc-faq__q"><?php esc_html_e( 'WP-Cron is disabled', 'storelly-product-builder-for-woocommerce' ); ?></summary>
			<div class="spbwc-faq__a">
				<p><?php esc_html_e( 'Background jobs — print-ready PDF rendering, quote expiry and other scheduled tasks — rely on WP-Cron. When DISABLE_WP_CRON is set, they will not run until a real cron triggers them.', 'storelly-product-builder-for-woocommerce' ); ?></p>
				<p><?php esc_html_e( 'If you set DISABLE_WP_CRON on purpose, add a server cron job that requests wp-cron.php every few minutes. Otherwise, remove the DISABLE_WP_CRON line from wp-config.php to let WordPress run cron on page loads.', 'storelly-product-builder-for-woocommerce' ); ?></p>
			</div>
		</details>

		<details class="spbwc-faq__item" id="faq-cloud">
			<summary class="spbwc-faq__q"><?php esc_html_e( 'Storelly Cloud is not connected', 'storelly-product-builder-for-woocommerce' ); ?></summary>
			<div class="spbwc-faq__a">
				<p><?php esc_html_e( 'Cloud features (print-ready PDF generation, sync and analytics) are optional and opt-in. Everything else in Storelly works fully offline — your store is never contacted by Storelly until you connect.', 'storelly-product-builder-for-woocommerce' ); ?></p>
				<p><?php esc_html_e( 'When you need cloud features, connect from the Storelly Overview screen. Setup runs inside wp-admin — no need to leave for storelly.com.', 'storelly-product-builder-for-woocommerce' ); ?></p>
			</div>
		</details>
	</div>

</div>
