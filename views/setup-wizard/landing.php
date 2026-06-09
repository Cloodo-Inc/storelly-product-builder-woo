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
// Full live status list (server + WordPress) for the System status panel.
$spbwc_sys_checks   = class_exists( 'SPBWC_System_Status' ) ? SPBWC_System_Status::get_checks() : array();

// "How to fix" pointers per capability: the exact file to edit + the directive,
// so the merchant knows precisely what to change. Doc link is a fallback guide.
$spbwc_fix_map = array(
	'imagick' => array( 'file' => 'php.ini',       'hint' => 'extension=imagick   (or at least extension=gd)' ),
	'memory'  => array( 'file' => 'wp-config.php', 'hint' => "define( 'WP_MEMORY_LIMIT', '256M' );" ),
	'upload'  => array( 'file' => 'php.ini',       'hint' => 'upload_max_filesize = 32M  ·  post_max_size = 64M' ),
	'php'     => array( 'file' => '',              'hint' => __( 'Switch PHP version in your host control panel ("Select PHP Version").', 'storelly-product-builder-for-woocommerce' ) ),
	'cron'    => array( 'file' => 'wp-config.php', 'hint' => "remove  define( 'DISABLE_WP_CRON', true );  — or add a real system cron" ),
);
$spbwc_docs_url = 'https://storelly.com/docs/server-requirements';

// Help & account widgets (items 2/3/4).
$spbwc_acct_connected = class_exists( 'SPBWC_Cloud_Connect' ) && SPBWC_Cloud_Connect::is_connected();
$spbwc_acct_url       = admin_url( 'admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=integration' );
$spbwc_support_email  = 'support@storelly.com';
$spbwc_support_wa     = '+84 937 869 689';
$spbwc_support_wa_url = 'https://wa.me/84937869689';
$spbwc_locale         = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
$spbwc_lang_url       = admin_url( 'options-general.php#WPLANG' );
$spbwc_translate_url  = 'https://translate.wordpress.org/projects/wp-plugins/storelly-product-builder-for-woocommerce/';
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
							<a href="#spbwc-system-status"><?php esc_html_e( 'See system status &amp; how to fix →', 'storelly-product-builder-for-woocommerce' ); ?></a>
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

	<!-- ── Help & account (items 2/3/4) ─────────────────────────── -->
	<div class="spbwc-section">
		<header class="spbwc-section__header">
			<h2 class="spbwc-section__title"><?php esc_html_e( 'Help & account', 'storelly-product-builder-for-woocommerce' ); ?></h2>
			<p class="spbwc-section__subtitle"><?php esc_html_e( 'Connect your store, switch language, or reach the Storelly team.', 'storelly-product-builder-for-woocommerce' ); ?></p>
		</header>
		<div class="spbwc-quick-grid">

			<!-- Storelly Account (item 4) -->
			<div class="spbwc-quick-card">
				<div class="spbwc-quick-card__head">
					<div class="spbwc-quick-card__icon"><span class="dashicons dashicons-cloud" aria-hidden="true"></span></div>
					<h2 class="spbwc-quick-card__title"><?php esc_html_e( 'Storelly Account', 'storelly-product-builder-for-woocommerce' ); ?></h2>
					<span class="spbwc-pill <?php echo $spbwc_acct_connected ? 'spbwc-pill--ok' : 'spbwc-pill--neutral'; ?>">
						<?php echo $spbwc_acct_connected ? esc_html__( 'Connected', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'Not connected', 'storelly-product-builder-for-woocommerce' ); ?>
					</span>
				</div>
				<p class="spbwc-quick-card__desc">
					<?php echo $spbwc_acct_connected
						? esc_html__( 'Your store is connected to Storelly Cloud. Manage the connection, cloud PDF and order sync from Settings.', 'storelly-product-builder-for-woocommerce' )
						: esc_html__( 'Connect to Storelly Cloud for print-ready PDF rendering and a central dashboard. Free local features keep working either way.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
				<div class="spbwc-quick-card__footer">
					<a class="spbwc-cta-btn spbwc-cta-btn--<?php echo $spbwc_acct_connected ? 'ghost' : 'solid'; ?> spbwc-cta-btn--sm" href="<?php echo esc_url( $spbwc_acct_url ); ?>">
						<span class="dashicons dashicons-<?php echo $spbwc_acct_connected ? 'admin-tools' : 'cloud'; ?>" aria-hidden="true"></span>
						<?php echo $spbwc_acct_connected ? esc_html__( 'Manage account', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'Set up Storelly Account', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
				</div>
			</div>

			<!-- Support (item 3) -->
			<div class="spbwc-quick-card">
				<div class="spbwc-quick-card__head">
					<div class="spbwc-quick-card__icon"><span class="dashicons dashicons-sos" aria-hidden="true"></span></div>
					<h2 class="spbwc-quick-card__title"><?php esc_html_e( 'Need a hand?', 'storelly-product-builder-for-woocommerce' ); ?></h2>
				</div>
				<p class="spbwc-quick-card__desc"><?php esc_html_e( 'Reach the Storelly team — we usually reply within one business day on weekdays.', 'storelly-product-builder-for-woocommerce' ); ?></p>
				<div class="spbwc-quick-card__footer spbwc-action-btns">
					<a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" href="<?php echo esc_url( 'mailto:' . $spbwc_support_email ); ?>">
						<span class="dashicons dashicons-email" aria-hidden="true"></span><?php echo esc_html( $spbwc_support_email ); ?>
					</a>
					<a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" href="<?php echo esc_url( $spbwc_support_wa_url ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-whatsapp" aria-hidden="true"></span><?php echo esc_html( $spbwc_support_wa ); ?>
					</a>
				</div>
			</div>

			<!-- Plugin Language (item 2) -->
			<div class="spbwc-quick-card">
				<div class="spbwc-quick-card__head">
					<div class="spbwc-quick-card__icon"><span class="dashicons dashicons-translation" aria-hidden="true"></span></div>
					<h2 class="spbwc-quick-card__title"><?php esc_html_e( 'Plugin Language', 'storelly-product-builder-for-woocommerce' ); ?></h2>
				</div>
				<p class="spbwc-quick-card__desc">
					<?php
					printf(
						/* translators: %s: current locale code, e.g. en_US. */
						esc_html__( 'Ships in 15 languages. Current site locale: %s. Switch your WordPress language and the plugin admin follows automatically.', 'storelly-product-builder-for-woocommerce' ),
						'<code>' . esc_html( $spbwc_locale ) . '</code>'
					);
					?>
				</p>
				<div class="spbwc-quick-card__footer spbwc-action-btns">
					<a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" href="<?php echo esc_url( $spbwc_lang_url ); ?>">
						<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span><?php esc_html_e( 'Change language', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
					<a class="spbwc-cta-btn spbwc-cta-btn--link spbwc-cta-btn--sm" href="<?php echo esc_url( $spbwc_translate_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Help translate', 'storelly-product-builder-for-woocommerce' ); ?>
						<span class="dashicons dashicons-external" aria-hidden="true"></span>
					</a>
				</div>
			</div>

		</div>
	</div>

	<!-- ── System status (item 1) — live, names the file to fix ──── -->
	<div class="spbwc-section" id="spbwc-system-status">
		<header class="spbwc-section__header">
			<h2 class="spbwc-section__title"><?php esc_html_e( 'System status', 'storelly-product-builder-for-woocommerce' ); ?></h2>
			<p class="spbwc-section__subtitle"><?php esc_html_e( 'Live snapshot of your server and WordPress. Fix anything marked "Action needed" so the builder renders and syncs reliably.', 'storelly-product-builder-for-woocommerce' ); ?></p>
		</header>
		<div class="spbwc-block">
			<ul class="spbwc-status-list">
				<?php
				$spbwc_software = array(
					array( 'label' => __( 'WordPress', 'storelly-product-builder-for-woocommerce' ),               'value' => get_bloginfo( 'version' ) ),
					array( 'label' => __( 'WooCommerce', 'storelly-product-builder-for-woocommerce' ),             'value' => defined( 'WC_VERSION' ) ? WC_VERSION : __( 'not active', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'label' => __( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ), 'value' => defined( 'SPBWC_PB_VERSION' ) ? SPBWC_PB_VERSION : '' ),
				);
				foreach ( $spbwc_software as $spbwc_soft ) :
				?>
				<li class="spbwc-status-row spbwc-status-row--ok">
					<span class="spbwc-status-row__dot" aria-hidden="true"></span>
					<span class="spbwc-status-row__label"><?php echo esc_html( $spbwc_soft['label'] ); ?></span>
					<span class="spbwc-status-row__value"><?php echo esc_html( $spbwc_soft['value'] ); ?></span>
					<span class="spbwc-status-row__badge"><?php esc_html_e( 'OK', 'storelly-product-builder-for-woocommerce' ); ?></span>
				</li>
				<?php endforeach; ?>

				<?php
				foreach ( $spbwc_sys_checks as $spbwc_chk ) :
					$spbwc_lvl   = isset( $spbwc_chk['level'] ) ? $spbwc_chk['level'] : 'ok';
					$spbwc_fix   = isset( $spbwc_fix_map[ $spbwc_chk['key'] ] ) ? $spbwc_fix_map[ $spbwc_chk['key'] ] : null;
					$spbwc_badge = ( 'ok' === $spbwc_lvl ) ? __( 'OK', 'storelly-product-builder-for-woocommerce' ) : ( ( 'warn' === $spbwc_lvl ) ? __( 'Recommended', 'storelly-product-builder-for-woocommerce' ) : __( 'Action needed', 'storelly-product-builder-for-woocommerce' ) );
				?>
				<li class="spbwc-status-row spbwc-status-row--<?php echo esc_attr( $spbwc_lvl ); ?>">
					<span class="spbwc-status-row__dot" aria-hidden="true"></span>
					<span class="spbwc-status-row__label"><?php echo esc_html( $spbwc_chk['label'] ); ?></span>
					<span class="spbwc-status-row__value"><?php echo esc_html( $spbwc_chk['detail'] ); ?></span>
					<span class="spbwc-status-row__badge"><?php echo esc_html( $spbwc_badge ); ?></span>
					<?php if ( 'ok' !== $spbwc_lvl ) : ?>
					<div class="spbwc-status-row__fix">
						<span class="dashicons dashicons-edit" aria-hidden="true"></span>
						<?php if ( 'cloud' === $spbwc_chk['key'] ) : ?>
							<a href="<?php echo esc_url( $spbwc_acct_url ); ?>"><?php esc_html_e( 'Set up Storelly Account →', 'storelly-product-builder-for-woocommerce' ); ?></a>
						<?php else : ?>
							<?php if ( ! empty( $spbwc_fix['file'] ) ) : ?>
								<span class="spbwc-status-row__fix-file"><?php esc_html_e( 'Edit', 'storelly-product-builder-for-woocommerce' ); ?> <code><?php echo esc_html( $spbwc_fix['file'] ); ?></code>:</span>
							<?php endif; ?>
							<?php if ( ! empty( $spbwc_fix['hint'] ) ) : ?>
								<code class="spbwc-status-row__fix-hint"><?php echo esc_html( $spbwc_fix['hint'] ); ?></code>
							<?php endif; ?>
							<a href="<?php echo esc_url( $spbwc_docs_url . '#' . $spbwc_chk['key'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Guide →', 'storelly-product-builder-for-woocommerce' ); ?></a>
						<?php endif; ?>
					</div>
					<?php endif; ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>

</div>
