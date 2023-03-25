<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('Printcart_Install')) {
    class Printcart_Install {
        public function __construct() {
            //todo something when initial class
        }
        public static function create_pages() {
            /* Create product builder page */
            $printcart_product_builder_page_id = Printcart_PB_Util::printcart_get_page_id('product_builder');
            if ($printcart_product_builder_page_id == -1 || !get_post($printcart_product_builder_page_id)) {
                $post = array(
                    'post_name'         => 'product-builder',
                    'post_status'       => 'publish',
                    'post_title'        => esc_html__('Product Builder', 'web-to-print-online-designer'),
                    'post_type'         => 'page',
                    'post_author'       => 1,
                    'post_content'      => '',
                    'comment_status'    => 'closed',
                    'post_date'         => date('Y-m-d H:i:s')
                );
                $printcart_product_builder_page_id = wp_insert_post($post, false);
                update_option('printcart_product_builder_page_id', $printcart_product_builder_page_id);
            }
        }
        public static function create_tables() {
            do_action('printcart_create_tables');
        }
        public static function init_files_and_folders() {
            Printcart_IO::mkdir(PRINTCART_PB_FONT_DIR);
            Printcart_IO::mkdir(PRINTCART_PB_CUSTOMER_DIR);
            do_action('printcart_init_files_and_folders');
        }
    }
}
