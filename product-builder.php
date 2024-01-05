<?php
 if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
/**
 * @package Storelly 
 */
/*
Plugin Name: Storelly Product Builder 
Plugin URI: https://storelly.com
Description: Create product builder for WC products
Version: 1.0.0
Author: Storelly Team
Author URI: https://storelly.com
Text Domain: pc-product-builder
WC requires at least: 6.0.0
WC tested up to: 6.5.1
PHP: >=7.0
*/

$upload_dir = wp_upload_dir();
$basedir    = $upload_dir['basedir'];
$baseurl    = $upload_dir['baseurl'];
define('STORELLY_PB_VERSION',                  '1.0.0');
define('STORELLY_PB_NUMBER_VERSION',           100);
define('STORELLY_PB_PLUGIN_URL',               plugin_dir_url(__FILE__));
define('STORELLY_PB_PLUGIN_DIR',               plugin_dir_path(__FILE__));
define('STORELLY_PB_DATA_DIR',                 $basedir . '/storelly-product-builder');
define('STORELLY_PB_DATA_URL',                 $baseurl . '/storelly-product-builder');
define('STORELLY_PB_DATA_CONFIG_URL',          STORELLY_PB_PLUGIN_URL . 'data/');
define('STORELLY_PB_DATA_CONFIG_DIR',          STORELLY_PB_PLUGIN_DIR . 'data/');
define('STORELLY_PB_FONT_URL',                 STORELLY_PB_DATA_URL . '/fonts');
define('STORELLY_PB_FONT_DIR',                 STORELLY_PB_DATA_DIR . '/fonts');
define('STORELLY_PB_UPLOAD_DIR',               STORELLY_PB_DATA_DIR . '/uploads');
define('STORELLY_PB_UPLOAD_URL',               STORELLY_PB_DATA_URL . '/uploads');
define('STORELLY_PB_CUSTOMER_DIR',             STORELLY_PB_DATA_DIR . '/designs');
define('STORELLY_PB_CUSTOMER_URL',             STORELLY_PB_DATA_URL . '/designs');
define('STORELLY_PB_ASSETS_URL',               STORELLY_PB_PLUGIN_URL . 'assets/');
define('STORELLY_PB_ASSETS_DIR',               STORELLY_PB_PLUGIN_DIR . 'assets/');
define('STORELLY_PB_JS_URL',                   STORELLY_PB_PLUGIN_URL . 'assets/js/');
define('STORELLY_PB_CSS_URL',                  STORELLY_PB_PLUGIN_URL . 'assets/css/');
define('STORELLY_ENABLE_NONCE',                TRUE);
define('STORELLY_API_URL',                      'https://dashboard.storelly.com/public');

require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-script-hook.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-export-pdf.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-util.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-image.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-io.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-install.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-product-builder-backend.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-product-builder-frontend.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-admin-options.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-frontend-options.php');

register_activation_hook(__FILE__, array('Storelly_Product_Builder_Backend', 'plugin_activation'));

$storelly_product_builder = new Storelly_Product_Builder_Backend;
$storelly_product_builder->init();

function create_user_storelly(){

    $option = get_option('storelly_connect_api_keys');

    if (empty($option['username'])) {
        $current_user = wp_get_current_user();
    
        $curl = curl_init(); // Tạo mới một CURL
        $user_name = $current_user->user_login . '_' . time();

        curl_setopt_array($curl, array( // Cấu hình cho CURL bên trong chứa các thông số
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => STORELLY_API_URL . '/api/v1/register', // đường dẫn tới URL cần xử lý
            CURLOPT_POST => 1,
            CURLOPT_SSL_VERIFYPEER => false, //Bỏ kiểm SSL
            CURLOPT_POSTFIELDS => http_build_query(array(
                "name" => $current_user->display_name,
                "currency_id" => 1,
                "country" => get_user_meta($current_user->ID, 'billing_country', true),
                "state" => get_user_meta($current_user->ID, 'billing_state', true) ? get_user_meta($current_user->ID, 'billing_state', true) : "state",
                "city" => get_user_meta($current_user->ID, 'billing_city', true),
                "zip_code" => get_user_meta($current_user->ID, 'billing_postcode', true) ? get_user_meta($current_user->ID, 'billing_postcode', true) : '100000',
                "landmark" => get_user_meta($current_user->ID, 'billing_address_1', true) ? get_user_meta($current_user->ID, 'billing_address_1', true) : 'address',
                "time_zone" => "Asia/Ho_Chi_Minh",
                "surname" => get_user_meta($current_user->ID, 'billing_last_name', true) ? get_user_meta($current_user->ID, 'billing_last_name', true) : 'lastname',
                "email" => $current_user->user_email,
                "first_name" => get_user_meta($current_user->ID, 'billing_first_name', true) ? get_user_meta($current_user->ID, 'billing_first_name', true) : 'firstname',
                "username" => $user_name,
                "password" => $user_name,
                "fy_start_month" => date('n'),
                "accounting_method" => "phuong_phap_1",
                "woocommerce_api_settings" => array(
                    "woocommerce_app_url" => home_url(),
                    "woocommerce_consumer_key" => $option['consumer_key'],
                    "woocommerce_consumer_secret" => $option['consumer_secret']
                )
            ))
        ));
        $resp = curl_exec($curl); // Thực thi CURL

        $resp = json_decode($resp, true);


        
        if ($resp['success'] == 1) {
            $option['username'] = $resp['username'];
            $option['unauth_token'] = $resp['unauth_token'];
            update_option('storelly_connect_api_keys', $option);
        } else {
            $option['log'] = $resp['msg'];
            update_option('storelly_connect_api_keys', $option);
        }
        curl_close($curl); // Ngắt CURL, giải phóng

    }

}
function storelly_activation_redirect($plugin){
    if ($plugin == plugin_basename(__FILE__)) {
        exit(wp_redirect(admin_url('admin.php?page=pc-product-builder-options/settings')));
    }
}
add_action('activated_plugin', 'storelly_activation_redirect');
//  bat su kien active plguin 
register_activation_hook(__FILE__, 'storelly_generate_key');

// function tao consumer key
function storelly_generate_key(){

    global $wpdb;
    $description = __('Storelly', 'pc-product-builder');
    $permissions = 'read_write';
    $user_id     = get_current_user_id();
    $response      = array();
    $consumer_key    = 'ck_' . wc_rand_hash();
    $consumer_secret = 'cs_' . wc_rand_hash();

    if (!$user_id || ($user_id && !current_user_can('edit_user', $user_id))) {
        throw new Exception(__('You do not have permission to assign API Keys to the selected user.', 'storelly-integration'));
    }

    $data = array(
        'user_id'         => $user_id,
        'description'     => $description,
        'permissions'     => $permissions,
        'consumer_key'    => wc_api_hash($consumer_key),
        'consumer_secret' => $consumer_secret,
        'truncated_key'   => substr($consumer_key, -7),
    );

    // Delete all previously generated keys
    $wpdb->delete(
        $wpdb->prefix . 'woocommerce_api_keys',
        array(
            'user_id'         => $user_id,
            'description'     => $description,
        )
    );

    $wpdb->insert(
        $wpdb->prefix . 'woocommerce_api_keys',
        $data,
        array(
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
        )
    );
    $response = get_option('storelly_connect_api_keys');
    if ($response) {
    } else {
        $response = array();
    }

    $response['consumer_key']    = $consumer_key;
    $response['consumer_secret'] = $consumer_secret;

    update_option('storelly_connect_api_keys', $response);
    create_user_storelly();
}


// lấy thông tin order trong woocommerce và đồng bộ qua curl 
add_action('woocommerce_checkout_order_processed', 'storelly_order_processed');
function storelly_order_processed($order_id){
    $order = wc_get_order($order_id);
    notify_on_new_order($order);
}
add_action('woocommerce_store_api_checkout_order_processed', 'notify_on_new_order');
function notify_on_new_order($order){
    
    // Lấy thông tin product trong order
    $products = array();
    $cFile = [];
    foreach ($order->get_items() as $item_id => $item) {
        $folder_design = wc_get_order_item_meta($item_id, '_pcpb_folder', true);
        Storelly_Export_PDF::exportPDF($folder_design);
        $path_pdf = STORELLY_PB_CUSTOMER_DIR . '/' . $folder_design . '/customer-pdfs';
        $files = Storelly_IO::get_list_files_by_type($path_pdf, 1, 'pdf');

        foreach ($files as $file) {
            $cFile[] = Storelly_IO::convert_path_to_url($file);
        }
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $quantity = $item->get_quantity();
        $product  = $item->get_product();
        $products[] = [
            "product_id" => $product_id,
            "variation_id" => $variation_id,
            "unit_price" => $product->get_price(),
            "unit_price_inc_tax" => 1,
            "quantity" => $quantity,
            "item_tax" => "",
            "enable_stock" => 0,
            "product_type" => "single",
            "tax_id" => ""
        ];
    }

    $api_settings = get_option('storelly_connect_api_keys');

    //đồng bộ qua curl 
    $curl = curl_init(); // Tạo mới một CURL
    $body = array(
        "is_quotation" => 0,

        "status" => "final",
        "final_total" => $order->get_total(),
        "contact_id" => 1,
        "is_direct_sale" => 1,
        "products" => $products,
        "tax_rate_id" => "",
        // "shipping_documents" => $cFile,
        "discount_type" => "fixed",
        "discount_amount" => $order->get_discount_total(),
        "payment" => [
            [
                "amount" => $order->get_total() - $order->get_discount_total(),
                "is_return" => 0,
                "method" => "cash"
            ]
        ],
        "price_group" => 0
    );
    $body['shipping_documents'] = $cFile;

    curl_setopt_array($curl, array( // Cấu hình cho CURL bên trong chứa các thông số
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => STORELLY_API_URL . '/api/v1/update-orders', // đường dẫn tới URL cần xử lý
        CURLOPT_POST => 1,
        CURLOPT_HTTPHEADER => ['X-STORLY: ' . $api_settings['unauth_token']],
        CURLOPT_SSL_VERIFYPEER => false, //Bỏ kiểm SSL
        CURLOPT_POSTFIELDS => http_build_query($body)
    ));
    $resp = curl_exec($curl); // Thực thi CURL

    $resp = json_decode($resp, true);
}

// // get img preview 
add_action('woocommerce_order_item_meta_end', 'storelly_img_design_order_items', 10, 4);
function storelly_img_design_order_items($item_id, $item, $order, $plain_text){
    $folder_design = wc_get_order_item_meta($item_id, '_pcpb_folder', true);
    $path_preview = STORELLY_PB_CUSTOMER_DIR . '/' . $folder_design . '/preview';
    $files = Storelly_IO::get_list_files_by_type($path_preview, 1, 'png');
    foreach ($files as $img){
        ?>
        <img width="80" src="<?php echo Storelly_IO::convert_path_to_url($img);?>" alt="">
        <?php
    }
    
}

