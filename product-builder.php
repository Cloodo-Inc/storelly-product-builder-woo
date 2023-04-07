<?php

/**
 * @package Printcart
 */
/*
Plugin Name: Printcart Product Builder
Plugin URI: https://printcart.com
Description: Create product builder for WC products
Version: 1.0.0
Author: Printcart Team
Author URI: https://printcart.com
Text Domain: pc-product-builder
WC requires at least: 6.0.0
WC tested up to: 6.5.1
PHP: >=7.0
*/

$upload_dir = wp_upload_dir();
$basedir    = $upload_dir['basedir'];
$baseurl    = $upload_dir['baseurl'];
define('PRINTCART_PB_VERSION',                  '1.0.0');
define('PRINTCART_PB_NUMBER_VERSION',           100);
define('PRINTCART_PB_PLUGIN_URL',               plugin_dir_url(__FILE__));
define('PRINTCART_PB_PLUGIN_DIR',               plugin_dir_path(__FILE__));
define('PRINTCART_PB_DATA_DIR',                 $basedir . '/printcart-product-builder');
define('PRINTCART_PB_DATA_URL',                 $baseurl . '/printcart-product-builder');
define('PRINTCART_PB_DATA_CONFIG_URL',          PRINTCART_PB_PLUGIN_URL . 'data/');
define('PRINTCART_PB_DATA_CONFIG_DIR',          PRINTCART_PB_PLUGIN_DIR . 'data/');
define('PRINTCART_PB_FONT_URL',                 PRINTCART_PB_DATA_URL . '/fonts');
define('PRINTCART_PB_FONT_DIR',                 PRINTCART_PB_DATA_DIR . '/fonts');
define('PRINTCART_PB_UPLOAD_DIR',               PRINTCART_PB_DATA_DIR . '/uploads');
define('PRINTCART_PB_UPLOAD_URL',               PRINTCART_PB_DATA_URL . '/uploads');
define('PRINTCART_PB_CUSTOMER_DIR',             PRINTCART_PB_DATA_DIR . '/designs');
define('PRINTCART_PB_CUSTOMER_URL',             PRINTCART_PB_DATA_URL . '/designs');
define('PRINTCART_PB_ASSETS_URL',               PRINTCART_PB_PLUGIN_URL . 'assets/');
define('PRINTCART_PB_ASSETS_DIR',               PRINTCART_PB_PLUGIN_DIR . 'assets/');
define('PRINTCART_PB_JS_URL',                   PRINTCART_PB_PLUGIN_URL . 'assets/js/');
define('PRINTCART_PB_CSS_URL',                  PRINTCART_PB_PLUGIN_URL . 'assets/css/');
define('PRINTCART_ENABLE_NONCE',                TRUE);

require_once(PRINTCART_PB_PLUGIN_DIR .  'includes/class-script-hook.php');
require_once(PRINTCART_PB_PLUGIN_DIR .  'includes/class-export-pdf.php');
require_once(PRINTCART_PB_PLUGIN_DIR .  'includes/class-util.php');
require_once(PRINTCART_PB_PLUGIN_DIR .  'includes/class-image.php');
require_once(PRINTCART_PB_PLUGIN_DIR .  'includes/class-io.php');
require_once(PRINTCART_PB_PLUGIN_DIR .  'includes/class-install.php');
require_once(PRINTCART_PB_PLUGIN_DIR .  'includes/class-product-builder-backend.php');
require_once(PRINTCART_PB_PLUGIN_DIR .  'includes/class-product-builder-frontend.php');
require_once(PRINTCART_PB_PLUGIN_DIR .  'includes/class-admin-options.php');
require_once(PRINTCART_PB_PLUGIN_DIR .  'includes/class-frontend-options.php');

register_activation_hook(__FILE__, array('Printcart_Product_Builder_Backend', 'plugin_activation'));

$printcart_product_builder = new Printcart_Product_Builder_Backend;
$printcart_product_builder->init();
