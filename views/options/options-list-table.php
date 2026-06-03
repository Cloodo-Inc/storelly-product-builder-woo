<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template-scope locals passed in / read-only listing filters; not true globals.
/* v3 is the default editor for new options. `create_v3` is the v3
 * equivalent of `create`; the dispatcher in class-admin-options.php
 * accepts both and routes them to the v3 view. Classic `create`
 * remains reachable via the per-row "Classic editor" sub-action. */
$link_create_option = add_query_arg(
	array(
		'action' => 'create_v3',
		'paged'  => 1,
		'id'     => 0,
	),
	admin_url( 'admin.php?page=' . SPBWC_PB_BUILDER_SLUG )
);

// ── Template variables ─────────────────────────────────────────────
$_nonce_block   = wp_create_nonce( 'spbwc_options_nonce' );        // For edit/copy links
$_nonce_list    = wp_create_nonce( 'spbwc_options_list_nonce' );   // For AJAX mutation ops (trash/dup/rename)
$_rest_nonce    = wp_create_nonce( 'wp_rest' );                    // For REST API GET (grid fetch)
$_rest_url      = rest_url( 'spbwc/v1/options-grid' );             // REST endpoint URL
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin listing filter; no state change.
$_page_slug = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : SPBWC_PB_BUILDER_SLUG;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only listing filter; sanitized in same statement before use.
$_status_filter = isset( $_REQUEST['status_filter'] ) && '' !== $_REQUEST['status_filter'] ? sanitize_text_field( wp_unslash( $_REQUEST['status_filter'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin listing filter; no state change.
$_search_term   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

$_status_counts  = SPBWC_Storelly_Options_List_Table::spbwc_count_all_statuses();
$_base_tab_url   = admin_url( 'admin.php?page=' . $_page_slug );
if ( $_search_term ) {
	$_base_tab_url = add_query_arg( 's', $_search_term, $_base_tab_url );
}
$_pager_base    = $_status_filter !== ''
	? add_query_arg( 'status_filter', $_status_filter, $_base_tab_url )
	: $_base_tab_url;
$_per_page_n    = 10;
$_total_items_n = (int) SPBWC_Storelly_Options_List_Table::spbwc_record_count();
$_total_pages_n = max( 1, (int) ceil( $_total_items_n / $_per_page_n ) );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$_current_page_n = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
?>
<div class="wrap spbwc-options-page">

	<!-- ── Page hero ─────────────────────────────────────────────────── -->
	<header class="spbwc-page-hero">
		<div class="spbwc-page-hero__grid">
			<div class="spbwc-page-hero__body">
				<div class="spbwc-page-hero__eyebrow">
					<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
					<?php esc_html_e( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ); ?>
				</div>
				<h1 class="spbwc-page-hero__title">
					<span class="dashicons dashicons-tickets-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Pricing Options', 'storelly-product-builder-for-woocommerce' ); ?>
				</h1>
				<p class="spbwc-page-hero__subtitle">
					<?php esc_html_e( 'Create and manage printing option groups. Assign them to products or categories to let customers configure their order.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
			</div>
			<div class="spbwc-page-hero__actions">
				<a href="<?php echo esc_url( $link_create_option ); ?>"
				   class="spbwc-cta-btn spbwc-cta-btn--solid">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'Add New Option', 'storelly-product-builder-for-woocommerce' ); ?>
				</a>
			</div>
		</div>
	</header>

	<!-- ── Stats overview row ────────────────────────────────────────── -->
	<div class="spbwc-options-stats">
		<div class="spbwc-stat-card spbwc-stat-card--clickable"
		     data-filter=""
		     role="button" tabindex="0"
		     title="<?php esc_attr_e( 'Show all options', 'storelly-product-builder-for-woocommerce' ); ?>"
		     aria-label="<?php esc_attr_e( 'Filter: all options', 'storelly-product-builder-for-woocommerce' ); ?>">
			<span class="dashicons dashicons-tickets-alt spbwc-stat-card__icon" aria-hidden="true"></span>
			<div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $_status_counts['all'] ) ); ?></div>
			<div class="spbwc-stat-card__label"><?php esc_html_e( 'Total Options', 'storelly-product-builder-for-woocommerce' ); ?></div>
			<div class="spbwc-stat-card__sub"><?php esc_html_e( 'All pricing option groups', 'storelly-product-builder-for-woocommerce' ); ?></div>
		</div>
		<div class="spbwc-stat-card spbwc-stat-card--clickable"
		     data-filter="1"
		     role="button" tabindex="0"
		     title="<?php esc_attr_e( 'Show published options', 'storelly-product-builder-for-woocommerce' ); ?>"
		     aria-label="<?php esc_attr_e( 'Filter: published options', 'storelly-product-builder-for-woocommerce' ); ?>">
			<span class="dashicons dashicons-yes-alt spbwc-stat-card__icon" aria-hidden="true" style="color:var(--nbd-color-success)"></span>
			<div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $_status_counts['published'] ) ); ?></div>
			<div class="spbwc-stat-card__label"><?php esc_html_e( 'Published', 'storelly-product-builder-for-woocommerce' ); ?></div>
			<div class="spbwc-stat-card__sub"><?php esc_html_e( 'Active on your store', 'storelly-product-builder-for-woocommerce' ); ?></div>
		</div>
		<div class="spbwc-stat-card spbwc-stat-card--clickable"
		     data-filter="0"
		     role="button" tabindex="0"
		     title="<?php esc_attr_e( 'Show draft options', 'storelly-product-builder-for-woocommerce' ); ?>"
		     aria-label="<?php esc_attr_e( 'Filter: draft options', 'storelly-product-builder-for-woocommerce' ); ?>">
			<span class="dashicons dashicons-edit spbwc-stat-card__icon" aria-hidden="true"></span>
			<div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $_status_counts['draft'] ) ); ?></div>
			<div class="spbwc-stat-card__label"><?php esc_html_e( 'Drafts', 'storelly-product-builder-for-woocommerce' ); ?></div>
			<div class="spbwc-stat-card__sub"><?php esc_html_e( 'Not yet visible to customers', 'storelly-product-builder-for-woocommerce' ); ?></div>
		</div>
		<?php if ( 0 === $_total_items_n ) : ?>
		<div class="spbwc-stat-card spbwc-stat-card--guide">
			<span class="dashicons dashicons-lightbulb spbwc-stat-card__icon" aria-hidden="true"></span>
			<div class="spbwc-stat-card__label"><?php esc_html_e( 'How to use', 'storelly-product-builder-for-woocommerce' ); ?></div>
			<ol class="spbwc-stat-card__steps">
				<li><?php esc_html_e( 'Create a new option group', 'storelly-product-builder-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Add pricing fields', 'storelly-product-builder-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Assign to products or categories', 'storelly-product-builder-for-woocommerce' ); ?></li>
			</ol>
		</div>
		<?php endif; ?>
	</div>

	<!-- ── Toolbar: tabs + search + sort + count + view toggle ───────── -->
	<div class="spbwc-list-toolbar">
		<div class="spbwc-status-tabs" role="tablist">
			<?php
			$spbwc_tabs = array(
				array(
					'label' => esc_html__( 'All', 'storelly-product-builder-for-woocommerce' ),
					'value' => '',
					'count' => $_status_counts['all'],
				),
				array(
					'label' => esc_html__( 'Published', 'storelly-product-builder-for-woocommerce' ),
					'value' => '1',
					'count' => $_status_counts['published'],
				),
				array(
					'label' => esc_html__( 'Draft', 'storelly-product-builder-for-woocommerce' ),
					'value' => '0',
					'count' => $_status_counts['draft'],
				),
			);
			foreach ( $spbwc_tabs as $spbwc_tab ) :
				$spbwc_tab_active = ( $_status_filter === $spbwc_tab['value'] );
				$spbwc_tab_url    = $spbwc_tab['value'] !== ''
					? add_query_arg( 'status_filter', $spbwc_tab['value'], $_base_tab_url )
					: $_base_tab_url;
			?>
			<a class="spbwc-status-tab<?php echo $spbwc_tab_active ? ' is-active' : ''; ?>"
			   href="<?php echo esc_url( $spbwc_tab_url ); ?>"
			   aria-current="<?php echo $spbwc_tab_active ? 'page' : 'false'; ?>"
			   data-filter="<?php echo esc_attr( $spbwc_tab['value'] ); ?>">
				<?php echo esc_html( $spbwc_tab['label'] ); ?>
				<span class="spbwc-status-tab__count"><?php echo esc_html( number_format_i18n( $spbwc_tab['count'] ) ); ?></span>
			</a>
			<?php endforeach; ?>
		</div>

		<div class="spbwc-list-toolbar__right">
			<!-- Unified search -->
			<div class="spbwc-block-search" id="spbwc-unified-search-wrap">
				<span class="dashicons dashicons-search spbwc-block-search__icon" aria-hidden="true"></span>
				<input type="search"
				       id="spbwc-unified-search"
				       class="spbwc-block-search__input"
				       value="<?php echo esc_attr( $_search_term ); ?>"
				       placeholder="<?php esc_attr_e( 'Search options…', 'storelly-product-builder-for-woocommerce' ); ?>"
				       aria-label="<?php esc_attr_e( 'Search options', 'storelly-product-builder-for-woocommerce' ); ?>">
				<button type="button"
				        class="spbwc-search-clear"
				        id="spbwc-search-clear"
				        aria-label="<?php esc_attr_e( 'Clear search', 'storelly-product-builder-for-woocommerce' ); ?>"
				        hidden>
					<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
				</button>
			</div>

			<!-- Sort by -->
			<select id="spbwc-sort-select" class="spbwc-sort-select"
			        aria-label="<?php esc_attr_e( 'Sort by', 'storelly-product-builder-for-woocommerce' ); ?>">
				<option value="modified-DESC"><?php esc_html_e( 'Newest first', 'storelly-product-builder-for-woocommerce' ); ?></option>
				<option value="modified-ASC"><?php esc_html_e( 'Oldest first', 'storelly-product-builder-for-woocommerce' ); ?></option>
				<option value="title-ASC"><?php esc_html_e( 'Name A → Z', 'storelly-product-builder-for-woocommerce' ); ?></option>
				<option value="title-DESC"><?php esc_html_e( 'Name Z → A', 'storelly-product-builder-for-woocommerce' ); ?></option>
			</select>

			<!-- Live count -->
			<span class="spbwc-list-count" id="spbwc-options-count" aria-live="polite">
				<?php
				echo esc_html( sprintf(
					/* translators: %d: number of option groups */
					_n( '%d option', '%d options', $_total_items_n, 'storelly-product-builder-for-woocommerce' ),
					$_total_items_n
				) );
				?>
			</span>

			<!-- View toggle -->
			<div class="spbwc-view-toggle" role="group"
			     aria-label="<?php esc_attr_e( 'Switch view', 'storelly-product-builder-for-woocommerce' ); ?>">
				<button type="button" class="spbwc-view-btn" data-view="list"
				        title="<?php esc_attr_e( 'List view', 'storelly-product-builder-for-woocommerce' ); ?>"
				        aria-pressed="false">
					<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'List view', 'storelly-product-builder-for-woocommerce' ); ?></span>
				</button>
				<button type="button" class="spbwc-view-btn" data-view="block"
				        title="<?php esc_attr_e( 'Card view', 'storelly-product-builder-for-woocommerce' ); ?>"
				        aria-pressed="true">
					<span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Card view', 'storelly-product-builder-for-woocommerce' ); ?></span>
				</button>
			</div>
		</div>
	</div>

	<!-- ── List view (WP table) ──────────────────────────────────────── -->
	<div id="spbwc-list-view" class="spbwc-list-view">
		<form method="post" id="spbwc-options-list-form">
			<?php
			wp_nonce_field( 'bulk-options', '_wpnonce' );
			$spbwc_options->spbwc_prepare_items();
			$spbwc_options->display();
			?>
		</form>
	</div>

	<!-- ── Card / grid view ──────────────────────────────────────────── -->
	<div id="spbwc-block-view" class="spbwc-block-view" hidden>

		<div class="spbwc-block-view__inner" id="spbwc-block-view-inner">

			<?php if ( ! empty( $spbwc_options->items ) ) : ?>
			<div class="spbwc-options-grid" id="spbwc-options-grid">
				<?php foreach ( $spbwc_options->items as $spbwc_item ) :
					$spbwc_title   = $spbwc_item['title'];
					$spbwc_pub     = (int) $spbwc_item['published'];
					$spbwc_id_str  = esc_attr( (string) absint( $spbwc_item['id'] ) );
					$spbwc_count   = SPBWC_Storelly_Options_List_Table::spbwc_count_fields( $spbwc_item['fields'] );
					$spbwc_accent  = SPBWC_Storelly_PB_Util::spbwc_option_color( $spbwc_title );

					$spbwc_edit_url = esc_url( add_query_arg(
						array(
							'page'     => $_page_slug,
							'action'   => 'edit',
							'id'       => absint( $spbwc_item['id'] ),
							'paged'    => 1,
							'_wpnonce' => $_nonce_block,
						),
						admin_url( 'admin.php' )
					) );

					// Category names.
					$spbwc_cat_html = '';
					if ( ! empty( $spbwc_item['product_cats'] ) ) {
						$spbwc_cats = maybe_unserialize( $spbwc_item['product_cats'] );
						if ( is_array( $spbwc_cats ) ) {
							$spbwc_cat_names = array();
							foreach ( array_slice( $spbwc_cats, 0, 3 ) as $spbwc_cid ) {
								$spbwc_term = get_term( absint( $spbwc_cid ), 'product_cat' );
								if ( $spbwc_term && ! is_wp_error( $spbwc_term ) ) {
									$spbwc_cat_names[] = esc_html( $spbwc_term->name );
								}
							}
							if ( $spbwc_cat_names ) {
								$spbwc_cat_html = implode( ', ', $spbwc_cat_names );
							}
						}
					}
				?>
				<article class="spbwc-option-card"
				         data-title="<?php echo esc_attr( mb_strtolower( $spbwc_title ) ); ?>"
				         data-option-id="<?php echo esc_attr( $spbwc_id_str ); ?>"
				         data-published="<?php echo esc_attr( $spbwc_pub ); ?>">
					<a href="<?php echo $spbwc_edit_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already esc_url'd above ?>"
					   class="spbwc-option-card__thumb spbwc-option-card__thumb--name"
					   style="background:<?php echo esc_attr( $spbwc_accent ); ?>;"
					   aria-label="<?php echo esc_attr( $spbwc_title ); ?>">
						<span class="spbwc-option-card__thumb-name"><?php echo esc_html( $spbwc_title ); ?></span>
					</a>
					<div class="spbwc-option-card__body">
						<div class="spbwc-option-card__header">
							<h3 class="spbwc-option-card__title">
								<a href="<?php echo $spbwc_edit_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already esc_url'd above ?>"><?php echo esc_html( $spbwc_title ); ?></a>
							</h3>
							<button type="button"
							        class="spbwc-publish-toggle<?php echo 1 === $spbwc_pub ? ' is-published' : ''; ?>"
							        data-spbwc-action="toggle-publish"
							        data-id="<?php echo esc_attr( $spbwc_id_str ); ?>"
							        data-published="<?php echo esc_attr( $spbwc_pub ); ?>"
							        title="<?php echo 1 === $spbwc_pub ? esc_attr__( 'Unpublish', 'storelly-product-builder-for-woocommerce' ) : esc_attr__( 'Publish', 'storelly-product-builder-for-woocommerce' ); ?>"
							        aria-pressed="<?php echo 1 === $spbwc_pub ? 'true' : 'false'; ?>">
								<span class="dashicons dashicons-<?php echo 1 === $spbwc_pub ? 'visibility' : 'hidden'; ?>" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php echo 1 === $spbwc_pub ? esc_html__( 'Unpublish', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'Publish', 'storelly-product-builder-for-woocommerce' ); ?></span>
							</button>
						</div>
						<div class="spbwc-option-card__meta">
							<?php if ( 1 === $spbwc_pub ) : ?>
							<span class="spbwc-badge spbwc-badge--published"><?php esc_html_e( 'Published', 'storelly-product-builder-for-woocommerce' ); ?></span>
							<?php else : ?>
							<span class="spbwc-badge spbwc-badge--draft"><?php esc_html_e( 'Draft', 'storelly-product-builder-for-woocommerce' ); ?></span>
							<?php endif; ?>
							<span class="spbwc-field-count-badge">
								<?php echo esc_html( sprintf(
									/* translators: %d: number of fields */
									_n( '%d field', '%d fields', $spbwc_count, 'storelly-product-builder-for-woocommerce' ),
									$spbwc_count
								) ); ?>
							</span>
						</div>

						<?php if ( $spbwc_cat_html ) : ?>
						<div class="spbwc-option-card__cats">
							<span class="dashicons dashicons-category" aria-hidden="true"></span>
							<?php echo $spbwc_cat_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- content already esc_html'd per item above ?>
						</div>
						<?php endif; ?>

						<?php if ( ! empty( $spbwc_item['modified'] ) ) : ?>
						<div class="spbwc-option-card__date">
							<span class="dashicons dashicons-clock" aria-hidden="true"></span>
							<?php
							echo esc_html( sprintf(
								/* translators: %s: human-readable time difference */
								__( 'Updated %s ago', 'storelly-product-builder-for-woocommerce' ),
								human_time_diff( strtotime( $spbwc_item['modified'] ), current_time( 'timestamp' ) )
							) );
							?>
						</div>
						<?php endif; ?>

						<div class="spbwc-option-card__actions">
							<a href="<?php echo $spbwc_edit_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already esc_url'd above ?>"
							   class="spbwc-card-btn spbwc-card-btn--primary">
								<span class="dashicons dashicons-edit" aria-hidden="true"></span>
								<?php esc_html_e( 'Edit', 'storelly-product-builder-for-woocommerce' ); ?>
							</a>
							<button type="button"
							        class="spbwc-card-btn"
							        data-spbwc-action="duplicate"
							        data-id="<?php echo esc_attr( $spbwc_id_str ); ?>"
							        title="<?php esc_attr_e( 'Duplicate', 'storelly-product-builder-for-woocommerce' ); ?>">
								<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
							</button>
							<button type="button"
							        class="spbwc-card-btn spbwc-card-btn--danger"
							        data-spbwc-action="trash"
							        data-id="<?php echo esc_attr( $spbwc_id_str ); ?>"
							        title="<?php esc_attr_e( 'Delete', 'storelly-product-builder-for-woocommerce' ); ?>">
								<span class="dashicons dashicons-trash" aria-hidden="true"></span>
							</button>
						</div>
					</div>
				</article>
				<?php endforeach; ?>
			</div>
			<?php else : ?>
			<div class="spbwc-block-empty" id="spbwc-block-empty-default">
				<span class="dashicons dashicons-tickets-alt" aria-hidden="true"></span>
				<p><?php esc_html_e( 'No options yet.', 'storelly-product-builder-for-woocommerce' ); ?></p>
				<a href="<?php echo esc_url( $link_create_option ); ?>"
				   class="spbwc-cta-btn spbwc-cta-btn--solid" style="margin-top:12px;">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'Create your first option', 'storelly-product-builder-for-woocommerce' ); ?>
				</a>
			</div>
			<?php endif; ?>

		</div><!-- #spbwc-block-view-inner -->

		<!-- Pagination (rendered/updated by JS) -->
		<nav id="spbwc-block-pagination" class="spbwc-block-pagination"
		     aria-label="<?php esc_attr_e( 'Pages', 'storelly-product-builder-for-woocommerce' ); ?>"
		     <?php echo $_total_pages_n <= 1 ? 'hidden' : ''; ?>>
			<?php if ( $_current_page_n > 1 ) : ?>
			<button type="button" class="spbwc-page-btn"
			        data-spbwc-page="<?php echo (int) ( $_current_page_n - 1 ); ?>"
			        aria-label="<?php esc_attr_e( 'Previous page', 'storelly-product-builder-for-woocommerce' ); ?>">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
			</button>
			<?php else : ?>
			<span class="spbwc-page-btn is-disabled" aria-hidden="true"><span class="dashicons dashicons-arrow-left-alt2"></span></span>
			<?php endif; ?>

			<span class="spbwc-page-info">
				<?php
				printf(
					/* translators: 1: current page number, 2: total pages */
					esc_html__( 'Page %1$d of %2$d', 'storelly-product-builder-for-woocommerce' ),
					(int) $_current_page_n,
					(int) $_total_pages_n
				);
				?>
			</span>

			<?php if ( $_current_page_n < $_total_pages_n ) : ?>
			<button type="button" class="spbwc-page-btn"
			        data-spbwc-page="<?php echo (int) ( $_current_page_n + 1 ); ?>"
			        aria-label="<?php esc_attr_e( 'Next page', 'storelly-product-builder-for-woocommerce' ); ?>">
				<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
			</button>
			<?php else : ?>
			<span class="spbwc-page-btn is-disabled" aria-hidden="true"><span class="dashicons dashicons-arrow-right-alt2"></span></span>
			<?php endif; ?>
		</nav>

	</div><!-- #spbwc-block-view -->

	<!-- Loading overlay (shown during AJAX) -->
	<div id="spbwc-loading-overlay" class="spbwc-loading-overlay" hidden
	     aria-live="polite"
	     aria-label="<?php esc_attr_e( 'Loading', 'storelly-product-builder-for-woocommerce' ); ?>">
		<span class="spbwc-loading-spin dashicons dashicons-update-alt" aria-hidden="true"></span>
	</div>

	<!-- ── Delete confirm modal ──────────────────────────────────────── -->
	<div id="spbwc-delete-modal" class="spbwc-modal-overlay" hidden
	     role="dialog" aria-modal="true" aria-hidden="true"
	     aria-labelledby="spbwc-delete-modal-title">
		<div class="spbwc-modal">
			<div class="spbwc-modal__icon" aria-hidden="true">
				<span class="dashicons dashicons-trash"></span>
			</div>
			<h3 id="spbwc-delete-modal-title" class="spbwc-modal__title">
				<?php esc_html_e( 'Delete option?', 'storelly-product-builder-for-woocommerce' ); ?>
			</h3>
			<p id="spbwc-delete-modal-desc" class="spbwc-modal__desc">
				<?php esc_html_e( 'This cannot be undone.', 'storelly-product-builder-for-woocommerce' ); ?>
			</p>
			<div class="spbwc-modal__actions">
				<button type="button" id="spbwc-delete-cancel" class="spbwc-modal-btn">
					<?php esc_html_e( 'Cancel', 'storelly-product-builder-for-woocommerce' ); ?>
				</button>
				<button type="button" id="spbwc-delete-confirm" class="spbwc-modal-btn spbwc-modal-btn--danger">
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					<?php esc_html_e( 'Delete', 'storelly-product-builder-for-woocommerce' ); ?>
				</button>
			</div>
		</div>
	</div>

</div><!-- .wrap -->

<!-- Toast notification (flex layout for inline Undo button) -->
<div id="spbwc-toast" class="spbwc-toast" hidden role="status" aria-live="polite">
	<span id="spbwc-toast-msg"></span>
	<button type="button" id="spbwc-toast-undo" class="spbwc-toast__undo" hidden>
		<?php esc_html_e( 'Undo', 'storelly-product-builder-for-woocommerce' ); ?>
	</button>
</div>

<script>
(function () {
	'use strict';

	var AJAX_URL    = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var NONCE       = '<?php echo esc_js( $_nonce_list ); ?>'; // Used for mutation ops (trash/dup/rename)
	// REST endpoint for read-only grid fetch — faster than admin-ajax.php
	// because it bypasses admin-specific hooks (~100-200 ms bootstrap savings).
	var REST_URL    = '<?php echo esc_js( esc_url_raw( $_rest_url ) ); ?>';
	var REST_NONCE  = '<?php echo esc_js( $_rest_nonce ); ?>';
	var STORAGE_KEY = 'spbwc_options_view';
	var SINGULAR    = '<?php echo esc_js( _x( 'option', 'singular item count', 'storelly-product-builder-for-woocommerce' ) ); ?>';
	var PLURAL      = '<?php echo esc_js( _x( 'options', 'plural item count', 'storelly-product-builder-for-woocommerce' ) ); ?>';
	var ERR_GENERIC = '<?php echo esc_js( __( 'Something went wrong. Please try again.', 'storelly-product-builder-for-woocommerce' ) ); ?>';

	/** Current filter state */
	var state = {
		paged:         <?php echo (int) $_current_page_n; ?>,
		status_filter: '<?php echo esc_js( $_status_filter ); ?>',
		s:             '<?php echo esc_js( $_search_term ); ?>',
		orderby:       'modified',
		order:         'DESC'
	};

	var fetchTimer = null;

	// ── Loading overlay ───────────────────────────────────────────────
	function setLoading( on ) {
		var overlay = document.getElementById( 'spbwc-loading-overlay' );
		var inner   = document.getElementById( 'spbwc-block-view-inner' );
		if ( overlay ) { overlay.hidden = ! on; }
		if ( inner ) {
			inner.style.opacity       = on ? '0.45' : '';
			inner.style.pointerEvents = on ? 'none' : '';
		}
	}

	// ── Toast (supports optional Undo callback) ───────────────────────
	function showToast( msg, type, undoCallback ) {
		var toast   = document.getElementById( 'spbwc-toast' );
		var msgEl   = document.getElementById( 'spbwc-toast-msg' );
		var undoBtn = document.getElementById( 'spbwc-toast-undo' );
		if ( ! toast || ! msgEl ) { return; }
		msgEl.textContent = msg;
		toast.className   = 'spbwc-toast spbwc-toast--' + ( type || 'success' );
		if ( undoCallback && undoBtn ) {
			undoBtn.hidden = false;
			// Clone to remove any previous click listener.
			var fresh = undoBtn.cloneNode( true );
			undoBtn.parentNode.replaceChild( fresh, undoBtn );
			fresh.addEventListener( 'click', function () {
				toast.hidden = true;
				clearTimeout( toast._timer );
				undoCallback();
			} );
		} else if ( undoBtn ) {
			undoBtn.hidden = true;
		}
		toast.hidden = false;
		clearTimeout( toast._timer );
		toast._timer = setTimeout( function () { toast.hidden = true; }, undoCallback ? 5000 : 3200 );
	}

	// ── Count label ───────────────────────────────────────────────────
	function countLabel( n ) {
		return n + ' ' + ( 1 === n ? SINGULAR : PLURAL );
	}

	// ── Delete confirm modal ──────────────────────────────────────────
	var _deleteModalPending = null; // { id, prevPub, onConfirm }

	function showDeleteModal( id, title, prevPub, onConfirm ) {
		var overlay = document.getElementById( 'spbwc-delete-modal' );
		var desc    = document.getElementById( 'spbwc-delete-modal-desc' );
		if ( ! overlay ) { return; }
		if ( desc ) {
			var cannotUndo = '<?php echo esc_js( __( 'This cannot be undone.', 'storelly-product-builder-for-woocommerce' ) ); ?>';
			desc.textContent = title
				? '“' + title + '” — ' + cannotUndo
				: cannotUndo;
		}
		_deleteModalPending = { id: id, prevPub: prevPub, onConfirm: onConfirm };
		overlay.hidden = false;
		overlay.removeAttribute( 'aria-hidden' );
		// Defer focus so the element is visible first.
		setTimeout( function () {
			var btn = document.getElementById( 'spbwc-delete-confirm' );
			if ( btn ) { btn.focus(); }
		}, 50 );
	}

	function closeDeleteModal() {
		var overlay = document.getElementById( 'spbwc-delete-modal' );
		if ( overlay ) {
			overlay.hidden = true;
			overlay.setAttribute( 'aria-hidden', 'true' );
		}
		_deleteModalPending = null;
	}

	// ── Skeleton loading placeholders ─────────────────────────────────
	function renderSkeletons( count ) {
		var html = '<div class="spbwc-options-grid">';
		for ( var i = 0; i < count; i++ ) {
			html += '<div class="spbwc-option-card spbwc-option-card--skeleton" aria-hidden="true">'
			      + '<div class="spbwc-skeleton__thumb"></div>'
			      + '<div class="spbwc-option-card__body">'
			      + '<div class="spbwc-skeleton__line" style="width:68%"></div>'
			      + '<div class="spbwc-skeleton__line spbwc-skeleton__line--sm" style="width:42%"></div>'
			      + '<div class="spbwc-skeleton__line spbwc-skeleton__line--sm" style="width:55%"></div>'
			      + '</div>'
			      + '</div>';
		}
		html += '</div>';
		return html;
	}

	// ── Fetch grid via REST API (GET) ────────────────────────────────
	// REST API skips admin-specific hooks → ~100-200 ms faster than admin-ajax.php.
	// Mutation ops (trash, duplicate, rename) still use doPost() → AJAX POST.
	function fetchList( resetPage ) {
		if ( resetPage ) { state.paged = 1; }

		// Build the request URL.
		// IMPORTANT: When pretty permalinks are disabled, rest_url() already
		// contains a query string ("?rest_route=/spbwc/v1/options-grid"), so
		// naively appending "?paged=1&..." would create a double-"?" URL that
		// PHP mis-parses (rest_route value becomes "/spbwc/v1/options-grid?paged=1"
		// which matches no registered route → 404).
		// Use the URL constructor to add params correctly in both permalink modes.
		var fetchUrl = new URL( REST_URL );
		fetchUrl.searchParams.set( 'paged',         state.paged );
		fetchUrl.searchParams.set( 'status_filter', state.status_filter );
		fetchUrl.searchParams.set( 's',             state.s );
		fetchUrl.searchParams.set( 'orderby',       state.orderby );
		fetchUrl.searchParams.set( 'order',         state.order );

		// Show skeletons immediately; real cards replace them on resolve.
		var inner = document.getElementById( 'spbwc-block-view-inner' );
		if ( inner ) { inner.innerHTML = renderSkeletons( 6 ); }
		setLoading( true );

		var fetchStart = performance.now();

		fetch( fetchUrl.toString(), {
			method:      'GET',
			credentials: 'same-origin',
			headers:     { 'X-WP-Nonce': REST_NONCE },
		} )
			.then( function ( r ) {
				if ( ! r.ok ) {
					return r.json().then( function ( body ) {
						throw new Error( ( body && body.message ) ? body.message : ERR_GENERIC );
					} );
				}
				return r.json();
			} )
			.then( function ( d ) {
				// REST response is the payload directly (no success/data wrapper).
				var networkMs = Math.round( performance.now() - fetchStart );

				// Log server-side timing when WP_DEBUG is on (PHP appends _timing).
				if ( d._timing ) {
					var t = d._timing;
					console.groupCollapsed( '[Storelly] REST fetchList — ' + networkMs + 'ms total' );
					console.log( 'Source      :', t.source );
					console.log( 'Network     :', networkMs + 'ms  (includes WP bootstrap + handler)' );
					if ( t.source === 'live' ) {
						console.log( 'Handler     :', t.handler_ms + 'ms  (inside PHP callback)' );
						console.log( '  DB query  :', t.db_ms + 'ms' );
						console.log( '  Render    :', t.render_ms + 'ms' );
						console.log( 'WP bootstrap:', ( networkMs - t.handler_ms ) + 'ms  (approx — load plugins + hooks)' );
					} else {
						console.log( 'Handler     :', t.handler_ms + 'ms  (transient hit)' );
						console.log( 'WP bootstrap:', ( networkMs - t.handler_ms ) + 'ms  (approx)' );
					}
					console.groupEnd();
				}

				if ( inner ) {
					inner.innerHTML = d.grid_html;
					// Re-attach pagination nav (it lives outside inner)
					var nav = document.getElementById( 'spbwc-block-pagination' );
					if ( nav ) { nav.parentNode.appendChild( nav ); }
				}
				renderPagination( d.paged, d.total_pages );
				updateCount( d.total );
				updateTabCounts( d.counts );
				bindCardActions();

				// Inject "Clear search" CTA when search returns nothing.
				if ( d.total === 0 && state.s && inner ) {
					var emptyEl = inner.querySelector( '.spbwc-block-empty' );
					if ( emptyEl ) {
						var clearSearchBtn = document.createElement( 'button' );
						clearSearchBtn.type      = 'button';
						clearSearchBtn.className = 'spbwc-cta-btn';
						clearSearchBtn.style.marginTop = '12px';
						clearSearchBtn.textContent = '<?php echo esc_js( __( 'Clear search', 'storelly-product-builder-for-woocommerce' ) ); ?>';
						clearSearchBtn.addEventListener( 'click', function () {
							var searchEl = document.getElementById( 'spbwc-unified-search' );
							if ( searchEl ) { searchEl.value = ''; }
							state.s = '';
							var clearBtnEl = document.getElementById( 'spbwc-search-clear' );
							if ( clearBtnEl ) { clearBtnEl.hidden = true; }
							fetchList( true );
						} );
						emptyEl.appendChild( clearSearchBtn );
					}
				}
			} )
			.catch( function ( err ) {
				showToast( ( err && err.message ) ? err.message : ERR_GENERIC, 'error' );
				// Clear stuck skeletons on network / parse errors.
				if ( inner ) { inner.innerHTML = ''; }
			} )
			.finally( function () { setLoading( false ); } );
	}

	// ── Pagination ────────────────────────────────────────────────────
	function renderPagination( paged, totalPages ) {
		var nav = document.getElementById( 'spbwc-block-pagination' );
		if ( ! nav ) { return; }
		if ( totalPages <= 1 ) { nav.hidden = true; return; }
		nav.hidden = false;

		var html = '';
		if ( paged > 1 ) {
			html += '<button type="button" class="spbwc-page-btn" data-spbwc-page="' + ( paged - 1 ) + '"'
			      + ' aria-label="Previous page"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button>';
		} else {
			html += '<span class="spbwc-page-btn is-disabled" aria-hidden="true"><span class="dashicons dashicons-arrow-left-alt2"></span></span>';
		}
		html += '<span class="spbwc-page-info">Page ' + paged + ' of ' + totalPages + '</span>';
		if ( paged < totalPages ) {
			html += '<button type="button" class="spbwc-page-btn" data-spbwc-page="' + ( paged + 1 ) + '"'
			      + ' aria-label="Next page"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>';
		} else {
			html += '<span class="spbwc-page-btn is-disabled" aria-hidden="true"><span class="dashicons dashicons-arrow-right-alt2"></span></span>';
		}
		nav.innerHTML = html;
	}

	// ── Count label ───────────────────────────────────────────────────
	function updateCount( total ) {
		var el = document.getElementById( 'spbwc-options-count' );
		if ( el ) { el.textContent = countLabel( total ); }
	}

	// ── Tab badge counts + active state ───────────────────────────────
	function updateTabCounts( counts ) {
		if ( ! counts ) { return; }
		var filterMap = { '': 'all', '1': 'published', '0': 'draft' };
		document.querySelectorAll( '.spbwc-status-tab' ).forEach( function ( tab ) {
			var sf    = tab.dataset.filter !== undefined ? tab.dataset.filter : '';
			var key   = filterMap[ sf ] !== undefined ? filterMap[ sf ] : 'all';
			var badge = tab.querySelector( '.spbwc-status-tab__count' );
			if ( badge && counts[ key ] !== undefined ) {
				badge.textContent = counts[ key ];
			}
			var isActive = sf === state.status_filter;
			tab.classList.toggle( 'is-active', isActive );
			tab.setAttribute( 'aria-current', isActive ? 'page' : 'false' );
		} );
	}

	// ── Generic AJAX POST helper ──────────────────────────────────────
	function doPost( actionName, extraData, onSuccess ) {
		var fd = new FormData();
		fd.append( 'action', actionName );
		fd.append( 'nonce',  NONCE );
		Object.keys( extraData ).forEach( function ( k ) {
			fd.append( k, extraData[ k ] );
		} );
		return fetch( AJAX_URL, { method: 'POST', credentials: 'same-origin', body: fd } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( res && res.success ) {
					onSuccess( res.data );
				} else {
					showToast( ( res && res.data && res.data.msg ) || ERR_GENERIC, 'error' );
				}
			} )
			.catch( function () { showToast( ERR_GENERIC, 'error' ); } );
	}

	// ── Bind card action buttons (called after each grid re-render) ───
	function bindCardActions() {
		// Trash — uses custom modal + 5-second Undo toast
		document.querySelectorAll( '[data-spbwc-action="trash"]:not([data-bound])' ).forEach( function ( btn ) {
			btn.setAttribute( 'data-bound', '1' );
			btn.addEventListener( 'click', function () {
				var id      = this.dataset.id;
				var card    = this.closest( '.spbwc-option-card' );
				var titleEl = card ? card.querySelector( '.spbwc-option-card__title a' ) : null;
				var title   = titleEl ? titleEl.textContent.trim() : '';
				var prevPub = card ? ( parseInt( card.dataset.published, 10 ) || 0 ) : 0;

				showDeleteModal( id, title, prevPub, function () {
					if ( card ) {
						card.style.opacity       = '0.4';
						card.style.pointerEvents = 'none';
					}
					setLoading( true );
					doPost( 'spbwc_trash_option', { id: id }, function ( data ) {
						// Undo restores the item to its previous published state.
						var undoFn = function () {
							doPost( 'spbwc_publish_option_ajax', { id: id, published: prevPub }, function () {
								fetchList( false );
							} );
						};
						showToast(
							data.msg || '<?php echo esc_js( __( 'Option deleted.', 'storelly-product-builder-for-woocommerce' ) ); ?>',
							'success',
							undoFn
						);
						updateTabCounts( data.counts );
						fetchList( false );
					} );
				} );
			} );
		} );

		// Duplicate
		document.querySelectorAll( '[data-spbwc-action="duplicate"]:not([data-bound])' ).forEach( function ( btn ) {
			btn.setAttribute( 'data-bound', '1' );
			btn.addEventListener( 'click', function () {
				var id   = this.dataset.id;
				var self = this;
				self.disabled = true;
				setLoading( true );
				doPost( 'spbwc_duplicate_option', { id: id }, function ( data ) {
					showToast( data.msg || 'Duplicated', 'success' );
					updateTabCounts( data.counts );
					fetchList( false );
				} ).finally( function () { self.disabled = false; } );
			} );
		} );

		// Toggle publish/unpublish
		document.querySelectorAll( '[data-spbwc-action="toggle-publish"]:not([data-bound])' ).forEach( function ( btn ) {
			btn.setAttribute( 'data-bound', '1' );
			btn.addEventListener( 'click', function () {
				var id      = this.dataset.id;
				var current = parseInt( this.dataset.published, 10 );
				var next    = current ? 0 : 1;
				var card    = this.closest( '.spbwc-option-card' );
				var self    = this;
				doPost( 'spbwc_publish_option_ajax', { id: id, published: next }, function ( data ) {
					var pub = parseInt( data.published, 10 );

					// Update toggle button state
					self.dataset.published = pub;
					self.setAttribute( 'aria-pressed', pub ? 'true' : 'false' );
					self.classList.toggle( 'is-published', 1 === pub );
					var icon = self.querySelector( '.dashicons' );
					if ( icon ) {
						icon.className = 'dashicons dashicons-' + ( pub ? 'visibility' : 'hidden' );
					}

					// Update status badge in card
					if ( card ) {
						var badge = card.querySelector( '.spbwc-badge' );
						if ( badge ) {
							badge.className   = 'spbwc-badge spbwc-badge--' + ( pub ? 'published' : 'draft' );
							badge.textContent = pub ? 'Published' : 'Draft';
						}
						card.dataset.published = pub;
					}

					var msg = pub
						? '<?php echo esc_js( __( 'Option published.', 'storelly-product-builder-for-woocommerce' ) ); ?>'
						: '<?php echo esc_js( __( 'Option set to draft.', 'storelly-product-builder-for-woocommerce' ) ); ?>';
					showToast( msg, 'success' );
					updateTabCounts( data.counts );

					// Re-fetch if a status filter is active so the card disappears correctly
					if ( state.status_filter !== '' ) { fetchList( false ); }
				} );
			} );
		} );

		// Attach inline-rename handlers to newly rendered cards.
		bindInlineRename();
	}

	// ── Inline rename: double-click card title ────────────────────────
	function bindInlineRename() {
		document.querySelectorAll( '.spbwc-option-card__title a:not([data-rename-bound])' ).forEach( function ( link ) {
			link.setAttribute( 'data-rename-bound', '1' );
			link.addEventListener( 'dblclick', function ( e ) {
				e.preventDefault();
				var card   = this.closest( '.spbwc-option-card' );
				var id     = card ? card.dataset.optionId : null;
				var anchor = this;
				if ( ! id || card.querySelector( '.spbwc-rename-input' ) ) { return; }

				var current  = anchor.textContent.trim();
				var input    = document.createElement( 'input' );
				input.type      = 'text';
				input.value     = current;
				input.className = 'spbwc-rename-input';
				input.setAttribute( 'aria-label', '<?php echo esc_js( __( 'Rename option', 'storelly-product-builder-for-woocommerce' ) ); ?>' );

				anchor.style.display = 'none';
				anchor.parentNode.insertBefore( input, anchor );
				input.select();

				var cancelled = false;
				var cancel = function () {
					cancelled = true;
					if ( input.parentNode ) { input.parentNode.removeChild( input ); }
					anchor.style.display = '';
				};
				var save = function () {
					if ( cancelled ) { return; }
					var newTitle = input.value.trim();
					if ( ! newTitle || newTitle === current ) { cancel(); return; }
					input.disabled = true;
					doPost( 'spbwc_rename_option', { id: id, title: newTitle }, function ( data ) {
						anchor.textContent = data.title || newTitle;
						if ( card ) { card.dataset.title = ( data.title || newTitle ).toLowerCase(); }
						cancel();
						showToast( data.msg || '<?php echo esc_js( __( 'Renamed.', 'storelly-product-builder-for-woocommerce' ) ); ?>', 'success' );
					} );
				};
				input.addEventListener( 'keydown', function ( ev ) {
					if ( 'Enter'  === ev.key ) { ev.preventDefault(); save(); }
					if ( 'Escape' === ev.key ) { ev.preventDefault(); cancel(); }
				} );
				input.addEventListener( 'blur', function () {
					setTimeout( function () { if ( ! cancelled ) { save(); } }, 120 );
				} );
			} );
		} );
	}

	// ── View switch ───────────────────────────────────────────────────
	function setView( view ) {
		var listView  = document.getElementById( 'spbwc-list-view' );
		var blockView = document.getElementById( 'spbwc-block-view' );
		if ( ! listView || ! blockView ) { return; }
		listView.hidden  = ( view === 'block' );
		blockView.hidden = ( view !== 'block' );
		document.querySelectorAll( '.spbwc-view-btn' ).forEach( function ( b ) {
			var active = b.dataset.view === view;
			b.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
			b.classList.toggle( 'active', active );
		} );
		try { localStorage.setItem( STORAGE_KEY, view ); } catch ( e ) { /* storage unavailable */ }
	}

	// ── Boot ─────────────────────────────────────────────────────────
	document.addEventListener( 'DOMContentLoaded', function () {
		// Restore saved view preference (default: block/card)
		var saved = 'block';
		try { saved = localStorage.getItem( STORAGE_KEY ) || 'block'; } catch ( e ) { /* storage unavailable */ }
		setView( saved );

		// ── Stat cards → click to filter ─────────────────────────────
		document.querySelectorAll( '.spbwc-stat-card--clickable' ).forEach( function ( card ) {
			var activate = function () {
				state.status_filter = card.dataset.filter !== undefined ? card.dataset.filter : '';
				setView( 'block' );
				fetchList( true );
				var grid = document.getElementById( 'spbwc-block-view' );
				if ( grid ) { grid.scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
			};
			card.addEventListener( 'click', activate );
			card.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key || ' ' === e.key ) { e.preventDefault(); activate(); }
			} );
		} );

		// Bind initial card actions (server-rendered on first load)
		bindCardActions();

		// View toggle buttons (skip buttons that have no data-view, e.g. the select toggle)
		document.querySelectorAll( '.spbwc-view-btn[data-view]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () { setView( this.dataset.view ); } );
		} );

		// Tab clicks → AJAX (no page reload)
		document.querySelectorAll( '.spbwc-status-tab' ).forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				state.status_filter = this.dataset.filter !== undefined ? this.dataset.filter : '';
				setView( 'block' );
				fetchList( true );
			} );
		} );

		// ── Search — debounced 300 ms + clear button ──────────────────
		var search   = document.getElementById( 'spbwc-unified-search' );
		var clearBtn = document.getElementById( 'spbwc-search-clear' );

		function syncClearBtn() {
			if ( clearBtn ) { clearBtn.hidden = ! ( search && search.value.trim() ); }
		}

		if ( search ) {
			syncClearBtn(); // reflect any server-rendered initial value
			search.addEventListener( 'input', function () {
				clearTimeout( fetchTimer );
				state.s = this.value.trim();
				syncClearBtn();
				// Always switch to block view and fetch — list view cannot update
				// dynamically without a full page reload, so mirror the Enter key
				// behaviour: switch to block view + debounced AJAX search.
				fetchTimer = setTimeout( function () {
					setView( 'block' );
					fetchList( true );
				}, 300 );
			} );
			search.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' !== e.key ) { return; }
				e.preventDefault();
				clearTimeout( fetchTimer );
				state.s = this.value.trim();
				setView( 'block' );
				fetchList( true );
			} );
		}

		if ( clearBtn && search ) {
			clearBtn.addEventListener( 'click', function () {
				search.value = '';
				state.s      = '';
				syncClearBtn();
				search.focus();
				setView( 'block' );
				fetchList( true );
			} );
		}

		// ── Sort dropdown ─────────────────────────────────────────────
		var sortEl = document.getElementById( 'spbwc-sort-select' );

		if ( sortEl ) {
			sortEl.addEventListener( 'change', function () {
				var parts     = this.value.split( '-' );
				// Handle 'modified-DESC', 'title-ASC' — join tail in case value ever has extra '-'
				state.orderby = parts[0] || 'modified';
				state.order   = ( parts.slice( 1 ).join( '-' ) || 'DESC' ).toUpperCase();
				fetchList( true );
			} );
		}

		// ── Delete confirm modal buttons ──────────────────────────────
		var delCancel  = document.getElementById( 'spbwc-delete-cancel' );
		var delConfirm = document.getElementById( 'spbwc-delete-confirm' );
		var delOverlay = document.getElementById( 'spbwc-delete-modal' );

		if ( delCancel ) {
			delCancel.addEventListener( 'click', closeDeleteModal );
		}
		if ( delOverlay ) {
			// Click outside modal box closes it.
			delOverlay.addEventListener( 'click', function ( e ) {
				if ( e.target === delOverlay ) { closeDeleteModal(); }
			} );
		}
		if ( delConfirm ) {
			delConfirm.addEventListener( 'click', function () {
				if ( ! _deleteModalPending ) { return; }
				var cb = _deleteModalPending.onConfirm;
				closeDeleteModal();
				if ( cb ) { cb(); }
			} );
		}

		// ── Keyboard shortcuts ────────────────────────────────────────
		// N = new option | / = focus search | 1/2/3 = filter tabs
		// Skip when an input/textarea/select/contenteditable is focused.
		document.addEventListener( 'keydown', function ( e ) {
			var tag    = e.target.tagName.toLowerCase();
			var inText = ( 'input' === tag || 'textarea' === tag || 'select' === tag )
			           || e.target.isContentEditable;
			if ( inText || e.ctrlKey || e.metaKey || e.altKey ) { return; }

			var tabMap = { '1': '', '2': '1', '3': '0' };

			if ( 'n' === e.key || 'N' === e.key ) {
				e.preventDefault();
				window.location.href = '<?php echo esc_js( $link_create_option ); ?>';
			} else if ( '/' === e.key ) {
				e.preventDefault();
				var searchEl = document.getElementById( 'spbwc-unified-search' );
				if ( searchEl ) { searchEl.focus(); searchEl.select(); }
			} else if ( tabMap.hasOwnProperty( e.key ) ) {
				e.preventDefault();
				state.status_filter = tabMap[ e.key ];
				setView( 'block' );
				fetchList( true );
			} else if ( 'Escape' === e.key ) {
				closeDeleteModal();
			}
		} );

		// ── Pagination (event delegation — survives AJAX re-render) ───
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-spbwc-page]' );
			if ( ! btn || btn.classList.contains( 'is-disabled' ) ) { return; }
			state.paged = parseInt( btn.dataset.spbwcPage, 10 );
			fetchList( false );
			window.scrollTo( { top: 0, behavior: 'smooth' } );
		} );
	} );
}());
</script>
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
