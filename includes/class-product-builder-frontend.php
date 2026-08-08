<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('SPBWC_Storelly_Product_Builder_Frontend')) {
    class SPBWC_Storelly_Product_Builder_Frontend
    {
        protected static $instance;
        protected static $spbwc_upload_debug_context_logged = false;
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
        public function spbwc_init()
        {
            $this->spbwc_frontend_enqueue_scripts();
            add_action('woocommerce_before_single_product', array(&$this, 'spbwc_before_product_container'), 1);

            // Register the Customize button + popup wrapper hooks UNCONDITIONALLY
            // at init time. The original design only added them from inside the
            // `woocommerce_before_single_product` callback, which means themes
            // that skip that action (custom WC templates, page builders that
            // bypass woocommerce_content, etc.) silently lose the button — the
            // `do_action('spbwc_after_default_options')` call in option-builder.php
            // fires with no listener attached, even though show_option_fields()
            // is rendering correctly via `woocommerce_before_add_to_cart_button`.
            // The handlers themselves check spbwc_is_product_builder($pid)
            // before emitting markup, so registering them always is safe.
            // Render the Preview & customize button RIGHT BELOW the product image (inside
            // the WooCommerce gallery container) via `woocommerce_product_thumbnails`. Now
            // that pricing-option fields surface the design components inline (opt-in), the
            // button is no longer a "next step inside the options form" — it's a way to open
            // the visual design preview, conceptually paired with the product image rather
            // than with the option fields. spbwc_product_builder_html guards on product type.
            add_action('woocommerce_product_thumbnails', array(&$this, 'spbwc_product_builder_html'), 30);
            add_action('wp_footer', array(&$this, 'spbwc_modal_product_builder'), 1);
            add_action('wp_ajax_spbwc_save_product_builder_design', array($this, 'spbwc_save_product_builder_design'));
            add_action('wp_ajax_nopriv_spbwc_save_product_builder_design', array($this, 'spbwc_save_product_builder_design'));

            if (is_admin()) {
                $this->spbwc_ajax();
            }
            add_action('template_redirect', array($this, 'spbwc_template_redirect'));
        }
        public function spbwc_ajax()
        {
            $ajax_events = array(
                'spbwc_save_product_builder_design' => true,
                'spbwc_customer_upload' => true,
            );
            foreach ($ajax_events as $ajax_event => $nopriv) {
                add_action('wp_ajax_' . $ajax_event, array($this, $ajax_event));
                if ($nopriv) {
                    add_action('wp_ajax_nopriv_' . $ajax_event, array($this, $ajax_event));
                }
            }
        }
        public function spbwc_template_redirect()
        {
            if (SPBWC_Storelly_PB_Util::spbwc_is_product_builder_page() && (current_user_can('editor') || current_user_can('administrator'))) {
                include(SPBWC_PB_PLUGIN_DIR . 'views/product-builder/index.php');
                exit();
            }
        }
        public function spbwc_customer_upload()
        {
            $nonce_value = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if (!$nonce_value || !wp_verify_nonce($nonce_value, 'spbwc_save_design_action')) {
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
            $file_name = $file_name_raw ? sanitize_file_name(wp_basename($file_name_raw)) : '';
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is set by PHP; do not use wp_unslash() — stripslashes() breaks Windows paths (C:\Users\...).
            $tmp_name = isset($_FILES['file']['tmp_name']) && is_string($_FILES['file']['tmp_name']) ? $_FILES['file']['tmp_name'] : '';
            $tmp_ok = ('' !== $tmp_name && is_uploaded_file($tmp_name))
                || ('' !== $tmp_name && file_exists($tmp_name) && is_readable($tmp_name));
            if ('' === $file_name || '' === $tmp_name || !$tmp_ok) {
                wp_send_json(array('flag' => 0, 'mes' => esc_html__('Invalid upload.', 'storelly-product-builder-for-woocommerce')));
            }

            $checked_type = wp_check_filetype_and_ext($tmp_name, $file_name);
            $mime_type = isset($checked_type['type']) ? $checked_type['type'] : '';

            if ('' === $file_name || !in_array($mime_type, $allowed_types, true)) {
                wp_send_json(array('flag' => 0, 'mes' => esc_html__('Only image files are supported.', 'storelly-product-builder-for-woocommerce')));
            }

            if (!function_exists('wp_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $prepared_file = array(
                'name' => $file_name,
                'type' => $mime_type,
                'tmp_name' => $tmp_name,
                'error' => 0,
                'size' => isset($_FILES['file']['size']) ? absint($_FILES['file']['size']) : 0,
            );

            $upload_overrides = array('test_form' => false);
            // Sideload uses is_readable + copy(); wp_handle_upload uses is_uploaded_file() which can falsely fail on some Windows/PHP setups.
            $uploaded_file = wp_handle_sideload($prepared_file, $upload_overrides);

            if (isset($uploaded_file['error'])) {
                wp_send_json(array('flag' => 0, 'mes' => sanitize_text_field($uploaded_file['error'])));
            }

            wp_send_json(array('flag' => 1, 'src' => esc_url_raw($uploaded_file['url'])));
        }
        public function spbwc_save_product_builder_design()
        {
            // Lightweight timing instrumentation — toggle via WP_DEBUG. Helps pinpoint
            // which step of the "Done" save flow dominates real wall-clock time so we
            // optimize the actual bottleneck instead of guessing. Set
            //   define('SPBWC_PB_PROFILE_SAVE', true);
            // in wp-config.php to enable logging without WP_DEBUG.
            $spbwc_profile = ( defined('WP_DEBUG') && WP_DEBUG ) || ( defined('SPBWC_PB_PROFILE_SAVE') && SPBWC_PB_PROFILE_SAVE );
            $spbwc_t0 = microtime(true);
            $spbwc_t_prev = $spbwc_t0;
            $spbwc_mark = function ( $label ) use ( $spbwc_profile, $spbwc_t0, &$spbwc_t_prev ) {
                if ( ! $spbwc_profile ) { return; }
                $now = microtime(true);
                error_log(sprintf('[SPBWC save] %-26s  step=%6.0fms  total=%6.0fms', $label, ($now - $spbwc_t_prev) * 1000, ($now - $spbwc_t0) * 1000)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                $spbwc_t_prev = $now;
            };
            // 1. SECURITY & PERMISSIONS


            $nonce_value = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if (!$nonce_value || !wp_verify_nonce($nonce_value, 'spbwc_save_design_action')) {
                wp_send_json_error(array('message' => __('Security error.', 'storelly-product-builder-for-woocommerce')), 403);
            }
            $spbwc_mark('nonce verified');

            // 2. INITIALIZE VARIABLES
            $result = array(
                'flag' => 'failure',
                'link' => '',
                'folder' => '',
            );

            do_action('spbwc_before_save_product_builder_design');

            $pcpb_item_pb_key = isset($_POST['pcpb_item_pb_key']) ? sanitize_text_field(wp_unslash($_POST['pcpb_item_pb_key'])) : '';
            // JS sends pcpb_cart_item_key (see SPBWC_PB_CONFIG); map cart item to saved design folder when editing cart.
            if ('' === $pcpb_item_pb_key && isset($_POST['pcpb_cart_item_key'])) {
                $cart_item_key = sanitize_text_field(wp_unslash($_POST['pcpb_cart_item_key']));
                if ('' !== $cart_item_key && function_exists('WC') && WC()->cart) {
                    $cart_item = WC()->cart->get_cart_item($cart_item_key);
                    if ($cart_item && isset($cart_item['pcpb_meta']['pcpb']) && '' !== $cart_item['pcpb_meta']['pcpb']) {
                        $pcpb_item_pb_key = sanitize_text_field($cart_item['pcpb_meta']['pcpb']);
                    }
                }
            }
            // Re-save in same session: hidden input prcpb-folder synced to POST as prcpb_folder from the builder.
            if ('' === $pcpb_item_pb_key && isset($_POST['prcpb_folder'])) {
                $from_form = sanitize_text_field(wp_unslash($_POST['prcpb_folder']));
                if ('' !== $from_form && $from_form === basename($from_form)) {
                    $pcpb_item_pb_key = $from_form;
                }
            }
            // Generate key if still empty.
            if ('' === $pcpb_item_pb_key) {
                $rand = wp_rand(1, 100); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand -- wp_rand() is always available in WordPress.
                $pcpb_item_pb_key = substr(md5(uniqid()), 0, 5) . $rand . time();
            }

            $is_creating_task = isset($_POST['is_creating_task']) ? sanitize_text_field(wp_unslash($_POST['is_creating_task'])) : '0';
            $oid = isset($_POST['oid']) ? absint(wp_unslash($_POST['oid'])) : 0;
            $path = SPBWC_PB_CUSTOMER_DIR . '/' . $pcpb_item_pb_key;
            $filtered_files = array();

            // 3. PROCESS STATIC FILES (WHITELIST)
            $allowed_file_keys = array('design', 'config', 'used_font', 'design_output');

            foreach ($allowed_file_keys as $key) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES[$key] is sanitized inside spbwc_sanitize_file_upload() method.
                if (isset($_FILES[$key]) && !empty($_FILES[$key]['tmp_name'])) {
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES[$key] is sanitized inside spbwc_sanitize_file_upload() method.
                    $filtered_files[$key] = $this->spbwc_sanitize_file_upload($_FILES[$key]);
                }
            }

            // 4. PROCESS DYNAMIC FILES (FRAMES)
            // Read config to determine frames, avoiding full $_FILES scan.
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name validated with is_uploaded_file() and used only for file reading.
            if (isset($_FILES['config']['tmp_name']) && is_uploaded_file($_FILES['config']['tmp_name'])) {
                $config_tmp_name = $_FILES['config']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated above, used only for file reading.
                $config_content = file_get_contents($config_tmp_name); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading temporary uploaded file.
                $config_data = json_decode($config_content);

                if ($config_data && isset($config_data->views)) {
                    $config_views = $config_data->views;
                    if (is_object($config_views)) {
                        $config_views = (array) $config_views;
                    }
                    if (is_array($config_views)) {
                        foreach ($config_views as $index => $view) {
                            // Each view uploads two artefacts: the rasterised PNG
                            // (frame_<i>) used for previews, and the vector SVG
                            // (frame_<i>_svg) the print-PDF pipeline rebuilds the page
                            // from (see SPBWC_Storelly_Export_PDF::spbwc_export_pdf()).
                            // Both must be persisted — dropping the _svg variant leaves
                            // PDF generation with nothing to render and the order stuck
                            // at "PDF failed".
                            $frame_keys = array('frame_' . $index, 'frame_' . $index . '_svg');

                            foreach ($frame_keys as $key) {
                                // Only process if this specific key exists
                                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES[$key] is sanitized inside spbwc_sanitize_file_upload() method.
                                if (isset($_FILES[$key]) && !empty($_FILES[$key]['tmp_name'])) {
                                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES[$key] is sanitized inside spbwc_sanitize_file_upload() method.
                                    $filtered_files[$key] = $this->spbwc_sanitize_file_upload($_FILES[$key]);
                                }
                            }
                        }
                    }
                }
            }

            $spbwc_mark('files filtered (' . count($filtered_files) . ')');
            // 5. SAVE DATA
            $save_status = $this->spbwc_store_product_builder_design_data($pcpb_item_pb_key, $filtered_files);
            $spbwc_mark('store_design_data (disk)');

            if (false != $save_status) {
                $result['image'] = $this->spbwc_create_preview($path);
                $spbwc_mark('create_preview (Imagick/GD)');

                if (is_array($result['image'])) {
                    asort($result['image']);
                }

                $result['flag'] = 'success';
                $result['folder'] = $pcpb_item_pb_key;

                // Free design tools — count billable buyer-added layers from the
                // saved frames (authoritative: the actual canvas objects, never a
                // client claim) and persist counts for the cart pricing engine.
                $this->spbwc_store_user_layer_counts($path);

                // Update Database if needed
                if ('1' === $is_creating_task && 0 !== $oid) {
                    global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
                    $table_name = $wpdb->prefix . 'storelly_product_builder_options';

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update required; related caches are cleared below.
                    $wpdb->update(
                        $table_name,
                        array('builder' => $pcpb_item_pb_key),
                        array('id' => $oid),
                        array('%s'),
                        array('%d')
                    );

                    wp_cache_delete('spbwc_option_' . $oid, 'spbwc_product_builder');
                }
            }

            do_action('spbwc_after_save_product_builder_design', $result);
            wp_send_json($result);
        }

        /**
         * Count buyer-added free layers (text/image) from the saved design.json
         * and persist them as user-layers.json in the design folder. The cart
         * pricing engine reads this file and multiplies by the per-layer fee from
         * the resolved option-set config — so the charge cannot be faked from the
         * client (counts come from the actual saved canvas objects).
         *
         * @param string $path Absolute path of the design folder.
         * @return array{text:int,image:int}
         */
        private function spbwc_store_user_layer_counts($path)
        {
            $counts = array('text' => 0, 'image' => 0);
            $design_raw = SPBWC_Storelly_IO::spbwc_get_local_file_contents($path . '/design.json');
            if (false !== $design_raw && '' !== $design_raw) {
                $design = json_decode($design_raw, true);
                if (is_array($design)) {
                    $seen = array();
                    foreach ($design as $stage) {
                        if (!is_array($stage) || !isset($stage['objects']) || !is_array($stage['objects'])) {
                            continue;
                        }
                        foreach ($stage['objects'] as $obj) {
                            if (!is_array($obj) || empty($obj['isUserLayer'])) {
                                continue;
                            }
                            $uid = isset($obj['userLayerUid']) ? (string) $obj['userLayerUid'] : '';
                            if ('' !== $uid) {
                                if (isset($seen[$uid])) { continue; }
                                $seen[$uid] = 1;
                            }
                            $type = isset($obj['type']) ? $obj['type'] : '';
                            $kind = isset($obj['userLayerKind']) ? $obj['userLayerKind'] : ('textbox' === $type ? 'text' : 'image');
                            if ('text' === $kind) {
                                $counts['text']++;
                            } else {
                                $counts['image']++;
                            }
                        }
                    }
                }
            }
            SPBWC_Storelly_IO::spbwc_put_local_file_contents($path . '/user-layers.json', wp_json_encode($counts));
            return $counts;
        }
        /**
         * Helper function to sanitize file array
         * Helps keep the main function clean
         */
        private function spbwc_sanitize_file_upload($file)
        {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name from PHP $_FILES; do not use wp_unslash() — stripslashes() destroys backslashes in Windows temp paths.
            $tmp_name = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';
            return array(
                'name' => isset($file['name']) ? sanitize_file_name(wp_unslash($file['name'])) : '',
                'type' => isset($file['type']) ? sanitize_text_field(wp_unslash($file['type'])) : '',
                'tmp_name' => $tmp_name,
                'error' => isset($file['error']) ? absint($file['error']) : 0,
                'size' => isset($file['size']) ? absint($file['size']) : 0,
            );
        }
        private function spbwc_create_preview($path)
        {
            $config_raw = SPBWC_Storelly_IO::spbwc_get_local_file_contents($path . '/config.json');
            $config = (false !== $config_raw) ? json_decode($config_raw) : null;
            $images = array();
            if (!wp_mkdir_p($path . '/preview') || !isset($config->views)) {
                return $images;
            }
            // Imagick is typically 3–5× faster than GD for resize + composite + PNG
            // encode. We prefer it when available; gracefully fall back to GD when not.
            $use_imagick = class_exists('Imagick') && extension_loaded('imagick');
            foreach ($config->views as $index => $view) {
                $design_path  = $path . '/frame_' . $index . '.png';
                $preview_path = $path . '/preview/' . $index . '.png';
                if (!file_exists($design_path)) {
                    continue;
                }
                list($width, $height) = getimagesize($design_path);
                $width  = intval($width);
                $height = intval($height);
                $base_img_path = SPBWC_Storelly_IO::spbwc_convert_url_to_path($view->base_url);
                if (!is_file($base_img_path)) {
                    // No base image — the design alone IS the preview.
                    copy($design_path, $preview_path);
                    $images[] = SPBWC_Storelly_IO::spbwc_convert_path_to_url($preview_path);
                    continue;
                }
                if ($use_imagick) {
                    try {
                        $base = new Imagick($base_img_path);
                        // FILTER_TRIANGLE = speed/quality sweet spot, much faster than LANCZOS.
                        $base->resizeImage($width, $height, Imagick::FILTER_TRIANGLE, 1);
                        $design = new Imagick($design_path);
                        $base->compositeImage($design, Imagick::COMPOSITE_OVER, 0, 0);
                        $base->setImageFormat('png');
                        // PNG encode speed-ups for previews — default zlib compression-level
                        // is 6 which dominates encode time; level 1 is ~3-5× faster and the
                        // ~20% size bump is fine for a small preview. compression-filter 0
                        // disables adaptive filtering for another speed bump.
                        $base->setOption('png:compression-level',  '1');
                        $base->setOption('png:compression-filter', '0');
                        $base->setImageCompressionQuality(80);
                        $base->writeImage($preview_path);
                        $design->clear(); $design->destroy();
                        $base->clear();   $base->destroy();
                    } catch (Exception $e) {
                        // Imagick blew up for some reason — fall back to GD on this view.
                        $this->spbwc_gd_composite_preview($base_img_path, $design_path, $preview_path, $width, $height);
                    }
                } else {
                    $this->spbwc_gd_composite_preview($base_img_path, $design_path, $preview_path, $width, $height);
                }
                $images[] = SPBWC_Storelly_IO::spbwc_convert_path_to_url($preview_path);
            }
            return $images;
        }
        /**
         * GD-based composite (resize base → overlay design → write PNG).
         * Extracted so spbwc_create_preview can call either Imagick or GD via the same shape.
         */
        private function spbwc_gd_composite_preview($base_img_path, $design_path, $preview_path, $width, $height) {
            $base_img = SPBWC_Storelly_Image::resize_imagepng($base_img_path, $width, $height);
            if (!$base_img) { return; }
            $design = imagecreatefrompng($design_path);
            if ($design) {
                imagecopy($base_img, $design, 0, 0, 0, 0, $width, $height);
                imagedestroy($design);
            }
            imagepng($base_img, $preview_path);
            imagedestroy($base_img);
        }
        private function spbwc_store_product_builder_design_data($pcpb_item_pb_key, $data)
        {
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            self::$spbwc_upload_debug_context_logged = false;
            $this->spbwc_log_multipart_upload_context_once();
            $upload_overrides = array('test_form' => false);
            $path = SPBWC_PB_CUSTOMER_DIR . '/' . $pcpb_item_pb_key;
            $this->path = $pcpb_item_pb_key;
            if (file_exists($path . '_old'))
                SPBWC_Storelly_IO::spbwc_delete_folder($path . '_old');
            if (file_exists($path))
                rename($path, $path . '_old'); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Direct filesystem rename needed for atomic backup operation.
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
                        $raw_ext = isset($mime_parts[1]) ? strtolower($mime_parts[1]) : 'bin';
                        $ext_map = array(
                            'svg+xml' => 'svg',
                        );
                        $safe_ext = isset($ext_map[$raw_ext]) ? $ext_map[$raw_ext] : preg_replace('/[^a-z0-9]/', '', $raw_ext);
                        $safe_key = sanitize_file_name($key);
                        $full_name = $path . '/' . $safe_key . '.' . ($safe_ext ? $safe_ext : 'bin');
                    }

                    if (!is_array($val) || empty($val['tmp_name'])) {
                        continue;
                    }

                    $prepared_file = array(
                        'name' => sanitize_file_name(basename($full_name)),
                        'type' => isset($val['type']) ? sanitize_text_field($val['type']) : '',
                        'tmp_name' => $val['tmp_name'],
                        'error' => isset($val['error']) ? absint($val['error']) : 0,
                        'size' => isset($val['size']) ? absint($val['size']) : 0,
                    );

                    if (UPLOAD_ERR_OK !== $prepared_file['error']) {
                        $this->spbwc_restore_backup_directory($path);
                        return false;
                    }

                    $raw_files_row = (isset($_FILES[$key]) && is_array($_FILES[$key])) ? $_FILES[$key] : null;
                    $this->spbwc_log_multipart_file_state($key, $prepared_file, $raw_files_row);
                    $uploaded_file = wp_handle_sideload($prepared_file, $upload_overrides);
                    if (isset($uploaded_file['error'])) {
                        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                            error_log('[SPBWC] wp_handle_sideload error key=' . $key . ' msg=' . $uploaded_file['error']);
                        }
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

        /**
         * Log multipart request limits once per save when WP_DEBUG_LOG is on.
         */
        private function spbwc_log_multipart_upload_context_once()
        {
            if (self::$spbwc_upload_debug_context_logged) {
                return;
            }
            if (!defined('WP_DEBUG') || !WP_DEBUG || !defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
                return;
            }
            self::$spbwc_upload_debug_context_logged = true;
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Debug-only; value is not output to HTML.
            $content_length = isset($_SERVER['CONTENT_LENGTH']) ? wp_unslash($_SERVER['CONTENT_LENGTH']) : '';
            $content_length = is_string($content_length) ? $content_length : '';
            error_log(
                sprintf(
                    '[SPBWC] design save multipart: CONTENT_LENGTH=%s upload_max_filesize=%s post_max_size=%s upload_tmp_dir=%s',
                    '' !== $content_length ? $content_length : 'n/a',
                    ini_get('upload_max_filesize'),
                    ini_get('post_max_size'),
                    ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : '(default)'
                )
            );
        }

        /**
         * Log per-field upload state when WP_DEBUG_LOG is on (diagnose is_uploaded_file vs is_readable).
         *
         * @param string          $key            $_FILES field name.
         * @param array           $prepared_file  File array for wp_handle_sideload().
         * @param array|null      $raw_file       $_FILES[$key] or null.
         */
        private function spbwc_log_multipart_file_state($key, $prepared_file, $raw_file = null)
        {
            if (!defined('WP_DEBUG') || !WP_DEBUG || !defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
                return;
            }
            $tmp = isset($prepared_file['tmp_name']) && is_string($prepared_file['tmp_name']) ? $prepared_file['tmp_name'] : '';
            $raw_err = (is_array($raw_file) && array_key_exists('error', $raw_file)) ? (string) $raw_file['error'] : 'n/a';
            error_log(
                sprintf(
                    '[SPBWC] design save file key=%s tmp_basename=%s tmp_len=%d prepared_error=%d size=%d is_uploaded_file=%s is_readable=%s file_exists=%s FILES_error=%s',
                    $key,
                    '' !== $tmp ? basename($tmp) : '',
                    strlen($tmp),
                    isset($prepared_file['error']) ? absint($prepared_file['error']) : -1,
                    isset($prepared_file['size']) ? absint($prepared_file['size']) : 0,
                    ('' !== $tmp && is_uploaded_file($tmp)) ? '1' : '0',
                    ('' !== $tmp && is_readable($tmp)) ? '1' : '0',
                    ('' !== $tmp && file_exists($tmp)) ? '1' : '0',
                    $raw_err
                )
            );
        }

        private function spbwc_restore_backup_directory($path)
        {
            if (file_exists($path)) {
                SPBWC_Storelly_IO::spbwc_delete_folder($path);
            }
            if (file_exists($path . '_old')) {
                rename($path . '_old', $path); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Restoring previous customer design snapshot.
            }
        }
        public function spbwc_before_product_container()
        {
            $pid = get_the_ID();
            if (SPBWC_Storelly_PB_Util::spbwc_product_has_designer($pid)) {
                add_action('wp_ajax_spbwc_save_product_builder_design', array($this, 'spbwc_save_product_builder_design'));
        add_action('wp_ajax_nopriv_spbwc_save_product_builder_design', array($this, 'spbwc_save_product_builder_design'));
                add_action('woocommerce_product_thumbnails', array(&$this, 'spbwc_product_builder_html'), 30);
                add_action('wp_footer', array(&$this, 'spbwc_modal_product_builder'), 1);
            }
        }

        public function spbwc_frontend_enqueue_scripts()
        {
            add_action('wp_enqueue_scripts', function () {
                $js_libs = array(
                    'fontfaceobserver' => array(
                        'link' => SPBWC_PB_ASSETS_URL . 'libs/fontfaceobserver.js',
                        'version' => '2.0.13',
                        'depends' => array('jquery')
                    ),
                    'spectrum' => array(
                        'link' => SPBWC_PB_JS_URL . 'spectrum.js',
                        'version' => '1.8.0',
                        'depends' => array('jquery')
                    ),
                    'fabricjs' => array(
                        'link' => SPBWC_PB_ASSETS_URL . 'libs/fabric.2.6.0.min.js',
                        'version' => '2.6.0',
                        'depends' => array()
                    ),
                    'pc-builderjs' => array(
                        'link' => SPBWC_PB_ASSETS_URL . 'libs/builderproductag.min.js',
                        'version' => '1.6.9',
                        'depends' => array('jquery')
                    ),
                    'product-builder' => array(
                        'link' => SPBWC_PB_JS_URL . 'app-product-builder.js',
                        // filemtime so edits bust the browser cache automatically (no hard-refresh).
                        'version' => ( file_exists( SPBWC_PB_ASSETS_DIR . 'js/app-product-builder.js' ) ? filemtime( SPBWC_PB_ASSETS_DIR . 'js/app-product-builder.js' ) : SPBWC_PB_VERSION ),
                        'depends' => array('jquery', 'underscore', 'pc-builderjs', 'fabricjs', 'fontfaceobserver', 'spectrum', 'spbwc-dialog')
                    )
                );
                // Register design tokens style first — app-product-builder.css
                // references var(--nbd-st-bg-soft), var(--st-brand) etc., which
                // resolve to nothing on the frontend if _tokens.css isn't
                // enqueued, breaking the popup background and other surfaces.
                if (!wp_style_is('spbwc-tokens', 'registered')) {
                    wp_register_style('spbwc-tokens', SPBWC_PB_CSS_URL . '_tokens.css', array(), SPBWC_PB_VERSION);
                }
                $css_libs = array(
                    'spectrum' => array(
                        'link' => SPBWC_PB_ASSETS_URL . 'css/spectrum.css',
                        'version' => '1.8.0',
                        'depends' => array()
                    ),
                    'product-builder' => array(
                        'link' => SPBWC_PB_ASSETS_URL . 'css/app-product-builder.css',
                        // filemtime so edits bust the browser cache automatically (no hard-refresh).
                        'version' => ( file_exists( SPBWC_PB_ASSETS_DIR . 'css/app-product-builder.css' ) ? filemtime( SPBWC_PB_ASSETS_DIR . 'css/app-product-builder.css' ) : SPBWC_PB_VERSION ),
                        'depends' => array('spectrum', 'spbwc-tokens', 'spbwc-dialog')
                    ),
                );
                foreach ($css_libs as $key => $css) {
                    $link = $css['link'];
                    wp_register_style($key, $link, $css['depends'], $css['version']);
                }
                // Load app-product-builder-rtl.css automatically on RTL sites so
                // the designer modal mirrors correctly (padding/float/absolute).
                wp_style_add_data( 'product-builder', 'rtl', 'replace' );
                foreach ($js_libs as $key => $js) {
                    $link = $js['link'];
                    wp_register_script($key, $link, $js['depends'], $js['version'], false);
                }
            });
        }
        public function spbwc_product_builder_html()
        {
            // Only render the Customize button on designer products. The hook
            // (`woocommerce_product_thumbnails`) fires on every product page, so the gate
            // here replaces the implicit `if ($has_nbpb)` guard that wrapped the previous
            // `spbwc_after_default_options` listener.
            $pid = ( function_exists( 'is_product' ) && is_product() ) ? get_queried_object_id() : get_the_ID();
            if ( ! $pid || ! SPBWC_Storelly_PB_Util::spbwc_product_has_designer( $pid ) ) {
                return;
            }
            include(SPBWC_PB_PLUGIN_DIR . 'views/product-builder/customize-btn.php');
        }
        public function spbwc_modal_product_builder()
        {
            $product_id = get_the_ID();
            $option_id = get_transient('spbwc_product_builder_' . $product_id);
            if (SPBWC_Storelly_PB_Util::spbwc_product_has_designer($product_id)) {
                /* V2 customizer (Cloodo-style 3-column layout) is the default.
                 * Legacy single-pane UI is preserved at wrapper-legacy.php for
                 * emergency rollback — opt-in via filter or constant:
                 *   add_filter( 'spbwc_use_legacy_customizer', '__return_true' );
                 *   define( 'SPBWC_USE_LEGACY_CUSTOMIZER', true );
                 */
                $use_legacy = ( defined( 'SPBWC_USE_LEGACY_CUSTOMIZER' ) && SPBWC_USE_LEGACY_CUSTOMIZER )
                    || apply_filters( 'spbwc_use_legacy_customizer', false );
                $wrapper_file = $use_legacy ? 'wrapper-legacy.php' : 'wrapper.php';
                $wrapper_path = SPBWC_PB_PLUGIN_DIR . 'views/product-builder/' . $wrapper_file;
                if ( ! file_exists( $wrapper_path ) ) {
                    $wrapper_path = SPBWC_PB_PLUGIN_DIR . 'views/product-builder/wrapper.php';
                }
                include $wrapper_path;
            }
        }
    }
}
$spbwc_product_builder_frontend = SPBWC_Storelly_Product_Builder_Frontend::instance();
$spbwc_product_builder_frontend->spbwc_init();
