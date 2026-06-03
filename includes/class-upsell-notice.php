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

			// Contextual message: lead with whichever limit was hit.
			if ( $hit_products ) {
				$headline = sprintf(
					/* translators: 1: linked product count, 2: Free plan product limit. */
					__( 'You\'re using %1$d of %2$d products on the Free plan', 'storelly-product-builder-for-woocommerce' ),
					(int) $linked,
					(int) $max_products
				);
			} else {
				$headline = sprintf(
					/* translators: 1: pricing option group count, 2: Free plan option-group limit. */
					__( 'You\'re using %1$d of %2$d option groups on the Free plan', 'storelly-product-builder-for-woocommerce' ),
					(int) $pricing,
					(int) $max_pricing
				);
			}

			$license_url = admin_url( 'admin.php?page=' . SPBWC_PB_LICENSE_SLUG );
			$dismiss_url = wp_nonce_url( add_query_arg( self::NONCE_QUERY, '1' ), self::NONCE_QUERY );
			?>
			<div class="notice notice-info is-dismissible spbwc-upsell-notice">
				<p style="font-weight:600;margin-bottom:4px;">
					<span class="dashicons dashicons-star-filled" aria-hidden="true" style="color:#f59e0b;"></span>
					<?php echo esc_html( $headline ); ?>
				</p>
				<p style="margin-top:0;">
					<?php esc_html_e( 'Upgrade to unlock unlimited products and option groups, premium templates and priority support. Your existing setup stays exactly as it is.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $license_url ); ?>">
						<?php esc_html_e( 'See upgrade options', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
					<a class="button-link" href="<?php echo esc_url( $dismiss_url ); ?>" style="margin-left:8px;">
						<?php esc_html_e( 'Maybe later', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
				</p>
			</div>
			<?php
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
