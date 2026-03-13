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

    public function create_or_update_print_option($new_product_id, $data) {
        if (!is_array($data)) {
            return;
        }
        $media_objects_raw = isset($data['media_objects']) ? $data['media_objects'] : '';
        $media_objects = maybe_unserialize($media_objects_raw);
        if (is_array($media_objects) && count($media_objects) > 0) {
            $option_fields_raw = isset($data['fields']) ? $data['fields'] : '';
            $option_fields = maybe_unserialize($option_fields_raw);
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
            $data['fields'] = serialize($option_fields);
        }
        $this->save_print_option($new_product_id, $data);
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
        $wpdb->insert(
            $table_name,
            array(
                'title'       => $title,
                'published'   => isset($data['published']) ? (int) $data['published'] : 1,
                'product_ids' => serialize(array($new_product_id)),
                'created'     => $now,
                'modified'    => $now,
                'created_by'  => $current_user_id,
                'modified_by' => $current_user_id,
                'fields'      => isset($data['fields']) ? $data['fields'] : '',
                'builder'     => isset($data['builder']) ? $data['builder'] : '',
            ),
            array('%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
        );
        $option_id = $wpdb->insert_id;
        if ($option_id) {
            delete_transient('spbwc_product_builder_' . $new_product_id);
            set_transient('spbwc_product_builder_' . $new_product_id, $option_id);
            update_post_meta($new_product_id, '_spbwc_option_id', $option_id);
            update_post_meta($new_product_id, '_storelly_pb_enable', 1);
        }
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
