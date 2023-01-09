<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('Printcart_Product_Builder_Plugin')) {
    class Printcart_Product_Builder_Plugin {
        public function __construct() {
        }
        public function init() {
            if (is_admin()) {
                $this->admin_hook();
                $this->ajax();
            } else {
                $this->frontend_hook();
            }
        }
        public function ajax() {
            $ajax_events = array();

            foreach ($ajax_events as $ajax_event => $nopriv) {
                add_action('wp_ajax_' . $ajax_event, array($this, $ajax_event));
                if ($nopriv) {
                    add_action('wp_ajax_nopriv_' . $ajax_event, array($this, $ajax_event));
                }
            }
        }
        public function admin_hook() {
            add_action('admin_menu', array($this, 'printcart_menu'));
            add_action('plugins_loaded', array($this, 'printcart_user_role'));
        }
        public function frontend_hook() {
        }
        public function printcart_menu() {
            do_action('printcart_pb_menu');
        }
        public function printcart_user_role() {
            $capabilities = array(
                1 => 'manage_product_builder',
            );
            $admin_role = get_role('administrator');
            if (null != $admin_role) {
                foreach ($capabilities as $cap) {
                    $admin_role->add_cap($cap);
                }
            }
        }
        public static function plugin_activation($network_wide) {
            if (is_multisite() && $network_wide) {
                global $wpdb;
                foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
                    switch_to_blog($blog_id);
                    self::_plugin_activation();
                    restore_current_blog();
                }
            } else {
                self::_plugin_activation();
            }
        }
        public static function _plugin_activation() {
            self::install();
        }
        public static function install() {
            /* Install */
            Printcart_Install::create_pages();
            Printcart_Install::create_tables();
            update_option('printcart_version_plugin', PRINTCART_PB_VERSION);
        }
    }
}
