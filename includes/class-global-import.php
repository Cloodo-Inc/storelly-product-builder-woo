<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPBWC_Global_Import')) {
    class SPBWC_Global_Import {
        protected static $instance;
        private const EXPORT_DIR = 'global-import-exports';
        private const MAX_FILES = 50;
        
        public static function instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        public function init() {
            add_action('init', array($this, 'register_rest_routes'));
            add_action('admin_init', array($this, 'handle_export_actions'));
        }
        
        public function get_export_dir() {
            $upload_dir = wp_upload_dir();
            return trailingslashit($upload_dir['basedir']) . self::EXPORT_DIR . '/';
        }
        
        public function get_export_url() {
            $upload_dir = wp_upload_dir();
            return trailingslashit($upload_dir['baseurl']) . self::EXPORT_DIR . '/';
        }
        
        private function filesystem() {
            global $wp_filesystem;
            if (!$wp_filesystem) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            return $wp_filesystem;
        }

        private function get_export_file_by_import_id($import_id) {
            if (empty($import_id)) {
                return '';
            }
            $export_dir = $this->get_export_dir();
            $files = glob($export_dir . 'import_*.json');
            if (!is_array($files) || empty($files)) {
                return '';
            }
            $fs = $this->filesystem();
            foreach ($files as $file) {
                $file_data = $fs ? $fs->get_contents($file) : '';
                $json_data = json_decode($file_data, true);
                if (
                    is_array($json_data)
                    && isset($json_data['manifest']['import_id'])
                    && $json_data['manifest']['import_id'] === $import_id
                ) {
                    return $file;
                }
            }
            return '';
        }

        private function merge_export_products($existing_products, $new_products) {
            $merged_products = array();
            foreach (array($existing_products, $new_products) as $product_groups) {
                if (!is_array($product_groups)) {
                    continue;
                }
                foreach ($product_groups as $product) {
                    if (!is_array($product)) {
                        continue;
                    }
                    $product_key = isset($product['id']) ? (string) $product['id'] : md5(wp_json_encode($product));
                    $merged_products[$product_key] = $product;
                }
            }
            return array_values($merged_products);
        }

        public function ensure_export_dir() {
            $export_dir = $this->get_export_dir();
            $fs = $this->filesystem();
            if ($fs && !$fs->is_dir($export_dir)) {
                $fs->mkdir($export_dir);
            }
            $index_file = $export_dir . 'index.php';
            if ($fs && !$fs->exists($index_file)) {
                $fs->put_contents($index_file, "<?php\nheader(\"HTTP/1.1 403 Forbidden\");\nexit;\n");
            }
            $htaccess_file = $export_dir . '.htaccess';
            if ($fs && !$fs->exists($htaccess_file)) {
                $fs->put_contents($htaccess_file, "Options -Indexes\n<Files \"*.json\">\n    Order allow,deny\n    Deny from all\n</Files>");
            }
            return true;
        }
        
        public function export_product_data($product_data, $source_file = '', $import_session_id = '') {
            $this->ensure_export_dir();
            
            $export_dir = $this->get_export_dir();
            $import_id = !empty($import_session_id) ? $import_session_id : wp_generate_uuid4();
            $existing_file = $this->get_export_file_by_import_id($import_id);
            $existing_products = array();
            if ($existing_file) {
                $filename = basename($existing_file);
                $filepath = $existing_file;
                $fs = $this->filesystem();
                $existing_content = $fs ? $fs->get_contents($existing_file) : '';
                $existing_data = json_decode($existing_content, true);
                if (is_array($existing_data) && isset($existing_data['products']) && is_array($existing_data['products'])) {
                    $existing_products = $existing_data['products'];
                }
            } else {
                $timestamp = current_time('Y-m-d_H-i-s');
                $filename = sprintf('import_%s_%s.json', $timestamp, substr($import_id, 0, 8));
                $filepath = $export_dir . $filename;
            }
            $merged_products = $this->merge_export_products($existing_products, $product_data);
            $manifest = array(
                'import_id' => $import_id,
                'export_date' => current_time('mysql'),
                'source_file' => basename($source_file),
                'total_products' => count($merged_products),
                'version' => SPBWC_PB_VERSION,
                'site_url' => get_site_url()
            );
            $export_data = array(
                'manifest' => $manifest,
                'products' => $merged_products
            );
            
            $json_data = wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if (!is_string($json_data) || '' === $json_data) {
                return array('success' => false, 'error' => __('Failed to encode export data', 'storelly-product-builder-for-woocommerce'));
            }
            
            $fs = $this->filesystem();
            $written = $fs ? $fs->put_contents($filepath, $json_data) : false;
            if ($written) {
                $this->cleanup_old_exports();
                return array(
                    'success' => true,
                    'filename' => $filename,
                    'import_id' => $import_id,
                    'filepath' => $filepath
                );
            }
            
            return array('success' => false, 'error' => __('Failed to write export file', 'storelly-product-builder-for-woocommerce'));
        }
        
        public function cleanup_old_exports() {
            $export_dir = $this->get_export_dir();
            $files = glob($export_dir . 'import_*.json');
            
            if (count($files) > self::MAX_FILES) {
                // Sort files by modification time (oldest first)
                usort($files, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                
                $files_to_delete = array_slice($files, 0, count($files) - self::MAX_FILES);
                
                $fs = $this->filesystem();
                foreach ($files_to_delete as $file) {
                    if ($fs && $fs->exists($file)) {
                        $fs->delete($file);
                    }
                }
            }
        }
        
        public function get_export_files() {
            $this->ensure_export_dir();
            $export_dir = $this->get_export_dir();
            $files = glob($export_dir . 'import_*.json');
            
            $export_files = array();
            
            foreach ($files as $file) {
                $filename = basename($file);
                $fs = $this->filesystem();
                $file_data = $fs ? $fs->get_contents($file) : '';
                $json_data = json_decode($file_data, true);
                
                if ($json_data && isset($json_data['manifest'])) {
                    $export_files[] = array(
                        'filename' => $filename,
                        'filepath' => $file,
                        'filesize' => size_format(filesize($file)),
                        'modified' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($file)),
                        'manifest' => $json_data['manifest'],
                        'product_count' => count($json_data['products'])
                    );
                }
            }
            
            // Sort by modification time (newest first)
            usort($export_files, function($a, $b) {
                return filemtime($b['filepath']) - filemtime($a['filepath']);
            });
            
            return $export_files;
        }
        
        public function delete_export_file($filename) {
            $export_dir = $this->get_export_dir();
            $filepath = $export_dir . sanitize_file_name($filename);
            
            $fs = $this->filesystem();
            if ($fs && $fs->exists($filepath)) {
                return $fs->delete($filepath);
            }
            
            return false;
        }
        
        public function get_export_file_content($filename) {
            $export_dir = $this->get_export_dir();
            $filepath = $export_dir . sanitize_file_name($filename);
            
            $fs = $this->filesystem();
            if ($fs && $fs->exists($filepath)) {
                return $fs->get_contents($filepath);
            }
            
            return false;
        }
        
        public function register_rest_routes() {
            register_rest_route('storelly/v1', '/stage-import', array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_stage_import'),
                'permission_callback' => array($this, 'check_stage_import_permissions'),
                'args' => array(
                    'import_id' => array(
                        'required' => true,
                        'validate_callback' => array($this, 'validate_import_id')
                    ),
                    'target_url' => array(
                        'required' => true,
                        'validate_callback' => 'esc_url_raw'
                    ),
                    'consumer_key' => array(
                        'required' => true,
                        'validate_callback' => 'sanitize_text_field'
                    ),
                    'consumer_secret' => array(
                        'required' => true,
                        'validate_callback' => 'sanitize_text_field'
                    )
                )
            ));
        }
        
        public function check_stage_import_permissions($request) {
            return current_user_can('manage_woocommerce');
        }
        
        public function validate_import_id($param) {
            return !empty($param) && is_string($param);
        }
        
        public function handle_stage_import($request) {
            $import_id = $request->get_param('import_id');
            $target_url = $request->get_param('target_url');
            $consumer_key = $request->get_param('consumer_key');
            $consumer_secret = $request->get_param('consumer_secret');
            
            // Find the export file by import_id
            $export_files = $this->get_export_files();
            $target_file = null;
            
            foreach ($export_files as $file) {
                if ($file['manifest']['import_id'] === $import_id) {
                    $target_file = $file;
                    break;
                }
            }
            
            if (!$target_file) {
                return new WP_Error('import_not_found', __('Import session not found', 'storelly-product-builder-for-woocommerce'), array('status' => 404));
            }
            
            $file_content = $this->get_export_file_content($target_file['filename']);
            
            if (!$file_content) {
                return new WP_Error('file_read_error', __('Failed to read export file', 'storelly-product-builder-for-woocommerce'), array('status' => 500));
            }

            $import_data = json_decode($file_content, true);
            $products = isset($import_data['products']) && is_array($import_data['products']) ? $import_data['products'] : array();
            $auth = array(
                'Authorization' => 'Basic ' . base64_encode($consumer_key . ':' . $consumer_secret),
                'Content-Type' => 'application/json'
            );
            $summary = array('created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => array());
            foreach ($products as $product) {
                $payload = $this->map_remote_payload($product, $target_url, $auth);
                $remote_id = $this->remote_find_product_by_sku($target_url, $auth, $product['sku'] ?? '');
                $url = trailingslashit($target_url) . 'wp-json/wc/v3/products';
                $method = 'POST';
                if ($remote_id) {
                    $url = $url . '/' . $remote_id;
                    $method = 'PUT';
                }
                $response = wp_remote_request($url, array(
                    'method' => $method,
                    'headers' => $auth,
                    'body' => wp_json_encode($payload),
                    'timeout' => 45
                ));
                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 400) {
                    $summary['failed']++;
                    $summary['errors'][] = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
                } else {
                    if ($remote_id) {
                        $summary['updated']++;
                    } else {
                        $summary['created']++;
                    }
                }
            }

            return array(
                'success' => true,
                'message' => __('Import data staged successfully', 'storelly-product-builder-for-woocommerce'),
                'import_id' => $import_id,
                'products_count' => $target_file['product_count'],
                'target_url' => $target_url,
                'summary' => $summary
            );
        }

        private function remote_find_product_by_sku($target_url, $auth, $sku) {
            if (!$sku) return 0;
            $url = trailingslashit($target_url) . 'wp-json/wc/v3/products?sku=' . rawurlencode($sku);
            $response = wp_remote_get($url, array('headers' => $auth, 'timeout' => 30));
            if (is_wp_error($response)) return 0;
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($body) && !empty($body[0]['id'])) {
                return (int)$body[0]['id'];
            }
            return 0;
        }

        private function map_remote_payload($product, $target_url, $auth) {
            $payload = array(
                'name' => $product['name'] ?? '',
                'type' => $product['type'] ?? 'simple',
                'status' => $product['status'] ?? 'publish',
                'sku' => $product['sku'] ?? '',
                'regular_price' => $product['regular_price'] ?? '',
                'sale_price' => $product['sale_price'] ?? '',
                'description' => $product['description'] ?? '',
                'short_description' => $product['short_description'] ?? '',
                'stock_status' => $product['stock_status'] ?? 'instock',
                'stock_quantity' => $product['stock_quantity'] ?? null
            );
            $payload['images'] = array();
            if (!empty($product['images']['featured'])) {
                $payload['images'][] = array('src' => $product['images']['featured']);
            }
            if (!empty($product['images']['gallery']) && is_array($product['images']['gallery'])) {
                foreach ($product['images']['gallery'] as $img) {
                    $payload['images'][] = array('src' => $img);
                }
            }
            $payload['categories'] = $this->map_remote_terms($target_url, $auth, $product['categories'] ?? array(), 'products/categories');
            $payload['tags'] = $this->map_remote_terms($target_url, $auth, $product['tags'] ?? array(), 'products/tags');
            $payload['attributes'] = $product['attributes'] ?? array();
            if (!empty($product['variations'])) {
                $payload['variations'] = $product['variations'];
            }
            return $payload;
        }

        private function map_remote_terms($target_url, $auth, $names, $endpoint) {
            $terms = array();
            if (!is_array($names)) return $terms;
            foreach ($names as $name) {
                if (!$name) continue;
                $id = $this->remote_get_or_create_term($target_url, $auth, $endpoint, $name);
                if ($id) {
                    $terms[] = array('id' => $id);
                }
            }
            return $terms;
        }

        private function remote_get_or_create_term($target_url, $auth, $endpoint, $name) {
            $search_url = trailingslashit($target_url) . 'wp-json/wc/v3/' . $endpoint . '?search=' . rawurlencode($name);
            $res = wp_remote_get($search_url, array('headers' => $auth, 'timeout' => 30));
            if (!is_wp_error($res)) {
                $body = json_decode(wp_remote_retrieve_body($res), true);
                if (is_array($body) && !empty($body[0]['id'])) {
                    return (int)$body[0]['id'];
                }
            }
            $create_url = trailingslashit($target_url) . 'wp-json/wc/v3/' . $endpoint;
            $res = wp_remote_request($create_url, array(
                'method' => 'POST',
                'headers' => $auth,
                'body' => wp_json_encode(array('name' => $name)),
                'timeout' => 30
            ));
            if (is_wp_error($res)) return 0;
            $body = json_decode(wp_remote_retrieve_body($res), true);
            return isset($body['id']) ? (int)$body['id'] : 0;
        }
        
        public function handle_export_actions() {
            if (!isset($_GET['spbwc_export_action']) || !current_user_can('manage_woocommerce')) {
                return;
            }
            
            $action = sanitize_text_field($_GET['spbwc_export_action']);
            $filename = isset($_GET['filename']) ? sanitize_file_name($_GET['filename']) : '';
            
            switch ($action) {
                case 'download':
                    $this->download_export_file($filename);
                    break;
                case 'delete':
                    $this->delete_export_file($filename);
                    wp_redirect(remove_query_arg(array('spbwc_export_action', 'filename')));
                    exit;
                    break;
            }
        }
        
        public function download_export_file($filename) {
            $filepath = $this->get_export_dir() . $filename;
            
            $fs = $this->filesystem();
            if ($fs && $fs->exists($filepath)) {
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($filepath));
                echo $fs->get_contents($filepath); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw JSON file download, escaping would corrupt the attachment
                exit;
            }

            wp_die(esc_html__('File not found', 'storelly-product-builder-for-woocommerce'));
        }
    }
}

// Initialize the global import functionality
function spbwc_global_import_init() {
    $global_import = SPBWC_Global_Import::instance();
    $global_import->init();
    return $global_import;
}
add_action('init', 'spbwc_global_import_init');
