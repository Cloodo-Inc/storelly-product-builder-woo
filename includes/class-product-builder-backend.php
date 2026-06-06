<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('SPBWC_Storelly_Product_Builder_Backend')) {
    class SPBWC_Storelly_Product_Builder_Backend {
        public function __construct() {
        }
        public function spbwc_init() {
            if (is_admin()) {
                $this->spbwc_admin_hook();
                $this->spbwc_ajax();
            } else {
                $this->spbwc_frontend_hook();
            }
        }
        public function spbwc_ajax() {
            $ajax_events = array();

            foreach ($ajax_events as $ajax_event => $nopriv) {
                add_action('wp_ajax_' . $ajax_event, array($this, $ajax_event));
                if ($nopriv) {
                    add_action('wp_ajax_nopriv_' . $ajax_event, array($this, $ajax_event));
                }
            }
        }
        public function spbwc_admin_hook() {
            add_action('admin_menu', array($this, 'spbwc_menu'));
            add_action('plugins_loaded', array($this, 'spbwc_user_role'));
        }
        public function spbwc_frontend_hook() {
        }
        public function spbwc_menu() {
            do_action('spbwc_pb_menu');
        }
        public function spbwc_user_role() {
            $capabilities = array(
                1 => 'spbwc_manage_product_builder',
            );
            $admin_role = get_role('administrator');
            if (null != $admin_role) {
                foreach ($capabilities as $cap) {
                    $admin_role->add_cap($cap);
                }
            }
        }
        public static function spbwc_plugin_activation($network_wide = '') {
            if (is_multisite() && $network_wide) {
                $site_ids = get_sites(
                    array(
                        'fields' => 'ids',
                        'number' => 0,
                    )
                );
                foreach ($site_ids as $blog_id) {
                    switch_to_blog((int) $blog_id);
                    self::spbwc__plugin_activation();
                    restore_current_blog();
                }
            } else {
                self::spbwc__plugin_activation();
            }
        }
        public static function spbwc__plugin_activation() {
            self::spbwc_install();
        }
        public static function spbwc_install() {
            /* Install */
            SPBWC_Storelly_Install::spbwc_create_pages();
            SPBWC_Storelly_Install::spbwc_create_tables();
            SPBWC_Storelly_Install::spbwc_init_files_and_folders();
            update_option('spbwc_version_plugin', SPBWC_PB_VERSION);
            // Clear every account-endpoint flush flag so the next `init` re-flushes
            // rewrite rules and all My-Account endpoints resolve without the user
            // having to re-save permalinks. Endpoints register on `init`, after this
            // activation hook, so we cannot flush here directly — we arm the lazy
            // flush instead (covers (re)activation cleanly).
            $flush_flags = array(
                'spbwc_quotes_endpoint_flushed',
                'spbwc_saved_designs_flushed',
                'spbwc_b2b_account_flushed',
                'spbwc_b2b_reorders_flushed',
                'spbwc_b2b_team_flushed',
                'spbwc_b2b_approval_flushed',
                'spbwc_b2b_store_flushed',
            );
            foreach ( $flush_flags as $flag ) {
                delete_option( $flag );
            }
        }
    }
}
