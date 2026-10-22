<?php
/**
 * Freemium upsell by intent.
 *
 * The free plan has no product or option-group limit, so nothing here counts
 * how much the store has built. The notice appears only after the merchant
 * touches a Storelly Cloud feature (print-ready PDF, order sync, ...): the
 * locked-feature error records that intent, and the notice then offers the
 * plan that unlocks exactly that feature. Dismissing it snoozes it for 30 days.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Upsell_Notice' ) ) {

	class SPBWC_Upsell_Notice {

		const OPTION_SNOOZE = 'spbwc_upsell_snooze';
		const OPTION_INTENT = 'spbwc_cloud_intent';
		const NONCE_QUERY   = 'spbwc_dismiss_upsell';
		const SNOOZE_DAYS   = 30;
		/** How long a touch of a Cloud feature keeps the notice relevant. */
		const INTENT_DAYS = 7;

		public static function init() {
			add_action( 'admin_notices', array( __CLASS__, 'maybe_render' ) );
			add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss' ) );
		}

		/**
		 * Remember that the merchant just reached for a Cloud feature.
		 *
		 * @param string $cap Capability they touched, e.g. 'cloud_pdf'.
		 */
		public static function note_intent( $cap ) {
			$cap = sanitize_key( $cap );
			if ( '' === $cap ) {
				return;
			}
			$current = get_option( self::OPTION_INTENT );
			if ( is_array( $current ) && isset( $current['cap'], $current['time'] ) && $current['cap'] === $cap && ( time() - (int) $current['time'] ) < HOUR_IN_SECONDS ) {
				return; // Already recorded; avoid a write per call.
			}
			update_option( self::OPTION_INTENT, array( 'cap' => $cap, 'time' => time() ), false );
		}

		/** The Cloud capability touched within the last INTENT_DAYS days, or ''. */
		protected static function recent_intent() {
			$intent = get_option( self::OPTION_INTENT );
			if ( ! is_array( $intent ) || empty( $intent['cap'] ) || empty( $intent['time'] ) ) {
				return '';
			}
			if ( ( time() - (int) $intent['time'] ) > ( self::INTENT_DAYS * DAY_IN_SECONDS ) ) {
				return '';
			}
			return sanitize_key( $intent['cap'] );
		}

		public static function maybe_render() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( ! self::is_storelly_screen() ) {
				return;
			}
			if ( ! class_exists( 'SPBWC_License_Manager' ) ) {
				return;
			}

			$license = SPBWC_License_Manager::get_current_license();
			$status  = is_array( $license ) && isset( $license['status'] ) ? $license['status'] : 'free';
			if ( 'free' !== $status ) {
				return; // Paid plans never see the upsell.
			}

			// Snooze window after a dismiss.
			$snooze = (int) get_option( self::OPTION_SNOOZE, 0 );
			if ( $snooze && ( time() - $snooze ) < ( self::SNOOZE_DAYS * DAY_IN_SECONDS ) ) {
				return;
			}

			// Only after the merchant reached for a Cloud feature; otherwise stay silent.
			$cap = self::recent_intent();
			if ( '' === $cap || SPBWC_License_Manager::can( $cap ) ) {
				return;
			}

			$label       = SPBWC_License_Manager::cap_label( $cap );
			$unlock_url  = SPBWC_License_Manager::upsell_url( $cap );
			$dismiss_url = wp_nonce_url( add_query_arg( self::NONCE_QUERY, '1' ), self::NONCE_QUERY );
			self::ensure_css();
			?>
			<div class="notice notice-info is-dismissible spbwc-upsell-notice">
				<p class="spbwc-upsell-notice__title">
					<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
					<?php
					/* translators: %s: name of a Storelly Cloud feature, e.g. "Print-ready PDF". */
					echo esc_html( sprintf( __( '%s is part of Storelly Cloud', 'storelly-product-builder-for-woocommerce' ), $label ) );
					?>
				</p>
				<p class="spbwc-upsell-notice__body">
					<?php esc_html_e( 'Your product options, builder, quotes and orders stay free, on your own site, with no product limit. A Cloud plan only adds the hosted extras.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
				<p class="spbwc-upsell-notice__actions">
					<a class="button button-primary" href="<?php echo esc_url( $unlock_url ); ?>">
						<?php esc_html_e( 'See plans', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
					<a class="button-link" href="<?php echo esc_url( $dismiss_url ); ?>">
						<?php esc_html_e( 'Maybe later', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
				</p>
			</div>
			<?php
		}

		/** Lazy-enqueue the shared onboarding-notice stylesheet (tokens + dashicons deps). */
		protected static function ensure_css() {
			if ( ! defined( 'SPBWC_PB_CSS_URL' ) || ! defined( 'SPBWC_PB_VERSION' ) ) {
				return;
			}
			if ( ! wp_style_is( 'spbwc-tokens', 'registered' ) ) {
				wp_register_style( 'spbwc-tokens', SPBWC_PB_CSS_URL . '_tokens.css', array(), SPBWC_PB_VERSION );
			}
			if ( ! wp_style_is( 'spbwc-onboarding-notices', 'registered' ) ) {
				wp_register_style( 'spbwc-onboarding-notices', SPBWC_PB_CSS_URL . 'onboarding-notices.css', array( 'spbwc-tokens', 'dashicons' ), SPBWC_PB_VERSION );
			}
			wp_enqueue_style( 'spbwc-onboarding-notices' );
		}

		public static function maybe_dismiss() {
			if ( empty( $_GET[ self::NONCE_QUERY ] ) ) {
				return;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( ! check_admin_referer( self::NONCE_QUERY ) ) {
				return;
			}
			update_option( self::OPTION_SNOOZE, time(), false );
			delete_option( self::OPTION_INTENT );
			wp_safe_redirect( esc_url_raw( remove_query_arg( array( self::NONCE_QUERY, '_wpnonce' ) ) ) );
			exit;
		}

		/** True on the Storelly admin screens (and the WC product editor). */
		protected static function is_storelly_screen() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || ! isset( $screen->id ) ) {
				return false;
			}
			if ( false !== strpos( $screen->id, 'storelly-product-builder-for-woocommerce' ) ) {
				return true;
			}
			// WooCommerce product list / editor — where merchants assign options.
			return ( 'product' === $screen->id || 'edit-product' === $screen->id );
		}
	}

	SPBWC_Upsell_Notice::init();
}
