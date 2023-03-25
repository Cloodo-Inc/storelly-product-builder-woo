<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('Printcart_PB_Util')) {
    class Printcart_PB_Util {
        public function __construct() {
            //TODO
        }
        public static function printcart_get_page_id($page) {
            $page = get_option('printcart_' . $page . '_page_id');
            return $page ? absint($page) : -1;
        }
        public static function printcartGetUrlPage($page) {
            switch ($page) {
                case 'product_builder':
                    $post = self::printcart_get_page_id('product_builder');
                    break;
                default:
                    $post = self::printcart_get_page_id($page);
                    break;
            }
            return get_post($post) ? get_page_link($post) : '#';
        }
        public static function printcart_get_max_input_var() {
            return abs(intval(ini_get('max_input_vars')));
        }
        public static function printcart_get_max_upload_default() {
            if (function_exists('wp_max_upload_size')) {
                return round(wp_max_upload_size() / 1024 / 1024);
            } else {
                return abs(intval(ini_get('post_max_size')));
            }
        }
        public static function printcart_get_image_thumbnail($id, $size = 'thumbnail') {
            if (absint($id) != 0) {
                $image = wp_get_attachment_image_src($id, $size);
                if (!$image) {
                    $image_url = wp_get_attachment_url($id);
                } else {
                    $image_url = $image[0];
                }
            } else {
                $image_url = PRINTCART_PB_ASSETS_URL . 'images/placeholder.png';
            }
            return $image_url;
        }
        public static function printcart_custom_notices($command, $mes) {
            switch ($command) {
                case 'success':
                    if (!isset($mes))
                        $mes = esc_html__('Your settings have been saved.', 'pc-product-builder');
                    $notice = '<div class="updated notice notice-success is-dismissible">
                                <p>' . $mes . '</p>
                                <button type="button" class="notice-dismiss">
                                    <span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'pc-product-builder') . '</span>
                                </button>
                            </div>';
                    break;
                case 'error':
                    if (!isset($mes))
                        $mes = esc_html__('Irks! An error has occurred.', 'pc-product-builder');
                    $notice = '<div class="notice notice-error is-dismissible">
                                <p>' . $mes . '</p>
                                <button type="button" class="notice-dismiss">
                                    <span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'pc-product-builder') . '</span>
                                </button>
                            </div>';
                    break;
                case 'notices':
                    if (!isset($mes))
                        $mes = esc_html__('Irks! An error has occurred.', 'pc-product-builder');
                    $notice = '<div class="notice notice-warning">
                                <p>' . $mes . '</p>
                            </div>';
                    break;
                case 'warning':
                    if (!isset($mes))
                        $mes = esc_html__('Warning.', 'pc-product-builder');
                    $notice = '<div class="notice notice-warning is-dismissible">
                                <p>' . $mes . '</p>
                                <button type="button" class="notice-dismiss">
                                    <span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'pc-product-builder') . '</span>
                                </button>
                            </div>';
                    break;
                default:
                    $notice = '';
            }
            return $notice;
        }
        public static function printcart_locate_template($template_name, $template_path = '', $default_path = '') {
            // Set variable to search in pc-product-builder folder of theme.
            if (!$template_path) :
                $template_path = 'pc-product-builder/';
            endif;
            // Set default plugin templates path.
            if (!$default_path) :
                $default_path = PRINTCART_PB_PLUGIN_DIR . 'templates/'; // Path to the template folder
            endif;
            // Search template file in theme folder.
            $template = locate_template(array(
                $template_path . $template_name,
                $template_name
            ));
            // Get plugins template file.
            if (!$template) :
                $template = $default_path . $template_name;
            endif;
            return apply_filters('printcart_locate_template', $template, $template_name, $template_path, $default_path);
        }
        public static function printcart_get_template($template_name, $args = array(), $tempate_path = '', $default_path = '') {
            if (is_array($args) && isset($args)) :
                extract($args);
            endif;
            $template_file = self::printcart_locate_template($template_name, $tempate_path, $default_path);
            if (!file_exists($template_file)) :
                _doing_it_wrong(__FUNCTION__, sprintf('<code>%s</code> does not exist.', $template_file), '1.3.1');
                return;
            endif;
            include $template_file;
        }
        public static function is_printcart_product_builder_page() {
            return is_page(self::printcart_get_page_id('product_builder'));
        }

        public static function is_printcart_product_builder($id) {
            $id     = self::get_wpml_original_id($id);
            $check  = get_post_meta($id, '_printcart_pb_enable', true);
            if ($check) return true;
            return false;
        }
        public static function get_wpml_original_id($id, $type = 'post', $current_lang = false) {
            if (class_exists('SitePress')) {
                global $sitepress;
                $langcode = $sitepress->get_default_language();
                if ($current_lang) {
                    $langcode = $sitepress->get_current_language();
                }
                if (function_exists('icl_object_id')) {
                    $id = icl_object_id($id, $type, true, $langcode);
                }
            }
            return $id;
        }
        public static function printcart_get_redirect_url() {
            $rd                 = wc_clean($_GET['rd']);
            switch ($rd) {
                case 'print_option':
                    $get                = array(
                        'action'    => 'edit',
                        'id'        => isset($_GET['oid']) ? absint($_GET['oid']) : '',
                        'paged'     => isset($_GET['paged']) ? absint($_GET['paged']) : '',
                    );
                    $redirect_url       = add_query_arg($get, admin_url('admin.php?page=pc_product_builder_options'));
                    break;
                default:
                    $redirect_url       = $rd;
                    break;
            }
            return apply_filters('printcart_redirect_url', $redirect_url);
        }
        public static function printcart_get_product_pre_builder($option_id, $pcpb_cart_item_key) {
            $data = array();
            if ($pcpb_cart_item_key != '') {
                $cart_item = WC()->cart->get_cart_item($pcpb_cart_item_key);
                if (isset($cart_item['pcpb_meta'])) {
                    $builder_folder = $cart_item['pcpb_meta']['pcpb'];
                    $path           = PRINTCART_PB_CUSTOMER_DIR . '/' . $builder_folder;
                    $data['config'] = self::printcart_get_data_from_json($path . '/config.json');
                    $data['design'] = self::printcart_get_data_from_json($path . '/design.json');
                }
            } else {
                global $wpdb;
                $sql = "SELECT builder FROM {$wpdb->prefix}printcart_product_builder_options WHERE id = {$option_id}";
                $options = $wpdb->get_results($sql, 'ARRAY_A');
                if (isset($options[0])) {
                    $builder_folder = $options[0]['builder'];
                    if ($builder_folder) {
                        $path = PRINTCART_PB_CUSTOMER_DIR . '/' . $builder_folder;
                        $data['config'] = self::printcart_get_data_from_json($path . '/config.json');
                        $data['design'] = self::printcart_get_data_from_json($path . '/design.json');
                    }
                }
            }
            return $data;
        }
        public static function pritcart_get_image_thumbnail($id, $size = 'thumbnail') {
            if (absint($id) != 0) {
                $image = wp_get_attachment_image_src($id, $size);
                if (!$image) {
                    $image_url = wp_get_attachment_url($id);
                } else {
                    $image_url = $image[0];
                }
            } else {
                $image_url = PRINTCART_PB_ASSETS_URL . 'images/placeholder.png';
            }
            return $image_url;
        }
        public static function printcart_get_data_from_json($path = '') {
            $content = file_exists($path) ? file_get_contents($path) : '';
            return json_decode($content);
        }
        public static function is_base64_string($s) {
            if (($b = base64_decode($s, TRUE)) === FALSE) {
                return FALSE;
            }
            $e = mb_detect_encoding($b);
            if (in_array($e, array('UTF-8', 'ASCII'))) {
                return TRUE;
            } else {
                return FALSE;
            }
        }
    }
}
