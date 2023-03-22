<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('PRINTCART_FRONTEND_OPTIONS')) {
    class PRINTCART_FRONTEND_OPTIONS {
        protected static $instance;
        public $is_edit_mode = FALSE;
        /** Holds the cart key when editing a product in the cart **/
        public $cart_edit_key = NULL;
        /** Edit option in cart helper **/
        public function __construct() {
            if (isset($_REQUEST['nbo_cart_item_key']) && $_REQUEST['nbo_cart_item_key'] != '') {
                $this->is_edit_mode = true;
                $this->cart_edit_key = $_REQUEST['nbo_cart_item_key'];
            }
        }
        public static function instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        public function init() {
            add_action('woocommerce_before_add_to_cart_button', array($this, 'show_option_fields'));
        }
        public static function get_option($id) {
            global $wpdb;
            $sql = "SELECT * FROM {$wpdb->prefix}printcart_product_builder_options";
            $sql .= " WHERE id = " . esc_sql($id);
            $result = $wpdb->get_results($sql, 'ARRAY_A');
            return count($result[0]) ? $result[0] : false;
        }
        public static function get_product_option($product_id) {
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
                                            $options['fields'][$key]['general']['pb_config'][$a_index][$s_index]['views'][$v_index]['image_url'] =  $pb_image_obj ? $pb_image_obj : PRINTCART_PB_ASSETS_URL . 'images/placeholder.png';
                                        }
                                    }
                                }
                            } else {
                                $field['general']['pb_config'] = array();
                            }
                            foreach ($field['general']['attributes']['options'] as $op_index => $option) {

                                $options['fields'][$key]['general']['attributes']['options'][$op_index]['image_url'] = printcart_get_image_thumbnail($option['image']);
                            };
                            $options['fields'][$key]['general']['component_icon_url'] = printcart_get_image_thumbnail($field['general']['component_icon']);
                        }
                        if (isset($field['general']['attributes']['bg_type']) && $field['general']['attributes']['bg_type'] == 'i') {
                            foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                                foreach ($option['bg_image'] as $bg_index => $bg) {
                                    $bg_obj = wp_get_attachment_url(absint($bg));
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index]['bg_image_url'][$bg_index] = $bg_obj ? $bg_obj : PRINTCART_PB_ASSETS_URL . 'images/placeholder.png';
                                }
                            };
                        }
                    }
                    if (isset($options['views'])) {
                        foreach ($options['views'] as $vkey => $view) {
                            $view['base'] = isset($view['base']) ? $view['base'] : 0;
                            $options['views'][$vkey]['base'] = $view['base'];
                            $view_bg_obj = wp_get_attachment_url(absint($view['base']));
                            $options['views'][$vkey]['base_url'] = $view_bg_obj ? $view_bg_obj : PRINTCART_PB_ASSETS_URL . 'images/placeholder.png';
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
                    $nbdpb_enable   = get_post_meta($product_id, '_printcart_pb_enable', true);

                    if (isset($_POST['nbd-field'])) {
                        $form_values = $_POST['nbd-field'];
                    } else if (isset($_GET['nbo_cart_item_key']) && $_GET['nbo_cart_item_key'] != '') {
                        $cart_item_key  = $_GET['nbo_cart_item_key'];
                        $cart_item      = WC()->cart->get_cart_item($cart_item_key);
                        if (isset($cart_item['nbo_meta'])) {
                            $form_values = $cart_item['nbo_meta']['field'];
                        }
                    }

                    if (isset($_GET['nbo_values'])) {
                        $params     = array();
                        $value_str  = base64_decode(wc_clean($_GET['nbo_values']));
                        parse_str($value_str, $params);
                        if (isset($params['nbd-field'])) {
                            $form_values = $params['nbd-field'];
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
                    printcart_get_template('single-product/option-builder.php', array(
                        'product_id'            => $product_id,
                        'options'               => $options,
                        'type'                  => $type,
                        'quantity'              => $quantity,
                        'nbdpb_enable'          => $nbdpb_enable,
                        'price'                 => $product->get_price('edit'),
                        'is_sold_individually'  => $product->is_sold_individually(),
                        'variations'            => json_encode((array) $variations),
                        'dimensions'            => json_encode((array) $dimensions),
                        'form_values'           => $form_values,
                        'cart_item_key'         => $cart_item_key,
                        'nbau'                  => $nbau,
                    ));
                    $options_form = ob_get_clean();
                    echo $options_form;
                }
            }
        }
    }
}
$printcart_frontend_options = PRINTCART_FRONTEND_OPTIONS::instance();
$printcart_frontend_options->init();
