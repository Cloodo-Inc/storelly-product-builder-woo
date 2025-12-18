<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

// Read-only query args coming from WooCommerce edit/cart flows; no custom nonce is available.
$pcpb_cart_item_key_raw = isset( $_GET['pcpb_cart_item_key'] ) ? wp_unslash( $_GET['pcpb_cart_item_key'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query arg for builder configuration.
$pcpb_cart_item_key     = '' !== $pcpb_cart_item_key_raw ? sanitize_text_field( $pcpb_cart_item_key_raw ) : ''; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.

$oid_raw = isset( $_GET['oid'] ) ? wp_unslash( $_GET['oid'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query arg for builder configuration.
$oid     = '' !== $oid_raw ? absint( sanitize_text_field( $oid_raw ) ) : 0; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.

$rd_raw      = isset( $_GET['rd'] ) ? wp_unslash( $_GET['rd'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect key used to compute safe redirect URL.
$redirect_url = '' !== $rd_raw ? SPBWC_Storelly_PB_Util::spbwc_get_redirect_url( sanitize_text_field( $rd_raw ) ) : ''; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
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
if (file_exists(SPBWC_PB_FONT_DIR . '/googlefonts.json')) {
    $google_fonts = (array)json_decode(file_get_contents(SPBWC_PB_FONT_DIR . '/googlefonts.json')); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
}
$fonts      = $google_fonts; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
$font_url   = SPBWC_PB_FONT_URL; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable.
wp_localize_script( 'product-builder', 'NBPBCONFIG', array(
        'is_mobile' => wp_is_mobile(),
        'is_creating_task' => $is_creating_task,
        'assets_url' => SPBWC_PB_ASSETS_URL,
        'plg_url' => SPBWC_PB_PLUGIN_URL,
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('spbwc_save_design_action'),
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
$pid = get_the_ID();
if (is_singular('product') && SPBWC_Storelly_PB_Util::spbwc_is_product_builder($pid)) {
    wp_enqueue_style('product-builder'); 
    wp_enqueue_script('product-builder');
}
?>
