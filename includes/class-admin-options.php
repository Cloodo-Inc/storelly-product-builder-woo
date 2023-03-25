<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('Printcart_PB_Admin_Options')) {
    class Printcart_PB_Admin_Options {
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
            add_action('printcart_pb_menu', array($this, 'tab_menu'));
            add_action('printcart_create_tables', array($this, 'create_options_table'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
            add_action('add_meta_boxes', array($this, 'add_meta_boxes'), 35);
            add_action('save_post', array($this, 'save_product_option'));

            // Alter the product thumbnail in order
            add_filter('woocommerce_admin_order_item_thumbnail', array($this, 'admin_order_item_thumbnail'), 50, 3);
            //Hide some price option data in order
            add_filter('woocommerce_hidden_order_itemmeta', array($this, 'hidden_custom_order_item_metada'));
        }
        public function ajax() {
            $ajax_events = array(
                'nbd_download_option_image'     => true,
                'nbd_get_media_full_size_url'   => true
            );
            foreach ($ajax_events as $ajax_event => $nopriv) {
                add_action('wp_ajax_' . $ajax_event, array($this, $ajax_event));
                if ($nopriv) {
                    // AJAX can be used for frontend ajax requests
                    add_action('wp_ajax_nopriv_' . $ajax_event, array($this, $ajax_event));
                }
            }
        }
        public function nbd_get_media_full_size_url() {
            if (!wp_verify_nonce($_POST['nonce'], 'save-design') && PRINTCART_ENABLE_NONCE) {
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
            if (!wp_verify_nonce($_POST['nonce'], 'save-design') && PRINTCART_ENABLE_NONCE) {
                die('Security error');
            }
            $result = array(
                'flag'      => 1,
                'image'     => array()
            );
            $url = wc_clean($_POST['image']);
            require_once(PRINTCART_PB_PLUGIN_DIR . 'includes/class-download-image.php');
            if (strpos($url, get_site_url()) > -1) {
                $result['image'] = array(
                    'current_site'  => 1
                );
            } else {
                $download_remote_image = new Printcart_PB_Download_Image($url, array());
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
                    'pc_product_builder_options',
                    array($this, 'product_builder_options'),
                    PRINTCART_PB_PLUGIN_URL . '/assets/images/logo.svg'
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
            if (PRINTCART_PB_VERSION != get_option("printcart_version_plugin")) {
                $tables =  "
CREATE TABLE {$wpdb->prefix}printcart_product_builder_options ( 
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
            wp_register_script('angularjs', PRINTCART_PB_ASSETS_URL . 'libs/angular.min.js', array('jquery'), '1.6.9');
            wp_register_style('printcart_options', PRINTCART_PB_CSS_URL . 'admin-options.css', array('wp-color-picker', 'wp-jquery-ui-dialog'), PRINTCART_PB_VERSION);
            wp_register_style('printcart-general', PRINTCART_PB_CSS_URL . 'printcart-general.css', array('dashicons'), PRINTCART_PB_VERSION);
            wp_register_script('snap_svg', PRINTCART_PB_ASSETS_URL . 'libs/snap.svg.js', array(), '0.3.0');
            wp_register_script('pc-tiptip', PRINTCART_PB_ASSETS_URL . 'js/tiptip.js', array('jquery'), PRINTCART_PB_VERSION);
            wp_enqueue_style('printcart-general');

            if ($hook == 'toplevel_page_pc_product_builder_options') {
                wp_register_script('printcart_options', PRINTCART_PB_JS_URL . 'admin-options.js', array('jquery', 'wpdialogs', 'jquery-ui-resizable', 'jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-sortable', 'jquery-ui-datepicker', 'jquery-ui-autocomplete', 'wp-color-picker', 'angularjs', 'wc-enhanced-select', 'snap_svg', 'pc-tiptip'), PRINTCART_PB_VERSION);
                wp_localize_script('printcart_options', 'printcart_options', array(
                    'search_products_nonce'     => wp_create_nonce("search-products"),
                    'calendar_image'            => PRINTCART_PB_PLUGIN_URL . 'assets/images/calendar.png',
                    'printcart_options_lang'    => $this->printcart_option_i18n(),
                ));
                wp_enqueue_style('printcart_options');
                wp_enqueue_script('printcart_options');
            }
        }
        public function product_builder_options() {
            if (isset($_GET['action']) && $_GET['action'] != 'copy') {
                $paged      = get_query_var('paged', 1);
                $message    = array('content'  => '');
                if ($_GET['action'] == 'unpublish') {
                    $this->unpublish_option($_REQUEST['id']);
                    wp_redirect(esc_url_raw(add_query_arg(array('paged' => $paged), admin_url('admin.php?page=pc_product_builder_options'))));
                } else {
                    $id = (isset($_REQUEST['id']) && absint($_REQUEST['id']) > 0) ? absint($_REQUEST['id']) : 0;
                    if (isset($_POST['save']) || isset($_POST['options'])) {
                        $result = $this->save_option();
                        if ($result['status']) {
                            $message = array(
                                'flag'      => 'success',
                                'content'   => esc_html__('Option updated.', 'web-to-print-online-designer')
                            );
                            if ($id == 0) {
                                $id = $result['id'];
                                wp_redirect(esc_url_raw(add_query_arg(array(
                                    'paged'     => 1,
                                    'action'    => 'edit',
                                    'id'        => $id
                                ), admin_url('admin.php?page=pc_product_builder_options'))));
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
                    include_once(PRINTCART_PB_PLUGIN_DIR . 'views/options/edit-option.php');
                }
            } else {
                require_once PRINTCART_PB_PLUGIN_DIR . 'includes/options/fields-list-table.php';
                $nbd_options = new Printcart_Options_List_Table();
                include_once(PRINTCART_PB_PLUGIN_DIR . 'views/options/options-list-table.php');
            }
        }
        function get_extension_filename() {
        }
        public function unpublish_option($id) {
            global $wpdb;
            $result = $wpdb->update($wpdb->prefix . 'pc_product_builder_options', array(
                'published' => 0
            ), array('id' => esc_sql($id)));
            if ($result) $this->clear_transients();
        }
        private function clear_transients() {
        }
        public function get_option($id) {
            global $wpdb;
            $sql = "SELECT * FROM {$wpdb->prefix}printcart_product_builder_options";
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
                $result             = $wpdb->update("{$wpdb->prefix}printcart_product_builder_options", $arr, array('id' => $id));
            } else {
                $arr['created']     = $date->format('Y-m-d H:i:s');
                $arr['created_by']  = wp_get_current_user()->ID;
                $result             = $wpdb->insert("{$wpdb->prefix}printcart_product_builder_options", $arr);
                $id                 = $result ?  $wpdb->insert_id : 0;
            }
            $this->clear_transients();
            do_action('printcart_save_print_option', $arr);
            return array(
                'status'    => $result,
                'id'        => $id
            );
        }
        public function build_options($options = null) {
            if (is_null($options)) {
                $options = array(
                    'version'                   => PRINTCART_PB_NUMBER_VERSION,
                    'fields'                    => array(
                        0   =>  $this->default_field()
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
                    $options['views'][$vkey]['base_url'] = Printcart_PB_Util::pritcart_get_image_thumbnail($view['base']);
                }
            }
            return $options;
        }
        // public function build_config_conditional_show($value = null) {
        //     if (is_null($value)) $value = 'n';
        //     return $value;
        // }
        // public function build_config_conditional_logic($value = null) {
        //     if (is_null($value)) $value = 'a';
        //     return $value;
        // }
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
            if (is_null($value)) $value = __('Option name', 'web-to-print-online-designer');
            return array(
                'title'         => __('Option name', 'web-to-print-online-designer'),
                'description'   =>  '',
                'value'         => $value,
                'type'          => 'text'
            );
        }
        public function build_config_general_description($value = null) {
            if (is_null($value)) $value = __('Option description', 'web-to-print-online-designer');
            return array(
                'title'         => __('Description', 'web-to-print-online-designer'),
                'description'   => '',
                'value'         => $value,
                'type'          => 'textarea'
            );
        }
        public function build_config_general_data_type($value = null) {
            if (is_null($value)) $value = 'm';
            return array(
                'title'         => esc_html__('Data type', 'web-to-print-online-designer'),
                'description'   => '',
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'       => 'i',
                        'text'      => esc_html__('Custom input', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'       => 'm',
                        'text'      => esc_html__('Multiple options', 'web-to-print-online-designer')
                    )
                )
            );
        }
        public function build_config_general_input_type($value = null) {
            if (is_null($value)) $value = 't';
            return array(
                'title'         => esc_html__('Input type', 'web-to-print-online-designer'),
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
                        'text'      => esc_html__('Text', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'       => 'u',
                        'text'      => esc_html__('Upload', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'       => 'a',
                        'text'      => esc_html__('Textarea', 'web-to-print-online-designer')
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
                'title'         => esc_html__('Input option', 'web-to-print-online-designer'),
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
                'title'         => esc_html__('Text input option', 'web-to-print-online-designer'),
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
                'title'         => __('Enabled', 'web-to-print-online-designer'),
                'description'   => __('Choose whether the option is enabled or not.', 'web-to-print-online-designer'),
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'       => 'y',
                        'text'      => __('Yes', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'       => 'n',
                        'text'      => __('No', 'web-to-print-online-designer')
                    )
                )
            );
        }
        public function build_config_general_published($value = null) {
            if (is_null($value)) $value = 'y';
            return array(
                'title'         => __('Published', 'web-to-print-online-designer'),
                'description'   => __('Show in summary options or not.', 'web-to-print-online-designer'),
                'value'         => $value,
                'type'          => 'dropdown',
                'options' =>    array(
                    array(
                        'key'       => 'y',
                        'text'      => __('Yes', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'       => 'n',
                        'text'      => __('No', 'web-to-print-online-designer')
                    )
                )
            );
        }
        public function build_config_general_required($value = null) {
            if (is_null($value)) $value = 'n';
            return array(
                'title'         => __('Required', 'web-to-print-online-designer'),
                'description'   => __('Choose whether the option is required or not.'),
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'       => 'y',
                        'text'      => __('Yes', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'       => 'n',
                        'text'      => __('No', 'web-to-print-online-designer')
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
                    'max_size'      =>  Printcart_PB_Util::printcart_get_max_upload_default(),
                    'allow_type'    =>  'png,jpg,jpeg'
                );
            }
            return array(
                'title'         => esc_html__('Upload file option', 'web-to-print-online-designer'),
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
                'title'         => esc_html__('Price type', 'web-to-print-online-designer'),
                'description'   => esc_html__('Here you can choose how the price is calculated. Depending on the field there various types you can choose.'),
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'       => 'f',
                        'text'      => esc_html__('Fixed amount', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'       => 'p',
                        'text'      => esc_html__('Percent of the original price', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'       => 'p+',
                        'text'      => esc_html__('Percent of the original price + options', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'       => 'c',
                        'text'      => esc_html__('Current value * price', 'web-to-print-online-designer'),
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
                        'text'      => esc_html__('Price per char', 'web-to-print-online-designer'),
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
                'title'         => esc_html__('Additional Price', 'web-to-print-online-designer'),
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
                        'name'                  => __('Attribute name', 'web-to-print-online-designer'),
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
                        'implicit_value'        => ''
                    )
                );
            } else {
                $options = $attributes['options'];
            };
            foreach ($options as $key => $option) {
                $options[$key]['implicit_value']     = isset($option['implicit_value']) ? $option['implicit_value'] : '';
                $options[$key]['image_url']          = Printcart_PB_Util::printcart_get_image_thumbnail($option['image']);
                if (isset($options[$key]['product_image'])) {
                    $options[$key]['product_image_url'] = Printcart_PB_Util::printcart_get_image_thumbnail($option['product_image']);
                }
            }
            $same_size          = isset($attributes['same_size']) ? $attributes['same_size'] : 'y';
            $bg_type            = isset($attributes['bg_type']) ? $attributes['bg_type'] : 'i';
            $show_as_pt         = isset($attributes['show_as_pt']) ? $attributes['show_as_pt'] : 'n';
            $number_of_sides    = isset($attributes['number_of_sides']) ? $attributes['number_of_sides'] : 2;
            return array(
                'title'           => __('Attributes', 'web-to-print-online-designer'),
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
                        $configs[$key][$skey]['views'][$vkey]['image_url']  = Printcart_PB_Util::pritcart_get_image_thumbnail($view['image']);
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
                'title'         => __('Display type', 'web-to-print-online-designer'),
                'description'   => '',
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'   => 'd',
                        'text'  => __('Dropdown', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'   => 'r',
                        'text'  => __('Radio button', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'   => 's',
                        'text'  => __('Swatch', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'   => 'l',
                        'text'  => __('Label', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'   => 'ad',
                        'text'  => __('Advanced Dropdown', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'   => 'xl',
                        'text'  => __('Large label', 'web-to-print-online-designer')
                    )
                )
            );
        }
        public function build_config_appearance_change_image_product($value = null) {
            if (is_null($value)) $value = 'n';
            return array(
                'title'         => __('Changes product image', 'web-to-print-online-designer'),
                'description'   => __('Choose whether to change the product image.', 'web-to-print-online-designer'),
                'type'          => 'dropdown',
                'value'         => $value,
                'options'       => array(
                    array(
                        'key'   => 'y',
                        'text'  => __('Yes', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'   => 'n',
                        'text'  => __('No', 'web-to-print-online-designer')
                    )
                )
            );
        }
        public function build_config_appearance_css_class($value = null) {
            if (is_null($value)) $value = '';
            return array(
                'title'         => __('CSS Class', 'web-to-print-online-designer'),
                'description'   => '',
                'type'          => 'text',
                'value'         => $value
            );
        }
        function printcart_option_i18n() {
            return array(
                'nbpb_com'              => esc_html__('Component', 'web-to-print-online-designer'),
                'nbpb_text'             => esc_html__('Text', 'web-to-print-online-designer'),
                'nbpb_image'            => esc_html__('Image', 'web-to-print-online-designer'),
                'attribute_name'        => esc_html__('Attribute name', 'web-to-print-online-designer'),
                'sub_attribute_name'    => esc_html__('Sub attribute name', 'web-to-print-online-designer'),
            );
        }
        public function add_meta_boxes() {
            add_meta_box('printcart_product_builder', __('Printcart product builder', 'web-to-print-online-designer'), array($this, 'meta_box'), 'product', 'normal', 'high');
        }
        public function meta_box() {
            $post_id            = get_the_ID();
            $nbdpb_enable       = get_post_meta($post_id, '_printcart_pb_enable', true);
            $option_id          = $this->get_product_option($post_id);
            $option_id          = $option_id ? $option_id : 0;
            $link_edit_option   = add_query_arg(
                array(
                    'product_id'    => $post_id,
                    'action'        => 'edit',
                    'paged'         => 1,
                    'id'            => $option_id
                ),
                admin_url('admin.php?page=pc_product_builder_options')
            );
            include_once(PRINTCART_PB_PLUGIN_DIR . 'views/options/meta-box.php');
        }
        public function get_product_option($product_id) {
            $enable = get_post_meta($product_id, '_printcart_pb_enable', true);
            if (!$enable) return false;
            $option_id = get_transient('printcart_product_builder_' . $product_id);
            if (false === $option_id) {
                global $wpdb;
                $sql = "SELECT id, product_ids FROM {$wpdb->prefix}printcart_product_builder_options WHERE published = 1";
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
                        set_transient('printcart_product_builder_' . $product_id, $option_id);
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
            if (isset($_POST['_printcart_pb_enable'])) {
                $enable = wc_clean($_POST['_printcart_pb_enable']);
                update_post_meta($post_id, '_printcart_pb_enable', $enable);
            }
        }
        public function hidden_custom_order_item_metada($order_items) {
            $order_items[] = '_pcpb_option_price';
            $order_items[] = '_pcpb_field';
            $order_items[] = '_pcpb_options';
            $order_items[] = '_pcpb_original_price';
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
    }
}
$printcart_pb_admin_options = Printcart_PB_Admin_Options::instance();
$printcart_pb_admin_options->init();
