<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('STORELLY_FRONTEND_OPTIONS')) {
    class STORELLY_FRONTEND_OPTIONS {
        protected static $instance;
        public $is_edit_mode = FALSE;
        /** Holds the cart key when editing a product in the cart **/
        public $cart_edit_key = NULL;
        public $appid;
        public $new_add_to_cart_key = FALSE;
        /** Edit option in cart helper **/
        public function __construct() {

            if (isset($_REQUEST['pcpb_cart_item_key']) && sanitize_text_field($_REQUEST['pcpb_cart_item_key']) != '') {
                $this->is_edit_mode = true; 
                $this->cart_edit_key = sanitize_text_field( $_REQUEST['pcpb_cart_item_key'] );
            }
            $this->appid = "nbo-app-" . time() . rand(1, 1000);
        }
        public static function instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        public function init() {
            add_filter('upload_mimes', [__CLASS__, 'storelly_allow_uploads']);
            
            add_action('woocommerce_before_add_to_cart_button', array($this, 'show_option_fields'));

            // handle customer input as order item meta
            add_filter('woocommerce_get_item_data', array($this, 'get_item_data'), 10, 2);
            // Add item data to the cart
            add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 4);
            // Alters add to cart text when editing a product
            add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'add_to_cart_text'), 9999, 1);
            // Remove product from cart when editing a product
            add_filter('woocommerce_add_to_cart_validation', array($this, 'remove_previous_product_from_cart'), 99999, 6);
            // Alters the cart item key when editing a product
            add_action('woocommerce_add_to_cart', array($this, 'add_to_cart'), 10, 6);
            // Change quantity value when editing a cart item
            add_filter('woocommerce_quantity_input_args', array($this, 'quantity_input_args'), 9999, 2);
            // Calculate totals on remove from cart/update
            add_action('woocommerce_cart_loaded_from_session', array($this, 're_calculate_price'), 1, 1);
            // Add meta to order
            add_action('woocommerce_checkout_create_order_line_item', array($this, 'order_line_item'), 50, 3);
            // Change option independent quantity prices to cart fee
            add_action('woocommerce_cart_calculate_fees', array($this, 'add_cart_fee'), 1, 1);
            // Gets saved option when using the order again function
            add_filter('woocommerce_order_again_cart_item_data', array($this, 'order_again_cart_item_data'), 50, 3);
            // Alter the product thumbnail in cart
            add_filter('woocommerce_cart_item_thumbnail', array($this, 'cart_item_thumbnail'), 50, 2);
            // Remove item quantity in checkout
            add_filter('woocommerce_checkout_cart_item_quantity', array($this, 'remove_cart_item_quantity'), 10, 3);
            // Adds edit link on product title in cart and item quantity
            add_filter('woocommerce_cart_item_name', array($this, 'cart_item_name'), 50, 3);
            // persist the cart item data, and set the item price (when needed) first, before any other plugins
            add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 1, 2);
            // on add to cart set the price when needed, and do it first, before any other plugins
            add_filter('woocommerce_add_cart_item', array($this, 'set_product_prices'), 1, 1);

            add_action('wp_enqueue_scripts', array($this , 'frontend_enqueue_scripts'));
            
        }
        public static function storelly_allow_uploads($mimes) {
            $mimes['json'] = 'application/json';
            $mimes['svg'] = 'image/svg+xml';
            return $mimes;
        }
        
        public static function get_option($id) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'storelly_product_builder_options';
            $cache_key = 'storelly_option_' . $id;
            $cached = wp_cache_get($cache_key, 'storelly');
            if (false !== $cached) {
                return $cached;
            }
            $query = $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $options = $wpdb->get_results($query, ARRAY_A);
            if (!empty($options) && isset($options[0])) {
                wp_cache_set($cache_key, $options[0], 'storelly', 3600);
                return $options[0];
            }
            return false;
        }
        public static function get_product_option($product_id) {
            $enable = get_post_meta($product_id, '_storelly_pb_enable', true);
            if (!$enable) {
                return false;
            }
            $cache_key = 'storelly_product_builder_' . $product_id;
            $option_id = wp_cache_get($cache_key, 'storelly');
            if (false === $option_id) {
                $option_id = get_transient($cache_key);
            }
            if (false === $option_id) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'storelly_product_builder_options';
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $options = $wpdb->get_results("SELECT id, product_ids FROM {$table_name} WHERE published = 1", ARRAY_A);

                if ($options) {
                    $_options = array();
                    foreach ($options as $option) {
                        $products = unserialize($option['product_ids']);
                        if (in_array($product_id, $products, true)) {
                            $_options[] = $option;
                        }
                    }
                    $_options = array_reverse($_options);
                    $option_id = $_options[0]['id'] ?? '';

                    if ($option_id) {
                        wp_cache_set($cache_key, $option_id, 'storelly', 3600);
                        set_transient($cache_key, $option_id, 12 * HOUR_IN_SECONDS);
                    }
                }
            }
            return $option_id;
        }
        public static function recursive_stripslashes($fields) {
            $valid_fields = array();
            foreach ($fields as $key => $field) {
                if (is_array($field)) {
                    $valid_fields[$key] = self::recursive_stripslashes($field);
                } else if (!is_null($field)) {
                    $valid_fields[$key] = stripslashes($field);
                }
            }
            return $valid_fields;
        }
        public function frontend_enqueue_scripts(){
            $product_id = get_the_ID();
            $option_id = $this->get_product_option($product_id);
             if ($option_id) {
                $_options = $this->get_option($option_id);
                if ($_options) {
                    $options = unserialize($_options['fields']);
                    if (!isset($options['fields'])) {
                        $options['fields'] = array();
                    }
                    $options['fields'] = $this->recursive_stripslashes($options['fields']);
                    foreach ($options['fields'] as $key => $field) {
                        if (!isset($field['general']['attributes'])) {
                            $field['general']['attributes'] = array();
                            $field['general']['attributes']['options'] = array();
                            $options['fields'][$key]['general']['attributes'] = array();
                            $options['fields'][$key]['general']['attributes']['options'] = array();
                        }
                        if ($field['appearance']['change_image_product'] == 'y') {
                            foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                                $option['product_image'] = isset($option['product_image']) ? $option['product_image'] : 0;
                                $attachment_id = absint($option['product_image']);
                                if ($attachment_id != 0) {
                                    $image_link         = wp_get_attachment_url($attachment_id);
                                    $attachment_object  = get_post($attachment_id);
                                    $full_src           = wp_get_attachment_image_src($attachment_id, 'large');
                                    $image_title        = get_the_title($attachment_id);
                                    $image_alt          = trim(strip_tags(get_post_meta($attachment_id, '_wp_attachment_image_alt', TRUE)));
                                    $image_srcset       = function_exists('wp_get_attachment_image_srcset') ? wp_get_attachment_image_srcset($attachment_id, 'shop_single') : FALSE;
                                    $image_sizes        = function_exists('wp_get_attachment_image_sizes') ? wp_get_attachment_image_sizes($attachment_id, 'shop_single') : FALSE;
                                    $image_caption      = $attachment_object->post_excerpt;
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index] = array_replace_recursive($options['fields'][$key]['general']['attributes']['options'][$op_index], array(
                                        'imagep'        => 'y',
                                        'image_link'    => $image_link,
                                        'image_title'   => $image_title,
                                        'image_alt'     => $image_alt,
                                        'image_srcset'  => $image_srcset,
                                        'image_sizes'   => $image_sizes,
                                        'image_caption' => $image_caption,
                                        'full_src'      => $full_src[0],
                                        'full_src_w'    => $full_src[1],
                                        'full_src_h'    => $full_src[2]
                                    ));
                                } else {
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index]['imagep'] = 'n';
                                }
                            }
                        }
                        if (isset($field['nbpb_type']) && $field['nbpb_type'] == 'nbpb_com') {
                            if (isset($field['general']['pb_config'])) {
                                foreach ($field['general']['pb_config'] as $a_index => $attr) {
                                    foreach ($attr as $s_index => $sattr) {
                                        foreach ($sattr['views'] as $v_index => $view) {
                                            $pb_image_obj = wp_get_attachment_url(absint($view['image']));
                                            $options['fields'][$key]['general']['pb_config'][$a_index][$s_index]['views'][$v_index]['image_url'] =  $pb_image_obj ? $pb_image_obj : STORELLY_PB_ASSETS_URL . 'images/placeholder.png';
                                        }
                                    }
                                }
                            } else {
                                $field['general']['pb_config'] = array();
                            }
                            foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                                if( isset($option['enable_subattr']) && $option['enable_subattr'] == 'on' && isset($option['sub_attributes']) && count($option['sub_attributes']) > 0 ){
                                    foreach( $option['sub_attributes'] as $sa_index => $sattr ){
                                        $options['fields'][$key]['general']['attributes']['options'][$op_index]['sub_attributes'][$sa_index]['image_url'] = Storelly_PB_Util::storelly_get_image_thumbnail( $sattr['image'] );
                                    }
                                }else{
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index]['image_url'] = Storelly_PB_Util::storelly_get_image_thumbnail( $option['image'] );
                                }
                            };
                            $options['fields'][$key]['general']['component_icon_url'] = Storelly_PB_Util::storelly_get_image_thumbnail($field['general']['component_icon']);
                        }
                        if (isset($field['general']['attributes']['bg_type']) && $field['general']['attributes']['bg_type'] == 'i') {
                            foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                                foreach ($option['bg_image'] as $bg_index => $bg) {
                                    $bg_obj = wp_get_attachment_url(absint($bg));
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index]['bg_image_url'][$bg_index] = $bg_obj ? $bg_obj : STORELLY_PB_ASSETS_URL . 'images/placeholder.png';
                                }
                            };
                        }
                    }
                    if (isset($options['views'])) {
                        foreach ($options['views'] as $vkey => $view) {
                            $view['base'] = isset($view['base']) ? $view['base'] : 0;
                            $options['views'][$vkey]['base'] = $view['base'];
                            $view_bg_obj = wp_get_attachment_url(absint($view['base']));
                            $options['views'][$vkey]['base_url'] = $view_bg_obj ? $view_bg_obj : STORELLY_PB_ASSETS_URL . 'images/placeholder.png';
                        }
                    }
                    $product        = wc_get_product($product_id);
                    $type           = $product->get_type();
                    $variations     = array();
                    $dimensions     = array();
                    $form_values    = array();
                    $cart_item_key  = '';
                    $quantity       = 1;
                    $nbau           = '';
                    $nbdpb_enable   = get_post_meta($product_id, '_storelly_pb_enable', true);

                    if (isset($_POST['pcpb-field'])) {
                        $form_values = sanitize_text_field($_POST['pcpb-field']);
                    } else if (isset($_GET['pcpb_cart_item_key']) && sanitize_text_field($_GET['pcpb_cart_item_key']) != '') {
                        $cart_item_key  = sanitize_text_field($_GET['pcpb_cart_item_key']);
                        $cart_item      = WC()->cart->get_cart_item($cart_item_key);
                        if (isset($cart_item['pcpb_meta'])) {
                            $form_values = $cart_item['pcpb_meta']['field'];
                        }
                    }

                    if (isset($_GET['nbo_values'])) {
                        $params     = array();
                        $value_str  = base64_decode(wc_clean($_GET['nbo_values']));
                        parse_str($value_str, $params);
                        if (isset($params['pcpb-field'])) {
                            $form_values = $params['pcpb-field'];
                        }
                        if (isset($params["qty"])) {
                            $quantity = $params["qty"];
                        }
                    }

                    if ($type == 'variable') {
                        $all = get_posts(array(
                            'post_parent' => $product_id,
                            'post_type'   => 'product_variation',
                            'orderby'     => array('menu_order' => 'ASC', 'ID' => 'ASC'),
                            'post_status' => 'publish',
                            'numberposts' => -1,
                        ));
                        foreach ($all as $child) {
                            $vid                = $child->ID;
                            $variation          = wc_get_product($vid);
                            $variations[$vid]   = $variation->get_price('edit');

                            $width = $height = '';
                            $dimensions[$vid]   = array(
                                'width'     => $variation->get_width(),
                                'height'    => $variation->get_length()
                            );
                        }
                    }
                    $nbds_frontend = array(
                        'wc_currency_format_num_decimals'               =>  wc_get_price_decimals(),
                        'currency_format_num_decimals'                  =>  4,
                        'currency_format_symbol'                        =>  html_entity_decode((string) get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8'),
                        'currency_format_decimal_sep'                   =>  stripslashes(wc_get_price_decimal_separator()),
                        'currency_format_thousand_sep'                  =>  stripslashes(wc_get_price_thousand_separator()),
                        'currency_format'                               =>  esc_attr(str_replace(array('%1$s', '%2$s'), array('%s', '%v'), get_woocommerce_price_format())),
                        'nbstorelly_hide_add_cart_until_form_filled'    =>  'yes'
                    );
                    wp_register_script('option_builder', STORELLY_PB_JS_URL . 'option-builder.js',('pc-angularjs'), '1.0.0', true);
                    wp_localize_script( 'option_builder', 'option_builder_variable', array(
                        'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
                        'appid'                 => $this->appid,
                        'nbds_frontend'         => $nbds_frontend,
                        'options'               => $options,
                        'product_id'            => $product_id,
                        'type'                  => $type,
                        'quantity'              => $quantity,
                        'variations'            => wp_json_encode((array) $variations),
                        'form_values'           => $form_values,
                        'is_sold_individually'  => $product->is_sold_individually(),
                        'file_too_big'          => __('Sorry, file is too big, max size: ', 'pc-product-builder'),
                        'file_too_small'        => __('Sorry, file is too small, min size: ', 'pc-product-builder'),
                        'file_type'             => __('Sorry, this file type is not permitted for security reasons. Only accept: ', 'pc-product-builder'),
                    ));
                    wp_enqueue_script('option_builder');
                }
            }
        }
        public function show_option_fields() {
            global $product;
            $product_id = $product->get_id();
            $option_id = $this->get_product_option($product_id);
            if ($option_id) {
                $_options = $this->get_option($option_id);
                if ($_options) {
                    $options = unserialize($_options['fields']);
                    if (!isset($options['fields'])) {
                        $options['fields'] = array();
                    }
                    $options['fields'] = $this->recursive_stripslashes($options['fields']);
                    foreach ($options['fields'] as $key => $field) {
                        if (!isset($field['general']['attributes'])) {
                            $field['general']['attributes'] = array();
                            $field['general']['attributes']['options'] = array();
                            $options['fields'][$key]['general']['attributes'] = array();
                            $options['fields'][$key]['general']['attributes']['options'] = array();
                        }
                        if ($field['appearance']['change_image_product'] == 'y') {
                            foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                                $option['product_image'] = isset($option['product_image']) ? $option['product_image'] : 0;
                                $attachment_id = absint($option['product_image']);
                                if ($attachment_id != 0) {
                                    $image_link         = wp_get_attachment_url($attachment_id);
                                    $attachment_object  = get_post($attachment_id);
                                    $full_src           = wp_get_attachment_image_src($attachment_id, 'large');
                                    $image_title        = get_the_title($attachment_id);
                                    $image_alt          = trim(strip_tags(get_post_meta($attachment_id, '_wp_attachment_image_alt', TRUE)));
                                    $image_srcset       = function_exists('wp_get_attachment_image_srcset') ? wp_get_attachment_image_srcset($attachment_id, 'shop_single') : FALSE;
                                    $image_sizes        = function_exists('wp_get_attachment_image_sizes') ? wp_get_attachment_image_sizes($attachment_id, 'shop_single') : FALSE;
                                    $image_caption      = $attachment_object->post_excerpt;
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index] = array_replace_recursive($options['fields'][$key]['general']['attributes']['options'][$op_index], array(
                                        'imagep'        => 'y',
                                        'image_link'    => $image_link,
                                        'image_title'   => $image_title,
                                        'image_alt'     => $image_alt,
                                        'image_srcset'  => $image_srcset,
                                        'image_sizes'   => $image_sizes,
                                        'image_caption' => $image_caption,
                                        'full_src'      => $full_src[0],
                                        'full_src_w'    => $full_src[1],
                                        'full_src_h'    => $full_src[2]
                                    ));
                                } else {
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index]['imagep'] = 'n';
                                }
                            }
                        }
                        if (isset($field['nbpb_type']) && $field['nbpb_type'] == 'nbpb_com') {
                            if (isset($field['general']['pb_config'])) {
                                foreach ($field['general']['pb_config'] as $a_index => $attr) {
                                    foreach ($attr as $s_index => $sattr) {
                                        foreach ($sattr['views'] as $v_index => $view) {
                                            $pb_image_obj = wp_get_attachment_url(absint($view['image']));
                                            $options['fields'][$key]['general']['pb_config'][$a_index][$s_index]['views'][$v_index]['image_url'] =  $pb_image_obj ? $pb_image_obj : STORELLY_PB_ASSETS_URL . 'images/placeholder.png';
                                        }
                                    }
                                }
                            } else {
                                $field['general']['pb_config'] = array();
                            }
                            foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                                if( isset($option['enable_subattr']) && $option['enable_subattr'] == 'on' && isset($option['sub_attributes']) && count($option['sub_attributes']) > 0 ){
                                    foreach( $option['sub_attributes'] as $sa_index => $sattr ){
                                        $options['fields'][$key]['general']['attributes']['options'][$op_index]['sub_attributes'][$sa_index]['image_url'] = Storelly_PB_Util::storelly_get_image_thumbnail( $sattr['image'] );
                                    }
                                }else{
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index]['image_url'] = Storelly_PB_Util::storelly_get_image_thumbnail( $option['image'] );
                                }
                            };
                            $options['fields'][$key]['general']['component_icon_url'] = Storelly_PB_Util::storelly_get_image_thumbnail($field['general']['component_icon']);
                        }
                        if (isset($field['general']['attributes']['bg_type']) && $field['general']['attributes']['bg_type'] == 'i') {
                            foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                                foreach ($option['bg_image'] as $bg_index => $bg) {
                                    $bg_obj = wp_get_attachment_url(absint($bg));
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index]['bg_image_url'][$bg_index] = $bg_obj ? $bg_obj : STORELLY_PB_ASSETS_URL . 'images/placeholder.png';
                                }
                            };
                        }
                    }
                    if (isset($options['views'])) {
                        foreach ($options['views'] as $vkey => $view) {
                            $view['base'] = isset($view['base']) ? $view['base'] : 0;
                            $options['views'][$vkey]['base'] = $view['base'];
                            $view_bg_obj = wp_get_attachment_url(absint($view['base']));
                            $options['views'][$vkey]['base_url'] = $view_bg_obj ? $view_bg_obj : STORELLY_PB_ASSETS_URL . 'images/placeholder.png';
                        }
                    }
                    $product        = wc_get_product($product_id);
                    $type           = $product->get_type();
                    $variations     = array();
                    $dimensions     = array();
                    $form_values    = array();
                    $cart_item_key  = '';
                    $quantity       = 1;
                    $nbau           = '';
                    $nbdpb_enable   = get_post_meta($product_id, '_storelly_pb_enable', true);

                    if (isset($_POST['pcpb-field'])) {
                        $form_values = sanitize_text_field($_POST['pcpb-field']);
                    } else if (isset($_GET['pcpb_cart_item_key']) && sanitize_text_field($_GET['pcpb_cart_item_key']) != '') {
                        $cart_item_key  = sanitize_text_field($_GET['pcpb_cart_item_key']);
                        $cart_item      = WC()->cart->get_cart_item($cart_item_key);
                        if (isset($cart_item['pcpb_meta'])) {
                            $form_values = $cart_item['pcpb_meta']['field'];
                        }
                    }

                    if (isset($_GET['nbo_values'])) {
                        $params     = array();
                        $value_str  = base64_decode(wc_clean($_GET['nbo_values']));
                        parse_str($value_str, $params);
                        if (isset($params['pcpb-field'])) {
                            $form_values = $params['pcpb-field'];
                        }
                        if (isset($params["qty"])) {
                            $quantity = $params["qty"];
                        }
                    }

                    if ($type == 'variable') {
                        $all = get_posts(array(
                            'post_parent' => $product_id,
                            'post_type'   => 'product_variation',
                            'orderby'     => array('menu_order' => 'ASC', 'ID' => 'ASC'),
                            'post_status' => 'publish',
                            'numberposts' => -1,
                        ));
                        foreach ($all as $child) {
                            $vid                = $child->ID;
                            $variation          = wc_get_product($vid);
                            $variations[$vid]   = $variation->get_price('edit');

                            $width = $height = '';
                            $dimensions[$vid]   = array(
                                'width'     => $variation->get_width(),
                                'height'    => $variation->get_length()
                            );
                        }
                    }
                    ob_start();
                    Storelly_PB_Util::storelly_get_template('single-product/option-builder.php', array(
                        'product_id'            => $product_id,
                        'appid'                 => $this->appid,
                        'options'               => $options,
                        'type'                  => $type,
                        'quantity'              => $quantity,
                        'nbdpb_enable'          => $nbdpb_enable,
                        'price'                 => $product->get_price('edit'),
                        'is_sold_individually'  => $product->is_sold_individually(),
                        'variations'            => wp_json_encode((array) $variations),
                        'dimensions'            => wp_json_encode((array) $dimensions),
                        'form_values'           => $form_values,
                        'cart_item_key'         => $cart_item_key,
                        'nbau'                  => $nbau,
                    ));
                    $options_form = ob_get_clean();
                    echo wp_kses_post( $options_form );
                }
            }
        }
        public function add_cart_item_data($cart_item_data, $product_id, $variation_id, $quantity = 1) {
            $post_data = map_deep( $_POST, 'sanitize_text_field' );
            $option_id = $this->get_product_option($product_id);
            if (!$option_id) {
                return $cart_item_data;
            }
            if (isset($post_data['pcpb-field']) || isset($post_data['pcpb-add-to-cart'])) {
                $options        = $this->get_option($option_id);
                $nbd_field      = isset($post_data['pcpb-field']) ? $post_data['pcpb-field'] : array();
                if (isset($cart_item_data['pcpb-field'])) {
                    /* Bulk variation */
                    $nbd_field = $cart_item_data['pcpb-field'];
                    unset($cart_item_data['pcpb-field']);
                } else { 
                    if (!empty($_FILES) && isset($_FILES["pcpb-field"])) { 
                        $files = sanitize_text_field( $_FILES["pcpb-field"] );
                        foreach ($files['name'] as $field_id => $file) {
                            if (!isset($nbd_field[$field_id])) {
                                $nbd_upload_field = $this->upload_file(sanitize_text_field($_FILES["pcpb-field"]), $field_id);
                                if (!empty($nbd_upload_field)) {
                                    $nbd_field[$field_id] = $nbd_upload_field[$field_id];
                                }
                            }
                        }
                    }
                }
                $product        = $variation_id ? wc_get_product($variation_id) : wc_get_product($product_id);
                $original_price = (float)$product->get_price('edit');
                $option_price   = $this->option_processing($options, $original_price, $nbd_field, $quantity, null, $product);
                if (isset($post_data['prcpb-folder'])) {
                    $cart_item_data['pcpb_meta']['pcpb'] = $post_data['prcpb-folder'];
                    $path   = STORELLY_PB_CUSTOMER_DIR . '/' . $post_data['prcpb-folder'] . '/preview';
                    $images = Storelly_IO::get_list_images($path, 1);
                    if (count($images)) {
                        ksort($images);
                        $option_price['cart_image'] = Storelly_IO::convert_path_to_url(end($images));
                    }
                }
                $options['fields']                              = base64_encode($options['fields']);
                $cart_item_data['pcpb_meta']['option_price']     = $option_price;
                $cart_item_data['pcpb_meta']['field']            = $nbd_field;
                $cart_item_data['pcpb_meta']['options']          = $options;
                $cart_item_data['pcpb_meta']['original_price']   = $original_price;
                $cart_item_data['pcpb_meta']['price']            = $original_price + $option_price['total_price'] - $option_price['discount_price'];
            }
            return $cart_item_data;
        }

        public function custom_upload_directory_no_date(){
            $user_folder = md5($woocommerce->session->get_customer_id());
            $upload['subdir'] = '/storelly-product-builder/uploads/' . $user_folder;
            $upload['path'] = $upload['basedir'] . $upload['subdir'];
            $upload['url'] = $upload['baseurl'] . $upload['subdir'];
            return $upload;
        }

        public function upload_file($files, $field_id) {
            $nbd_upload_fields = array();
            global $woocommerce;
            $user_folder = md5($woocommerce->session->get_customer_id());
            $file = $files['name'][$field_id];
            if ($files['error'][$field_id] == 0) {
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $new_name = strtotime("now") . substr(md5(rand(1111, 9999)), 0, 8) . '.' . $ext;
                $new_path = STORELLY_PB_UPLOAD_DIR . '/' . $user_folder . '/' . $new_name;
                $mkpath = wp_mkdir_p(STORELLY_PB_UPLOAD_DIR . '/' . $user_folder);

                if ($mkpath) {
                    add_filter( 'upload_dir',array($this,'custom_upload_directory_no_date' ));
                    $file = array(
                        'name' => $new_name,
                        'type' => $files['type'][$field_id],
                        'tmp_name' => $files['tmp_name'][ $field_id ],
                        'error' => $files['error'][ $field_id ],
                        'size' => $files['size'][ $field_id ]
                    );
                    $upload_overrides = array( 'test_form' => false );
                    $movefile = wp_handle_upload( $file, $upload_overrides );
                    remove_filter( 'upload_dir',array($this,'custom_upload_directory_no_date' ));
                    if ($movefile && !isset( $movefile['error'] )) {
                        $nbd_upload_fields[$field_id] = $user_folder . '/' . $new_name;
                    } else {
                        //todo
                    }
                }
            }
            return $nbd_upload_fields;
        }
        public function get_field_by_id($option_fields, $field_id) {
            foreach ($option_fields['fields'] as $key => $field) {
                if ($field['id'] == $field_id) return $field;
            }
        }
        public function option_processing($options, $original_price, $fields, $quantity, $cart_item_key = null, $product = null) {
            if (Storelly_PB_Util::is_base64_string($options['fields'])) {
                $options['fields'] = base64_decode($options['fields']);
            }
            $option_fields  = maybe_unserialize($options['fields']);
            $option_fields  = $this->recursive_stripslashes($option_fields);
            $xfactor        = 1;
            $total_price    = 0;
            $discount_price = 0;
            $_fields        = array();
            $cart_image     = '';
            $cart_item_fee  = 0;
            $line_price     = array(
                'fixed'     => 0,
                'percent'   => 0,
                'xfactor'   => 1
            );
            foreach ($fields as $key => $val) {
                $origin_field = $this->get_field_by_id($option_fields, $key);
                $published    = isset($origin_field['general']['published']) ? $origin_field['general']['published'] : 'y';
                $_fields[$key] = array(
                    'name'          => $origin_field['general']['title'],
                    'val'           => $val,
                    'value_name'    => $val,
                    'published'     => $published
                );
                if ($origin_field['general']['data_type'] == 'i') {
                    $factor = isset($origin_field['general']['price']) ? $origin_field['general']['price'] : 0;
                    if ($origin_field['general']['input_type'] == 'u') {
                        $file_name = explode('/', $val);
                        $_fields[$key]['value_name']    = $file_name[count($file_name) - 1];
                        $_fields[$key]['is_upload']     = 1;
                    }
                } else {
                    $select_val = is_array($val) ? (isset($val['value']) ? $val['value'] : $val[0]) : $val;
                    $option = $origin_field['general']['attributes']['options'][$select_val];
                    $has_subattr = false;
                    if (isset($option['enable_subattr']) && $option['enable_subattr'] == 'on' && isset($option['sub_attributes']) && count($option['sub_attributes']) > 0) {
                        $has_subattr = true;
                    }
                    if (!$has_subattr && isset($val['sub_value'])) {
                        unset($val['sub_value']);
                    }
                    $_fields[$key]['value_name'] = $option['name'];
                    if (is_array($val)) {
                        if ($has_subattr) {
                            $_fields[$key]['value_name'] .= ' - ' . $option['sub_attributes'][$val['sub_value']]['name'];
                        } else {
                            $_fields[$key]['value_name'] = '';
                            foreach ($val as $k => $v) {
                                $_fields[$key]['value_name'] .= ($k == 0 ? '' : ', ') . $origin_field['general']['attributes']['options'][$v]['name'];
                            }
                        }
                    }
                    $factor = floatval($option['price'][0]);

                    if ($has_subattr && isset($val['sub_value'])) {
                        $soption = $option['sub_attributes'][$val['sub_value']];
                        $factor += floatval($soption['price'][0]);
                    }
                    if ($origin_field['appearance']['change_image_product'] == 'y' && isset($option['product_image']) && $option['product_image'] > 0) {
                        $image = wp_get_attachment_image_src($option['product_image'], 'thumbnail');
                        if ($image) {
                            $cart_image = $image[0];
                        } else {
                            $cart_image = wp_get_attachment_url($option['product_image']);
                        }
                    }
                }
                $_fields[$key]['is_pp'] = 0;
                $factor = floatval($factor);
                $_factor = $factor;
                switch ($origin_field['general']['price_type']) {
                    case 'f':
                        $_fields[$key]['price'] = $_factor;
                        $total_price += $factor;
                    case 'p':
                        $_fields[$key]['price'] = $original_price * $_factor / 100;
                        $total_price += $original_price * $factor / 100;
                        break;
                    case 'p+':
                        $_fields[$key]['price'] = $factor / 100;
                        $_fields[$key]['_price'] = $_factor / 100;
                        $_fields[$key]['is_pp'] = 1;
                        $xfactor *= (1 + $factor / 100);
                        break;
                    case 'c':
                        $current_val = absint($val);
                        $current_val = $current_val > 0 ? $current_val : 0;
                        $_fields[$key]['price'] = $_factor * $current_val;
                        $total_price += $factor * $current_val;
                        break;
                    case 'cp':
                        $_fields[$key]['price'] = $_factor * absint(strlen($val));
                        $total_price += $factor * absint(strlen($val));
                        break;
                }
            }
            $total_price += (($original_price + $total_price) * ($xfactor - 1));
            foreach ($_fields as $key => $val) {
                if ($_fields[$key]['is_pp'] == 1) {
                    $_fields[$key]['price'] = $_fields[$key]['price'] * ($original_price + $total_price) / ($_fields[$key]['price'] + 1);
                }
            }
            $total_cart_price = ($original_price + $total_price) * $quantity;
            $cart_item_fee = array(
                'value'   => 0,
                'name'    => '',
                'id'      => '',
                'fields'  => array()
            );
            if ($line_price['fixed'] != 0 || $line_price['xfactor'] != 1 || $line_price['percent'] != 0) {
                $_total_cart_price = $total_cart_price;
                if ($line_price['fixed'] != 0) {
                    $total_cart_price += $line_price['fixed'];
                }
                if ($line_price['percent'] != 0) {
                    $total_cart_price += ($original_price * $line_price['percent'] / 100);
                }
                if ($line_price['xfactor'] != 1) {
                    $total_cart_price += ($total_cart_price * ($line_price['xfactor'] - 1));
                    foreach ($_fields as $key => $val) {
                        if ($val['is_pp'] == 1 && isset($val['ind_qty']) && $val['ind_qty'] == 1) {
                            $_fields[$key]['price'] = $val['_price'] * $total_cart_price / ($val['_price'] + 1);
                        }
                    }
                }
                foreach ($_fields as $key => $val) {
                    if (isset($val['ind_qty']) && $val['ind_qty'] == 1) {
                        $cart_item_fee['name'] .= $val['name'] . ', ';
                        $cart_item_fee['fields'][] = array(
                            'name'  => $val['name'] . ': ' . $val['value_name'],
                            'price' => $val['price']
                        );
                    }
                }
                if ($cart_item_fee['name'] != '') {
                    $cart_item_fee['name'] = substr($cart_item_fee['name'], 0, strlen($cart_item_fee['name']) - 2);
                }
                $cart_item_fee['value'] = $total_cart_price - $_total_cart_price;
            }
            return array(
                'total_price'       => $total_price,
                'cart_item_fee'     => $cart_item_fee,
                'discount_price'    => $discount_price,
                'fields'            => $_fields,
                'cart_image'        => $cart_image,
                'original_price'    => $original_price
            );
        }
        public function add_to_cart_text($var) {
            if ($this->is_edit_mode) {
                return esc_attr__('Update cart', 'pc-product-builder');
            }
            return $var;
        }
        public function remove_previous_product_from_cart($passed, $product_id, $qty, $variation_id = '', $variations = array(), $cart_item_data = array()) {
            if ($this->cart_edit_key) {
                $cart_item_key = $this->cart_edit_key;
                if (isset($this->new_add_to_cart_key)) {
                    if ($this->new_add_to_cart_key == $cart_item_key && isset($_POST['quantity'])) {
                        WC()->cart->set_quantity($this->new_add_to_cart_key, wc_clean($_POST['quantity']), TRUE);
                    } else {
                        $nbd_session = WC()->session->get($cart_item_key . '_nbd');
                        if ($nbd_session) {
                            WC()->session->set('pcpb_session_removed', $nbd_session);
                            WC()->session->__unset($cart_item_key . '_nbd');

                            if (isset(WC()->cart->cart_contents[$cart_item_key]['nbd_design_id'])) {
                                $design_id = WC()->cart->cart_contents[$cart_item_key]['nbd_design_id'];
                                WC()->session->set('pcpb_session_design_id_removed', $design_id);
                            }
                        }
                        WC()->cart->remove_cart_item($cart_item_key);
                        unset(WC()->cart->removed_cart_contents[$cart_item_key]);
                    }
                }
            }
            return $passed;
        }
        public function add_to_cart($cart_item_key = "", $product_id = "", $quantity = "", $variation_id = "", $variation = "", $cart_item_data = "") {
            if ($this->cart_edit_key) {
                $this->new_add_to_cart_key = $cart_item_key;
                $nbd_session = WC()->session->get('pcpb_session_removed');
                if ($nbd_session) {
                    WC()->session->set($cart_item_key . '_nbd', $nbd_session);
                    if (!isset(WC()->cart->cart_contents[$cart_item_key]['nbd_item_meta_ds'])) WC()->cart->cart_contents[$cart_item_key]['nbd_item_meta_ds'] = array();
                    WC()->cart->cart_contents[$cart_item_key]['nbd_item_meta_ds']['nbd'] = $nbd_session;
                    WC()->session->__unset('pcpb_session_removed');

                    $design_id = WC()->session->get('pcpb_session_design_id_removed');
                    if ($design_id) {
                        WC()->cart->cart_contents[$cart_item_key]['nbd_design_id'] = $design_id;
                        WC()->session->__unset('pcpb_session_design_id_removed');
                    }
                }
            } else {
                if (is_array($cart_item_data) && isset($cart_item_data['pcpb_meta'])) {
                    $cart_contents = WC()->cart->cart_contents;
                    if (
                        is_array($cart_contents) &&
                        isset($cart_contents[$cart_item_key]) &&
                        !empty($cart_contents[$cart_item_key]) &&
                        !isset($cart_contents[$cart_item_key]['pcpb_cart_item_key'])
                    ) {
                        WC()->cart->cart_contents[$cart_item_key]['pcpb_cart_item_key'] = $cart_item_key;
                    }
                }
            }
        }
        public function quantity_input_args($args = "", $product = "") {
            if ($this->cart_edit_key) {
                $cart_item_key = $this->cart_edit_key;
                $cart_item = WC()->cart->get_cart_item($cart_item_key);
                if (isset($cart_item["quantity"])) {
                    $args["input_value"] = $cart_item["quantity"];
                }
            }
            return $args;
        }
        public function re_calculate_price($cart) {
            foreach ($cart->cart_contents as $cart_item_key => $cart_item) {
                if (isset($cart_item['pcpb_meta'])) {
                    $variation_id   = $cart_item['variation_id'];
                    $product_id     = $cart_item['product_id'];
                    $product        = $variation_id ? wc_get_product($variation_id) : wc_get_product($product_id);
                    $options        = $cart_item['pcpb_meta']['options'];
                    $fields         = $cart_item['pcpb_meta']['field'];
                    $original_price = (float)$product->get_price('edit');
                    $quantity       = $cart_item['quantity'];
                    $option_price   = $this->option_processing($options, $original_price, $fields, $quantity, $cart_item_key, $product);
                    if (isset($cart_item['pcpb_meta']['pcpb'])) {
                        $path   = STORELLY_PB_CUSTOMER_DIR . '/' . $cart_item['pcpb_meta']['pcpb'] . '/preview';
                        $images = Storelly_IO::get_list_images($path, 1);
                        if (count($images)) {
                            ksort($images);
                            $option_price['cart_image'] = Storelly_IO::convert_path_to_url(end($images));
                        }
                    }
                    $adjusted_price = $original_price + $option_price['total_price'] - $option_price['discount_price'];
                    $adjusted_price = $adjusted_price > 0 ? $adjusted_price : 0;
                    WC()->cart->cart_contents[$cart_item_key]['pcpb_meta']['option_price'] = $option_price;
                    $adjusted_price = apply_filters('storelly_adjusted_price', $adjusted_price, $cart_item);
                    WC()->cart->cart_contents[$cart_item_key]['pcpb_meta']['price'] = $adjusted_price;
                    $needed_change  = apply_filters('storelly_need_change_cart_item_price', true, WC()->cart->cart_contents[$cart_item_key]);
                    if ($needed_change) WC()->cart->cart_contents[$cart_item_key]['data']->set_price($adjusted_price);
                }
            }
        }
        public function order_line_item($item, $cart_item_key, $values) {
            if (isset($values['pcpb_meta'])) {
                $num_decimals = absint(wc_get_price_decimals());
                foreach ($values['pcpb_meta']['option_price']['fields'] as $field) {
                    if (!isset($field['published']) || $field['published'] == 'y') {
                        $price = floatval($field['price']) >= 0 ? '+' . wc_price($field['price'], array('decimals' => $num_decimals)) : wc_price($field['price'], array('decimals' => $num_decimals));
                        if (isset($field['is_upload'])) {
                            if (strpos($field['val'], 'http') !== false) {
                                $file_url = $field['val'];
                            } else {
                                $file_url = Storelly_IO::convert_path_to_url(STORELLY_PB_UPLOAD_DIR . '/' . $field['val']);
                            }
                            $field['value_name'] = '<a href="' . $file_url . '">' . $field['value_name'] . '</a>';
                        }
                        $post_fix = '';
                        if (isset($field['ind_qty'])) {
                            $post_fix = '<small>' . esc_html__('( cart fee )', 'pc-product-builder') . '</small>';
                        }
                        if (isset($field['fixed_amount'])) {
                            $post_fix = '<small>' . esc_html__('( for all items )', 'pc-product-builder') . '</small>';
                        }
                        $display_price = $price . $post_fix;
                        $item->add_meta_data($field['name'], $field['value_name'] . '&nbsp;&nbsp;' . $display_price);
                    }
                }
                if (floatval($values['pcpb_meta']['option_price']['discount_price']) > 0) {
                    $item->add_meta_data(esc_html__('Quantity Discount', 'pc-product-builder'), '-' . wc_price($values['pcpb_meta']['option_price']['discount_price'], array('decimals' => $num_decimals)));
                }
                $item->add_meta_data('_pcpb_option_price', $values['pcpb_meta']['option_price']);
                $item->add_meta_data('_pcpb_field', $values['pcpb_meta']['field']);
                $item->add_meta_data('_pcpb_folder', $values['pcpb_meta']['pcpb']);
                $item->add_meta_data('_pcpb_options', wp_slash($values['pcpb_meta']['options']));
                $item->add_meta_data('_pcpb_original_price', $values['pcpb_meta']['original_price']);
            }
        }
        public function add_cart_fee($cart_object) {
            if (is_array($cart_object->cart_contents)) {
                foreach ($cart_object->cart_contents as $key => $value) {
                    if (isset($value['pcpb_meta']) && isset($value['pcpb_meta']['option_price']['cart_item_fee']) && $value['pcpb_meta']['option_price']['cart_item_fee']['value'] != 0) {
                        $fees       = array();
                        if (is_object($cart_object) && is_callable(array($cart_object, "get_fees"))) {
                            $fees = $cart_object->get_fees();
                        } else {
                            $fees = $cart_object->fees;
                        }
                        foreach ($value['pcpb_meta']['option_price']['cart_item_fee']['fields'] as $field) {
                            $fee_name       = $field['name'] . ' - ' . strtoupper(substr($key, 0, 7));
                            $product        = $value["data"];
                            $tax_class      = $product->get_tax_class();
                            $tax_status     = $product->get_tax_status();
                            if (get_option('woocommerce_calc_taxes') == "yes" && $tax_status == "taxable") {
                                $tax = TRUE;
                            } else {
                                $tax = FALSE;
                            }
                            $fee_price  = $field['price'];
                            $can_add    = TRUE;
                            if (is_array($fees)) {
                                foreach ($fees as $fee) {
                                    if ($fee->id == sanitize_title($fee_name)) {
                                        $fee->amount = (float) $fee_price;
                                        $can_add     = FALSE;
                                        break;
                                    }
                                }
                            }
                            if ($can_add) {
                                $cart_object->add_fee($fee_name, $fee_price, $tax, $tax_status);
                            }
                        }
                    }
                }
            }
        }
        public function get_item_data($item_data, $cart_item) {
            if (isset($cart_item['pcpb_meta'])) {
                $num_decimals = absint(wc_get_price_decimals());
                foreach ($cart_item['pcpb_meta']['option_price']['fields'] as $field) {
                    if (!isset($field['published']) || $field['published'] == 'y') {
                        $price = floatval($field['price']) >= 0 ? '+' . wc_price($field['price'], array('decimals' =>  $num_decimals)) : wc_price($field['price'], array('decimals' => $num_decimals));
                        if (round($field['price'], $num_decimals) == 0) {
                            $price = '';
                        }
                        if (isset($field['is_upload'])) {
                            if (strpos($field['val'], 'http') !== false) {
                                $file_url = $field['val'];
                            } else {
                                $file_url = Storelly_IO::convert_path_to_url(STORELLY_PB_UPLOAD_DIR . '/' . $field['val']);
                            }
                            $field['value_name'] = '<a href="' . $file_url . '">' . $field['value_name'] . '</a>';
                        }
                        $post_fix = '';
                        if (isset($field['ind_qty'])) {
                            $post_fix = '<small>' . esc_html__('( cart fee )', 'pc-product-builder') . '</small>';
                        }
                        if (isset($field['fixed_amount'])) {
                            $post_fix = '<small>' . esc_html__('( for all items )', 'pc-product-builder') . '</small>';
                        }
                        $item_data[] = array(
                            'name'      => $field['name'],
                            'display'   => $field['value_name'] . '&nbsp;&nbsp;' . $price . $post_fix,
                            'hidden'    => false
                        );
                    }
                }
                if (floatval($cart_item['pcpb_meta']['option_price']['discount_price']) > 0) {
                    $item_data[] = array(
                        'name'      => esc_html__('Quantity Discount', 'pc-product-builder'),
                        'display'   => '-' . wc_price($cart_item['pcpb_meta']['option_price']['discount_price'], array('decimals' => $num_decimals)),
                        'hidden'    => false
                    );
                }
            }
            return $item_data;
        }
        public function order_again_cart_item_data($arr, $item, $order) {
            remove_filter('woocommerce_add_to_cart_validation', array($this, 'add_to_cart_validation'), 1, 6);
            $order_items = $order->get_items();
            foreach ($order_items as $order_item_id => $order_item) {
                if ($item->get_id() == $order_item_id) {
                    if ($option_price = wc_get_order_item_meta($order_item_id, '_pcpb_option_price')) {
                        $arr['pcpb_meta']['option_price'] = $option_price;
                    }
                    if ($field = wc_get_order_item_meta($order_item_id, '_pcpb_field')) {
                        $arr['pcpb_meta']['field'] = $field;
                    }
                    if ($options = wc_get_order_item_meta($order_item_id, '_pcpb_options')) {
                        $arr['pcpb_meta']['options'] = $options;
                    }
                    if ($original_price = wc_get_order_item_meta($order_item_id, '_pcpb_original_price')) {
                        $arr['pcpb_meta']['original_price'] = $original_price;
                        $arr['pcpb_meta']['price'] = $this->format_price($original_price + $option_price['total_price'] - $option_price['discount_price']);
                    }
                }
            }
            return $arr;
        }
        public function format_price($price) {
            $num_decimals = absint(wc_get_price_decimals());
            $price = round($price, $num_decimals);
            return $price;
        }
        public function cart_item_thumbnail($image = "", $cart_item = array()) {
            if (isset($cart_item['pcpb_meta']) && $cart_item['pcpb_meta']['option_price']['cart_image'] != '') {
                $size = 'shop_thumbnail';
                $dimensions = wc_get_image_size($size);
                $image = '<img src="' . $cart_item['pcpb_meta']['option_price']['cart_image']
                    . '" width="' . esc_attr($dimensions['width'])
                    . '" height="' . esc_attr($dimensions['height'])
                    . '" class="pcpb-thumbnail woocommerce-placeholder wp-post-image" />';
            }
            $image = apply_filters('storelly_cart_item_thumbnail', $image, $cart_item);
            return $image;
        }
        public function remove_cart_item_quantity($quantity_html, $cart_item, $cart_item_key) {
            if (isset($cart_item['pcpb_meta'])) $quantity_html = '';
            return $quantity_html;
        }
        public function cart_item_name($title = "", $cart_item = array(), $cart_item_key = "") {
            if (!(is_cart() || is_checkout())) {
                return $title;
            }
            if (!isset($cart_item['pcpb_meta'])) {
                return $title;
            }
            if (is_checkout()) {
                $title .= ' &times; <strong>' . $cart_item['quantity'] . '</strong>';
            }
            $product = $cart_item['data'];
            $link = add_query_arg(
                array(
                    'pcpb_cart_item_key' => $cart_item_key,
                    'task'              => 'edit'
                ),
                $product->get_permalink($cart_item)
            );
            $link = wp_nonce_url($link, 'nbo-edit');
            $show_edit_link = apply_filters('storelly_show_edit_option_link_in_cart', true, $cart_item);
            if ($show_edit_link) $title .= '<br /><a class="nbo-edit-option-cart" href="' . $link . '" class="nbo-cart-edit-options">' . esc_html__('Edit options', 'pc-product-builder') . '</a><br />';
            return apply_filters('storelly_cart_item_name', $title, $cart_item, $cart_item_key);
        }
        public function get_cart_item_from_session($cart_item, $values) {
            if (isset($values['pcpb_meta'])) {
                $cart_item['pcpb_meta'] = $values['pcpb_meta'];
                $cart_item = $this->set_product_prices($cart_item);
            }
            return $cart_item;
        }
        public function set_product_prices($cart_item) {
            if (isset($cart_item['pcpb_meta'])) {
                $new_price = (float) $cart_item['pcpb_meta']['price'];
                $needed_change = apply_filters('storelly_need_change_cart_item_price', true, $cart_item);
                if ($needed_change) $cart_item['data']->set_price($new_price);
            }
            return $cart_item;
        }
    }
}
$storelly_frontend_options = STORELLY_FRONTEND_OPTIONS::instance();
$storelly_frontend_options->init();
