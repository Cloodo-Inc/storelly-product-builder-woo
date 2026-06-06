<?php
/**
 * Storelly admin menu polish (U0).
 *
 * Presentational only — does NOT change any slug, capability, callback or
 * route. It runs LATE on `admin_menu` (priority 9999, after every Storelly
 * submenu — including the marketplace at 200 — has registered) and:
 *
 *   1. Re-orders the existing submenu items into three task bands
 *      (BUILD / SELL / CONFIGURE) and injects non-clickable section headings.
 *   2. Swaps the washed-out PNG menu icon for a crisp inline SVG.
 *
 * Safety: the re-order is loss-proof — any submenu item not named in the
 * explicit order (e.g. a future page) is appended at the end, so nothing can
 * disappear from the menu even if a slug changes upstream.
 *
 * The fold of System Info / About / License / Setup Wizard / Emails into a
 * tabbed Settings page is a separate, later milestone (U2) because it touches
 * routing; this file deliberately leaves slugs alone.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Admin_Menu' ) ) {

	class SPBWC_Admin_Menu {

		/** Prefix for the fake heading slugs (targeted by CSS, never navigable). */
		const HEADING_PREFIX = 'spbwc-sep-';

		public static function init() {
			// 9999 → after every Storelly submenu registration (max seen is 200).
			add_action( 'admin_menu', array( __CLASS__, 'reorder' ), 9999 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
			// Headings are non-navigable; if one is ever reached anyway (keyboard,
			// middle-click, screen reader), bounce to Overview instead of WP's
			// "you are not allowed" page. CSS only blocks the mouse.
			add_action( 'admin_init', array( __CLASS__, 'guard_heading_slugs' ) );
		}

		/**
		 * Redirect the fake heading slugs to Overview so they never render an
		 * error page when accessed directly.
		 */
		public static function guard_heading_slugs() {
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			if ( '' !== $page && 0 === strpos( $page, self::HEADING_PREFIX ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=' . SPBWC_PB_OVERVIEW_SLUG ) );
				exit;
			}
		}

		/**
		 * Desired top-level order of the Storelly submenu, grouped into bands.
		 * A string is an existing submenu slug; an array is a section heading
		 * ['heading', 'LABEL', 'unique-suffix'].
		 *
		 * @return array<int, string|array>
		 */
		protected static function desired_order() {
			$opts   = defined( 'SPBWC_PB_OPTIONS_SLUG' ) ? SPBWC_PB_OPTIONS_SLUG : 'storelly-product-builder-for-woocommerce-options';
			$emails = defined( 'SPBWC_PB_EMAILS_SLUG' ) ? SPBWC_PB_EMAILS_SLUG : 'storelly-product-builder-for-woocommerce-emails';

			return array(
				SPBWC_PB_OVERVIEW_SLUG,

				array( 'heading', __( 'Build', 'storelly-product-builder-for-woocommerce' ), 'build' ),
				SPBWC_PB_BUILDER_SLUG,
				SPBWC_PB_VISUAL_BUILDER_SLUG,
				SPBWC_PB_PRODUCTS_SLUG,
				SPBWC_PB_TEMPLATE_LIBRARY_SLUG,
				$opts . '/manager-fonts',

				array( 'heading', __( 'Sell', 'storelly-product-builder-for-woocommerce' ), 'sell' ),
				SPBWC_PB_ORDERS_SLUG,
				'storelly-product-builder-for-woocommerce-custom-quotes',
				SPBWC_PB_QUOTES_SLUG,
				'storelly-product-builder-for-woocommerce-b2b-companies',
				'storelly-product-builder-for-woocommerce-b2b-pricing',

				array( 'heading', __( 'Configure', 'storelly-product-builder-for-woocommerce' ), 'configure' ),
				$opts,
				SPBWC_PB_LICENSE_SLUG,
				$emails,
				$opts . '/global-import',
			);
		}

		/**
		 * Re-order $submenu into bands + inject headings, and refresh the icon.
		 */
		public static function reorder() {
			global $submenu, $menu;

			$parent = defined( 'SPBWC_PB_OVERVIEW_SLUG' ) ? SPBWC_PB_OVERVIEW_SLUG : '';
			if ( '' === $parent ) {
				return;
			}

			self::set_icon( $menu, $parent );

			if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
				return;
			}

			// Index existing items by their slug (index 2).
			$by_slug = array();
			foreach ( $submenu[ $parent ] as $item ) {
				if ( isset( $item[2] ) ) {
					$by_slug[ $item[2] ] = $item;
				}
			}

			$ordered = array();
			$used    = array();

			foreach ( self::desired_order() as $entry ) {
				if ( is_array( $entry ) ) {
					// Section heading: non-navigable, styled by admin-menu.css.
					$ordered[] = array(
						'<span class="spbwc-nav-heading">' . esc_html( $entry[1] ) . '</span>',
						'read',
						self::HEADING_PREFIX . $entry[2],
					);
					continue;
				}
				if ( isset( $by_slug[ $entry ] ) ) {
					$ordered[] = $by_slug[ $entry ];
					$used[ $entry ] = true;
				}
			}

			// Loss-proof: append anything we did not explicitly place.
			foreach ( $submenu[ $parent ] as $item ) {
				$slug = isset( $item[2] ) ? $item[2] : '';
				if ( '' !== $slug && empty( $used[ $slug ] ) ) {
					$ordered[] = $item;
				}
			}

			$submenu[ $parent ] = array_values( $ordered );
		}

		/**
		 * Replace the top-level menu icon (index 6 of its $menu row) with a
		 * crisp inline SVG so it reads cleanly on the dark admin sidebar.
		 *
		 * @param array  $menu   The global $menu (by reference via global).
		 * @param string $parent Overview slug = the top-level menu slug.
		 */
		protected static function set_icon( &$menu, $parent ) {
			if ( empty( $menu ) || ! is_array( $menu ) ) {
				return;
			}
			$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23a7aaad' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polygon points='12 2 2 7 12 12 22 7 12 2'/><polyline points='2 17 12 22 22 17'/><polyline points='2 12 12 17 22 12'/></svg>";
			$uri = 'data:image/svg+xml;base64,' . base64_encode( str_replace( '%23', '#', $svg ) );
			foreach ( $menu as $key => $row ) {
				if ( isset( $row[2] ) && $row[2] === $parent && isset( $menu[ $key ][6] ) ) {
					$menu[ $key ][6] = $uri;
					break;
				}
			}
		}

		/** Tiny global stylesheet for the menu headings + count badges. */
		public static function enqueue() {
			if ( ! defined( 'SPBWC_PB_CSS_URL' ) || ! defined( 'SPBWC_PB_VERSION' ) ) {
				return;
			}
			wp_enqueue_style(
				'spbwc-admin-menu',
				SPBWC_PB_CSS_URL . 'admin-menu.css',
				array(),
				SPBWC_PB_VERSION
			);
		}
	}

	SPBWC_Admin_Menu::init();
}
