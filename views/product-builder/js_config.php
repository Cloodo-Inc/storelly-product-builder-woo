<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
$pcpb_cart_item_key  = (isset($_GET['pcpb_cart_item_key']) &&  sanitize_text_field($_GET['pcpb_cart_item_key']) != '') ? sanitize_text_field($_GET['pcpb_cart_item_key']) : '';
$oid                = (isset($_GET['oid']) && $_GET['oid'] != '') ? absint(sanitize_text_field($_GET['oid'])) :  0;
$redirect_url       = (isset($_GET['rd']) && $_GET['rd'] != '') ? SPBWC_Storelly_PB_Util::spbwc_get_redirect_url(sanitize_text_field($_GET['rd'])) :  '';
if ($is_creating_task == 0) {
    $oid = $option_id;
} else if ($oid == 0) {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    get_template_part(404);
    exit();
}
$fonts = array();
$google_fonts = array();
if (file_exists(SPBWC_PB_FONT_DIR . '/googlefonts.json')) {
    $google_fonts = (array)json_decode(file_get_contents(SPBWC_PB_FONT_DIR . '/googlefonts.json'));
}
$fonts      = $google_fonts;
$font_url   = SPBWC_PB_FONT_URL;
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
