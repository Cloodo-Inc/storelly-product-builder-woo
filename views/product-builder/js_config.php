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
        $redirect_url = isset( $_GET['rd'] ) ? SPBWC_Storelly_PB_Util::spbwc_get_redirect_url( sanitize_text_field( wp_unslash( $_GET['rd'] ) ) ) : '';
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
wp_localize_script( 'product-builder', 'SPBWC_PB_CONFIG', array(
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
$spbwc_pid = get_the_ID();
if (is_singular('product') && SPBWC_Storelly_PB_Util::spbwc_is_product_builder($spbwc_pid)) {
    wp_enqueue_style('product-builder'); 
    wp_enqueue_script('product-builder');
}
?>
