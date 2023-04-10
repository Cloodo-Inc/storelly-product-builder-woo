<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('Printcart_Product_Builder_Frontend')) {
    class Printcart_Product_Builder_Frontend {
        protected static $instance;
        protected $isDesign = false;
        public function __construct() {
            //TODO
        }
        public static function instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        public function init() {
            $this->frontend_enqueue_scripts();
            add_action('woocommerce_before_single_product', array(&$this, 'before_product_container'), 1);
            if (is_admin()) {
                $this->ajax();
            }
            add_action('template_redirect', array($this, 'template_redirect'));
        }
        public function ajax() {
            $ajax_events = array(
                'printcart_save_product_builder_design'   => true
            );
            foreach ($ajax_events as $ajax_event => $nopriv) {
                add_action('wp_ajax_' . $ajax_event, array($this, $ajax_event));
                if ($nopriv) {
                    add_action('wp_ajax_nopriv_' . $ajax_event, array($this, $ajax_event));
                }
            }
        }
        public function template_redirect() {
            if (Printcart_PB_Util::is_printcart_product_builder_page() && (current_user_can('editor') || current_user_can('administrator'))) {
                include(PRINTCART_PB_PLUGIN_DIR . 'views/product-builder/index.php');
                exit();
            }
        }
        public function printcart_save_product_builder_design() {
            if (!wp_verify_nonce($_POST['nonce'], 'save-design') && PRINTCART_ENABLE_NONCE) {
                die('Security error');
            }
            $result = array(
                'flag'  =>  'failure',
                'link'  =>  '',
                'folder' => ''
            );
            do_action('before_printcart_save_product_builder_design');
            $pcpb_item_pb_key = (isset($_POST['pcpb_item_pb_key']) && $_POST['pcpb_item_pb_key'] != '') ? wc_clean($_POST['pcpb_item_pb_key']) : substr(md5(uniqid()), 0, 5) . rand(1, 100) . time();
            $is_creating_task = (isset($_POST['is_creating_task']) && $_POST['is_creating_task'] != '') ? wc_clean($_POST['is_creating_task']) :  '0';
            $oid = (isset($_POST['oid']) && $_POST['oid'] != '') ? absint($_POST['oid']) :  0;
            $path = PRINTCART_PB_CUSTOMER_DIR . '/' . $pcpb_item_pb_key;
            $save_status = $this->store_product_builder_design_data($pcpb_item_pb_key, $_FILES);
            if (false != $save_status) {
                $result['image'] = $this->create_preview($path);
                asort($result['image']);
                $result['flag'] = 'success';
                $result['folder'] = $pcpb_item_pb_key;
                if ($is_creating_task == '1' && $oid != 0) {
                    global $wpdb;
                    $arr = array(
                        'builder'   =>  $pcpb_item_pb_key
                    );
                    $result_update = $wpdb->update("{$wpdb->prefix}printcart_product_builder_options", $arr, array('id' => $oid));
                }
            }
            do_action('after_printcart_save_product_builder_design', $result);
            echo json_encode($result);
            wp_die();
        }
        private function create_preview($path) {
            $config = json_decode(file_get_contents($path . '/config.json'));
            $images = array();
            if (wp_mkdir_p($path . '/preview')) {
                foreach ($config->views as $index => $view) {
                    $design_path = $path . '/frame_' . $index . '.png';
                    if (file_exists($design_path)) {
                        list($width, $height) = getimagesize($design_path);
                        $width = intval($width);
                        $height = intval($height);
                        $base_img_path = Printcart_IO::convert_url_to_path($view->base_url);
                        if (is_file($base_img_path)) {
                            $base_img_info = pathinfo($base_img_path);
                            if ($base_img_info['extension'] == "png") {
                                $base_img = PRINTCART_IMAGE::resize_imagepng($base_img_path, $width, $height);
                            } else {
                                $base_img = PRINTCART_IMAGE::resize_imagepng($base_img_path, $width, $height);
                            }
                            $design = imagecreatefrompng($design_path);
                            imagecopy($base_img, $design, 0, 0, 0, 0, $width, $height);
                            imagepng($base_img, $path . '/preview/' . $index . '.png');
                            imagedestroy($base_img);
                            imagedestroy($design);
                        } else {
                            copy($design_path, $path . '/preview/' . $index . '.png');
                        }
                        $images[] = Printcart_IO::convert_path_to_url($path . '/preview/' . $index . '.png');
                    }
                }
            };
            return $images;
        }
        private function store_product_builder_design_data($pcpb_item_pb_key, $data) {
            $path = PRINTCART_PB_CUSTOMER_DIR . '/' . $pcpb_item_pb_key;
            if (file_exists($path . '_old')) Printcart_IO::delete_folder($path . '_old');
            if (file_exists($path)) rename($path, $path . '_old');
            if (wp_mkdir_p($path)) {
                foreach ($data as $key => $val) {
                    if ($key == 'design') {
                        $full_name = $path . '/design.json';
                    } else if ($key == 'config') {
                        $full_name = $path . '/config.json';
                    } else if ($key == 'used_font') {
                        $full_name = $path . '/used_font.json';
                    } else if ($key == 'design_output') {
                        $full_name = $path . '/design_output.json';
                    } else {
                        $ext = explode('/', $val["type"])[1];
                        $full_name = $path . '/' . $key . '.' . $ext;
                    }
                    if (!move_uploaded_file($val["tmp_name"], $full_name)) {
                        return false;
                    }
                }
            } else {
                Nbdesigner_DebugTool::wirite_log('Your server not allow creat folder', 'save design');
                rename($path . '_old', $path);
                return false;
            }
            return true;
        }
        public function before_product_container() {
            $pid = get_the_ID();
            if (Printcart_PB_Util::is_printcart_product_builder($pid)) {
                add_action('nbo_after_default_options', array(&$this, 'product_builder_html'), 1);
                add_action('wp_footer', array(&$this, 'nbd_modal_product_builder'), 1);
            }
        }
        public function frontend_enqueue_scripts() {
            add_action('wp_enqueue_scripts', function () {
                $js_libs = array(
                    'fontfaceobserver' => array(
                        'link' => PRINTCART_PB_ASSETS_URL . 'libs/fontfaceobserver.js',
                        'version'   => '2.0.13',
                        'depends'  => array()
                    ),
                    'spectrum' => array(
                        'link' => PRINTCART_PB_JS_URL . 'spectrum.js',
                        'version'   => '1.8.0',
                        'depends'  => array()
                    ),
                    'fabricjs' => array(
                        'link' => PRINTCART_PB_ASSETS_URL . 'libs/fabric.2.6.0.min.js',
                        'version'   => '2.6.0',
                        'depends'  => array()
                    ),
                    'angularjs' => array(
                        'link' => PRINTCART_PB_ASSETS_URL . 'libs/angular.min.js',
                        'version'   => '1.6.9',
                        'depends'  => array('jquery')
                    ),
                    'product-builder' => array(
                        'link' => PRINTCART_PB_JS_URL . 'app-product-builder.js',
                        'version'   => PRINTCART_PB_VERSION,
                        'depends'  => array('jquery', 'underscore', 'angularjs', 'fabricjs', 'fontfaceobserver', 'spectrum')
                    )
                );
                $css_libs = array(
                    'spectrum' => array(
                        'link'  => PRINTCART_PB_ASSETS_URL . 'css/spectrum.css',
                        'version'   => '1.8.0',
                        'depends'  =>  array()
                    ),
                    'product-builder' => array(
                        'link'  => PRINTCART_PB_ASSETS_URL . 'css/app-product-builder.css',
                        'version'   => PRINTCART_PB_VERSION,
                        'depends'  =>  array('spectrum')
                    ),
                );
                foreach ($css_libs as $key => $css) {
                    $link = $css['link'];
                    wp_register_style($key, $link, $css['depends'], $css['version']);
                }
                foreach ($js_libs as $key => $js) {
                    $link = $js['link'];
                    wp_register_script($key, $link, $js['depends'], $js['version'], false);
                }
                $pid = get_the_ID();
                if (is_singular('product') && Printcart_PB_Util::is_printcart_product_builder($pid)) {
                    wp_enqueue_style('product-builder');
                    wp_enqueue_script('product-builder');
                }
            });
        }
        public function product_builder_html() {
            include(PRINTCART_PB_PLUGIN_DIR . 'views/product-builder/customize-btn.php');
        }
        public function nbd_modal_product_builder() {
            $product_id = get_the_ID();
            $option_id = get_transient('printcart_product_builder_' . $product_id);
            if (Printcart_PB_Util::is_printcart_product_builder($product_id)) {
                include(PRINTCART_PB_PLUGIN_DIR . 'views/product-builder/wrapper.php');
            }
        }
    }
}
$nbd_product_builder = Printcart_Product_Builder_Frontend::instance();
$nbd_product_builder->init();
