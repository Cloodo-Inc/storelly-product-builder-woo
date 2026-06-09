<?php
/**
 * Account Shell — shared chrome for Storelly My-Account endpoints (User Menu M4).
 *
 * Every plugin account endpoint (Saved designs, Quotes, Designer Store, and the
 * B2B pages) renders its own inline markup, so the portal looks inconsistent and
 * is not theme-overridable. This helper emits one consistent header/body wrapper
 * via theme-overridable templates (templates/account/shell-header.php +
 * shell-footer.php) and auto-enqueues the tokenised shell stylesheet.
 *
 * Usage:
 *   SPBWC_Account_Shell::open( array( 'title' => __( 'Saved designs', ... ) ) );
 *   // ... endpoint content ...
 *   SPBWC_Account_Shell::close();
 *
 * Theme override: copy templates to yourtheme/storelly/account/.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Account_Shell' ) ) {

	class SPBWC_Account_Shell {

		const THEME_PATH = 'storelly';

		/**
		 * Open the account shell: enqueue assets + render header/breadcrumb.
		 *
		 * @param array $args {
		 *     @type string $title      Page title.
		 *     @type string $subtitle   Optional sub-heading.
		 *     @type string $actions    Optional pre-escaped actions HTML (top-right).
		 *     @type array  $breadcrumb Optional [ ['label'=>..,'url'=>..], ... ].
		 * }
		 */
		public static function open( $args = array() ) {
			self::enqueue();
			$args = wp_parse_args(
				$args,
				array(
					'title'      => '',
					'eyebrow'    => '',
					'subtitle'   => '',
					'actions'    => '',
					'breadcrumb' => array(),
				)
			);
			wc_get_template(
				'account/shell-header.php',
				$args,
				self::THEME_PATH . '/',
				self::default_path()
			);
		}

		/** Close the account shell. */
		public static function close() {
			wc_get_template(
				'account/shell-footer.php',
				array(),
				self::THEME_PATH . '/',
				self::default_path()
			);
		}

		/**
		 * Render a friendly empty-state block with an optional primary CTA
		 * (User Menu G1). Use inside an endpoint when there is nothing to list.
		 *
		 * @param array $args {
		 *     @type string $title     Headline (e.g. "No saved designs yet").
		 *     @type string $text      Supporting one-liner.
		 *     @type string $cta_label Primary button label (optional).
		 *     @type string $cta_url   Primary button URL (optional).
		 *     @type string $icon      Inline SVG/emoji markup (optional).
		 * }
		 */
		public static function empty_state( $args = array() ) {
			$args = wp_parse_args(
				$args,
				array(
					'title'     => '',
					'text'      => '',
					'cta_label' => '',
					'cta_url'   => '',
					'icon'      => '',
				)
			);
			echo '<div class="spbwc-account__empty">';
			if ( '' !== $args['icon'] ) {
				echo '<div class="spbwc-account__empty-icon" aria-hidden="true">' . wp_kses_post( $args['icon'] ) . '</div>';
			}
			if ( '' !== $args['title'] ) {
				echo '<p class="spbwc-account__empty-title">' . esc_html( $args['title'] ) . '</p>';
			}
			if ( '' !== $args['text'] ) {
				echo '<p class="spbwc-account__empty-text">' . esc_html( $args['text'] ) . '</p>';
			}
			if ( '' !== $args['cta_label'] && '' !== $args['cta_url'] ) {
				echo '<a class="spbwc-account__empty-cta" href="' . esc_url( $args['cta_url'] ) . '">' . esc_html( $args['cta_label'] ) . '</a>';
			}
			echo '</div>';
		}

		/** Plugin templates/ dir, with trailing slash. */
		protected static function default_path() {
			if ( defined( 'SPBWC_PB_PLUGIN_DIR' ) ) {
				return trailingslashit( SPBWC_PB_PLUGIN_DIR ) . 'templates/';
			}
			return trailingslashit( dirname( __DIR__ ) ) . 'templates/';
		}

		/** Register + enqueue the tokenised shell stylesheet (idempotent). */
		public static function enqueue() {
			if ( ! defined( 'SPBWC_PB_CSS_URL' ) ) {
				return;
			}
			$ver = defined( 'SPBWC_PB_VERSION' ) ? SPBWC_PB_VERSION : false;
			if ( ! wp_style_is( 'spbwc-tokens-storefront', 'registered' ) ) {
				wp_register_style( 'spbwc-tokens-storefront', SPBWC_PB_CSS_URL . '_tokens-storefront.css', array(), $ver );
			}
			if ( ! wp_style_is( 'spbwc-account-shell', 'registered' ) ) {
				$name = is_rtl() ? 'account-shell-rtl' : 'account-shell';
				wp_register_style( 'spbwc-account-shell', self::css_url( $name ), array( 'spbwc-tokens-storefront' ), $ver );
			}
			wp_enqueue_style( 'spbwc-tokens-storefront' );
			wp_enqueue_style( 'spbwc-account-shell' );
		}

		/**
		 * Resolve a storefront CSS file URL, preferring the minified build when it
		 * exists and SCRIPT_DEBUG is off (User Menu M6). Falls back to source so a
		 * missing .min never breaks the page. Shared so other endpoints (launcher)
		 * can opt into minified assets too.
		 *
		 * @param string $name File name without extension (e.g. 'account-shell').
		 * @return string Stylesheet URL.
		 */
		public static function css_url( $name ) {
			$name = (string) $name;
			$use_min = ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
			if ( $use_min && defined( 'SPBWC_PB_PLUGIN_DIR' )
				&& file_exists( SPBWC_PB_PLUGIN_DIR . 'static/css/' . $name . '.min.css' ) ) {
				return SPBWC_PB_CSS_URL . $name . '.min.css';
			}
			return SPBWC_PB_CSS_URL . $name . '.css';
		}

		/**
		 * Resolve a storefront JS file URL, preferring the minified build when it
		 * exists and SCRIPT_DEBUG is off (User Menu G5). Falls back to source.
		 *
		 * @param string $name File name without extension (e.g. 'launcher').
		 * @return string Script URL.
		 */
		public static function js_url( $name ) {
			$name = (string) $name;
			$use_min = ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
			if ( $use_min && defined( 'SPBWC_PB_PLUGIN_DIR' )
				&& file_exists( SPBWC_PB_PLUGIN_DIR . 'static/js/' . $name . '.min.js' ) ) {
				return SPBWC_PB_JS_URL . $name . '.min.js';
			}
			return SPBWC_PB_JS_URL . $name . '.js';
		}
	}
}
