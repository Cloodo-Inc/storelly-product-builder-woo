<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('Storelly_Product_Builder_Frontend')) {
    class Storelly_Product_Builder_Frontend {
        protected static $instance;
        protected $isDesign = false;
        protected $path = '';
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
                'storelly_save_product_builder_design'   => true,
                'storelly_customer_upload'             => true,
            );
            foreach ($ajax_events as $ajax_event => $nopriv) {
                add_action('wp_ajax_' . $ajax_event, array($this, $ajax_event));
                if ($nopriv) {
                    add_action('wp_ajax_nopriv_' . $ajax_event, array($this, $ajax_event));
                }
            }
        }
        public function template_redirect() {
            if (Storelly_PB_Util::is_storelly_product_builder_page() && (current_user_can('editor') || current_user_can('administrator'))) {
                include(STORELLY_PB_PLUGIN_DIR . 'views/product-builder/index.php');
                exit();
            }
        }
        public function storelly_customer_upload() {
            if ( ! isset( $_FILES['file'] ) ) {
                echo wp_json_encode( [ 'flag' => 0, 'mes' => 'No file uploaded' ] );
                wp_die();
            }

            $file = $_FILES['file'];
            $allowed_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml' ];

            if ( ! in_array( $file['type'], $allowed_types, true ) ) {
                echo wp_json_encode( [ 'flag' => 0, 'mes' => 'Only image files are supported' ] );
                wp_die();
            }
            $upload_overrides = [
                'test_form' => false,
                'mimes'     => [
                    'jpg|jpeg' => 'image/jpeg',
                    'png'      => 'image/png',
                    'gif'      => 'image/gif',
                    'svg'      => 'image/svg+xml',
                ],
            ];

            $movefile = wp_handle_upload( $file, $upload_overrides );

            if ( $movefile && ! isset( $movefile['error'] ) ) {
                echo wp_json_encode( [
                    'flag' => 1,
                    'src'  => $movefile['url'],
                ] );
                wp_die();
            } else {
                $error = isset( $movefile['error'] ) ? $movefile['error'] : 'Failed to upload file';
                echo wp_json_encode( [ 'flag' => 0, 'mes' => $error ] );
                wp_die();
            }
        }
        public function storelly_save_product_builder_design() {
            if (!wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'save-design') && STORELLY_ENABLE_NONCE) {
                die('Security error');
            }
            $result = array(
                'flag'  =>  'failure',
                'link'  =>  '',
                'folder' => ''
            );
            do_action('storelly_before_save_product_builder_design');
            $pcpb_item_pb_key = (isset($_POST['pcpb_item_pb_key']) && sanitize_text_field($_POST['pcpb_item_pb_key'] != '')) ? wc_clean($_POST['pcpb_item_pb_key']) : substr( md5( uniqid() ), 0, 5 ) . bin2hex(random_bytes(2)) . time();
            $is_creating_task = (isset($_POST['is_creating_task']) && sanitize_text_field($_POST['is_creating_task'] != '')) ? wc_clean($_POST['is_creating_task']) :  '0';
            $oid = (isset($_POST['oid']) && absint($_POST['oid'] != '')) ? absint($_POST['oid']) :  0;
            $path = STORELLY_PB_CUSTOMER_DIR . '/' . $pcpb_item_pb_key;
            $save_status = $this->store_product_builder_design_data($pcpb_item_pb_key, map_deep( $_FILES, 'sanitize_text_field' ));
            if (false != $save_status) {
                $result['image'] = $this->create_preview($path);
                asort($result['image']);
                $result['flag'] = 'success';
                $result['folder'] = $pcpb_item_pb_key;
              $options = wp_cache_get('storelly_option_' . $oid, 'storelly');
                if ($options === false) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $options = $wpdb->get_results(
                        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}storelly_product_builder_options WHERE id = %d", $oid),
                        ARRAY_A
                    );
                    wp_cache_set('storelly_option_' . $oid, $options, 'storelly', 300);
                }
            }
            do_action('storelly_after_save_product_builder_design', $result);
            echo wp_json_encode($result);
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
                        $base_img_path = Storelly_IO::convert_url_to_path($view->base_url);
                        if (is_file($base_img_path)) {
                            $base_img_info = pathinfo($base_img_path);
                            if ($base_img_info['extension'] == "png") {
                                $base_img = STORELLY_IMAGE::resize_imagepng($base_img_path, $width, $height);
                            } else {
                                $base_img = STORELLY_IMAGE::resize_imagepng($base_img_path, $width, $height);
                            }
                            $design = imagecreatefrompng($design_path);
                            imagecopy($base_img, $design, 0, 0, 0, 0, $width, $height);
                            imagepng($base_img, $path . '/preview/' . $index . '.png');
                            imagedestroy($base_img);
                            imagedestroy($design);
                        } else {
                            copy($design_path, $path . '/preview/' . $index . '.png');
                        }
                        $images[] = Storelly_IO::convert_path_to_url($path . '/preview/' . $index . '.png');
                    }
                }
            };
            return $images;
        }
        private function store_product_builder_design_data( $pcpb_item_pb_key, $data ) {
            global $wp_filesystem;

            // Khởi tạo WP_Filesystem nếu chưa có
            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            if ( ! $wp_filesystem ) {
                WP_Filesystem();
            }

            $upload_overrides = [ 'test_form' => false ];
            $path = STORELLY_PB_CUSTOMER_DIR . '/' . $pcpb_item_pb_key;
            $this->path = $pcpb_item_pb_key;

            // Xóa folder _old nếu tồn tại
            if ( $wp_filesystem->exists( $path . '_old' ) ) {
                Storelly_IO::delete_folder( $path . '_old' );
            }

            // Đổi tên folder hiện tại sang _old
            if ( $wp_filesystem->exists( $path ) ) {
                $wp_filesystem->move( $path, $path . '_old', true ); // true = overwrite nếu cần
            }

            // Tạo folder mới
            if ( ! $wp_filesystem->is_dir( $path ) && ! $wp_filesystem->mkdir( $path ) ) {
                // Nếu tạo folder thất bại, phục hồi folder cũ
                if ( $wp_filesystem->exists( $path . '_old' ) ) {
                    $wp_filesystem->move( $path . '_old', $path, true );
                }
                return false;
            }

            foreach ( $data as $key => $val ) {
                switch ( $key ) {
                    case 'design':
                        $full_name = $path . '/design.json';
                        break;
                    case 'config':
                        $full_name = $path . '/config.json';
                        break;
                    case 'used_font':
                        $full_name = $path . '/used_font.json';
                        break;
                    case 'design_output':
                        $full_name = $path . '/design_output.json';
                        break;
                    default:
                        $ext       = explode( '/', $val['type'] )[1];
                        $full_name = $path . '/' . $key . '.' . $ext;
                        break;
                }
                $_FILES[$key] = [
                    'name'     => basename( $full_name ),
                    'type'     => $val['type'],
                    'tmp_name' => $val['tmp_name'],
                    'error'    => $val['error'],
                    'size'     => $val['size'],
                ];

                $uploaded_file = wp_handle_upload( $_FILES[$key], $upload_overrides );

                if ( isset( $uploaded_file['error'] ) ) {
                    if ( $wp_filesystem->exists( $path . '_old' ) ) {
                        $wp_filesystem->delete( $path ); // xóa folder lỗi
                        $wp_filesystem->move( $path . '_old', $path, true );
                    }
                    return false;
                }
                if ( $wp_filesystem->exists( $uploaded_file['file'] ) ) {
                    $wp_filesystem->move( $uploaded_file['file'], $full_name, true );
                }
            }
            return true;
        }

        public function before_product_container() {
            $pid = get_the_ID();
            if (Storelly_PB_Util::is_storelly_product_builder($pid)) {
                add_action('storelly_after_default_options', array(&$this, 'product_builder_html'), 1);
                add_action('wp_footer', array(&$this, 'nbd_modal_product_builder'), 1);
            }
        }
        
        public function frontend_enqueue_scripts() {
            add_action('wp_enqueue_scripts', function () {
                $js_libs = array(
                    'fontfaceobserver' => array(
                        'link' => STORELLY_PB_ASSETS_URL . 'libs/fontfaceobserver.js',
                        'version'   => '2.0.13',
                        'depends'  => array('jquery')
                    ),
                    'spectrum' => array(
                        'link' => STORELLY_PB_JS_URL . 'spectrum.js',
                        'version'   => '1.8.0',
                        'depends'  => array('jquery')
                    ),
                    'fabricjs' => array(
                        'link' => STORELLY_PB_ASSETS_URL . 'libs/fabric.2.6.0.min.js',
                        'version'   => '2.6.0',
                        'depends'  => array()
                    ),
                    'pc-angularjs' => array(
                        'link' => STORELLY_PB_ASSETS_URL . 'libs/builderproductag.min.js',
                        'version'   => '1.6.9',
                        'depends'  => array('jquery')
                    ),  
                    'product-builder' => array(
                        'link' => STORELLY_PB_JS_URL . 'app-product-builder.js',
                        'version'   => STORELLY_PB_VERSION,
                        'depends'  => array('jquery', 'underscore', 'pc-angularjs', 'fabricjs', 'fontfaceobserver', 'spectrum')
                    )
                );
                $css_libs = array(
                    'spectrum' => array(
                        'link'  => STORELLY_PB_ASSETS_URL . 'css/spectrum.css',
                        'version'   => '1.8.0',
                        'depends'  =>  array()
                    ),
                    'product-builder' => array(
                        'link'  => STORELLY_PB_ASSETS_URL . 'css/app-product-builder.css',
                        'version'   => STORELLY_PB_VERSION,
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
            });
        }
        public function product_builder_html() {
            include(STORELLY_PB_PLUGIN_DIR . 'views/product-builder/customize-btn.php');
        }
        public function nbd_modal_product_builder() {
            $product_id = get_the_ID();
            $option_id = get_transient('storelly_product_builder_' . $product_id);
            if (Storelly_PB_Util::is_storelly_product_builder($product_id)) {
                include(STORELLY_PB_PLUGIN_DIR . 'views/product-builder/wrapper.php');
            }
        }
    }
}
$nbd_product_builder = Storelly_Product_Builder_Frontend::instance();
$nbd_product_builder->init();