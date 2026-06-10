<?php
/**
 * Shared dialog & toast asset loader (spbwcDialog).
 *
 * Registers and enqueues the token-styled dialog utility that replaces the
 * native browser alert/confirm/prompt popups across the plugin.
 *
 * - Admin: registered + enqueued on every admin page (tiny footprint; the
 *   utility is used by inline scripts scattered across Storelly and
 *   WooCommerce-native screens — product edit metaboxes, B2B, quotes, etc.).
 * - Storefront: registered as a dependency so the buyer-facing handles can
 *   declare it; the matching stylesheet is auto-paired when the script is
 *   enqueued.
 *
 * @package Storelly_Product_Builder
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'SPBWC_Dialog' ) ) {

	class SPBWC_Dialog {

		/**
		 * Wire the asset hooks.
		 */
		public static function init() {
			// Register early (priority 1) so other handles can depend on it.
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register' ), 1 );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 1 );

			// Admin: load on every admin page (utility is used widely there).
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 30 );

			// Storefront: pair the stylesheet with the script when a buyer-facing
			// handle has declared the dependency.
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_pair_style' ), 99 );
		}

		/**
		 * Register the script + stylesheet (idempotent).
		 */
		public static function register() {
			// Register the design-token stylesheets with filemtime cache-busting
			// FIRST (this hook runs at priority 1, before the defensive
			// `if ( ! registered )` blocks scattered across the other classes),
			// so edits to _tokens.css / _tokens-storefront.css always reach the
			// browser instead of being pinned to the static plugin version.
			self::register_token_style( 'spbwc-tokens', '_tokens.css' );
			self::register_token_style( 'spbwc-tokens-storefront', '_tokens-storefront.css' );

			if ( ! wp_script_is( 'spbwc-dialog', 'registered' ) ) {
				$js = SPBWC_PB_PLUGIN_DIR . 'static/js/spbwc-dialog.js';
				wp_register_script(
					'spbwc-dialog',
					SPBWC_PB_JS_URL . 'spbwc-dialog.js',
					array(),
					file_exists( $js ) ? filemtime( $js ) : SPBWC_PB_VERSION,
					true
				);
				wp_localize_script( 'spbwc-dialog', 'spbwcDialogI18n', self::i18n() );
			}

			if ( ! wp_style_is( 'spbwc-dialog', 'registered' ) ) {
				$css = SPBWC_PB_PLUGIN_DIR . 'static/css/spbwc-dialog.css';
				wp_register_style(
					'spbwc-dialog',
					SPBWC_PB_CSS_URL . 'spbwc-dialog.css',
					array( 'dashicons' ),
					file_exists( $css ) ? filemtime( $css ) : SPBWC_PB_VERSION
				);
			}
		}

		/**
		 * Register a design-token stylesheet with filemtime cache-busting,
		 * unless something already registered the handle.
		 *
		 * @param string $handle Style handle.
		 * @param string $file   File name under static/css/.
		 */
		protected static function register_token_style( $handle, $file ) {
			if ( wp_style_is( $handle, 'registered' ) ) {
				return;
			}
			$path = SPBWC_PB_PLUGIN_DIR . 'static/css/' . $file;
			wp_register_style(
				$handle,
				SPBWC_PB_CSS_URL . $file,
				array(),
				file_exists( $path ) ? filemtime( $path ) : SPBWC_PB_VERSION
			);
		}

		/**
		 * Enqueue both assets (admin).
		 */
		public static function enqueue() {
			self::register();
			wp_enqueue_script( 'spbwc-dialog' );
			wp_enqueue_style( 'spbwc-dialog' );
		}

		/**
		 * On the storefront, ensure the stylesheet ships whenever the script
		 * has been enqueued by a buyer-facing handle that declared it as a dep.
		 */
		public static function maybe_pair_style() {
			if ( wp_script_is( 'spbwc-dialog', 'enqueued' ) && ! wp_style_is( 'spbwc-dialog', 'enqueued' ) ) {
				self::register();
				wp_enqueue_style( 'spbwc-dialog' );
			}
		}

		/**
		 * Default translatable strings handed to the JS module.
		 *
		 * @return array<string,string>
		 */
		protected static function i18n() {
			return array(
				'ok'      => __( 'OK', 'storelly-product-builder-for-woocommerce' ),
				'confirm' => __( 'OK', 'storelly-product-builder-for-woocommerce' ),
				'cancel'  => __( 'Cancel', 'storelly-product-builder-for-woocommerce' ),
				'prompt'  => __( 'Enter a value', 'storelly-product-builder-for-woocommerce' ),
			);
		}
	}
}
