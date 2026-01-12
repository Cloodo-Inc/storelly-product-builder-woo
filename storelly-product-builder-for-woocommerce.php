<?php
 if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
/**
 * @package Storelly 
 */
/*

Plugin Name:            Storelly Product Builder for WooCommerce
Plugin URI:             https://storelly.com/product-builder
Description:            Create product builder for Woocommerce products
Version:                1.2.2
Requires Plugins:       woocommerce
WC requires at least:   6.0.0
WC tested up to:        6.5.1
PHP:                    >=7.0
Author:                 Storelly Team
Author URI:             https://storelly.com
License:                GPL v2 or later
License URI:            https://www.gnu.org/licenses/gpl-2.0.html 
Text Domain:            storelly-product-builder-for-woocommerce
*/

$spbwc_upload_dir = wp_upload_dir();
$spbwc_basedir    = $spbwc_upload_dir['basedir'];
$spbwc_baseurl    = $spbwc_upload_dir['baseurl'];
define('SPBWC_PB_VERSION',                  '1.0.0');
define('SPBWC_PB_NUMBER_VERSION',           100);
define('SPBWC_PB_PLUGIN_URL',               plugin_dir_url(__FILE__));
define('SPBWC_PB_PLUGIN_DIR',               plugin_dir_path(__FILE__));
define('SPBWC_PB_DATA_DIR',                 $spbwc_basedir . '/storelly-product-builder');
define('SPBWC_PB_DATA_URL',                 $spbwc_baseurl . '/storelly-product-builder');
define('SPBWC_PB_DATA_CONFIG_URL',          SPBWC_PB_PLUGIN_URL . 'data/');
define('SPBWC_PB_DATA_CONFIG_DIR',          SPBWC_PB_PLUGIN_DIR . 'data/');
define('SPBWC_PB_FONT_URL',                 SPBWC_PB_DATA_URL . '/fonts');
define('SPBWC_PB_FONT_DIR',                 SPBWC_PB_DATA_DIR . '/fonts');
define('SPBWC_PB_UPLOAD_DIR',               SPBWC_PB_DATA_DIR . '/uploads');
define('SPBWC_PB_UPLOAD_URL',               SPBWC_PB_DATA_URL . '/uploads');
define('SPBWC_PB_CUSTOMER_DIR',             SPBWC_PB_DATA_DIR . '/designs');
define('SPBWC_PB_CUSTOMER_URL',             SPBWC_PB_DATA_URL . '/designs');
define('SPBWC_PB_ASSETS_URL',               SPBWC_PB_PLUGIN_URL . 'assets/');
define('SPBWC_PB_ASSETS_DIR',               SPBWC_PB_PLUGIN_DIR . 'assets/');
define('SPBWC_PB_JS_URL',                   SPBWC_PB_PLUGIN_URL . 'assets/js/');
define('SPBWC_PB_CSS_URL',                  SPBWC_PB_PLUGIN_URL . 'assets/css/');
define('SPBWC_ENABLE_NONCE',                TRUE);
define('SPBWC_API_URL',                     'https://app.storelly.com/public');


// check if woocommerce works
register_activation_hook(__FILE__, 'storelly_plugin_activation');
function storelly_plugin_activation() {
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        $message  = '<div class="error"><p>';
        $message .= esc_html__('WooCommerce is not active. Please activate WooCommerce before using', 'storelly-product-builder-for-woocommerce');
        $message .= ' <b>' . esc_html__('Product Builder Integration', 'storelly-product-builder-for-woocommerce') . '</b>';
        $message .= '</p></div>';
        wp_die(wp_kses_post($message));
    }
    SPBWC_Storelly_Product_Builder_Backend::spbwc_plugin_activation();
}

require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-script-hook.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-export-pdf.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-util.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-image.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-io.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-install.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-product-builder-backend.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-product-builder-frontend.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-admin-options.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-frontend-options.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-http.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-productbuilder-api.php');

register_activation_hook(__FILE__, array('SPBWC_Storelly_Product_Builder_API', 'spbwc_generate_key'));

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

// Before convert array to string, after sanitize_text_field to text
function spbwc_sanitize_recursive($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = spbwc_sanitize_recursive($value);
        }
    } elseif (is_string($data)) {
        $data = sanitize_text_field($data);
    }
    return $data;
}

$storelly_product_builder = new SPBWC_Storelly_Product_Builder_Backend();
$storelly_product_builder->spbwc_init();



