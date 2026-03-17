<?php
if (!defined('ABSPATH')) {
    exit;
}

class SPBWC_Printcart_Import_Adapter {
    private function filesystem() {
        global $wp_filesystem;
        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        return $wp_filesystem;
    }

    public function file_get_contents_remote($url) {
        $response = wp_remote_get($url);
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            if ($body !== '') {
                return $body;
            }
        }
        return '';
    }

    public function add_attachment_from_url($file) {
        $filename = basename(parse_url($file, PHP_URL_PATH));
        $response = wp_remote_get($file);
        if (is_wp_error($response)) {
            return 0;
        }
        $contents = wp_remote_retrieve_body($response);
        if ($contents === '') {
            return 0;
        }
        $upload_file = wp_upload_bits($filename, null, $contents);
        if (!empty($upload_file['error'])) {
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
            return 0;
        }
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_file['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        return $attachment_id;
    }

    public function download_remote_file($url, $path) {
        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return false;
        }
        $data = wp_remote_retrieve_body($response);
        if ($data === '') {
            return false;
        }
        $fs = $this->filesystem();
        return $fs ? $fs->put_contents($path, $data) : false;
    }

    public function add_product($data) {
        $product = new WC_Product();
        $product->set_name($data['name']);
        $product->set_description($data['description']);
        $product->set_regular_price($data['regular_price']);
        $product->set_sale_price($data['sale_price']);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_stock_status('instock');
        if (!empty($data['image'])) {
            $media_id = $this->add_attachment_from_url($data['image']);
            if ($media_id) {
                $product->set_image_id($media_id);
            }
        }
        $product_id = $product->save();
        update_post_meta($product_id, '_nbdesigner_enable', $data['enable_design']);
        update_post_meta($product_id, '_nbdesigner_enable_upload', $data['enable_upload']);
        update_post_meta($product_id, '_nbdesigner_enable_upload_without_design', $data['upload_without_design']);
        update_post_meta($product_id, '_nbo_enable', $data['nbo_enable']);
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
            foreach ($product_config as $key => $_config) {
                $im_id = $this->add_attachment_from_url($_config['img_src']);
                $product_config[$key]['img_src'] = $im_id ? $im_id : $default_bg_id;
                $ov_id = $this->add_attachment_from_url($_config['img_overlay']);
                $product_config[$key]['img_overlay'] = $ov_id ? $ov_id : $default_ov_id;
            }
            $setting_design = serialize($product_config);
            update_post_meta($product_id, '_designer_setting', $setting_design);
        }
        return $product_id;
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
                        $attachment_id = $this->add_attachment_from_url($item);
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
                            $attachment_id = $this->add_attachment_from_url($url);
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

        $is_exported_option = isset($data['fields']) && is_array($data['fields']);
        if ($is_exported_option) {
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
            $temp_name = substr(md5(uniqid()), 0, 5) . rand(1, 100) . time();
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
