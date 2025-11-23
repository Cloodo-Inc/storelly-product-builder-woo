<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('Storelly_Install')) {
    class Storelly_Install {
        public function __construct() {
            //todo something when initial class
        }
        public static function create_pages() {
            /* Create product builder page */
            $storelly_product_builder_page_id = SPBWC_Storelly_PB_Util::spbwc_get_page_id('product_builder');
            if ($storelly_product_builder_page_id == -1 || !get_post($storelly_product_builder_page_id)) {
                $post = array(
                    'post_name'         => 'product-builder',
                    'post_status'       => 'publish',
                    'post_title'        => esc_html__('Product Builder', 'pc-product-builder'),
                    'post_type'         => 'page',
                    'post_author'       => 1,
                    'post_content'      => '',
                    'comment_status'    => 'closed',
                    'post_date'         => date('Y-m-d H:i:s')
                );
                $storelly_product_builder_page_id = wp_insert_post($post, false);
                update_option('storelly_product_builder_page_id', $storelly_product_builder_page_id);
            }
        }
        public static function create_tables() {
            do_action('storelly_create_tables');
        }
        public static function init_files_and_folders() {
            SPBWC_Storelly_IO::spbwc_mkdir(STORELLY_PB_FONT_DIR);
            SPBWC_Storelly_IO::spbwc_mkdir(STORELLY_PB_CUSTOMER_DIR);
            do_action('storelly_init_files_and_folders');
        }
    }
}
