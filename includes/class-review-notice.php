<?php
/**
 * Review request notice (M9.2).
 *
 * Retention lever: politely ask happy merchants for a wordpress.org review —
 * but only once they've genuinely seen value, and never as a nag.
 *
 * Gate (all must hold):
 *   - Free OR paid, any plan (reviews help every tier).
 *   - Tenure: at least MIN_DAYS since first activation (spbwc_activated_at).
 *   - Achievement: onboarding is complete — i.e. they created an option group
 *     AND applied it to a product (SPBWC_Onboarding::is_onboarding_complete()).
 *     Asking before the "aha" moment just annoys.
 *   - Not already handled (reviewed / dismissed forever) and not snoozed.
 *
 * Shows ONE dismissible notice on the Storelly admin screens only (mirrors
 * SPBWC_Upsell_Notice::is_storelly_screen) — no site-wide nag. Three choices:
 *   - "Sure, I'll review"  → mark done forever, send to the reviews page.
 *   - "Maybe later"        → snooze SNOOZE_DAYS, ask again later.
 *   - "Already did / No"   → mark done forever, never ask again.
 *
 * Reuses the dismiss/option pattern of SPBWC_I18n_Notice and the usage signals
 * from SPBWC_Onboarding; adds no queries beyond what those already cache.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Review_Notice' ) ) {

	class SPBWC_Review_Notice {

		/** Timestamp once the merchant reviewed or permanently dismissed. */
		const OPTION_DONE   = 'spbwc_review_done';

		/** Timestamp of the last "Maybe later". */
		const OPTION_SNOOZE = 'spbwc_review_snooze';

		/** Single query arg carrying the chosen action: did | later | never. */
		const QUERY_ACTION  = 'spbwc_review_action';

		/** Nonce action for all three choices. */
		const NONCE         = 'spbwc_review_notice';

		/** Minimum tenure before the first ask. */
		const MIN_DAYS      = 14;

		/** How long "Maybe later" hides the notice. */
		const SNOOZE_DAYS   = 30;

		/** Public reviews page for the plugin. */
		const REVIEWS_URL   = 'https://wordpress.org/support/plugin/storelly-product-builder-for-woocommerce/reviews/#new-post';

		public static function init() {
			add_action( 'admin_notices', array( __CLASS__, 'maybe_render' ) );
			add_action( 'admin_init', array( __CLASS__, 'maybe_handle_action' ) );
		}

		/** True when every gate condition is satisfied. */
		protected static function should_show() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return false;
			}
			if ( ! self::is_storelly_screen() ) {
				return false;
			}
			if ( ! class_exists( 'SPBWC_Onboarding' ) ) {
				return false;
			}
			// Already reviewed / dismissed forever.
			if ( (int) get_option( self::OPTION_DONE, 0 ) > 0 ) {
				return false;
			}
			// Tenure gate — measured from first activation.
			$activated = SPBWC_Onboarding::get_activated_at();
			if ( $activated <= 0 || ( time() - $activated ) < ( self::MIN_DAYS * DAY_IN_SECONDS ) ) {
				return false;
			}
			// Achievement gate — they've actually set the plugin up and seen it work.
			if ( ! SPBWC_Onboarding::is_onboarding_complete() ) {
				return false;
			}
			// Snooze window after a "Maybe later".
			$snooze = (int) get_option( self::OPTION_SNOOZE, 0 );
			if ( $snooze && ( time() - $snooze ) < ( self::SNOOZE_DAYS * DAY_IN_SECONDS ) ) {
				return false;
			}
			return true;
		}

		public static function maybe_render() {
			if ( ! self::should_show() ) {
				return;
			}
			self::ensure_css();

			$did_url   = self::action_url( 'did' );
			$later_url = self::action_url( 'later' );
			$never_url = self::action_url( 'never' );
			?>
			<div class="notice notice-info is-dismissible spbwc-review-notice">
				<p class="spbwc-review-notice__title">
					<span class="dashicons dashicons-heart" aria-hidden="true"></span>
					<?php esc_html_e( 'Enjoying Storelly Product Builder?', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
				<p class="spbwc-review-notice__body">
					<?php esc_html_e( 'You\'ve got the builder up and running on your store — that\'s great to see! A quick review on WordPress.org helps other merchants find us and means a lot to our small team.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
				<p class="spbwc-review-notice__actions">
					<a class="button button-primary" href="<?php echo esc_url( $did_url ); ?>">
						<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
						<?php esc_html_e( 'Sure, I\'ll leave a review', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
					<a class="button-link spbwc-review-notice__spacer" href="<?php echo esc_url( $later_url ); ?>">
						<?php esc_html_e( 'Maybe later', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
					<a class="button-link" href="<?php echo esc_url( $never_url ); ?>">
						<?php esc_html_e( 'Already did / No thanks', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
				</p>
			</div>
			<?php
		}

		/**
		 * Lazy-enqueue the shared onboarding-notice stylesheet. Registers the
		 * design tokens too, since this notice can render on the WC product
		 * editor where the Storelly admin bundle isn't loaded. WP prints styles
		 * enqueued during admin_notices in the admin footer.
		 */
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

		/** Nonce-protected URL for one of the three choices. */
		protected static function action_url( $action ) {
			return wp_nonce_url(
				add_query_arg( self::QUERY_ACTION, $action ),
				self::NONCE
			);
		}

		/**
		 * Handle a choice. All three route here (same-site, nonce-checked):
		 *   did   → mark done forever, then send to the reviews page.
		 *   later → snooze, return to the current screen.
		 *   never → mark done forever, return to the current screen.
		 */
		public static function maybe_handle_action() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified just below before any state change.
			if ( empty( $_GET[ self::QUERY_ACTION ] ) ) {
				return;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( ! check_admin_referer( self::NONCE ) ) {
				return;
			}

			$action = sanitize_key( wp_unslash( $_GET[ self::QUERY_ACTION ] ) );
			$back   = remove_query_arg( array( self::QUERY_ACTION, '_wpnonce' ) );

			if ( 'did' === $action ) {
				update_option( self::OPTION_DONE, time(), false );
				// External URL is a fixed plugin constant (no user input), so a
				// plain redirect is safe; wp_safe_redirect would block the host.
				wp_redirect( self::REVIEWS_URL ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- fixed external constant, intentional off-site redirect to the reviews page.
				exit;
			}

			if ( 'never' === $action ) {
				update_option( self::OPTION_DONE, time(), false );
			} else { // 'later' (and any unexpected value) → snooze, ask again later.
				update_option( self::OPTION_SNOOZE, time(), false );
			}

			wp_safe_redirect( esc_url_raw( $back ) );
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
			return ( 'product' === $screen->id || 'edit-product' === $screen->id );
		}
	}

	SPBWC_Review_Notice::init();
}
