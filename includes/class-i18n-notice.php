<?php
/**
 * One-shot welcome notice that tells the admin the plugin is multilingual
 * and how to switch to their language.
 *
 * Shows on plugin admin pages and the WordPress dashboard until the admin
 * clicks "Got it" (dismiss). Dismissal is recorded in a site-wide option
 * so it doesn't reappear after a page reload.
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
		 * Render the notice if (a) not yet dismissed, (b) current user can
		 * manage options, and (c) we are on a plugin page or the WP dashboard.
		 */
		public static function maybe_render() {
			if ( get_option( self::OPTION_KEY ) ) {
				return;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( ! self::is_relevant_screen() ) {
				return;
			}

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

			// Build the situational body line.
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
			<div class="notice notice-info is-dismissible spbwc-i18n-notice" style="border-left-color:#1971c2;">
				<p>
					<strong>
						<span class="dashicons dashicons-translation" aria-hidden="true" style="color:#1971c2;"></span>
						<?php esc_html_e( 'Storelly Product Builder · 15 languages out of the box', 'storelly-product-builder-for-woocommerce' ); ?>
					</strong>
				</p>
				<p style="max-width:780px;"><?php echo wp_kses_post( $body ); ?></p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $lang_settings ); ?>">
						<?php esc_html_e( 'Change Site Language', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( $profile_lang ); ?>">
						<?php esc_html_e( 'My Profile Language', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
					<a class="button button-link" href="<?php echo esc_url( $contribute_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Help Translate', 'storelly-product-builder-for-woocommerce' ); ?>
						<span class="dashicons dashicons-external" aria-hidden="true" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span>
					</a>
					<a class="button button-link" href="<?php echo esc_url( $dismiss_url ); ?>" style="color:#646970;">
						<?php esc_html_e( 'Got it · don\'t show again', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
				</p>
			</div>
			<?php
		}

		/**
		 * Only show on plugin admin pages or the main WordPress dashboard,
		 * to avoid spamming the notice on every admin screen.
		 */
		protected static function is_relevant_screen() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen ) {
				return false;
			}

			// Main WordPress dashboard (index.php → screen id "dashboard").
			if ( 'dashboard' === $screen->id ) {
				return true;
			}

			// Any Storelly Builder admin page — top-level "toplevel_page_storelly..."
			// or any submenu starting with "storelly-builder_page_storelly...".
			if ( false !== strpos( $screen->id, 'storelly-product-builder-for-woocommerce' )
				|| false !== strpos( $screen->id, 'storelly-builder' )
			) {
				return true;
			}

			return false;
		}

		/**
		 * Render an always-visible language widget for the Overview Dashboard.
		 * Different from the dismissible welcome notice: this is a reference
		 * card that shows what locale the plugin is currently rendering in
		 * and how to change it.
		 */
		public static function render_language_widget() {
			$locale         = determine_locale();
			$is_english     = ( 0 === strpos( $locale, 'en' ) );
			$is_supported   = in_array( $locale, self::SUPPORTED_LOCALES, true );
			$is_rtl_locale  = is_rtl();
			$total_locales  = count( self::SUPPORTED_LOCALES ) + 1; // +1 for English source.
			$lang_settings  = admin_url( 'options-general.php#WPLANG' );
			$profile_lang   = admin_url( 'profile.php#language' );
			$contribute_url = 'https://translate.wordpress.org/projects/wp-plugins/storelly-product-builder-for-woocommerce/';

			// Status label + colour hint.
			if ( $is_english ) {
				$status_label = esc_html__( 'Source language', 'storelly-product-builder-for-woocommerce' );
				$status_tone  = 'info';
			} elseif ( 'vi' === $locale ) {
				$status_label = esc_html__( 'Extended translation', 'storelly-product-builder-for-woocommerce' );
				$status_tone  = 'success';
			} elseif ( $is_supported ) {
				$status_label = esc_html__( 'Core translation', 'storelly-product-builder-for-woocommerce' );
				$status_tone  = 'success';
			} else {
				$status_label = esc_html__( 'Not yet translated', 'storelly-product-builder-for-woocommerce' );
				$status_tone  = 'warn';
			}

			?>
			<div class="spbwc-block spbwc-block--language" style="margin-top:16px;">
				<header class="spbwc-block__head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
					<h3 class="spbwc-block__title" style="margin:0;display:flex;align-items:center;gap:8px;">
						<span class="dashicons dashicons-translation" aria-hidden="true" style="color:#1971c2;"></span>
						<?php esc_html_e( 'Plugin Language', 'storelly-product-builder-for-woocommerce' ); ?>
					</h3>
					<span class="spbwc-pill spbwc-pill--<?php echo esc_attr( $status_tone ); ?>"
						style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;<?php echo 'success' === $status_tone ? 'background:#e7f5ee;color:#1f7a3a;' : ( 'warn' === $status_tone ? 'background:#fff4e5;color:#7a4a00;' : 'background:#e7f0fa;color:#1971c2;' ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</span>
				</header>
				<div class="spbwc-block__body" style="padding:12px 0;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;">
					<div>
						<div style="font-size:11px;text-transform:uppercase;color:#646970;letter-spacing:.4px;">
							<?php esc_html_e( 'Current locale', 'storelly-product-builder-for-woocommerce' ); ?>
						</div>
						<div style="font-size:15px;font-weight:600;font-family:Menlo,Consolas,monospace;">
							<?php echo esc_html( $locale ); ?>
						</div>
					</div>
					<div>
						<div style="font-size:11px;text-transform:uppercase;color:#646970;letter-spacing:.4px;">
							<?php esc_html_e( 'Layout direction', 'storelly-product-builder-for-woocommerce' ); ?>
						</div>
						<div style="font-size:15px;font-weight:600;">
							<?php echo $is_rtl_locale ? esc_html__( 'RTL (right-to-left)', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'LTR (left-to-right)', 'storelly-product-builder-for-woocommerce' ); ?>
						</div>
					</div>
					<div>
						<div style="font-size:11px;text-transform:uppercase;color:#646970;letter-spacing:.4px;">
							<?php esc_html_e( 'Bundled languages', 'storelly-product-builder-for-woocommerce' ); ?>
						</div>
						<div style="font-size:15px;font-weight:600;">
							<?php
							printf(
								/* translators: %d: number of bundled languages including English. */
								esc_html__( '%d languages', 'storelly-product-builder-for-woocommerce' ),
								(int) $total_locales
							);
							?>
						</div>
					</div>
				</div>
				<footer class="spbwc-block__foot" style="padding-top:8px;border-top:1px solid #f0f0f1;display:flex;flex-wrap:wrap;gap:8px;">
					<a class="button" href="<?php echo esc_url( $lang_settings ); ?>">
						<?php esc_html_e( 'Change Site Language', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( $profile_lang ); ?>">
						<?php esc_html_e( 'Per-User Language', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
					<a class="button button-link" href="#" onclick="document.getElementById('spbwc-i18n-all-locales').style.display='block';this.style.display='none';return false;">
						<?php esc_html_e( 'See all 15 bundled locales', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
					<a class="button button-link" href="<?php echo esc_url( $contribute_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Help Translate', 'storelly-product-builder-for-woocommerce' ); ?>
						<span class="dashicons dashicons-external" aria-hidden="true" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span>
					</a>
				</footer>
				<div id="spbwc-i18n-all-locales" style="display:none;margin-top:12px;padding:12px;background:#f6f7f7;border-radius:6px;font-size:13px;line-height:1.7;">
					<strong><?php esc_html_e( '15 bundled locales:', 'storelly-product-builder-for-woocommerce' ); ?></strong><br>
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
			<?php
		}

		/**
		 * Handle the "Got it · don't show again" click.
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
