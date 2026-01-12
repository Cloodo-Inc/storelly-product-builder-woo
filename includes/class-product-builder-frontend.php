<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('SPBWC_Storelly_Product_Builder_Frontend')) {
    class SPBWC_Storelly_Product_Builder_Frontend {
        protected static $instance;
        protected $isDesign = false;
        protected $path = '';
        public function __construct()
        {
            //TODO
        }
        public static function instance()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        public function spbwc_init() {
            $this->spbwc_frontend_enqueue_scripts();
            add_action('woocommerce_before_single_product', array(&$this, 'spbwc_before_product_container'), 1);
            if (is_admin()) {
                $this->spbwc_ajax();
            }
            add_action('spbwc_template_redirect', array($this, 'spbwc_template_redirect'));
        }
        public function spbwc_ajax() {
            $ajax_events = array(
                'spbwc_save_product_builder_design'   => true,
                'spbwc_customer_upload'             => true,
            );
            foreach ($ajax_events as $ajax_event => $nopriv) {
                add_action('wp_ajax_' . $ajax_event, array($this, $ajax_event));
                if ($nopriv) {
                    add_action('wp_ajax_nopriv_' . $ajax_event, array($this, $ajax_event));
                }
            }
        }
        public function spbwc_template_redirect() {
            if (SPBWC_Storelly_PB_Util::spbwc_is_product_builder_page() && (current_user_can('editor') || current_user_can('administrator'))) {
                include(SPBWC_PB_PLUGIN_DIR . 'views/product-builder/index.php');
                exit();
            }
        }
        public function spbwc_customer_upload() {
            $nonce_value = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if ( ! $nonce_value || ! wp_verify_nonce( $nonce_value, 'spbwc_save_design_action' ) ) {
                wp_send_json(array('flag' => 0, 'mes' => esc_html__('Security check failed.', 'storelly-product-builder-for-woocommerce')));
            }

            if (empty($_FILES['file']) || !is_array($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
                wp_send_json(array('flag' => 0, 'mes' => esc_html__('No file uploaded.', 'storelly-product-builder-for-woocommerce')));
            }

            $file_error = isset($_FILES['file']['error']) ? absint($_FILES['file']['error']) : UPLOAD_ERR_NO_FILE;
            if (UPLOAD_ERR_OK !== $file_error) {
                wp_send_json(array('flag' => 0, 'mes' => esc_html__('Upload error. Please try again.', 'storelly-product-builder-for-woocommerce')));
            }

            $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/svg+xml');
            $file_name_raw = isset($_FILES['file']['name']) ? sanitize_file_name(wp_unslash($_FILES['file']['name'])) : '';
            $file_name     = $file_name_raw ? sanitize_file_name(wp_basename($file_name_raw)) : '';
            $tmp_name      = isset($_FILES['file']['tmp_name']) ? sanitize_text_field(wp_unslash($_FILES['file']['tmp_name'])) : '';
            if ('' === $file_name || '' === $tmp_name || !is_uploaded_file($tmp_name)) {
                wp_send_json(array('flag' => 0, 'mes' => esc_html__('Invalid upload.', 'storelly-product-builder-for-woocommerce')));
            }

            $checked_type = wp_check_filetype_and_ext($tmp_name, $file_name);
            $mime_type    = isset($checked_type['type']) ? $checked_type['type'] : '';

            if ('' === $file_name || !in_array($mime_type, $allowed_types, true)) {
                wp_send_json(array('flag' => 0, 'mes' => esc_html__('Only image files are supported.', 'storelly-product-builder-for-woocommerce')));
            }

            if (!function_exists('wp_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $prepared_file = array(
                'name'     => $file_name,
                'type'     => $mime_type,
                'tmp_name' => $tmp_name,
                'error'    => 0,
                'size'     => isset($_FILES['file']['size']) ? absint($_FILES['file']['size']) : 0,
            );

            $upload_overrides = array('test_form' => false);
            $uploaded_file    = wp_handle_upload($prepared_file, $upload_overrides);

            if (isset($uploaded_file['error'])) {
                wp_send_json(array('flag' => 0, 'mes' => sanitize_text_field($uploaded_file['error'])));
            }

            wp_send_json(array('flag' => 1, 'src' => esc_url_raw($uploaded_file['url'])));
        }
        public function spbwc_save_product_builder_design() {
            if ( ! current_user_can( 'edit_posts' ) ) { 
                die( esc_html__( 'You do not have permission to save design.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $nonce_value = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! $nonce_value || ! wp_verify_nonce( $nonce_value, 'spbwc_save_design_action' ) ) {
                die( esc_html__( 'Security error.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $result = array(
                'flag'  =>  'failure',
                'link'  =>  '',
                'folder' => ''
            );
            do_action('spbwc_before_save_product_builder_design');
            $pcpb_item_pb_key = isset($_POST['pcpb_item_pb_key']) ? sanitize_text_field(wp_unslash($_POST['pcpb_item_pb_key'])) : substr(md5(uniqid()), 0, 5) . ( function_exists( 'wp_rand' ) ? wp_rand( 1, 100 ) : rand( 1, 100 ) ) . time(); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand -- Fallback for environments where wp_rand is not available.
            $is_creating_task = isset($_POST['is_creating_task']) ? sanitize_text_field(wp_unslash($_POST['is_creating_task'])) : '0';
            $oid = isset($_POST['oid']) ? absint(wp_unslash($_POST['oid'])) : 0;
            $path = SPBWC_PB_CUSTOMER_DIR . '/' . $pcpb_item_pb_key;
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload data validated by wp_handle_upload in spbwc_store_product_builder_design_data.
            $save_status = $this->spbwc_store_product_builder_design_data($pcpb_item_pb_key, $_FILES);
            if (false != $save_status) {
                $result['image'] = $this->spbwc_create_preview($path);
                asort($result['image']);
                $result['flag'] = 'success';
                $result['folder'] = $pcpb_item_pb_key;
                if ($is_creating_task == '1' && $oid != 0) {
                    global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
                    $arr = array(
                        'builder'   =>  $pcpb_item_pb_key
                    );
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update required; related caches are cleared below.
                    $result_update = $wpdb->update(
                        "{$wpdb->prefix}storelly_product_builder_options",
                        $arr,
                        array('id' => $oid),
                        array('%s'),
                        array('%d')
                    );
                    wp_cache_delete('spbwc_option_' . $oid, 'spbwc_product_builder');
                }
            }
            do_action('spbwc_after_save_product_builder_design', $result);
            echo wp_json_encode($result);
            wp_die();
        }
        private function spbwc_create_preview($path) {
            $config_raw = SPBWC_Storelly_IO::spbwc_get_local_file_contents($path . '/config.json');
            $config = (false !== $config_raw) ? json_decode($config_raw) : null;
            $images = array();
            if (wp_mkdir_p($path . '/preview')) {
                foreach ($config->views as $index => $view) {
                    $design_path = $path . '/frame_' . $index . '.png';
                    if (file_exists($design_path)) {
                        list($width, $height) = getimagesize($design_path);
                        $width = intval($width);
                        $height = intval($height);
                        $base_img_path = SPBWC_Storelly_IO::spbwc_convert_url_to_path($view->base_url);
                        if (is_file($base_img_path)) {
                            $base_img_info = pathinfo($base_img_path);
                            if ($base_img_info['extension'] == "png") {
                                $base_img = SPBWC_Storelly_Image::resize_imagepng($base_img_path, $width, $height);
                            } else {
                                $base_img = SPBWC_Storelly_Image::resize_imagepng($base_img_path, $width, $height);
                            }
                            $design = imagecreatefrompng($design_path);
                            imagecopy($base_img, $design, 0, 0, 0, 0, $width, $height);
                            imagepng($base_img, $path . '/preview/' . $index . '.png');
                            imagedestroy($base_img);
                            imagedestroy($design);
                        } else {
                            copy($design_path, $path . '/preview/' . $index . '.png');
                        }
                        $images[] = SPBWC_Storelly_IO::spbwc_convert_path_to_url($path . '/preview/' . $index . '.png');
                    }
                }
            };
            return $images;
        }
        private function spbwc_store_product_builder_design_data($pcpb_item_pb_key, $data) {
            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
            }
            $upload_overrides = array( 'test_form' => false );
            $path = SPBWC_PB_CUSTOMER_DIR . '/' . $pcpb_item_pb_key;
            $this->path = $pcpb_item_pb_key;
            if (file_exists($path . '_old')) SPBWC_Storelly_IO::spbwc_delete_folder($path . '_old');
            if (file_exists($path)) rename($path, $path . '_old'); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Direct filesystem rename needed for atomic backup operation.
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
                        $mime_parts = isset($val['type']) ? explode('/', $val['type']) : array();
                        $raw_ext    = isset($mime_parts[1]) ? strtolower($mime_parts[1]) : 'bin';
                        $ext_map    = array(
                            'svg+xml' => 'svg',
                        );
                        $safe_ext   = isset($ext_map[$raw_ext]) ? $ext_map[$raw_ext] : preg_replace('/[^a-z0-9]/', '', $raw_ext);
                        $safe_key   = sanitize_file_name($key);
                        $full_name  = $path . '/' . $safe_key . '.' . ( $safe_ext ? $safe_ext : 'bin' );
                    }

                    if (!is_array($val) || empty($val['tmp_name'])) {
                        continue;
                    }

                    $prepared_file = array(
                        'name'     => sanitize_file_name(basename($full_name)),
                        'type'     => isset($val['type']) ? sanitize_text_field($val['type']) : '',
                        'tmp_name' => $val['tmp_name'],
                        'error'    => isset($val['error']) ? absint($val['error']) : 0,
                        'size'     => isset($val['size']) ? absint($val['size']) : 0,
                    );

                    if (UPLOAD_ERR_OK !== $prepared_file['error']) {
                        $this->spbwc_restore_backup_directory($path);
                        return false;
                    }

                    $uploaded_file = wp_handle_upload($prepared_file, $upload_overrides);
                    if (isset($uploaded_file['error'])) {
                        $this->spbwc_restore_backup_directory($path);
                        return false;
                    }

                    if (isset($uploaded_file['file']) && file_exists($uploaded_file['file'])) {
                        rename($uploaded_file['file'], $full_name); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Direct filesystem rename needed to move uploaded file to final destination.
                    }
                }
            } else {
                $this->spbwc_restore_backup_directory($path);
                return false;
            }
            return true;
        }
        private function spbwc_restore_backup_directory($path) {
            if (file_exists($path)) {
                SPBWC_Storelly_IO::spbwc_delete_folder($path);
            }
            if (file_exists($path . '_old')) {
                rename($path . '_old', $path); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Restoring previous customer design snapshot.
            }
        }
        public function spbwc_before_product_container() {
            $pid = get_the_ID();
            if (SPBWC_Storelly_PB_Util::spbwc_is_product_builder($pid)) {
                add_action('spbwc_after_default_options', array(&$this, 'spbwc_product_builder_html'), 1);
                add_action('wp_footer', array(&$this, 'spbwc_modal_product_builder'), 1);
            }
        }
        
        public function spbwc_frontend_enqueue_scripts() {
            add_action('wp_enqueue_scripts', function () {
                $js_libs = array(
                    'fontfaceobserver' => array(
                        'link' => SPBWC_PB_ASSETS_URL . 'libs/fontfaceobserver.js',
                        'version'   => '2.0.13',
                        'depends'  => array('jquery')
                    ),
                    'spectrum' => array(
                        'link' => SPBWC_PB_JS_URL . 'spectrum.js',
                        'version'   => '1.8.0',
                        'depends'  => array('jquery')
                    ),
                    'fabricjs' => array(
                        'link' => SPBWC_PB_ASSETS_URL . 'libs/fabric.2.6.0.min.js',
                        'version'   => '2.6.0',
                        'depends'  => array()
                    ),
                    'pc-angularjs' => array(
                        'link' => SPBWC_PB_ASSETS_URL . 'libs/builderproductag.min.js',
                        'version'   => '1.6.9',
                        'depends'  => array('jquery')
                    ),
                    'product-builder' => array(
                        'link' => SPBWC_PB_JS_URL . 'app-product-builder.js',
                        'version'   => SPBWC_PB_VERSION,
                        'depends'  => array('jquery', 'underscore', 'pc-angularjs', 'fabricjs', 'fontfaceobserver', 'spectrum')
                    )
                );
                $css_libs = array(
                    'spectrum' => array(
                        'link'  => SPBWC_PB_ASSETS_URL . 'css/spectrum.css',
                        'version'   => '1.8.0',
                        'depends'  =>  array()
                    ),
                    'product-builder' => array(
                        'link'  => SPBWC_PB_ASSETS_URL . 'css/app-product-builder.css',
                        'version'   => SPBWC_PB_VERSION,
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
        public function spbwc_product_builder_html() {
            include(SPBWC_PB_PLUGIN_DIR . 'views/product-builder/customize-btn.php');
        }
        public function spbwc_modal_product_builder() {
            $product_id = get_the_ID();
            $option_id = get_transient('spbwc_product_builder_' . $product_id);
            if (SPBWC_Storelly_PB_Util::spbwc_is_product_builder($product_id)) {
                include(SPBWC_PB_PLUGIN_DIR . 'views/product-builder/wrapper.php');
            }
        }
    }
}
$spbwc_product_builder_frontend = SPBWC_Storelly_Product_Builder_Frontend::instance();
$spbwc_product_builder_frontend->spbwc_init();
