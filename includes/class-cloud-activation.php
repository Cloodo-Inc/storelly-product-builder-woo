<?php
/**
 * SPBWC Cloud Activation — connect-back flow (SPEC_FREEMIUM_V1_1 §6 / SPEC_ACCOUNT_PLAN_UX U3).
 *
 * Closed-loop premium activation: the merchant clicks "Choose plan" → we redirect to
 * app.storelly.com with a one-time `state` → they pay on Storelly's own gateway (the plugin is
 * payment-agnostic, D7) → Storelly redirects back with a one-time `token` → we verify `state`
 * and exchange the token server-to-server for the license (caps[]).
 *
 * Backend dependency: the `/connect` screen + token issuance + `/api/v1/license/exchange`
 * endpoint live on app.storelly.com (open item O-6). Until they exist, the exchange fails
 * gracefully and the merchant falls back to manual license-key entry / Sync on the Account &
 * Plan page — never a dead end, never a white screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Cloud_Activation' ) ) {

	class SPBWC_Cloud_Activation {

		/** Single-use CSRF/binding state for the redirect round-trip (15-min TTL). */
		const STATE_TRANSIENT = 'spbwc_activation_state';

		/** Nonce action for the checkout redirect handler. */
		const NONCE = 'spbwc_cloud_checkout';

		/** Transient holding the one-shot admin notice after a return. */
		const NOTICE_TRANSIENT = 'spbwc_activation_notice';

		public static function init() {
			add_action( 'admin_post_spbwc_cloud_checkout', array( __CLASS__, 'handle_checkout' ) );
			add_action( 'admin_init', array( __CLASS__, 'maybe_handle_return' ) );
			add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
		}

		/**
		 * Build the admin-post URL a "Choose plan" / "Unlock" CTA points to.
		 *
		 * @param string $plan e.g. 'cloud-standard-monthly' | 'cloud-b2b-monthly'
		 * @param string $ctx  optional upsell-by-intent context "cap:object_id"
		 * @return string
		 */
		public static function checkout_url( $plan, $ctx = '' ) {
			$args = array(
				'action' => 'spbwc_cloud_checkout',
				'plan'   => $plan,
			);
			if ( '' !== $ctx ) {
				$args['ctx'] = $ctx;
			}
			$url = add_query_arg( $args, admin_url( 'admin-post.php' ) );
			return wp_nonce_url( $url, self::NONCE );
		}

		/**
		 * admin-post handler: create the state, store it, redirect to app.storelly.com/connect.
		 */
		public static function handle_checkout() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ) );
			}
			check_admin_referer( self::NONCE );

			$plan = isset( $_GET['plan'] ) ? sanitize_key( wp_unslash( $_GET['plan'] ) ) : 'cloud-standard-monthly';
			$ctx  = isset( $_GET['ctx'] ) ? sanitize_text_field( wp_unslash( $_GET['ctx'] ) ) : '';

			$state = wp_generate_password( 32, false );
			set_transient(
				self::STATE_TRANSIENT,
				array( 'state' => $state, 'plan' => $plan, 'ctx' => $ctx ),
				15 * MINUTE_IN_SECONDS
			);

			$return_url = admin_url( 'admin.php?page=' . ( defined( 'SPBWC_PB_LICENSE_SLUG' ) ? SPBWC_PB_LICENSE_SLUG : '' ) );

			$connect = add_query_arg(
				array(
					'intent'      => 'checkout',
					'plan'        => $plan,
					'site_url'    => rawurlencode( home_url() ),
					'admin_email' => rawurlencode( get_option( 'admin_email' ) ),
					'return_url'  => rawurlencode( $return_url ),
					'state'       => $state,
					'ctx'         => $ctx,
				),
				rtrim( SPBWC_API_URL, '/' ) . '/connect'
			);

			wp_redirect( $connect ); // external, user-initiated.
			exit;
		}

		/**
		 * On the Account & Plan page, detect the return token, verify state, exchange it.
		 */
		public static function maybe_handle_return() {
			if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
				return;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- state token below is the CSRF check; nonce-equivalent.
			if ( empty( $_GET['spbwc_activation_token'] ) || empty( $_GET['state'] ) ) {
				return;
			}

			$token = sanitize_text_field( wp_unslash( $_GET['spbwc_activation_token'] ) );
			$state = sanitize_text_field( wp_unslash( $_GET['state'] ) );

			$stored = get_transient( self::STATE_TRANSIENT );
			delete_transient( self::STATE_TRANSIENT ); // single use, whatever the outcome

			if ( ! is_array( $stored ) || empty( $stored['state'] ) || ! hash_equals( (string) $stored['state'], $state ) ) {
				self::set_notice( 'error', __( 'Activation link expired or invalid. Please start again from the Plans section.', 'storelly-product-builder-for-woocommerce' ) );
				self::redirect_clean();
				return;
			}

			$result = self::exchange( $token );
			if ( is_wp_error( $result ) ) {
				self::set_notice(
					'error',
					/* translators: %s: error detail */
					sprintf( __( 'Could not activate automatically: %s. You can enter your license key manually below, or click Sync.', 'storelly-product-builder-for-woocommerce' ), $result->get_error_message() )
				);
			} else {
				self::set_notice( 'success', __( 'Cloud plan activated. Your premium features are now unlocked.', 'storelly-product-builder-for-woocommerce' ) );
			}
			self::redirect_clean();
		}

		/**
		 * Server-to-server token exchange. Persists the returned license on success.
		 *
		 * @param string $token
		 * @return true|WP_Error
		 */
		protected static function exchange( $token ) {
			$url  = rtrim( SPBWC_API_URL, '/' ) . '/api/v1/license/exchange';
			$resp = SPBWC_Storelly_HTTP::spbwc_post_data(
				$url,
				array( 'token' => $token, 'site_url' => home_url() )
			);

			if ( is_wp_error( $resp ) ) {
				return $resp;
			}
			if ( empty( $resp['license_key'] ) && empty( $resp['caps'] ) && empty( $resp['status'] ) ) {
				return new WP_Error(
					'spbwc_exchange_failed',
					isset( $resp['error'] ) ? sanitize_text_field( $resp['error'] ) : __( 'license server did not return a license', 'storelly-product-builder-for-woocommerce' )
				);
			}

			// Persist the activated key, then let the canonical sync map the entitlement shape.
			if ( ! empty( $resp['license_key'] ) ) {
				$api_keys = get_option( 'spbwc_connect_api_keys', array() );
				if ( ! is_array( $api_keys ) ) {
					$api_keys = array();
				}
				$api_keys['license_key'] = sanitize_text_field( $resp['license_key'] );
				update_option( 'spbwc_connect_api_keys', $api_keys );
			}

			if ( is_callable( array( 'SPBWC_License_Manager', 'sync_from_api' ) ) ) {
				SPBWC_License_Manager::sync_from_api();
			}
			return true;
		}

		// ── notice plumbing ───────────────────────────────────────────
		protected static function set_notice( $type, $msg ) {
			set_transient( self::NOTICE_TRANSIENT, array( 'type' => $type, 'msg' => $msg ), 60 );
		}

		public static function render_notice() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$notice = get_transient( self::NOTICE_TRANSIENT );
			if ( ! is_array( $notice ) || empty( $notice['msg'] ) ) {
				return;
			}
			delete_transient( self::NOTICE_TRANSIENT );
			$class = ( 'success' === ( $notice['type'] ?? '' ) ) ? 'notice-success' : 'notice-error';
			printf(
				'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $class ),
				esc_html( $notice['msg'] )
			);
		}

		protected static function redirect_clean() {
			$url = remove_query_arg( array( 'spbwc_activation_token', 'state', 'ctx' ) );
			wp_safe_redirect( $url );
			exit;
		}
	}

	SPBWC_Cloud_Activation::init();
}
