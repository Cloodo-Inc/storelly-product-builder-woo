<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$link_create_option = add_query_arg(
	array(
		'action' => 'edit',
		'paged'  => 1,
		'id'     => 0,
	),
	admin_url( 'admin.php?page=' . SPBWC_PB_BUILDER_SLUG )
);

// ── Template variables ─────────────────────────────────────────────
$_nonce_block   = wp_create_nonce( 'spbwc_options_nonce' );        // For edit/copy links
$_nonce_list    = wp_create_nonce( 'spbwc_options_list_nonce' );   // For AJAX list operations
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$_page_slug = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : SPBWC_PB_BUILDER_SLUG;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$_status_filter = isset( $_REQUEST['status_filter'] ) && strlen( $_REQUEST['status_filter'] )
	? sanitize_text_field( wp_unslash( $_REQUEST['status_filter'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
		<div class="spbwc-stat-card">
			<span class="dashicons dashicons-tickets-alt spbwc-stat-card__icon" aria-hidden="true"></span>
			<div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $_status_counts['all'] ) ); ?></div>
			<div class="spbwc-stat-card__label"><?php esc_html_e( 'Total Options', 'storelly-product-builder-for-woocommerce' ); ?></div>
			<div class="spbwc-stat-card__sub"><?php esc_html_e( 'All pricing option groups', 'storelly-product-builder-for-woocommerce' ); ?></div>
		</div>
		<div class="spbwc-stat-card">
			<span class="dashicons dashicons-yes-alt spbwc-stat-card__icon" aria-hidden="true" style="color:var(--nbd-color-success)"></span>
			<div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $_status_counts['published'] ) ); ?></div>
			<div class="spbwc-stat-card__label"><?php esc_html_e( 'Published', 'storelly-product-builder-for-woocommerce' ); ?></div>
			<div class="spbwc-stat-card__sub"><?php esc_html_e( 'Active on your store', 'storelly-product-builder-for-woocommerce' ); ?></div>
		</div>
		<div class="spbwc-stat-card">
			<span class="dashicons dashicons-edit spbwc-stat-card__icon" aria-hidden="true"></span>
			<div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $_status_counts['draft'] ) ); ?></div>
			<div class="spbwc-stat-card__label"><?php esc_html_e( 'Drafts', 'storelly-product-builder-for-woocommerce' ); ?></div>
			<div class="spbwc-stat-card__sub"><?php esc_html_e( 'Not yet visible to customers', 'storelly-product-builder-for-woocommerce' ); ?></div>
		</div>
		<div class="spbwc-stat-card spbwc-stat-card--guide">
			<span class="dashicons dashicons-lightbulb spbwc-stat-card__icon" aria-hidden="true"></span>
			<div class="spbwc-stat-card__label"><?php esc_html_e( 'How to use', 'storelly-product-builder-for-woocommerce' ); ?></div>
			<ol class="spbwc-stat-card__steps">
				<li><?php esc_html_e( 'Create a new option group', 'storelly-product-builder-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Add pricing fields', 'storelly-product-builder-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Assign to products or categories', 'storelly-product-builder-for-woocommerce' ); ?></li>
			</ol>
		</div>
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
				       placeholder="<?php esc_attr_e( 'Search options\xe2\x80\xa6', 'storelly-product-builder-for-woocommerce' ); ?>"
				       aria-label="<?php esc_attr_e( 'Search options', 'storelly-product-builder-for-woocommerce' ); ?>">
			</div>

			<!-- Sort by -->
			<select id="spbwc-sort-select" class="spbwc-sort-select"
			        aria-label="<?php esc_attr_e( 'Sort by', 'storelly-product-builder-for-woocommerce' ); ?>">
				<option value="modified-DESC"><?php esc_html_e( 'Newest first', 'storelly-product-builder-for-woocommerce' ); ?></option>
				<option value="modified-ASC"><?php esc_html_e( 'Oldest first', 'storelly-product-builder-for-woocommerce' ); ?></option>
				<option value="title-ASC"><?php esc_html_e( 'Name A\xe2\x86\x92Z', 'storelly-product-builder-for-woocommerce' ); ?></option>
				<option value="title-DESC"><?php esc_html_e( 'Name Z\xe2\x86\x92A', 'storelly-product-builder-for-woocommerce' ); ?></option>
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
					$spbwc_thumb   = SPBWC_Storelly_PB_Util::spbwc_render_option_thumbnail( $spbwc_item, 88 );

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
				         data-option-id="<?php echo $spbwc_id_str; ?>"
				         data-published="<?php echo $spbwc_pub; ?>">
					<a href="<?php echo $spbwc_edit_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already esc_url'd above ?>"
					   class="spbwc-option-card__thumb spbwc-option-card__thumb--svg"
					   aria-label="<?php echo esc_attr( $spbwc_title ); ?>">
						<?php echo $spbwc_thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG escaped internally. ?>
					</a>
					<div class="spbwc-option-card__body">
						<div class="spbwc-option-card__header">
							<h3 class="spbwc-option-card__title">
								<a href="<?php echo $spbwc_edit_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already esc_url'd above ?>"><?php echo esc_html( $spbwc_title ); ?></a>
							</h3>
							<button type="button"
							        class="spbwc-publish-toggle<?php echo 1 === $spbwc_pub ? ' is-published' : ''; ?>"
							        data-spbwc-action="toggle-publish"
							        data-id="<?php echo $spbwc_id_str; ?>"
							        data-published="<?php echo $spbwc_pub; ?>"
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
							        data-id="<?php echo $spbwc_id_str; ?>"
							        title="<?php esc_attr_e( 'Duplicate', 'storelly-product-builder-for-woocommerce' ); ?>">
								<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
							</button>
							<button type="button"
							        class="spbwc-card-btn spbwc-card-btn--danger"
							        data-spbwc-action="trash"
							        data-id="<?php echo $spbwc_id_str; ?>"
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
					$_current_page_n,
					$_total_pages_n
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

</div><!-- .wrap -->

<!-- Toast notification -->
<div id="spbwc-toast" class="spbwc-toast" hidden role="status" aria-live="polite"></div>

<script>
(function () {
	'use strict';

	var AJAX_URL    = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var NONCE       = '<?php echo esc_js( $_nonce_list ); ?>';
	var STORAGE_KEY = 'spbwc_options_view';
	var SINGULAR    = '<?php echo esc_js( _x( 'option', 'singular item count', 'storelly-product-builder-for-woocommerce' ) ); ?>';
	var PLURAL      = '<?php echo esc_js( _x( 'options', 'plural item count', 'storelly-product-builder-for-woocommerce' ) ); ?>';
	var CONFIRM_DEL = '<?php echo esc_js( __( 'Delete this option? This cannot be undone.', 'storelly-product-builder-for-woocommerce' ) ); ?>';
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

	// ── Toast ─────────────────────────────────────────────────────────
	function showToast( msg, type ) {
		var toast = document.getElementById( 'spbwc-toast' );
		if ( ! toast ) { return; }
		toast.textContent = msg;
		toast.className   = 'spbwc-toast spbwc-toast--' + ( type || 'success' );
		toast.hidden      = false;
		clearTimeout( toast._timer );
		toast._timer = setTimeout( function () { toast.hidden = true; }, 3200 );
	}

	// ── Count label ───────────────────────────────────────────────────
	function countLabel( n ) {
		return n + ' ' + ( 1 === n ? SINGULAR : PLURAL );
	}

	// ── Fetch grid via AJAX ───────────────────────────────────────────
	function fetchList( resetPage ) {
		if ( resetPage ) { state.paged = 1; }

		var fd = new FormData();
		fd.append( 'action',        'spbwc_list_options_html' );
		fd.append( 'nonce',         NONCE );
		fd.append( 'paged',         state.paged );
		fd.append( 'status_filter', state.status_filter );
		fd.append( 's',             state.s );
		fd.append( 'orderby',       state.orderby );
		fd.append( 'order',         state.order );

		setLoading( true );

		fetch( AJAX_URL, { method: 'POST', credentials: 'same-origin', body: fd } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( ! res || ! res.success ) {
					showToast( ( res && res.data && res.data.msg ) || ERR_GENERIC, 'error' );
					return;
				}
				var d     = res.data;
				var inner = document.getElementById( 'spbwc-block-view-inner' );
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
			} )
			.catch( function () { showToast( ERR_GENERIC, 'error' ); } )
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
		// Trash
		document.querySelectorAll( '[data-spbwc-action="trash"]:not([data-bound])' ).forEach( function ( btn ) {
			btn.setAttribute( 'data-bound', '1' );
			btn.addEventListener( 'click', function () {
				// eslint-disable-next-line no-alert
				if ( ! window.confirm( CONFIRM_DEL ) ) { return; }
				var id   = this.dataset.id;
				var card = this.closest( '.spbwc-option-card' );
				if ( card ) {
					card.style.opacity       = '0.4';
					card.style.pointerEvents = 'none';
				}
				setLoading( true );
				doPost( 'spbwc_trash_option', { id: id }, function ( data ) {
					showToast( data.msg || 'Deleted', 'success' );
					updateTabCounts( data.counts );
					fetchList( false );
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

		// Bind initial card actions (server-rendered on first load)
		bindCardActions();

		// View toggle buttons
		document.querySelectorAll( '.spbwc-view-btn' ).forEach( function ( btn ) {
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

		// Search — debounced 300 ms, fires AJAX in card view
		var search = document.getElementById( 'spbwc-unified-search' );
		if ( search ) {
			search.addEventListener( 'input', function () {
				clearTimeout( fetchTimer );
				state.s = this.value.trim();
				var blockView = document.getElementById( 'spbwc-block-view' );
				if ( blockView && ! blockView.hidden ) {
					fetchTimer = setTimeout( function () { fetchList( true ); }, 300 );
				}
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

		// Sort dropdown
		var sortEl = document.getElementById( 'spbwc-sort-select' );
		if ( sortEl ) {
			sortEl.addEventListener( 'change', function () {
				var parts     = this.value.split( '-' );
				state.orderby = parts[0] || 'modified';
				state.order   = parts[1] || 'DESC';
				fetchList( true );
			} );
		}

		// Pagination buttons (event delegation — works after AJAX re-render too)
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
