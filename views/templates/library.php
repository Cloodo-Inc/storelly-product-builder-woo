<?php
/**
 * Template Library — grid view.
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

/** @var array $templates */
/** @var array $categories */
$catalog = SPBWC_Template_Catalog::instance();
?>
<div class="wrap spbwc-template-library">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Template Library', 'storelly-product-builder-for-woocommerce' ); ?>
	</h1>
	<p class="spbwc-tl-subtitle">
		<?php esc_html_e( 'Pick a pre-built printing option, apply it to your products or categories, then customize freely. Global templates here stay read-only.', 'storelly-product-builder-for-woocommerce' ); ?>
	</p>

	<?php if ( empty( $templates ) ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'No bundled templates found. Make sure storage/print-templates/catalog.json ships with the plugin.', 'storelly-product-builder-for-woocommerce' ); ?></p>
		</div>
	<?php else : ?>
		<div class="spbwc-tl-toolbar">
			<input type="search"
				id="spbwc-tl-search"
				class="regular-text"
				placeholder="<?php esc_attr_e( 'Search templates…', 'storelly-product-builder-for-woocommerce' ); ?>" />

			<select id="spbwc-tl-category-filter">
				<option value=""><?php esc_html_e( 'All categories', 'storelly-product-builder-for-woocommerce' ); ?></option>
				<?php foreach ( $categories as $cat_id => $labels ) : ?>
					<option value="<?php echo esc_attr( $cat_id ); ?>">
						<?php echo esc_html( $catalog->get_category_label( $cat_id ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<span class="spbwc-tl-count">
				<?php
				/* translators: %d: number of templates */
				echo esc_html( sprintf( _n( '%d template', '%d templates', count( $templates ), 'storelly-product-builder-for-woocommerce' ), count( $templates ) ) );
				?>
			</span>
		</div>

		<div class="spbwc-tl-grid" id="spbwc-tl-grid">
			<?php foreach ( $templates as $tpl ) :
				$name   = $catalog->get_display_name( $tpl );
				$cat_id = isset( $tpl['category'] ) ? $tpl['category'] : '';
			?>
				<article class="spbwc-tl-card"
					data-slug="<?php echo esc_attr( $tpl['slug'] ); ?>"
					data-category="<?php echo esc_attr( $cat_id ); ?>"
					data-name="<?php echo esc_attr( strtolower( $name ) ); ?>">
					<div class="spbwc-tl-card-thumb">
						<span class="dashicons dashicons-art" aria-hidden="true"></span>
					</div>
					<div class="spbwc-tl-card-body">
						<h3 class="spbwc-tl-card-title"><?php echo esc_html( $name ); ?></h3>
						<div class="spbwc-tl-card-meta">
							<span class="spbwc-tl-badge spbwc-tl-badge--category">
								<?php echo esc_html( $catalog->get_category_label( $cat_id ) ); ?>
							</span>
							<span class="spbwc-tl-badge spbwc-tl-badge--info">
								<?php
								/* translators: %d: number of fields in template */
								echo esc_html( sprintf( _n( '%d field', '%d fields', (int) $tpl['field_count'], 'storelly-product-builder-for-woocommerce' ), (int) $tpl['field_count'] ) );
								?>
							</span>
							<span class="spbwc-tl-badge spbwc-tl-badge--neutral">
								<?php echo esc_html( str_replace( '_', ' ', $tpl['pricing_method'] ) ); ?>
							</span>
						</div>
						<?php if ( ! empty( $tpl['description'] ) ) : ?>
							<p class="spbwc-tl-card-desc"><?php echo esc_html( $tpl['description'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $tpl['pricing_source'] ) ) : ?>
							<p class="spbwc-tl-card-note">
								<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
								<?php echo esc_html( $tpl['pricing_source'] ); ?>
							</p>
						<?php endif; ?>
					</div>
					<div class="spbwc-tl-card-actions">
						<button type="button" class="button button-secondary spbwc-tl-preview"
							data-slug="<?php echo esc_attr( $tpl['slug'] ); ?>">
							<?php esc_html_e( 'Preview', 'storelly-product-builder-for-woocommerce' ); ?>
						</button>
						<button type="button" class="button button-primary spbwc-tl-apply"
							data-slug="<?php echo esc_attr( $tpl['slug'] ); ?>"
							data-name="<?php echo esc_attr( $name ); ?>">
							<?php esc_html_e( 'Apply', 'storelly-product-builder-for-woocommerce' ); ?>
						</button>
					</div>
				</article>
			<?php endforeach; ?>
		</div>

		<dialog id="spbwc-tl-preview-dialog" class="spbwc-tl-dialog">
			<form method="dialog" class="spbwc-tl-dialog-form">
				<header>
					<h2 id="spbwc-tl-preview-title"></h2>
					<button type="submit" class="spbwc-tl-dialog-close" aria-label="<?php esc_attr_e( 'Close', 'storelly-product-builder-for-woocommerce' ); ?>">&times;</button>
				</header>
				<div id="spbwc-tl-preview-body" class="spbwc-tl-dialog-body"></div>
			</form>
		</dialog>

		<dialog id="spbwc-tl-apply-dialog" class="spbwc-tl-dialog">
			<form id="spbwc-tl-apply-form" class="spbwc-tl-dialog-form">
				<header>
					<h2><?php esc_html_e( 'Apply template', 'storelly-product-builder-for-woocommerce' ); ?></h2>
					<button type="button" class="spbwc-tl-dialog-close" data-close="apply" aria-label="<?php esc_attr_e( 'Close', 'storelly-product-builder-for-woocommerce' ); ?>">&times;</button>
				</header>
				<div class="spbwc-tl-dialog-body">
					<input type="hidden" name="slug" id="spbwc-tl-apply-slug" />

					<p>
						<label for="spbwc-tl-apply-title"><?php esc_html_e( 'Option title', 'storelly-product-builder-for-woocommerce' ); ?></label>
						<input type="text" id="spbwc-tl-apply-title" name="title" class="regular-text" />
					</p>

					<fieldset class="spbwc-tl-fieldset">
						<legend><?php esc_html_e( 'Apply to', 'storelly-product-builder-for-woocommerce' ); ?></legend>
						<label><input type="radio" name="apply_for" value="p" checked> <?php esc_html_e( 'Specific products', 'storelly-product-builder-for-woocommerce' ); ?></label>
						<label><input type="radio" name="apply_for" value="c"> <?php esc_html_e( 'Product categories', 'storelly-product-builder-for-woocommerce' ); ?></label>
					</fieldset>

					<p class="spbwc-tl-scope spbwc-tl-scope--p">
						<label for="spbwc-tl-products"><?php esc_html_e( 'Products', 'storelly-product-builder-for-woocommerce' ); ?></label>
						<select id="spbwc-tl-products" name="scope_ids_p[]" multiple="multiple" class="wc-product-search" style="width:100%;"
							data-placeholder="<?php esc_attr_e( 'Search for a product…', 'storelly-product-builder-for-woocommerce' ); ?>"
							data-action="woocommerce_json_search_products_and_variations"></select>
					</p>

					<p class="spbwc-tl-scope spbwc-tl-scope--c" style="display:none;">
						<label for="spbwc-tl-categories"><?php esc_html_e( 'Categories', 'storelly-product-builder-for-woocommerce' ); ?></label>
						<select id="spbwc-tl-categories" name="scope_ids_c[]" multiple="multiple" class="spbwc-tl-cat-select" style="width:100%;">
							<?php
							$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
							if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
								foreach ( $terms as $term ) {
									printf(
										'<option value="%d">%s</option>',
										(int) $term->term_id,
										esc_html( $term->name )
									);
								}
							}
							?>
						</select>
					</p>
				</div>
				<footer class="spbwc-tl-dialog-footer">
					<button type="button" class="button" data-close="apply"><?php esc_html_e( 'Cancel', 'storelly-product-builder-for-woocommerce' ); ?></button>
					<button type="submit" class="button button-primary" id="spbwc-tl-apply-submit"><?php esc_html_e( 'Apply', 'storelly-product-builder-for-woocommerce' ); ?></button>
				</footer>
				<p class="spbwc-tl-apply-error" id="spbwc-tl-apply-error" style="display:none;"></p>
			</form>
		</dialog>
	<?php endif; ?>
</div>
