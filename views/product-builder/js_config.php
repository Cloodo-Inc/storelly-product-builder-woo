<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

// Only accept cart edit keys when the edit nonce is valid.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
$pcpb_cart_item_key = '';
if ( isset( $_GET['pcpb_cart_item_key'], $_GET['_wpnonce'] ) ) {
    $spbwc_edit_nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
    if ( wp_verify_nonce( $spbwc_edit_nonce, 'nbo-edit' ) ) {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
        $pcpb_cart_item_key = sanitize_text_field( wp_unslash( $_GET['pcpb_cart_item_key'] ) );
    }
}

// Only accept preview/task query args when the preview nonce is valid.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
$oid = 0;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
$redirect_url = '';
if ( isset( $_GET['_wpnonce'] ) ) {
    $spbwc_preview_nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
    if ( wp_verify_nonce( $spbwc_preview_nonce, 'spbwc_builder_preview_action' ) ) {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
        $oid = isset( $_GET['oid'] ) ? absint( wp_unslash( $_GET['oid'] ) ) : 0;
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
        $redirect_url = isset( $_GET['rd'] ) ? SPBWC_Storelly_PB_Util::spbwc_get_redirect_url() : '';
    }
}
if ($is_creating_task == 0) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable defined by parent template.
    $oid = $option_id; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
} else if ($oid == 0) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
    global $wp_query; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wp_query.
    $wp_query->set_404();
    status_header(404);
    get_template_part(404);
    exit();
}
$fonts = array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
$google_fonts = array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
$google_fonts_json = SPBWC_Storelly_IO::spbwc_get_local_file_contents(SPBWC_PB_FONT_DIR . '/googlefonts.json');
if (false !== $google_fonts_json) {
    $google_fonts = (array) json_decode($google_fonts_json); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
}
$fonts      = $google_fonts; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
$font_url   = SPBWC_PB_FONT_URL; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.

// Volume-discount tiers for the V3 customizer's live total. Mirror of the
// server engine (SPBWC_Storelly_Frontend_Options::option_processing): only
// non-empty tiers with val>0 && dis>0 are surfaced; the JS picks the highest
// tier where qty>=val. Display-only — the cart re-computes authoritatively at
// add-to-cart, so this just keeps the shown price honest.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
$spbwc_qbreaks_cfg = array();
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
$spbwc_qdtype_cfg  = 'f';
if ( $oid > 0 ) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
    $spbwc_qb_cache_key = 'spbwc_pb_qbreaks_' . $oid;
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
    $spbwc_optblob = wp_cache_get( $spbwc_qb_cache_key, 'storelly_product_builder' );
    if ( false === $spbwc_optblob ) {
        global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
        $spbwc_qb_table = $wpdb->prefix . 'storelly_product_builder_options';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name from $wpdb->prefix; value parameterized; result cached on next line.
        $spbwc_qb_raw  = $wpdb->get_var( $wpdb->prepare( "SELECT fields FROM {$spbwc_qb_table} WHERE `id` = %d LIMIT 1", $oid ) );
        $spbwc_optblob = $spbwc_qb_raw ? maybe_unserialize( $spbwc_qb_raw ) : array();
        wp_cache_set( $spbwc_qb_cache_key, $spbwc_optblob, 'storelly_product_builder', HOUR_IN_SECONDS );
    }
    if ( is_array( $spbwc_optblob ) ) {
        $spbwc_qdtype_cfg = isset( $spbwc_optblob['quantity_discount_type'] ) ? (string) $spbwc_optblob['quantity_discount_type'] : 'f';
        if ( isset( $spbwc_optblob['quantity_breaks'] ) && is_array( $spbwc_optblob['quantity_breaks'] ) ) {
            foreach ( $spbwc_optblob['quantity_breaks'] as $spbwc_qb ) {
                if ( ! is_array( $spbwc_qb ) ) { continue; }
                $spbwc_qb_v = isset( $spbwc_qb['val'] ) ? $spbwc_qb['val'] : '';
                $spbwc_qb_d = isset( $spbwc_qb['dis'] ) ? $spbwc_qb['dis'] : '';
                $spbwc_qb_v = is_array( $spbwc_qb_v ) ? ( isset( $spbwc_qb_v['value'] ) ? $spbwc_qb_v['value'] : '' ) : $spbwc_qb_v;
                $spbwc_qb_d = is_array( $spbwc_qb_d ) ? ( isset( $spbwc_qb_d['value'] ) ? $spbwc_qb_d['value'] : '' ) : $spbwc_qb_d;
                if ( '' === (string) $spbwc_qb_v || '' === (string) $spbwc_qb_d ) { continue; }
                $spbwc_qb_v = (int) $spbwc_qb_v;
                $spbwc_qb_d = (float) $spbwc_qb_d;
                if ( $spbwc_qb_v > 0 && $spbwc_qb_d > 0 ) {
                    $spbwc_qbreaks_cfg[] = array( 'val' => $spbwc_qb_v, 'dis' => $spbwc_qb_d );
                }
            }
        }
    }
}

wp_localize_script( 'product-builder', 'SPBWC_PB_CONFIG', array(
        'is_mobile' => wp_is_mobile(),
        'is_creating_task' => $is_creating_task,
        'assets_url' => SPBWC_PB_ASSETS_URL,
        'plg_url' => SPBWC_PB_PLUGIN_URL,
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('spbwc_save_design_action'),
        /* Currency context for the V2/V3 customizer sidebar — used by
         * $scope.formatPrice() to render per-option price tags. */
        'currency_symbol' => html_entity_decode( (string) get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
        'currency_decimals' => SPBWC_Storelly_PB_Util::spbwc_get_option_decimals(),
        /* V3 — base price (raw float) for $scope.computeBuildTotal().
         * Lives outside i18n so the JS can read it as a number. Falls
         * back to 0 outside a product context (admin create-task flow). */
        'base_price_raw' => ( function_exists( 'wc_get_product' ) && is_singular( 'product' ) && wc_get_product( get_the_ID() ) ) ? (float) wc_get_product( get_the_ID() )->get_price() : 0,
        /* V3 — volume-discount tiers + type for $scope.getVolumeDiscount().
         * Empty array when the option set defines no quantity breaks. */
        'quantity_breaks'        => $spbwc_qbreaks_cfg,
        'quantity_discount_type' => $spbwc_qdtype_cfg,
        'pcpb_cart_item_key' => $pcpb_cart_item_key,
        'oid' => $oid, 
        'redirect_url' => $redirect_url,
        'google_fonts' => wp_json_encode((array) $google_fonts),
        'pre_builder' => wp_json_encode((array) SPBWC_Storelly_PB_Util::spbwc_get_product_pre_builder($oid, $pcpb_cart_item_key)),
        'fonts' => wp_json_encode((array) $fonts),
        'font_url' => $font_url,
        'i18n' => array(
            'only_support_image' => esc_html__('Only support image!', 'storelly-product-builder-for-woocommerce'),
            'max_file_size' => esc_html__('Max file size', 'storelly-product-builder-for-woocommerce'),
            'min_file_size' => esc_html__('Min file size', 'storelly-product-builder-for-woocommerce'),
            'confirm_delete_image' => esc_html__('Are you sure you want to delete this image?', 'storelly-product-builder-for-woocommerce'),
            'confirm_delete_text' => esc_html__('Are you sure you want to delete this text?', 'storelly-product-builder-for-woocommerce'),
            'can_not_save_design' => esc_html__('Oops! Design has not been saved!', 'storelly-product-builder-for-woocommerce'),
            'choose' => esc_html__('Choose', 'storelly-product-builder-for-woocommerce'),
            'cancel' => esc_html__('Cancel', 'storelly-product-builder-for-woocommerce'),
        ),
));
$spbwc_pid = get_the_ID();
$is_pb_page = SPBWC_Storelly_PB_Util::spbwc_is_product_builder_page();
if ($is_pb_page || (is_singular('product') && SPBWC_Storelly_PB_Util::spbwc_is_product_builder($spbwc_pid))) {
    wp_enqueue_style('product-builder'); 
    wp_enqueue_script('product-builder');
}
?>
