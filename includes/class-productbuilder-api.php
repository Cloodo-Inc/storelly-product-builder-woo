<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
if (!class_exists('Storelly_Product_Builder_API')) {
    class Storelly_Product_Builder_API{

        public function __construct()
        {
        } 
        
        public function init()
        {
            //  bat su kien active plguin     
            add_action('init',  array($this, 'create_user_storelly'));

            add_action('activated_plugin', array($this, 'storelly_activation_redirect'), 10, 1);
            
            // lấy thông tin order trong woocommerce và đồng bộ qua curl 
            add_action('woocommerce_checkout_order_processed', array($this, 'storelly_order_processed'), 10, 1);

            add_action('woocommerce_store_api_checkout_order_processed', array($this, 'notify_on_new_order'), 10, 1); 

            // get img preview 
            add_action('woocommerce_order_item_meta_end', array($this, 'storelly_img_design_order_items'), 10, 4); 
            
       
        }

        protected static $instance;

        public static function instance()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public static function create_user_storelly(){
            $option = get_option('storelly_connect_api_keys');
            if (empty($option['username'])) {
                $current_user = wp_get_current_user(); 
                $user_name = $current_user->user_login . '_' . time();

                $datas = array(
                    "name" => $current_user->display_name,
                    "currency_id" => 1,
                    "country" => get_user_meta($current_user->ID, 'billing_country', true) ? get_user_meta($current_user->ID, 'billing_country', true) : "country",
                    "state" => get_user_meta($current_user->ID, 'billing_state', true) ? get_user_meta($current_user->ID, 'billing_state', true) : "state",
                    "city" => get_user_meta($current_user->ID, 'billing_city', true) ? get_user_meta($current_user->ID, 'billing_city', true) : "city", 
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
                );
                
                $resp = STORELLY_HTTP::postDataWithoutAuth(STORELLY_API_URL . '/api/v1/register', $datas);
                if (isset($resp) && is_array($resp)) {
                    if ($resp['success'] == 1) {
                        if (isset($resp['username'])) {
                            $option['username'] = $resp['username'];
                        }
                        if (isset($resp['unauth_token'])) {
                            $option['unauth_token'] = $resp['unauth_token'];
                        }
                        update_option('storelly_connect_api_keys', $option);
                    } else {
                        if (isset($resp['msg'])) {
                            $option['log'] = $resp['msg'];
                        }
                        update_option('storelly_connect_api_keys', $option);
                    }
                } else {
                    update_option('storelly_connect_api_keys', $option);
                }
            }
        }
        public static function storelly_generate_key(){
            
            $response = get_option('storelly_connect_api_keys');
            if ($response) {
            } else {
                $response = array();
            }
            if(!empty($response)) return;
        
            global $wpdb;
            $description = __('Storelly', 'pc-product-builder');
            $permissions = 'read_write';
            $user_id     = get_current_user_id();
            $response      = array();
            $consumer_key    = 'ck_' . wc_rand_hash();
            $consumer_secret = 'cs_' . wc_rand_hash();
        
            if (!$user_id || ($user_id && !current_user_can('edit_user', $user_id))) {
                throw new Exception(__('You do not have permission to assign API Keys to the selected user.', 'pc-product-builder'));
            }
        
            $data = array(
                'user_id'         => $user_id,
                'description'     => $description,
                'permissions'     => $permissions,
                'consumer_key'    => wc_api_hash($consumer_key),
                'consumer_secret' => $consumer_secret,
                'truncated_key'   => substr($consumer_key, -7),
            );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete(
                $wpdb->prefix . 'woocommerce_api_keys',
                array(
                    'user_id'     => $user_id,
                    'description' => $description,
                )
            );

            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            wp_cache_delete('woocommerce_api_keys', 'options');
        
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            wp_cache_delete('woocommerce_api_keys', 'options');
            $response['consumer_key']    = $consumer_key;
            $response['consumer_secret'] = $consumer_secret;
        
            update_option('storelly_connect_api_keys', $response);
            self::create_user_storelly();  
        }

        public function storelly_order_processed($order_id){
            $order = wc_get_order($order_id);
            $this->notify_on_new_order($order);
        } 
        
        public function notify_on_new_order($order){
            $products = array();
            $cFile = [];
            foreach ($order->get_items() as $item_id => $item) {
                $folder_design = wc_get_order_item_meta($item_id, '_pcpb_folder', true);
                Storelly_Export_PDF::exportPDF($folder_design);
                $path_pdf = STORELLY_PB_CUSTOMER_DIR . '/' . $folder_design . '/customer-pdfs';
                $files = Storelly_IO::get_list_files_by_type($path_pdf, 'pdf', 1);
        
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

        
            $body = array(
                "is_quotation" => 0,
                "status" => "final",
                "final_total" => $order->get_total(),
                "contact_id" => 1,
                "is_direct_sale" => 1,
                "products" => $products,
                "tax_rate_id" => "",
                "shipping_documents" => $cFile,
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
            $resp = STORELLY_HTTP::postData(STORELLY_API_URL . '/api/v1/update-orders',$body);
        
            $body = array(
                "is_quotation" => 0,
        
                "status" => "final",
                "final_total" => $order->get_total(),
                "contact_id" => 1,
                "is_direct_sale" => 1,
                "products" => $products,
                "tax_rate_id" => "", 
                "shipping_documents" => $cFile,
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
            $resp = STORELLY_HTTP::postData(STORELLY_API_URL . '/api/v1/update-orders',$body); 
        }
        public function plugin_activation(){

        }
        
        public function storelly_activation_redirect($plugin){
            if ($plugin == plugin_basename(__FILE__)) {
                exit(wp_redirect(admin_url('admin.php?page=pc-product-builder-options/settings')));
            }
        }
        
        public function storelly_img_design_order_items($item_id, $item, $order, $plain_text){
            $folder_design = wc_get_order_item_meta($item_id, '_pcpb_folder', true);
            $path_preview = STORELLY_PB_CUSTOMER_DIR . '/' . $folder_design . '/preview';
            $files = Storelly_IO::get_list_files_by_type($path_preview, 'png', 1);
            foreach ($files as $img){
                ?>
<img width="80" src="<?php echo esc_url(Storelly_IO::convert_path_to_url($img)) ;?>" alt="">
<?php
            }
            
        }
        
    }
}
$stll_product_builder_API = Storelly_Product_Builder_API::instance();
$stll_product_builder_API->init();