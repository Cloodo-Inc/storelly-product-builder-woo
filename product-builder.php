<?php
 if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
/**
 * @package Storelly 
 */
/*

Plugin Name:            Product Builder for Woocommerce
Plugin URI:             https://storelly.com/product-builder
Description:            Create product builder for Woocommerce products
Version:                1.0.0
WC requires at least:   6.0.0
WC tested up to:        6.5.1
PHP:                    >=7.0
Author:                 Storelly Team
Author URI:             https://storelly.com
License:                GPL v2 or later
License URI:            https://www.gnu.org/licenses/gpl-2.0.html 
Text Domain:            pc-product-builder
Domain Path: /languages
*/

$upload_dir = wp_upload_dir();
$basedir    = $upload_dir['basedir'];
$baseurl    = $upload_dir['baseurl'];
define('STORELLY_PB_VERSION',                  '1.0.0');
define('STORELLY_PB_NUMBER_VERSION',           100);
define('STORELLY_PB_PLUGIN_URL',               plugin_dir_url(__FILE__));
define('STORELLY_PB_PLUGIN_DIR',               plugin_dir_path(__FILE__));
define('STORELLY_PB_DATA_DIR',                 $basedir . '/storelly-product-builder');
define('STORELLY_PB_DATA_URL',                 $baseurl . '/storelly-product-builder');
define('STORELLY_PB_DATA_CONFIG_URL',          STORELLY_PB_PLUGIN_URL . 'data/');
define('STORELLY_PB_DATA_CONFIG_DIR',          STORELLY_PB_PLUGIN_DIR . 'data/');
define('STORELLY_PB_FONT_URL',                 STORELLY_PB_DATA_URL . '/fonts');
define('STORELLY_PB_FONT_DIR',                 STORELLY_PB_DATA_DIR . '/fonts');
define('STORELLY_PB_UPLOAD_DIR',               STORELLY_PB_DATA_DIR . '/uploads');
define('STORELLY_PB_UPLOAD_URL',               STORELLY_PB_DATA_URL . '/uploads');
define('STORELLY_PB_CUSTOMER_DIR',             STORELLY_PB_DATA_DIR . '/designs');
define('STORELLY_PB_CUSTOMER_URL',             STORELLY_PB_DATA_URL . '/designs');
define('STORELLY_PB_ASSETS_URL',               STORELLY_PB_PLUGIN_URL . 'assets/');
define('STORELLY_PB_ASSETS_DIR',               STORELLY_PB_PLUGIN_DIR . 'assets/');
define('STORELLY_PB_JS_URL',                   STORELLY_PB_PLUGIN_URL . 'assets/js/');
define('STORELLY_PB_CSS_URL',                  STORELLY_PB_PLUGIN_URL . 'assets/css/');
define('STORELLY_ENABLE_NONCE',                TRUE);
define('STORELLY_API_URL',                      'https://dashboard.storelly.com/public');


// check if woocommerce works
register_activation_hook(__FILE__, 'storelly_plugin_activation');
function storelly_plugin_activation() {
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        $message = '<div class="error"><p>' . esc_html__('WooCommerce is not active. Please activate WooCommerce before using', 'pc-product-builder') . ' <b>
        ' . esc_html__('Product Builder Integration', 'pc-product-builder') . '</b></p></div>';
        wp_die($message);
    }
    Storelly_Product_Builder_Backend::plugin_activation();
}

require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-script-hook.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-export-pdf.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-util.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-image.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-io.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-install.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-product-builder-backend.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-product-builder-frontend.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-admin-options.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-frontend-options.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-http.php');
require_once(STORELLY_PB_PLUGIN_DIR .  'includes/class-productbuilder-api.php');

register_activation_hook(__FILE__, array('Storelly_Product_Builder_API', 'storelly_generate_key'));

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

// Before convert array to string, after sanitize_text_field to text
function storelly_sanitize_recursive($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = storelly_sanitize_recursive($value);
        }
    } elseif (is_string($data)) {
        $data = sanitize_text_field($data);
    }
    return $data;
}
function pc_product_builder_load_textdomain() {
    load_plugin_textdomain(
        'pc-product-builder',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'pc_product_builder_load_textdomain');

$storelly_product_builder = new Storelly_Product_Builder_Backend();
$storelly_product_builder->init();