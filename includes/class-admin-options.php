<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('Storelly_PB_Admin_Options')) {
    class Storelly_PB_Admin_Options {
        protected static $instance;
        public function __construct() {
            //todo something
        }
        public static function instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        public function init() {
            if (is_admin()) {
                $this->ajax();
            }
            // Create a menu for the Product builder
            add_action('storelly_pb_menu', array($this, 'tab_menu'));
            add_action('storelly_create_tables', array($this, 'create_options_table'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
            add_action('add_meta_boxes', array($this, 'add_meta_boxes'), 35);
            // Add options design in Order WC
            add_action('add_meta_boxes', array($this, 'storelly_add_design_box'), 38);
            add_action('save_post', array($this, 'save_product_option'));

            // Alter the product thumbnail in order
            add_filter('woocommerce_admin_order_item_thumbnail', array($this, 'admin_order_item_thumbnail'), 50, 3);
            //Hide some price option data in order
            add_filter('woocommerce_hidden_order_itemmeta', array($this, 'hidden_custom_order_item_metada'));
        }
        public function ajax() {
            $ajax_events = array(
                'nbd_download_option_image'         => true,
                'nbd_get_media_full_size_url'       => true,
                'storelly_add_google_font'         => true,
                'storelly_download_order_designs'  => true,
            );
            foreach ($ajax_events as $ajax_event => $nopriv) {
                add_action('wp_ajax_' . $ajax_event, array($this, $ajax_event));
                if ($nopriv) {
                    // AJAX can be used for frontend ajax requests
                    add_action('wp_ajax_nopriv_' . $ajax_event, array($this, $ajax_event));
                }
            }
        }
        public function storelly_add_design_box() {
            add_meta_box(
                'storelly_product_builder_design',
                esc_html__('Product builder designs', 'pc-product-builder'),
                array($this, 'storelly_product_builder_design'),
                'shop_order',
                'side',
                'default'
            );
        }
        public function storelly_product_builder_design($post) {
            $order_id       = $post->ID;
            $order          = wc_get_order($order_id);
            $order_items    = $order->get_items();
            include_once(STORELLY_PB_PLUGIN_DIR . 'views/box-order-metadata.php');
        }
        public function nbd_get_media_full_size_url() {
            if (!wp_verify_nonce($_POST['nonce'], 'save-design') && STORELLY_ENABLE_NONCE) {
                die('Security error');
            }
            $result = array(
                'flag'      => 1,
                'images'    => array()
            );
            $images = json_decode(stripslashes($_POST['images']), true);
            foreach ($images as $key => $image) {
                $result['images'][$key] = wp_get_attachment_url($image);
            }
            echo json_encode($result);
            wp_die();
        }
        public function nbd_download_option_image() {
            if (!wp_verify_nonce($_POST['nonce'], 'save-design') && STORELLY_ENABLE_NONCE) {
                die('Security error');
            }
            $result = array(
                'flag'      => 1,
                'image'     => array()
            );
            $url = wc_clean($_POST['image']);
            require_once(STORELLY_PB_PLUGIN_DIR . 'includes/class-download-image.php');
            if (strpos($url, get_site_url()) > -1) {
                $result['image'] = array(
                    'current_site'  => 1
                );
            } else {
                $download_remote_image = new Storelly_PB_Download_Image($url, array());
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
            echo json_encode($result);
            wp_die();
        }
        public function tab_menu() {
            if (current_user_can('manage_product_builder')) {
                add_menu_page(
                    'PC Product Builder',
                    'Product Builder Options',
                    'manage_product_builder',
                    'pc-product-builder-options',
                    array($this, 'product_builder_options'),
                    STORELLY_PB_PLUGIN_URL . '/assets/images/logo.svg'
                );
                add_submenu_page(
                    'pc-product-builder-options',
                    esc_html__('Builder options', 'pc-product-builder'),
                    esc_html__('Builder options', 'pc-product-builder'),
                    'manage_options',
                    'pc-product-builder-options',
                    array($this, 'product_builder_options')
                );
                add_submenu_page(
                    'pc-product-builder-options',
                    esc_html__('Fonts', 'pc-product-builder'),
                    esc_html__('Fonts', 'pc-product-builder'),
                    'manage_options',
                    'pc-product-builder-options/manager-fonts',
                    array($this, 'storelly_manager_fonts')
                );
                add_submenu_page(
                    'pc-product-builder-options',
                    esc_html__('Settings', 'pc-product-builder'),
                    esc_html__('Settings', 'pc-product-builder'),
                    'manage_options',
                    'pc-product-builder-options/settings',
                    array($this, 'storelly_settings')
                );
            }
        }
        public function create_options_table() {
            global $wpdb;
            $collate = '';
            if ($wpdb->has_cap('collation')) {
                $collate = $wpdb->get_charset_collate();
            }
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            if (STORELLY_PB_VERSION != get_option("storelly_version_plugin")) {
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
        public function admin_enqueue_scripts($hook) {
            wp_register_script('pc-angular', STORELLY_PB_PLUGIN_URL . 'assets/libs/builderproductag.min.js', array('jquery'), '1.6.9');  
            wp_register_script('snap_svg', STORELLY_PB_ASSETS_URL . 'libs/snap.svg.js', array(), '0.3.0');
            wp_register_script('pc-tiptip', STORELLY_PB_ASSETS_URL . 'js/tiptip.js', array('jquery'), STORELLY_PB_VERSION);
            wp_register_script('pc-fontfaceobserver', STORELLY_PB_PLUGIN_URL . 'assets/libs/fontfaceobserver.js', array(), '2.0.13');
            wp_register_script('pc-sweetalert', STORELLY_PB_PLUGIN_URL . 'assets/libs/sweetalert.min.js', array(), '5.6.10', true);
            wp_register_script('storelly-general', STORELLY_PB_ASSETS_URL . 'js/storelly-general.js', array('jquery'), STORELLY_PB_VERSION, true);

            wp_register_style('storelly_options', STORELLY_PB_CSS_URL . 'admin-options.css', array('wp-color-picker', 'wp-jquery-ui-dialog'), STORELLY_PB_VERSION);
            wp_register_style('storelly-general', STORELLY_PB_CSS_URL . 'storelly-general.css', array('dashicons'), STORELLY_PB_VERSION);
            wp_register_style('pc-sweetalert', STORELLY_PB_CSS_URL . 'sweetalert.css', array(), '5.6.10');
            wp_register_style('manager-fonts', STORELLY_PB_CSS_URL . 'manager-fonts.css', array('pc-sweetalert'), STORELLY_PB_VERSION);

            // style menu setting
            wp_enqueue_style('menu-setting',  STORELLY_PB_CSS_URL . '/menu-setting.css', array(), '1.0', 'all');

            wp_localize_script('storelly-general', 'storelly_admin', array(
                'url'       => admin_url('admin-ajax.php'),
            ));
            wp_enqueue_style('storelly-general');
            wp_enqueue_script('storelly-general');

            if ($hook == 'toplevel_page_pc-product-builder-options') {
                wp_register_script('storelly_options', STORELLY_PB_JS_URL . 'admin-options.js', array('jquery', 'wpdialogs', 'jquery-ui-resizable', 'jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-sortable', 'jquery-ui-datepicker', 'jquery-ui-autocomplete', 'wp-color-picker', 'pc-angular', 'wc-enhanced-select', 'snap_svg', 'pc-tiptip'), STORELLY_PB_VERSION);
                wp_localize_script('storelly_options', 'storelly_options', array(
                    'search_products_nonce'     => wp_create_nonce("search-products"),
                    'calendar_image'            => STORELLY_PB_PLUGIN_URL . 'assets/images/calendar.png',
                    'storelly_options_lang'    => $this->storelly_option_i18n(),
                ));
                wp_enqueue_style('storelly_options');
                wp_enqueue_script('storelly_options');
            }
            if ($hook == 'product-builder-options_page_pc-product-builder-options/manager-fonts') {
                wp_register_script('manager-fonts', STORELLY_PB_JS_URL . 'manager-fonts.js', array('pc-fontfaceobserver', 'pc-sweetalert', 'pc-angular'), STORELLY_PB_VERSION, true);
                wp_localize_script('manager-fonts', 'storelly_pb_fonts', array(
                    'url'       => admin_url('admin-ajax.php'),
                    'nonce'     => wp_create_nonce('storelly_update_fonts'),
                    'complete'  => esc_html__('Complete!', 'pc-product-builder'),
                ));
                wp_enqueue_script('manager-fonts');
                wp_enqueue_style('manager-fonts');
            }
        }
        public function product_builder_options() {
            if (isset($_GET['action']) && $_GET['action'] != 'copy') {
                $paged      = get_query_var('paged', 1);
                $message    = array('content'  => '');
                if ($_GET['action'] == 'unpublish') {
                    $this->unpublish_option($_REQUEST['id']);
                    wp_redirect(esc_url_raw(add_query_arg(array('paged' => $paged), admin_url('admin.php?page=pc-product-builder-options'))));
                } else {
                    $id = (isset($_REQUEST['id']) && absint($_REQUEST['id']) > 0) ? absint($_REQUEST['id']) : 0;
                    if (isset($_POST['save']) || isset($_POST['options'])) {
                        $result = $this->save_option();
                        if ($result['status']) {
                            $message = array(
                                'flag'      => 'success',
                                'content'   => esc_html__('Option updated.', 'pc-product-builder')
                            );
                            if ($id == 0) {
                                $id = $result['id'];
                                wp_redirect(esc_url_raw(add_query_arg(array(
                                    'paged'     => 1,
                                    'action'    => 'edit',
                                    'id'        => $id
                                ), admin_url('admin.php?page=pc-product-builder-options'))));
                            }
                        } else {
                            $message = array(
                                'flag'      => 'error',
                                'content'   => ''
                            );
                        }
                    }
                    $_options = ($id > 0) ? $this->get_option($id) : false;
                    if ($_options) {
                        $raw_options = unserialize($_options['fields']);
                        if (!isset($raw_options["fields"])) {
                            $raw_options["fields"] = array();
                        }
                        $options                    = $this->build_options($raw_options);
                        $options['id']              = $_options['id'];
                        $options['title']           = $_options['title'];
                        $options['published']       = $_options['published'];
                        $options['created']         = $_options['created'];
                        $options['modified']        = $_options['modified'];
                        $options['product_ids']     = isset($_options['product_ids']) ? (!is_null(unserialize($_options['product_ids'])) ? unserialize($_options['product_ids']) : array()) : array();
                    } else {
                        $options = $this->build_options();
                        $options['id']              = 0;
                        $options['title']           = '';
                        $options['product_ids']     = array();
                        $options['published']       = 1;
                        $options['created']         = '';
                        $options['modified']        = '';
                    }
                    $default_field = $this->default_config_field();
                    $product_id = (isset($_GET['product_id']) && absint($_GET['product_id']) > 0) ? absint($_GET['product_id']) : 0;
                    if ($product_id) {
                        if (!$_options) {
                            $options['product_ids'] = array(0 => $product_id);
                        }
                    }
                    include_once(STORELLY_PB_PLUGIN_DIR . 'views/options/edit-option.php');
                }
            } else {
                require_once STORELLY_PB_PLUGIN_DIR . 'includes/options/fields-list-table.php';
                $nbd_options = new Storelly_Options_List_Table();
                include_once(STORELLY_PB_PLUGIN_DIR . 'views/options/options-list-table.php');
            }
        }
        function get_extension_filename() {
        }
        public function unpublish_option($id) {
            global $wpdb;
            $result = $wpdb->update($wpdb->prefix . 'pc-product-builder-options', array(
                'published' => 0
            ), array('id' => esc_sql($id)));
            if ($result) $this->clear_transients();
        }
        private function clear_transients() {
        }
        public function get_option($id) {
            global $wpdb;
            $sql = "SELECT * FROM {$wpdb->prefix}storelly_product_builder_options";
            $sql .= " WHERE id = " . esc_sql($id);
            $result = $wpdb->get_results($sql, 'ARRAY_A');
            return count($result[0]) ? $result[0] : false;
        }
        public function save_option() {
            $id             = absint($_REQUEST['id']);
            $modified_date  = new DateTime();
            $arr            = array(
                'title'         => wc_clean($_POST['title']),
                'published'     => 1,
                'product_ids'   => isset($_POST['product_ids']) ? serialize($_POST['product_ids']) : serialize(array()),
                'modified'      => $modified_date->format('Y-m-d H:i:s')
            );
            $post_options = $_POST['options'];
            if (isset($_POST['options']['jsonFields'])) {
                $post_options['fields'] = json_decode(stripslashes($_POST['options']['jsonFields']), true);
                unset($post_options['jsonFields']);
            }
            $arr['fields'] = serialize($post_options);
            global $wpdb;
            $date = new DateTime();
            if ($id > 0) {
                $arr['modified']    = $date->format('Y-m-d H:i:s');
                $arr['modified_by'] = wp_get_current_user()->ID;
                $result             = $wpdb->update("{$wpdb->prefix}storelly_product_builder_options", $arr, array('id' => $id));
            } else {
                $arr['created']     = $date->format('Y-m-d H:i:s');
                $arr['created_by']  = wp_get_current_user()->ID;
                $result             = $wpdb->insert("{$wpdb->prefix}storelly_product_builder_options", $arr);
                $id                 = $result ?  $wpdb->insert_id : 0;
            }
            $this->clear_transients();
            do_action('storelly_save_print_option', $arr);
            return array(
                'status'    => $result,
                'id'        => $id
            );
        }
        public function build_options($options = null) {
            if (is_null($options)) {
                $options = array(
                    'version'                   => STORELLY_PB_NUMBER_VERSION,
                    'fields'                    => array(
                        0   =>  $this->default_field()
                    ),
                    'design_output'  => array(
                        'dpi'                   => 300,
                        'dimension_unit'        => 'px',
                    )
                );
            }
            $options['fields'] = $this->recursive_stripslashes($options['fields']);
            foreach ($options['fields'] as $f_key => $field) {
                $field = array_replace_recursive($this->default_field(), $field);
                foreach ($field as $tab =>  $data) {
                    if ($tab != 'id' && $tab != 'nbpb_type' && $tab != 'nbd_template') {
                        foreach ($data as $key => $f) {
                            $funcname = "build_config_" . $tab . '_' . $key;
                            if (is_callable(array($this, $funcname))) {
                                $options['fields'][$f_key][$tab][$key] = $this->$funcname($f);
                            }
                            if( $key == 'component_icon' ){
                                $options['fields'][$f_key][$tab]['component_icon_url'] =  Storelly_PB_Util::storelly_get_image_thumbnail( $f );
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
                    $options['views'][$vkey]['base_url'] = Storelly_PB_Util::storelly_get_image_thumbnail($view['base']);
                }
            }
            return $options;
        }
        public function build_config_conditional_depend($value = null) {
            if (is_null($value) || count($value) == 0) $value = array(
                0   =>  array(
                    'id'        => '',
                    'operator'  => 'i',
                    'val'       => ''
                )
            );
            return $value;
        }
        public function default_config_field() {
            $field = $this->default_field();
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
        public function recursive_stripslashes($fields) {
            $valid_fields = array();
            foreach ($fields as $key => $field) {
                if (is_array($field)) {
                    $valid_fields[$key] = $this->recursive_stripslashes($field);
                } else if (!is_null($field)) {
                    $valid_fields[$key] = stripslashes($field);
                }
            }
            return $valid_fields;
        }
        public function default_field() {
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
            if (is_null($value)) $value = __('Option name', 'pc-product-builder');
            return array(
                'title'         => __('Option name', 'pc-product-builder'),
                'description'   =>  '',
                'value'         => $value,
                'type'          => 'text'
            );
        }
        public function build_config_general_description($value = null) {
            if (is_null($value)) $value = __('Option description', 'pc-product-builder');
            return array(
                'title'         => __('Description', 'pc-product-builder'),
                'description'   => '',
                'value'         => $value,
                'type'          => 'textarea'
            );
        }
        public function build_config_general_data_type($value = null) {
            if (is_null($value)) $value = 'm';
            return array(
                'title'         => esc_html__('Data type', 'pc-product-builder'),
                'description'   => '',
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'       => 'i',
                        'text'      => esc_html__('Custom input', 'pc-product-builder')
                    ),
                    array(
                        'key'       => 'm',
                        'text'      => esc_html__('Multiple options', 'pc-product-builder')
                    )
                )
            );
        }
        public function build_config_general_input_type($value = null) {
            if (is_null($value)) $value = 't';
            return array(
                'title'         => esc_html__('Input type', 'pc-product-builder'),
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
                        'text'      => esc_html__('Text', 'pc-product-builder')
                    ),
                    array(
                        'key'       => 'u',
                        'text'      => esc_html__('Upload', 'pc-product-builder')
                    ),
                    array(
                        'key'       => 'a',
                        'text'      => esc_html__('Textarea', 'pc-product-builder')
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
                'title'         => esc_html__('Input option', 'pc-product-builder'),
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
                'title'         => esc_html__('Text input option', 'pc-product-builder'),
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
                'title'         => __('Enabled', 'pc-product-builder'),
                'description'   => __('Choose whether the option is enabled or not.', 'pc-product-builder'),
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'       => 'y',
                        'text'      => __('Yes', 'pc-product-builder')
                    ),
                    array(
                        'key'       => 'n',
                        'text'      => __('No', 'pc-product-builder')
                    )
                )
            );
        }
        public function build_config_general_published($value = null) {
            if (is_null($value)) $value = 'y';
            return array(
                'title'         => __('Published', 'pc-product-builder'),
                'description'   => __('Show in summary options or not.', 'pc-product-builder'),
                'value'         => $value,
                'type'          => 'dropdown',
                'options' =>    array(
                    array(
                        'key'       => 'y',
                        'text'      => __('Yes', 'pc-product-builder')
                    ),
                    array(
                        'key'       => 'n',
                        'text'      => __('No', 'pc-product-builder')
                    )
                )
            );
        }
        public function build_config_general_required($value = null) {
            if (is_null($value)) $value = 'n';
            return array(
                'title'         => __('Required', 'pc-product-builder'),
                'description'   => __('Choose whether the option is required or not.'),
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'       => 'y',
                        'text'      => __('Yes', 'pc-product-builder')
                    ),
                    array(
                        'key'       => 'n',
                        'text'      => __('No', 'pc-product-builder')
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
                    'max_size'      =>  Storelly_PB_Util::storelly_get_max_upload_default(),
                    'allow_type'    =>  'png,jpg,jpeg'
                );
            }
            return array(
                'title'         => esc_html__('Upload file option', 'pc-product-builder'),
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
                'title'         => esc_html__('Price type', 'pc-product-builder'),
                'description'   => esc_html__('Here you can choose how the price is calculated. Depending on the field there various types you can choose.'),
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'       => 'f',
                        'text'      => esc_html__('Fixed amount', 'pc-product-builder')
                    ),
                    array(
                        'key'       => 'p',
                        'text'      => esc_html__('Percent of the original price', 'pc-product-builder')
                    ),
                    array(
                        'key'       => 'p+',
                        'text'      => esc_html__('Percent of the original price + options', 'pc-product-builder')
                    ),
                    array(
                        'key'       => 'c',
                        'text'      => esc_html__('Current value * price', 'pc-product-builder'),
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
                        'text'      => esc_html__('Price per char', 'pc-product-builder'),
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
                'title'         => esc_html__('Additional Price', 'pc-product-builder'),
                'description'   => esc_html__('Enter the price for this field or leave it blank for no price.'),
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
                        'name'                  => __('Attribute name', 'pc-product-builder'),
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
                $options[$key]['image_url']          = Storelly_PB_Util::storelly_get_image_thumbnail($option['image']);
                if (isset($options[$key]['product_image'])) {
                    $options[$key]['product_image_url'] = Storelly_PB_Util::storelly_get_image_thumbnail($option['product_image']);
                }
                if( isset( $option['enable_subattr'] ) ){
                    foreach( $options[$key]['sub_attributes'] as $sak => $sa ){
                        $options[$key]['sub_attributes'][$sak]['image_url'] = Storelly_PB_Util::storelly_get_image_thumbnail( $sa['image'] );
                    }
                }
            }
            $same_size          = isset($attributes['same_size']) ? $attributes['same_size'] : 'y';
            $bg_type            = isset($attributes['bg_type']) ? $attributes['bg_type'] : 'i';
            $show_as_pt         = isset($attributes['show_as_pt']) ? $attributes['show_as_pt'] : 'n';
            $number_of_sides    = isset($attributes['number_of_sides']) ? $attributes['number_of_sides'] : 2;
            return array(
                'title'           => __('Attributes', 'pc-product-builder'),
                'description'     => __('Attributes let you define extra product data, such as size or color.'),
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
                        $configs[$key][$skey]['views'][$vkey]['image_url']  = Storelly_PB_Util::storelly_get_image_thumbnail($view['image']);
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
                'title'         => __('Display type', 'pc-product-builder'),
                'description'   => '',
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'   => 'd',
                        'text'  => __('Dropdown', 'pc-product-builder')
                    ),
                    array(
                        'key'   => 'r',
                        'text'  => __('Radio button', 'pc-product-builder')
                    ),
                    array(
                        'key'   => 's',
                        'text'  => __('Swatch', 'pc-product-builder')
                    ),
                    array(
                        'key'   => 'l',
                        'text'  => __('Label', 'pc-product-builder')
                    ),
                    array(
                        'key'   => 'ad',
                        'text'  => __('Advanced Dropdown', 'pc-product-builder')
                    ),
                    array(
                        'key'   => 'xl',
                        'text'  => __('Large label', 'pc-product-builder')
                    )
                )
            );
        }
        public function build_config_appearance_change_image_product($value = null) {
            if (is_null($value)) $value = 'n';
            return array(
                'title'         => __('Changes product image', 'pc-product-builder'),
                'description'   => __('Choose whether to change the product image.', 'pc-product-builder'),
                'type'          => 'dropdown',
                'value'         => $value,
                'options'       => array(
                    array(
                        'key'   => 'y',
                        'text'  => __('Yes', 'pc-product-builder')
                    ),
                    array(
                        'key'   => 'n',
                        'text'  => __('No', 'pc-product-builder')
                    )
                )
            );
        }
        public function build_config_appearance_css_class($value = null) {
            if (is_null($value)) $value = '';
            return array(
                'title'         => __('CSS Class', 'pc-product-builder'),
                'description'   => '',
                'type'          => 'text',
                'value'         => $value
            );
        }
        function storelly_option_i18n() {
            return array(
                'nbpb_com'              => esc_html__('Component', 'pc-product-builder'),
                'nbpb_text'             => esc_html__('Text', 'pc-product-builder'),
                'nbpb_image'            => esc_html__('Image', 'pc-product-builder'),
                'attribute_name'        => esc_html__('Attribute name', 'pc-product-builder'),
                'sub_attribute_name'    => esc_html__('Sub attribute name', 'pc-product-builder'),
            );
        }
        public function add_meta_boxes() {
            add_meta_box('storelly_product_builder', __('Storelly product builder', 'pc-product-builder'), array($this, 'meta_box'), 'product', 'normal', 'high');
        }
        public function meta_box() {
            $post_id            = get_the_ID();
            $nbdpb_enable       = get_post_meta($post_id, '_storelly_pb_enable', true);
            $option_id          = $this->get_product_option($post_id);
            $option_id          = $option_id ? $option_id : 0;
            $link_edit_option   = add_query_arg(
                array(
                    'product_id'    => $post_id,
                    'action'        => 'edit',
                    'paged'         => 1,
                    'id'            => $option_id
                ),
                admin_url('admin.php?page=pc-product-builder-options')
            );
            include_once(STORELLY_PB_PLUGIN_DIR . 'views/options/meta-box.php');
        }
        public function get_product_option($product_id) {
            $enable = get_post_meta($product_id, '_storelly_pb_enable', true);
            if (!$enable) return false;
            $option_id = get_transient('storelly_product_builder_' . $product_id);
            if (false === $option_id) {
                global $wpdb;
                $sql = "SELECT id, product_ids FROM {$wpdb->prefix}storelly_product_builder_options WHERE published = 1";
                $options = $wpdb->get_results($sql, 'ARRAY_A');
                if ($options) {
                    $_options = array();
                    foreach ($options as $option) {
                        $execute_option = true;
                        if ($execute_option) {
                            $products = unserialize($option['product_ids']);
                            $execute_option = in_array($product_id, $products) ? true : false;
                        }
                        if ($execute_option) {
                            $_options[] = $option;
                        }
                    }
                    $_options = array_reverse($_options);
                    $option_id = isset($_options[0]) && isset($_options[0]['id']) ? $_options[0]['id'] : '';
                    if ($option_id) {
                        set_transient('storelly_product_builder_' . $product_id, $option_id);
                    }
                }
            }
            return $option_id;
        }
        public function save_product_option($post_id) {
            if (
                !isset($_POST['pc_box_nonce']) || !wp_verify_nonce($_POST['pc_box_nonce'], 'pc_box')
                || !(current_user_can('administrator') || current_user_can('shop_manager'))
            ) {
                return $post_id;
            }
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return $post_id;
            }
            if ('page' == $_POST['post_type']) {
                if (!current_user_can('edit_page', $post_id)) {
                    return $post_id;
                }
            } else {
                if (!current_user_can('edit_post', $post_id)) {
                    return $post_id;
                }
            }
            if (isset($_POST['_storelly_pb_enable'])) {
                $enable = wc_clean($_POST['_storelly_pb_enable']);
                update_post_meta($post_id, '_storelly_pb_enable', $enable);
            }
        }
        public function hidden_custom_order_item_metada($order_items) {
            $order_items[] = '_pcpb_option_price';
            $order_items[] = '_pcpb_field';
            $order_items[] = '_pcpb_options';
            $order_items[] = '_pcpb_original_price';
            $order_items[] = '_pcpb_folder';
            return $order_items;
        }
        public function admin_order_item_thumbnail($image = "", $item_id = "", $item = "") {
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
        public function storelly_add_google_font() {
            $data = array(
                'mes'   =>  esc_html__('You do not have permission to add font!', 'pc-product-builder'),
                'flag'  => 0
            );
            if (!wp_verify_nonce($_POST['nonce'], 'storelly_update_fonts')) {
                die('Security error');
            }
            $gg_fonts = array();
            if (!isset($_POST['fonts'])) {
                die('Empty data');
            } else {
                $all_fonts  = json_decode(file_get_contents(STORELLY_PB_PLUGIN_DIR . '/data/google-fonts-ttf.json'))->items;
                $fonts      = json_decode(stripslashes($_POST['fonts']));
                foreach ($fonts as $key => $font) {
                    $subset = 'all';
                    $file = array('r' => 1);
                    foreach ($all_fonts as $f) {
                        if ($font->name == $f->family) {
                            $subset = $f->subsets[0];
                            if (isset($f->files->regular)) {
                                $file['r'] = $f->files->regular;
                            } else {
                                $file['r'] = reset($f->files);
                            }
                            if (isset($f->files->italic)) {
                                $file['i'] = $f->files->italic;
                            }
                            if (isset($f->files->{"700"})) {
                                $file['b'] = $f->files->{"700"};
                            }
                            if (isset($f->files->{"700italic"})) {
                                $file['bi'] = $f->files->{"700italic"};
                            }
                            break;
                        }
                    }
                    $gg_fonts[] = array(
                        "id"    =>  $key,
                        "name"    =>  $font->name,
                        "alias"    =>  $font->name,
                        "type"   =>  "google",
                        "subset"   =>  $subset,
                        "file"   =>  $file,
                        "cat" => array("99")
                    );
                }
            }
            $path_font      = STORELLY_PB_FONT_DIR . '/googlefonts.json';
            file_put_contents($path_font, json_encode($gg_fonts));
            $data['mes']    = esc_html__('The google fonts have been added successfully!', 'pc-product-builder');
            $data['flag']   = 1;
            echo json_encode($data);
            wp_die();
        }
        public function storelly_manager_fonts() {
            $subsets                = Storelly_PB_Util::storelly_font_subsets();
            $current_subset         = 'all';
            $current_cat            = filter_input(INPUT_GET, "cat_id", FILTER_VALIDATE_INT);

            include_once(STORELLY_PB_PLUGIN_DIR . 'views/manager-fonts.php');
        }
        public function storelly_settings() {
            $storelly_pb_settings = get_option('storelly_pb_settings');
            if (!isset($storelly_pb_settings['enable_cloud2print_api'])) {
                $storelly_pb_settings['enable_cloud2print_api'] = 'no';
            }
            $message = '';
            $status = '';

            if (isset($_POST['_action_storelly_settings']) && $_POST['_action_storelly_settings'] === 'submit') {
                $storelly_enable_cloud2print_api      = isset($_POST['storelly_enable_cloud2print_api']) ? sanitize_text_field($_POST['storelly_enable_cloud2print_api']) : 'no';
                $message        = esc_html__('Your settings have been saved.', 'pc-product-builder');
                $status         = 'updated';
                $storelly_pb_settings['enable_cloud2print_api'] = $storelly_enable_cloud2print_api;
                update_option('storelly_pb_settings', $storelly_pb_settings);
            }
            include_once(STORELLY_PB_PLUGIN_DIR . 'views/menu-settings.php');
        }
        public function convert_svg_embed($path) {
            $svgs       = Storelly_IO::get_list_files_by_type($path, 1, 'svg');
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
                    $path_image = Storelly_IO::convert_url_to_path($img_src);
                    $data = nbd_file_get_contents($path_image);
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
        public function storelly_download_order_designs() {
            $item_ids     = isset($_POST['item_ids']) ? $_POST['item_ids'] : array();
            $order_id           = isset($_POST['order_id']) ? sanitize_text_field($_POST['order_id']) : '';
            $type_download      = isset($_POST['type_download']) ? sanitize_text_field($_POST['type_download']) : '';
            $files = array();
            $option_name = array();
            if (is_array($item_ids) && count($item_ids) > 0) {
                foreach ($item_ids as $key => $item_id) {
                    $folder = wc_get_order_item_meta($item_id, '_pcpb_folder', true);
                    $item_files = array();
                    $item_option_name = array();
                    if ($folder) {
                        $path           = STORELLY_PB_CUSTOMER_DIR . '/' . $folder;
                        if ($type_download == 'svg') {
                            $svg_path = $path . '/svg';
                            if (!file_exists($svg_path)) {
                                $this->convert_svg_embed($path);
                            }
                            $item_files = Storelly_IO::get_list_files_by_type($svg_path, 1, 'svg');
                        } else if ($type_download == 'png') {
                            $item_files = Storelly_IO::get_list_files_by_type($path, 1, 'png');
                        } else if ($type_download == 'png-preview') {
                            $item_files = Storelly_IO::get_list_files_by_type($path . '/preview', 1, 'png');
                        } else if ($type_download == 'pdf') {
                            $item_files = Storelly_Export_PDF::exportPDF($folder, false);
                        } else if ($type_download == 'pdf-preview') {
                            $item_files = Storelly_Export_PDF::exportPDF($folder, true);
                        }
                    }
                    if (count($item_files)) {
                        foreach ($item_files as $item_file) {
                            global $wpdb;
                            $order_item_name = $wpdb->get_var(
                                $wpdb->prepare(
                                    "SELECT order_item_name FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d LIMIT 1;",
                                    $item_id
                                )
                            );
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
                exit();
            } else {
                $pathZip = STORELLY_PB_DATA_DIR . '/download/' . $order_id . '_' . $type_download . '.zip';
                $urlZip = STORELLY_PB_DATA_URL . '/download/' . $order_id . '_' . $type_download . '.zip';
                if (Storelly_PB_Util::zip_files($zip_files, $pathZip, $option_name)) {
                    $response['flag'] = 1;
                    $response['file'] = $urlZip;
                }
            }
            echo json_encode($response);
            wp_die();
        }
    }
}
$storelly_pb_admin_options = Storelly_PB_Admin_Options::instance();
$storelly_pb_admin_options->init();
