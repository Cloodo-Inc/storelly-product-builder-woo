<?php
/**
 * Onboarding & activation plumbing (M1).
 *
 * Foundation for the auto-first onboarding flow described in
 * docs/SPEC_ONBOARDING_ACTIVATION.md. This class owns the small,
 * cross-cutting state that the Welcome experience (M2), the auto
 * sample import (M3), the Woo migrate (M4) and the Cloud opt-in (M5)
 * all read:
 *
 *   - spbwc_activated_at : first-activation UNIX timestamp. Drives the
 *       "ask for a review after N days" gate later, and lets us tell a
 *       brand-new install apart from an upgrade.
 *   - spbwc_store_uuid   : a stable, per-store identifier. Generated once
 *       and reused so that — once Cloud is connected (M5) — reinstalling
 *       the plugin re-links the same Storelly store profile instead of
 *       creating a duplicate. NOTHING is sent anywhere here; this is a
 *       local value only. The network call happens after the explicit
 *       1-click Cloud consent (M5).
 *   - spbwc_onboarding_state : small array used by the Overview Welcome
 *       UI (dismissed flag, etc.).
 *
 * On activation we also drop a short-lived transient so the very next
 * admin page load can bounce the merchant straight into the Overview in
 * Welcome mode — no hunting through the menu. The redirect is suppressed
 * for bulk/network activation so we never hijack a multi-plugin action.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Onboarding' ) ) {

	class SPBWC_Onboarding {

		/** First-activation timestamp (UNIX). Autoload yes — read on most admin loads. */
		const OPT_ACTIVATED_AT = 'spbwc_activated_at';

		/** Stable per-store UUID. Autoload no — only needed on Overview / Cloud connect. */
		const OPT_STORE_UUID = 'spbwc_store_uuid';

		/** Onboarding UI state (array). Autoload yes — cheap, read on Overview. */
		const OPT_STATE = 'spbwc_onboarding_state';

		/** One-shot post-activation redirect flag. */
		const REDIRECT_TRANSIENT = 'spbwc_activation_redirect';

		/** Query arg that puts the Overview into Welcome mode. */
		const WELCOME_FLAG = 'spbwc-welcome';

		/** Nonce action for the "dismiss Welcome" link. */
		const NONCE_DISMISS = 'spbwc_dismiss_welcome';

		public static function init() {
			add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_after_activation' ) );
			add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss_welcome' ) );
		}

		/**
		 * Permanently dismiss the Overview Welcome surface. Sticky via the
		 * onboarding state option. Nonce + capability gated.
		 */
		public static function maybe_dismiss_welcome() {
			if ( empty( $_GET[ self::NONCE_DISMISS ] ) ) {
				return;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( ! check_admin_referer( self::NONCE_DISMISS ) ) {
				return;
			}
			self::update_state( array( 'dismissed' => true ) );
			wp_safe_redirect(
				esc_url_raw( remove_query_arg( array( self::NONCE_DISMISS, self::WELCOME_FLAG, '_wpnonce' ) ) )
			);
			exit;
		}

		/** URL that dismisses the Welcome surface (nonce-protected). */
		public static function get_dismiss_url() {
			return wp_nonce_url(
				add_query_arg( self::NONCE_DISMISS, '1' ),
				self::NONCE_DISMISS
			);
		}

		/**
		 * Called from the plugin activation hook (after install succeeds).
		 * Idempotent: safe on re-activation and inside the multisite
		 * per-blog loop. Does NOT touch the network or send any data.
		 */
		public static function on_activate() {
			// Stamp first-activation time once. An upgrade/re-activate keeps
			// the original date so the review gate measures real tenure.
			if ( ! get_option( self::OPT_ACTIVATED_AT ) ) {
				add_option( self::OPT_ACTIVATED_AT, time() );
			}

			// Persist the stable store UUID once and keep it. Local only.
			if ( ! get_option( self::OPT_STORE_UUID ) ) {
				add_option( self::OPT_STORE_UUID, self::derive_store_uuid(), '', false );
			}

			// Arm the one-shot Welcome redirect for the next admin page load.
			set_transient( self::REDIRECT_TRANSIENT, 1, MINUTE_IN_SECONDS );
		}

		/**
		 * Bounce to the Overview in Welcome mode exactly once after
		 * activation. Skipped for bulk activation, network activation, and
		 * non-managers so we never hijack another action or redirect a user
		 * who can't see the page anyway.
		 */
		public static function maybe_redirect_after_activation() {
			if ( ! get_transient( self::REDIRECT_TRANSIENT ) ) {
				return;
			}

			// Bulk plugin activation sets these — don't hijack it.
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only guard on core's own activation query args; no state change here.
			if ( isset( $_GET['activate-multi'] ) || isset( $_REQUEST['action'] ) && 'activate-selected' === $_REQUEST['action'] ) {
				delete_transient( self::REDIRECT_TRANSIENT );
				return;
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			// Consume the flag before redirecting so it only ever fires once.
			delete_transient( self::REDIRECT_TRANSIENT );

			if ( is_network_admin() || ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
				return;
			}

			$url = add_query_arg(
				array(
					'page'              => SPBWC_PB_OVERVIEW_SLUG,
					self::WELCOME_FLAG  => 1,
				),
				admin_url( 'admin.php' )
			);
			wp_safe_redirect( $url );
			exit;
		}

		/**
		 * True when the Overview should render its Welcome surface: either
		 * the merchant was just redirected here, or onboarding isn't finished
		 * and they haven't dismissed it.
		 */
		public static function is_welcome_mode() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view flag; no state change.
			if ( isset( $_GET[ self::WELCOME_FLAG ] ) ) {
				return true;
			}
			$state = self::get_state();
			if ( ! empty( $state['dismissed'] ) ) {
				return false;
			}
			return ! self::is_onboarding_complete();
		}

		/**
		 * Stable store UUID (local identifier; never auto-transmitted — only
		 * sent after the explicit Cloud consent in M5).
		 *
		 * A previously stored value always wins (kept across uninstall so a
		 * reinstall on the same DB re-links). When absent — e.g. a full DB
		 * wipe — we DERIVE it deterministically from the site URL + admin
		 * email, so the very same id is reproduced and the store re-links with
		 * no manual step (see docs/SPEC_M5_CLOUD_CONSENT.md §3.2).
		 */
		public static function get_store_uuid() {
			$uuid = get_option( self::OPT_STORE_UUID );
			if ( ! $uuid ) {
				$uuid = self::derive_store_uuid();
				add_option( self::OPT_STORE_UUID, $uuid, '', false );
			}
			return $uuid;
		}

		/**
		 * Deterministic store id from normalized site URL + admin email.
		 * Plain md5 (NOT wp_hash) on purpose: it must reproduce identically
		 * after a reinstall, independent of WP salts which a DB wipe could
		 * change. This is a non-secret identifier, not an auth token.
		 *
		 * @return string uuid-formatted (8-4-4-4-12) hex.
		 */
		protected static function derive_store_uuid() {
			$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			$path = (string) wp_parse_url( home_url(), PHP_URL_PATH );
			$norm = strtolower( $host ) . rtrim( $path, '/' );
			$mail = strtolower( trim( (string) get_option( 'admin_email' ) ) );
			$hex  = md5( $norm . '|' . $mail ); // 32 hex chars.
			return substr( $hex, 0, 8 ) . '-' . substr( $hex, 8, 4 ) . '-'
				. substr( $hex, 12, 4 ) . '-' . substr( $hex, 16, 4 ) . '-' . substr( $hex, 20, 12 );
		}

		/** First-activation timestamp (UNIX), or 0 if unknown (legacy install). */
		public static function get_activated_at() {
			return (int) get_option( self::OPT_ACTIVATED_AT, 0 );
		}

		/** Onboarding UI state array. */
		public static function get_state() {
			$state = get_option( self::OPT_STATE, array() );
			return is_array( $state ) ? $state : array();
		}

		public static function update_state( array $patch ) {
			$state = array_merge( self::get_state(), $patch );
			update_option( self::OPT_STATE, $state );
			return $state;
		}

		/**
		 * Compute the getting-started checklist. Each step is auto-detected
		 * from real store state so it stays honest even if the merchant did
		 * the work outside the Welcome flow.
		 *
		 * @return array<int,array{key:string,done:bool,label:string}>
		 */
		public static function get_checklist() {
			return array(
				array(
					'key'   => 'has_option_set',
					'done'  => self::count_option_sets() > 0,
					'label' => __( 'Create your first pricing option group', 'storelly-product-builder-for-woocommerce' ),
				),
				array(
					'key'   => 'has_linked_product',
					'done'  => self::count_linked_products() > 0,
					'label' => __( 'Apply it to a product', 'storelly-product-builder-for-woocommerce' ),
				),
				array(
					'key'   => 'cloud_connected',
					'done'  => self::is_cloud_connected(),
					'label' => __( 'Enable Cloud (high-quality print PDF)', 'storelly-product-builder-for-woocommerce' ),
				),
			);
		}

		/** Onboarding is "complete" once the core two steps are done. */
		public static function is_onboarding_complete() {
			return self::count_option_sets() > 0 && self::count_linked_products() > 0;
		}

		/** Count of option groups in the custom options table. */
		public static function count_option_sets() {
			global $wpdb;
			$table = $wpdb->prefix . 'storelly_product_builder_options';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- prefix-only custom table name, no user input.
			return (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );
		}

		/**
		 * Count of products linked to a Storelly option set. The authoritative
		 * per-product pointer is the `_spbwc_option_id` post meta (set wherever
		 * a product is assigned an option group — see class-admin-options.php
		 * and the Woo seed controller). Counting distinct products carrying a
		 * non-empty pointer stays HPOS-agnostic: products are a normal post
		 * type regardless of the orders storage backend.
		 */
		public static function count_linked_products() {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- single fixed meta key, no user input; counting distinct products with an option-set assignment.
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT pm.post_id)
					 FROM {$wpdb->postmeta} pm
					 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					 WHERE pm.meta_key = %s
					   AND pm.meta_value != ''
					   AND pm.meta_value != '0'
					   AND p.post_type = 'product'
					   AND p.post_status != 'trash'",
					'_spbwc_option_id'
				)
			);
		}

		/**
		 * Whether the merchant has opted into Cloud sync. True only after the
		 * explicit 1-click Cloud consent (M5) flips `enable_api_sync` to "yes"
		 * inside the `spbwc_pb_settings` option. Until then this is false and
		 * nothing has been transmitted. (Note: `enable_cloud2print_api` is a
		 * separate PDF-rendering toggle and is intentionally NOT used as the
		 * "connected" signal here.)
		 */
		public static function is_cloud_connected() {
			$settings = get_option( 'spbwc_pb_settings', array() );
			return is_array( $settings )
				&& isset( $settings['enable_api_sync'] )
				&& 'yes' === $settings['enable_api_sync'];
		}
	}

	SPBWC_Onboarding::init();
}
