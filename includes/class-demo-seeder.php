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

		/** Set by the activation hook; consumed on the next admin load. */
		const OPTION_PENDING = 'spbwc_demo_seed_pending';

		/** One-shot guard so a merchant Undo never re-triggers the auto-seed. */
		const OPTION_AUTODONE = 'spbwc_demo_autoseeded';

		/** Atomic claim so concurrent admin loads can't each run the heavy seed. */
		const OPTION_LOCK = 'spbwc_demo_seed_lock';

		/** Seconds before a held lock is considered stale (a crashed run) and reclaimed. */
		const LOCK_TTL = 600;

		public static function init() {
			add_action( 'wp_ajax_spbwc_demo_seed', array( __CLASS__, 'ajax_seed' ) );
			add_action( 'wp_ajax_spbwc_demo_undo', array( __CLASS__, 'ajax_undo' ) );
			add_action( 'wp_ajax_spbwc_demo_publish', array( __CLASS__, 'ajax_publish' ) );
			add_action( 'admin_init', array( __CLASS__, 'maybe_seed' ) );
		}

		/**
		 * Called from the plugin activation hook — just arms the auto-seeder. The
		 * heavy lifting (CPT + media sideload) is deferred to the first admin load
		 * where WooCommerce and a capable actor are guaranteed available.
		 */
		public static function arm() {
			update_option( self::OPTION_PENDING, 1, false );
		}

		/**
		 * Auto-install the bundled bag demo ONCE, on the first authenticated admin
		 * load after activation. Unlike the explicit Welcome-card seed (which
		 * publishes), this imports the product as a DRAFT so nothing goes live on
		 * the storefront until the merchant publishes it. Mirrors SPBWC_B2B_Sample.
		 */
		public static function maybe_seed() {
			if ( ! get_option( self::OPTION_PENDING ) ) {
				return;
			}
			// Already auto-seeded once — survive a later merchant Undo.
			if ( get_option( self::OPTION_AUTODONE ) ) {
				delete_option( self::OPTION_PENDING );
				return;
			}
			if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
				return; // WooCommerce not ready yet — retry next load.
			}
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return; // Wait for an admin context (not cron / ajax / front).
			}
			if ( ! self::bundle_available() ) {
				delete_option( self::OPTION_PENDING );
				return;
			}
			// A demo already exists (e.g. merchant clicked the Welcome card first):
			// don't create a duplicate, just retire the pending flag.
			if ( self::is_seeded() ) {
				update_option( self::OPTION_AUTODONE, 1, false );
				delete_option( self::OPTION_PENDING );
				return;
			}

			// The seed sideloads ~100 bundled images synchronously, so a fresh
			// activation can hang the first admin load for many seconds. If the
			// merchant reloads or opens another admin tab during that wait, each
			// full page-load fires admin_init again — and since the done-flag is
			// only written AFTER seed() returns, every one of those requests used
			// to start its own seed() and create a duplicate bag (the "6 bags"
			// report) while the parallel sideloads melted the server. Claim an
			// atomic DB lock so exactly ONE request does the work; the rest bail
			// out immediately. A crashed run self-heals after LOCK_TTL.
			if ( ! self::acquire_lock() ) {
				return;
			}
			try {
				$res = self::seed( 'draft' );
				if ( ! is_wp_error( $res ) ) {
					update_option( self::OPTION_AUTODONE, 1, false );
					add_action( 'admin_notices', array( __CLASS__, 'autoseeded_notice' ) );
				}
				// Retire the pending flag once we've actually attempted the seed
				// (we won the lock). On failure the Welcome card stays as a manual
				// fallback — we never silently retry the heavy import every load.
				delete_option( self::OPTION_PENDING );
			} finally {
				self::release_lock();
			}
		}

		/**
		 * Atomically claim the one-shot seed. Uses an INSERT IGNORE on the unique
		 * option_name index — the only WordPress primitive guaranteed atomic across
		 * concurrent requests — so a hanging, image-heavy seed that the merchant
		 * reloads several times can never be run twice in parallel. A stale lock
		 * (previous run fatal/timeout mid-sideload) is reclaimed after LOCK_TTL.
		 *
		 * @return bool True only for the request that won the lock.
		 */
		protected static function acquire_lock() {
			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global.
			$now   = time();
			$added = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
					self::OPTION_LOCK,
					(string) $now
				)
			);
			if ( $added ) {
				return true;
			}
			// A lock row exists. If it is still fresh, another request owns it.
			$held = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::OPTION_LOCK )
			);
			if ( $held && ( $now - $held ) < self::LOCK_TTL ) {
				return false;
			}
			// Stale lock: steal it only if no one else changed it first (compare-and-swap).
			$stolen = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
					(string) $now,
					self::OPTION_LOCK,
					(string) $held
				)
			);
			return (bool) $stolen;
		}

		/** Release the seed lock (also cleared implicitly if the row is left to go stale). */
		protected static function release_lock() {
			delete_option( self::OPTION_LOCK );
		}

		/**
		 * One-time admin notice after the auto-seed: the demo is staged as a draft,
		 * with a one-click path to preview the Visual Builder and publish it.
		 */
		public static function autoseeded_notice() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}
			$overview = defined( 'SPBWC_PB_OVERVIEW_SLUG' )
				? admin_url( 'admin.php?page=' . SPBWC_PB_OVERVIEW_SLUG )
				: admin_url( 'admin.php' );
			?>
			<div class="notice notice-info is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'A demo product with a ready-made Visual Builder was imported as a draft so you can preview it. Publish it when you\'re ready to show it on your storefront.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $overview ); ?>"><?php esc_html_e( 'Preview & publish demo', 'storelly-product-builder-for-woocommerce' ); ?></a>
				</p>
			</div>
			<?php
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
			if ( is_array( $state ) && ! empty( $state['product_id'] ) ) {
				if ( 'product' === get_post_type( (int) $state['product_id'] ) ) {
					return true;
				}
			}
			// Fallback: a prior run may have created the product but crashed before
			// writing OPTION_STATE (timeout mid-import). Detect that orphan by its
			// sample flag so we never seed a second bag on top of it.
			return self::existing_sample_product_id() > 0;
		}

		/**
		 * Find a demo bag product left behind by an earlier (possibly crashed) run.
		 *
		 * @return int Product id, or 0.
		 */
		protected static function existing_sample_product_id() {
			$found = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => self::META_SAMPLE,   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => '1',                 // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			return ! empty( $found ) ? (int) $found[0] : 0;
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

		public static function ajax_publish() {
			self::guard();
			$res = self::publish();
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
			wp_send_json_success( $res );
		}

		// ── Seed ────────────────────────────────────────────────────────────

		/**
		 * Install the bundled bag demo. Idempotent: if already seeded, returns
		 * the existing ids without creating duplicates.
		 *
		 * @param string $status Product post status to create with: 'publish'
		 *                       (explicit Welcome-card seed) or 'draft' (silent
		 *                       auto-seed on activation — hidden until published).
		 * @return array|WP_Error ['product_id'=>int,'option_id'=>int,'view_url'=>string,'created'=>bool]
		 */
		public static function seed( $status = 'publish' ) {
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
			$p->set_status( 'draft' === $status ? 'draft' : 'publish' );
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

		/**
		 * Flip the seeded demo product live. Used when the demo was auto-installed
		 * as a draft and the merchant clicks "Publish" on the Welcome card.
		 *
		 * @return array|WP_Error ['product_id'=>int,'view_url'=>string]
		 */
		public static function publish() {
			$state      = get_option( self::OPTION_STATE, false );
			$product_id = is_array( $state ) && ! empty( $state['product_id'] ) ? (int) $state['product_id'] : 0;
			if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
				return new WP_Error( 'no_demo', __( 'The demo product no longer exists.', 'storelly-product-builder-for-woocommerce' ) );
			}
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
			if ( $product ) {
				$product->set_status( 'publish' );
				$product->save();
			} else {
				wp_update_post( array( 'ID' => $product_id, 'post_status' => 'publish' ) );
			}
			delete_transient( 'spbwc_product_builder_' . $product_id );

			return array(
				'product_id' => $product_id,
				'view_url'   => get_permalink( $product_id ),
			);
		}

		/** Post status of the seeded demo product, or '' if none. */
		public static function seeded_status() {
			$state = get_option( self::OPTION_STATE, false );
			if ( ! is_array( $state ) || empty( $state['product_id'] ) ) {
				return '';
			}
			$status = get_post_status( (int) $state['product_id'] );
			return $status ? (string) $status : '';
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
