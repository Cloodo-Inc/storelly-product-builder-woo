<?php
/**
 * Plugin Language UX surfaces.
 *
 *   1. A dismissible welcome NOTICE shown ONCE on the WP Dashboard
 *      (`index.php`) after activation. Onboarding push — "hey, plugin
 *      ships in 15 languages, here's how to switch." Dismiss is sticky
 *      (saved as a site option).
 *
 *   2. An always-visible WIDGET embedded in the Storelly Overview and
 *      General Settings pages — a persistent reference card showing
 *      current locale, layout direction, bundled language count, and
 *      links to change the language / contribute translations.
 *
 * Why split: showing both on the same screen is noisy (the user pointed
 * this out). Notice = onboarding push; Widget = persistent reference.
 * No screen renders both.
 *
 * Styles live in static/css/i18n-widget.css and use the existing design
 * tokens — no inline `style=` attributes here.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_I18n_Notice' ) ) {

	class SPBWC_I18n_Notice {

		const OPTION_KEY  = 'spbwc_i18n_notice_dismissed';
		const NONCE_QUERY = 'spbwc_dismiss_i18n_notice';

		/** Locales we ship translations for. Keep in sync with /languages. */
		const SUPPORTED_LOCALES = array(
			'vi',
			'fr_FR',
			'de_DE',
			'es_ES',
			'pt_BR',
			'it_IT',
			'ja',
			'zh_CN',
			'ru_RU',
			'ar',
			'nl_NL',
			'pl_PL',
			'tr_TR',
			'sv_SE',
			'id_ID',
		);

		public static function init() {
			add_action( 'admin_notices', array( __CLASS__, 'maybe_render' ) );
			add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss' ) );
		}

		/**
		 * Render the dismissible welcome notice on the WP Dashboard only.
		 * Overview + Settings show the always-visible widget instead, so
		 * users never see both surfaces stacked.
		 */
		public static function maybe_render() {
			if ( get_option( self::OPTION_KEY ) ) {
				return;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( ! self::is_dashboard_screen() ) {
				return;
			}

			self::ensure_css();

			$locale         = determine_locale();
			$is_english     = ( 0 === strpos( $locale, 'en' ) );
			$is_supported   = in_array( $locale, self::SUPPORTED_LOCALES, true );
			$lang_settings  = admin_url( 'options-general.php#WPLANG' );
			$profile_lang   = admin_url( 'profile.php#language' );
			$contribute_url = 'https://translate.wordpress.org/projects/wp-plugins/storelly-product-builder-for-woocommerce/';
			$dismiss_url    = wp_nonce_url(
				add_query_arg( self::NONCE_QUERY, '1' ),
				self::NONCE_QUERY
			);

			if ( $is_english ) {
				$body = esc_html__( 'Storelly Product Builder ships with translations for 15 languages. Switch your WordPress Site Language and the plugin admin will follow automatically — no extra setup.', 'storelly-product-builder-for-woocommerce' );
			} elseif ( $is_supported ) {
				$body = sprintf(
					/* translators: %s: current locale code, e.g. vi or fr_FR. */
					esc_html__( 'Storelly Product Builder is running in your language (%s). Help us reach 100%% coverage by contributing the missing strings.', 'storelly-product-builder-for-woocommerce' ),
					esc_html( $locale )
				);
			} else {
				$body = sprintf(
					/* translators: %s: current locale code, e.g. ko_KR. */
					esc_html__( 'Storelly Product Builder ships with translations for 15 languages but not yet your locale (%s). Help translate so other merchants in your region see the plugin in their language.', 'storelly-product-builder-for-woocommerce' ),
					esc_html( $locale )
				);
			}

			?>
			<div class="notice notice-info is-dismissible spbwc-i18n-notice">
				<p class="spbwc-i18n-notice__title">
					<span class="dashicons dashicons-translation" aria-hidden="true"></span>
					<?php esc_html_e( 'Storelly Product Builder · 15 languages out of the box', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
				<p class="spbwc-i18n-notice__body"><?php echo wp_kses_post( $body ); ?></p>
				<p class="spbwc-i18n-notice__actions">
					<a class="button button-primary" href="<?php echo esc_url( $lang_settings ); ?>">
						<?php esc_html_e( 'Change Site Language', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( $profile_lang ); ?>">
						<?php esc_html_e( 'My Profile Language', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
					<a class="button button-link" href="<?php echo esc_url( $contribute_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Help Translate', 'storelly-product-builder-for-woocommerce' ); ?>
						<span class="dashicons dashicons-external" aria-hidden="true"></span>
					</a>
					<a class="spbwc-i18n-notice__dismiss-link" href="<?php echo esc_url( $dismiss_url ); ?>">
						<?php esc_html_e( 'Got it · don\'t show again', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
				</p>
			</div>
			<?php
		}

		/**
		 * Render the always-visible language card.
		 * Called from views/overview.php and views/menu-settings.php.
		 */
		public static function render_language_widget() {
			self::ensure_css();

			$locale         = determine_locale();
			$is_english     = ( 0 === strpos( $locale, 'en' ) );
			$is_supported   = in_array( $locale, self::SUPPORTED_LOCALES, true );
			$is_rtl_locale  = is_rtl();
			$bundled_count  = count( self::SUPPORTED_LOCALES );
			$lang_settings  = admin_url( 'options-general.php#WPLANG' );
			$profile_lang   = admin_url( 'profile.php#language' );
			$contribute_url = 'https://translate.wordpress.org/projects/wp-plugins/storelly-product-builder-for-woocommerce/';

			if ( $is_english ) {
				$status_label    = esc_html__( 'Source language', 'storelly-product-builder-for-woocommerce' );
				$block_modifier  = 'brand';
			} elseif ( 'vi' === $locale ) {
				$status_label    = esc_html__( 'Extended translation', 'storelly-product-builder-for-woocommerce' );
				$block_modifier  = 'success';
			} elseif ( $is_supported ) {
				$status_label    = esc_html__( 'Core translation', 'storelly-product-builder-for-woocommerce' );
				$block_modifier  = 'success';
			} else {
				$status_label    = esc_html__( 'Not yet translated', 'storelly-product-builder-for-woocommerce' );
				$block_modifier  = 'warning';
			}

			$details_id = 'spbwc-i18n-all-locales';
			?>
			<section class="spbwc-block spbwc-block--<?php echo esc_attr( $block_modifier ); ?> spbwc-lang-block">
				<header class="spbwc-block__head">
					<h3 class="spbwc-block__title">
						<span class="dashicons dashicons-translation" aria-hidden="true"></span>
						<?php esc_html_e( 'Plugin Language', 'storelly-product-builder-for-woocommerce' ); ?>
					</h3>
					<span class="spbwc-block__badge">
						<?php echo esc_html( $status_label ); ?>
					</span>
				</header>

				<div class="spbwc-block__body">
					<div class="spbwc-lang-block__grid">
						<div class="spbwc-lang-block__cell">
							<div class="spbwc-lang-block__cell-label">
								<?php esc_html_e( 'Current locale', 'storelly-product-builder-for-woocommerce' ); ?>
							</div>
							<div class="spbwc-lang-block__cell-value spbwc-lang-block__cell-value--code">
								<?php echo esc_html( $locale ); ?>
							</div>
						</div>
						<div class="spbwc-lang-block__cell">
							<div class="spbwc-lang-block__cell-label">
								<?php esc_html_e( 'Layout direction', 'storelly-product-builder-for-woocommerce' ); ?>
							</div>
							<div class="spbwc-lang-block__cell-value">
								<?php echo $is_rtl_locale
									? esc_html__( 'RTL (right-to-left)', 'storelly-product-builder-for-woocommerce' )
									: esc_html__( 'LTR (left-to-right)', 'storelly-product-builder-for-woocommerce' ); ?>
							</div>
						</div>
						<div class="spbwc-lang-block__cell">
							<div class="spbwc-lang-block__cell-label">
								<?php esc_html_e( 'Bundled languages', 'storelly-product-builder-for-woocommerce' ); ?>
							</div>
							<div class="spbwc-lang-block__cell-value">
								<?php
								printf(
									/* translators: %d: number of locales bundled with the plugin (not counting English source). */
									esc_html__( '%d + English source', 'storelly-product-builder-for-woocommerce' ),
									(int) $bundled_count
								);
								?>
							</div>
						</div>
					</div>

					<div id="<?php echo esc_attr( $details_id ); ?>" class="spbwc-lang-block__details" hidden>
						<strong><?php esc_html_e( '15 bundled locales', 'storelly-product-builder-for-woocommerce' ); ?></strong>
						<code>vi</code> Vietnamese (extended, ~210 strings) ·
						<code>fr_FR</code> French · <code>de_DE</code> German ·
						<code>es_ES</code> Spanish · <code>pt_BR</code> Portuguese (Brazil) ·
						<code>it_IT</code> Italian · <code>ja</code> Japanese ·
						<code>zh_CN</code> Chinese (Simplified) · <code>ru_RU</code> Russian ·
						<code>ar</code> Arabic (RTL) · <code>nl_NL</code> Dutch ·
						<code>pl_PL</code> Polish · <code>tr_TR</code> Turkish ·
						<code>sv_SE</code> Swedish · <code>id_ID</code> Indonesian
					</div>
				</div>

				<footer class="spbwc-block__foot spbwc-lang-block__foot">
					<div class="spbwc-lang-block__actions">
						<a class="button" href="<?php echo esc_url( $lang_settings ); ?>">
							<?php esc_html_e( 'Change Site Language', 'storelly-product-builder-for-woocommerce' ); ?>
						</a>
						<a class="button" href="<?php echo esc_url( $profile_lang ); ?>">
							<?php esc_html_e( 'Per-User Language', 'storelly-product-builder-for-woocommerce' ); ?>
						</a>
					</div>
					<div class="spbwc-lang-block__meta">
						<button type="button"
							class="spbwc-block__foot-link spbwc-lang-block__see-all"
							aria-expanded="false"
							aria-controls="<?php echo esc_attr( $details_id ); ?>"
							onclick="var el=document.getElementById('<?php echo esc_js( $details_id ); ?>');var open=el.hasAttribute('hidden');if(open){el.removeAttribute('hidden');this.setAttribute('aria-expanded','true');this.textContent='<?php echo esc_js( __( 'Hide all locales', 'storelly-product-builder-for-woocommerce' ) ); ?>';}else{el.setAttribute('hidden','');this.setAttribute('aria-expanded','false');this.textContent='<?php echo esc_js( __( 'See all 15 bundled locales', 'storelly-product-builder-for-woocommerce' ) ); ?>';}return false;">
							<?php esc_html_e( 'See all 15 bundled locales', 'storelly-product-builder-for-woocommerce' ); ?>
						</button>
						<a class="spbwc-block__foot-link" href="<?php echo esc_url( $contribute_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Help Translate', 'storelly-product-builder-for-woocommerce' ); ?>
							<span class="dashicons dashicons-external" aria-hidden="true"></span>
						</a>
					</div>
				</footer>
			</section>
			<?php
		}

		/**
		 * Lazy-enqueue the i18n widget stylesheet once per request, regardless
		 * of whether the welcome notice or the embedded widget is rendered
		 * first. Safe to call multiple times: wp_enqueue_style() de-dupes.
		 */
		protected static function ensure_css() {
			if ( ! defined( 'SPBWC_PB_CSS_URL' ) || ! defined( 'SPBWC_PB_VERSION' ) ) {
				return;
			}
			$handle = 'spbwc-i18n-widget';
			if ( ! wp_style_is( $handle, 'registered' ) ) {
				wp_register_style(
					$handle,
					SPBWC_PB_CSS_URL . 'i18n-widget.css',
					array(),
					SPBWC_PB_VERSION
				);
			}
			wp_enqueue_style( $handle );
		}

		/** True only on the WP Dashboard (index.php → screen id "dashboard"). */
		protected static function is_dashboard_screen() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			return ( $screen && 'dashboard' === $screen->id );
		}

		/**
		 * Handle the "Got it · don't show again" link click.
		 */
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
			update_option( self::OPTION_KEY, time(), false );
			wp_safe_redirect( esc_url_raw( remove_query_arg( array( self::NONCE_QUERY, '_wpnonce' ) ) ) );
			exit;
		}
	}

	SPBWC_I18n_Notice::init();
}
