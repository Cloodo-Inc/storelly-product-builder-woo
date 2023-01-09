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
        }
        public function ajax() {
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
 product_ids text NULL, 
 created datetime NOT NULL default '0000-00-00 00:00:00',
 modified datetime NOT NULL default '0000-00-00 00:00:00', 
 created_by BIGINT(20) NULL, 
 modified_by BIGINT(20) NULL,  
 fields longtext,
 PRIMARY KEY  (id)
) $collate; 
                ";
                @dbDelta($tables);
            }
        }
        public function admin_enqueue_scripts($hook) {
            wp_register_script('angularjs', PRINTCART_PB_ASSETS_URL . 'libs/angular.min.js', array('jquery'), '1.6.9');
            wp_register_style('nbd_options', PRINTCART_PB_CSS_URL . 'admin-options.css', array('wp-color-picker'), PRINTCART_PB_VERSION);
            wp_register_script('snap_svg', PRINTCART_PB_ASSETS_URL . 'libs/snap.svg.js', array(), '0.3.0');

            if ($hook == 'toplevel_page_pc_product_builder_options') {
                wp_register_script('nbd_options', PRINTCART_PB_JS_URL . 'admin-options.js', array('jquery', 'jquery-ui-resizable', 'jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-sortable', 'jquery-ui-datepicker', 'jquery-ui-autocomplete', 'wp-color-picker', 'angularjs', 'wc-enhanced-select', 'snap_svg'), PRINTCART_PB_VERSION);
                wp_localize_script('nbd_options', 'nbd_options', array(
                    'search_products_nonce'     =>  wp_create_nonce("search-products"),
                ));
                wp_enqueue_style(array('wp-jquery-ui-dialog', 'wp-color-picker', 'nbd_options'));
                wp_enqueue_script(array('wpdialogs', 'nbd_options'));
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
                        $options['product_ids']     = isset($_options['product_ids']) ? (!is_null(unserialize($_options['product_ids'])) ? unserialize($_options['product_ids']) : array()) : array();
                    } else {
                        $options = $this->build_options();
                        $options['id']              = 0;
                        $options['title']           = '';
                        $options['product_ids']     = array();
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
                'priority'      => wc_clean($_POST['priority']),
                'date_from'     => wc_clean($_POST['date_from']),
                'date_to'       => wc_clean($_POST['date_to']),
                'apply_for'     => wc_clean($_POST['apply_for']),
                'product_cats'  => isset($_POST['product_cats']) ? serialize($_POST['product_cats']) : serialize(array()),
                'product_ids'   => isset($_POST['product_ids']) ? serialize($_POST['product_ids']) : serialize(array()),
                'modified'      => $modified_date->format('Y-m-d H:i:s')
            );
            $post_options = $_POST['options'];
            if (isset($_POST['options']['jsonFields'])) {
                $post_options['fields'] = json_decode(stripslashes($_POST['options']['jsonFields']), true);
                unset($post_options['jsonFields']);
            }
            $arr['fields'] = serialize($this->validate_option($post_options));

            global $wpdb;
            $date = new DateTime();
            if ($id > 0) {
                $arr['modified']    = $date->format('Y-m-d H:i:s');
                $arr['modified_by'] = wp_get_current_user()->ID;
                $result             = $wpdb->update("{$wpdb->prefix}nbdesigner_options", $arr, array('id' => $id));
            } else {
                $arr['created']     = $date->format('Y-m-d H:i:s');
                $arr['created_by']  = wp_get_current_user()->ID;
                $result             = $wpdb->insert("{$wpdb->prefix}nbdesigner_options", $arr);
                $id                 = $result ?  $wpdb->insert_id : 0;
            }
            $this->clear_transients();
            do_action('nbo_save_print_option', $arr);
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
                    if ($tab != 'id' && $tab != 'nbd_type' && $tab != 'nbpb_type' && $tab != 'nbe_type') {
                        foreach ($data as $key => $f) {
                            if (!in_array($key, array('price_no_range', 'price_depend_no', 'component_icon', 'page_display', 'exclude_page', 'auto_select_page', 'mesure', 'mesure_type', 'mesure_min_area', 'mesure_range', 'mesure_base_pages', 'mesure_base_qty', 'min_width', 'max_width', 'step_width', 'default_width', 'min_height', 'max_height', 'step_height', 'default_height'))) {
                                $funcname = "build_config_" . $tab . '_' . $key;
                                if (is_callable(array($this, $funcname))) {
                                    $options['fields'][$f_key][$tab][$key] = $this->$funcname($f);
                                }
                            }
                            if ($key == 'component_icon') {
                                $options['fields'][$f_key][$tab]['component_icon_url'] = printcart_get_image_thumbnail($f);
                            }
                        }
                    }
                    if ($tab == 'nbpb_type') {
                        $options['fields'][$f_key]['nbd_template'] = 'nbd.' . $data;
                    }
                }
            }
            return $options;
        }
        public function build_config_conditional_show($value = null) {
            if (is_null($value)) $value = 'n';
            return $value;
        }
        public function build_config_conditional_logic($value = null) {
            if (is_null($value)) $value = 'a';
            return $value;
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
                if ($tab != 'id') {
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
                    'enabled'           => null,
                    'required'          => null,
                    'published'         => null,
                    'price_type'        => null,
                    'attributes'        => null
                ),
                'appearance' => array(
                    'display_type'          => null,
                    'change_image_product'  => null,
                    'css_class'             => null
                )
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
        public function build_config_general_price_type($value = null) {
            if (is_null($value)) $value = 'f';
            return array(
                'title'         => __('Price type', 'web-to-print-online-designer'),
                'description'   => __('Here you can choose how the price is calculated. Depending on the field there various types you can choose.'),
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'       => 'f',
                        'text'      => __('Fixed amount', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'       => 'p',
                        'text'      => __('Percent of the original price', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'       => 'p+',
                        'text'      => __('Percent of the original price + options', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'       => 'c',
                        'text'      => __('Current value * price', 'web-to-print-online-designer'),
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
                        'text'      => __('Price per char', 'web-to-print-online-designer'),
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
                    array(
                        'key'       => 'mf',
                        'text'      => __('Math formula', 'web-to-print-online-designer')
                    )
                )
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
                        'enable_subattr'        => 0,
                        'preview_type'          => 'i',
                        'image'                 => 0,
                        'image_url'             => '',
                        'product_image'         => 0,
                        'product_image_url'     => '',
                        'color'                 => '#ffffff',
                        'sub_attributes'        => array(),
                        'sattr_display_type'    => 's',
                        'enable_con'            => 0,
                        'con_show'              => 'n',
                        'con_logic'             => 'a',
                        'depend'                => array(
                            0   => array(
                                'id'        => '',
                                'operator'  => 'i',
                                'val'       => '',
                                'subval'    => ''
                            )
                        ),
                        'implicit_value'        => ''
                    )
                );
            } else {
                $options = $attributes['options'];
            };
            foreach ($options as $key => $option) {
                $options[$key]['enable_subattr']     = isset($options[$key]['enable_subattr']) ? $options[$key]['enable_subattr'] : 0;
                $options[$key]['sub_attributes']     = isset($options[$key]['sub_attributes']) ? $options[$key]['sub_attributes'] : array();
                $options[$key]['sattr_display_type'] = isset($options[$key]['sattr_display_type']) ? $options[$key]['sattr_display_type'] : 's';
                $options[$key]['enable_con']         = isset($options[$key]['enable_con']) ? $options[$key]['enable_con'] : 0;
                $options[$key]['con_show']           = isset($options[$key]['con_show']) ? $options[$key]['con_show'] : 'n';
                $options[$key]['con_logic']          = isset($options[$key]['con_logic']) ? $options[$key]['con_logic'] : 'a';
                $options[$key]['depend']             = (isset($option['depend']) && count($option['depend'])) ? $option['depend'] : array(0 => array('id' => '', 'operator' => 'i', 'val' => '', 'subval' => ''));
                $options[$key]['implicit_value']     = isset($option['implicit_value']) ? $option['implicit_value'] : '';

                if (isset($option['enable_subattr'])) {
                    foreach ($options[$key]['sub_attributes'] as $sak => $sa) {
                        $options[$key]['sub_attributes'][$sak]['enable_con']        = isset($sa['enable_con']) ? $sa['enable_con'] : 0;
                        $options[$key]['sub_attributes'][$sak]['con_show']          = isset($sa['con_show']) ? $sa['con_show'] : 'n';
                        $options[$key]['sub_attributes'][$sak]['con_logic']         = isset($sa['con_logic']) ? $sa['con_logic'] : 'a';
                        $options[$key]['sub_attributes'][$sak]['depend']            = (isset($sa['depend']) && count($sa['depend'])) ? $sa['depend'] : array(0 => array('id' => '', 'operator' => 'i', 'val' => '', 'subval' => ''));
                        $options[$key]['sub_attributes'][$sak]['implicit_value']    = isset($sa['implicit_value']) ? $sa['implicit_value'] : '';
                    }
                }

                $options[$key]['image_url']          = printcart_get_image_thumbnail($option['image']);
                if (isset($options[$key]['product_image'])) {
                    $options[$key]['product_image_url'] = printcart_get_image_thumbnail($option['product_image']);
                }
                if (isset($attributes['bg_type'])) {
                    if ($attributes['bg_type'] == 'i') {
                        foreach ($option['bg_image'] as $k => $bg) {
                            $options[$key]['bg_image_url'][$k] = printcart_get_image_thumbnail($bg);
                        }
                    } else {
                        $options[$key]['bg_image']      = array();
                        $options[$key]['bg_image_url']  = array();
                    }
                }
                if (isset($option['enable_subattr'])) {
                    foreach ($options[$key]['sub_attributes'] as $sak => $sa) {
                        $options[$key]['sub_attributes'][$sak]['image_url'] = printcart_get_image_thumbnail($sa['image']);
                    }
                }
                if (isset($option['overlay_image'])) {
                    foreach ($option['overlay_image'] as $k => $ov) {
                        $options[$key]['overlay_image_url'][$k] = printcart_get_image_thumbnail($ov);
                    }
                }
                if (isset($option['frame_image'])) {
                    $options[$key]['frame_image_url'] = printcart_get_image_thumbnail($option['frame_image']);
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
                'depend'          =>  array(
                    array(
                        'field'     => 'data_type',
                        'operator'  => '=',
                        'value'     => 'm'
                    )
                ),
                'options'         => $options
            );
        }
        public function build_config_appearance_display_type( $value = null ){
            if (is_null($value)) $value = 'd';
            return array(  
                'title'         => __( 'Display type', 'web-to-print-online-designer'),
                'description'   => '',
                'value'         => $value,
                'type'          => 'dropdown',
                'options'       => array(
                    array(
                        'key'   => 'd',
                        'text'  => __( 'Dropdown', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'   => 'r',
                        'text'  => __( 'Radio button', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'   => 's',
                        'text'  => __( 'Swatch', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'   => 'l',
                        'text'  => __( 'Label', 'web-to-print-online-designer')
                    ),    
                     array(
                        'key'   => 'ad',
                        'text'  => __( 'Advanced Dropdown', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'   => 'xl',
                        'text'  => __( 'Large label', 'web-to-print-online-designer')
                    )
                )
            );
        }
        public function build_config_appearance_change_image_product( $value = null ){
            if (is_null($value)) $value = 'n';
            return array(  
                'title'         => __( 'Changes product image', 'web-to-print-online-designer'),
                'description'   => __('Choose whether to change the product image.', 'web-to-print-online-designer'),
                'type'          => 'dropdown',
                'value'         => $value,
                'options'       => array(
                    array(
                        'key'   => 'y',
                        'text'  => __( 'Yes', 'web-to-print-online-designer')
                    ),
                    array(
                        'key'   => 'n',
                        'text'  => __( 'No', 'web-to-print-online-designer')
                    )
                )
            );
        }
        public function build_config_appearance_css_class( $value = null ){
            if (is_null($value)) $value = '';
            return array(
                'title'         => __( 'CSS Class', 'web-to-print-online-designer'),
                'description'   => '',
                'type'          => 'text',
                'value'         => $value
            );
        }
        private function validate_option($options) {
            if ($options['display_type'] == 2) {
                if (!isset($options['pm_hoz'])) {
                    $options['pm_hoz'] = array();
                }
                if (!isset($options['pm_ver'])) {
                    $options['pm_ver'] = array();
                }
                if (!isset($options['manual_build_pm'])) {
                    $options['manual_build_pm'] = 'off';
                    $options['manual_pm']       = '';
                }
            } else if ($options['display_type'] == 3) {
                if (!isset($options['bulk_fields'])) {
                    $options['bulk_fields'] = array();
                }
            } else if ($options['display_type'] == 4) {
                if (!isset($options['groups'])) {
                    $options['groups'] = array();
                }
            } else if ($options['display_type'] == 6) {
                if (!isset($options['popup_fields'])) {
                    $options['popup_fields']        = array();
                    $options['popup_trigger_field'] = '';
                    $options['popup_trigger_value'] = '';
                }
            } else if (!isset($options['display_type'])) {
                $options['display_type'] = 1;
            }

            if (isset($options['popup_trigger_field'])) {
                if (strpos($options['popup_trigger_field'], 'string') !== FALSE) {
                    $options['popup_trigger_field'] = '';
                }
            }
            if (isset($options['popup_trigger_value'])) {
                if (strpos($options['popup_trigger_value'], 'undefined') !== FALSE) {
                    $options['popup_trigger_value'] = '';
                }
            }
            return $options;
        }
    }
}
$printcart_pb_admin_options = Printcart_PB_Admin_Options::instance();
$printcart_pb_admin_options->init();
