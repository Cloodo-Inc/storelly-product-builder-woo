<?php
/**
 * Bundled "bag" demo seeder (M9.1).
 *
 * The Welcome flow's fastest path is "see it live with a demo product". The
 * legacy sample importer fetches its dataset from app.storelly.com, so on a
 * fresh / offline install it returns nothing and the aha-moment dies. This
 * seeder ships ONE ready-made customizable product (the bag) inside the plugin
 * and installs it with no network at all:
 *
 *   storage/printcart/demo/bag.json         — product meta + option-set fields
 *   storage/printcart/demo/img/<oldId>.webp — each referenced image (resized)
 *
 * Images inside the fields blob are referenced by their original attachment id.
 * On import we sideload each bundled webp into the media library, map old id ->
 * new id, and rewrite the blob. Everything created is tagged so Undo can remove
 * it cleanly (product, option-set row, and attachments via _spbwc_is_sample).
 *
 * Compliance: no wp_remote_* here — strictly local file reads + media sideload
 * from a temp copy. Runs only on explicit merchant action (Welcome button),
 * nonce + capability gated.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Demo_Seeder' ) ) {

	class SPBWC_Demo_Seeder {

		/** Records the seeded ids: ['product_id'=>int,'option_id'=>int,'time'=>int]. */
		const OPTION_STATE = 'spbwc_demo_seeded';

		/** Marks every product / attachment created by the demo seed. */
		const META_SAMPLE = '_spbwc_is_sample';

		/** template_slug prefix for the seeded option row (distinct from wizard/prepare). */
		const SLUG_PREFIX = 'demo_sample_';

		/** AJAX nonce action. */
		const NONCE = 'spbwc_demo_seed';

		public static function init() {
			add_action( 'wp_ajax_spbwc_demo_seed', array( __CLASS__, 'ajax_seed' ) );
			add_action( 'wp_ajax_spbwc_demo_undo', array( __CLASS__, 'ajax_undo' ) );
		}

		protected static function bundle_dir() {
			return SPBWC_PB_PLUGIN_DIR . 'storage/printcart/demo/';
		}

		/** Whether the bundled dataset is present (so the UI can hide the CTA if not). */
		public static function bundle_available() {
			return file_exists( self::bundle_dir() . 'bag.json' );
		}

		/** Already seeded and the product still exists? */
		public static function is_seeded() {
			$state = get_option( self::OPTION_STATE, false );
			if ( ! is_array( $state ) || empty( $state['product_id'] ) ) {
				return false;
			}
			return 'product' === get_post_type( (int) $state['product_id'] );
		}

		// ── AJAX ────────────────────────────────────────────────────────────

		protected static function guard() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
			}
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
			}
		}

		public static function ajax_seed() {
			self::guard();
			$res = self::seed();
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
			wp_send_json_success( $res );
		}

		public static function ajax_undo() {
			self::guard();
			wp_send_json_success( self::undo() );
		}

		// ── Seed ────────────────────────────────────────────────────────────

		/**
		 * Install the bundled bag demo. Idempotent: if already seeded, returns
		 * the existing ids without creating duplicates.
		 *
		 * @return array|WP_Error ['product_id'=>int,'option_id'=>int,'view_url'=>string,'created'=>bool]
		 */
		public static function seed() {
			if ( self::is_seeded() ) {
				$state = get_option( self::OPTION_STATE, array() );
				return array(
					'product_id' => (int) $state['product_id'],
					'option_id'  => (int) $state['option_id'],
					'view_url'   => get_permalink( (int) $state['product_id'] ),
					'created'    => false,
				);
			}

			if ( ! function_exists( 'wc_get_product' ) ) {
				return new WP_Error( 'no_woo', __( 'WooCommerce is required to import the demo.', 'storelly-product-builder-for-woocommerce' ) );
			}
			$bundle = self::read_bundle();
			if ( is_wp_error( $bundle ) ) {
				return $bundle;
			}

			self::require_media();

			// 1) Sideload every bundled image, building old-id -> new-id map.
			$map     = array();
			$created = array(); // attachment ids we made, for undo
			foreach ( $bundle['image_ids'] as $old ) {
				$old  = (int) $old;
				$file = self::bundle_dir() . 'img/' . $old . '.webp';
				$new  = file_exists( $file ) ? self::sideload_local( $file, 'demo-bag-' . $old . '.webp' ) : 0;
				$map[ $old ] = $new;
				if ( $new ) {
					$created[] = $new;
				}
			}

			// 2) Rewrite image references inside the fields blob.
			$fields = $bundle['option_set']['fields'];
			self::remap_images( $fields, $map );

			// 3) Create the WooCommerce product.
			$p = new WC_Product_Simple();
			$p->set_name( (string) $bundle['product']['name'] );
			$p->set_status( 'publish' );
			$p->set_catalog_visibility( 'visible' );
			if ( '' !== (string) $bundle['product']['regular_price'] ) {
				$p->set_regular_price( (string) $bundle['product']['regular_price'] );
			}
			if ( ! empty( $bundle['product']['description'] ) ) {
				$p->set_description( (string) $bundle['product']['description'] );
			}
			if ( ! empty( $bundle['product']['short'] ) ) {
				$p->set_short_description( (string) $bundle['product']['short'] );
			}
			$product_id = (int) $p->save();
			if ( ! $product_id ) {
				self::cleanup_attachments( $created );
				return new WP_Error( 'no_product', __( 'Could not create the demo product.', 'storelly-product-builder-for-woocommerce' ) );
			}
			$thumb_old = isset( $bundle['product']['thumb'] ) ? (int) $bundle['product']['thumb'] : 0;
			if ( $thumb_old && ! empty( $map[ $thumb_old ] ) ) {
				set_post_thumbnail( $product_id, (int) $map[ $thumb_old ] );
			}

			// 4) Insert the option-set row (published, applied to this product).
			$option_id = self::insert_option_row( $bundle['option_set']['title'], $fields, $product_id );
			if ( ! $option_id ) {
				wp_trash_post( $product_id );
				self::cleanup_attachments( $created );
				return new WP_Error( 'no_option', __( 'Could not create the demo option set.', 'storelly-product-builder-for-woocommerce' ) );
			}

			// 5) Link + tag for clean Undo.
			update_post_meta( $product_id, '_spbwc_option_id', $option_id );
			update_post_meta( $product_id, '_storelly_pb_enable', 1 );
			update_post_meta( $product_id, self::META_SAMPLE, 1 );
			delete_transient( 'spbwc_product_builder_' . $product_id );
			self::flush_caches( array( $option_id ), array( $product_id ) );

			update_option(
				self::OPTION_STATE,
				array(
					'product_id'  => $product_id,
					'option_id'   => $option_id,
					'attachments' => $created,
					'time'        => time(),
				),
				false
			);

			return array(
				'product_id' => $product_id,
				'option_id'  => $option_id,
				'view_url'   => get_permalink( $product_id ),
				'created'    => true,
			);
		}

		/**
		 * Remove everything the seed created: product, option row, attachments.
		 *
		 * @return array ['removed'=>bool]
		 */
		public static function undo() {
			$state = get_option( self::OPTION_STATE, false );
			if ( ! is_array( $state ) ) {
				return array( 'removed' => false );
			}
			$product_id = isset( $state['product_id'] ) ? (int) $state['product_id'] : 0;
			$option_id  = isset( $state['option_id'] ) ? (int) $state['option_id'] : 0;

			if ( $option_id ) {
				global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global.
				$table = $wpdb->prefix . 'storelly_product_builder_options';
				$wpdb->delete( $table, array( 'id' => $option_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
			if ( $product_id ) {
				wp_delete_post( $product_id, true );
			}
			if ( ! empty( $state['attachments'] ) && is_array( $state['attachments'] ) ) {
				self::cleanup_attachments( $state['attachments'] );
			}
			self::flush_caches( array( $option_id ), array( $product_id ) );
			delete_option( self::OPTION_STATE );

			return array( 'removed' => true );
		}

		// ── Helpers ─────────────────────────────────────────────────────────

		protected static function read_bundle() {
			$path = self::bundle_dir() . 'bag.json';
			if ( ! file_exists( $path ) ) {
				return new WP_Error( 'no_bundle', __( 'The bundled demo data is missing.', 'storelly-product-builder-for-woocommerce' ) );
			}
			$raw  = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled local asset, not remote.
			$data = json_decode( $raw, true );
			if ( ! is_array( $data ) || empty( $data['option_set']['fields'] ) || empty( $data['image_ids'] ) ) {
				return new WP_Error( 'bad_bundle', __( 'The bundled demo data is invalid.', 'storelly-product-builder-for-woocommerce' ) );
			}
			return $data;
		}

		protected static function require_media() {
			if ( ! function_exists( 'media_handle_sideload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
		}

		/** Sideload a local file (no network) and tag it as sample. Returns attachment id or 0. */
		protected static function sideload_local( $path, $filename ) {
			$tmp = wp_tempnam( $filename );
			if ( ! $tmp || ! @copy( $path, $tmp ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return 0;
			}
			$file = array(
				'name'     => $filename,
				'type'     => 'image/webp',
				'tmp_name' => $tmp,
				'error'    => 0,
				'size'     => filesize( $tmp ),
			);

			// WordPress only whitelists the webp mime since 5.8. The plugin still
			// supports older cores (readme "Requires at least"), where
			// media_handle_sideload() would reject our bundled .webp files with
			// "file type not permitted" and the demo would import with no images.
			// Force-allow webp ONLY for the duration of this sideload, then remove
			// the filters so we never widen uploads globally.
			$allow_webp = static function ( $mimes ) {
				$mimes['webp'] = 'image/webp';
				return $mimes;
			};
			$force_webp = static function ( $data, $unused_file, $unused_filename, $unused_mimes, $real_mime ) {
				if ( 'image/webp' === $real_mime || ( empty( $data['ext'] ) && empty( $data['type'] ) ) ) {
					$data['ext']  = 'webp';
					$data['type'] = 'image/webp';
				}
				return $data;
			};
			add_filter( 'upload_mimes', $allow_webp );
			add_filter( 'wp_check_filetype_and_ext', $force_webp, 10, 5 );

			$id = media_handle_sideload( $file, 0, null, array( 'test_form' => false, 'test_size' => false ) );

			remove_filter( 'upload_mimes', $allow_webp );
			remove_filter( 'wp_check_filetype_and_ext', $force_webp, 10 );

			if ( is_wp_error( $id ) ) {
				if ( file_exists( $tmp ) ) {
					wp_delete_file( $tmp );
				}
				return 0;
			}
			update_post_meta( (int) $id, self::META_SAMPLE, 1 );
			return (int) $id;
		}

		/** Recursively rewrite every `image` id in the fields blob via the map (missing -> 0). */
		protected static function remap_images( &$node, array $map ) {
			if ( ! is_array( $node ) ) {
				return;
			}
			foreach ( $node as $k => &$v ) {
				if ( 'image' === $k && is_scalar( $v ) && (int) $v > 0 ) {
					$v = isset( $map[ (int) $v ] ) ? (int) $map[ (int) $v ] : 0;
				} elseif ( is_array( $v ) ) {
					self::remap_images( $v, $map );
				}
			}
			unset( $v );
		}

		/** Insert a published option-set row applied to one product. Mirrors the prepare schema. */
		protected static function insert_option_row( $title, $fields, $product_id ) {
			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global.
			$table = $wpdb->prefix . 'storelly_product_builder_options';
			$now   = current_time( 'mysql' );
			$uid   = (int) get_current_user_id();
			$ok    = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'title'            => (string) $title,
					'published'        => 1,
					'product_ids'      => serialize( array( (int) $product_id ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- existing schema stores serialized arrays.
					'apply_for'        => 'p',
					'product_cats'     => serialize( array() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- existing schema stores serialized arrays.
					'created'          => $now,
					'modified'         => $now,
					'created_by'       => $uid,
					'modified_by'      => $uid,
					'fields'           => serialize( $fields ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- existing schema stores serialized arrays.
					'template_slug'    => self::SLUG_PREFIX . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 12 ),
					'template_version' => '1.0.0',
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
			);
			return $ok ? (int) $wpdb->insert_id : 0;
		}

		protected static function cleanup_attachments( array $ids ) {
			foreach ( $ids as $id ) {
				$id = (int) $id;
				if ( $id && 'attachment' === get_post_type( $id ) ) {
					wp_delete_attachment( $id, true );
				}
			}
		}

		/** Invalidate the option-resolver caches (mirrors SPBWC_Woo_Prepare::flush_caches). */
		protected static function flush_caches( array $row_ids, array $product_ids ) {
			foreach ( $row_ids as $rid ) {
				if ( $rid ) {
					wp_cache_delete( 'spbwc_option_' . (int) $rid, 'spbwc_product_builder' );
				}
			}
			foreach ( $product_ids as $pid ) {
				if ( $pid ) {
					delete_transient( 'spbwc_product_builder_' . (int) $pid );
				}
			}
			wp_cache_delete( 'spbwc_published_options', 'spbwc_product_builder' );
		}
	}

	SPBWC_Demo_Seeder::init();
}
