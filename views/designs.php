<?php
/**
 * Designs page — standalone admin page listing all customer designs.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view template; variables here are local to this included view, not plugin globals.

if ( ! class_exists( 'SPBWC_Designs_List_Table' ) ) {
	require_once SPBWC_PB_PLUGIN_DIR . 'includes/marketplace/admin/class-designs-list-table.php';
}

// ── Filters ──────────────────────────────────────────────────────────────────
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$filter_status   = isset( $_REQUEST['filter_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_status'] ) ) : '';
$search_customer = isset( $_REQUEST['search_customer'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search_customer'] ) ) : '';
$filter_designer = isset( $_REQUEST['filter_designer'] ) ? absint( wp_unslash( $_REQUEST['filter_designer'] ) ) : 0;
// phpcs:enable

// Resolve customer name/email to user ID for the list table.
if ( ! empty( $search_customer ) && 0 === $filter_designer ) {
	$user_search = new WP_User_Query( array(
		'search'         => '*' . $search_customer . '*',
		'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
		'number'         => 1,
		'fields'         => 'ID',
	) );
	$found = $user_search->get_results();
	if ( ! empty( $found ) ) {
		$filter_designer = (int) $found[0];
	}
}

// Expose resolved designer ID for the list table's prepare_items().
if ( $filter_designer > 0 ) {
	$_REQUEST['filter_designer'] = $filter_designer;
}
if ( '' !== $filter_status ) {
	$_REQUEST['filter_status'] = $filter_status;
}

// ── Stats ─────────────────────────────────────────────────────────────────────
$counts    = function_exists( 'spbwc_marketplace_get_design_status_count' )
	? spbwc_marketplace_get_design_status_count()
	: array( 'all' => 0, 'pending' => 0, 'approved' => 0 );
$total     = (int) ( $counts['all'] ?? 0 );
$published = (int) ( $counts['approved'] ?? 0 );
$draft     = (int) ( $counts['pending'] ?? 0 );

// ── Table ─────────────────────────────────────────────────────────────────────
$table = new SPBWC_Designs_List_Table();
$table->prepare_items();

// ── Notices ───────────────────────────────────────────────────────────────────
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$bulk_done = isset( $_GET['bulk_done'] ) ? sanitize_key( wp_unslash( $_GET['bulk_done'] ) ) : '';
$notice_map = array(
	'publish'   => __( 'Selected designs have been published.', 'storelly-product-builder-for-woocommerce' ),
	'unpublish' => __( 'Selected designs have been unpublished.', 'storelly-product-builder-for-woocommerce' ),
	'delete'    => __( 'Selected designs have been deleted.', 'storelly-product-builder-for-woocommerce' ),
);
?>
<div class="wrap spbwc-designs-page">

	<?php if ( ! empty( $bulk_done ) && isset( $notice_map[ $bulk_done ] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $notice_map[ $bulk_done ] ); ?></p>
		</div>
	<?php endif; ?>

	<!-- ── Page hero ──────────────────────────────────────────────────────── -->
	<header class="spbwc-page-hero">
		<div class="spbwc-page-hero__grid">
			<div class="spbwc-page-hero__body">
				<div class="spbwc-page-hero__eyebrow">
					<span class="dashicons dashicons-art" aria-hidden="true"></span>
					<?php esc_html_e( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ); ?>
				</div>
				<h1 class="spbwc-page-hero__title">
					<span class="dashicons dashicons-images-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'Customer Designs', 'storelly-product-builder-for-woocommerce' ); ?>
				</h1>
				<p class="spbwc-page-hero__subtitle">
					<?php esc_html_e( 'Review, manage and publish designs created by your customers.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
			</div>
		</div>
	</header>

	<!-- ── Stat cards ────────────────────────────────────────────────────── -->
	<div class="spbwc-mp-cards">
		<div class="spbwc-mp-card">
			<span class="spbwc-mp-card__label">
				<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
				<?php esc_html_e( 'Total', 'storelly-product-builder-for-woocommerce' ); ?>
			</span>
			<span class="spbwc-mp-card__value"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
		</div>
		<div class="spbwc-mp-card">
			<span class="spbwc-mp-card__label">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<?php esc_html_e( 'Published', 'storelly-product-builder-for-woocommerce' ); ?>
			</span>
			<span class="spbwc-mp-card__value spbwc-designs-page__stat--ok"><?php echo esc_html( number_format_i18n( $published ) ); ?></span>
		</div>
		<div class="spbwc-mp-card">
			<span class="spbwc-mp-card__label">
				<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
				<?php esc_html_e( 'Draft', 'storelly-product-builder-for-woocommerce' ); ?>
			</span>
			<span class="spbwc-mp-card__value spbwc-designs-page__stat--off"><?php echo esc_html( number_format_i18n( $draft ) ); ?></span>
		</div>
	</div>

	<!-- ── Filter form + list table ──────────────────────────────────────── -->
	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( SPBWC_PB_DESIGNS_SLUG ); ?>" />

		<!-- Filter bar -->
		<div class="spbwc-designs-page__filters">
			<div class="spbwc-designs-page__filter-group">
				<label for="spbwc-filter-status" class="spbwc-designs-page__filter-label">
					<?php esc_html_e( 'Status', 'storelly-product-builder-for-woocommerce' ); ?>
				</label>
				<select name="filter_status" id="spbwc-filter-status" class="spbwc-designs-page__select">
					<option value=""><?php esc_html_e( 'All statuses', 'storelly-product-builder-for-woocommerce' ); ?></option>
					<option value="published" <?php selected( $filter_status, 'published' ); ?>>
						<?php esc_html_e( 'Published', 'storelly-product-builder-for-woocommerce' ); ?>
					</option>
					<option value="draft" <?php selected( $filter_status, 'draft' ); ?>>
						<?php esc_html_e( 'Draft', 'storelly-product-builder-for-woocommerce' ); ?>
					</option>
				</select>
			</div>

			<div class="spbwc-designs-page__filter-group">
				<label for="spbwc-search-customer" class="spbwc-designs-page__filter-label">
					<?php esc_html_e( 'Customer', 'storelly-product-builder-for-woocommerce' ); ?>
				</label>
				<div class="spbwc-designs-page__search-wrap">
					<span class="dashicons dashicons-search spbwc-designs-page__search-icon" aria-hidden="true"></span>
					<input
						type="search"
						name="search_customer"
						id="spbwc-search-customer"
						class="spbwc-designs-page__search-input"
						value="<?php echo esc_attr( $search_customer ); ?>"
						placeholder="<?php esc_attr_e( 'Name or email…', 'storelly-product-builder-for-woocommerce' ); ?>"
					/>
				</div>
			</div>

			<?php submit_button( __( 'Filter', 'storelly-product-builder-for-woocommerce' ), 'secondary spbwc-designs-page__filter-btn', '', false ); ?>

			<?php if ( ! empty( $filter_status ) || ! empty( $search_customer ) ) : ?>
				<a
					href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_DESIGNS_SLUG ) ); ?>"
					class="button spbwc-designs-page__clear-btn"
				><?php esc_html_e( 'Clear', 'storelly-product-builder-for-woocommerce' ); ?></a>
			<?php endif; ?>
		</div>

		<?php if ( $total > 0 || $filter_status || $search_customer ) : ?>
			<?php $table->display(); ?>
		<?php else : ?>
			<div class="spbwc-designs-page__empty">
				<span class="dashicons dashicons-images-alt2 spbwc-designs-page__empty-icon" aria-hidden="true"></span>
				<p class="spbwc-designs-page__empty-title">
					<?php esc_html_e( 'No customer designs yet', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
				<p class="spbwc-designs-page__empty-desc">
					<?php esc_html_e( 'When customers save their designs in the product builder, they will appear here.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
			</div>
		<?php endif; ?>
	</form>

</div><!-- .spbwc-designs-page -->
