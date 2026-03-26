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
                'spbwc_download_option_image'   => true,
                'spbwc_get_media_full_size_url'  => true,
                'spbwc_add_google_font'          => true,
                'spbwc_download_order_designs'   => true,
                // License management AJAX actions
                'spbwc_license_activate'         => false, // admin only
                'spbwc_license_sync'             => false, // admin only
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
        protected function spbwc_extract_product_cats_from_option($option) {
            if (empty($option) || !is_array($option) || !isset($option['product_cats'])) {
                return array();
            }
            $product_cats = maybe_unserialize($option['product_cats']);
            if (!is_array($product_cats)) {
                return array();
            }
            $product_cats = array_map('absint', $product_cats);
            return array_filter(array_unique($product_cats));
        }
        protected function spbwc_flush_option_caches($option_id = 0, $product_ids = array()) {
            if ($option_id > 0) {
                wp_cache_delete($this->spbwc_cache_key_option($option_id), self::CACHE_GROUP);
            }
            wp_cache_delete('spbwc_published_options', self::CACHE_GROUP);

            global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk clearing transients via prefix.
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    '_transient_spbwc_product_builder_%',
                    '_transient_timeout_spbwc_product_builder_%'
                )
            );
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
            $options = $wpdb->get_results($wpdb->prepare("SELECT id, product_ids, apply_for, product_cats FROM {$table_name} WHERE published = %d", 1), ARRAY_A);
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
                    SPBWC_PB_OVERVIEW_SLUG,
                    array($this, 'spbwc_overview'),
                    SPBWC_PB_ASSETS_URL . 'images/logo.png'
                );
                // Set Overview as the first child of the main menu slug to prevent double entries
                add_submenu_page(
                    SPBWC_PB_OVERVIEW_SLUG,
                    esc_html__('Overview', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Overview', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_OVERVIEW_SLUG,
                    array($this, 'spbwc_overview')
                );
                add_submenu_page(
                    SPBWC_PB_OVERVIEW_SLUG,
                    esc_html__('Settings', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Settings', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_OPTIONS_SLUG,
                    array($this, 'spbwc_settings')
                );
                add_submenu_page(
                    SPBWC_PB_OVERVIEW_SLUG,
                    esc_html__('Pricing Options', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Pricing Options', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_BUILDER_SLUG,
                    array($this, 'spbwc_product_builder_options')
                );
                add_submenu_page(
                    SPBWC_PB_OVERVIEW_SLUG,
                    esc_html__('Products', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Products', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_PRODUCTS_SLUG,
                    array($this, 'spbwc_products_manager')
                );
                add_submenu_page(
                    SPBWC_PB_OVERVIEW_SLUG,
                    esc_html__('Orders', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Orders', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_ORDERS_SLUG,
                    array($this, 'spbwc_orders_manager')
                );
                add_submenu_page(
                    SPBWC_PB_OVERVIEW_SLUG,
                    esc_html__('Quotes', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Quotes', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_QUOTES_SLUG,
                    array($this, 'spbwc_quotes_manager')
                );
                // License submenu – placed right after Quotes
                add_submenu_page(
                    SPBWC_PB_OVERVIEW_SLUG,
                    esc_html__('License', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('License', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_LICENSE_SLUG,
                    array($this, 'spbwc_license_page')
                );
                add_submenu_page(
                    SPBWC_PB_OVERVIEW_SLUG,
                    esc_html__('Fonts', 'storelly-product-builder-for-woocommerce'),
                    esc_html__('Fonts', 'storelly-product-builder-for-woocommerce'),
                    'manage_options',
                    SPBWC_PB_OPTIONS_SLUG . '/manager-fonts',
                    array($this, 'spbwc_manager_fonts')
                );
                add_submenu_page(
                    SPBWC_PB_OVERVIEW_SLUG,
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
                    apply_for varchar(10) NOT NULL default 'p',
                    product_cats text NULL,
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
                    .spbwc-product-card{background:#fff;border:1px solid #e0e4ea;box-shadow:0 2px 8px #e9e9f3;border-radius:12px;overflow:hidden;transition:box-shadow .2s}
                    .spbwc-product-card:hover{box-shadow:0 6px 24px #d1d1e6;transform:translateY(-2px)}
                    .spbwc-product-thumb{display:block;position:relative;aspect-ratio:1/1;background:#f6f7f7}
                    .spbwc-product-thumb img{width:100%;height:100%;object-fit:cover;border-radius:8px 8px 0 0}
                    .spbwc-product-body{padding:16px}
                    .spbwc-product-title{font-weight:700;line-height:1.35;margin:0 0 8px;font-size:1.1em;color:#1d2327}
                    .spbwc-product-meta{font-size:12px;color:#6c7280;margin-bottom:10px}
                    .spbwc-product-actions{display:flex;gap:8px;align-items:center;margin-top:8px}
                    .spbwc-product-actions .dashicons{font-size:18px;line-height:18px}
                    .spbwc-action-icon{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #dcdcde;border-radius:6px;color:#1d2327;text-decoration:none;background:#f7f8fa;transition:background .2s,border .2s}
                    .spbwc-action-icon:hover{background:#e6f0fa;border-color:#339af0;color:#1971c2}
                    .spbwc-product-actions .button-primary{background:#339af0;border:none;box-shadow:none;font-weight:600;transition:background .2s}
                    .spbwc-product-actions .button-primary:hover{background:#1971c2}
                    /* Pagination styles */
                    .spbwc-pagination {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        margin: 40px 0 20px;
                        gap: 8px;
                    }
                    .spbwc-pagination .page-numbers {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        min-width: 40px;
                        height: 40px;
                        padding: 0 8px;
                        border-radius: 10px;
                        background: #fff;
                        color: #4a5568;
                        text-decoration: none;
                        transition: all 0.25s ease;
                        font-weight: 600;
                        font-size: 14px;
                        border: 1px solid #e2e8f0;
                        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                    }
                    .spbwc-pagination .page-numbers:hover:not(.dots):not(.current) {
                        background: #f7fafc;
                        border-color: #cbd5e0;
                        color: #3182ce;
                        transform: translateY(-1px);
                        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
                    }
                    .spbwc-pagination .page-numbers.current {
                        background: #3182ce;
                        color: #fff;
                        border-color: #3182ce;
                        box-shadow: 0 4px 12px rgba(49, 130, 206, 0.3);
                    }
                    .spbwc-pagination .page-numbers.dots {
                        background: none;
                        color: #a0aec0;
                        border: none;
                        box-shadow: none;
                        cursor: default;
                    }
                    .spbwc-pagination .prev.page-numbers,
                    .spbwc-pagination .next.page-numbers {
                        font-size: 18px;
                        padding-bottom: 2px;
                    }
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
                                        <button class="button button-small spbwc-export-ref" data-id="<?php echo esc_attr( $product_id ); ?>" title="<?php esc_attr_e( 'Export product reference data', 'storelly-product-builder-for-woocommerce' ); ?>" style="display: none;">
                                            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                                        </button>
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
                    echo '<div class="spbwc-pagination">';
                    echo paginate_links(
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
                            'type'      => 'plain',
                        )
                    );
                    echo '</div>';
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
            <style>
                .spbwc-about-container{max-width:600px;margin:40px auto 0 auto;background:#fff;border-radius:16px;box-shadow:0 4px 32px #e9e9f3;padding:40px 32px;text-align:center;position:relative}
                .spbwc-about-title{font-size:2.1em;font-weight:700;margin-bottom:10px;color:#1971c2;letter-spacing:-1px}
                .spbwc-about-desc{font-size:1.1em;color:#444;margin-bottom:28px}
                .spbwc-about-btns{display:flex;justify-content:center;gap:16px;margin-bottom:0}
                .spbwc-about-btns a{padding:10px 28px;font-size:1em;border-radius:8px;font-weight:600;transition:background .2s,color .2s,box-shadow .2s}
                .spbwc-about-btns .button-primary{background:#339af0;border:none;box-shadow:0 2px 8px #d1e6fa;color:#fff}
                .spbwc-about-btns .button-primary:hover{background:#1971c2;color:#fff}
                .spbwc-about-btns .button{background:#f7f8fa;color:#1971c2;border:1px solid #e0e4ea}
                .spbwc-about-btns .button:hover{background:#e6f0fa;color:#1971c2}
            </style>
            <div class="spbwc-about-container">
                <span class="spbwc-about-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="304" height="243"><path fill="none" d="M0 0h304v243h-40a768 768 0 0 1 6.539-3.24c3.164-2.263 4.182-4.425 5.844-7.932l.888-1.857c3.443-7.3 6.532-14.758 9.604-22.221q1.304-3.145 2.61-6.29 1.162-2.803 2.32-5.61c.686-1.636 1.401-3.263 2.195-4.85h-11l-1.898 4.559-2.477 5.879-1.25 3.01-1.21 2.86-1.112 2.65C274 212 274 212 272 213c-1.51-3.267-3.006-6.539-4.5-9.812l-1.3-2.815-1.231-2.705-1.143-2.491C263 193 263 193 263 190h-13l1.723 3.594c3.85 8.06 7.678 16.133 11.34 24.281l.904 1.959c1.558 3.515 2.484 6.312 2.033 10.166-1.125 1.813-1.125 1.813-3 3-3.543.302-6.604.045-10-1l-3 7 9 3v1H0zm147.688 7.375-3.211 1.703L141 11l-1.778.979a941 941 0 0 0-11.925 6.71 4233 4233 0 0 1-7.39 4.206A2241 2241 0 0 0 94.25 37.688l-2.185 1.279C87.122 41.878 87.122 41.878 86 43c-.104 2.51-.144 4.992-.142 7.501l-.01 2.38a2319 2319 0 0 0-.017 5.134q-.012 4.059-.041 8.118c-.053 7.696-.1 15.392-.116 23.089-.01 4.702-.04 9.404-.081 14.106q-.02 2.692-.015 5.385c.002 2.51-.02 5.017-.048 7.527l.02 2.257c-.092 5.056-.092 5.056-2.583 7.488L81 127c.09 1.758.09 1.758 1 4 2.176 1.475 4.24 2.676 6.563 3.875q2.21 1.192 4.417 2.39l2.443 1.319c4.706 2.586 9.323 5.322 13.952 8.041a4710 4710 0 0 0 30.16 17.489l3.133 1.804 2.759 1.587C148 169 148 169 150.52 170.818 153 172 153 172 155.5 171.356L158 170l2.434-1.258 2.574-1.437c.973-.543 1.946-1.087 2.95-1.646l3.167-1.784 3.314-1.86c9.432-5.322 18.752-10.821 28.05-16.374 6.91-4.122 13.852-8.167 20.933-11.992 2.919-1.599 5.393-3.112 7.578-5.649l-2.959-1.512C223 124 223 124 222.288 120.478a104 104 0 0 1 .05-4.288l.002-2.372q.008-2.544.054-5.089c.048-2.686.057-5.37.056-8.058.005-6.671.071-13.341.144-20.012.061-5.638.095-11.275.089-16.914.008-2.645.058-5.287.109-7.931q.003-2.438 0-4.874l.076-2.222c-.04-2.965-.27-5.108-2.391-7.27A49 49 0 0 0 215 38l-2.506-1.375-2.283-1.07c-5.334-2.584-10.455-5.467-15.586-8.43l-2.774-1.592a1297 1297 0 0 1-14.726-8.596C173.164 14.61 169.248 12.735 165 11l-5-3h-2V6c-4.334-1.296-6.365-.736-10.312 1.375M214 176v52h11v-52zm20 0v52h11v-52zM17 186c-2.217 3.73-2.28 6.89-1.602 11.074 1.334 4.269 4.555 6.34 8.293 8.434 3.611 1.628 7.223 2.639 11.059 3.554 6.98 1.669 6.98 1.669 9.25 3.938.125 2.625.125 2.625 0 5-9.443 2.29-17.483.259-26-4l-5 9 3.125 1.875 1.758 1.055c5.68 2.871 10.745 3.435 16.992 3.445l2.316.074c5.715.03 10.04-1.251 14.809-4.449 4.305-4.636 5.322-7.573 5.203-13.75-.294-3.253-.99-4.829-3.203-7.25-6.112-3.879-12.73-5.342-19.707-6.973-3.077-.96-4.38-1.516-6.293-4.027.813-1.937.813-1.937 2-4 7.42-2.473 13.87-.454 21 2 4.045-5.394 4.045-5.394 3.844-8.094C54 181 54 181 50.437 179.25c-4.483-1.31-8.917-1.567-13.562-1.625l-2.026-.042C27.3 177.653 21.873 180.152 17 186m48-5v9h-7v9l6 1 .028 1.94c.054 2.917.138 5.832.222 8.748l.043 3.052c.247 7.47.247 7.47 2.504 11.342 3.907 3.401 6.517 4.196 11.703 4.168l2.844.016c2.696-.27 4.345-.893 6.656-2.266l-2-8-2.875.063C80 219 80 219 78 218c-1.206-3.619-1.108-6.925-1.062-10.687l.013-2.127q.02-2.594.049-5.186l9-1v-8l-10-1v-9zm28 16c-3.655 5.352-4.44 9.517-4 16 2.356 6.833 5.444 11.722 12 15 6.671 2.11 15.18 2.293 21.563-.687 5.561-3.365 8.112-7.806 9.96-13.91 1.075-5.418.318-9.784-2.21-14.653-4.728-5.622-9.183-9.266-16.633-9.969-8.643-.317-15 1.403-20.68 8.219m77.063-1c-4.373 6.36-4.87 11.306-4.063 19 1.719 5.41 5.478 8.834 10 12 4.43 1.795 8.233 2.297 13 2.313l3.563.05c3.794-.4 6.194-1.383 9.437-3.363 1.375-2.187 1.375-2.187 2-4l-4-5-2.437 1c-5.387 1.512-10.281 1.971-15.5-.25C180 216 180 216 178 212h29c0-12.601 0-12.601-5-18-9.635-7.273-23.663-7.532-31.937 2M152 192l-1 1-1-3h-12v38h12l.148-4.453.227-5.797c.03-.968.062-1.936.094-2.934l.117-2.832.095-2.602c.34-2.542.985-4.204 2.319-6.382 3.333-2.346 7.025-2.541 11-3v-10c-4.795-1.598-7.6-.2-12 2" id="svg-layer-0" style="pointer-events: all; fill: none;"></path><path fill="#305C83" d="M158 6v2l2.063.75c3.506 1.492 6.653 3.318 9.937 5.25v2l2.125.75c7.986 3.472 7.986 3.472 9.875 7.25.07 1.874.084 3.75.063 5.625l-.028 3.04L182 35h2v9h-2v13h2v5l-5 2v2q5.922-3.25 11.84-6.506l4.015-2.205a3931 3931 0 0 0 5.825-3.203l1.917-1.054a228 228 0 0 0 13.357-8.014C218 44 218 44 222 44v63c-2.36-2.36-2.332-3.124-2.633-6.352l-.254-2.578-.238-2.695-.262-2.719A954 954 0 0 1 218 86l-3-1v-1l-6-1v-3l-2 1-2-3-3-1-2 4h-5l-1-4c-2.791 1.207-4.605 2.217-6 4.98-.73 1.985-1.371 4.001-2 6.02h-2v2l-6-1 2-1c1.627-3.56 2.79-7.282 4-11h-2v-4h-6v2c-1.014-.23-2.029-.461-3.074-.7-4.688-.358-6.684.598-10.738 2.888l-3.395 1.855c-2.704 1.895-3.728 2.896-4.793 5.957l-.632-2.278c-1.806-3.593-3.705-4.431-7.25-6.273l-3.536-1.865-3.707-1.897q-1.867-.975-3.73-1.955Q130.582 68.346 126 66l3-1v-5h-2V49h2v-5l-3-1V32h2l1-5h-2v-3l-11 4-1-2 2.832-1.582 3.98-2.23 2.136-1.198c6.656-3.744 13.259-7.577 19.842-11.446l2.323-1.357 2.056-1.207C151.876 4.995 154.008 5.03 158 6" id="svg-layer-1" style="pointer-events: all;"></path><path fill="#254266" d="m174 74 2 1v-2h6v4h2c.255 3.063.278 4.576-1.437 7.188L181 86l-1 2-1.875.25C176 89 176 89 174.25 91.5c-1.36 3.808-1.392 6.478-1.062 10.45-.244 2.664-1.27 3.274-3.188 5.05-.687 3.188-.687 3.188-1 6h3l2 2c1.317.697 2.65 1.37 4 2l-1 8c2.867-.573 3.861-.861 6-3 2.625.375 2.625.375 5 1v3c2.875-.5 2.875-.5 6-2 .739-2.04 1.066-4.022 1.441-6.156C196 116 196 116 198.5 114.5c2.5-.5 2.5-.5 4.5-.5 1.688-2.125 1.688-2.125 3-5l-.5-2.937c-.653-4.002.423-5.65 2.5-9.063l1.688-2.062c1.656-2.446 1.553-4.046 1.312-6.938l-4 1c-2.125-4.625-2.125-4.625-1-8h2l1-2 1 3c3.438 1.125 3.438 1.125 7 2l2 1c.395 1.918.395 1.918.598 4.402l.24 2.698.225 2.838q.23 2.764.472 5.527l.2 2.488C221 105 221 105 222 107c.31 2.61.508 5.13.625 7.75l.215 2.133c.082 2.322-.028 3.933-.84 6.117-2.673 2.59-5.739 4.243-9 6q-2.28 1.34-4.559 2.684-2.436 1.38-4.879 2.754l-5.519 3.128-2.944 1.669c-6.672 3.8-13.31 7.66-19.952 11.513-3.044 1.76-6.095 3.507-9.147 5.252l-3.112 1.813-2.974 1.695-2.65 1.528C155 162 155 162 152 161l2-1-.013-2.814q-.06-13.131-.09-26.263-.015-6.752-.048-13.502-.032-6.517-.039-13.033-.005-2.485-.021-4.97c-.015-2.322-.017-4.643-.016-6.966l-.022-2.07c.017-3.893.392-6.935 2.249-10.382 2.863-2.055 2.863-2.055 6.313-3.875l3.425-1.867c3.456-1.333 4.822-1.44 8.262-.258" id="svg-layer-2" style="pointer-events: all;"></path><path fill="#6DADD4" d="m86 43 3 1 .031 2.365q.147 11.036.308 22.07.084 5.674.158 11.347.072 5.475.156 10.951.03 2.088.056 4.177.038 2.926.086 5.853c.015 1.11.03 2.22.047 3.364C90 107 90 107 91 111l16 1 2-4 .96 1.793c3.734 6.321 7.86 10.146 15.04 12.207 2.305-.383 2.305-.383 4-1v2l-3 1c-1.387 1.41-1.387 1.41-2.687 3.063L121 130l1.828-1.277 2.422-1.66 2.39-1.653c2.36-1.41 2.36-1.41 4.594-1.914L134 123l1-3c3 1 3 1 4 3l3.125-.187C146 123 146 123 152 126l1-32h1v65c-3.361-1.345-6.316-2.623-9.457-4.328l-2.586-1.403-2.77-1.519-2.906-1.589c-10.32-5.677-20.469-11.637-30.621-17.606-4.755-2.78-9.547-5.493-14.344-8.2C89 123 89 123 88 122a134 134 0 0 1-.19-6.232l-.025-1.971q-.025-2.125-.045-4.25-.032-3.36-.077-6.718-.088-7.134-.163-14.266c-.06-5.506-.121-11.01-.192-16.516-.027-2.21-.047-4.42-.068-6.63l-.05-4.055-.039-3.585C87 55 87 55 86 53a95 95 0 0 1-.062-5.125l.027-2.758z" id="svg-layer-3" style="pointer-events: all;"></path><path fill="#A1D3EA" d="M89 45c3.608 1.443 6.792 2.883 10.176 4.723l2.983 1.617 3.153 1.722 3.265 1.777c12.043 6.569 23.978 13.307 35.86 20.161l2.535 1.46 2.337 1.353 2.087 1.207C153 80 153 80 154 81c.152 2.1.222 4.207.25 6.313l.078 3.488c-.35 3.416-1.015 4.733-3.328 7.199v-2l-2.703-.367-3.547-.508-3.516-.492C136.03 93.615 136.03 93.615 134 92l-1-6-1 17h2l-1-5 3 1-.25 2.25c.25 2.75.25 2.75 2.25 4.469 2.799 3.192 2.405 5.36 2.25 9.531l-.11 3.828L140 122l-2-1c-.1-1.915-.13-3.833-.125-5.75-.332-5.708-.332-5.708-2.715-8.086-2.223-1.289-2.223-1.289-4.445-2.297L129 104v-2h2l-1-18-7.625-3.812L108 73v9l-7-1v2l-8-1-1 2h-3z" id="svg-layer-4" style="pointer-events: all;"></path><path fill="#C3D2DF" d="m86 123 2.184 1.258c7.97 4.574 15.98 9.075 24.004 13.555 8.554 4.777 17.074 9.607 25.535 14.548q2.085 1.216 4.18 2.413c1.49.872 2.96 1.78 4.413 2.714l2.34 1.446 2.088 1.341c3.316 1.066 5.102.081 8.256-1.275a137 137 0 0 0 5.902-3.238l3.414-1.967 3.621-2.107q1.875-1.084 3.75-2.164 2.863-1.65 5.725-3.303c6.005-3.47 12.044-6.881 18.088-10.284 7.538-4.25 15.052-8.532 22.5-12.937l7 5c-4.778 4.187-10.205 7.16-15.687 10.313l-3.187 1.844Q205.066 143.084 200 146l-10.016 5.786q-3.284 1.894-6.573 3.78c-5.07 2.907-10.114 5.843-15.099 8.895l-2.639 1.595q-2.415 1.463-4.801 2.972l-2.142 1.288-1.83 1.14c-3.526 1.01-6.29-1.14-9.382-2.746l-2.631-1.476-2.996-1.676-3.203-1.808-3.347-1.882C117.05 151.548 98.974 140.863 81 130c.742-3.781.742-3.781 3.063-5.75z" id="svg-layer-5" style="pointer-events: all;"></path><path fill="#2C373E" d="M54 181c.844 1.906.844 1.906 1 4-1.937 3.25-1.937 3.25-4 6l-1.906-.656c-12.59-4.096-12.59-4.096-19.094-.844l-2 1.5c.266 1.906.266 1.906 1 4 1.61.906 1.61.906 3.75 1.5l2.438.727q3.06.84 6.124 1.671C52.34 202.031 52.34 202.031 56 207c1.738 3.476 1.455 7.199 1 11-2.389 4.87-5.333 7.32-10 10-8.815 2.938-20.785 2.02-29.152-1.832C16 225 16 225 13 222l5-8 4.188 1.938C29.477 219.156 36.146 219.904 44 218c.313-2.312.313-2.312 0-5-4.851-3.652-10.505-4.487-16.309-5.84-5.032-1.581-8.573-3.573-11.628-7.91-1.35-4.13-1.28-7.1-.063-11.25 2.595-4.912 5.36-7.787 10.676-9.559 8.84-1.678 19.218-1.423 27.324 2.559M250 190h13l1.934 4.559 2.503 5.879 1.276 3.01 1.22 2.86 1.127 2.65C272 211 272 211 273 212l.81-1.912q1.811-4.264 3.627-8.525l1.276-3.01 1.22-2.862 1.127-2.65C282 191 282 191 283 190c2.02-.072 4.042-.084 6.063-.062l3.347.027L295 190l-1.906 4.582-1.244 2.989q-1.359 3.263-2.722 6.524a1624 1624 0 0 0-6.398 15.483l-1.05 2.563a535 535 0 0 0-1.903 4.704c-2.486 6.027-5.283 12.276-11.464 15.155-5.946 1.824-11.92 1.197-17.438-1.562L249 239l4-8 2.117.29c.91.11 1.82.22 2.758.335l2.742.352L263 232l2-2c-.203-6.587-2.316-11.49-5.25-17.312l-2.45-5.016-1.196-2.424a372 372 0 0 1-4.166-8.81l-1.16-2.504C250 192 250 192 250 190M124 192c4.356 3.892 8.057 8.108 9 14 .18 7.094-1.313 12.588-6 18-7.045 5.495-13.324 5.587-22 5-5.715-1.359-10.4-4.713-13.75-9.5-2.517-5.033-3.097-9.993-1.754-15.555 2.229-6.673 6.23-10.728 12.504-13.945 7.14-2.164 15.564-2.01 22 2m-22 11c-1.62 3.24-1.565 6.498-1 10 1.34 2.807 2.547 3.86 5 6 4.369 1.43 6.172 1.406 10.313-.625 2.905-2.568 4.012-3.58 4.687-7.375-.366-4.148-1.018-7.753-3.625-11.062-6.219-2.455-10.772-1.68-15.375 3.062M199 192c3.41 2.945 6.24 5.833 8 10v10h-29c3.49 5.817 3.49 5.817 7 7 5.323.658 10.023.042 15-2l4 5c-1.035 3.001-1.898 3.938-4.656 5.613-6.592 2.734-14.95 2.446-21.781.387-4.584-2.054-7.742-5.945-10.563-10-1.832-5.497-2.177-11.966-.059-17.426 2.979-4.957 6.462-8.366 11.746-10.824 7.286-1.65 13.698-1.511 20.313 2.25m-21 10v4h18c-2.476-6.19-2.476-6.19-6-8-5.396-.687-8.28-.059-12 4" id="svg-layer-6" style="pointer-events: all;"></path><path fill="#254367" d="M127 24v3h2l-1 5h-2q.172 2.72.375 5.438l.21 3.058c.07.413.07.413.415 2.504l2 1v5h-2v11h2v5c-4.943-.54-8.403-2.058-12.719-4.492l-1.881-1.05a1029 1029 0 0 1-5.9-3.333 3983 3983 0 0 0-5.9-3.311q-2.679-1.506-5.352-3.02C94 48 94 48 90.643 46.452 88 45 88 45 87 42l9.063-5.25 2.568-1.488q2.51-1.454 5.023-2.903 2.215-1.282 4.417-2.589c3.73-2.197 6.64-3.198 10.929-3.77l2.125-1.062C123 24 123 24 127 24" id="svg-layer-7" style="pointer-events: all;"></path><path fill="#6EAFD6" d="M170 14a80.5 80.5 0 0 1 9.773 4.504l2.728 1.482 2.874 1.576 2.966 1.622a514 514 0 0 1 20.499 11.91c3.48 2.1 7.019 4.051 10.62 5.933C221 42 221 42 222 44l-3.072 1.27c-3.719 1.595-7.239 3.455-10.78 5.417l-1.884 1.042q-1.955 1.082-3.908 2.168-2.972 1.654-5.95 3.298c-9.803 5.43-9.803 5.43-13.193 7.535C181 66 181 66 179 66v-2l1.938-.687C183 62 183 62 183.75 59.375L184 57h-2V44h2v-9h-2l-.078-2.266c-.464-8.046-.464-8.046-2.547-11.172-3.058-2.011-6.15-3.826-9.375-5.562z" id="svg-layer-8" style="pointer-events: all;"></path><path fill="#2C373E" d="M65 181h11v9l10 1v8l-9 1q-.04 3.656-.062 7.313l-.026 2.091c-.015 3.144.086 5.59 1.088 8.596l3.375.375C85 219 85 219 87 221c.398 1.988.738 3.99 1 6-4.783 2.807-9.6 2.649-15 2-3.91-1.99-5.577-3.365-8-7-.319-2.36-.319-2.36-.414-5.105l-.117-2.979-.094-3.103-.117-3.14Q64.117 203.838 64 200l-6-1v-9h7z" id="svg-layer-9" style="pointer-events: all;"></path><path fill="#2C3941" d="M164 190v10l-6.738 1.27c-2.533.817-3.592 1.69-5.262 3.73-1.203 2.405-1.203 4.104-1.316 6.79l-.127 2.85-.12 2.985-.13 3.008q-.159 3.683-.307 7.367h-12v-38h12l1 3 1.75-1.437c3.995-2.775 6.584-2.73 11.25-1.563" id="svg-layer-10" style="pointer-events: all;"></path><path fill="#2C363C" d="M234 176h11v52h-11zM214 176h11v52h-11z" id="svg-layer-11" style="pointer-events: all;"></path><path fill="#305C83" d="M102 82c2.438.688 2.438.688 5 2 .813 2.625.813 2.625 1 5h-2v2l2.191.836c4.81 1.993 9.27 4.623 13.809 7.164l1.84 1.028q2.865 1.606 5.722 3.222l1.842 1.034c2.483 1.413 4.566 2.686 6.596 4.716.195 2.82.195 2.82.125 6.125l-.055 3.32L138 121c-3.77-1.417-7.204-3.14-10.71-5.117l-3.274-1.84-3.391-1.918-3.367-1.895c-5.43-3.057-10.852-6.13-16.258-9.23V88l3-1-2-1z" id="svg-layer-12" style="pointer-events: all;"></path><path fill="#84BEDE" d="m132 85 2 1c.625 3.063.625 3.063 1 6 4.547.996 9.038 1.372 13.672 1.719C151 94 151 94 152 95h1v32l-7-3c-2.434-.293-2.434-.293-4.437-.187L138 124l1-3c.17-2.142.266-4.29.313-6.437l.113-3.434c-.513-3.766-1.646-4.676-4.426-7.129-.25-2.75-.25-2.75 0-5l-2-1 1 5h-2z" id="svg-layer-13" style="pointer-events: all;"></path><path fill="#86C1E0" d="M108 73h2v17h-1v-5h-4c-1.75-1.5-1.75-1.5-3-3v4l3 1v1h-5c-.081 1.937-.14 3.875-.187 5.813l-.106 3.269C100 100 100 100 101.344 101.738L103 103c2.688 2.375 2.688 2.375 5 5 .145 2.664.145 2.664 0 5-2.795-.227-5.584-.48-8.375-.75l-2.406-.187-2.305-.235-2.126-.19C91 111 91 111 89.768 109.288c-.906-2.698-.995-4.865-.963-7.71l.02-3.105.05-3.223.027-3.27q.036-3.99.098-7.98h2l1-2 9 1v-2h6z" id="svg-layer-14" style="pointer-events: all;"></path><path fill="#305C83" d="M110 73c18.597 9.194 18.597 9.194 20 12 .072 2.718.093 5.409.063 8.125l-.014 2.285q-.02 2.795-.049 5.59c-4.914-1.436-9.004-3.543-13.375-6.125L110 91z" id="svg-layer-15" style="pointer-events: all;"></path><path fill="#9DD0E9" d="m194 77 1 4c2.02-.602 4.021-1.273 6-2l1-2c1.938.313 1.938.313 4 1 1 3 1 3 .438 5.563L206 86l2 2h3c1.125 4.75 1.125 4.75 0 7-3.375-.547-5.082-1.055-8-3a140 140 0 0 0-4-1l-1-1c-7.71-.436-7.71-.436-11 2a179 179 0 0 0-2 4c-1.812 1.875-1.812 1.875-4 3-2.687-.687-2.687-.687-5-2l-1-2a101 101 0 0 1 3-6l6 1v-2l2-1c.838-1.997 1.466-4.026 2.121-6.09C189.378 78.177 190.956 77 194 77" id="svg-layer-16" style="pointer-events: all;"></path><path fill="#F3F6F9" d="M115 199c3.61 2.535 4.769 4.205 6 8.438 0 4.152-.669 6.143-3 9.562-3.001 2.584-4.52 2.991-8.5 3.063-3.573-1.085-5.408-2.208-7.617-5.25-1.703-3.497-1.434-5.956-.883-9.813 3.63-5.686 7.321-7.113 14-6" id="svg-layer-17" style="pointer-events: all;"></path><path fill="#6EAFD6" d="m198 106 2 3 2-2v2c3-1 3-1 5-3-.654 3.53-1.729 6.198-4 9-3.687.813-3.687.813-7 1l-.625 3.875c-.531 2.21-.531 2.21-1.375 4.125-3.125 1.5-3.125 1.5-6 2l-1-3c-4.75.75-4.75.75-7 3-2.125-.375-2.125-.375-4-1l1-8-5-2v-6h1v5h6v-2l1.625.438L183 113l2.938.75c3.827.312 5.076-.392 8.062-2.75 2.313-2.687 2.313-2.687 4-5" id="svg-layer-18" style="pointer-events: all;"></path><path fill="#264469" d="M196 94c1 1 1 1 1.313 4.688-.064 4.436-1.344 6.942-4.313 10.312-2.75 1.375-2.75 1.375-5 2-2.062-1.562-2.062-1.562-4-4-.08-4.336.756-7.055 3.438-10.375 3.26-3.34 4.214-4.028 8.562-2.625" id="svg-layer-19" style="pointer-events: all;"></path><path fill="#2B5278" d="M86 53c2 2 2 2 2.238 4.757l-.014 3.557v1.962q.001 2.11-.01 4.221-.013 3.341.005 6.683c.028 6.334.04 12.668.011 19.003-.017 3.873-.004 7.746.026 11.62q.01 2.213-.014 4.427c-.022 2.067-.007 4.13.017 6.197l-.005 3.562C89 122 89 122 92.046 124.41L95 126l-2 2c-5.875-3.875-5.875-3.875-7-5-.094-2.178-.117-4.36-.114-6.54v-2.069c0-2.267.009-4.534.016-6.801l.005-4.704q.006-6.203.024-12.404.014-6.324.02-12.648Q85.967 65.417 86 53" id="svg-layer-20" style="pointer-events: all;"></path><path fill="#2C5379" d="M94 127c4.085.6 7.069 1.937 10.656 3.957l3.32 1.856 3.524 2 3.626 2.036c3.63 2.04 7.253 4.094 10.874 6.151l3.205 1.82a3575 3575 0 0 1 15.684 8.967A3627 3627 0 0 0 154 159l-3 2a2444 2444 0 0 1-39-22l-2.568-1.474q-3.595-2.068-7.182-4.151l-2.186-1.254c-4.949-2.89-4.949-2.89-6.064-5.121" id="svg-layer-21" style="pointer-events: all;"></path><path fill="#2A4F74" d="M108 106c15.745 8.262 15.745 8.262 23 13-2.386 1.892-4.072 3.024-7 4-7.472-2.958-12.477-7.839-16-15z" id="svg-layer-22" style="pointer-events: all;"></path><path fill="#7BB8DB" d="M100 88h1v13l5 2q2.547 1.302 5.055 2.68l2.863 1.566 2.957 1.629 2.934 1.605A810 810 0 0 1 135 119c-.227 1.84-.227 1.84-1 4-1.96 1.379-1.96 1.379-4.375 2.563-3.054 1.517-5.336 2.89-7.625 5.437l-2-1a271 271 0 0 1 2.813-3.5l1.582-1.969C126 123 126 123 129 123l1-4c-3.45-2.034-6.912-4.05-10.375-6.062l-2.984-1.76c-.941-.545-1.882-1.09-2.852-1.65l-2.634-1.54L109 107l-2 1-4-4-2.25-1.687C98.091 98.799 98.521 95.263 99 91z" id="svg-layer-23" style="pointer-events: all;"></path><path fill="#EDF1F5" d="M190 198c4.019 2.446 4.836 3.344 6 8h-18c1-5 1-5 2.625-6.812 3.359-1.68 5.665-1.644 9.375-1.188" id="svg-layer-24" style="pointer-events: all;"></path><path fill="#7CB7D9" d="M197 91c2.384 2.384 2.296 2.994 2.313 6.25-.144 5.595-1.638 9.12-5.688 13.063-2.7 1.736-4.428 2.519-7.625 2.687-3-2-3-2-3.824-4.7-.21-3.927.587-6.458 2.012-10.112l1.292-3.458C187 92 187 92 188.98 91.125c2.713-.168 5.303-.302 8.02-.125m-10 6c-1.882 3.45-3.025 5.864-2.937 9.813C185 109 185 109 186.75 110.5c2.25.5 2.25.5 4.813-.562 3.243-2.579 5.167-4.743 5.687-8.97.063-2.156.063-2.156-.25-5.968l-2-2c-4.046 0-5.128 1.2-8 4" id="svg-layer-25" style="pointer-events: all;"></path><path fill="#86C0E0" d="M204.824 91.777 210 95l-.875 1.574c-3.687 6.927-3.687 6.927-3.125 11.426l-6 2-1-1c.091-2.494.238-4.952.438-7.437.562-7.48.562-7.48.562-9.563 3-1 3-1 4.824-.223" id="svg-layer-26" style="pointer-events: all;"></path><path fill="#2C547A" d="M191 89c3.438-.5 3.438-.5 7 0 2.547 2.711 2.953 4.643 3.438 8.313-.703 5.922-3.096 9.983-6.75 14.624-3.345 2.568-5.49 3.063-9.688 3.063-2.437-.875-2.437-.875-4-3-1.304-6.63.701-11.33 4-17l1 2c-.594 1.734-.594 1.734-1.5 3.75-1.523 3.776-1.6 6.251-.5 10.25 2.125.75 2.125.75 5 1 4.857-2.429 7.253-4.857 9-10 .271-3.024.33-5.98 0-9-1-1-1-1-2.848-1.098L189 92z" id="svg-layer-27" style="pointer-events: all;"></path><path fill="#84BFDF" d="m176 97 2.313.5C181 98 181 98 184 98l-.937 1.938c-1.382 3.982-1.717 7.879-2.063 12.062h-2v2h-6c-.187-2.812-.187-2.812 0-6q1.47-1.53 3-3c1.315-5.37 1.315-5.37 0-8" id="svg-layer-28" style="pointer-events: all;"></path><path fill="#305C83" d="M176 88h2c-.523 2.763-1.109 5.326-2 8l1.063 1.813c.937 2.187.937 2.187.25 5.25-1.233 2.757-2.199 3.915-4.313 5.937-.687 2.188-.687 2.188-1 4h-3c-.187-2.812-.187-2.812 0-6l1.516-1.434C172 104 172 104 172.152 101.984l-.214-2.234C171.73 94.703 173.19 92.128 176 88" id="svg-layer-29" style="pointer-events: all;"></path><path fill="#89C3E1" d="m129 83 2 1v18c-4 1-4 1-6.953-.512l-3.297-2.175c-4.796-3.103-9.58-5.88-14.75-8.313v-2h2l1-4 .22 2.32c.78 2.68.78 2.68 2.73 4.257l2.523 1.318 2.724 1.455 2.865 1.463 2.877 1.521A739 739 0 0 0 130 101z" id="svg-layer-30" style="pointer-events: all;"></path></svg>
                </span>
                <div class="spbwc-about-title"></div>
                <div class="spbwc-about-desc"><?php esc_html_e( 'Storelly Product Builder for WooCommerce helps merchants create advanced printing options and product builder workflows.', 'storelly-product-builder-for-woocommerce' ); ?></div>
                <div class="spbwc-about-btns">
                    <a class="button button-primary" href="https://storelly.com/product-builder" target="_blank" rel="noopener"><?php esc_html_e( 'Visit Website', 'storelly-product-builder-for-woocommerce' ); ?></a>
                    <a class="button" href="https://storelly.com" target="_blank" rel="noopener"><?php esc_html_e( 'Storelly', 'storelly-product-builder-for-woocommerce' ); ?></a>
                </div>
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
            $apply_for   = isset($_POST['apply_for']) ? sanitize_text_field(wp_unslash($_POST['apply_for'])) : 'p';
            $product_cats = isset($_POST['product_cats']) ? array_map('absint', (array) wp_unslash($_POST['product_cats'])) : array();
            $arr            = array(
                'title'        => $title,
                'published'    => 1,
                'product_ids'  => serialize($product_ids),
                'apply_for'    => $apply_for,
                'product_cats' => serialize($product_cats),
                'modified'     => $modified_date->format('Y-m-d H:i:s')
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
            $option_id = get_transient('spbwc_product_builder_' . $product_id);
            if (false === $option_id) {
                $options   = $this->spbwc_get_cached_published_options();
                $option_id = '';
                if (!empty($options)) {
                    $_options = array();
                    $product_cat_ids = array();

                    foreach ($options as $option) {
                        $apply_for = isset($option['apply_for']) ? $option['apply_for'] : 'p';
                        if ('p' === $apply_for) {
                            $products = $this->spbwc_extract_product_ids_from_option($option);
                            if (in_array($product_id, $products, true)) {
                                $_options[] = $option;
                            }
                        } else {
                            if (empty($product_cat_ids)) {
                                $terms = get_the_terms($product_id, 'product_cat');
                                if (!is_wp_error($terms) && !empty($terms)) {
                                    foreach ($terms as $term) {
                                        $product_cat_ids[] = $term->term_id;
                                    }
                                }
                            }
                            $option_cats = $this->spbwc_extract_product_cats_from_option($option);
                            $intersect = array_intersect($product_cat_ids, $option_cats);
                            if (!empty($intersect)) {
                                $_options[] = $option;
                            }
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
                
                // Save API keys FIRST so they are available when registering with Storelly.
                if ( '' !== $consumer_key || '' !== $consumer_secret ) {
                    $api_keys['consumer_key']    = $consumer_key;
                    $api_keys['consumer_secret'] = $consumer_secret;
                    update_option( 'spbwc_connect_api_keys', $api_keys );
                }

                // Always attempt to fetch unauth_token when credentials are provided.
                if ( ! empty( $consumer_key ) && ! empty( $consumer_secret ) ) {
                    SPBWC_Storelly_Product_Builder_API::spbwc_create_user_storelly();
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

        // ============================================================
        //  Overview Page
        // ============================================================

        /**
         * Render the Overview dashboard page.
         * Shows general stats (products, pricing options, orders, quotes) and license status.
         */
        public function spbwc_overview() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
            }

            // --- Local WooCommerce / WordPress counts ---
            global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
            $table_options    = $wpdb->prefix . 'storelly_product_builder_options';
            $total_pricing    = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table_options}" ); // phpcs:ignore WordPress.DB
            $total_products   = (int) wp_count_posts( 'product' )->publish;

            // WooCommerce orders (all statuses)
            $total_orders = 0;
            if ( function_exists( 'wc_get_orders' ) ) {
                $total_orders = (int) array_sum( (array) wc_get_order_statuses() );
                $count_query = $wpdb->get_var( // phpcs:ignore WordPress.DB
                    "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='shop_order' AND post_status != 'trash'"
                );
                $total_orders = $count_query ? (int) $count_query : 0;
            }

            // Quote requests stored in plugin's own table (if exists)
            $quote_table  = $wpdb->prefix . 'storelly_quote_requests';
            $total_quotes = 0;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $quote_table ) ) === $quote_table ) {
                $total_quotes = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$quote_table}" ); // phpcs:ignore WordPress.DB
            }

            // --- Remote (Storelly) stats overlay ---
            $remote_stats = SPBWC_License_Manager::get_overview_stats();

            // --- License info ---
            $license = SPBWC_License_Manager::get_current_license();

            include_once( SPBWC_PB_PLUGIN_DIR . 'views/overview.php' );
        }

        // ============================================================
        //  License Page
        // ============================================================

        /**
         * Render the License management page.
         * Shows available packages and the user's current active plan.
         */
        public function spbwc_license_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
            }

            $license   = SPBWC_License_Manager::get_current_license();
            $packages  = SPBWC_License_Manager::get_packages();
            $nonce     = wp_create_nonce( 'spbwc_license_action' );

            include_once( SPBWC_PB_PLUGIN_DIR . 'views/license.php' );
        }

        // ============================================================
        //  AJAX: Activate License Key
        // ============================================================

        /**
         * AJAX handler: activate a license key entered by the admin.
         * wp_ajax_spbwc_license_activate
         */
        public function spbwc_license_activate() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array( 'msg' => esc_html__( 'Permission denied.', 'storelly-product-builder-for-woocommerce' ) ) );
            }

            $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'spbwc_license_action' ) ) {
                wp_send_json_error( array( 'msg' => esc_html__( 'Security check failed.', 'storelly-product-builder-for-woocommerce' ) ) );
            }

            $key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
            $result = SPBWC_License_Manager::activate_key( $key );

            if ( $result['success'] ) {
                wp_send_json_success( $result );
            } else {
                wp_send_json_error( $result );
            }
        }

        // ============================================================
        //  AJAX: Sync License from Server
        // ============================================================

        /**
         * AJAX handler: manually trigger a license sync from the Storelly server.
         * wp_ajax_spbwc_license_sync
         */
        public function spbwc_license_sync() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array( 'msg' => esc_html__( 'Permission denied.', 'storelly-product-builder-for-woocommerce' ) ) );
            }

            $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'spbwc_license_action' ) ) {
                wp_send_json_error( array( 'msg' => esc_html__( 'Security check failed.', 'storelly-product-builder-for-woocommerce' ) ) );
            }

            $result = SPBWC_License_Manager::sync_from_api();

            if ( is_wp_error( $result ) ) {
                $msg = __( 'Could not connect to the license server. The Free plan is shown temporarily until sync succeeds.', 'storelly-product-builder-for-woocommerce' );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $result->get_error_message() ) {
                    $msg .= ' ' . $result->get_error_message();
                }
                wp_send_json_error( array( 'msg' => $msg ) );
            }

            $license = SPBWC_License_Manager::get_current_license();
            wp_send_json_success( array(
                'msg'          => esc_html__( 'License synced successfully.', 'storelly-product-builder-for-woocommerce' ),
                'package_name' => $license['package_name'],
                'status'       => $license['status'],
                'expires_at'   => $license['expires_at'],
            ) );
        }
    }
}
$SPBWC_Storelly_PB_Admin_Options = SPBWC_Storelly_PB_Admin_Options::instance();
$SPBWC_Storelly_PB_Admin_Options->spbwc_init();
