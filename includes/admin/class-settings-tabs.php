<?php
/**
 * Settings tab shell (S2 / U2).
 *
 * Settings, Emails, Cloud & License, System, About and Setup Wizard read as ONE
 * tabbed Settings area, without moving any page: each keeps its own slug,
 * callback and asset enqueues (those are keyed on the page hook, so moving a
 * page would silently drop its CSS/JS). This class only:
 *
 *   1. Prints the shared tab bar under each page's hero (render()).
 *   2. Hides the moved pages from the Storelly submenu and keeps "Settings"
 *      highlighted while any of them is open.
 *   3. Accepts the spec's URL shape `?page=…-options&tab=license|emails|…` and
 *      redirects it to the real page, so deep links of either form work and
 *      every old bookmark keeps working unchanged.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Settings_Tabs' ) ) {

	class SPBWC_Settings_Tabs {

		/** Submenu class that hides an item from the Storelly menu (admin-menu.css). */
		const HIDDEN_CLASS = 'spbwc-menu-hidden';

		public static function init() {
			add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ) );
			add_filter( 'submenu_file', array( __CLASS__, 'highlight_settings' ), 10, 2 );
		}

		/**
		 * The tabs, in display order. 'page' is the real admin page slug.
		 *
		 * @return array<string, array{label: string, icon: string, page: string}>
		 */
		public static function tabs() {
			$opts = SPBWC_PB_OPTIONS_SLUG;
			return array(
				'general' => array(
					'label' => __( 'General', 'storelly-product-builder-for-woocommerce' ),
					'icon'  => 'dashicons-admin-settings',
					'page'  => $opts,
				),
				'emails'  => array(
					'label' => __( 'Emails', 'storelly-product-builder-for-woocommerce' ),
					'icon'  => 'dashicons-email-alt',
					'page'  => SPBWC_PB_EMAILS_SLUG,
				),
				'license' => array(
					'label' => __( 'Cloud & License', 'storelly-product-builder-for-woocommerce' ),
					'icon'  => 'dashicons-cloud',
					'page'  => SPBWC_PB_LICENSE_SLUG,
				),
				'setup'   => array(
					'label' => __( 'Setup Wizard', 'storelly-product-builder-for-woocommerce' ),
					'icon'  => 'dashicons-admin-tools',
					'page'  => $opts . '/global-import',
				),
				'system'  => array(
					'label' => __( 'System', 'storelly-product-builder-for-woocommerce' ),
					'icon'  => 'dashicons-info-outline',
					'page'  => $opts . '/system-infor',
				),
				'about'   => array(
					'label' => __( 'About', 'storelly-product-builder-for-woocommerce' ),
					'icon'  => 'dashicons-heart',
					'page'  => $opts . '/about',
				),
			);
		}

		/** Pages that live under Settings but are not the Settings page itself. */
		public static function moved_pages() {
			$pages = array();
			foreach ( self::tabs() as $key => $tab ) {
				if ( 'general' !== $key ) {
					$pages[] = $tab['page'];
				}
			}
			return $pages;
		}

		/** Admin URL of a tab. */
		public static function url( $key ) {
			$tabs = self::tabs();
			$page = isset( $tabs[ $key ] ) ? $tabs[ $key ]['page'] : SPBWC_PB_OPTIONS_SLUG;
			return admin_url( 'admin.php?page=' . $page );
		}

		/** Key of the tab for the page being viewed, or '' when not a Settings page. */
		public static function current() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page lookup for highlighting.
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			foreach ( self::tabs() as $key => $tab ) {
				if ( $tab['page'] === $page ) {
					return $key;
				}
			}
			return '';
		}

		/**
		 * Print the tab bar and the admin-notice anchor. Call right after the page
		 * hero, on a page that has no other .wp-header-end (WP would copy each
		 * notice after every marker).
		 *
		 * @param string $active Tab key to mark as current.
		 */
		public static function render( $active ) {
			?>
			<nav class="spbwc-settings-tabbar" aria-label="<?php esc_attr_e( 'Settings sections', 'storelly-product-builder-for-woocommerce' ); ?>">
				<?php foreach ( self::tabs() as $key => $tab ) : ?>
					<a class="spbwc-settings-tabbar__tab<?php echo $key === $active ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( self::url( $key ) ); ?>"
						<?php echo $key === $active ? 'aria-current="page"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>" aria-hidden="true"></span>
						<?php echo esc_html( $tab['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<?php // WP moves admin notices below this marker (they would land inside the hero otherwise). ?>
			<hr class="wp-header-end" />
			<?php
		}

		/**
		 * `?page=…-options&tab=<key>` for a moved page → the real page. Other
		 * query args (e.g. intent=cloud_pdf) are carried over.
		 */
		public static function maybe_redirect() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing, no state change.
			if ( ! isset( $_GET['page'], $_GET['tab'] ) || SPBWC_PB_OPTIONS_SLUG !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
				return;
			}
			$key  = sanitize_key( wp_unslash( $_GET['tab'] ) );
			$tabs = self::tabs();
			if ( 'general' === $key || ! isset( $tabs[ $key ] ) ) {
				return;
			}
			$args = array();
			foreach ( wp_unslash( $_GET ) as $name => $value ) {
				if ( ! in_array( $name, array( 'page', 'tab' ), true ) && is_scalar( $value ) ) {
					$args[ sanitize_key( $name ) ] = sanitize_text_field( $value );
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( add_query_arg( $args, self::url( $key ) ) );
			exit;
		}

		/**
		 * Keep "Settings" highlighted in the Storelly menu on every Settings tab.
		 *
		 * @param string|null $submenu_file Current submenu file.
		 * @param string      $parent_file  Current parent file.
		 * @return string|null
		 */
		public static function highlight_settings( $submenu_file, $parent_file ) {
			$current = self::current();
			if ( '' !== $current && 'general' !== $current ) {
				return SPBWC_PB_OPTIONS_SLUG;
			}
			return $submenu_file;
		}

	}

	SPBWC_Settings_Tabs::init();
}
