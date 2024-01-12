<?php
 if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
/**
 * @package Storelly 
 */
/*
Plugin Name: Storelly Product Builder 
Plugin URI: https://storelly.com
Description: Create product builder for WC products
Version: 1.0.0
Author: Storelly Team
Author URI: https://storelly.com
Text Domain: pc-product-builder
WC requires at least: 6.0.0
WC tested up to: 6.5.1
PHP: >=7.0
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

$storelly_product_builder = new Storelly_Product_Builder_Backend();
$storelly_product_builder->init();



