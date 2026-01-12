<?php
defined('ABSPATH') || exit;
if (!class_exists('SPBWC_SPBWC_Storelly_PB_Script_Hook')) {
    class SPBWC_Storelly_PB_Script_Hook
    {
        protected static $instance;
        public static function instance()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        public function __construct()
        {
            //todo
        }
        public function spbwc_init()
        {
            add_action('spbwc_head', array($this, 'spbwc_enqueue_script_head'), 1, 1);
            add_action('spbwc_footer', array($this, 'spbwc_enqueue_script_footer'), 1, 1);
        }

        public function spbwc_print_styles($handles = false)
        {
            global $wp_styles; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wp_styles.

            _wp_scripts_maybe_doing_it_wrong(__FUNCTION__);

            if (!($wp_styles instanceof WP_Styles)) {
                if (!$handles) {
                    return array(); // No need to instantiate if nothing is there.
                }
            }

            return wp_styles()->do_items($handles);
        }

        public function spbwc_print_scripts($handles = false)
        {
            global $wp_scripts; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wp_scripts.

            _wp_scripts_maybe_doing_it_wrong(__FUNCTION__);

            if (!($wp_scripts instanceof WP_Scripts)) {
                if (!$handles) {
                    return array(); // No need to instantiate if nothing is there.
                }
            }

            return wp_scripts()->do_items($handles);
        }

        public function spbwc_enqueue_style($handles = false)
        {

            if (is_array($handles) && count($handles) > 0) {
                $this->spbwc_print_styles($handles);
            }
        }

        public function spbwc_enqueue_script($handles = false)
        {

            if (is_array($handles) && count($handles) > 0) {
                $this->spbwc_print_scripts($handles);
            }
        }

        // PC custom add param script
        public function spbwc_enqueue_script_head($page)
        {
            wp_register_style('spbwc-poppins-font-r', 'https://fonts.googleapis.com/css?family=Poppins:400,400i,700,700i', array(), SPBWC_PB_VERSION);
            wp_register_style('spbwc-spectrum-css', SPBWC_PB_CSS_URL . 'spectrum.css', array(), '1.8.0');
            wp_register_style('spbwc-app-product-builder', SPBWC_PB_CSS_URL . 'app-product-builder.css', array(), SPBWC_PB_VERSION);
            wp_register_style('storelly-product-builder-for-woocommerce-view', SPBWC_PB_CSS_URL . 'views/product-builder.css', array(), SPBWC_PB_VERSION);

            wp_register_script('wc-accounting', WC()->plugin_url() . '/assets/js/accounting/accounting.min.js', array(), '0.4.2', true);
            wp_register_script('spbwc-fontfaceobserver', SPBWC_PB_PLUGIN_URL . 'assets/libs/fontfaceobserver.js', array(), '2.0.13', true);
            wp_register_script('spbwc-fabric', SPBWC_PB_PLUGIN_URL . 'assets/libs/fabric.2.6.0.min.js', array(), '2.6.0', true);
            wp_register_script('spbwc-spectrum-css', SPBWC_PB_JS_URL . 'spectrum.js', array(), SPBWC_PB_VERSION, true);
            wp_register_script('spbwc-tiptip', SPBWC_PB_ASSETS_URL . 'js/tiptip.js', array('jquery'), SPBWC_PB_VERSION, true);

            if ($page == 'product-builder') {
                $this->spbwc_enqueue_style(array('spbwc-poppins-font-r', 'spbwc-spectrum-css', 'spbwc-app-product-builder', 'storelly-product-builder-for-woocommerce'));
                $this->spbwc_enqueue_script(array('spbwc-storelly-ext', 'spbwc-ag', 'wc-accounting'));
            }
            if ($page == 'single-product') {
                $this->spbwc_enqueue_script(array('spbwc-ag', 'spbwc-tiptip'));
            }
        }

        public function spbwc_enqueue_script_custom()
        {
            do_action('spbwc_enqueue_script_custom');
        }

        public function spbwc_enqueue_script_footer($page)
        {
            wp_enqueue_script('lodash');
            wp_register_script('spbwc-fontfaceobserver', SPBWC_PB_PLUGIN_URL . 'assets/libs/fontfaceobserver.js', array('jquery'), '2.0.13', true);
            wp_register_script('spbwc-fabric', SPBWC_PB_PLUGIN_URL . 'assets/libs/fabric.2.6.0.min.js', array('jquery'), '2.6.0', true);
            wp_register_script('spbwc-spectrum-css', SPBWC_PB_JS_URL . 'spectrum.js', array('jQuery'), SPBWC_PB_VERSION, true);
            wp_register_script('spbwc-app-product-builder', SPBWC_PB_JS_URL . 'app-product-builder.js', array('jquery'), SPBWC_PB_VERSION, true);

            if ($page === 'product-builder') {
                $this->spbwc_enqueue_script(array('spbwc-fontfaceobserver'));
                $this->spbwc_enqueue_script(array('spbwc-fabric'));
                $this->spbwc_enqueue_script(array('spbwc-spectrum-css'));
                $this->spbwc_enqueue_script(array('spbwc-app-product-builder'));
            }
        }
    }
}
$spbwc_script_hook = SPBWC_Storelly_PB_Script_Hook::instance();
$spbwc_script_hook->spbwc_init();