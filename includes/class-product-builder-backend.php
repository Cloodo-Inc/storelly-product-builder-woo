<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('Storelly_Product_Builder_Backend')) {
    class Storelly_Product_Builder_Backend {
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
            add_action('admin_menu', array($this, 'storelly_menu'));
            add_action('plugins_loaded', array($this, 'storelly_user_role'));
        }
        public function frontend_hook() {
        }
        public function storelly_menu() {
            do_action('storelly_pb_menu');
        }
        public function storelly_user_role() {
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
        public static function plugin_activation($network_wide = '') {
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
            Storelly_Install::create_pages();
            Storelly_Install::create_tables();
            Storelly_Install::init_files_and_folders();
            update_option('storelly_version_plugin', STORELLY_PB_VERSION);
        }
    }
}
