<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('SPBWC_Storelly_PB_Admin_Options')) {
    class SPBWC_Storelly_PB_Admin_Options {
        protected static $instance;
        private const CACHE_GROUP = 'spbwc_product_builder';
        private const CACHE_TTL   = 900; // 15 minutes.
        public function __construct() {
            //todo something
        }
        public static function instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        public function spbwc_init() {
            if (is_admin()) {
                $this->spbwc_ajax();
            }
            // Create a menu for the Product builder
            add_action('spbwc_pb_menu', array($this, 'spbwc_tab_menu'));
            add_action('spbwc_create_tables', array($this, 'spbwc_create_options_table'));
            add_action('admin_enqueue_scripts', array($this, 'spbwc_admin_enqueue_scripts'));
            add_action('add_meta_boxes', array($this, 'spbwc_add_meta_boxes'), 35);
            // Add options design in Order WC
            add_action('add_meta_boxes', array($this, 'spbwc_add_design_box'), 38);
            add_action('save_post', array($this, 'spbwc_save_product_option'));

            // Alter the product thumbnail in order
            add_filter('woocommerce_spbwc_admin_order_item_thumbnail', array($this, 'spbwc_admin_order_item_thumbnail'), 50, 3);
            //Hide some price option data in order
            add_filter('woocommerce_hidden_order_itemmeta', array($this, 'spbwc_hidden_custom_order_item_metada'));
             //Add title page
            add_filter( 'display_post_states', array( $this, 'spbwc_add_display_post_states' ), 10, 2 );
        }
        public function spbwc_ajax() {
            $ajax_events = array(
                'spbwc_download_option_image'         => true,
                'spbwc_get_media_full_size_url'       => true,
                'spbwc_add_google_font'         => true,
                'spbwc_download_order_designs'  => true,
            );
            foreach ($ajax_events as $ajax_event => $nopriv) {
                add_action('wp_ajax_' . $ajax_event, array($this, $ajax_event));
                if ($nopriv) {
                    // AJAX can be used for frontend ajax requests
                    add_action('wp_ajax_nopriv_' . $ajax_event, array($this, $ajax_event));
                }
            }
        }
        protected function spbwc_cache_key_option($option_id) {
            return 'spbwc_option_' . absint($option_id);
        }
        protected function spbwc_extract_product_ids_from_option($option) {
            if (empty($option) || !is_array($option) || !isset($option['product_ids'])) {
                return array();
            }
            $product_ids = maybe_unserialize($option['product_ids']);
            if (!is_array($product_ids)) {
                return array();
            }
            $product_ids = array_map('absint', $product_ids);
            return array_filter(array_unique($product_ids));
        }
        protected function spbwc_flush_option_caches($option_id = 0, $product_ids = array()) {
            if ($option_id > 0) {
                wp_cache_delete($this->spbwc_cache_key_option($option_id), self::CACHE_GROUP);
            }
            wp_cache_delete('spbwc_published_options', self::CACHE_GROUP);
            if (!empty($product_ids)) {
                $product_ids = array_map('absint', (array) $product_ids);
                $product_ids = array_filter(array_unique($product_ids));
                foreach ($product_ids as $product_id) {
                    delete_transient('spbwc_product_builder_' . $product_id);
                }
            }
        }
        protected function spbwc_get_cached_published_options() {
            $cache_key = 'spbwc_published_options';
            $cached    = wp_cache_get($cache_key, self::CACHE_GROUP);
            if (false !== $cached) {
                return $cached;
            }
            global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
            $table_name = $wpdb->prefix . 'storelly_product_builder_options';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table read cached immediately below.
            $options = $wpdb->get_results($wpdb->prepare("SELECT id, product_ids FROM {$table_name} WHERE published = %d", 1), ARRAY_A);
            if (!is_array($options)) {
                $options = array();
            }
            wp_cache_set($cache_key, $options, self::CACHE_GROUP, self::CACHE_TTL);
            return $options;
        }
        public function spbwc_add_display_post_states( $post_states, $post ){
            
            if (SPBWC_Storelly_PB_Util::spbwc_get_page_id('product_builder') === $post->ID ) {
                $post_states['spbwc_product_builder_page'] = esc_html__( 'Storelly Product builder Page', 'storelly-product-builder-for-woocommerce' );
            }
            return $post_states;
        }
        public function spbwc_add_design_box() {
            $cot_class = 'Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\CustomOrdersTableController';

            if ( class_exists( $cot_class ) ) {
                $controller = wc_get_container()->get( $cot_class );

                if ( $controller && $controller->custom_orders_table_usage_is_enabled() ) {
                    $screen = wc_get_page_screen_id( 'shop-order' );
                } else {
                    $screen = 'shop_order';
                }

            } else {
                $screen = 'shop_order';
            }

            add_meta_box(
                'spbwc_product_builder_design',
                esc_html__( 'Product builder designs', 'storelly-product-builder-for-woocommerce' ),
                array( $this, 'spbwc_product_builder_design' ),
                $screen,
                'side',
                'default'
            );
        }
       public function spbwc_product_builder_design($post = null) {
            // Lấy order id (WC new Orders screen passes id via URL)
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin read-only context.
            $order_id = absint( isset( $_GET['id'] ) ? wp_unslash( $_GET['id'] ) : 0 );

            // Nếu bạn vẫn ở context cũ (meta box truyền $post), fallback:
            if ( ! $order_id && is_object( $post ) && property_exists( $post, 'ID' ) ) {
                $order_id = absint( $post->ID );
            }

            if ( ! $order_id ) {
                echo '<p>Order ID not found.</p>';
                return;
            }

            $order = wc_get_order( $order_id );

            if ( ! $order ) {
                echo '<p>Order not found.</p>';
                return;
            }

            // LẤY DỮ LIỆU TRƯỚC KHI INCLUDE
            $order_id    = $order->get_id();
            $order_items = $order->get_items(); // <- chắc chắn có

            // Pass sang view bằng include (biến hiện có trong scope của file được include)
            include_once( SPBWC_PB_PLUGIN_DIR . 'views/box-order-metadata.php' );
        }
        public function spbwc_get_media_full_size_url() {
            if (!current_user_can('upload_files')) {
                wp_send_json_error(array('mes' => esc_html__('You do not have permission.', 'storelly-product-builder-for-woocommerce')));
            }
            $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! $nonce || ! wp_verify_nonce( $nonce, 'spbwc_save_design_action' ) ) {
                wp_die( esc_html__( 'Security error.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $result = array(
                'flag'      => 1,
                'images'    => array()
            );
            // Nonce checked above in spbwc_get_media_full_size_url.
            $raw_images = isset($_POST['images']) ? wp_unslash($_POST['images']) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above. Input sanitized via array_map('absint') immediately after.
            $images     = is_array( $raw_images ) ? array_map( 'absint', $raw_images ) : array();
            if ( ! empty( $images ) ) {
                foreach ( $images as $key => $image_id ) {
                    $image_id = absint( $image_id );
                    if ( $image_id ) {
                        $result['images'][ $key ] = esc_url( wp_get_attachment_url( $image_id ) );
                    }
                }
            } else {
                $result['flag'] = 0;
            }
            echo wp_json_encode($result);
            wp_die();
        }
        public function spbwc_download_option_image() { 
            if (!current_user_can('upload_files')) {
                wp_send_json_error(array('mes' => esc_html__('You do not have permission.', 'storelly-product-builder-for-woocommerce')));
            }
            $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! $nonce || ! wp_verify_nonce( $nonce, 'spbwc_save_design_action' ) ) {
                wp_die( esc_html__( 'Security error.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $result = array(
                'flag'      => 1,
                'image'     => array()
            );
            $url = isset($_POST['image']) ? esc_url_raw(wp_unslash($_POST['image'])) : '';
            
            require_once(SPBWC_PB_PLUGIN_DIR . 'includes/class-download-image.php');
            if (strpos($url, get_site_url()) > -1) {
                $result['image'] = array(
                    'current_site'  => 1
                );
            } else {
                $download_remote_image = new SPBWC_Storelly_PB_Download_Image($url, array());
                $attachment_id = $download_remote_image->download();
                if ($attachment_id) {
                    $result['image'] = array(
                        'current_site'  => 0,
                        'id'            => $attachment_id
                    );
                } else {
                    $result['flag'] = 0;
                }
            }
            echo wp_json_encode($result);
            wp_die();
        }
        public function spbwc_tab_menu() {
            if (current_user_can('spbwc_manage_product_builder')) {
                add_menu_page(
                    'PC Product Builder',
                    'Product Builder Options',
                    'spbwc_manage_product_builder',
                    'storelly-product-builder-for-woocommerce-options',
                    array($this, 'spbwc_product_builder_options'),
                    SPBWC_PB_PLUGIN_URL . '/assets/images/logo.svg'
                );
                add_submenu_page(
                    'storelly-product-builder-for-woocommerce-options',
                    esc_html__('Builder options', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Builder options', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    'storelly-product-builder-for-woocommerce-options',
                    array($this, 'spbwc_product_builder_options')
                );
                add_submenu_page(
                    'storelly-product-builder-for-woocommerce-options',
                    esc_html__('Fonts', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Fonts', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    'storelly-product-builder-for-woocommerce-options/manager-fonts',
                    array($this, 'spbwc_manager_fonts')
                );
                add_submenu_page(
                    'storelly-product-builder-for-woocommerce-options',
                    esc_html__('Settings', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Settings', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    'storelly-product-builder-for-woocommerce-options/settings',
                    array($this, 'spbwc_settings')
                );
            }
        }
        public function spbwc_create_options_table() {
            global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
            $collate = '';
            if ($wpdb->has_cap('collation')) {
                $collate = $wpdb->get_charset_collate();
            }
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            if (SPBWC_PB_VERSION != get_option("spbwc_version_plugin")) {
                $tables =  "
                    CREATE TABLE {$wpdb->prefix}storelly_product_builder_options ( 
                    id bigint(20) unsigned NOT NULL auto_increment,
                    title text NOT NULL,
                    published  TINYINT(1) NOT NULL default 1,
                    product_ids text NULL, 
                    created datetime NOT NULL default '0000-00-00 00:00:00',
                    modified datetime NOT NULL default '0000-00-00 00:00:00', 
                    created_by BIGINT(20) NULL, 
                    modified_by BIGINT(20) NULL,  
                    fields longtext,
                    builder text NULL,
                    PRIMARY KEY  (id)
                    ) $collate; 
                ";
                @dbDelta($tables);
            }
        }
        public function spbwc_admin_enqueue_scripts($hook) {
            wp_register_script('spbwc-ag', SPBWC_PB_PLUGIN_URL . 'assets/libs/builderproductag.min.js', array('jquery'), '1.6.9', true);  
            wp_register_script('spbwc-snap-svg', SPBWC_PB_ASSETS_URL . 'libs/snap.svg.js', array(), '0.3.0', true);
            wp_register_script('spbwc-tiptip', SPBWC_PB_ASSETS_URL . 'js/tiptip.js', array('jquery'), SPBWC_PB_VERSION, true);
            wp_register_script('spbwc-fontfaceobserver', SPBWC_PB_PLUGIN_URL . 'assets/libs/fontfaceobserver.js', array(), '2.0.13', true);
            wp_register_script('spbwc-sweetalert-js', SPBWC_PB_PLUGIN_URL . 'assets/libs/sweetalert.min.js', array(), '5.6.10', true);
            wp_register_script('spbwc-general-js', SPBWC_PB_ASSETS_URL . 'js/storelly-general.js', array('jquery'), SPBWC_PB_VERSION, true);

            wp_register_style('spbwc-options-style', SPBWC_PB_CSS_URL . 'admin-options.css', array('wp-color-picker', 'wp-jquery-ui-dialog'), SPBWC_PB_VERSION);
            wp_register_style('spbwc-general-css', SPBWC_PB_CSS_URL . 'storelly-general.css', array('dashicons'), SPBWC_PB_VERSION);
            wp_register_style('spbwc-sweetalert-css', SPBWC_PB_CSS_URL . 'sweetalert.css', array(), '5.6.10');
            wp_register_style('spbwc-manager-fonts', SPBWC_PB_CSS_URL . 'manager-fonts.css', array('spbwc-sweetalert-css'), SPBWC_PB_VERSION);

            // style menu setting
            wp_enqueue_style('menu-setting',  SPBWC_PB_CSS_URL . '/menu-setting.css', array(), '1.0', 'all');

            wp_localize_script('spbwc-general-js', 'storelly_admin', array(
                'url'       => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce( 'spbwc_download_order_designs' ),
            ));
            wp_enqueue_style('spbwc-general-css');
            wp_enqueue_script('spbwc-general-js');

            if ($hook == 'toplevel_page_storelly-product-builder-for-woocommerce-options') {
                wp_register_script('spbwc-options-script', SPBWC_PB_JS_URL . 'admin-options.js', array('jquery', 'wpdialogs', 'jquery-ui-resizable', 'jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-sortable', 'jquery-ui-datepicker', 'jquery-ui-autocomplete', 'wp-color-picker', 'spbwc-ag', 'wc-enhanced-select', 'spbwc-snap-svg', 'spbwc-tiptip'), SPBWC_PB_VERSION, true);
                wp_localize_script('spbwc-options-script', 'storelly_options', array(
                    'search_products_nonce'     => wp_create_nonce("search-products"),
                    'calendar_image'            => SPBWC_PB_PLUGIN_URL . 'assets/images/calendar.png',
                    'storelly_options_lang'    => $this->spbwc_storelly_option_i18n(),
                ));
               wp_enqueue_style('spbwc-options-style');
               wp_enqueue_script('spbwc-options-script');
            }
            if ($hook == 'product-builder-options_page_storelly-product-builder-for-woocommerce-options/manager-fonts') {
                wp_register_script('spbwc-manager-fonts-script', SPBWC_PB_JS_URL . 'manager-fonts.js', array('spbwc-fontfaceobserver', 'spbwc-sweetalert-js', 'spbwc-ag'), SPBWC_PB_VERSION, true);
                wp_localize_script('spbwc-manager-fonts-script', 'storelly_pb_fonts', array(
                    'url'       => admin_url('admin-ajax.php'),
                    'nonce'     => wp_create_nonce('spbwc_update_fonts'),
                    'complete'  => esc_html__('Complete!', 'storelly-product-builder-for-woocommerce'),
                ));
                wp_enqueue_script('spbwc-manager-fonts-script');
                wp_enqueue_style('spbwc-manager-fonts');
            }
        }
        public function spbwc_product_builder_options() {
            if (isset($_GET['action']) && sanitize_text_field(wp_unslash($_GET['action'])) != 'copy') {
                
                $paged      = get_query_var('paged', 1);
                $ID = isset( $_REQUEST['id'] ) ? absint(wp_unslash($_REQUEST['id'])) : 0;
                $message    = array('content' => ''); 
                $current_action = sanitize_text_field(wp_unslash($_GET['action']));
                
                if (isset($_GET['message']) && sanitize_text_field(wp_unslash($_GET['message'])) == 'created') {
                    $message = array(
                        'flag'      => 'success',
                        'content'   => esc_html__('Option created successfully.', 'storelly-product-builder-for-woocommerce')
                    );
                }
                
                if ($current_action == 'unpublish') {
                    $nonce_value = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
                    if (
                        ! $nonce_value ||
                        ! wp_verify_nonce( $nonce_value, 'spbwc_unpublish_option_action' ) ||
                        ! current_user_can( 'spbwc_manage_product_builder' )
                    ) {
                        wp_die( esc_html__( 'Security check failed.', 'storelly-product-builder-for-woocommerce' ) );
                    }
                    $this->spbwc_unpublish_option($ID);
                    wp_safe_redirect(esc_url_raw(add_query_arg(array('paged' => $paged), admin_url('admin.php?page=storelly-product-builder-for-woocommerce-options'))));
                    exit;
                } 
                
                if ($current_action == 'edit' || $current_action == 'create') {
                    
                    $id = ($current_action == 'edit' && $ID > 0) ? $ID : 0;
                    
                    if (isset($_POST['save']) || isset($_POST['options'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked immediately below.
                        // Kiểm tra Nonce (Bảo mật)
            if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['_wpnonce']) ), 'spbwc_save_option_action' ) ) {
                            wp_die( esc_html__( 'Security check failed.', 'storelly-product-builder-for-woocommerce' ) );
                        }
                        
                        $result = $this->spbwc_save_option( $id );
                        
                        if ($result['status']) {
                            
                            if ($id == 0) {
                                $id = $result['id'];
                                
                                wp_safe_redirect(esc_url_raw(add_query_arg(array(
                                    'paged'     => 1,
                                    'action'    => 'edit', // Chuyển sang action edit sau khi tạo
                                    'id'        => $id,
                                    'message'   => 'created' // Truyền thông báo
                                ), admin_url('admin.php?page=storelly-product-builder-for-woocommerce-options'))));
                                exit;
                            } else {
                                $message = array(
                                    'flag'      => 'success',
                                    'content'   => esc_html__('Option updated.', 'storelly-product-builder-for-woocommerce')
                                );
                            }
                        } else {
                            $message = array(
                                'flag'      => 'error',
                                'content'   => ''
                            );
                        }
                    }
                    
                    $_options = ($id > 0) ? (array) $this->spbwc_get_option($id) : false;
                    
                    if ($_options) {
                        $raw_options = unserialize($_options['fields']);
                        if (!isset($raw_options["fields"])) {
                            $raw_options["fields"] = array();
                        }
                        $options                        = $this->spbwc_build_options($raw_options);
                        $options['id']                  = $_options['id'];
                        $options['title']               = $_options['title'];
                        $options['published']           = $_options['published'];
                        $options['created']             = $_options['created'];
                        $options['modified']            = $_options['modified'];
                        $options['product_ids']         = isset($_options['product_ids']) ? (is_array(maybe_unserialize($_options['product_ids'])) ? maybe_unserialize($_options['product_ids']) : array()) : array();
                    } else {
                        $options = $this->spbwc_build_options();
                        $options['id']                  = 0;
                        $options['title']               = '';
                        $options['product_ids']         = array();
                        $options['published']           = 1;
                        $options['created']             = '';
                        $options['modified']            = '';
                    }
                    
                    $default_field = $this->spbwc_default_config_field();
                    $product_id = (isset($_GET['product_id']) && absint(wp_unslash($_GET['product_id'])) > 0) ? absint(wp_unslash($_GET['product_id'])) : 0;
                    if ($product_id) {
                        if (!$_options) {
                            $options['product_ids'] = array(0 => $product_id);
                        }
                    }
                    
                    wp_register_script('spbwc_option_field_script', SPBWC_PB_JS_URL . 'admin-options.js', array(), SPBWC_PB_VERSION, true);
                    wp_localize_script('spbwc_option_field_script', 'storelly_option_variable', array(
                        'STORELLY_OPTIONS' =>  $options,
                        'STORELLY_OPTION_FIELD' => $default_field,
                        'ajax_url' => esc_url(admin_url('admin-ajax.php')),
                        'nbnonce' => esc_attr(wp_create_nonce('spbwc_save_design_action')),
                        'max_input_vars' => (int) SPBWC_Storelly_PB_Util::spbwc_get_max_input_var()
                    ));
                    wp_enqueue_script("spbwc_option_field_script");
                    include_once(SPBWC_PB_PLUGIN_DIR . 'views/options/edit-option.php');
                } 
            } else {
                require_once SPBWC_PB_PLUGIN_DIR . 'includes/options/fields-list-table.php';
                $spbwc_options = new SPBWC_Storelly_Options_List_Table();
                include_once(SPBWC_PB_PLUGIN_DIR . 'views/options/options-list-table.php');
            }
        }
        public function spbwc_unpublish_option($id) { 
            global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
            $option_id   = absint($id);
            $option      = $this->spbwc_get_option($option_id);
            $product_ids = $this->spbwc_extract_product_ids_from_option($option);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write; caches/transients are flushed below.
            $result = $wpdb->update(
                $wpdb->prefix . 'pc-product_builder_options',
                array('published' => 0),
                array('id' => $option_id),
                array('%d'),
                array('%d')
            ); 
            if (false !== $result) {
                $this->spbwc_flush_option_caches($option_id, $product_ids);
            }
        }
        public function spbwc_get_option($id) { 
            $option_id = absint($id);
            if (0 === $option_id) {
                return false;
            }
            $cache_key = $this->spbwc_cache_key_option($option_id);
            $cached    = wp_cache_get($cache_key, self::CACHE_GROUP);
            if (false !== $cached) {
                return !empty($cached) ? $cached : false;
            }
            global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
            $table_name = $wpdb->prefix . 'storelly_product_builder_options';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name built from $wpdb->prefix; values are parameterized below.
            $option = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE `id` = %d LIMIT 1", $option_id ), ARRAY_A );
            if (!is_array($option)) {
                $option = array();
            }
            wp_cache_set($cache_key, $option, self::CACHE_GROUP, self::CACHE_TTL);
            return !empty($option) ? $option : false; 
        }
        public function spbwc_save_option( $id = 0 ) {
            if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
                 return array( 'status' => false, 'id' => 0 );
            }
            $modified_date  = new DateTime();
            $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in spbwc_product_builder_options.
            $product_ids = isset($_POST['product_ids']) ? array_map('absint', (array) wp_unslash($_POST['product_ids'])) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in spbwc_product_builder_options.
            $arr            = array(
                'title'       => $title,
                'published'   => 1,
                'product_ids' => serialize($product_ids),
                'modified'    => $modified_date->format('Y-m-d H:i:s')
            );
            $post_options_raw = isset( $_POST['options'] ) ? wp_unslash( $_POST['options'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in spbwc_product_builder_options and data sanitized recursively via spbwc_sanitize_recursive.
            $post_options = spbwc_sanitize_recursive( $post_options_raw );
            if (isset($post_options['jsonFields'])) {
                $post_options['fields'] = json_decode(stripslashes($post_options['jsonFields']), true); 
                unset($post_options['jsonFields']);
            }

            $arr['fields'] = serialize($post_options);
            global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
            $date = new DateTime();
            $previous_product_ids = array();
            if ($id > 0) {
                $previous_option      = $this->spbwc_get_option($id);
                $previous_product_ids = $this->spbwc_extract_product_ids_from_option($previous_option);
            }
            if ($id > 0) {
                $arr['modified']    = $date->format('Y-m-d H:i:s');
                $arr['modified_by'] = wp_get_current_user()->ID;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write; caches/transients are flushed below.
                $result             = $wpdb->update("{$wpdb->prefix}storelly_product_builder_options", $arr, array('id' => $id));
            } else {
                $arr['created']     = $date->format('Y-m-d H:i:s');
                $arr['created_by']  = wp_get_current_user()->ID;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write; caches/transients are flushed below.
                $result             = $wpdb->insert("{$wpdb->prefix}storelly_product_builder_options", $arr);
                $id                 = $result ?  $wpdb->insert_id : 0;
            }
            if ($result) {
                $affected_products = array_merge($product_ids, $previous_product_ids);
                $this->spbwc_flush_option_caches($id, $affected_products);
            }
            do_action('storelly_save_print_option', $arr);
            return array(
                'status'    => $result,
                'id'        => $id
            );
        }
        public function spbwc_build_options($options = null) {
            if (is_null($options)) {
                $options = array(
                    'version'                   => SPBWC_PB_NUMBER_VERSION,
                    'fields'                    => array(
                        0   =>  $this->spbwc_clear_transients()
                    ),
                    'design_output'  => array(
                        'dpi'                   => 300,
                        'dimension_unit'        => 'px',
                    )
                );
            }
            $options['fields'] = $this->spbwc_recursive_stripslashes($options['fields']);
            foreach ($options['fields'] as $f_key => $field) {
                $field = array_replace_recursive($this->spbwc_clear_transients(), $field);
                foreach ($field as $tab =>  $data) {
                    if ($tab != 'id' && $tab != 'nbpb_type' && $tab != 'nbd_template') {
                        foreach ($data as $key => $f) {
                            $funcname = "build_config_" . $tab . '_' . $key;
                            if (is_callable(array($this, $funcname))) {
                                $options['fields'][$f_key][$tab][$key] = $this->$funcname($f);
                            }
                            if( $key == 'component_icon' ){
                                $options['fields'][$f_key][$tab]['component_icon_url'] =  SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail( $f );
                            }
                        }
                    }
                    if ($tab == 'nbpb_type') {
                        $options['fields'][$f_key]['nbd_template'] = 'nbd.' . $data;
                    }
                }
            }
            if (isset($options['views'])) {
                foreach ($options['views'] as $vkey => $view) {
                    $view['base'] = isset($view['base']) ? $view['base'] : 0;
                    $options['views'][$vkey]['base'] = $view['base'];
                    $options['views'][$vkey]['base_url'] = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail($view['base']);
                }
            }
            return $options;
        }
        public function spbwc_build_config_conditional_depend($value = null) {
            if (is_null($value) || count($value) == 0) $value = array(
                0   =>  array(
                    'id'        => '',
                    'operator'  => 'i',
                    'val'       => ''
                )
            );
            return $value;
        }
        public function spbwc_default_config_field() {
            $field = $this->spbwc_clear_transients();
            foreach ($field as $tab =>  $data) {
                if ($tab != 'id' && $tab != 'nbpb_type' && $tab != 'nbd_template') {
                    foreach ($data as $key => $f) {
                        $funcname = "build_config_" . $tab . '_' . $key;
                        if (is_callable(array($this, $funcname))) {
                            $field[$tab][$key] = $this->$funcname($f);
                        }
                    }
                }
            }
            return $field;
        }
        public function spbwc_recursive_stripslashes($fields) {
            $valid_fields = array();
            foreach ($fields as $key => $field) {
                if (is_array($field)) {
                    $valid_fields[$key] = $this->spbwc_recursive_stripslashes($field);
                } else if (!is_null($field)) {
                    $valid_fields[$key] = stripslashes($field);
                }
            }
            return $valid_fields;
        }
        public function spbwc_clear_transients() {
            return array(
                'id'            => 'f' . round(microtime(true) * 1000),
                'general'       => array(
                    'title'             => null,
                    'description'       => null,
                    'data_type'         => null,
                    'input_type'        => null,
                    'input_option'      => null,
                    'text_option'       => null,
                    'upload_option'     => null,
                    'enabled'           => null,
                    'required'          => null,
                    'published'         => null,
                    'price_type'        => null,
                    'price'             => null,
                    'attributes'        => null
                ),
                'appearance' => array(
                    'display_type'          => null,
                    'change_image_product'  => null,
                    'css_class'             => null
                ),
            );
        }
        public function build_config_general_title($value = null) {
            if (is_null($value)) $value = __('Option name', 'storelly-product-builder-for-woocommerce');
            return array(
                'title'         => __('Option name', 'storelly-product-builder-for-woocommerce'),
                'description'   =>  '',
                'value'         => $value,
                'type'          => 'text'
            );
        }
        public function build_config_general_description($value = null) {
            if (is_null($value)) $value = __('Option description', 'storelly-product-builder-for-woocommerce');
            return array(
                'title'         => __('Description', 'storelly-product-builder-for-woocommerce'),
                'description'   => '',
                'value'         => $value,
                'type'          => 'textarea'
            );
        }
        public function build_config_general_data_type($value = null) {
            if (is_null($value)) $value = 'm';
            return array(
                'title'         => esc_html__('Data type', 'storelly-product-builder-for-woocommerce'),
                'description'   => '',
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'       => 'i',
                        'text'      => esc_html__('Custom input', 'storelly-product-builder-for-woocommerce')
                    ),
                    array(
                        'key'       => 'm',
                        'text'      => esc_html__('Multiple options', 'storelly-product-builder-for-woocommerce')
                    )
                )
            );
        }
        public function build_config_general_input_type($value = null) {
            if (is_null($value)) $value = 't';
            return array(
                'title'         => esc_html__('Input type', 'storelly-product-builder-for-woocommerce'),
                'description'   =>  '',
                'value'         => $value,
                'type'          => 'dropdown',
                'depend'        => array(
                    array(
                        'field'     =>  'data_type',
                        'operator'  =>  '=',
                        'value'     =>  'i'
                    )
                ),
                'options'       => array(
                    array(
                        'key'       => 't',
                        'text'      => esc_html__('Text', 'storelly-product-builder-for-woocommerce')
                    ),
                    array(
                        'key'       => 'u',
                        'text'      => esc_html__('Upload', 'storelly-product-builder-for-woocommerce')
                    ),
                    array(
                        'key'       => 'a',
                        'text'      => esc_html__('Textarea', 'storelly-product-builder-for-woocommerce')
                    )
                )
            );
        }
        public function build_config_general_input_option($value = null) {
            if (is_null($value)) {
                $value = array(
                    'min'       => 1,
                    'max'       => 100,
                    'step'      => 1,
                    'default'   => 1
                );
            }
            if (!isset($value['default'])) $value['default'] = $value['min'];
            return array(
                'title'         => esc_html__('Input option', 'storelly-product-builder-for-woocommerce'),
                'description'   => '',
                'value'         => $value,
                'type'          => 'table',
                'depend'        => array(
                    array(
                        'field'     => 'data_type',
                        'operator'  => '=',
                        'value'     => 'i'
                    ),
                    array(
                        'field'     => 'input_type',
                        'operator'  => '#',
                        'value'     => 't'
                    ),
                    array(
                        'field'     => 'input_type',
                        'operator'  => '#',
                        'value'     => 'u'
                    ),
                    array(
                        'field'     => 'input_type',
                        'operator'  => '#',
                        'value'     => 'a'
                    )
                )
            );
        }
        public function build_config_general_text_option($value = null) {
            if (is_null($value)) {
                $value = array(
                    'min'   =>  0,
                    'max'   =>  999
                );
            }
            return array(
                'title'         => esc_html__('Text input option', 'storelly-product-builder-for-woocommerce'),
                'description'   =>  '',
                'value'         => $value,
                'type'          => 'table',
                'depend'        =>  array(
                    array(
                        'field'     =>  'data_type',
                        'operator'  =>  '=',
                        'value'     =>  'i'
                    ),
                    array(
                        'field'     =>  'input_type',
                        'operator'  =>  '=',
                        'value'     =>  't,a'
                    )
                )
            );
        }
        public function build_config_general_enabled($value = null) {
            if (is_null($value)) $value = 'y';
            return array(
                'title'         => __('Enabled', 'storelly-product-builder-for-woocommerce'),
                'description'   => __('Choose whether the option is enabled or not.', 'storelly-product-builder-for-woocommerce'),
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'       => 'y',
                        'text'      => __('Yes', 'storelly-product-builder-for-woocommerce')
                    ),
                    array(
                        'key'       => 'n',
                        'text'      => __('No', 'storelly-product-builder-for-woocommerce')
                    )
                )
            );
        }
        public function build_config_general_published($value = null) {
            if (is_null($value)) $value = 'y';
            return array(
                'title'         => __('Published', 'storelly-product-builder-for-woocommerce'),
                'description'   => __('Show in summary options or not.', 'storelly-product-builder-for-woocommerce'),
                'value'         => $value,
                'type'          => 'dropdown',
                'options' =>    array(
                    array(
                        'key'       => 'y',
                        'text'      => __('Yes', 'storelly-product-builder-for-woocommerce')
                    ),
                    array(
                        'key'       => 'n',
                        'text'      => __('No', 'storelly-product-builder-for-woocommerce')
                    )
                )
            );
        }
        public function build_config_general_required($value = null) {
            if (is_null($value)) $value = 'n';
            return array(
                'title'         => __('Required', 'storelly-product-builder-for-woocommerce'),
                'description'   => __('Choose whether the option is required or not.', 'storelly-product-builder-for-woocommerce'),
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'       => 'y',
                        'text'      => __('Yes', 'storelly-product-builder-for-woocommerce')
                    ),
                    array(
                        'key'       => 'n',
                        'text'      => __('No', 'storelly-product-builder-for-woocommerce')
                    )
                ),
                'depend'        => array(
                    array(
                        'field'     => 'published',
                        'operator'  => '#',
                        'value'     => 'n'
                    )
                )
            );
        }
        public function build_config_general_upload_option($value = null) {
            if (is_null($value)) {
                $value = array(
                    'min_size'      =>  0,
                    'max_size'      =>  SPBWC_Storelly_PB_Util::spbwc_get_max_upload_default(),
                    'allow_type'    =>  'png,jpg,jpeg'
                );
            }
            return array(
                'title'         => esc_html__('Upload file option', 'storelly-product-builder-for-woocommerce'),
                'description'   =>  '',
                'value'         => $value,
                'type'          => 'table',
                'depend'        =>  array(
                    array(
                        'field'     =>  'data_type',
                        'operator'  =>  '=',
                        'value'     =>  'i'
                    ),
                    array(
                        'field'     =>  'input_type',
                        'operator'  =>  '=',
                        'value'     =>  'u'
                    )
                )
            );
        }
        public function build_config_general_price_type($value = null) {
            if (is_null($value)) $value = 'f';
            return array(
                'title'         => esc_html__('Price type', 'storelly-product-builder-for-woocommerce'),
                'description'   => esc_html__('Here you can choose how the price is calculated. Depending on the field there various types you can choose.', 'storelly-product-builder-for-woocommerce'),
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'       => 'f',
                        'text'      => esc_html__('Fixed amount', 'storelly-product-builder-for-woocommerce')
                    ),
                    array(
                        'key'       => 'p',
                        'text'      => esc_html__('Percent of the original price', 'storelly-product-builder-for-woocommerce')
                    ),
                    array(
                        'key'       => 'p+',
                        'text'      => esc_html__('Percent of the original price + options', 'storelly-product-builder-for-woocommerce')
                    ),
                    array(
                        'key'       => 'c',
                        'text'      => esc_html__('Current value * price', 'storelly-product-builder-for-woocommerce'),
                        'depend'    => array(
                            array(
                                'field'     => 'data_type',
                                'operator'  => '=',
                                'value'     => 'i'
                            ),
                            array(
                                'field'     => 'input_type',
                                'operator'  => '#',
                                'value'     => 'u'
                            ),
                            array(
                                'field'     => 'input_type',
                                'operator'  => '#',
                                'value'     => 't'
                            ),
                            array(
                                'field'     => 'input_type',
                                'operator'  => '#',
                                'value'     => 'a'
                            )
                        )
                    ),
                    array(
                        'key'       => 'cp',
                        'text'      => esc_html__('Price per char', 'storelly-product-builder-for-woocommerce'),
                        'depend'    => array(
                            array(
                                'field'     => 'data_type',
                                'operator'  => '=',
                                'value'     => 'i'
                            ),
                            array(
                                'field'     => 'input_type',
                                'operator'  => '=',
                                'value'     => 't'
                            )
                        )
                    ),
                )
            );
        }
        public function build_config_general_price($value = null) {
            if (is_null($value)) $value = '';
            return array(
                'title'         => esc_html__('Additional Price', 'storelly-product-builder-for-woocommerce'),
                'description'   => esc_html__('Enter the price for this field or leave it blank for no price.', 'storelly-product-builder-for-woocommerce'),
                'value'         => $value,
                'depend'        => array(
                    array(
                        'field'     => 'depend_quantity',
                        'operator'  => '#',
                        'value'     => 'y'
                    ),
                    array(
                        'field'     => 'data_type',
                        'operator'  => '=',
                        'value'     => 'i'
                    )
                ),
                'type'          => 'number'
            );
        }
        public function build_config_general_attributes($attributes = null) {
            if (is_null($attributes)) {
                $options = array(
                    0 => array(
                        'name'                  => __('Attribute name', 'storelly-product-builder-for-woocommerce'),
                        'des'                   => '',
                        'price'                 => array(),
                        'selected'              => 0,
                        'preview_type'          => 'i',
                        'image'                 => 0,
                        'image_url'             => '',
                        'product_image'         => 0,
                        'product_image_url'     => '',
                        'color'                 => '#ffffff',
                        'enable_con'            => 0,
                        'enable_subattr'        => 0,
                        'sub_attributes'        => array(),
                        'sattr_display_type'    => 's',
                    )
                );
            } else {
                $options = $attributes['options'];
            };
            foreach ($options as $key => $option) {
                $options[$key]['enable_subattr']     = isset( $options[$key]['enable_subattr'] ) ? $options[$key]['enable_subattr'] : 0;
                $options[$key]['sub_attributes']     = isset( $options[$key]['sub_attributes'] ) ? $options[$key]['sub_attributes'] : array();
                $options[$key]['sattr_display_type'] = isset( $options[$key]['sattr_display_type'] ) ? $options[$key]['sattr_display_type'] : 's';
                $options[$key]['image_url']          = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail($option['image']);
                if (isset($options[$key]['product_image'])) {
                    $options[$key]['product_image_url'] = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail($option['product_image']);
                }
                if( isset( $option['enable_subattr'] ) ){
                    foreach( $options[$key]['sub_attributes'] as $sak => $sa ){
                        $options[$key]['sub_attributes'][$sak]['image_url'] = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail( $sa['image'] );
                    }
                }
            }
            $same_size          = isset($attributes['same_size']) ? $attributes['same_size'] : 'y';
            $bg_type            = isset($attributes['bg_type']) ? $attributes['bg_type'] : 'i';
            $show_as_pt         = isset($attributes['show_as_pt']) ? $attributes['show_as_pt'] : 'n';
            $number_of_sides    = isset($attributes['number_of_sides']) ? $attributes['number_of_sides'] : 2;
            return array(
                'title'           => __('Attributes', 'storelly-product-builder-for-woocommerce'),
                'description'     => __('Attributes let you define extra product data, such as size or color.','storelly-product-builder-for-woocommerce'),
                'type'            => 'attributes',
                'same_size'       => $same_size,
                'bg_type'         => $bg_type,
                'show_as_pt'      => $show_as_pt,
                'number_of_sides' => $number_of_sides,
                'options'         => $options
            );
        }
        public function build_config_general_pb_config($configs) {
            foreach ($configs as $key => $o_config) {
                foreach ($o_config as $skey => $so_config) {
                    foreach ($so_config['views'] as $vkey => $view) {
                        $configs[$key][$skey]['views'][$vkey]['display']    = (isset($view['display']) && $view['display'] == 'on') ? true : false;
                        $configs[$key][$skey]['views'][$vkey]['image_url']  = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail($view['image']);
                    }
                }
            }
            return $configs;
        }
        public function build_config_general_nbpb_text_configs($configs) {
            if (!isset($configs['views'])) $configs['views'] = array();
            foreach ($configs['views'] as $key => $view) {
                $configs['views'][$key]['display'] = (isset($view['display']) && $view['display'] == 'on') ? true : false;
            }
            return $configs;
        }
        public function build_config_general_nbpb_image_configs($configs) {
            if (!isset($configs['views'])) $configs['views'] = array();
            foreach ($configs['views'] as $key => $view) {
                $configs['views'][$key]['display'] = (isset($view['display']) && $view['display'] == 'on') ? true : false;
            }
            return $configs;
        }
        public function build_config_appearance_display_type($value = null) {
            if (is_null($value)) $value = 'd';
            return array(
                'title'         => __('Display type', 'storelly-product-builder-for-woocommerce'),
                'description'   => '',
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'   => 'd',
                        'text'  => __('Dropdown', 'storelly-product-builder-for-woocommerce')
                    ),
                    array(
                        'key'   => 'r',
                        'text'  => __('Radio button', 'storelly-product-builder-for-woocommerce')
                    ),
                    array(
                        'key'   => 's',
                        'text'  => __('Swatch', 'storelly-product-builder-for-woocommerce')
                    ),
                    array(
                        'key'   => 'l',
                        'text'  => __('Label', 'storelly-product-builder-for-woocommerce')
                    ),
                    array(
                        'key'   => 'ad',
                        'text'  => __('Advanced Dropdown', 'storelly-product-builder-for-woocommerce')
                    ),
                    array(
                        'key'   => 'xl',
                        'text'  => __('Large label', 'storelly-product-builder-for-woocommerce')
                    )
                )
            );
        }
        public function build_config_appearance_change_image_product($value = null) {
            if (is_null($value)) $value = 'n';
            return array(
                'title'         => __('Changes product image', 'storelly-product-builder-for-woocommerce'),
                'description'   => __('Choose whether to change the product image.', 'storelly-product-builder-for-woocommerce'),
                'type'          => 'dropdown',
                'value'         => $value,
                'options'       => array(
                    array(
                        'key'   => 'y',
                        'text'  => __('Yes', 'storelly-product-builder-for-woocommerce')
                    ),
                    array(
                        'key'   => 'n',
                        'text'  => __('No', 'storelly-product-builder-for-woocommerce')
                    )
                )
            );
        }
        public function build_config_appearance_css_class($value = null) {
            if (is_null($value)) $value = '';
            return array(
                'title'         => __('CSS Class', 'storelly-product-builder-for-woocommerce'),
                'description'   => '',
                'type'          => 'text',
                'value'         => $value
            );
        }
        function spbwc_storelly_option_i18n() {
            return array(
                'nbpb_com'              => esc_html__('Component', 'storelly-product-builder-for-woocommerce'),
                'nbpb_text'             => esc_html__('Text', 'storelly-product-builder-for-woocommerce'),
                'nbpb_image'            => esc_html__('Image', 'storelly-product-builder-for-woocommerce'),
                'attribute_name'        => esc_html__('Attribute name', 'storelly-product-builder-for-woocommerce'),
                'sub_attribute_name'    => esc_html__('Sub attribute name', 'storelly-product-builder-for-woocommerce'),
            );
        }
        public function spbwc_add_meta_boxes() {
            add_meta_box('storelly_product_builder', __('Storelly product builder', 'storelly-product-builder-for-woocommerce'), array($this, 'meta_box'), 'product', 'normal', 'high');
        }
        public function spbwc_meta_box() {
            $post_id            = get_the_ID();
            $nbdpb_enable       = get_post_meta($post_id, '_storelly_pb_enable', true);
            $option_id          = $this->spbwc_get_product_option($post_id);
            $option_id          = $option_id ? $option_id : 0;
            $link_edit_option   = add_query_arg(
                array(
                    'product_id'    => $post_id,
                    'action'        => 'edit',
                    'paged'         => 1,
                    'id'            => $option_id
                ),
                admin_url('admin.php?page=storelly-product-builder-for-woocommerce-options')
            );
            include_once(SPBWC_PB_PLUGIN_DIR . 'views/options/meta-box.php');
        }
        public function spbwc_get_product_option($product_id) {
            $enable = get_post_meta($product_id, '_storelly_pb_enable', true);
            if (!$enable) return false;
            $option_id = get_transient('spbwc_product_builder_' . $product_id);
            if (false === $option_id) {
                $options   = $this->spbwc_get_cached_published_options();
                $option_id = '';
                if (!empty($options)) {
                    $_options = array();
                    foreach ($options as $option) {
                        $products = $this->spbwc_extract_product_ids_from_option($option);
                        if (empty($products)) {
                            continue;
                        }
                        if (in_array($product_id, $products, true)) {
                            $_options[] = $option;
                        }
                    }
                    if (!empty($_options)) {
                        $_options = array_reverse($_options);
                        $option_id = isset($_options[0]['id']) ? absint($_options[0]['id']) : '';
                    }
                    if ($option_id) {
                        set_transient('spbwc_product_builder_' . $product_id, $option_id);
                    }
                }
            }
            return $option_id;
        }
        public function spbwc_save_product_option($post_id) {
            if (
                !isset($_POST['pc_box_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pc_box_nonce'])), 'pc_box')
                || !(current_user_can('administrator') || current_user_can('shop_manager'))
            ) {
                return $post_id;
            }
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return $post_id;
            }
            $post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : '';
            if ('page' == $post_type) {
                if (!current_user_can('edit_page', $post_id)) {
                    return $post_id;
                }
            } else {
                if (!current_user_can('edit_post', $post_id)) {
                    return $post_id;
                }
            }
            if (isset($_POST['_storelly_pb_enable'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in surrounding save_post handler.
                $enable = sanitize_text_field( wp_unslash( $_POST['_storelly_pb_enable'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in surrounding save_post handler.
                update_post_meta($post_id, '_storelly_pb_enable', $enable);
            }
        }
        public function spbwc_hidden_custom_order_item_metada($order_items) {
            $order_items[] = '_pcpb_option_price';
            $order_items[] = '_pcpb_field';
            $order_items[] = '_pcpb_options';
            $order_items[] = '_pcpb_original_price';
            $order_items[] = '_pcpb_folder';
            return $order_items;
        }
        public function spbwc_admin_order_item_thumbnail($image = "", $item_id = "", $item = "") {
            if (!$item_id) {
                return  $image;
            }
            $option_price = wc_get_order_item_meta($item_id, '_pcpb_option_price', false);
            if ($option_price) {
                $option_price = maybe_unserialize($option_price);
                if (isset($option_price[0]) && $option_price[0]['cart_image']) {
                    $size = 'shop_thumbnail';
                    $dimensions = wc_get_image_size($size);
                    $image = '<img src="' . $option_price[0]['cart_image']
                        . '" width="' . esc_attr($dimensions['width'])
                        . '" height="' . esc_attr($dimensions['height'])
                        . '" class="pcpb-thumbnail woocommerce-placeholder wp-post-image" />';
                }
            }
            return $image;
        }
        public function spbwc_add_google_font() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error(
                    array(
                        'mes'  => esc_html__( 'You do not have permission to add font!', 'storelly-product-builder-for-woocommerce' ),
                        'flag' => 0,
                    )
                );
            }

            $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! $nonce || ! wp_verify_nonce( $nonce, 'spbwc_update_fonts' ) ) {
                wp_die( esc_html__( 'Security error.', 'storelly-product-builder-for-woocommerce' ) );
            }

            $data    = array(
                'mes'  => esc_html__( 'You do not have permission to add font!', 'storelly-product-builder-for-woocommerce' ),
                'flag' => 0,
            );
            $gg_fonts = array();
            if ( ! isset( $_POST['fonts'] ) ) {
                wp_die( esc_html__( 'Empty data.', 'storelly-product-builder-for-woocommerce' ) );
            } else {
                $fonts_raw = wp_unslash( $_POST['fonts'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string decoded and individual font properties sanitized below.
                $fonts     = json_decode( $fonts_raw );
                if ( empty( $fonts ) || ! is_array( $fonts ) ) {
                    die('Empty data');
                }

                $all_fonts_json = SPBWC_Storelly_IO::spbwc_get_local_file_contents( SPBWC_PB_PLUGIN_DIR . '/data/google-fonts-ttf.json' );
                $all_fonts      = ( false !== $all_fonts_json ) ? json_decode( $all_fonts_json ) : null;
                $all_fonts_list = isset( $all_fonts->items ) ? $all_fonts->items : array();

                foreach ( $fonts as $key => $font ) {
                    $font_name = isset( $font->name ) ? sanitize_text_field( $font->name ) : '';
                    if ( '' === $font_name ) {
                        continue;
                    }

                    $subset = 'all';
                    $file   = array( 'r' => 1 );
                    foreach ( $all_fonts_list as $f ) {
                        if ( isset( $f->family ) && $font_name === $f->family ) {
                            $subset = isset( $f->subsets[0] ) ? sanitize_text_field( $f->subsets[0] ) : 'all';
                            if ( isset( $f->files->regular ) ) {
                                $file['r'] = esc_url_raw( $f->files->regular );
                            } else {
                                $file_regular = reset( $f->files );
                                $file['r']    = is_string( $file_regular ) ? esc_url_raw( $file_regular ) : '';
                            }
                            if ( isset( $f->files->italic ) ) {
                                $file['i'] = esc_url_raw( $f->files->italic );
                            }
                            if ( isset( $f->files->{'700'} ) ) {
                                $file['b'] = esc_url_raw( $f->files->{'700'} );
                            }
                            if ( isset( $f->files->{'700italic'} ) ) {
                                $file['bi'] = esc_url_raw( $f->files->{'700italic'} );
                            }
                            break;
                        }
                    }
                    $gg_fonts[] = array(
                        'id'     => absint( $key ),
                        'name'   => $font_name,
                        'alias'  => $font_name,
                        'type'   => 'google',
                        'subset' => $subset,
                        'file'   => $file,
                        'cat'    => array( '99' ),
                    );
                }
            }
            $path_font      = SPBWC_PB_FONT_DIR . '/googlefonts.json';
            file_put_contents($path_font, wp_json_encode($gg_fonts));
            $data['mes']    = esc_html__('The google fonts have been added successfully!', 'storelly-product-builder-for-woocommerce');
            $data['flag']   = 1;
            echo wp_json_encode($data);
            wp_die();
        }
        public function spbwc_manager_fonts() {
            $subsets                = SPBWC_Storelly_PB_Util::spbwc_font_subsets();
            $current_subset         = 'all';
            $current_cat            = filter_input(INPUT_GET, "cat_id", FILTER_VALIDATE_INT);
            
            $path_font      = SPBWC_PB_FONT_DIR . '/googlefonts.json';
            if(!file_exists( $path_font )){
                $gg_fonts = [];
                file_put_contents($path_font, json_encode($gg_fonts));
            }
            $path = SPBWC_PB_FONT_DIR . '/googlefonts.json';
            $selected_fonts = SPBWC_Storelly_IO::spbwc_get_local_file_contents($path);
            if ($selected_fonts === false || $selected_fonts == '') {
                $selected_fonts = '[]';
            }
            $google_fonts_ttf_json = SPBWC_Storelly_IO::spbwc_get_local_file_contents(SPBWC_PB_DATA_CONFIG_DIR . '/google-fonts-ttf.json');
            $google_fonts_ttf = (false !== $google_fonts_ttf_json) ? json_decode($google_fonts_ttf_json, true) : array();
            wp_register_script('storelly_manager_fonts_script', SPBWC_PB_JS_URL . 'manager-fonts.js', array('spbwc-fontfaceobserver', 'spbwc-sweetalert-js', 'spbwc-ag'), SPBWC_PB_VERSION, true);
            wp_localize_script('storelly_manager_fonts_script', 'storelly_manager_fonts_variable', array(
                'selected_fonts' =>  $selected_fonts,
                'ggFonts' => $google_fonts_ttf,
                'fSubsets' => $subsets
            ));
            wp_enqueue_script("storelly_manager_fonts_script");
            include_once(SPBWC_PB_PLUGIN_DIR . 'views/manager-fonts.php');
        }
        public function spbwc_settings() {
            $storelly_pb_settings = get_option('spbwc_pb_settings');
            if (!isset($storelly_pb_settings['enable_cloud2print_api'])) {
                $storelly_pb_settings['enable_cloud2print_api'] = 'yes';
            }
            $api_keys = get_option( 'spbwc_connect_api_keys', array() );
            $message = '';
            $status = '';

            if ( isset( $_POST['_action_storelly_settings'] ) && 'submit' === sanitize_text_field( wp_unslash( $_POST['_action_storelly_settings'] ) ) ) {
                $settings_nonce = isset( $_POST['spbwc_settings_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['spbwc_settings_nonce'] ) ) : '';
                if ( ! $settings_nonce || ! wp_verify_nonce( $settings_nonce, 'spbwc_settings_action' ) ) {
                    wp_die( esc_html__( 'Security error.', 'storelly-product-builder-for-woocommerce' ) );
                }
                $storelly_enable_cloud2print_api      = isset($_POST['storelly_enable_cloud2print_api']) ? sanitize_text_field( wp_unslash( $_POST['storelly_enable_cloud2print_api'] ) ) : 'yes';
                $consumer_key                         = isset( $_POST['storelly_consumer_key'] ) ? sanitize_text_field( wp_unslash( $_POST['storelly_consumer_key'] ) ) : '';
                $consumer_secret                      = isset( $_POST['storelly_consumer_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['storelly_consumer_secret'] ) ) : '';

                $message        = esc_html__('Your settings have been saved.', 'storelly-product-builder-for-woocommerce');
                $status         = 'updated';
                $storelly_pb_settings['enable_cloud2print_api'] = $storelly_enable_cloud2print_api;
                update_option('spbwc_pb_settings', $storelly_pb_settings);

                if ( '' !== $consumer_key || '' !== $consumer_secret ) {
                    $api_keys['consumer_key']    = $consumer_key;
                    $api_keys['consumer_secret'] = $consumer_secret;
                    update_option( 'spbwc_connect_api_keys', $api_keys );
                }
            }
            include_once(SPBWC_PB_PLUGIN_DIR . 'views/menu-settings.php');
        }
        public function spbwc_convert_svg_embed($path) {
            $svgs       = SPBWC_Storelly_IO::spbwc_get_list_files_by_type($path, 'svg', 1);
            $svg_path   = $path . '/svg';
            if (!file_exists($svg_path)) wp_mkdir_p($svg_path);
            foreach ($svgs as $svg) {
                $svg_name = pathinfo($svg, PATHINFO_BASENAME);
                $new_svg_path = $svg_path . '/' . $svg_name;
                $xdoc = new DomDocument;
                $xdoc->Load($svg);
                /* Embed images */
                $images = $xdoc->getElementsByTagName('image');
                for ($i = 0; $i < $images->length; $i++) {
                    $tagName = $xdoc->getElementsByTagName('image')->item($i);
                    $attribNode = $tagName->getAttributeNode('xlink:href');
                    $img_src = $attribNode->value;
                    if (strpos($img_src, "data:image") !== FALSE)
                        continue;
                    if (strpos($img_src, "data:img") !== FALSE)
                        continue;
                    $type = pathinfo($img_src, PATHINFO_EXTENSION);
                    $type = ($type == 'svg') ? 'svg+xml' : $type;
                    $path_image = SPBWC_Storelly_IO::spbwc_convert_url_to_path($img_src);
                    $data = SPBWC_Storelly_IO::spbwc_get_local_file_contents($path_image);
                    if (false === $data) {
                        continue;
                    }
                    $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                    $tagName->setAttribute('xlink:href', $base64);
                }
                /* Embed fonts */
                $text_elements = $xdoc->getElementsByTagName('text');
                for ($i = 0; $i < $text_elements->length; $i++) {
                    $tagName = $xdoc->getElementsByTagName('text')->item($i);
                    $attribNode = $tagName->getAttributeNode('font-family');
                    $font_family = $attribNode->value;
                    $font = nbd_get_font_by_alias($font_family);
                    if ($font) {
                        $tagName->setAttribute('font-family', $font->name);
                    }
                }
                $new_svg = $xdoc->saveXML();
                file_put_contents($new_svg_path, $new_svg);
            }
        }
        public function spbwc_download_order_designs() {
            if ( ! current_user_can( 'upload_files' ) ) {
                wp_send_json_error( array( 'mes' => esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ) ) );
            }
            $download_nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! $download_nonce || ! wp_verify_nonce( $download_nonce, 'spbwc_download_order_designs' ) ) {
                wp_send_json_error( array( 'mes' => esc_html__( 'Security error.', 'storelly-product-builder-for-woocommerce' ) ) );
            }
            $item_ids_raw = isset( $_POST['item_ids'] ) ? wp_unslash( $_POST['item_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- IDs cast to integers via absint before use.
            $item_ids      = is_array($item_ids_raw) ? array_map( 'absint', $item_ids_raw ) : array();
            $order_id      = isset($_POST['order_id']) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
            $type_download = isset($_POST['type_download']) ? sanitize_text_field( wp_unslash( $_POST['type_download'] ) ) : '';
            $files = array();
            $option_name = array();
            $order_item_names = array();
            if ($order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    foreach ($order->get_items() as $loop_item_id => $order_item) {
                        $order_item_names[$loop_item_id] = $order_item->get_name();
                    }
                }
            }
            if (is_array($item_ids) && count($item_ids) > 0) {
                foreach ($item_ids as $key => $item_id) {
                    $folder = wc_get_order_item_meta($item_id, '_pcpb_folder', true);
                    $item_files = array();
                    $item_option_name = array();
                    if ($folder) {
                        $path           = SPBWC_PB_CUSTOMER_DIR . '/' . $folder;
                        if ($type_download == 'svg') {
                            $svg_path = $path . '/svg';
                            if (!file_exists($svg_path)) {
                                $this->spbwc_convert_svg_embed($path);
                            }
                            $item_files = SPBWC_Storelly_IO::spbwc_get_list_files_by_type($svg_path, 'svg', 1);
                        } else if ($type_download == 'png') {
                            $item_files = SPBWC_Storelly_IO::spbwc_get_list_files_by_type($path, 'png', 1);
                        } else if ($type_download == 'png-preview') {
                            $item_files = SPBWC_Storelly_IO::spbwc_get_list_files_by_type($path . '/preview', 'png', 1);
                        } else if ($type_download == 'pdf') {
                            $item_files = SPBWC_Storelly_Export_PDF::spbwc_export_pdf($folder, false);
                        } else if ($type_download == 'pdf-preview') {
                            $item_files = SPBWC_Storelly_Export_PDF::spbwc_export_pdf($folder, true);
                        }
                    }
                    if (count($item_files)) {
                        foreach ($item_files as $item_file) {
                            $order_item_name = isset($order_item_names[$item_id]) ? $order_item_names[$item_id] : '';
                            $file_name  = pathinfo($item_file, PATHINFO_FILENAME);
                            $item_option_name[] = $order_item_name  ? $order_item_name . '_' . $key . '_' . $file_name : $order_id . '_' . $item_id  . '_' . $file_name;
                        }
                        $option_name = array_merge($option_name, $item_option_name);
                        $files = array_merge($files, $item_files);
                    }
                }
            }
            $zip_files = array();
            if (count($files) > 0) {
                foreach ($files as $key => $file) {
                    $zip_files[] = $file;
                }
            }
            $response = array(
                'flag' => 0,
                'file' => '',
                'options' => $option_name
            );
            if (!count($zip_files)) {
                $response['flag'] = 0;
                echo wp_json_encode($response);
                wp_die();
            } else {
                $pathZip = SPBWC_PB_DATA_DIR . '/download/' . $order_id . '_' . $type_download . '.zip';
                $urlZip = SPBWC_PB_DATA_URL . '/download/' . $order_id . '_' . $type_download . '.zip';
                if (SPBWC_Storelly_PB_Util::spbwc_zip_files($zip_files, $pathZip, $option_name)) {
                    $response['flag'] = 1;
                    $response['file'] = $urlZip;
                }
            }
            echo wp_json_encode($response);
            wp_die();
        }
    }
}
$SPBWC_Storelly_PB_Admin_Options = SPBWC_Storelly_PB_Admin_Options::instance();
$SPBWC_Storelly_PB_Admin_Options->spbwc_init();
