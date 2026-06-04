<?php
/**
 * Freemium contextual upsell (M7).
 *
 * Stays quiet until it's genuinely relevant: only when the store is on the
 * Free plan AND has actually reached a plan limit (pricing option groups or
 * linked products). Then it shows ONE dismissible admin notice on the
 * Storelly screens explaining the value of upgrading — not a permanent nag.
 * Dismissing snoozes it for 30 days; it returns only if a limit is still hit.
 *
 * Reuses the dismiss/option pattern of SPBWC_I18n_Notice and the usage
 * counters from SPBWC_Onboarding, so it adds no new queries of its own beyond
 * the (cached) license read.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Upsell_Notice' ) ) {

	class SPBWC_Upsell_Notice {

		const OPTION_SNOOZE = 'spbwc_upsell_snooze';
		const NONCE_QUERY   = 'spbwc_dismiss_upsell';
		const SNOOZE_DAYS   = 30;

		public static function init() {
			add_action( 'admin_notices', array( __CLASS__, 'maybe_render' ) );
			add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss' ) );
		}

		public static function maybe_render() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( ! self::is_storelly_screen() ) {
				return;
			}
			if ( ! class_exists( 'SPBWC_License_Manager' ) || ! class_exists( 'SPBWC_Onboarding' ) ) {
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

			$max_pricing  = isset( $license['max_pricing_options'] ) ? (int) $license['max_pricing_options'] : 3;
			$max_products = isset( $license['max_products'] ) ? (int) $license['max_products'] : 5;
			$pricing      = SPBWC_Onboarding::count_option_sets();
			$linked       = SPBWC_Onboarding::count_linked_products();

			$hit_pricing  = ( $max_pricing > 0 && $pricing >= $max_pricing );
			$hit_products = ( $max_products > 0 && $linked >= $max_products );
			if ( ! $hit_pricing && ! $hit_products ) {
				return; // Not at a limit yet — stay silent.
			}

			// Contextual nudge: the merchant is actively using the builder, so
			// introduce Storelly Cloud. These counts are an engagement signal,
			// NOT a cap — the free plan has no product/option-group limit.
			if ( $hit_products ) {
				$headline = sprintf(
					/* translators: %d: number of products the merchant has set up with the builder. */
					_n( 'You\'ve set up %d product with Storelly', 'You\'ve set up %d products with Storelly', (int) $linked, 'storelly-product-builder-for-woocommerce' ),
					(int) $linked
				);
			} else {
				$headline = sprintf(
					/* translators: %d: number of pricing option groups the merchant has created. */
					_n( 'You\'ve created %d option group with Storelly', 'You\'ve created %d option groups with Storelly', (int) $pricing, 'storelly-product-builder-for-woocommerce' ),
					(int) $pricing
				);
			}

			$license_url = admin_url( 'admin.php?page=' . SPBWC_PB_LICENSE_SLUG );
			$dismiss_url = wp_nonce_url( add_query_arg( self::NONCE_QUERY, '1' ), self::NONCE_QUERY );
			self::ensure_css();
			?>
			<div class="notice notice-info is-dismissible spbwc-upsell-notice">
				<p class="spbwc-upsell-notice__title">
					<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
					<?php echo esc_html( $headline ); ?>
				</p>
				<p class="spbwc-upsell-notice__body">
					<?php esc_html_e( 'Add a Storelly Cloud plan to enable print-ready PDF rendering, order sync and dashboard analytics. Your builder, quotes and custom orders stay free and unchanged.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
				<p class="spbwc-upsell-notice__actions">
					<a class="button button-primary" href="<?php echo esc_url( $license_url ); ?>">
						<?php esc_html_e( 'See upgrade options', 'storelly-product-builder-for-woocommerce' ); ?>
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
