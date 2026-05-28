<?php
if (!defined('ABSPATH')) {
    exit;
}

class SPBWC_Printcart_Import_Adapter {
    private $media_cache = array();
    private $logger = null;

    public function set_logger($callback) {
        $this->logger = $callback;
    }

    protected function log($message) {
        if ($this->logger && is_callable($this->logger)) {
            call_user_func($this->logger, $message);
        }
    }

    /**
     * Reduce a remote URL to just its file name for log output, so the original source host/path
     * is never exposed to merchants watching the import log.
     *
     * @param string $url Remote URL.
     * @return string File name, or 'file' when none can be derived.
     */
    private function safe_file_label($url) {
        $path = wp_parse_url((string) $url, PHP_URL_PATH);
        $name = $path ? basename($path) : '';
        return '' !== $name ? $name : 'file';
    }


    private function filesystem() {

        global $wp_filesystem;
        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        return $wp_filesystem;
    }

    /**
     * Fetch a remote URL and report exactly why it failed.
     *
     * Detects responses that were cut off mid-transfer (PHP/network limits) by comparing the
     * declared Content-Length against the received body length, and optionally validates JSON.
     *
     * @param string $url         Remote URL.
     * @param bool   $expect_json Validate the body as JSON when true.
     * @param int    $timeout     Request timeout in seconds.
     * @return array{ok:bool, body:string, reason:string, http_code:int, message:string, bytes:int, expected:int}
     */
    public function fetch_remote($url, $expect_json = false, $timeout = 30) {
        $result = array(
            'ok'        => false,
            'body'      => '',
            'reason'    => '',
            'http_code' => 0,
            'message'   => '',
            'bytes'     => 0,
            'expected'  => 0,
        );
        if (empty($url) || !is_string($url)) {
            $result['reason']  = 'empty_url';
            $result['message'] = __('No URL provided', 'storelly-product-builder-for-woocommerce');
            return $result;
        }
        $response = wp_remote_get($url, array('timeout' => (int) $timeout));
        if (is_wp_error($response)) {
            $result['reason']  = 'wp_error';
            $result['message'] = $response->get_error_message();
            return $result;
        }
        $code               = (int) wp_remote_retrieve_response_code($response);
        $result['http_code'] = $code;
        if ($code < 200 || $code >= 300) {
            $result['reason']  = 'http_error';
            /* translators: %d: HTTP status code returned by the remote server */
            $result['message'] = sprintf(__('Remote server returned HTTP %d', 'storelly-product-builder-for-woocommerce'), $code);
            return $result;
        }
        $body            = wp_remote_retrieve_body($response);
        $result['bytes'] = strlen($body);
        if ('' === $body) {
            $result['reason']  = 'empty';
            $result['message'] = __('Empty response body', 'storelly-product-builder-for-woocommerce');
            return $result;
        }
        $declared = wp_remote_retrieve_header($response, 'content-length');
        if ('' !== $declared && is_numeric($declared)) {
            $result['expected'] = (int) $declared;
            // Body shorter than the declared size means the transfer was cut off. (Compressed
            // responses only ever make the decoded body larger, so this never false-positives.)
            if (strlen($body) < (int) $declared) {
                $result['reason']  = 'truncated';
                $result['message'] = sprintf(
                    /* translators: 1: bytes received, 2: total bytes expected */
                    __('Download was cut off: received %1$d of %2$d bytes', 'storelly-product-builder-for-woocommerce'),
                    strlen($body),
                    (int) $declared
                );
                $result['body'] = $body;
                return $result;
            }
        }
        if ($expect_json) {
            json_decode($body);
            if (JSON_ERROR_NONE !== json_last_error()) {
                $result['reason']  = 'invalid_json';
                $result['message'] = sprintf(
                    /* translators: %s: JSON parser error message */
                    __('Invalid or incomplete JSON (%s)', 'storelly-product-builder-for-woocommerce'),
                    json_last_error_msg()
                );
                $result['body'] = $body;
                return $result;
            }
        }
        $result['ok']   = true;
        $result['body'] = $body;
        return $result;
    }

    public function file_get_contents_remote($url) {
        $res = $this->fetch_remote($url);
        return $res['ok'] ? $res['body'] : '';
    }

    public function add_attachment_from_url($file) {
        if (empty($file) || !is_string($file)) {
            return 0;
        }

        // Check if it's already a local ID
        if (is_numeric($file) && (int)$file > 0) {
            return (int)$file;
        }

        $file = trim($file);
        
        // Handle local URLs efficiently
        $site_url = get_site_url();
        if (strpos($file, $site_url) !== false) {
            $path = str_replace($site_url, '', $file);
            $attachment_id = attachment_url_to_postid($file);
            if ($attachment_id) {
                return $attachment_id;
            }
        }

        $filename = basename(wp_parse_url($file, PHP_URL_PATH));
        if (empty($filename)) {
            $filename = 'image-' . time() . '.jpg';
        }

        $this->log('Downloading image: ' . $this->safe_file_label($file));
        $response = wp_remote_get($file, array('timeout' => 30));
        if (is_wp_error($response)) {
            $this->log('Download failed: ' . $response->get_error_message());
            return 0;
        }
        $http_code = (int) wp_remote_retrieve_response_code($response);
        if ($http_code < 200 || $http_code >= 300) {
            $this->log('Download failed: HTTP ' . $http_code);
            return 0;
        }
        $contents = wp_remote_retrieve_body($response);
        if ($contents === '') {
            $this->log('Download failed: Empty body');
            return 0;
        }
        $declared = wp_remote_retrieve_header($response, 'content-length');
        if ('' !== $declared && is_numeric($declared) && strlen($contents) < (int) $declared) {
            $this->log('Download failed: cut off (' . strlen($contents) . '/' . (int) $declared . ' bytes)');
            return 0;
        }
        $upload_file = wp_upload_bits($filename, null, $contents);
        if (!empty($upload_file['error'])) {
            $this->log('Failed to save image to uploads: ' . $upload_file['error']);
            return 0;
        }
        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attachment_id = wp_insert_attachment($attachment, $upload_file['file']);
        if (is_wp_error($attachment_id)) {
            $this->log('Failed to insert attachment: ' . $attachment_id->get_error_message());
            return 0;
        }
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_file['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        $this->log('Successfully imported image. ID: ' . $attachment_id);
        return $attachment_id;
    }



    public function download_remote_file($url, $path) {
        $response = wp_remote_get($url, array('timeout' => 30));
        if (is_wp_error($response)) {
            return false;
        }
        $http_code = (int) wp_remote_retrieve_response_code($response);
        if ($http_code < 200 || $http_code >= 300) {
            return false;
        }
        $data = wp_remote_retrieve_body($response);
        if ($data === '') {
            return false;
        }
        $declared = wp_remote_retrieve_header($response, 'content-length');
        if ('' !== $declared && is_numeric($declared) && strlen($data) < (int) $declared) {
            return false;
        }
        $fs = $this->filesystem();
        return $fs ? $fs->put_contents($path, $data) : false;
    }

    public function add_product($data) {
        $product = new WC_Product();
        $this->apply_product_data($product, $data);
        $product_id = $product->save();
        $this->apply_product_meta($product_id, $data);
        return $product_id;
    }

    /**
     * Overwrite an existing product in place from a decoded settings payload.
     *
     * Used by the re-import flow: keeps the same product ID (so existing WooCommerce orders and
     * links stay valid) while replacing its configuration, media and design settings.
     *
     * @param int   $product_id Existing WooCommerce product ID.
     * @param array $data       Decoded settings payload.
     * @return int Product ID (creates a new product if the ID no longer exists).
     */
    public function update_product($product_id, $data) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return $this->add_product($data);
        }
        $this->apply_product_data($product, $data);
        $product->save();
        $this->apply_product_meta($product_id, $data);
        return $product_id;
    }

    private function apply_product_data($product, $data) {
        if (isset($data['name'])) {
            $product->set_name($data['name']);
        }
        if (isset($data['description'])) {
            $product->set_description($data['description']);
        }
        if (isset($data['regular_price'])) {
            $product->set_regular_price($data['regular_price']);
        }
        if (isset($data['sale_price'])) {
            $product->set_sale_price($data['sale_price']);
        }
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_stock_status('instock');
        if (!empty($data['image'])) {
            $media_id = $this->add_attachment_from_url($data['image']);
            if ($media_id) {
                $product->set_image_id($media_id);
            }
        }
    }

    private function apply_product_meta($product_id, $data) {
        update_post_meta($product_id, '_nbdesigner_enable', $data['enable_design'] ?? '');
        update_post_meta($product_id, '_nbdesigner_enable_upload', $data['enable_upload'] ?? '');
        update_post_meta($product_id, '_nbdesigner_enable_upload_without_design', $data['upload_without_design'] ?? '');
        update_post_meta($product_id, '_nbo_enable', $data['nbo_enable'] ?? '');
        if (!empty($data['setting_upload'])) {
            update_post_meta($product_id, '_nbdesigner_upload', $data['setting_upload']);
        }
        if (!empty($data['option'])) {
            update_post_meta($product_id, '_nbdesigner_option', $data['option']);
        }
        if (!empty($data['setting_design'])) {
            $product_config = maybe_unserialize($data['setting_design']);
            $default_bg_id = get_option('nbdesigner_default_background');
            $default_ov_id = get_option('nbdesigner_default_overlay');
            if (is_array($product_config)) {
                foreach ($product_config as $key => $_config) {
                    $im_id = $this->add_attachment_from_url($_config['img_src']);
                    $product_config[$key]['img_src'] = $im_id ? $im_id : $default_bg_id;
                    $ov_id = $this->add_attachment_from_url($_config['img_overlay']);
                    $product_config[$key]['img_overlay'] = $ov_id ? $ov_id : $default_ov_id;
                }
            }
            update_post_meta($product_id, '_designer_setting', serialize($product_config)); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- legacy Storelly designer setting format stored as serialized string.
        }
    }

    /**
     * Remove print options that belong solely to a product (used before a re-import overwrite so
     * options are not duplicated).
     *
     * @param int $product_id WooCommerce product ID.
     * @return void
     */
    public function delete_print_options_for_product($product_id) {
        global $wpdb;
        $product_id = (int) $product_id;
        if ($product_id <= 0) {
            return;
        }
        $table  = $wpdb->prefix . 'storelly_product_builder_options';
        $needle = '%' . $wpdb->esc_like(serialize(array($product_id))) . '%'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- matches the exact serialized linkage written by save_print_option().
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- custom options table; clearing rows before re-import.
        $wpdb->query($wpdb->prepare("DELETE FROM `{$table}` WHERE product_ids LIKE %s", $needle));
        delete_post_meta($product_id, '_spbwc_option_id');
        delete_transient('spbwc_product_builder_' . $product_id);
    }

    private function decode_payload_to_array($value) {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return (array) $value;
        }
        $value = is_string($value) ? trim($value) : '';
        if ('' === $value) {
            return array();
        }
        $unserialized = maybe_unserialize($value);
        if (is_array($unserialized)) {
            return $unserialized;
        }
        if (is_object($unserialized)) {
            return (array) $unserialized;
        }
        $json = json_decode($value, true);
        if (is_array($json)) {
            return $json;
        }
        if (is_string($json)) {
            $json_unserialized = maybe_unserialize($json);
            if (is_array($json_unserialized)) {
                return $json_unserialized;
            }
            if (is_object($json_unserialized)) {
                return (array) $json_unserialized;
            }
        }
        return array();
    }

    private function normalize_exported_print_option($data, $new_product_id) {
        if (!is_array($data)) {
            return array();
        }
        $normalized = $data;
        $normalized['id'] = '0';
        $normalized['product_ids'] = array((string) $new_product_id);
        if (!isset($normalized['fields']) || !is_array($normalized['fields'])) {
            $normalized['fields'] = array();
        }
        return $normalized;
    }

    private function import_exported_print_option_media($value) {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && substr($key, -4) === '_url') {
                    $media_key = substr($key, 0, -4);
                    if (is_string($item) && '' !== trim($item)) {
                        if (isset($this->media_cache[$item])) {
                            $attachment_id = $this->media_cache[$item];
                            $this->log('Key "' . $key . '": using cached attachment ID ' . $attachment_id);
                        } else {
                            $this->log('Processing key: ' . $key);
                            $attachment_id = $this->add_attachment_from_url($item);
                            $this->media_cache[$item] = $attachment_id;
                        }
                        
                        if ($attachment_id) {
                            $value[$media_key] = $attachment_id;
                        }
                    } elseif (is_array($item)) {
                        $media_values = array();
                        foreach ($item as $index => $url) {
                            if (!is_string($url) || '' === trim($url)) {
                                $media_values[$index] = 0;
                                continue;
                            }
                            
                            if (isset($this->media_cache[$url])) {
                                $attachment_id = $this->media_cache[$url];
                            } else {
                                $attachment_id = $this->add_attachment_from_url($url);
                                $this->media_cache[$url] = $attachment_id;
                            }
                            
                            $media_values[$index] = $attachment_id ? $attachment_id : 0;
                        }
                        $value[$media_key] = $media_values;
                    }
                } else {
                    $value[$key] = $this->import_exported_print_option_media($item);
                }
            }
        }
        return $value;
    }


    private function is_field_config_object($value) {
        return is_array($value)
            && array_key_exists('value', $value)
            && array_key_exists('type', $value)
            && array_key_exists('title', $value);
    }

    private function collapse_field_config_objects($value) {
        if ($this->is_field_config_object($value)) {
            return $value['value'];
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->collapse_field_config_objects($v);
            }
        }
        return $value;
    }

    private function collapse_exported_print_option_to_raw($option) {
        if (!is_array($option) || !isset($option['fields']) || !is_array($option['fields'])) {
            return $option;
        }
        foreach ($option['fields'] as $idx => $field) {
            if (!is_array($field)) {
                continue;
            }
            if (isset($field['general']) && is_array($field['general'])) {
                $field['general'] = $this->collapse_field_config_objects($field['general']);
            }
            if (isset($field['appearance']) && is_array($field['appearance'])) {
                $field['appearance'] = $this->collapse_field_config_objects($field['appearance']);
            }
            $option['fields'][$idx] = $field;
        }
        return $option;
    }

    private function is_legacy_db_row($data) {
        return is_array($data)
            && isset($data['fields'])
            && is_string($data['fields'])
            && isset($data['created'])
            && isset($data['modified']);
    }

    private function is_fields_array($data) {
        return is_array($data) && isset($data[0]) && isset($data[0]['id']) && isset($data[0]['general']);
    }


    private function deep_unserialize($value) {
        if (is_string($value)) {
            $unserialized = maybe_unserialize($value);
            if ($unserialized !== $value) {
                return $this->deep_unserialize($unserialized);
            }
            return $value;
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->deep_unserialize($v);
            }
        }
        return $value;
    }

    public function create_or_update_print_option($new_product_id, $data) {
        if ($new_product_id <= 0) {
            return array(
                'success' => false,
                'option_id' => 0,
                'message' => __('Invalid product ID', 'storelly-product-builder-for-woocommerce'),
            );
        }
        if (!is_array($data)) {
            $data = $this->decode_payload_to_array($data);
        }
        if (empty($data)) {
            return array(
                'success' => false,
                'option_id' => 0,
                'message' => __('Print option payload is empty or invalid', 'storelly-product-builder-for-woocommerce'),
            );
        }

        if ($this->is_legacy_db_row($data)) {
            $fields_raw = $this->deep_unserialize($data['fields']);
            if (!is_array($fields_raw)) {
                $fields_raw = array();
            }
            $save_data = $fields_raw;
            $save_data['product_ids'] = array((string) $new_product_id);
            return $this->save_print_option($new_product_id, $save_data);
        }

        $is_fields_array = $this->is_fields_array($data);
        $is_exported_option = isset($data['fields']) && is_array($data['fields']);
        
        if ($is_fields_array || $is_exported_option) {
            if ($is_fields_array) {
                $data = array(
                    'title' => '',
                    'fields' => $data,
                    'published' => 1
                );
            }
            $data = $this->normalize_exported_print_option($data, $new_product_id);
            $data = $this->import_exported_print_option_media($data);
            $data = $this->collapse_exported_print_option_to_raw($data);
            return $this->save_print_option($new_product_id, $data);
        }


        $media_objects_raw = isset($data['media_objects']) ? $data['media_objects'] : '';
        $media_objects = $this->decode_payload_to_array($media_objects_raw);
        if (is_array($media_objects) && count($media_objects) > 0) {
            $option_fields_raw = isset($data['fields']) ? $data['fields'] : '';
            $option_fields = $this->decode_payload_to_array($option_fields_raw);
            if (!is_array($option_fields)) {
                $option_fields = array();
            }
            foreach ($media_objects as $key => $media) {
                $key_arr = explode('-', $key);
                $url = $media;
                $uploaded_id = $this->add_attachment_from_url($url);
                $reference = &$option_fields;
                foreach ($key_arr as $k) {
                    if (!is_array($reference) || !array_key_exists($k, $reference)) {
                        if (!is_array($reference)) {
                            $reference = array();
                        }
                        $reference[$k] = array();
                    }
                    $reference = &$reference[$k];
                }
                $reference = $uploaded_id;
                unset($reference);
            }
            $data['fields'] = $option_fields;
        }
        if (isset($data['fields']) && !is_array($data['fields'])) {
            $decoded_fields = $this->deep_unserialize($data['fields']);
            $data['fields'] = is_array($decoded_fields) ? $decoded_fields : array();
        }
        if (isset($data['builder']) && is_array($data['builder'])) {
            $data['builder'] = wp_json_encode($data['builder']);
        }
        return $this->save_print_option($new_product_id, $data);
    }

    public function save_print_option($new_product_id, $data) {
        global $wpdb;
        $date = new DateTime();
        $current_user_id = wp_get_current_user()->ID;
        $now = $date->format('Y-m-d H:i:s');
        $table_name = $wpdb->prefix . 'storelly_product_builder_options';
        $product = wc_get_product($new_product_id);
        $product_name = $product ? $product->get_name() : '';
        $title = isset($data['title']) && !empty($data['title'])
            ? $data['title']
            : sprintf(
                // translators: %s is the product name.
                __('Printing Options for %s', 'storelly-product-builder-for-woocommerce'),
                $product_name
            );
        $fields_payload = $data;
        if (
            isset($data['fields']) && is_array($data['fields'])
            && isset($data['fields']['fields']) && is_array($data['fields']['fields'])
        ) {
            $fields_payload = $data['fields'];
        }
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'title'       => $title,
                'published'   => isset($data['published']) ? (int) $data['published'] : 1,
                'product_ids' => serialize(array($new_product_id)),
                'created'     => $now,
                'modified'    => $now,
                'created_by'  => $current_user_id,
                'modified_by' => $current_user_id,
                'fields'      => serialize($fields_payload),
                'builder'     => isset($data['builder']) ? $data['builder'] : '',
            ),
            array('%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
        );
        if (false === $inserted) {
            return array(
                'success' => false,
                'option_id' => 0,
                'message' => '' !== $wpdb->last_error ? $wpdb->last_error : __('Failed to insert print option', 'storelly-product-builder-for-woocommerce'),
            );
        }
        $option_id = $wpdb->insert_id;
        if ($option_id) {
            delete_transient('spbwc_product_builder_' . $new_product_id);
            set_transient('spbwc_product_builder_' . $new_product_id, $option_id);
            update_post_meta($new_product_id, '_spbwc_option_id', $option_id);
            update_post_meta($new_product_id, '_storelly_pb_enable', 1);
        }
        return array(
            'success' => true,
            'option_id' => (int) $option_id,
            'message' => '',
        );
    }

    public function add_templates($templates, $product_id, $new_product_id) {
        global $wpdb;
        if (!extension_loaded('zip')) {
            return false;
        }
        foreach ($templates as $tem) {
            $temp_name = substr(md5(uniqid()), 0, 5) . wp_rand(1, 100) . time();
            $import_dir = SPBWC_PB_DATA_DIR . '/import/' . $product_id . '/';
            if (!file_exists($import_dir)) {
                wp_mkdir_p($import_dir);
            }
            $temp_path = $import_dir . $tem['folder'] . '.zip';
            $temp_dir = SPBWC_PB_CUSTOMER_DIR . '/' . $temp_name;
            $this->download_remote_file($tem['temp_url'], $temp_path);
            $zip = new ZipArchive();
            if (!$zip->open($temp_path, ZIPARCHIVE::CREATE)) {
                return false;
            }
            $zip->extractTo($temp_dir);
            $zip->close();
            unset($tem['temp_url']);
            $tem['product_id'] = $new_product_id;
            $tem['variation_id'] = 0;
            $tem['folder'] = $temp_name;
            $user_id = wp_get_current_user()->ID;
            $tem['user_id'] = $user_id;
            $date = new DateTime();
            $tem['created_date'] = $date->format('Y-m-d H:i:s');
            $wpdb->insert("{$wpdb->prefix}nbdesigner_templates", $tem);
        }
        return true;
    }
}
