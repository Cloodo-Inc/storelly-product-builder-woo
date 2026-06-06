<?php
/**
 * Account Menu normalizer — central ordering for the My-Account menu (User Menu M5).
 *
 * Seven feature classes each filter `woocommerce_account_menu_items` at the default
 * priority and several individually unset/re-append `customer-logout`, so the final
 * order depends on load order and the logout item gets juggled repeatedly. Rather
 * than rewrite all seven, this runs ONE late-priority pass over the finished array:
 *
 *   1. Pull the known Storelly endpoints out and re-insert them in a stable,
 *      grouped order (Designs → Quotes → B2B → Designer), right after the core
 *      WooCommerce items (dashboard, orders, …) which keep their relative order.
 *   2. Force `customer-logout` to the very end — a single, deterministic reposition.
 *
 * Array keys are unique, so duplicates are impossible by construction. The per-class
 * filters are left untouched (robust to other in-flight redesign work); their own
 * logout juggling becomes harmless because this pass is authoritative.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Account_Menu' ) ) {

	class SPBWC_Account_Menu {

		/**
		 * Canonical order + grouping of Storelly account endpoints.
		 * Group 1 Designs · Group 2 Quotes · Group 3 B2B · Group 4 Designer.
		 */
		const ORDER = array(
			'saved-designs',
			'reorders',
			'quotes',
			'brand-store',
			'team',
			'approval',
			'my-store',
		);

		public static function init() {
			// Priority 9999 → run after every per-class filter (default 10).
			add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'normalize' ), 9999 );
		}

		/**
		 * Re-order Storelly items into a stable group and pin logout last.
		 *
		 * @param array $items Menu items (slug => label).
		 * @return array
		 */
		public static function normalize( $items ) {
			if ( ! is_array( $items ) || empty( $items ) ) {
				return $items;
			}

			// Detach logout so it can be re-pinned last.
			$logout = null;
			if ( isset( $items['customer-logout'] ) ) {
				$logout = $items['customer-logout'];
				unset( $items['customer-logout'] );
			}

			// Pull known Storelly endpoints out in canonical order.
			$storelly = array();
			foreach ( self::ORDER as $slug ) {
				if ( isset( $items[ $slug ] ) ) {
					$storelly[ $slug ] = $items[ $slug ];
					unset( $items[ $slug ] );
				}
			}

			// Core/unknown items keep their order; Storelly group appended after;
			// logout pinned last. ('+' preserves keys; no dup keys are possible.)
			$items = $items + $storelly;
			if ( null !== $logout ) {
				$items['customer-logout'] = $logout;
			}

			/**
			 * Allow themes/add-ons to tweak the final, normalized account menu.
			 *
			 * @param array $items Final ordered menu items.
			 */
			return apply_filters( 'spbwc_account_menu_items', $items );
		}
	}

	SPBWC_Account_Menu::init();
}
