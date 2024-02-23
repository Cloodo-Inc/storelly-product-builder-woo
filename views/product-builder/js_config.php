<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
$pcpb_cart_item_key  = (isset($_GET['pcpb_cart_item_key']) &&  sanitize_text_field($_GET['pcpb_cart_item_key']) != '') ? sanitize_text_field($_GET['pcpb_cart_item_key']) : '';
$oid                = (isset($_GET['oid']) && $_GET['oid'] != '') ? absint(sanitize_text_field($_GET['oid'])) :  0;
$redirect_url       = (isset($_GET['rd']) && $_GET['rd'] != '') ? Storelly_PB_Util::Storelly_get_redirect_url(sanitize_text_field($_GET['rd'])) :  '';
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
if (file_exists(STORELLY_PB_FONT_DIR . '/googlefonts.json')) {
    $google_fonts = (array)json_decode(file_get_contents(STORELLY_PB_FONT_DIR . '/googlefonts.json'));
}
$fonts      = $google_fonts;

$font_url   = STORELLY_PB_FONT_URL;

?>

<!-- No inline scripts or styles unless dynamic. -->
<script type="text/javascript">
    var NBPBCONFIG = {
        is_mobile: "<?php echo esc_js(wp_is_mobile()); ?>",
        is_creating_task: "<?php echo esc_js($is_creating_task); ?>",
        assets_url: "<?php echo esc_url(STORELLY_PB_ASSETS_URL); ?>",
        plg_url: "<?php echo esc_url(STORELLY_PB_PLUGIN_URL); ?>",
        ajax_url: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
        nonce: "<?php echo esc_attr(wp_create_nonce('save-design')); ?>",
        pcpb_cart_item_key: "<?php echo esc_js($pcpb_cart_item_key); ?>",
        oid: "<?php echo esc_js($oid); ?>",
        redirect_url: "<?php echo esc_url($redirect_url); ?>",
        google_fonts: <?php echo wp_json_encode($google_fonts); ?>,
        pre_builder: <?php echo wp_json_encode(Storelly_PB_Util::Storelly_get_product_pre_builder($oid, $pcpb_cart_item_key)); ?>,
        fonts: <?php echo wp_json_encode($fonts); ?>,
        font_url: "<?php echo esc_url($font_url); ?>",
        i18n: <?php echo wp_json_encode(array(
                    'only_support_image'    => esc_html__('Only support image!', 'pc-product-builder'),
                    'max_file_size'         => esc_html__('Max file size', 'pc-product-builder'),
                    'min_file_size'         => esc_html__('Min file size', 'pc-product-builder'),
                    'confirm_delete_image'  => esc_html__('Are you sure you want to delete this image?', 'pc-product-builder'),
                    'confirm_delete_text'   => esc_html__('Are you sure you want to delete this text?', 'pc-product-builder'),
                    'can_not_save_design'   => esc_html__('Oops! Design has not been saved!', 'pc-product-builder'),
                    'choose'                => esc_html__('Choose', 'pc-product-builder'),
                    'cancel'                => esc_html__('Cancel', 'pc-product-builder')
                )); ?>
    };
    var nbds_frontend = [];
</script>