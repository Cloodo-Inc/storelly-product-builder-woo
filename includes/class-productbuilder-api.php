<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
if (!class_exists('SPBWC_Storelly_Product_Builder_API')) {
    class SPBWC_Storelly_Product_Builder_API{

        public function __construct()
        {
        } 
        
        public function spbwc_init()
        {
            // Removed automatic user creation on init hook to prevent phoning home without opt-in consent.
            // User creation now only happens when admin explicitly connects account in settings page.
            
            add_action('activated_plugin', array($this, 'spbwc_activation_redirect'), 10, 1);
            
            // lấy thông tin order trong woocommerce và đồng bộ qua curl 
            add_action('woocommerce_checkout_order_processed', array($this, 'spbwc_order_processed'), 10, 1);

            add_action('woocommerce_store_api_checkout_order_processed', array($this, 'spbwc_notify_on_new_order'), 10, 1); 

            // get img preview 
            add_action('woocommerce_order_item_meta_end', array($this, 'spbwc_img_design_order_items'), 10, 4); 
            
       
        }

        protected static $instance;

        public static function instance()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public static function spbwc_create_user_storelly(){
            // Only create user if explicitly enabled by admin via settings.
            $api_settings = get_option('spbwc_pb_settings', array());
            $enable_api_sync = isset($api_settings['enable_api_sync']) && 'yes' === $api_settings['enable_api_sync'];
            
            if (!$enable_api_sync) {
                return; // Exit early if API sync is not enabled (opt-in required).
            }
            
            $option = get_option('spbwc_connect_api_keys');
            if (empty($option['username'])) {
                $current_user = wp_get_current_user(); 
                $user_name = sanitize_user($current_user->user_login) . '_' . time();

                $datas = array(
                    "name" => sanitize_text_field($current_user->display_name),
                    "currency_id" => 1,
                    "country" => get_user_meta($current_user->ID, 'billing_country', true) ? sanitize_text_field(get_user_meta($current_user->ID, 'billing_country', true)) : "country",
                    "state" => get_user_meta($current_user->ID, 'billing_state', true) ? sanitize_text_field(get_user_meta($current_user->ID, 'billing_state', true)) : "state",
                    "city" => get_user_meta($current_user->ID, 'billing_city', true) ? sanitize_text_field(get_user_meta($current_user->ID, 'billing_city', true)) : "city", 
                    "zip_code" => get_user_meta($current_user->ID, 'billing_postcode', true) ? sanitize_text_field(get_user_meta($current_user->ID, 'billing_postcode', true)) : '100000',
                    "landmark" => get_user_meta($current_user->ID, 'billing_address_1', true) ? sanitize_text_field(get_user_meta($current_user->ID, 'billing_address_1', true)) : 'address',
                    "time_zone" => "Asia/Ho_Chi_Minh", 
                    "surname" => get_user_meta($current_user->ID, 'billing_last_name', true) ? sanitize_text_field(get_user_meta($current_user->ID, 'billing_last_name', true)) : 'lastname',
                    "email" => sanitize_email($current_user->user_email), // Sử dụng sanitize_email
                    "first_name" => get_user_meta($current_user->ID, 'billing_first_name', true) ? sanitize_text_field(get_user_meta($current_user->ID, 'billing_first_name', true)) : 'firstname',
                    "username" => $user_name,
                    "password" => $user_name,
                    "fy_start_month" => gmdate('n'),
                    "accounting_method" => "phuong_phap_1", 
                    "woocommerce_api_settings" => array(
                        "woocommerce_app_url" => esc_url(home_url()), // Escaping URL
                        "woocommerce_consumer_key" => isset($option['consumer_key']) ? sanitize_text_field($option['consumer_key']) : '',
                        "woocommerce_consumer_secret" => isset($option['consumer_secret']) ? sanitize_text_field($option['consumer_secret']) : ''
                    )
                );
                
                $api_url = SPBWC_API_URL . '/api/v1/register';
                $resp = SPBWC_Storelly_HTTP::spbwc_post_data_without_auth($api_url, $datas);
                if (isset($resp) && is_array($resp)) {
                    if ($resp['success'] == 1) {
                        if (isset($resp['username'])) {
                            $option['username'] = sanitize_text_field($resp['username']);
                        }
                        if (isset($resp['unauth_token'])) {
                            $option['unauth_token'] = sanitize_text_field($resp['unauth_token']);
                        }
                        update_option('spbwc_connect_api_keys', $option);
                    } else {
                        if (isset($resp['msg'])) {
                            $option['log'] = sanitize_text_field($resp['msg']);
                        }
                        update_option('spbwc_connect_api_keys', $option);
                    }
                } else {
                    update_option('spbwc_connect_api_keys', $option);
                }
            }
        }
        public static function spbwc_generate_key(){
            
            $response = get_option('spbwc_connect_api_keys');
            if ($response) {
            } else {
                $response = array();
            }
            if(!empty($response)) return;
        
            global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
            $description = __('Storelly', 'storelly-product-builder-for-woocommerce');
            $permissions = 'read_write';
            $user_id     = get_current_user_id();
            $response      = array();
            $consumer_key    = 'ck_' . wc_rand_hash();
            $consumer_secret = 'cs_' . wc_rand_hash();
        
            if (!$user_id || ($user_id && !current_user_can('edit_user', $user_id))) {
                   throw new Exception(esc_html__('You do not have permission to assign API Keys to the selected user.', 'storelly-product-builder-for-woocommerce')); // storelly-integration -> storelly-product-builder-for-woocommerce
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
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WooCommerce stores REST API keys in a custom table; write query required to rotate keys.
            $wpdb->delete(
                $wpdb->prefix . 'woocommerce_api_keys',
                array(
                    'user_id'         => $user_id,
                    'description'     => $description,
                ),
                array(
                    '%d',
                    '%s',
                )
            );
        
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WooCommerce stores REST API keys in a custom table; write query required to generate keys.
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
        
            $response['consumer_key']    = $consumer_key;
            $response['consumer_secret'] = $consumer_secret;
        
            update_option('spbwc_connect_api_keys', $response);
            // Removed automatic user creation - now only happens when admin enables API sync in settings.
        }

        public function spbwc_order_processed($order_id){
            // Only sync orders if API sync is enabled (opt-in).
            $api_settings = get_option('spbwc_pb_settings', array());
            $enable_api_sync = isset($api_settings['enable_api_sync']) && 'yes' === $api_settings['enable_api_sync'];
            
            if (!$enable_api_sync) {
                return; // Exit early if API sync is not enabled.
            }
            
            $order = wc_get_order($order_id);
            $this->spbwc_notify_on_new_order($order);
        } 
        
        public function spbwc_notify_on_new_order($order){
            // Only sync orders if API sync is enabled (opt-in).
            $api_settings = get_option('spbwc_pb_settings', array());
            $enable_api_sync = isset($api_settings['enable_api_sync']) && 'yes' === $api_settings['enable_api_sync'];
            
            if (!$enable_api_sync) {
                return; // Exit early if API sync is not enabled.
            }
            $products = array();
            $cFile = [];
            foreach ($order->get_items() as $item_id => $item) {
                $folder_design = wc_get_order_item_meta($item_id, '_pcpb_folder', true);
                SPBWC_Storelly_Export_PDF::spbwc_export_pdf($folder_design);
                $path_pdf = SPBWC_PB_CUSTOMER_DIR . '/' . $folder_design . '/customer-pdfs';
                $files = SPBWC_Storelly_IO::spbwc_get_list_files_by_type($path_pdf, 'pdf', 1);
        
                foreach ($files as $file) {
                    $cFile[] = SPBWC_Storelly_IO::spbwc_convert_path_to_url($file);
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
            $resp = SPBWC_Storelly_HTTP::spbwc_post_data(SPBWC_API_URL . '/api/v1/update-orders',$body);
        
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
            $resp = SPBWC_Storelly_HTTP::spbwc_post_data(SPBWC_API_URL . '/api/v1/update-orders',$body); 
        }
        public function spbwc_plugin_activation(){ 
        }
        
        public function spbwc_activation_redirect($plugin){
            if ($plugin == plugin_basename(__FILE__)) {
                wp_safe_redirect(admin_url('admin.php?page=storelly-product-builder-for-woocommerce-options/settings'));
                exit;
            }
        }
        
        public function spbwc_img_design_order_items($item_id, $item, $order, $plain_text){
            $folder_design = wc_get_order_item_meta($item_id, '_pcpb_folder', true);
            $path_preview = SPBWC_PB_CUSTOMER_DIR . '/' . $folder_design . '/preview';
            $files = SPBWC_Storelly_IO::spbwc_get_list_files_by_type($path_preview, 'png', 1);
            foreach ($files as $img){
                ?>
                <img width="80" src="<?php echo esc_url(SPBWC_Storelly_IO::spbwc_convert_path_to_url($img)) ;?>" alt="">
                <?php
            }
            
        }
        
    }
}
$spbwc_product_builder_api = SPBWC_Storelly_Product_Builder_API::instance();
$spbwc_product_builder_api->spbwc_init();
