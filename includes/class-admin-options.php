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
            }
            // flag stays 1 even when images is empty (export with no media is still valid)
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
            if (current_user_can('spbwc_manage_product_builder') && current_user_can('manage_woocommerce')) {
                add_menu_page(
                    'Storelly Builder',
                    'Product Builder Options',
                    'spbwc_manage_product_builder',
                    SPBWC_PB_OPTIONS_SLUG,
                    array($this, 'spbwc_settings'),
                    SPBWC_PB_ASSETS_URL . 'images/logo.svg'
                );
                add_submenu_page(
                    SPBWC_PB_OPTIONS_SLUG,
                    esc_html__('Settings', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Settings', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_OPTIONS_SLUG,
                    array($this, 'spbwc_settings')
                );
                add_submenu_page(
                    SPBWC_PB_OPTIONS_SLUG,
                    esc_html__('Pricing Options', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Pricing Options', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_BUILDER_SLUG,
                    array($this, 'spbwc_product_builder_options')
                );
                add_submenu_page(
                    SPBWC_PB_OPTIONS_SLUG,
                    esc_html__('Products', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Products', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_PRODUCTS_SLUG,
                    array($this, 'spbwc_products_manager')
                );
                add_submenu_page(
                    SPBWC_PB_OPTIONS_SLUG,
                    esc_html__('Orders', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Orders', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_ORDERS_SLUG,
                    array($this, 'spbwc_orders_manager')
                );
                add_submenu_page(
                    SPBWC_PB_OPTIONS_SLUG,
                    esc_html__('Quotes', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Quotes', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_QUOTES_SLUG,
                    array($this, 'spbwc_quotes_manager')
                );
                add_submenu_page(
                    SPBWC_PB_OPTIONS_SLUG,
                    esc_html__('Fonts', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Fonts', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_OPTIONS_SLUG . '/manager-fonts',
                    array($this, 'spbwc_manager_fonts')
                );
                add_submenu_page(
                    SPBWC_PB_OPTIONS_SLUG,
                    esc_html__('Global Import', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Global Import', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_OPTIONS_SLUG . '/global-import',
                    array($this, 'spbwc_global_import')
                );
                add_submenu_page(
                    SPBWC_PB_OPTIONS_SLUG,
                    esc_html__('System Infor', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('System Infor', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_OPTIONS_SLUG . '/system-infor',
                    array($this, 'spbwc_system_infor')
                );
                add_submenu_page(
                    SPBWC_PB_OPTIONS_SLUG,
                    esc_html__('About', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('About', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_OPTIONS_SLUG . '/about',
                    array($this, 'spbwc_about')
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
            wp_register_script('spbwc-ag', SPBWC_PB_ASSETS_URL . 'libs/builderproductag.min.js', array('jquery'), '1.6.9', true);  
            wp_register_script('spbwc-snap-svg', SPBWC_PB_ASSETS_URL . 'libs/snap.svg.js', array(), '0.3.0', true);
            wp_register_script('spbwc-tiptip', SPBWC_PB_ASSETS_URL . 'js/tiptip.js', array('jquery'), SPBWC_PB_VERSION, true);
            wp_register_script('spbwc-fontfaceobserver', SPBWC_PB_ASSETS_URL . 'libs/fontfaceobserver.js', array(), '2.0.13', true);
            wp_register_script('spbwc-sweetalert-js', SPBWC_PB_ASSETS_URL . 'libs/sweetalert.min.js', array(), '5.6.10', true);
            wp_register_script('spbwc-general-js', SPBWC_PB_ASSETS_URL . 'js/storelly-general.js', array('jquery'), SPBWC_PB_VERSION, true);

            wp_register_style('spbwc-options-style', SPBWC_PB_CSS_URL . 'admin-options.css', array('wp-color-picker', 'wp-jquery-ui-dialog'), SPBWC_PB_VERSION);
            wp_register_style('spbwc-general-css', SPBWC_PB_CSS_URL . 'storelly-general.css', array('dashicons'), SPBWC_PB_VERSION);
            wp_register_style('spbwc-sweetalert-css', SPBWC_PB_CSS_URL . 'sweetalert.css', array(), '5.6.10');
            wp_register_style('spbwc-manager-fonts', SPBWC_PB_CSS_URL . 'manager-fonts.css', array('spbwc-sweetalert-css'), SPBWC_PB_VERSION);

            // style menu setting
            wp_enqueue_style('spbwc-menu-setting',  SPBWC_PB_CSS_URL . '/menu-setting.css', array(), SPBWC_PB_VERSION, 'all');

            wp_localize_script('spbwc-general-js', 'storelly_admin', array(
                'url'       => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce( 'spbwc_download_order_designs' ),
            ));
            wp_enqueue_style('spbwc-general-css');
            wp_enqueue_script('spbwc-general-js');

            if ($hook === 'toplevel_page_' . SPBWC_PB_OPTIONS_SLUG || $hook === 'product-builder-options_page_' . SPBWC_PB_BUILDER_SLUG) {
                wp_register_script('spbwc-options-script', SPBWC_PB_JS_URL . 'admin-options.js', array('jquery', 'wpdialogs', 'jquery-ui-resizable', 'jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-sortable', 'jquery-ui-datepicker', 'jquery-ui-autocomplete', 'wp-color-picker', 'spbwc-ag', 'wc-enhanced-select', 'spbwc-snap-svg', 'spbwc-tiptip'), SPBWC_PB_VERSION, true);
                wp_localize_script('spbwc-options-script', 'storelly_options', array(
                    'search_products_nonce'     => wp_create_nonce("search-products"),
                    'calendar_image'            => SPBWC_PB_ASSETS_URL . 'images/calendar.png',
                    'storelly_options_lang'    => $this->spbwc_storelly_option_i18n(),
                ));
               wp_enqueue_style('spbwc-options-style');
               wp_enqueue_script('spbwc-options-script');
            }
            if ($hook === 'product-builder-options_page_' . SPBWC_PB_OPTIONS_SLUG . '/manager-fonts') {
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
                    wp_safe_redirect(esc_url_raw(add_query_arg(array('paged' => $paged), admin_url('admin.php?page=' . SPBWC_PB_BUILDER_SLUG))));
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
                                ), admin_url('admin.php?page=' . SPBWC_PB_BUILDER_SLUG))));
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
        public function spbwc_products_manager() {
            if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only listing filters.
            $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination value.
            $paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
            $per_page = 20;

            $products_query = new WP_Query(
                array(
                    'post_type'      => 'product',
                    'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                    'posts_per_page' => $per_page,
                    'paged'          => $paged,
                    's'              => $search,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                )
            );
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Products', 'storelly-product-builder-for-woocommerce' ); ?></h1>
                <p><?php esc_html_e( 'Manage products with card view. Template actions are removed and replaced by printing option actions.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                <form method="get" style="margin: 12px 0;">
                    <input type="hidden" name="page" value="<?php echo esc_attr( SPBWC_PB_PRODUCTS_SLUG ); ?>" />
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search products', 'storelly-product-builder-for-woocommerce' ); ?>" />
                    <button type="submit" class="button"><?php esc_html_e( 'Search', 'storelly-product-builder-for-woocommerce' ); ?></button>
                </form>
                <style>
                    .spbwc-product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}
                    .spbwc-product-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;overflow:hidden}
                    .spbwc-product-thumb{display:block;position:relative;aspect-ratio:1/1;background:#f6f7f7}
                    .spbwc-product-thumb img{width:100%;height:100%;object-fit:cover}
                    .spbwc-product-body{padding:12px}
                    .spbwc-product-title{font-weight:600;line-height:1.35;margin:0 0 8px}
                    .spbwc-product-meta{font-size:12px;color:#50575e;margin-bottom:10px}
                    .spbwc-product-actions{display:flex;gap:8px;align-items:center}
                    .spbwc-product-actions .dashicons{font-size:18px;line-height:18px}
                    .spbwc-action-icon{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:1px solid #dcdcde;border-radius:4px;color:#1d2327;text-decoration:none}
                </style>
                <?php if ( $products_query->have_posts() ) : ?>
                    <div class="spbwc-product-grid">
                        <?php while ( $products_query->have_posts() ) : $products_query->the_post(); ?>
                            <?php
                            $product_id       = get_the_ID();
                            $product          = wc_get_product( $product_id );
                            $thumb_id         = get_post_thumbnail_id( $product_id );
                            $thumb_url        = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : wc_placeholder_img_src();
                            $option_id        = $this->spbwc_get_product_option( $product_id );
                            $edit_option_link = add_query_arg(
                                array(
                                    'page'       => SPBWC_PB_BUILDER_SLUG,
                                    'action'     => ( $option_id ? 'edit' : 'create' ),
                                    'id'         => absint( $option_id ),
                                    'product_id' => $product_id,
                                    'paged'      => 1,
                                ),
                                admin_url( 'admin.php' )
                            );
                            $option_text = $option_id ? sprintf( __( 'Printing Option #%d', 'storelly-product-builder-for-woocommerce' ), $option_id ) : __( 'No printing option mapped', 'storelly-product-builder-for-woocommerce' );
                            ?>
                            <div class="spbwc-product-card">
                                <a class="spbwc-product-thumb" href="<?php echo esc_url( $product ? $product->get_permalink() : '#' ); ?>" target="_blank" rel="noopener">
                                    <img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" />
                                </a>
                                <div class="spbwc-product-body">
                                    <p class="spbwc-product-title"><?php echo esc_html( get_the_title() ); ?></p>
                                    <p class="spbwc-product-meta">
                                        <?php echo esc_html( $option_text ); ?><br/>
                                        <code>#<?php echo esc_html( (string) $product_id ); ?></code>
                                    </p>
                                    <div class="spbwc-product-actions">
                                        <a class="spbwc-action-icon" href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>" title="<?php esc_attr_e( 'Edit product', 'storelly-product-builder-for-woocommerce' ); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                        <?php if ( $product ) : ?>
                                            <a class="spbwc-action-icon" href="<?php echo esc_url( $product->get_permalink() ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'View product', 'storelly-product-builder-for-woocommerce' ); ?>">
                                                <span class="dashicons dashicons-visibility"></span>
                                            </a>
                                        <?php endif; ?>
                                        <a class="button button-small button-primary" href="<?php echo esc_url( $edit_option_link ); ?>" target="_blank" rel="noopener">
                                            <?php echo $option_id ? esc_html__( 'Edit Printing Option', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'Create Printing Option', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else : ?>
                    <p><?php esc_html_e( 'No products found.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                <?php endif; ?>
                <?php
                $total_pages = (int) $products_query->max_num_pages;
                if ( $total_pages > 1 ) {
                    echo '<div class="tablenav"><div class="tablenav-pages">';
                    echo wp_kses_post(
                        paginate_links(
                            array(
                                'base'      => add_query_arg(
                                    array(
                                        'page'  => SPBWC_PB_PRODUCTS_SLUG,
                                        's'     => rawurlencode( $search ),
                                        'paged' => '%#%',
                                    ),
                                    admin_url( 'admin.php' )
                                ),
                                'format'    => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total'     => $total_pages,
                                'current'   => $paged,
                            )
                        )
                    );
                    echo '</div></div>';
                }
                wp_reset_postdata();
                ?>
            </div>
            <?php
        }
        public function spbwc_quotes_manager() {
            if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab/search query args.
            $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'get-quote';
            if ( ! in_array( $tab, array( 'get-quote', 'form-builder', 'history' ), true ) ) {
                $tab = 'get-quote';
            }

            if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['spbwc_save_quote_settings'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked below.
                check_admin_referer( 'spbwc_quote_settings_action', 'spbwc_quote_settings_nonce' );
                $settings = array(
                    'enable_quote'      => isset( $_POST['enable_quote'] ) ? sanitize_text_field( wp_unslash( $_POST['enable_quote'] ) ) : 'no', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
                    'admin_email'       => isset( $_POST['admin_email'] ) ? sanitize_email( wp_unslash( $_POST['admin_email'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
                    'success_message'   => isset( $_POST['success_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['success_message'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
                );
                update_option( 'spbwc_quote_settings', $settings );
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Quote settings saved.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
            }
            if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['spbwc_save_quote_form'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked below.
                check_admin_referer( 'spbwc_quote_form_action', 'spbwc_quote_form_nonce' );
                $fields = $this->spbwc_get_default_quote_form_fields();
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
                $posted_names = isset( $_POST['field_name'] ) ? (array) wp_unslash( $_POST['field_name'] ) : array();
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
                $posted_types = isset( $_POST['field_type'] ) ? (array) wp_unslash( $_POST['field_type'] ) : array();
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
                $posted_labels = isset( $_POST['field_label'] ) ? (array) wp_unslash( $_POST['field_label'] ) : array();
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
                $posted_placeholders = isset( $_POST['field_placeholder'] ) ? (array) wp_unslash( $_POST['field_placeholder'] ) : array();
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
                $posted_validation = isset( $_POST['field_validation'] ) ? (array) wp_unslash( $_POST['field_validation'] ) : array();
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
                $posted_required = isset( $_POST['field_required'] ) ? (array) wp_unslash( $_POST['field_required'] ) : array();
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
                $posted_enabled = isset( $_POST['field_enabled'] ) ? (array) wp_unslash( $_POST['field_enabled'] ) : array();
                $rows = max(
                    count( $posted_names ),
                    count( $posted_types ),
                    count( $posted_labels ),
                    count( $posted_placeholders ),
                    count( $posted_validation )
                );
                $new_fields = array();
                for ( $i = 0; $i < $rows; $i++ ) {
                    $name = isset( $posted_names[ $i ] ) ? sanitize_key( $posted_names[ $i ] ) : '';
                    if ( '' === $name ) {
                        continue;
                    }
                    $type = isset( $posted_types[ $i ] ) ? sanitize_key( $posted_types[ $i ] ) : 'text';
                    if ( ! in_array( $type, array( 'text', 'email', 'textarea', 'tel', 'select' ), true ) ) {
                        $type = 'text';
                    }
                    $new_fields[ $name ] = array(
                        'name'        => $name,
                        'type'        => $type,
                        'label'       => isset( $posted_labels[ $i ] ) ? sanitize_text_field( $posted_labels[ $i ] ) : ucfirst( $name ),
                        'placeholder' => isset( $posted_placeholders[ $i ] ) ? sanitize_text_field( $posted_placeholders[ $i ] ) : '',
                        'validation'  => isset( $posted_validation[ $i ] ) ? sanitize_key( $posted_validation[ $i ] ) : '',
                        'required'    => in_array( (string) $i, $posted_required, true ) ? '1' : '0',
                        'enabled'     => in_array( (string) $i, $posted_enabled, true ) ? '1' : '0',
                    );
                }
                if ( ! empty( $new_fields ) ) {
                    $fields = $new_fields;
                }
                update_option( 'spbwc_quote_form_fields', $fields );
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Quote form fields saved.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
            }

            $settings = get_option( 'spbwc_quote_settings', array() );
            $enable_quote = isset( $settings['enable_quote'] ) ? $settings['enable_quote'] : 'no';
            $admin_email = isset( $settings['admin_email'] ) ? $settings['admin_email'] : get_option( 'admin_email' );
            $success_message = isset( $settings['success_message'] ) ? $settings['success_message'] : __( 'Your quote request has been sent successfully.', 'storelly-product-builder-for-woocommerce' );
            $form_fields = get_option( 'spbwc_quote_form_fields', $this->spbwc_get_default_quote_form_fields() );
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Quotes', 'storelly-product-builder-for-woocommerce' ); ?></h1>
                <h2 class="nav-tab-wrapper">
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => SPBWC_PB_QUOTES_SLUG, 'tab' => 'get-quote' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo ( 'get-quote' === $tab ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Get Quote', 'storelly-product-builder-for-woocommerce' ); ?></a>
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => SPBWC_PB_QUOTES_SLUG, 'tab' => 'form-builder' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo ( 'form-builder' === $tab ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Form Builder', 'storelly-product-builder-for-woocommerce' ); ?></a>
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => SPBWC_PB_QUOTES_SLUG, 'tab' => 'history' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo ( 'history' === $tab ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Request History', 'storelly-product-builder-for-woocommerce' ); ?></a>
                </h2>
                <?php if ( 'get-quote' === $tab ) : ?>
                    <form method="post" style="margin-top:16px;">
                        <?php wp_nonce_field( 'spbwc_quote_settings_action', 'spbwc_quote_settings_nonce' ); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label><?php esc_html_e( 'Enable Get Quote', 'storelly-product-builder-for-woocommerce' ); ?></label></th>
                                <td>
                                    <label><input type="radio" name="enable_quote" value="yes" <?php checked( $enable_quote, 'yes' ); ?> /> <?php esc_html_e( 'Yes', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                    <label style="margin-left:10px;"><input type="radio" name="enable_quote" value="no" <?php checked( $enable_quote, 'no' ); ?> /> <?php esc_html_e( 'No', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="spbwc_quote_admin_email"><?php esc_html_e( 'Notification Email', 'storelly-product-builder-for-woocommerce' ); ?></label></th>
                                <td>
                                    <input id="spbwc_quote_admin_email" type="email" name="admin_email" value="<?php echo esc_attr( $admin_email ); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e( 'Send quote request notifications to this email.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="spbwc_quote_success_message"><?php esc_html_e( 'Success Message', 'storelly-product-builder-for-woocommerce' ); ?></label></th>
                                <td>
                                    <textarea id="spbwc_quote_success_message" name="success_message" rows="4" class="large-text"><?php echo esc_textarea( $success_message ); ?></textarea>
                                </td>
                            </tr>
                        </table>
                        <p><button class="button button-primary" type="submit" name="spbwc_save_quote_settings" value="1"><?php esc_html_e( 'Save changes', 'storelly-product-builder-for-woocommerce' ); ?></button></p>
                    </form>
                <?php elseif ( 'form-builder' === $tab ) : ?>
                    <style>
                        .spbwc-qf-add{display:flex;gap:8px;align-items:center;margin:16px 0}
                        .spbwc-qf-table th,.spbwc-qf-table td{vertical-align:middle}
                        .spbwc-qf-table input[type="text"],.spbwc-qf-table select{width:100%}
                    </style>
                    <form method="post" style="margin-top:16px;">
                        <?php wp_nonce_field( 'spbwc_quote_form_action', 'spbwc_quote_form_nonce' ); ?>
                        <h3><?php esc_html_e( 'Request quote form builder', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                        <div class="spbwc-qf-add">
                            <input type="text" id="spbwc-new-field-name" class="regular-text" placeholder="<?php esc_attr_e( 'Enter field name', 'storelly-product-builder-for-woocommerce' ); ?>" />
                            <button type="button" class="button" id="spbwc-add-quote-field"><?php esc_html_e( 'Add field', 'storelly-product-builder-for-woocommerce' ); ?></button>
                        </div>
                        <table class="widefat striped spbwc-qf-table" id="spbwc-quote-fields-table">
                            <thead>
                                <tr>
                                    <th style="width:36px;">&nbsp;</th>
                                    <th><?php esc_html_e( 'Name', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Type', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Label', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Placeholder', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Validation rules', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Required', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Enabled', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $index = 0; ?>
                                <?php foreach ( (array) $form_fields as $field ) : ?>
                                    <tr>
                                        <td><span class="dashicons dashicons-menu"></span></td>
                                        <td><input type="text" name="field_name[]" value="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : '' ); ?>" /></td>
                                        <td>
                                            <select name="field_type[]">
                                                <option value="text" <?php selected( isset( $field['type'] ) ? $field['type'] : '', 'text' ); ?>><?php esc_html_e( 'Text', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                                <option value="email" <?php selected( isset( $field['type'] ) ? $field['type'] : '', 'email' ); ?>><?php esc_html_e( 'Email', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                                <option value="tel" <?php selected( isset( $field['type'] ) ? $field['type'] : '', 'tel' ); ?>><?php esc_html_e( 'Phone', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                                <option value="textarea" <?php selected( isset( $field['type'] ) ? $field['type'] : '', 'textarea' ); ?>><?php esc_html_e( 'Textarea', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                                <option value="select" <?php selected( isset( $field['type'] ) ? $field['type'] : '', 'select' ); ?>><?php esc_html_e( 'Select', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                            </select>
                                        </td>
                                        <td><input type="text" name="field_label[]" value="<?php echo esc_attr( isset( $field['label'] ) ? $field['label'] : '' ); ?>" /></td>
                                        <td><input type="text" name="field_placeholder[]" value="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ); ?>" /></td>
                                        <td>
                                            <select name="field_validation[]">
                                                <option value="" <?php selected( isset( $field['validation'] ) ? $field['validation'] : '', '' ); ?>><?php esc_html_e( 'No validation', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                                <option value="email" <?php selected( isset( $field['validation'] ) ? $field['validation'] : '', 'email' ); ?>><?php esc_html_e( 'Email', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                                <option value="phone" <?php selected( isset( $field['validation'] ) ? $field['validation'] : '', 'phone' ); ?>><?php esc_html_e( 'Phone', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                            </select>
                                        </td>
                                        <td><input type="checkbox" name="field_required[]" value="<?php echo esc_attr( (string) $index ); ?>" <?php checked( isset( $field['required'] ) ? $field['required'] : '0', '1' ); ?> /></td>
                                        <td><input type="checkbox" name="field_enabled[]" value="<?php echo esc_attr( (string) $index ); ?>" <?php checked( isset( $field['enabled'] ) ? $field['enabled'] : '1', '1' ); ?> /></td>
                                        <td><button type="button" class="button button-small spbwc-remove-field"><?php esc_html_e( 'Remove', 'storelly-product-builder-for-woocommerce' ); ?></button></td>
                                    </tr>
                                    <?php $index++; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="margin-top:12px;">
                            <button class="button button-primary" type="submit" name="spbwc_save_quote_form" value="1"><?php esc_html_e( 'Save changes', 'storelly-product-builder-for-woocommerce' ); ?></button>
                        </p>
                    </form>
                    <script>
                        (function($){
                            function nextIndex(){ return $('#spbwc-quote-fields-table tbody tr').length; }
                            function buildRow(name){
                                var idx = nextIndex();
                                var label = name ? name.replace(/_/g, ' ').replace(/\b\w/g, function(l){ return l.toUpperCase(); }) : '';
                                return '<tr>'
                                    + '<td><span class="dashicons dashicons-menu"></span></td>'
                                    + '<td><input type="text" name="field_name[]" value="'+ (name || '') +'" /></td>'
                                    + '<td><select name="field_type[]"><option value="text">Text</option><option value="email">Email</option><option value="tel">Phone</option><option value="textarea">Textarea</option><option value="select">Select</option></select></td>'
                                    + '<td><input type="text" name="field_label[]" value="'+ label +'" /></td>'
                                    + '<td><input type="text" name="field_placeholder[]" value="" /></td>'
                                    + '<td><select name="field_validation[]"><option value="">No validation</option><option value="email">Email</option><option value="phone">Phone</option></select></td>'
                                    + '<td><input type="checkbox" name="field_required[]" value="'+ idx +'" /></td>'
                                    + '<td><input type="checkbox" name="field_enabled[]" value="'+ idx +'" checked /></td>'
                                    + '<td><button type="button" class="button button-small spbwc-remove-field">Remove</button></td>'
                                    + '</tr>';
                            }
                            $('#spbwc-add-quote-field').on('click', function(){
                                var val = ($('#spbwc-new-field-name').val() || '').toLowerCase().replace(/[^a-z0-9_]/g, '_');
                                if(!val){ return; }
                                $('#spbwc-quote-fields-table tbody').append(buildRow(val));
                                $('#spbwc-new-field-name').val('');
                            });
                            $(document).on('click', '.spbwc-remove-field', function(){
                                $(this).closest('tr').remove();
                            });
                        })(jQuery);
                    </script>
                <?php else : ?>
                    <?php
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search filter.
                    $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination value.
                    $paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
                    $quote_orders = wc_get_orders(
                        array(
                            'type'        => 'shop_order',
                            'limit'       => 20,
                            'page'        => $paged,
                            'paginate'    => true,
                            'orderby'     => 'date',
                            'order'       => 'DESC',
                            'meta_query'  => array(
                                'relation' => 'OR',
                                array(
                                    'key'     => '_raq_request',
                                    'compare' => 'EXISTS',
                                ),
                                array(
                                    'key'     => '_spbwc_quote_request',
                                    'compare' => 'EXISTS',
                                ),
                            ),
                            'search'      => $search ? '*' . $search . '*' : '',
                            'search_columns' => array( 'billing_email', 'billing_first_name', 'billing_last_name' ),
                        )
                    );
                    $orders = isset( $quote_orders->orders ) ? $quote_orders->orders : array();
                    $max_pages = isset( $quote_orders->max_num_pages ) ? (int) $quote_orders->max_num_pages : 1;
                    ?>
                    <form method="get" style="margin:16px 0;">
                        <input type="hidden" name="page" value="<?php echo esc_attr( SPBWC_PB_QUOTES_SLUG ); ?>" />
                        <input type="hidden" name="tab" value="history" />
                        <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by customer info', 'storelly-product-builder-for-woocommerce' ); ?>" />
                        <button type="submit" class="button"><?php esc_html_e( 'Search', 'storelly-product-builder-for-woocommerce' ); ?></button>
                    </form>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Quote Order', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Customer', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Email', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Message', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Date', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'storelly-product-builder-for-woocommerce' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $orders ) ) : ?>
                                <?php foreach ( $orders as $order ) : ?>
                                    <?php
                                    $customer_name = $order->get_meta( '_raq_customer_name' );
                                    $customer_email = $order->get_meta( '_raq_customer_email' );
                                    $message = $order->get_meta( '_raq_customer_message' );
                                    if ( ! $message ) {
                                        $message = $order->get_meta( '_spbwc_quote_request' );
                                    }
                                    if ( ! $customer_name ) {
                                        $customer_name = trim( $order->get_formatted_billing_full_name() );
                                    }
                                    if ( ! $customer_email ) {
                                        $customer_email = $order->get_billing_email();
                                    }
                                    ?>
                                    <tr>
                                        <td><strong>#<?php echo esc_html( (string) $order->get_id() ); ?></strong></td>
                                        <td><?php echo esc_html( $customer_name ? $customer_name : __( 'Guest', 'storelly-product-builder-for-woocommerce' ) ); ?></td>
                                        <td><?php echo esc_html( $customer_email ); ?></td>
                                        <td><?php echo esc_html( $message ? wp_trim_words( $message, 14, '...' ) : '-' ); ?></td>
                                        <td><?php echo esc_html( $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '-' ); ?></td>
                                        <td><a class="button button-small" href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><?php esc_html_e( 'View order', 'storelly-product-builder-for-woocommerce' ); ?></a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr><td colspan="6"><?php esc_html_e( 'No quote requests found.', 'storelly-product-builder-for-woocommerce' ); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php if ( $max_pages > 1 ) : ?>
                        <div class="tablenav"><div class="tablenav-pages">
                            <?php
                            echo wp_kses_post(
                                paginate_links(
                                    array(
                                        'base'      => add_query_arg(
                                            array(
                                                'page'  => SPBWC_PB_QUOTES_SLUG,
                                                'tab'   => 'history',
                                                's'     => rawurlencode( $search ),
                                                'paged' => '%#%',
                                            ),
                                            admin_url( 'admin.php' )
                                        ),
                                        'format'    => '',
                                        'prev_text' => '&laquo;',
                                        'next_text' => '&raquo;',
                                        'total'     => $max_pages,
                                        'current'   => $paged,
                                    )
                                )
                            );
                            ?>
                        </div></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php
        }
        private function spbwc_get_default_quote_form_fields() {
            return array(
                'first_name' => array(
                    'name'        => 'first_name',
                    'type'        => 'text',
                    'label'       => 'First Name',
                    'placeholder' => '',
                    'validation'  => '',
                    'required'    => '1',
                    'enabled'     => '1',
                ),
                'last_name' => array(
                    'name'        => 'last_name',
                    'type'        => 'text',
                    'label'       => 'Last Name',
                    'placeholder' => '',
                    'validation'  => '',
                    'required'    => '1',
                    'enabled'     => '1',
                ),
                'email' => array(
                    'name'        => 'email',
                    'type'        => 'email',
                    'label'       => 'Email',
                    'placeholder' => '',
                    'validation'  => 'email',
                    'required'    => '1',
                    'enabled'     => '1',
                ),
                'message' => array(
                    'name'        => 'message',
                    'type'        => 'textarea',
                    'label'       => 'Message',
                    'placeholder' => '',
                    'validation'  => '',
                    'required'    => '0',
                    'enabled'     => '1',
                ),
            );
        }
        public function spbwc_system_infor() {
            if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
            }
            global $wp_version; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global variable.
            $upload_dir = wp_upload_dir();
            $memory_limit = ini_get( 'memory_limit' );
            $max_execution_time = ini_get( 'max_execution_time' );
            $max_input_vars = ini_get( 'max_input_vars' );
            $max_upload_size = size_format( wp_max_upload_size() );
            $timezone = wp_timezone_string();
            $theme = wp_get_theme();
            $active_plugins = (array) get_option( 'active_plugins', array() );
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'System Infor', 'storelly-product-builder-for-woocommerce' ); ?></h1>
                <table class="widefat striped" style="max-width:900px;">
                    <tbody>
                        <tr><th><?php esc_html_e( 'Plugin Version', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( SPBWC_PB_VERSION ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'WordPress Version', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( $wp_version ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'WooCommerce Version', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A' ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'PHP Version', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( phpversion() ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'MySQL Version', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( function_exists( 'mysqli_get_client_info' ) ? mysqli_get_client_info() : 'N/A' ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Site URL', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( site_url() ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Home URL', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( home_url() ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'WP Debug', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'Enabled' : 'Disabled' ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'WP Memory Limit', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : 'N/A' ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'PHP Memory Limit', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( $memory_limit ? $memory_limit : 'N/A' ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'PHP Max Execution Time', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( $max_execution_time ? $max_execution_time . 's' : 'N/A' ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'PHP Max Input Vars', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( $max_input_vars ? $max_input_vars : 'N/A' ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Max Upload Size', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( $max_upload_size ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Timezone', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( $timezone ? $timezone : 'UTC' ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Theme', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Uploads Base Directory', 'storelly-product-builder-for-woocommerce' ); ?></th><td><code><?php echo esc_html( isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : '' ); ?></code></td></tr>
                        <tr><th><?php esc_html_e( 'Uploads Base URL', 'storelly-product-builder-for-woocommerce' ); ?></th><td><code><?php echo esc_html( isset( $upload_dir['baseurl'] ) ? $upload_dir['baseurl'] : '' ); ?></code></td></tr>
                        <tr><th><?php esc_html_e( 'Storelly Data Directory', 'storelly-product-builder-for-woocommerce' ); ?></th><td><code><?php echo esc_html( SPBWC_PB_DATA_DIR ); ?></code></td></tr>
                        <tr><th><?php esc_html_e( 'Storelly Data URL', 'storelly-product-builder-for-woocommerce' ); ?></th><td><code><?php echo esc_html( SPBWC_PB_DATA_URL ); ?></code></td></tr>
                        <tr><th><?php esc_html_e( 'Active Plugins', 'storelly-product-builder-for-woocommerce' ); ?></th><td><?php echo esc_html( (string) count( $active_plugins ) ); ?></td></tr>
                    </tbody>
                </table>
            </div>
            <?php
        }
        public function spbwc_about() {
            if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
            }
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'About', 'storelly-product-builder-for-woocommerce' ); ?></h1>
                <p><?php esc_html_e( 'Storelly Product Builder for WooCommerce helps merchants create advanced printing options and product builder workflows.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                <p>
                    <a class="button button-primary" href="https://storelly.com/product-builder" target="_blank" rel="noopener"><?php esc_html_e( 'Visit Website', 'storelly-product-builder-for-woocommerce' ); ?></a>
                    <a class="button" href="https://storelly.com" target="_blank" rel="noopener"><?php esc_html_e( 'Storelly', 'storelly-product-builder-for-woocommerce' ); ?></a>
                </p>
            </div>
            <?php
        }
        public function spbwc_orders_manager() {
            if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
            }

            global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only listing filters.
            $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination value.
            $paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
            $per_page = 20;
            $offset   = ( $paged - 1 ) * $per_page;

            $order_items_table = $wpdb->prefix . 'woocommerce_order_items';
            $order_itemmeta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
            $posts_table = $wpdb->posts;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only query for admin listing, table names are trusted from $wpdb.
            $total_orders = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT oi.order_id)
                    FROM {$order_items_table} oi
                    INNER JOIN {$order_itemmeta_table} oim ON oi.order_item_id = oim.order_item_id
                    INNER JOIN {$posts_table} p ON p.ID = oi.order_id
                    WHERE oim.meta_key = %s
                    AND p.post_type IN ('shop_order', 'shop_order_refund')
                    AND (%s = '' OR CAST(oi.order_id AS CHAR) LIKE %s)",
                    '_pcpb_option_price',
                    $search,
                    '%' . $wpdb->esc_like( $search ) . '%'
                )
            );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only query for admin listing, table names are trusted from $wpdb.
            $order_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT oi.order_id
                    FROM {$order_items_table} oi
                    INNER JOIN {$order_itemmeta_table} oim ON oi.order_item_id = oim.order_item_id
                    INNER JOIN {$posts_table} p ON p.ID = oi.order_id
                    WHERE oim.meta_key = %s
                    AND p.post_type IN ('shop_order', 'shop_order_refund')
                    AND (%s = '' OR CAST(oi.order_id AS CHAR) LIKE %s)
                    ORDER BY oi.order_id DESC
                    LIMIT %d OFFSET %d",
                    '_pcpb_option_price',
                    $search,
                    '%' . $wpdb->esc_like( $search ) . '%',
                    $per_page,
                    $offset
                )
            );
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Orders', 'storelly-product-builder-for-woocommerce' ); ?></h1>
                <p><?php esc_html_e( 'Manage custom orders that contain pricing options.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                <form method="get" style="margin: 12px 0;">
                    <input type="hidden" name="page" value="<?php echo esc_attr( SPBWC_PB_ORDERS_SLUG ); ?>" />
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by order ID', 'storelly-product-builder-for-woocommerce' ); ?>" />
                    <button type="submit" class="button"><?php esc_html_e( 'Search', 'storelly-product-builder-for-woocommerce' ); ?></button>
                </form>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Order', 'storelly-product-builder-for-woocommerce' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'storelly-product-builder-for-woocommerce' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'storelly-product-builder-for-woocommerce' ); ?></th>
                            <th><?php esc_html_e( 'Customer', 'storelly-product-builder-for-woocommerce' ); ?></th>
                            <th><?php esc_html_e( 'Total', 'storelly-product-builder-for-woocommerce' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'storelly-product-builder-for-woocommerce' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $order_ids ) ) : ?>
                            <?php foreach ( $order_ids as $order_id ) : ?>
                                <?php $order = wc_get_order( absint( $order_id ) ); ?>
                                <?php if ( ! $order ) { continue; } ?>
                                <tr>
                                    <td><strong>#<?php echo esc_html( (string) $order->get_id() ); ?></strong></td>
                                    <td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
                                    <td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
                                    <td><?php echo esc_html( trim( $order->get_formatted_billing_full_name() ) ? $order->get_formatted_billing_full_name() : __( 'Guest', 'storelly-product-builder-for-woocommerce' ) ); ?></td>
                                    <td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
                                    <td>
                                        <a class="button button-small" href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><?php esc_html_e( 'View order', 'storelly-product-builder-for-woocommerce' ); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6"><?php esc_html_e( 'No custom orders found.', 'storelly-product-builder-for-woocommerce' ); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php
                $total_pages = (int) ceil( $total_orders / $per_page );
                if ( $total_pages > 1 ) {
                    echo '<div class="tablenav"><div class="tablenav-pages">';
                    echo wp_kses_post(
                        paginate_links(
                            array(
                                'base'      => add_query_arg(
                                    array(
                                        'page'  => SPBWC_PB_ORDERS_SLUG,
                                        's'     => rawurlencode( $search ),
                                        'paged' => '%#%',
                                    ),
                                    admin_url( 'admin.php' )
                                ),
                                'format'    => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total'     => $total_pages,
                                'current'   => $paged,
                            )
                        )
                    );
                    echo '</div></div>';
                }
                ?>
            </div>
            <?php
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
            add_meta_box('storelly_product_builder', __('Storelly product builder', 'storelly-product-builder-for-woocommerce'), array($this, 'spbwc_meta_box'), 'product', 'normal', 'high');
        }
        public function spbwc_meta_box() {
            $post_id            = get_the_ID();
            $nbdpb_enable       = get_post_meta($post_id, '_storelly_pb_enable', true);
            $spbwc_enable_quote = get_post_meta($post_id, '_spbwc_enable_quote', true);
            $option_id          = $this->spbwc_get_product_option($post_id);
            $option_id          = $option_id ? $option_id : 0;
            $option_title       = '';
            if ($option_id > 0) {
                $option_row = $this->spbwc_get_option($option_id);
                $option_title = is_array($option_row) && !empty($option_row['title']) ? $option_row['title'] : '';
            }
            $link_edit_option   = add_query_arg(
                array(
                    'product_id'    => $post_id,
                    'action'        => 'edit',
                    'paged'         => 1,
                    'id'            => $option_id
                ),
                admin_url('admin.php?page=' . SPBWC_PB_BUILDER_SLUG)
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
            if (isset($_POST['_spbwc_enable_quote'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in surrounding save_post handler.
                $enable_quote = sanitize_text_field( wp_unslash( $_POST['_spbwc_enable_quote'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in surrounding save_post handler.
                update_post_meta($post_id, '_spbwc_enable_quote', $enable_quote);
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

                $all_fonts_json = SPBWC_Storelly_IO::spbwc_get_local_file_contents( SPBWC_PB_DATA_CONFIG_DIR . 'google-fonts-ttf.json' );
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
            // CORRECT PATTERN: Verify nonce and parameters BEFORE processing.
            // This method is read-only (displays/filters fonts), but we verify nonce when cat_id is provided for consistency.
            if (isset($_GET['cat_id'])) {
                $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
                if (empty($nonce) || !wp_verify_nonce($nonce, 'spbwc_manager_fonts_action')) {
                    wp_die(esc_html__('Security error.', 'storelly-product-builder-for-woocommerce'));
                }
            }
            
            $subsets                = SPBWC_Storelly_PB_Util::spbwc_font_subsets();
            $current_subset         = 'all';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameter, nonce verified above if provided.
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
            $google_fonts_ttf_json = SPBWC_Storelly_IO::spbwc_get_local_file_contents(SPBWC_PB_DATA_CONFIG_DIR . 'google-fonts-ttf.json');
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
                $storelly_enable_api_sync              = isset($_POST['storelly_enable_api_sync']) ? sanitize_text_field( wp_unslash( $_POST['storelly_enable_api_sync'] ) ) : 'no';
                $consumer_key                         = isset( $_POST['storelly_consumer_key'] ) ? sanitize_text_field( wp_unslash( $_POST['storelly_consumer_key'] ) ) : '';
                $consumer_secret                      = isset( $_POST['storelly_consumer_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['storelly_consumer_secret'] ) ) : '';

                $message        = esc_html__('Your settings have been saved.', 'storelly-product-builder-for-woocommerce');
                $status         = 'updated';
                $storelly_pb_settings['enable_cloud2print_api'] = $storelly_enable_cloud2print_api;
                $storelly_pb_settings['enable_api_sync'] = $storelly_enable_api_sync;
                update_option('spbwc_pb_settings', $storelly_pb_settings);
                
                // If API sync is enabled and user has entered credentials, trigger account creation.
                if ('yes' === $storelly_enable_api_sync && ( ! empty( $consumer_key ) || ! empty( $consumer_secret ) ) ) {
                    SPBWC_Storelly_Product_Builder_API::spbwc_create_user_storelly();
                }

                if ( '' !== $consumer_key || '' !== $consumer_secret ) {
                    $api_keys['consumer_key']    = $consumer_key;
                    $api_keys['consumer_secret'] = $consumer_secret;
                    update_option( 'spbwc_connect_api_keys', $api_keys );
                }

                // Save Printing Options.
                $po_keys = array(
                    'spbwc_number_of_decimals',
                    'spbwc_enable_rich_snippet_price',
                    'spbwc_option_display',
                    'spbwc_hide_add_cart_until_form_filled',
                    'spbwc_hide_summary_options',
                    'spbwc_float_summary_options',
                    'spbwc_hide_table_pricing',
                    'spbwc_table_pricing_type',
                    'spbwc_hide_option_swatch_label',
                    'spbwc_change_base_price_html',
                    'spbwc_hide_zero_price',
                    'spbwc_tooltip_position',
                    'spbwc_ad_sublist_position',
                    'spbwc_selector_increase_qty_btn',
                    'spbwc_display_product_option',
                    'spbwc_force_select_options',
                    'spbwc_show_options_in_archive_pages',
                    'spbwc_enable_ajax_cart',
                    'spbwc_turn_off_persistent_cart',
                    'spbwc_enable_clear_cart_button',
                    'spbwc_hide_options_in_cart',
                    'spbwc_hide_option_price_in_cart',
                    'spbwc_hide_option_price_in_order',
                );
                foreach ( $po_keys as $key ) {
                    if ( isset( $_POST[ $key ] ) ) {
                        $val = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
                        if ( 'spbwc_number_of_decimals' === $key ) {
                            $val = absint( $val );
                            if ( $val > 6 ) {
                                $val = 6;
                            }
                        }
                        update_option( $key, $val );
                    }
                }
            }
            include_once(SPBWC_PB_PLUGIN_DIR . 'views/menu-settings.php');
        }
        
        public function spbwc_global_import() {
            // Delegate to the global import admin class
            $global_import_admin = SPBWC_Global_Import_Admin::instance();
            $global_import_admin->render_global_import_page();
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
