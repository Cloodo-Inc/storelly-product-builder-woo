<?php
/**
 * Template Library admin page — register submenu, render grid, run the
 * idempotent DB column migration on admin_init.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Template_Library_Admin' ) ) {

	class SPBWC_Template_Library_Admin {

		protected static $instance = null;

		private const MIGRATION_OPTION = 'spbwc_template_columns_migrated_v1';

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function init() {
			add_action( 'spbwc_pb_menu', array( $this, 'register_menu' ), 20 );
			add_action( 'admin_init', array( $this, 'maybe_migrate_template_columns' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

		public function register_menu() {
			if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
				return;
			}
			add_submenu_page(
				SPBWC_PB_OVERVIEW_SLUG,
				esc_html__( 'Options Templates', 'storelly-product-builder-for-woocommerce' ),
				esc_html__( 'Options Templates', 'storelly-product-builder-for-woocommerce' ),
				'manage_options',
				SPBWC_PB_TEMPLATE_LIBRARY_SLUG,
				array( $this, 'render_page' ),
				4 // Just after Pricing Options (position 3 in the existing menu order).
			);
		}

		public function enqueue_scripts( $hook ) {
			if ( 'storelly-builder_page_' . SPBWC_PB_TEMPLATE_LIBRARY_SLUG !== $hook ) {
				return;
			}
			// WC admin styles bring Select2 visuals matching the rest of WP-admin.
			wp_enqueue_style( 'woocommerce_admin_styles' );

			// Ensure design-token base sheets are registered before we depend on them.
			if ( ! wp_style_is( 'spbwc-tokens', 'registered' ) ) {
				wp_register_style( 'spbwc-tokens', SPBWC_PB_CSS_URL . '_tokens.css', array(), SPBWC_PB_VERSION );
			}
			if ( ! wp_style_is( 'spbwc-admin-ui', 'registered' ) ) {
				wp_register_style( 'spbwc-admin-ui', SPBWC_PB_CSS_URL . 'storelly-admin-ui.css', array( 'spbwc-tokens', 'dashicons' ), SPBWC_PB_VERSION );
			}

			// Use file mtime as version so dev changes are always reflected without a
			// manual version bump (production builds pin version via SPBWC_PB_VERSION).
			$css_file = SPBWC_PB_PLUGIN_DIR . 'static/css/template-library.css';
			$css_ver  = file_exists( $css_file ) ? filemtime( $css_file ) : SPBWC_PB_VERSION;
			$js_file  = SPBWC_PB_PLUGIN_DIR . 'static/js/template-library.js';
			$js_ver   = file_exists( $js_file ) ? filemtime( $js_file ) : SPBWC_PB_VERSION;

			wp_enqueue_style(
				'spbwc-template-library',
				SPBWC_PB_CSS_URL . 'template-library.css',
				array( 'woocommerce_admin_styles', 'spbwc-admin-ui' ),
				$css_ver
			);
			wp_enqueue_script(
				'spbwc-template-library',
				SPBWC_PB_JS_URL . 'template-library.js',
				array( 'jquery', 'wc-enhanced-select', 'selectWoo' ),
				$js_ver,
				true
			);

			// Currency symbol — shown as an affordance next to the
			// "Sample base price" input in the preview toolbar, and used
			// when surfacing the live total in the dialog subtitle.
			$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' )
				? html_entity_decode( (string) get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
				: '$';

			wp_localize_script(
				'spbwc-template-library',
				'spbwcTemplateLibrary',
				array(
					'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
					'nonce'               => wp_create_nonce( 'spbwc_template_library' ),
					'searchProductsNonce' => wp_create_nonce( 'search-products' ),
					'previewUrl'          => class_exists( 'SPBWC_Template_Preview_Render' ) ? SPBWC_Template_Preview_Render::preview_url() : '',
					'previewOrigin'       => wp_parse_url( home_url( '/' ), PHP_URL_SCHEME ) . '://' . wp_parse_url( home_url( '/' ), PHP_URL_HOST ) . ( wp_parse_url( home_url( '/' ), PHP_URL_PORT ) ? ':' . wp_parse_url( home_url( '/' ), PHP_URL_PORT ) : '' ),
					'currencySymbol'      => $currency_symbol,
					'i18n'                => array(
						'applying'           => esc_html__( 'Applying…', 'storelly-product-builder-for-woocommerce' ),
						'apply'              => esc_html__( 'Apply template', 'storelly-product-builder-for-woocommerce' ),
						'genericError'       => esc_html__( 'Something went wrong. Please try again.', 'storelly-product-builder-for-woocommerce' ),
						'selectProduct'      => esc_html__( 'Search for a product…', 'storelly-product-builder-for-woocommerce' ),
						'selectCategory'     => esc_html__( 'Select categories…', 'storelly-product-builder-for-woocommerce' ),
						'noProductSelected'  => esc_html__( 'Please select at least one product.', 'storelly-product-builder-for-woocommerce' ),
						'noCategorySelected' => esc_html__( 'Please select at least one category.', 'storelly-product-builder-for-woocommerce' ),
						'fieldsTab'          => esc_html__( 'Fields', 'storelly-product-builder-for-woocommerce' ),
						'aboutTab'           => esc_html__( 'About', 'storelly-product-builder-for-woocommerce' ),
						'previewTab'         => esc_html__( 'Live preview', 'storelly-product-builder-for-woocommerce' ),
						'estimatedTotal'     => esc_html__( 'est.', 'storelly-product-builder-for-woocommerce' ),
						'previewFailed'      => esc_html__( 'Couldn’t load the preview.', 'storelly-product-builder-for-woocommerce' ),
						'retry'              => esc_html__( 'Retry', 'storelly-product-builder-for-woocommerce' ),
						'updating'           => esc_html__( 'Updating…', 'storelly-product-builder-for-woocommerce' ),
						// translators: %d: number of options in the template.
						'optionsCount'       => esc_html__( '%d options', 'storelly-product-builder-for-woocommerce' ),
						'required'           => esc_html__( 'Required', 'storelly-product-builder-for-woocommerce' ),
						'displayDropdown'    => esc_html__( 'Dropdown', 'storelly-product-builder-for-woocommerce' ),
						'displayRadio'       => esc_html__( 'Radio', 'storelly-product-builder-for-woocommerce' ),
						'displaySwatch'      => esc_html__( 'Swatch', 'storelly-product-builder-for-woocommerce' ),
						'displayLabel'       => esc_html__( 'Label', 'storelly-product-builder-for-woocommerce' ),
						'inputText'          => esc_html__( 'Text input', 'storelly-product-builder-for-woocommerce' ),
						'inputUpload'        => esc_html__( 'File upload', 'storelly-product-builder-for-woocommerce' ),
						'inputTextarea'      => esc_html__( 'Long text', 'storelly-product-builder-for-woocommerce' ),
						'inputNumber'        => esc_html__( 'Number', 'storelly-product-builder-for-woocommerce' ),
						'previewApplyCTA'    => esc_html__( 'Apply this template', 'storelly-product-builder-for-woocommerce' ),
						'applySuccess'       => esc_html__( 'Template applied successfully.', 'storelly-product-builder-for-woocommerce' ),
					),
				)
			);
		}

		public function render_page() {
			if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
				wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
			}
			$catalog    = SPBWC_Template_Catalog::instance();
			$templates  = $catalog->get_templates();
			$categories = $catalog->get_categories();
			$view       = SPBWC_PB_PLUGIN_DIR . 'views/templates/library.php';
			if ( file_exists( $view ) ) {
				include $view;
			} else {
				echo '<div class="wrap"><h1>' . esc_html__( 'Template Library', 'storelly-product-builder-for-woocommerce' ) . '</h1>';
				echo '<p>' . esc_html__( 'View file missing.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
			}
		}

		/**
		 * Idempotent column migration for existing installs.
		 *
		 * Runs once per install (gated by self::MIGRATION_OPTION). Fresh installs
		 * get the columns via the CREATE TABLE statement in
		 * SPBWC_Storelly_PB_Admin_Options::spbwc_create_options_table().
		 */
		public function maybe_migrate_template_columns() {
			if ( get_option( self::MIGRATION_OPTION ) ) {
				return;
			}
			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global.
			$table = $wpdb->prefix . 'storelly_product_builder_options';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table name, schema check.
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists !== $table ) {
				// Table doesn't exist yet — install will create it via dbDelta with new columns.
				update_option( self::MIGRATION_OPTION, 1, false );
				return;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table name.
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
			if ( ! is_array( $columns ) ) {
				return;
			}

			if ( ! in_array( 'template_slug', $columns, true ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL migration, trusted table name.
				$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN template_slug VARCHAR(100) NULL" );
			}
			if ( ! in_array( 'template_version', $columns, true ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL migration, trusted table name.
				$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN template_version VARCHAR(20) NULL" );
			}

			update_option( self::MIGRATION_OPTION, 1, false );
		}
	}
}
