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

            // Sync new orders to the Storelly Dashboard. Both classic and
            // Store-API (blocks) checkout enqueue an async job so the customer's
            // checkout request is never blocked by Cloud2Print PDF rendering or
            // the dashboard HTTP round-trip (runs via Action Scheduler).
            add_action('woocommerce_checkout_order_processed', array($this, 'spbwc_maybe_queue_order_sync'), 10, 1);
            add_action('woocommerce_store_api_checkout_order_processed', array($this, 'spbwc_maybe_queue_order_sync'), 10, 1);
            add_action('spbwc_sync_order_to_storelly', array($this, 'spbwc_run_order_sync'), 10, 1);

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
            // Register with Storelly to obtain unauth_token for API communication.
            // This is required regardless of API sync setting - the token is needed for license and PDF features.
            $option = get_option('spbwc_connect_api_keys');
            if (!is_array($option)) {
                $option = array();
            }
            // Re-register if we don't have a username OR don't have an unauth_token yet.
            if (empty($option['username']) || empty($option['unauth_token'])) {
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
                    "time_zone" => wp_timezone_string(),
                    "surname" => get_user_meta($current_user->ID, 'billing_last_name', true) ? sanitize_text_field(get_user_meta($current_user->ID, 'billing_last_name', true)) : 'lastname',
                    "email" => sanitize_email($current_user->user_email),
                    "first_name" => get_user_meta($current_user->ID, 'billing_first_name', true) ? sanitize_text_field(get_user_meta($current_user->ID, 'billing_first_name', true)) : 'firstname',
                    "username" => $user_name,
                    "password" => $user_name,
                    "fy_start_month" => gmdate('n'),
                    /**
                     * Filter the accounting method sent to the Storelly Dashboard on registration.
                     *
                     * @since 1.2.7
                     * @param string $accounting_method Default 'standard'.
                     */
                    "accounting_method" => apply_filters( 'spbwc_storelly_accounting_method', 'standard' ),
                    "woocommerce_api_settings" => array(
                        "woocommerce_app_url" => esc_url(home_url()),
                        "woocommerce_consumer_key" => isset($option['consumer_key']) ? sanitize_text_field($option['consumer_key']) : '',
                        "woocommerce_consumer_secret" => isset($option['consumer_secret']) ? sanitize_text_field($option['consumer_secret']) : ''
                    )
                );

                // Stable store identifier so a reinstall re-links to the same
                // Storelly store instead of creating a duplicate. Server is
                // expected to treat register as idempotent by store_uuid.
                // See docs/SPEC_M5_CLOUD_CONSENT.md §3.2.
                if (class_exists('SPBWC_Onboarding')) {
                    $datas['store_uuid'] = SPBWC_Onboarding::get_store_uuid();
                }
                if (!empty($option['store_id'])) {
                    $datas['store_id'] = sanitize_text_field($option['store_id']); // manual re-link hint
                }

                $api_url = SPBWC_API_URL . '/api/v1/register';
                $resp = SPBWC_Storelly_HTTP::spbwc_post_data_without_auth($api_url, $datas);
                if (isset($resp) && is_array($resp)) {
                    if (isset($resp['success']) && $resp['success'] == 1) {
                        if (isset($resp['username'])) {
                            $option['username'] = sanitize_text_field($resp['username']);
                        }
                        if (isset($resp['unauth_token'])) {
                            $option['unauth_token'] = sanitize_text_field($resp['unauth_token']);
                        }
                        // Adopt the canonical store id the server resolved (idempotent
                        // by store_uuid) so this site is pinned to the same store.
                        if (isset($resp['store_id'])) {
                            $option['store_id'] = sanitize_text_field($resp['store_id']);
                        }
                        $option['log'] = esc_html__('Connected successfully', 'storelly-product-builder-for-woocommerce') . ' - ' . current_time('mysql');
                        update_option('spbwc_connect_api_keys', $option);

                        // Notify the site admin of their new Storelly account
                        // (only when the server actually provisioned one, i.e. a
                        // username came back AND we just generated the password).
                        if (isset($resp['username'])) {
                            self::spbwc_send_account_email($option['username'], $user_name);
                        }
                    } else {
                        $option['log'] = isset($resp['msg']) ? sanitize_text_field($resp['msg']) : esc_html__('Registration failed: unknown error', 'storelly-product-builder-for-woocommerce');
                        $option['log'] .= ' - ' . current_time('mysql');
                        update_option('spbwc_connect_api_keys', $option);
                    }
                } else {
                    $option['log'] = esc_html__('Connection error: could not reach Storelly API', 'storelly-product-builder-for-woocommerce') . ' - ' . current_time('mysql');
                    update_option('spbwc_connect_api_keys', $option);
                }
            }
        }
        /**
         * Email the site admin the credentials of the Storelly account that was
         * auto-provisioned for them during cloud connect, so they can sign in to
         * the dashboard (app.storelly.com). Sent once, to the site admin email
         * only. Filterable so it can be disabled or re-targeted.
         *
         * @param string $username Storelly username returned by the API.
         * @param string $password Generated password (same value used at register).
         */
        public static function spbwc_send_account_email($username, $password){
            /**
             * Filter whether to email the admin their new Storelly account details.
             *
             * @since 1.6.4
             * @param bool $send Default true.
             */
            if (!apply_filters('spbwc_send_account_email', true)) {
                return;
            }
            $to        = get_option('admin_email');
            if (!$to || !is_email($to)) {
                return;
            }
            $login_url = SPBWC_API_URL . '/login';
            $site      = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
            /* translators: %s: site name. */
            $subject   = sprintf(__('[%s] Your Storelly account is ready', 'storelly-product-builder-for-woocommerce'), $site);
            $lines     = array(
                __('Your store has been connected to Storelly Cloud and a dashboard account was created for you.', 'storelly-product-builder-for-woocommerce'),
                '',
                __('Sign in at:', 'storelly-product-builder-for-woocommerce') . ' ' . esc_url_raw($login_url),
                /* translators: %s: account username. */
                sprintf(__('Username: %s', 'storelly-product-builder-for-woocommerce'), $username),
                /* translators: %s: account password. */
                sprintf(__('Password: %s', 'storelly-product-builder-for-woocommerce'), $password),
                '',
                __('For your security, please sign in and change this password.', 'storelly-product-builder-for-woocommerce'),
            );
            $message = implode("\n", $lines);
            wp_mail($to, $subject, $message);
        }

        public static function spbwc_generate_key(){

            $response = get_option('spbwc_connect_api_keys');
            if (!is_array($response)) {
                $response = array();
            }
            // Only skip when WC REST keys already exist. A previous failed
            // registration may have written only `log`/`username` to this option;
            // returning on a merely non-empty array (the old check) would then
            // leave the store without consumer keys and register with blanks.
            if (!empty($response['consumer_key']) && !empty($response['consumer_secret'])) {
                return;
            }

            global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
            $description = __('Storelly', 'storelly-product-builder-for-woocommerce');
            $permissions = 'read_write';
            $user_id     = get_current_user_id();
            // Keep any data already stored (username/unauth_token/store_id/log);
            // we only (re)write the WC REST key pair below.
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

        /** True when the merchant opted into Dashboard order sync. */
        protected static function spbwc_api_sync_enabled() {
            $api_settings = get_option('spbwc_pb_settings', array());
            return isset($api_settings['enable_api_sync']) && 'yes' === $api_settings['enable_api_sync'];
        }

        /**
         * Checkout hook (classic + Store API). Normalises the argument to an
         * order id and queues an async sync so checkout stays fast. The classic
         * hook passes an order id; the Store-API hook passes a WC_Order.
         *
         * @param int|WC_Order $order_or_id
         */
        public function spbwc_maybe_queue_order_sync($order_or_id){
            if (!self::spbwc_api_sync_enabled()) {
                return; // Opt-in gate.
            }
            $order_id = ($order_or_id instanceof WC_Order) ? $order_or_id->get_id() : absint($order_or_id);
            if (!$order_id) {
                return;
            }
            if (function_exists('as_enqueue_async_action')) {
                // De-dupe: don't queue a second job if one is already pending.
                if (function_exists('as_has_scheduled_action') && as_has_scheduled_action('spbwc_sync_order_to_storelly', array($order_id), 'spbwc-order-sync')) {
                    return;
                }
                as_enqueue_async_action('spbwc_sync_order_to_storelly', array($order_id), 'spbwc-order-sync');
            } else {
                // No Action Scheduler — fall back to inline sync.
                $this->spbwc_run_order_sync($order_id);
            }
        }

        /**
         * Action Scheduler worker: build the order payload and POST it to the
         * Storelly Dashboard. Re-checks the opt-in gate at run time.
         *
         * @param int $order_id
         */
        public function spbwc_run_order_sync($order_id){
            if (!self::spbwc_api_sync_enabled()) {
                return;
            }
            $order = wc_get_order(absint($order_id));
            if (!$order) {
                return;
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
        }
        public function spbwc_plugin_activation(){ 
        }
        
        public function spbwc_activation_redirect($plugin){
            if ($plugin == plugin_basename(__FILE__)) {
                wp_safe_redirect(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG));
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
