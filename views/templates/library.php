<?php
/**
 * Template Library — grid view + Preview & Apply dialogs.
 *
 * Receives from caller:
 *   array $templates   Catalog templates (already normalized).
 *   array $categories  Catalog categories map.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view template; variables here are local to this included view, not plugin globals.

/**
 * Catalog templates (normalized) passed from SPBWC_Template_Library_Admin::render_page().
 *
 * @var array
 */
// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- passed via include.
/**
 * Catalog categories map passed from SPBWC_Template_Library_Admin::render_page().
 *
 * @var array
 */
// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- passed via include.
$catalog = SPBWC_Template_Catalog::instance();
?>
<div class="wrap spbwc-template-library">
	<header class="spbwc-page-hero">
		<div class="spbwc-page-hero__grid">
			<div class="spbwc-page-hero__body">
				<div class="spbwc-page-hero__eyebrow">
					<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
					<?php esc_html_e( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ); ?>
				</div>
				<h1 class="spbwc-page-hero__title">
					<span class="dashicons dashicons-layout" aria-hidden="true"></span>
					<?php esc_html_e( 'Template Library', 'storelly-product-builder-for-woocommerce' ); ?>
				</h1>
				<p class="spbwc-page-hero__subtitle">
					<?php esc_html_e( 'Pick a pre-built printing option, apply it to your products or categories, then customize freely. Global templates stay read-only — your applied copy is yours to edit.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
			</div>
			<div class="spbwc-page-hero__actions">
				<a href="https://storelly.com/docs/templates" target="_blank" rel="noopener noreferrer"
					class="spbwc-cta-btn spbwc-cta-btn--ghost">
					<span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Template Docs', 'storelly-product-builder-for-woocommerce' ); ?>
				</a>
			</div>
		</div>
	</header>

	<?php if ( empty( $templates ) ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'No bundled templates found. Make sure storage/print-templates/catalog.json ships with the plugin.', 'storelly-product-builder-for-woocommerce' ); ?></p>
		</div>
	<?php else : ?>

		<!-- ── Stats row ─────────────────────────────────────────────── -->
		<?php
		$spbwc_total_tpl = count( $templates );
		$spbwc_total_cat = count( $categories );
		$spbwc_cat_labels = array();
		$spbwc_shown = 0;
		foreach ( $categories as $spbwc_cat_id_s => $spbwc_unused ) {
			if ( $spbwc_shown >= 4 ) break;
			$spbwc_cat_labels[] = $catalog->get_category_label( $spbwc_cat_id_s );
			$spbwc_shown++;
		}
		$spbwc_cats_str = implode( ' · ', $spbwc_cat_labels );
		if ( $spbwc_total_cat > 4 ) {
			$spbwc_cats_str .= ' …';
		}
		?>
		<div class="spbwc-tl-stats-row">
			<div class="spbwc-tl-stat-card">
				<span class="spbwc-tl-stat-card__icon dashicons dashicons-layout" aria-hidden="true"></span>
				<div class="spbwc-tl-stat-card__value"><?php echo esc_html( $spbwc_total_tpl ); ?></div>
				<div class="spbwc-tl-stat-card__label"><?php esc_html_e( 'Ready-to-use templates', 'storelly-product-builder-for-woocommerce' ); ?></div>
				<div class="spbwc-tl-stat-card__sub"><?php esc_html_e( 'Pricing schemes &amp; product option sets', 'storelly-product-builder-for-woocommerce' ); ?></div>
			</div>
			<div class="spbwc-tl-stat-card">
				<span class="spbwc-tl-stat-card__icon dashicons dashicons-category" aria-hidden="true"></span>
				<div class="spbwc-tl-stat-card__value"><?php echo esc_html( $spbwc_total_cat ); ?></div>
				<div class="spbwc-tl-stat-card__label"><?php esc_html_e( 'Product categories', 'storelly-product-builder-for-woocommerce' ); ?></div>
				<div class="spbwc-tl-stat-card__sub"><?php echo esc_html( $spbwc_cats_str ); ?></div>
			</div>
			<div class="spbwc-tl-stat-card spbwc-tl-stat-card--guide">
				<span class="spbwc-tl-stat-card__icon dashicons dashicons-lightbulb" aria-hidden="true"></span>
				<div class="spbwc-tl-stat-card__label"><?php esc_html_e( 'How to use', 'storelly-product-builder-for-woocommerce' ); ?></div>
				<ol class="spbwc-tl-stat-card__steps">
					<li><?php esc_html_e( 'Browse and preview templates', 'storelly-product-builder-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Click Apply → choose products', 'storelly-product-builder-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Edit your copy freely', 'storelly-product-builder-for-woocommerce' ); ?></li>
				</ol>
			</div>
		</div>

		<div class="spbwc-tl-toolbar">
			<div class="spbwc-tl-toolbar__search">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<input type="search"
					id="spbwc-tl-search"
					placeholder="<?php esc_attr_e( 'Search templates…', 'storelly-product-builder-for-woocommerce' ); ?>" />
			</div>

			<select id="spbwc-tl-category-filter" class="spbwc-tl-toolbar__filter">
				<option value=""><?php esc_html_e( 'All categories', 'storelly-product-builder-for-woocommerce' ); ?></option>
				<?php foreach ( $categories as $spbwc_cat_id => $spbwc_cat_labels ) : ?>
					<option value="<?php echo esc_attr( $spbwc_cat_id ); ?>">
						<?php echo esc_html( $catalog->get_category_label( $spbwc_cat_id ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<span class="spbwc-tl-count" id="spbwc-tl-count">
				<?php
				/* translators: %d: number of templates */
				echo esc_html( sprintf( _n( '%d template', '%d templates', count( $templates ), 'storelly-product-builder-for-woocommerce' ), count( $templates ) ) );
				?>
			</span>
		</div>

		<div class="spbwc-tl-grid" id="spbwc-tl-grid">
			<?php
			foreach ( $templates as $tpl ) :
				$name         = $catalog->get_display_name( $tpl );
				$spbwc_cat_id = isset( $tpl['category'] ) ? $tpl['category'] : '';
				$cat_lb       = $catalog->get_category_label( $spbwc_cat_id );
				?>
				<article class="spbwc-tl-card"
					data-slug="<?php echo esc_attr( $tpl['slug'] ); ?>"
					data-category="<?php echo esc_attr( $spbwc_cat_id ); ?>"
					data-name="<?php echo esc_attr( strtolower( $name ) ); ?>">
					<div class="spbwc-tl-card-thumb spbwc-tl-card-thumb--<?php echo esc_attr( $spbwc_cat_id ); ?>">
						<?php if ( ! empty( $tpl['thumbnail'] ) ) : ?>
							<img class="spbwc-tl-card-thumb__img"
								src="<?php echo esc_url( SPBWC_PB_PLUGIN_URL . 'storage/print-templates/' . $tpl['thumbnail'] ); ?>"
								alt="<?php echo esc_attr( $name ); ?>"
								width="400" height="240"
								loading="lazy" />
						<?php else : ?>
							<span class="dashicons dashicons-art" aria-hidden="true"></span>
						<?php endif; ?>
						<span class="spbwc-tl-card-thumb__cat"><?php echo esc_html( $cat_lb ); ?></span>
					</div>
					<div class="spbwc-tl-card-body">
						<h3 class="spbwc-tl-card-title"><?php echo esc_html( $name ); ?></h3>
						<?php
					$spbwc_is_scheme  = ( 'fixed' === ( $tpl['pricing_method'] ?? 'fixed' ) );
					$spbwc_type_label = $spbwc_is_scheme
						? esc_html__( 'Pricing Scheme', 'storelly-product-builder-for-woocommerce' )
						: esc_html__( 'Product Options', 'storelly-product-builder-for-woocommerce' );
					$spbwc_type_cls   = $spbwc_is_scheme ? 'spbwc-tl-badge--scheme' : 'spbwc-tl-badge--options';
					?>
					<div class="spbwc-tl-card-meta">
							<span class="spbwc-tl-badge spbwc-tl-badge--info">
								<?php
								/* translators: %d: number of fields in template */
								echo esc_html( sprintf( _n( '%d field', '%d fields', (int) $tpl['field_count'], 'storelly-product-builder-for-woocommerce' ), (int) $tpl['field_count'] ) );
								?>
							</span>
							<span class="spbwc-tl-badge <?php echo esc_attr( $spbwc_type_cls ); ?>">
								<?php echo wp_kses_post( $spbwc_type_label ); // already escaped above ?>
							</span>
						</div>
						<?php if ( ! empty( $tpl['description'] ) ) : ?>
							<p class="spbwc-tl-card-desc"><?php echo esc_html( $tpl['description'] ); ?></p>
						<?php endif; ?>
					</div>
					<div class="spbwc-tl-card-actions">
						<button type="button" class="button button-secondary spbwc-tl-preview"
							data-slug="<?php echo esc_attr( $tpl['slug'] ); ?>"
							data-name="<?php echo esc_attr( $name ); ?>">
							<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
							<?php esc_html_e( 'Preview', 'storelly-product-builder-for-woocommerce' ); ?>
						</button>
						<button type="button" class="button button-primary spbwc-tl-apply"
							data-slug="<?php echo esc_attr( $tpl['slug'] ); ?>"
							data-name="<?php echo esc_attr( $name ); ?>">
							<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
							<?php esc_html_e( 'Apply', 'storelly-product-builder-for-woocommerce' ); ?>
						</button>
					</div>
				</article>
			<?php endforeach; ?>
		</div>

		<div id="spbwc-tl-empty-search" class="spbwc-tl-empty-search" hidden>
			<span class="dashicons dashicons-search" aria-hidden="true"></span>
			<p><?php esc_html_e( 'No templates match your search.', 'storelly-product-builder-for-woocommerce' ); ?></p>
		</div>

		<!-- Backdrop overlay (used with el.show() — see template-library.js) -->
		<div id="spbwc-tl-backdrop" class="spbwc-tl-backdrop" aria-hidden="true"></div>

		<!-- ─────────────────  PREVIEW DIALOG  ───────────────── -->
		<dialog id="spbwc-tl-preview-dialog" class="spbwc-tl-dialog spbwc-tl-dialog--wide">
			<div class="spbwc-tl-dialog-form">
				<header class="spbwc-tl-dialog-header">
					<div class="spbwc-tl-dialog-header__title">
						<h2 id="spbwc-tl-preview-title"><?php esc_html_e( 'Template Preview', 'storelly-product-builder-for-woocommerce' ); ?></h2>
						<span class="spbwc-tl-dialog-header__sub" id="spbwc-tl-preview-subtitle"></span>
					</div>
					<nav class="spbwc-tl-tabs" role="tablist">
						<button type="button" class="spbwc-tl-tab spbwc-tl-tab--active" data-tab="live"><?php esc_html_e( 'Preview', 'storelly-product-builder-for-woocommerce' ); ?></button>
						<button type="button" class="spbwc-tl-tab" data-tab="fields"><?php esc_html_e( 'Fields', 'storelly-product-builder-for-woocommerce' ); ?></button>
						<button type="button" class="spbwc-tl-tab" data-tab="about"><?php esc_html_e( 'About', 'storelly-product-builder-for-woocommerce' ); ?></button>
					</nav>
					<button type="button" class="spbwc-tl-dialog-close" data-close="preview" aria-label="<?php esc_attr_e( 'Close', 'storelly-product-builder-for-woocommerce' ); ?>">&times;</button>
				</header>
				<div class="spbwc-tl-dialog-body spbwc-tl-preview-body" id="spbwc-tl-preview-body">
					<div class="spbwc-tl-tabpanel spbwc-tl-tabpanel--active" data-tabpanel="live" id="spbwc-tl-preview-live">
						<div class="spbwc-tl-preview-toolbar">
							<div class="spbwc-tl-vp" role="group" aria-label="<?php esc_attr_e( 'Preview viewport', 'storelly-product-builder-for-woocommerce' ); ?>">
								<button type="button" class="spbwc-tl-vp__btn spbwc-tl-vp__btn--active" data-pv="desktop"><?php esc_html_e( 'Desktop', 'storelly-product-builder-for-woocommerce' ); ?></button>
								<button type="button" class="spbwc-tl-vp__btn" data-pv="tablet"><?php esc_html_e( 'Tablet', 'storelly-product-builder-for-woocommerce' ); ?></button>
								<button type="button" class="spbwc-tl-vp__btn" data-pv="mobile"><?php esc_html_e( 'Mobile', 'storelly-product-builder-for-woocommerce' ); ?></button>
							</div>
							<label class="spbwc-tl-baseprice">
								<span class="spbwc-tl-baseprice__label"><?php esc_html_e( 'Sample base price', 'storelly-product-builder-for-woocommerce' ); ?></span>
								<span class="spbwc-tl-baseprice__field">
									<span class="spbwc-tl-baseprice__symbol" id="spbwc-tl-baseprice-symbol" aria-hidden="true">$</span>
									<input type="number" id="spbwc-tl-baseprice" class="spbwc-tl-baseprice__input" min="0" step="0.01" value="0" inputmode="decimal" />
								</span>
							</label>
						</div>
						<div class="spbwc-tl-preview-stage" data-viewport="desktop">
							<div class="spbwc-tl-preview-frame-wrap" id="spbwc-tl-preview-frame-wrap">
								<iframe id="spbwc-tl-preview-frame" class="spbwc-tl-preview-frame" title="<?php esc_attr_e( 'Live template preview', 'storelly-product-builder-for-woocommerce' ); ?>"></iframe>
								<div class="spbwc-tl-preview-frame-loading" id="spbwc-tl-preview-frame-loading">
									<span class="spinner is-active" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Loading preview…', 'storelly-product-builder-for-woocommerce' ); ?></span>
								</div>
								<div class="spbwc-tl-preview-frame-updating" id="spbwc-tl-preview-frame-updating" hidden aria-live="polite">
									<span class="spinner is-active" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Updating…', 'storelly-product-builder-for-woocommerce' ); ?></span>
								</div>
								<div class="spbwc-tl-preview-frame-error" id="spbwc-tl-preview-frame-error" hidden role="alert">
									<span class="dashicons dashicons-warning" aria-hidden="true"></span>
									<p><?php esc_html_e( 'Couldn’t load the preview.', 'storelly-product-builder-for-woocommerce' ); ?></p>
									<button type="button" class="button" id="spbwc-tl-preview-frame-retry"><?php esc_html_e( 'Retry', 'storelly-product-builder-for-woocommerce' ); ?></button>
								</div>
							</div>
							<p class="spbwc-tl-preview-hint">
								<?php esc_html_e( 'This is the exact storefront UI buyers see — change the sample base price to preview the live total.', 'storelly-product-builder-for-woocommerce' ); ?>
							</p>
						</div>
					</div>
					<div class="spbwc-tl-tabpanel" data-tabpanel="fields" id="spbwc-tl-preview-fields"></div>
					<div class="spbwc-tl-tabpanel" data-tabpanel="about" id="spbwc-tl-preview-about"></div>
				</div>
				<footer class="spbwc-tl-dialog-footer">
					<button type="button" class="button" data-close="preview"><?php esc_html_e( 'Close', 'storelly-product-builder-for-woocommerce' ); ?></button>
					<button type="button" class="button button-primary" id="spbwc-tl-preview-apply-cta">
						<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
						<?php esc_html_e( 'Apply this template', 'storelly-product-builder-for-woocommerce' ); ?>
					</button>
				</footer>
			</div>
		</dialog>

		<!-- ─────────────────  APPLY DIALOG  ───────────────── -->
		<dialog id="spbwc-tl-apply-dialog" class="spbwc-tl-dialog spbwc-tl-dialog--apply">
			<form id="spbwc-tl-apply-form" class="spbwc-tl-dialog-form">
				<header class="spbwc-tl-dialog-header">
					<div class="spbwc-tl-dialog-header__title">
						<h2><?php esc_html_e( 'Apply template', 'storelly-product-builder-for-woocommerce' ); ?></h2>
						<span class="spbwc-tl-dialog-header__sub" id="spbwc-tl-apply-subtitle"></span>
					</div>
					<button type="button" class="spbwc-tl-dialog-close" data-close="apply"
						aria-label="<?php esc_attr_e( 'Close', 'storelly-product-builder-for-woocommerce' ); ?>">&times;</button>
				</header>

				<div class="spbwc-tl-dialog-body">

					<!-- ── Form fields (swapped out when success state shows) ── -->
					<div id="spbwc-tl-apply-body-content">
						<input type="hidden" name="slug" id="spbwc-tl-apply-slug" />

						<!-- Global-template info note -->
						<div class="spbwc-tl-info-box spbwc-tl-info-box--note">
							<span class="dashicons dashicons-info-outline spbwc-tl-info-box__icon" aria-hidden="true"></span>
							<div class="spbwc-tl-info-box__body">
								<strong><?php esc_html_e( 'Global template — you get your own editable copy', 'storelly-product-builder-for-woocommerce' ); ?></strong>
								<p><?php esc_html_e( 'Storelly creates a private copy of this template on your store. The original stays read-only and shared. You can rename fields, adjust prices, and change which products or categories use it at any time from the Pricing Options screen.', 'storelly-product-builder-for-woocommerce' ); ?></p>
							</div>
						</div>

						<div class="spbwc-tl-field">
							<label for="spbwc-tl-apply-title" class="spbwc-tl-field__label">
								<?php esc_html_e( 'Name for your copy', 'storelly-product-builder-for-woocommerce' ); ?>
							</label>
							<input type="text"
								id="spbwc-tl-apply-title"
								name="title"
								class="spbwc-tl-field__input"
								required />
							<p class="spbwc-tl-field__hint">
								<?php esc_html_e( 'Shown in your Pricing Options list — you can rename it any time.', 'storelly-product-builder-for-woocommerce' ); ?>
							</p>
						</div>

						<div class="spbwc-tl-field">
							<span class="spbwc-tl-field__label">
								<?php esc_html_e( 'Apply to', 'storelly-product-builder-for-woocommerce' ); ?>
								<span class="spbwc-tl-field__label-optional"><?php esc_html_e( '(optional)', 'storelly-product-builder-for-woocommerce' ); ?></span>
							</span>
							<div class="spbwc-tl-radio-cards">
								<label class="spbwc-tl-radio-card spbwc-tl-radio-card--active">
									<input type="radio" name="apply_for" value="p" checked>
									<span class="spbwc-tl-radio-card__icon dashicons dashicons-products"></span>
									<span class="spbwc-tl-radio-card__title"><?php esc_html_e( 'Specific products', 'storelly-product-builder-for-woocommerce' ); ?></span>
									<span class="spbwc-tl-radio-card__hint"><?php esc_html_e( 'Only products without an existing Pricing Option', 'storelly-product-builder-for-woocommerce' ); ?></span>
								</label>
								<label class="spbwc-tl-radio-card">
									<input type="radio" name="apply_for" value="c">
									<span class="spbwc-tl-radio-card__icon dashicons dashicons-category"></span>
									<span class="spbwc-tl-radio-card__title"><?php esc_html_e( 'Product categories', 'storelly-product-builder-for-woocommerce' ); ?></span>
									<span class="spbwc-tl-radio-card__hint"><?php esc_html_e( 'Applied to every product in the selected categories', 'storelly-product-builder-for-woocommerce' ); ?></span>
								</label>
							</div>
							<p class="spbwc-tl-field__hint" style="margin-top:8px;">
								<span class="dashicons dashicons-info" style="font-size:13px;width:13px;height:13px;vertical-align:middle;color:var(--st-brand);" aria-hidden="true"></span>
								<?php esc_html_e( 'Selection is optional — you can skip and assign products or categories later from Pricing Options.', 'storelly-product-builder-for-woocommerce' ); ?>
							</p>
						</div>

						<div class="spbwc-tl-field spbwc-tl-scope spbwc-tl-scope--p">
							<label for="spbwc-tl-products" class="spbwc-tl-field__label">
								<?php esc_html_e( 'Products', 'storelly-product-builder-for-woocommerce' ); ?>
								<span class="spbwc-tl-field__label-badge" id="spbwc-tl-products-count" hidden></span>
							</label>
							<select id="spbwc-tl-products"
								name="scope_ids_p[]"
								multiple="multiple"
								class="spbwc-tl-select wc-product-search"
								style="width:100%;"
								data-placeholder="<?php esc_attr_e( 'Type a product name or SKU to search…', 'storelly-product-builder-for-woocommerce' ); ?>"
								data-action="woocommerce_json_search_products_and_variations"></select>
							<p class="spbwc-tl-field__hint">
								<?php esc_html_e( 'Search and select as many products as you like. Only products without an existing Pricing Option are recommended.', 'storelly-product-builder-for-woocommerce' ); ?>
							</p>
						</div>

						<div class="spbwc-tl-field spbwc-tl-scope spbwc-tl-scope--c" hidden>
							<label for="spbwc-tl-categories" class="spbwc-tl-field__label">
								<?php esc_html_e( 'Categories', 'storelly-product-builder-for-woocommerce' ); ?>
							</label>
							<select id="spbwc-tl-categories"
								name="scope_ids_c[]"
								multiple="multiple"
								class="spbwc-tl-select"
								style="width:100%;">
								<?php
								$terms = get_terms(
									array(
										'taxonomy'   => 'product_cat',
										'hide_empty' => false,
									)
								);
								if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
									foreach ( $terms as $wc_term ) {
										printf(
											'<option value="%d">%s</option>',
											(int) $wc_term->term_id,
											esc_html( $wc_term->name )
										);
									}
								}
								?>
							</select>
							<p class="spbwc-tl-field__hint">
								<?php esc_html_e( 'Select one or more categories — the template applies to every product within them.', 'storelly-product-builder-for-woocommerce' ); ?>
							</p>
						</div>

						<div class="spbwc-tl-apply-error notice notice-error inline" id="spbwc-tl-apply-error" hidden>
							<p></p>
						</div>
					</div><!-- #spbwc-tl-apply-body-content -->

					<!-- ── Success state (visible after apply while browser redirects) ── -->
					<div id="spbwc-tl-apply-success" class="spbwc-tl-apply-success-state" hidden aria-live="assertive">
						<span class="dashicons dashicons-yes-alt spbwc-tl-apply-success-state__icon" aria-hidden="true"></span>
						<p class="spbwc-tl-apply-success-state__title">
							<?php esc_html_e( 'Template applied!', 'storelly-product-builder-for-woocommerce' ); ?>
						</p>
						<p class="spbwc-tl-apply-success-state__sub">
							<?php esc_html_e( 'Opening the editor…', 'storelly-product-builder-for-woocommerce' ); ?>
						</p>
						<span class="spinner is-active" style="float:none;margin:4px 0 0;" aria-hidden="true"></span>
					</div>

				</div><!-- .spbwc-tl-dialog-body -->

				<footer class="spbwc-tl-dialog-footer" id="spbwc-tl-apply-footer">
					<button type="button" class="button" data-close="apply">
						<?php esc_html_e( 'Cancel', 'storelly-product-builder-for-woocommerce' ); ?>
					</button>
					<button type="submit" class="button button-primary" id="spbwc-tl-apply-submit">
						<span class="dashicons dashicons-edit" aria-hidden="true"></span>
						<?php esc_html_e( 'Apply &amp; Edit', 'storelly-product-builder-for-woocommerce' ); ?>
					</button>
				</footer>
			</form>
		</dialog>
	<?php endif; ?>
</div>
