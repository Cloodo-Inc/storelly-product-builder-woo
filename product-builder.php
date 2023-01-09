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

define('PRINTCART_PB_VERSION',                  '1.0.0');
define('PRINTCART_PB_NUMBER_VERSION',           100);
define('PRINTCART_PB_PLUGIN_URL',               plugin_dir_url(__FILE__));
define('PRINTCART_PB_PLUGIN_DIR',               plugin_dir_path(__FILE__));
define('PRINTCART_PB_ASSETS_URL',                 PRINTCART_PB_PLUGIN_URL . 'assets/');
define('PRINTCART_PB_JS_URL',                     PRINTCART_PB_PLUGIN_URL . 'assets/js/');
define('PRINTCART_PB_CSS_URL',                    PRINTCART_PB_PLUGIN_URL . 'assets/css/');

require_once(PRINTCART_PB_PLUGIN_DIR .  'includes/functions.php');
require_once(PRINTCART_PB_PLUGIN_DIR .  'includes/class-install.php');
require_once(PRINTCART_PB_PLUGIN_DIR .  'includes/class-admin-options.php');
require_once(PRINTCART_PB_PLUGIN_DIR .  'includes/class-printcart-product-builder.php');

register_activation_hook(__FILE__, array('Printcart_Product_Builder_Plugin', 'plugin_activation'));

$printcart_product_builder = new Printcart_Product_Builder_Plugin;
$printcart_product_builder->init();
