<?php
/**
 * Unified admin search box partial.
 *
 * Shared markup for every Storelly admin search field so all four search
 * controls (options list, products, fonts, template library) look and behave
 * the same. Styling lives in static/css/_components.css (.spbwc-search*).
 *
 * Per-page JavaScript still owns the filter/clear behaviour — this partial only
 * standardises the markup + classes. Wire your handlers to the input `id` and
 * the clear button `id` passed in below.
 *
 * Usage:
 *   $spbwc_search = array(
 *       'id'          => 'spbwc-unified-search',           // input id (required)
 *       'name'        => 's',                              // input name attribute (optional)
 *       'placeholder' => __( 'Search…', '…' ),            // optional
 *       'value'       => $current_value,                   // raw value, escaped here (optional)
 *       'clear_id'    => 'spbwc-search-clear',             // clear button id (optional)
 *       'aria_label'  => __( 'Search', '…' ),             // optional, defaults to placeholder
 *       'type'        => 'search',                         // input type (optional, default 'search')
 *       'input_attrs' => 'ng-model="filterFont.name"',     // raw extra attrs, caller must pre-escape (optional)
 *       'wrapper_id'  => '',                               // optional wrapper id
 *       'wrapper_class' => '',                             // optional extra wrapper class
 *   );
 *   include SPBWC_PB_PLUGIN_DIR . 'views/partials/search-box.php';
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$spbwc_search = isset( $spbwc_search ) && is_array( $spbwc_search ) ? $spbwc_search : array();

$spbwc_sb_id          = isset( $spbwc_search['id'] ) ? (string) $spbwc_search['id'] : 'spbwc-search-input';
$spbwc_sb_name        = isset( $spbwc_search['name'] ) ? (string) $spbwc_search['name'] : '';
$spbwc_sb_placeholder = isset( $spbwc_search['placeholder'] ) ? (string) $spbwc_search['placeholder'] : esc_html__( 'Search…', 'storelly-product-builder-for-woocommerce' );
$spbwc_sb_value       = isset( $spbwc_search['value'] ) ? (string) $spbwc_search['value'] : '';
$spbwc_sb_clear_id    = isset( $spbwc_search['clear_id'] ) ? (string) $spbwc_search['clear_id'] : '';
$spbwc_sb_aria        = isset( $spbwc_search['aria_label'] ) && '' !== $spbwc_search['aria_label']
	? (string) $spbwc_search['aria_label']
	: $spbwc_sb_placeholder;
$spbwc_sb_type        = isset( $spbwc_search['type'] ) ? (string) $spbwc_search['type'] : 'search';
$spbwc_sb_extra_attrs = isset( $spbwc_search['input_attrs'] ) ? (string) $spbwc_search['input_attrs'] : '';
$spbwc_sb_wrap_id     = isset( $spbwc_search['wrapper_id'] ) ? (string) $spbwc_search['wrapper_id'] : '';
$spbwc_sb_wrap_class  = isset( $spbwc_search['wrapper_class'] ) ? (string) $spbwc_search['wrapper_class'] : '';

$spbwc_sb_clear_hidden = ( '' === trim( $spbwc_sb_value ) );
?>
<div class="spbwc-search<?php echo '' !== $spbwc_sb_wrap_class ? ' ' . esc_attr( $spbwc_sb_wrap_class ) : ''; ?>"<?php echo '' !== $spbwc_sb_wrap_id ? ' id="' . esc_attr( $spbwc_sb_wrap_id ) . '"' : ''; ?>>
	<span class="dashicons dashicons-search spbwc-search__icon" aria-hidden="true"></span>
	<input type="<?php echo esc_attr( $spbwc_sb_type ); ?>"
		id="<?php echo esc_attr( $spbwc_sb_id ); ?>"
		class="spbwc-search__input"
		<?php echo '' !== $spbwc_sb_name ? 'name="' . esc_attr( $spbwc_sb_name ) . '"' : ''; ?>
		value="<?php echo esc_attr( $spbwc_sb_value ); ?>"
		placeholder="<?php echo esc_attr( $spbwc_sb_placeholder ); ?>"
		aria-label="<?php echo esc_attr( $spbwc_sb_aria ); ?>"
		autocomplete="off"
		<?php
		// Extra attributes are caller-controlled (e.g. AngularJS ng-* bindings).
		// The caller is responsible for escaping them; we never accept request data here.
		echo $spbwc_sb_extra_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, caller-supplied markup attributes; no request data.
		?>
	/>
	<?php if ( '' !== $spbwc_sb_clear_id ) : ?>
	<button type="button"
		id="<?php echo esc_attr( $spbwc_sb_clear_id ); ?>"
		class="spbwc-search__clear"
		aria-label="<?php esc_attr_e( 'Clear search', 'storelly-product-builder-for-woocommerce' ); ?>"
		<?php echo $spbwc_sb_clear_hidden ? 'hidden' : ''; ?>>
		<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
	</button>
	<?php endif; ?>
</div>
