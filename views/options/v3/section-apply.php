<?php
/**
 * v3 — Apply to products / categories section.
 *
 * Picks where this Pricing Option attaches: specific products (default)
 * or every product in chosen categories. Reuses `wc-product-search` and
 * `wc-enhanced-select` (already registered by WooCommerce) for the
 * search-as-you-type pickers. Same form names as classic.
 *
 * @package Storelly_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$apply_for    = isset( $options['apply_for'] ) ? $options['apply_for'] : 'p';
$applied_cats = isset( $options['product_cats'] ) && is_array( $options['product_cats'] )
    ? array_filter( array_map( 'intval', $options['product_cats'] ) )
    : array();
$applied_pids = isset( $options['product_ids'] ) && is_array( $options['product_ids'] )
    ? array_filter( array_map( 'intval', $options['product_ids'] ) )
    : array();
$applied_count = count( $applied_pids );
$applied_cat_count = count( $applied_cats );
?>
<div class="v2-card spbwc-po-v3__apply-card">
    <div class="v2-card__head v2-card__head--brand">
        <h2 class="v2-card__title">
            <span class="v2-card__title-icon">📦</span>
            <?php esc_html_e( 'Apply this option to…', 'storelly-product-builder-for-woocommerce' ); ?>
        </h2>
        <span class="spbwc-po-v3__apply-count">
            <span ng-show="options.apply_for !== 'c'">
                <strong><?php echo absint( $applied_count ); ?></strong>
                <?php
                echo esc_html( _n( 'product', 'products', $applied_count, 'storelly-product-builder-for-woocommerce' ) );
                ?>
            </span>
            <span ng-show="options.apply_for === 'c'">
                <strong><?php echo absint( $applied_cat_count ); ?></strong>
                <?php
                echo esc_html( _n( 'category', 'categories', $applied_cat_count, 'storelly-product-builder-for-woocommerce' ) );
                ?>
            </span>
        </span>
    </div>
    <div class="v2-card__body">
        <?php /* Selection-mode segmented control. The hidden input is the
               actual POSTed value; the segmented buttons toggle it via
               click + ng-class. Classic JS also uses
               `data-spbwc-apply-mode` so its show/hide logic still works. */ ?>
        <div class="v2-form-row">
            <label class="v2-form-row__label">
                <?php esc_html_e( 'Selection mode', 'storelly-product-builder-for-woocommerce' ); ?>
            </label>
            <p class="v2-form-row__help">
                <?php esc_html_e( 'Pick specific products one-by-one, or apply automatically to every product in chosen categories.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
            <div class="v2-segmented" role="radiogroup" aria-label="<?php esc_attr_e( 'Selection mode', 'storelly-product-builder-for-woocommerce' ); ?>" data-spbwc-apply-mode>
                <button type="button" class="v2-segmented__btn"
                        ng-class="{'is-active': options.apply_for !== 'c'}"
                        ng-click="options.apply_for = 'p'"
                        data-apply-mode="p">
                    <span class="dashicons dashicons-products" aria-hidden="true"></span>
                    <?php esc_html_e( 'Specific products', 'storelly-product-builder-for-woocommerce' ); ?>
                </button>
                <button type="button" class="v2-segmented__btn"
                        ng-class="{'is-active': options.apply_for === 'c'}"
                        ng-click="options.apply_for = 'c'"
                        data-apply-mode="c">
                    <span class="dashicons dashicons-category" aria-hidden="true"></span>
                    <?php esc_html_e( 'Product categories', 'storelly-product-builder-for-woocommerce' ); ?>
                </button>
            </div>
            <input type="hidden" name="apply_for" id="apply_for"
                   value="<?php echo esc_attr( $apply_for ); ?>"
                   ng-value="options.apply_for" />
        </div>

        <!-- Products picker — visible when mode = p -->
        <div class="v2-form-row spbwc-po-v3__apply-picker"
             id="nbo-products-wrap"
             data-spbwc-apply-products
             ng-show="options.apply_for !== 'c'"
             <?php echo $apply_for === 'c' ? 'hidden' : ''; ?>>
            <label class="v2-form-row__label" for="product_ids">
                <?php esc_html_e( 'Select products', 'storelly-product-builder-for-woocommerce' ); ?>
            </label>
            <p class="v2-form-row__help">
                <?php esc_html_e( 'Search by product name. The option will appear on every selected product.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
            <select name="product_ids[]" id="product_ids" class="wc-product-search"
                    multiple="multiple" style="width: 100%;"
                    data-placeholder="<?php esc_attr_e( 'Search for a product…', 'storelly-product-builder-for-woocommerce' ); ?>"
                    data-action="woocommerce_json_search_products">
                <?php
                foreach ( $applied_pids as $product_id ) {
                    $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
                    if ( is_object( $product ) ) {
                        echo '<option value="' . esc_attr( $product_id ) . '"' . wp_kses_post( selected( true, true, false ) ) . '>'
                            . wp_kses_post( $product->get_formatted_name() )
                            . '</option>';
                    }
                }
                ?>
            </select>
        </div>

        <!-- Categories picker — visible when mode = c -->
        <div class="v2-form-row spbwc-po-v3__apply-picker"
             data-spbwc-apply-cats
             ng-show="options.apply_for === 'c'"
             <?php echo $apply_for === 'c' ? '' : 'hidden'; ?>>
            <label class="v2-form-row__label" for="product_cats">
                <?php esc_html_e( 'Select categories', 'storelly-product-builder-for-woocommerce' ); ?>
            </label>
            <p class="v2-form-row__help">
                <?php esc_html_e( 'Every product in the chosen categories will automatically inherit this option.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
            <select name="product_cats[]" id="product_cats" multiple="multiple" style="width: 100%;"
                    data-placeholder="<?php esc_attr_e( 'Pick one or more categories…', 'storelly-product-builder-for-woocommerce' ); ?>">
                <?php
                $product_cats_terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
                if ( ! is_wp_error( $product_cats_terms ) && is_array( $product_cats_terms ) ) {
                    foreach ( $product_cats_terms as $term ) {
                        echo '<option value="' . esc_attr( $term->term_id ) . '"'
                            . selected( in_array( $term->term_id, $applied_cats, true ), true, false )
                            . '>'
                            . esc_html( $term->name ) . ' (' . (int) $term->count . ')'
                            . '</option>';
                    }
                }
                ?>
            </select>
        </div>

        <!-- Stat strip (matches classic data hook so its JS-driven recount works). -->
        <div class="spbwc-po-v3__apply-stats" data-spbwc-apply-stats>
            <div class="spbwc-po-v3__apply-stat" ng-show="options.apply_for !== 'c'">
                <span class="spbwc-po-v3__apply-stat-icon" aria-hidden="true">📦</span>
                <div>
                    <strong data-spbwc-apply-count-products><?php echo absint( $applied_count ); ?></strong>
                    <small><?php esc_html_e( 'individual products', 'storelly-product-builder-for-woocommerce' ); ?></small>
                </div>
            </div>
            <div class="spbwc-po-v3__apply-stat" ng-show="options.apply_for === 'c'">
                <span class="spbwc-po-v3__apply-stat-icon" aria-hidden="true">📁</span>
                <div>
                    <strong data-spbwc-apply-count-cats><?php echo absint( $applied_cat_count ); ?></strong>
                    <small><?php esc_html_e( 'categories selected', 'storelly-product-builder-for-woocommerce' ); ?></small>
                </div>
            </div>
        </div>
    </div>
</div>
