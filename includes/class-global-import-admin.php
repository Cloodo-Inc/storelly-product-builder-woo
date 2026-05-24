<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPBWC_Global_Import_Admin')) {
    class SPBWC_Global_Import_Admin {
        protected static $instance;
        
        public static function instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        public function init() {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
        
        public function enqueue_admin_scripts($hook) {
            // Hook suffix is {sanitize_title( top menu title )}_page_{ submenu slug }. Top menu is "Storelly Builder" → "storelly-builder_page_…", not "product-builder-options_page_…".
            $global_import_page_needle = '_page_' . SPBWC_PB_OPTIONS_SLUG . '/global-import';
            if (strpos($hook, $global_import_page_needle) === false) {
                return;
            }
            wp_register_style('spbwc-tokens', SPBWC_PB_CSS_URL . '_tokens.css', array(), SPBWC_PB_VERSION);
            // Register shared admin UI in case this page loads without the main enqueue hook.
            wp_register_style('spbwc-admin-ui', SPBWC_PB_CSS_URL . 'storelly-admin-ui.css', array('spbwc-tokens', 'dashicons'), SPBWC_PB_VERSION);
            wp_enqueue_style(
                'spbwc-global-import-admin',
                SPBWC_PB_CSS_URL . 'global-import-app.css',
                array('spbwc-admin-ui'),
                SPBWC_PB_VERSION
            );
            wp_enqueue_script(
                'spbwc-vue',
                'https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js',
                array(),
                '3.4.27',
                true
            );
            wp_enqueue_script(
                'spbwc-global-import-helpers',
                SPBWC_PB_JS_URL . 'global-import-helpers.js',
                array(),
                SPBWC_PB_VERSION,
                true
            );
            wp_enqueue_script(
                'spbwc-global-import-app',
                SPBWC_PB_JS_URL . 'global-import-app.js',
                array('spbwc-vue', 'spbwc-global-import-helpers', 'jquery'),
                SPBWC_PB_VERSION,
                true
            );
            wp_localize_script('spbwc-global-import-app', 'SPBWC_GLOBAL_IMPORT', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'admin_url' => admin_url('admin.php'),
                'page_slug' => 'storelly-product-builder-for-woocommerce-options/global-import',
                'nonce' => wp_create_nonce('spbwc_global_import'),
                'rest_url' => esc_url_raw(rest_url('storelly/v1')),
                'rest_nonce' => wp_create_nonce('wp_rest'),
                'assets_url' => SPBWC_PB_ASSETS_URL,
                'i18n' => array(
                    'upload_title' => __('Upload file', 'storelly-product-builder-for-woocommerce'),
                    'import_title' => __('Import products', 'storelly-product-builder-for-woocommerce'),
                    'export_title' => __('Export sessions', 'storelly-product-builder-for-woocommerce'),
                    'confirm_delete' => __('Are you sure you want to delete this export?', 'storelly-product-builder-for-woocommerce'),
                    'loading_products' => __('Loading products…', 'storelly-product-builder-for-woocommerce'),
                )
            ));
        }
        
        public function render_global_import_page() {
            ?>
            <div class="wrap spbwc-global-import-wrap">
                <header class="spbwc-page-hero">
                    <div class="spbwc-page-hero__grid">
                        <div class="spbwc-page-hero__body">
                            <div class="spbwc-page-hero__eyebrow">
                                <span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
                                <?php esc_html_e( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                            </div>
                            <h1 class="spbwc-page-hero__title">
                                <span class="dashicons dashicons-upload" aria-hidden="true"></span>
                                <?php esc_html_e( 'Global Import', 'storelly-product-builder-for-woocommerce' ); ?>
                            </h1>
                            <p class="spbwc-page-hero__subtitle">
                                <?php esc_html_e( 'Bulk-import product configurations, upload design files and export sessions for migration or backup.', 'storelly-product-builder-for-woocommerce' ); ?>
                            </p>
                        </div>
                        <div class="spbwc-page-hero__actions">
                            <a href="https://storelly.com/docs/import" target="_blank" rel="noopener noreferrer"
                               class="spbwc-cta-btn spbwc-cta-btn--ghost">
                                <span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
                                <?php esc_html_e( 'Import Guide', 'storelly-product-builder-for-woocommerce' ); ?>
                            </a>
                        </div>
                    </div>
                </header>
                <div id="spbwc-global-import-app"></div>
            </div>
            <?php
        }
    }
}

// Initialize the admin interface
function spbwc_global_import_admin_init() {
    $global_import_admin = SPBWC_Global_Import_Admin::instance();
    $global_import_admin->init();
    return $global_import_admin;
}
add_action('init', 'spbwc_global_import_admin_init');
