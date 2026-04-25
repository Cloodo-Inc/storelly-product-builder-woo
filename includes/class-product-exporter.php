<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPBWC_Product_Exporter')) {
    class SPBWC_Product_Exporter {
        protected static $instance;

        public static function instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function init() {
            add_action('wp_ajax_spbwc_export_product_reference', array($this, 'handle_ajax_export'));
            add_action('admin_footer', array($this, 'enqueue_scripts'));
        }

        public function enqueue_scripts() {
            $screen = get_current_screen();
            if (!$screen || $screen->id !== 'product-builder-options_page_' . SPBWC_PB_PRODUCTS_SLUG) {
                return;
            }
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('.spbwc-export-ref').on('click', function(e) {
                        e.preventDefault();
                        var btn = $(this);
                        var product_id = btn.data('id');
                        
                        if (!confirm('<?php _e('Do you want to export and download reference data for this product?', 'storelly-product-builder-for-woocommerce'); ?>')) {
                            return;
                        }

                        btn.prop('disabled', true).find('.dashicons').removeClass('dashicons-download').addClass('dashicons-update spin');

                        $.post(ajaxurl, {
                            action: 'spbwc_export_product_reference',
                            nonce: '<?php echo wp_create_nonce('spbwc_export_product_nonce'); ?>',
                            product_id: product_id
                        }, function(res) {
                            btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-download');
                            if (res.success) {
                                // Trigger download
                                window.location.href = res.data.url;
                            } else {
                                alert('Error: ' + res.data);
                            }
                        }).fail(function() {
                            btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-download');
                            alert('Request failed');
                        });
                    });
                });
            </script>
            <style>
                .spin {
                    animation: spbwc-spin 2s infinite linear;
                }
                @keyframes spbwc-spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(359deg); }
                }
            </style>
            <?php
        }

        public function export_product($product_id) {
            global $wpdb;
            $product = wc_get_product($product_id);
            if (!$product) {
                return new WP_Error('invalid_product', __('Product not found.', 'storelly-product-builder-for-woocommerce'));
            }

            $upload_dir = wp_upload_dir();
            $base_export_dir = trailingslashit($upload_dir['basedir']) . 'spbwc-exports';
            $base_export_url = trailingslashit($upload_dir['baseurl']) . 'spbwc-exports';

            if (!file_exists($base_export_dir)) {
                wp_mkdir_p($base_export_dir);
            }

            $product_dir_name = 'product-' . $product_id . '-' . time();
            $export_dir = trailingslashit($base_export_dir) . $product_dir_name;
            if (!file_exists($export_dir)) {
                wp_mkdir_p($export_dir);
            }

            // 1. Prepare settings.txt
            $settings_data = array(
                'name'                  => $product->get_name(),
                'description'           => $product->get_description(),
                'regular_price'         => $product->get_regular_price(),
                'sale_price'            => $product->get_sale_price(),
                'image'                 => wp_get_attachment_url($product->get_image_id()),
                'enable_design'         => get_post_meta($product_id, '_nbdesigner_enable', true),
                'enable_upload'         => get_post_meta($product_id, '_nbdesigner_enable_upload', true),
                'upload_without_design' => get_post_meta($product_id, '_nbdesigner_enable_upload_without_design', true),
                'nbo_enable'            => get_post_meta($product_id, '_nbo_enable', true),
                'setting_upload'        => get_post_meta($product_id, '_nbdesigner_upload', true),
                'option'                => get_post_meta($product_id, '_nbdesigner_option', true),
                'setting_design'        => get_post_meta($product_id, '_designer_setting', true),
            );
            file_put_contents($export_dir . '/settings.txt', serialize($settings_data));

            // 2. Prepare print_options.txt
            $tables_to_check = array(
                $wpdb->prefix . 'storelly_product_builder_options',
                $wpdb->prefix . 'pcpo_printing_options',
                $wpdb->prefix . 'nbdesigner_options'
            );
            
            $options_row = null;
            foreach ($tables_to_check as $table_name) {
                // Check if table exists first
                if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
                    $row = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$table_name} WHERE product_ids LIKE %s LIMIT 1",
                        '%' . $wpdb->esc_like('"' . $product_id . '"') . '%'
                    ), ARRAY_A);
                    
                    if ($row) {
                        $options_row = $row;
                        break;
                    }
                }
            }

            if ($options_row) {
                file_put_contents($export_dir . '/print_options.txt', serialize($options_row));
            } else {
                file_put_contents($export_dir . '/print_options.txt', serialize(array()));
            }

            // 3. Export Featured Image
            $image_id = $product->get_image_id();
            if ($image_id) {
                $image_path = get_attached_file($image_id);
                if ($image_path && file_exists($image_path)) {
                    $ext = pathinfo($image_path, PATHINFO_EXTENSION);
                    $new_image_name = sanitize_title($product->get_name()) . '.' . $ext;
                    copy($image_path, $export_dir . '/' . $new_image_name);
                }
            }

            // 4. Zip the folder
            $zip_file = $base_export_dir . '/' . $product_dir_name . '.zip';
            $zip_url  = trailingslashit($base_export_url) . $product_dir_name . '.zip';
            
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                    $files = scandir($export_dir);
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..') {
                            $zip->addFile($export_dir . '/' . $file, $file);
                        }
                    }
                    $zip->close();
                    
                    // Cleanup the temporary folder
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..') {
                            unlink($export_dir . '/' . $file);
                        }
                    }
                    rmdir($export_dir);
                    
                    return array(
                        'success' => true,
                        'url' => $zip_url,
                        'path' => $zip_file
                    );
                }
            }

            return new WP_Error('zip_error', __('Could not create ZIP file.', 'storelly-product-builder-for-woocommerce'));
        }

        public function handle_ajax_export() {
            check_ajax_referer('spbwc_export_product_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Permission denied.', 'storelly-product-builder-for-woocommerce'));
            }

            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            if (!$product_id) {
                wp_send_json_error(__('Invalid product ID.', 'storelly-product-builder-for-woocommerce'));
            }

            $result = $this->export_product($product_id);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                wp_send_json_success($result);
            }
        }
    }
}
$spbwc_product_exporter = SPBWC_Product_Exporter::instance();
$spbwc_product_exporter->init();


