<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Printcart_IO {
    public function __construct() {
    }
    public static function delete_folder($path) {
        if (is_dir($path) === true) {
            $files = array_diff(scandir($path), array('.', '..'));
            foreach ($files as $file) {
                self::delete_folder(realpath($path) . '/' . $file);
            }
            return rmdir($path);
        } else if (is_file($path) === true) {
            return unlink($path);
        }
        return false;
    }
    public static function copy_dir($src, $dst) {
        if (file_exists($dst)) self::delete_folder($dst);
        if (is_dir($src)) {
            wp_mkdir_p($dst);
            $files = scandir($src);
            foreach ($files as $file) {
                if ($file != "." && $file != "..") self::copy_dir("$src/$file", "$dst/$file");
            }
        } else if (file_exists($src)) copy($src, $dst);
    }
    public static function mkdir($dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
    public static function convert_url_to_path($url) {
        $upload_dir     = wp_upload_dir();
        $basedir        = $upload_dir['basedir'];
        $arr            = explode('/', $basedir);
        $upload         = $arr[count($arr) - 1];
        if (is_multisite() && !is_main_site()) $upload = $arr[count($arr) - 3] . '/' . $arr[count($arr) - 2] . '/' . $arr[count($arr) - 1];
        $arr_url = explode('/' . $upload, $url);
        if (isset($arr_url[1])) {
            if (count($arr_url) == 2) {
                return $basedir . $arr_url[1];
            } else {
                return $basedir . $arr_url[1] . '/' . $upload . $arr_url[2];
            }
        } else {
            $path = str_replace(
                site_url(),
                wp_normalize_path(untrailingslashit(ABSPATH)),
                wp_normalize_path($url)
            );
            return $path;
        }
    }
    public static function wp_convert_path_to_url($path = '') {
        $url = str_replace(
            wp_normalize_path(untrailingslashit(ABSPATH)),
            site_url(),
            wp_normalize_path($path)
        );
        return esc_url_raw($url);
    }
}

if (!function_exists('printcart_get_page_id')) {
    function printcart_get_page_id($page) {
        $page = get_option('printcart_' . $page . '_page_id');
        return $page ? absint($page) : -1;
    }
}
function printcartGetUrlPage($page) {
    switch ($page) {
        case 'product_builder':
            $post = printcart_get_page_id('product_builder');
            break;
        default:
            $post = printcart_get_page_id($page);
            break;
    }
    return get_post($post) ? get_page_link($post) : '#';
}
function printcart_get_max_input_var() {
    return abs(intval(ini_get('max_input_vars')));
}
function printcart_get_max_upload_default() {
    if (function_exists('wp_max_upload_size')) {
        return round(wp_max_upload_size() / 1024 / 1024);
    } else {
        return abs(intval(ini_get('post_max_size')));
    }
}
function printcart_get_image_thumbnail($id, $size = 'thumbnail') {
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
function printcart_custom_notices($command, $mes) {
    switch ($command) {
        case 'success':
            if (!isset($mes))
                $mes = esc_html__('Your settings have been saved.', 'web-to-print-online-designer');
            $notice = '<div class="updated notice notice-success is-dismissible">
                            <p>' . $mes . '</p>
                            <button type="button" class="notice-dismiss">
                                <span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'web-to-print-online-designer') . '</span>
                            </button>
                        </div>';
            break;
        case 'error':
            if (!isset($mes))
                $mes = esc_html__('Irks! An error has occurred.', 'web-to-print-online-designer');
            $notice = '<div class="notice notice-error is-dismissible">
                            <p>' . $mes . '</p>
                            <button type="button" class="notice-dismiss">
                                <span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'web-to-print-online-designer') . '</span>
                            </button>
                        </div>';
            break;
        case 'notices':
            if (!isset($mes))
                $mes = esc_html__('Irks! An error has occurred.', 'web-to-print-online-designer');
            $notice = '<div class="notice notice-warning">
                            <p>' . $mes . '</p>
                        </div>';
            break;
        case 'warning':
            if (!isset($mes))
                $mes = esc_html__('Warning.', 'web-to-print-online-designer');
            $notice = '<div class="notice notice-warning is-dismissible">
                            <p>' . $mes . '</p>
                            <button type="button" class="notice-dismiss">
                                <span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'web-to-print-online-designer') . '</span>
                            </button>
                        </div>';
            break;
        default:
            $notice = '';
    }
    return $notice;
}
function printcart_locate_template($template_name, $template_path = '', $default_path = '') {
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
function printcart_get_template($template_name, $args = array(), $tempate_path = '', $default_path = '') {
    if (is_array($args) && isset($args)) :
        extract($args);
    endif;
    $template_file = printcart_locate_template($template_name, $tempate_path, $default_path);
    if (!file_exists($template_file)) :
        _doing_it_wrong(__FUNCTION__, sprintf('<code>%s</code> does not exist.', $template_file), '1.3.1');
        return;
    endif;
    include $template_file;
}
if (!function_exists('is_printcart_product_builder_page')) {
    function is_printcart_product_builder_page() {
        return is_page(printcart_get_page_id('product_builder'));
    }
}
function is_printcart_product_builder($id) {
    $id         = get_wpml_original_id($id);
    $check = get_post_meta($id, '_printcart_pb_enable', true);
    if ($check) return true;
    return false;
}
function printcart_get_redirect_url() {
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
    return apply_filters('nbd_redirect_url', $redirect_url);
}
function printcart_get_product_pre_builder($option_id, $nbo_cart_item_key) {
    $data = array();
    if ($nbo_cart_item_key != '') {
        $cart_item = WC()->cart->get_cart_item($nbo_cart_item_key);
        if (isset($cart_item['nbo_meta'])) {
            $builder_folder = $cart_item['nbo_meta']['nbdpb'];
            $path           = PRINTCART_PB_CUSTOMER_DIR . '/' . $builder_folder;
            $data['config'] = nbd_get_data_from_json($path . '/config.json');
            $data['design'] = nbd_get_data_from_json($path . '/design.json');
        }
    } else {
        global $wpdb;
        $sql = "SELECT builder FROM {$wpdb->prefix}printcart_product_builder_options WHERE id = {$option_id}";
        $options = $wpdb->get_results($sql, 'ARRAY_A');
        if (isset($options[0])) {
            $builder_folder = $options[0]['builder'];
            if ($builder_folder) {
                $path = PRINTCART_PB_CUSTOMER_DIR . '/' . $builder_folder;
                $data['config'] = nbd_get_data_from_json($path . '/config.json');
                $data['design'] = nbd_get_data_from_json($path . '/design.json');
            }
        }
    }
    return $data;
}
function pritcart_get_image_thumbnail($id, $size = 'thumbnail') {
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
