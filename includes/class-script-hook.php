<?php
defined('ABSPATH') || exit;
class PC_PB_Script_Hook {
    protected static $instance;
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public function __construct() {
        //todo
    }
    public function init() {

        add_action('pc_head', array($this, 'pc_enqueue_script_head'), 1, 1);

        add_action('pc_footer', array($this, 'pc_enqueue_script_footer'), 1, 1);
    }

    public function pc_print_styles($handles = false) {
        global $wp_styles;

        _wp_scripts_maybe_doing_it_wrong(__FUNCTION__);

        if (!($wp_styles instanceof WP_Styles)) {
            if (!$handles) {
                return array(); // No need to instantiate if nothing is there.
            }
        }

        return wp_styles()->do_items($handles);
    }

    public function pc_print_scripts($handles = false) {
        global $wp_scripts;

        _wp_scripts_maybe_doing_it_wrong(__FUNCTION__);

        if (!($wp_scripts instanceof WP_Scripts)) {
            if (!$handles) {
                return array(); // No need to instantiate if nothing is there.
            }
        }

        return wp_scripts()->do_items($handles);
    }

    public function pc_enqueue_style($handles = false) {

        if (is_array($handles) && count($handles) > 0) {
            $this->pc_print_styles($handles);
        }
    }

    public function pc_enqueue_script($handles = false) {

        if (is_array($handles) && count($handles) > 0) {
            $this->pc_print_scripts($handles);
        }
    }

    // PC custom add param script
    public function pc_enqueue_script_head($page) {
        wp_register_style('pc-poppins-font-r', 'https://fonts.googleapis.com/css?family=Poppins:400,400i,700,700i', array(), PRINTCART_PB_VERSION);
        wp_register_style('pc-spectrum', PRINTCART_PB_CSS_URL . 'spectrum.css', array(), '1.8.0');
        wp_register_style('pc-app-product-builder', PRINTCART_PB_CSS_URL . 'app-product-builder.css', array(), PRINTCART_PB_VERSION);
        wp_register_style('pc-product-builder', PRINTCART_PB_CSS_URL . 'views/product-builder.css', array(), PRINTCART_PB_VERSION);

        wp_register_script('pc-printcart-ext', PRINTCART_PB_PLUGIN_URL . 'assets/libs/printcart-ext.js', array(), PRINTCART_PB_VERSION, true);
        wp_register_script('pc-angular', 'https://ajax.googleapis.com/ajax/libs/angularjs/1.6.9/angular.min.js', array(), '1.6.9', true);
        wp_register_script('wc-accounting',  WC()->plugin_url() . '/assets/js/accounting/accounting.min.js', array(), '0.4.2', true);
        wp_register_script('pc-lodash-min', 'https://cdn.jsdelivr.net/npm/lodash@4.17.11/lodash.min.js', array(), '4.17.11', true);
        wp_register_script('pc-fontfaceobserver', PRINTCART_PB_PLUGIN_URL . 'assets/libs/fontfaceobserver.js', array(), '2.0.13', true);
        wp_register_script('pc-fabric', PRINTCART_PB_PLUGIN_URL . 'assets/libs/fabric.2.6.0.min.js', array(), '2.6.0', true);
        wp_register_script('pc-spectrum', PRINTCART_PB_JS_URL . 'spectrum.js', array(), PRINTCART_PB_VERSION, true);

        if ($page == 'product-builder') {
            $this->pc_enqueue_style(array('pc-poppins-font-r', 'pc-spectrum', 'pc-app-product-builder', 'pc-product-builder'));
            $this->pc_enqueue_script(array('pc-printcart-ext', 'pc-angular', 'wc-accounting'));
        }
        if ($page == 'single-product') {
            $this->pc_enqueue_script(array('pc-angular'));
        }
    }

    public function _pc_enqueue_script() {
        do_action('_pc_enqueue_script');
    }

    public function pc_enqueue_script_footer($page) {
        wp_register_script('pc-lodash-min', 'https://cdn.jsdelivr.net/npm/lodash@4.17.11/lodash.min.js', array(), '4.17.11', true);
        wp_register_script('pc-fontfaceobserver', PRINTCART_PB_PLUGIN_URL . 'assets/libs/fontfaceobserver.js', array(), '2.0.13', true);
        wp_register_script('pc-fabric', PRINTCART_PB_PLUGIN_URL . 'assets/libs/fabric.2.6.0.min.js', array(), '2.6.0', true);
        wp_register_script('pc-spectrum', PRINTCART_PB_JS_URL . 'spectrum.js', array(), PRINTCART_PB_VERSION, true);
        wp_register_script('pc-app-product-builder', PRINTCART_PB_JS_URL . 'app-product-builder.js', array(), PRINTCART_PB_VERSION, true);
        if ($page == 'product-builder') {
            $this->pc_enqueue_script(array('pc-lodash-min', 'pc-fontfaceobserver', 'pc-spectrum', 'pc-fabric', 'pc-app-product-builder'));
        }
    }
}
$pc_pb_script_hook = PC_PB_Script_Hook::instance();
$pc_pb_script_hook->init();
