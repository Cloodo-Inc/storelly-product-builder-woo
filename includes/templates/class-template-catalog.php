<?php
/**
 * Bundled template catalog reader.
 *
 * Reads storage/print-templates/catalog.json + individual template JSON files
 * shipped with the plugin. Templates here are READ-ONLY — admin actions never
 * write back to the file system. Applied templates are copied (forked) into
 * the wp_storelly_product_builder_options table by SPBWC_Template_Applier.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Template_Catalog' ) ) {

	class SPBWC_Template_Catalog {

		protected static $instance = null;

		private const STORAGE_REL_DIR = 'storage/print-templates/';
		private const CATALOG_FILE    = 'catalog.json';
		private const CACHE_TTL       = HOUR_IN_SECONDS;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function storage_dir() {
			return SPBWC_PB_PLUGIN_DIR . self::STORAGE_REL_DIR;
		}

		public function catalog_file_path() {
			return $this->storage_dir() . self::CATALOG_FILE;
		}

		/**
		 * Get the full parsed catalog (categories + templates list).
		 *
		 * Cached via transient keyed by catalog.json mtime so that bumping
		 * the bundled file (e.g. via a plugin update) auto-invalidates.
		 *
		 * @return array
		 */
		public function get_catalog() {
			$file = $this->catalog_file_path();
			if ( ! is_readable( $file ) ) {
				return $this->empty_catalog();
			}

			$mtime     = (int) filemtime( $file );
			$cache_key = 'spbwc_template_catalog_' . $mtime;
			$cached    = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}

			$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin asset, not remote.
			if ( ! is_string( $raw ) || '' === $raw ) {
				return $this->empty_catalog();
			}

			$data = json_decode( $raw, true );
			if ( ! is_array( $data ) || empty( $data['templates'] ) ) {
				return $this->empty_catalog();
			}

			$catalog = array(
				'version'    => isset( $data['version'] ) ? (string) $data['version'] : '1',
				'generated'  => isset( $data['generated'] ) ? (string) $data['generated'] : '',
				'categories' => is_array( $data['categories'] ?? null ) ? $data['categories'] : array(),
				'templates'  => array_values(
					array_filter(
						array_map( array( $this, 'normalize_template_meta' ), $data['templates'] ),
						function ( $tpl ) {
							return ! empty( $tpl['slug'] );
						}
					)
				),
			);

			set_transient( $cache_key, $catalog, self::CACHE_TTL );
			return $catalog;
		}

		public function get_templates() {
			$catalog = $this->get_catalog();
			return isset( $catalog['templates'] ) ? $catalog['templates'] : array();
		}

		public function get_categories() {
			$catalog = $this->get_catalog();
			return isset( $catalog['categories'] ) ? $catalog['categories'] : array();
		}

		public function get_template_meta( $slug ) {
			$slug = $this->normalize_slug( $slug );
			foreach ( $this->get_templates() as $tpl ) {
				if ( isset( $tpl['slug'] ) && $tpl['slug'] === $slug ) {
					return $tpl;
				}
			}
			return null;
		}

		/**
		 * Read the actual template body JSON (the printing-option object).
		 *
		 * @param string $slug
		 * @return array|null
		 */
		public function get_template_data( $slug ) {
			$meta = $this->get_template_meta( $slug );
			if ( ! is_array( $meta ) ) {
				return null;
			}
			$rel = isset( $meta['file'] ) ? ltrim( (string) $meta['file'], '/' ) : '';
			if ( '' === $rel ) {
				return null;
			}
			// Refuse anything outside storage dir.
			$file = $this->storage_dir() . $rel;
			$real = realpath( $file );
			$root = realpath( $this->storage_dir() );
			if ( false === $real || false === $root || 0 !== strpos( $real, $root ) ) {
				return null;
			}
			if ( ! is_readable( $real ) ) {
				return null;
			}
			$raw = file_get_contents( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local asset.
			if ( ! is_string( $raw ) || '' === $raw ) {
				return null;
			}
			$data = json_decode( $raw, true );
			return is_array( $data ) ? $data : null;
		}

		/**
		 * Catalog version (used to detect "Update available" on applied options).
		 *
		 * Defined as the catalog's own version field. Bumped manually when the
		 * bundled JSON changes in a way that should ping admins to reset.
		 *
		 * @return string
		 */
		public function get_catalog_version() {
			$catalog = $this->get_catalog();
			return isset( $catalog['version'] ) ? (string) $catalog['version'] : '1';
		}

		public function flush_cache() {
			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot transient cleanup.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_spbwc_template_catalog_%' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot transient cleanup.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_spbwc_template_catalog_%' ) );
		}

		protected function normalize_template_meta( $tpl ) {
			if ( ! is_array( $tpl ) ) {
				return null;
			}
			$tpl['slug']             = isset( $tpl['slug'] ) ? $this->normalize_slug( $tpl['slug'] ) : '';
			$tpl['file']             = isset( $tpl['file'] ) ? (string) $tpl['file'] : '';
			$tpl['category']         = isset( $tpl['category'] ) ? (string) $tpl['category'] : '';
			$tpl['field_count']      = isset( $tpl['field_count'] ) ? (int) $tpl['field_count'] : 0;
			$tpl['pricing_method']   = isset( $tpl['pricing_method'] ) ? (string) $tpl['pricing_method'] : 'fixed';
			$tpl['template_version'] = isset( $tpl['template_version'] ) ? (string) $tpl['template_version'] : '1.0.0';
			$tpl['pricing_source']   = isset( $tpl['pricing_source'] ) ? (string) $tpl['pricing_source'] : '';
			$tpl['description']      = isset( $tpl['description'] ) ? (string) $tpl['description'] : '';
			$tpl['thumbnail']        = isset( $tpl['thumbnail'] ) ? (string) $tpl['thumbnail'] : '';
			$tpl['name']             = is_array( $tpl['name'] ?? null ) ? $tpl['name'] : array( 'en_US' => (string) ( $tpl['name'] ?? $tpl['slug'] ) );
			return $tpl;
		}

		public function get_display_name( $tpl, $locale = '' ) {
			if ( ! is_array( $tpl ) || empty( $tpl['name'] ) ) {
				return '';
			}
			$name_map = $tpl['name'];
			if ( '' === $locale ) {
				$locale = determine_locale();
			}
			if ( ! empty( $name_map[ $locale ] ) ) {
				return (string) $name_map[ $locale ];
			}
			if ( ! empty( $name_map['en_US'] ) ) {
				return (string) $name_map['en_US'];
			}
			return (string) reset( $name_map );
		}

		public function get_category_label( $category_id, $locale = '' ) {
			$cats = $this->get_categories();
			if ( ! isset( $cats[ $category_id ] ) || ! is_array( $cats[ $category_id ] ) ) {
				return ucwords( str_replace( '_', ' ', (string) $category_id ) );
			}
			$labels = $cats[ $category_id ];
			if ( '' === $locale ) {
				$locale = determine_locale();
			}
			if ( ! empty( $labels[ $locale ] ) ) {
				return (string) $labels[ $locale ];
			}
			return ! empty( $labels['en_US'] ) ? (string) $labels['en_US'] : (string) reset( $labels );
		}

		protected function normalize_slug( $slug ) {
			$slug = strtolower( (string) $slug );
			$slug = preg_replace( '/[^a-z0-9\-_]/', '', $slug );
			return (string) $slug;
		}

		protected function empty_catalog() {
			return array(
				'version'    => '1',
				'generated'  => '',
				'categories' => array(),
				'templates'  => array(),
			);
		}
	}
}
