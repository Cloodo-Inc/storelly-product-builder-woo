<?php
/**
 * Cloud 1-click consent (M5).
 *
 * Connects the store to the Storelly cloud — and ONLY ever after the
 * merchant clicks the consent button on the Welcome dashboard. Nothing in
 * this class runs on activation or page load; the single network path is
 * the nonce + capability gated AJAX handler below. This is the line that
 * keeps the plugin wordpress.org-compliant: no phone-home without explicit,
 * informed consent (see docs/SPEC_M5_CLOUD_CONSENT.md).
 *
 * Connecting reuses the existing registration machinery
 * (SPBWC_Storelly_Product_Builder_API::spbwc_generate_key +
 * spbwc_create_user_storelly) — we do not re-implement it — then flips the
 * opt-in flags (order sync + cloud PDF) and records a consent log entry.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Cloud_Connect' ) ) {

	class SPBWC_Cloud_Connect {

		const NONCE         = 'spbwc_cloud_connect';
		const OPTION_CONSENT = 'spbwc_cloud_consent';
		const SETTINGS_KEY   = 'spbwc_pb_settings';
		const KEYS_OPTION    = 'spbwc_connect_api_keys';

		public static function init() {
			add_action( 'wp_ajax_spbwc_cloud_connect', array( __CLASS__, 'ajax_connect' ) );
			add_action( 'wp_ajax_spbwc_cloud_disconnect', array( __CLASS__, 'ajax_disconnect' ) );
			add_action( 'wp_ajax_spbwc_cloud_link_manual', array( __CLASS__, 'ajax_link_manual' ) );
		}

		// ── AJAX ────────────────────────────────────────────────────────────

		public static function ajax_connect() {
			self::guard();
			$result = self::connect();
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			wp_send_json_success( array( 'connected' => true ) );
		}

		public static function ajax_disconnect() {
			self::guard();
			self::disconnect();
			wp_send_json_success( array( 'connected' => false ) );
		}

		public static function ajax_link_manual() {
			self::guard();
			$store_id = isset( $_POST['store_id'] ) ? sanitize_text_field( wp_unslash( $_POST['store_id'] ) ) : '';
			$result   = self::link_manual( $store_id );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			wp_send_json_success( array( 'store_id' => $store_id ) );
		}

		// ── Core ────────────────────────────────────────────────────────────

		/**
		 * Run the 1-click connect: provision WC REST keys, register the store
		 * with Storelly (sending the stable store UUID), enable the opt-in
		 * flags, and log consent. Idempotent — re-running when already
		 * registered is a no-op for the network calls.
		 *
		 * @return true|WP_Error
		 */
		public static function connect() {
			if ( ! class_exists( 'SPBWC_Storelly_Product_Builder_API' ) ) {
				return new WP_Error( 'spbwc_cloud_unavailable', __( 'Cloud connector is unavailable.', 'storelly-product-builder-for-woocommerce' ) );
			}

			try {
				// WC REST consumer keys so Storelly can read orders/products.
				SPBWC_Storelly_Product_Builder_API::spbwc_generate_key();
				// Register the store (obtains unauth_token). Sends store UUID.
				SPBWC_Storelly_Product_Builder_API::spbwc_create_user_storelly();
			} catch ( Exception $e ) {
				return new WP_Error( 'spbwc_cloud_connect_failed', $e->getMessage() );
			}

			// Verify registration actually succeeded before flipping flags.
			$keys = get_option( self::KEYS_OPTION, array() );
			if ( ! is_array( $keys ) || empty( $keys['unauth_token'] ) ) {
				$msg = ( is_array( $keys ) && ! empty( $keys['log'] ) )
					? (string) $keys['log']
					: __( 'Could not reach Storelly. Please check your connection and try again.', 'storelly-product-builder-for-woocommerce' );
				return new WP_Error( 'spbwc_cloud_register_failed', $msg );
			}

			// Enable both opt-ins (consent covers cloud PDF + order sync).
			$settings = get_option( self::SETTINGS_KEY, array() );
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}
			$settings['enable_api_sync']        = 'yes';
			$settings['enable_cloud2print_api'] = 'yes';
			update_option( self::SETTINGS_KEY, $settings );

			// GDPR consent log — who/when/what version. No IP stored.
			update_option(
				self::OPTION_CONSENT,
				array(
					'user_id' => (int) get_current_user_id(),
					'time'    => time(),
					'version' => defined( 'SPBWC_PB_VERSION' ) ? SPBWC_PB_VERSION : '',
					'scope'   => array( 'cloud_pdf', 'order_sync' ),
				),
				false
			);

			return true;
		}

		/**
		 * Opt back out: turn off both flags so no further data leaves the
		 * site. Registration keys are left in place (harmless without the
		 * flags) so re-connecting is instant; uninstall is what clears them.
		 */
		public static function disconnect() {
			$settings = get_option( self::SETTINGS_KEY, array() );
			if ( is_array( $settings ) ) {
				$settings['enable_api_sync']        = 'no';
				$settings['enable_cloud2print_api'] = 'no';
				update_option( self::SETTINGS_KEY, $settings );
			}
			delete_option( self::OPTION_CONSENT );
		}

		/**
		 * Manual re-link fallback (full DB wipe + domain change): the merchant
		 * pastes the Store ID shown in their Storelly dashboard so the server
		 * can attach this site to the existing store. Stored as a hint in the
		 * keys option; the server reconciles it on the next register call.
		 *
		 * @param string $store_id
		 * @return true|WP_Error
		 */
		public static function link_manual( $store_id ) {
			$store_id = trim( (string) $store_id );
			if ( '' === $store_id ) {
				return new WP_Error( 'spbwc_cloud_store_id_empty', __( 'Please enter your Store ID.', 'storelly-product-builder-for-woocommerce' ) );
			}
			$keys = get_option( self::KEYS_OPTION, array() );
			if ( ! is_array( $keys ) ) {
				$keys = array();
			}
			$keys['store_id'] = $store_id;
			update_option( self::KEYS_OPTION, $keys );
			return true;
		}

		/** Whether the merchant has connected (order-sync opt-in is on). */
		public static function is_connected() {
			$settings = get_option( self::SETTINGS_KEY, array() );
			return is_array( $settings )
				&& isset( $settings['enable_api_sync'] )
				&& 'yes' === $settings['enable_api_sync'];
		}

		protected static function guard() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
			}
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
			}
		}
	}

	SPBWC_Cloud_Connect::init();
}
