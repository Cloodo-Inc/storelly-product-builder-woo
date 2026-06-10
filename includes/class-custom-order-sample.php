<?php
/**
 * Custom Order sample seeder (Wave 2, item 9 — see docs/SPEC_CUSTOM_ORDER.md
 * "Milestone W2-SAMPLE").
 *
 * A fresh store has no Custom Order to look at, so the whole COW design-folder /
 * proof / production workflow (SPBWC_Custom_Order_Detail) is invisible until a
 * real buyer places one. This seeder installs ONE clearly-labelled sample custom
 * order so a merchant can explore the Custom Order Detail workspace end to end,
 * then remove it in one click.
 *
 * What it builds (all tagged `_spbwc_is_sample` for a clean Undo):
 *   - a draft WooCommerce product ("Storelly Sample — Custom Tote"),
 *   - a WC order for it whose line item carries a COW design folder,
 *   - the design folder itself: a fresh clone (via
 *     SPBWC_Storelly_IO::spbwc_clone_design_folder) of the bundled template in
 *     storage/printcart/custom-order/design so the sample owns its own artwork
 *     and nothing is shared (copy-on-write, per the COW rule).
 *
 * Compliance (CLAUDE.md): no wp_remote_* — the bundle is read from local disk and
 * staged into SPBWC_PB_CUSTOMER_DIR with the same filesystem helpers the plugin
 * already uses. Runs only on an explicit merchant click (admin-post), nonce +
 * capability gated. Mirrors SPBWC_Demo_Seeder / SPBWC_B2B_Sample.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Custom_Order_Sample' ) ) {

	class SPBWC_Custom_Order_Sample {

		/** Marks every product / order / attachment created by this seeder. */
		const FLAG = '_spbwc_is_sample';

		/** Records the seeded ids: ['order_id'=>int,'product_id'=>int,'folder'=>string,'time'=>int]. */
		const OPTION_STATE = 'spbwc_custom_order_sample';

		/** admin-post action for the "Add Custom Order sample" button. */
		const ACTION_ADD = 'spbwc_co_sample_add';

		/** admin-post action for the "Remove sample" button. */
		const ACTION_REMOVE = 'spbwc_co_sample_remove';

		public static function init() {
			add_action( 'admin_post_' . self::ACTION_ADD, array( __CLASS__, 'handle_add' ) );
			add_action( 'admin_post_' . self::ACTION_REMOVE, array( __CLASS__, 'handle_remove' ) );
		}

		/* ── Bundle ───────────────────────────────────────────────── */

		/** Absolute path to the bundled design-folder template. */
		protected static function bundle_dir() {
			return SPBWC_PB_PLUGIN_DIR . 'storage/printcart/custom-order/design/';
		}

		/** Whether the bundled design template is present (so the UI can hide the CTA if not). */
		public static function bundle_available() {
			return file_exists( self::bundle_dir() . 'config.json' );
		}

		/* ── Existence helpers ────────────────────────────────────── */

		/** Sample order id (still present), or 0. */
		public static function order_id() {
			$state = get_option( self::OPTION_STATE, false );
			if ( is_array( $state ) && ! empty( $state['order_id'] ) ) {
				$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $state['order_id'] ) : null;
				if ( $order ) {
					return (int) $state['order_id'];
				}
			}
			return self::existing_sample_order_id();
		}

		/** Whether the sample is currently installed. */
		public static function exists() {
			return self::order_id() > 0;
		}

		/** Find a sample order left behind by an earlier run (HPOS-safe). */
		protected static function existing_sample_order_id() {
			if ( ! function_exists( 'wc_get_orders' ) ) {
				return 0;
			}
			$found = wc_get_orders(
				array(
					'limit'      => 1,
					'return'     => 'ids',
					'meta_key'   => self::FLAG,   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => '1',          // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			return ! empty( $found ) ? (int) $found[0] : 0;
		}

		/* ── Seed ─────────────────────────────────────────────────── */

		/**
		 * Install the sample custom order. Idempotent: if already present, returns
		 * the existing ids without creating duplicates.
		 *
		 * @return array|WP_Error ['order_id'=>int,'product_id'=>int,'folder'=>string,'view_url'=>string,'created'=>bool]
		 */
		public static function seed() {
			if ( self::exists() ) {
				$state = get_option( self::OPTION_STATE, array() );
				$oid   = is_array( $state ) && ! empty( $state['order_id'] ) ? (int) $state['order_id'] : self::existing_sample_order_id();
				return array(
					'order_id'   => $oid,
					'product_id' => is_array( $state ) ? (int) ( $state['product_id'] ?? 0 ) : 0,
					'folder'     => is_array( $state ) ? (string) ( $state['folder'] ?? '' ) : '',
					'view_url'   => class_exists( 'SPBWC_Custom_Order_Detail' ) ? SPBWC_Custom_Order_Detail::url( $oid ) : '',
					'created'    => false,
				);
			}

			if ( ! function_exists( 'wc_create_order' ) || ! function_exists( 'wc_get_product' ) ) {
				return new WP_Error( 'no_woo', __( 'WooCommerce is required to install the sample.', 'storelly-product-builder-for-woocommerce' ) );
			}
			if ( ! self::bundle_available() ) {
				return new WP_Error( 'no_bundle', __( 'The bundled sample design is missing.', 'storelly-product-builder-for-woocommerce' ) );
			}

			// 1) Stage the COW design folder: clone the bundled template into a
			//    fresh, sample-owned folder so it can never mutate anything else.
			$folder = self::stage_design_folder();
			if ( '' === $folder ) {
				return new WP_Error( 'no_folder', __( 'Could not prepare the sample design files.', 'storelly-product-builder-for-woocommerce' ) );
			}

			// Seeding must be silent: suppress the order emails the demo data fires.
			add_filter( 'pre_wp_mail', '__return_true', 9999 );
			try {
				$result = self::build( $folder );
			} finally {
				remove_filter( 'pre_wp_mail', '__return_true', 9999 );
			}

			if ( is_wp_error( $result ) ) {
				// Roll back the staged folder so a failed seed leaves no orphan.
				self::delete_folder( $folder );
			}
			return $result;
		}

		/**
		 * Build the product + order around an already-staged design folder.
		 *
		 * @param string $folder Staged design folder id.
		 * @return array|WP_Error
		 */
		protected static function build( $folder ) {
			// 2) A draft product the sample order references.
			$product = new WC_Product_Simple();
			$product->set_name( __( 'Storelly Sample — Custom Tote', 'storelly-product-builder-for-woocommerce' ) );
			$product->set_status( 'draft' );
			$product->set_catalog_visibility( 'hidden' );
			$product->set_regular_price( '24.00' );
			$product->set_short_description( __( 'Sample personalised product used by the Custom Order demo. Remove the sample when you are done exploring.', 'storelly-product-builder-for-woocommerce' ) );
			$product_id = (int) $product->save();
			if ( ! $product_id ) {
				return new WP_Error( 'no_product', __( 'Could not create the sample product.', 'storelly-product-builder-for-woocommerce' ) );
			}
			update_post_meta( $product_id, self::FLAG, '1' );
			update_post_meta( $product_id, '_storelly_pb_enable', 1 );

			// 3) The order.
			$order = wc_create_order();
			if ( is_wp_error( $order ) || ! $order ) {
				wp_delete_post( $product_id, true );
				return new WP_Error( 'no_order', __( 'Could not create the sample order.', 'storelly-product-builder-for-woocommerce' ) );
			}
			$item_id = $order->add_product( $product, 2 );
			if ( ! $item_id ) {
				$order->delete( true );
				wp_delete_post( $product_id, true );
				return new WP_Error( 'no_item', __( 'Could not add the sample design to the order.', 'storelly-product-builder-for-woocommerce' ) );
			}

			// 4) Attach the COW design folder + a readable option summary so the
			//    Custom Order Detail "Design" tab renders previews + specs.
			wc_update_order_item_meta( $item_id, '_pcpb_folder', $folder );
			wc_update_order_item_meta( $item_id, '_pcpb_option_price', self::sample_option_price() );

			$order->set_address(
				array(
					'first_name' => __( 'Sample', 'storelly-product-builder-for-woocommerce' ),
					'last_name'  => __( 'Buyer', 'storelly-product-builder-for-woocommerce' ),
					'email'      => 'sample-buyer@example.com',
					'city'       => __( 'Sampletown', 'storelly-product-builder-for-woocommerce' ),
					'country'    => '',
				),
				'billing'
			);
			$order->add_order_note( __( 'Sample custom order created by Storelly so you can explore the Custom Order workspace. Remove it from the Setup Wizard when done.', 'storelly-product-builder-for-woocommerce' ) );
			$order->calculate_totals();
			$order->update_meta_data( self::FLAG, '1' );
			$order->set_status( 'processing' );
			$order->save();
			$order_id = (int) $order->get_id();

			update_option(
				self::OPTION_STATE,
				array(
					'order_id'   => $order_id,
					'product_id' => $product_id,
					'folder'     => $folder,
					'time'       => time(),
				),
				false
			);

			return array(
				'order_id'   => $order_id,
				'product_id' => $product_id,
				'folder'     => $folder,
				'view_url'   => class_exists( 'SPBWC_Custom_Order_Detail' ) ? SPBWC_Custom_Order_Detail::url( $order_id ) : '',
				'created'    => true,
			);
		}

		/**
		 * Stage the bundled design template into SPBWC_PB_CUSTOMER_DIR and return a
		 * fresh copy-on-write folder id. The bundle stores image references with
		 * portable tokens ({{PLUGIN_URL}}) so they resolve on any site; we copy the
		 * raw template into a temp staging folder, rewrite the tokens, then clone it
		 * so the result is indistinguishable from a real buyer design.
		 *
		 * @return string Folder id on success, '' on failure.
		 */
		protected static function stage_design_folder() {
			$base = SPBWC_PB_CUSTOMER_DIR;
			SPBWC_Storelly_IO::spbwc_mkdir( $base );

			// Temp staging id (cleaned up after the clone).
			$stage_id = 'spbwc_co_sample_stage_' . wp_generate_uuid4();
			$stage    = $base . '/' . $stage_id;
			SPBWC_Storelly_IO::spbwc_copy_dir( self::bundle_dir(), $stage );
			if ( ! is_dir( $stage ) ) {
				return '';
			}

			// Rewrite portable tokens to this site's real URLs (no hardcoded host).
			$plugin_url = defined( 'SPBWC_PB_PLUGIN_URL' ) ? SPBWC_PB_PLUGIN_URL : trailingslashit( plugins_url( '', dirname( __FILE__ ) ) );
			$replace    = array( '{{PLUGIN_URL}}' => $plugin_url );
			foreach ( array( 'config.json', 'design.json', 'design_output.json' ) as $json ) {
				$path = $stage . '/' . $json;
				if ( ! file_exists( $path ) ) {
					continue;
				}
				$raw = SPBWC_Storelly_IO::spbwc_get_local_file_contents( $path );
				if ( false === $raw ) {
					continue;
				}
				$raw = strtr( $raw, $replace );
				SPBWC_Storelly_IO::spbwc_put_local_file_contents( $path, $raw );
			}

			// Copy-on-write clone -> the sample's own folder; drop the staging copy.
			$folder = SPBWC_Storelly_IO::spbwc_clone_design_folder( $stage_id );
			self::delete_folder( $stage_id );
			return is_string( $folder ) ? $folder : '';
		}

		/** A readable option summary mirroring a real builder line (_pcpb_option_price). */
		protected static function sample_option_price() {
			return array(
				'fields' => array(
					array(
						'name'       => __( 'Material', 'storelly-product-builder-for-woocommerce' ),
						'value_name' => __( 'Organic cotton canvas', 'storelly-product-builder-for-woocommerce' ),
					),
					array(
						'name'       => __( 'Print area', 'storelly-product-builder-for-woocommerce' ),
						'value_name' => __( 'Front + Top + Inside', 'storelly-product-builder-for-woocommerce' ),
					),
				),
			);
		}

		/* ── Removal ──────────────────────────────────────────────── */

		/**
		 * Remove every sample artefact: the order (and its design folder), the
		 * sample product, and any sample-tagged attachment. Idempotent.
		 *
		 * @return array{orders:int,products:int} Counts removed.
		 */
		public static function remove_all() {
			$orders   = 0;
			$products = 0;

			// Orders (HPOS-safe) tagged sample — delete their design folders too.
			if ( function_exists( 'wc_get_orders' ) ) {
				$order_ids = wc_get_orders(
					array(
						'limit'      => -1,
						'return'     => 'ids',
						'meta_key'   => self::FLAG,   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value' => '1',          // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					)
				);
				foreach ( (array) $order_ids as $oid ) {
					$order = wc_get_order( $oid );
					if ( ! $order ) {
						continue;
					}
					foreach ( $order->get_items() as $item ) {
						$folder = is_callable( array( $item, 'get_meta' ) ) ? (string) $item->get_meta( '_pcpb_folder' ) : '';
						if ( '' !== $folder ) {
							self::delete_folder( $folder );
						}
					}
					$order->delete( true );
					$orders++;
				}
			}

			// Remove ONLY the product WE recorded for this sample. The demo-seeder
			// "bag" shares the same `_spbwc_is_sample` flag, so a blind meta query
			// would delete its product too — scope strictly to our recorded id.
			$state      = get_option( self::OPTION_STATE, array() );
			$own_pid    = is_array( $state ) && ! empty( $state['product_id'] ) ? (int) $state['product_id'] : 0;
			$own_folder = is_array( $state ) && ! empty( $state['folder'] ) ? (string) $state['folder'] : '';
			if ( $own_pid && 'product' === get_post_type( $own_pid ) ) {
				wp_delete_post( $own_pid, true );
				$products++;
			}
			if ( '' !== $own_folder ) {
				self::delete_folder( $own_folder );
			}

			delete_option( self::OPTION_STATE );

			return array(
				'orders'   => $orders,
				'products' => $products,
			);
		}

		/** Delete a design folder by id, guarding against path traversal. */
		protected static function delete_folder( $folder ) {
			$folder = is_string( $folder ) ? trim( $folder ) : '';
			if ( '' === $folder || $folder !== basename( $folder ) ) {
				return;
			}
			$path = SPBWC_PB_CUSTOMER_DIR . '/' . $folder;
			if ( is_dir( $path ) ) {
				SPBWC_Storelly_IO::spbwc_delete_folder( $path );
			}
		}

		/* ── Install CTA (Custom Orders screen) ───────────────────── */

		/**
		 * "Install a sample order" CTA form, surfaced on the Custom Orders screen so
		 * a merchant can self-serve the sample. Posts to ACTION_ADD with
		 * spbwc_co_after=view so it lands on the new order's detail workspace.
		 * Returns '' when the bundle is missing or the sample is already installed.
		 *
		 * @param string $context 'hero' (compact) | 'empty' (with hint).
		 * @return string Escaped HTML, or ''.
		 */
		public static function install_cta_html( $context = 'empty' ) {
			if ( ! self::bundle_available() || self::exists() ) {
				return '';
			}
			ob_start();
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spbwc-co-sample-cta spbwc-co-sample-cta--<?php echo esc_attr( $context ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_ADD ); ?>" />
				<input type="hidden" name="spbwc_co_after" value="view" />
				<?php wp_nonce_field( self::ACTION_ADD ); ?>
				<button type="submit" class="spbwc-cta-btn spbwc-cta-btn--solid spbwc-cta-btn--sm">
					<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Install a sample order', 'storelly-product-builder-for-woocommerce' ); ?>
				</button>
				<?php if ( 'empty' === $context ) : ?>
					<span class="spbwc-co-sample-cta__hint"><?php esc_html_e( 'Adds one labelled sample order with its own design folder so you can explore this workspace. Remove it anytime from the Setup Wizard.', 'storelly-product-builder-for-woocommerce' ); ?></span>
				<?php endif; ?>
			</form>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * "Remove sample" CTA form (Custom Orders hero + the detail-page sample
		 * banner), so the install↔remove loop lives where the sample is, not only
		 * in the Setup Wizard. Returns '' when no sample is installed.
		 *
		 * @param string $context 'hero' | 'banner'.
		 * @return string Escaped HTML, or ''.
		 */
		public static function remove_cta_html( $context = 'hero' ) {
			if ( ! self::exists() ) {
				return '';
			}
			ob_start();
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spbwc-co-sample-cta spbwc-co-sample-cta--<?php echo esc_attr( $context ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Remove the sample custom order? This cannot be undone.', 'storelly-product-builder-for-woocommerce' ) ); ?>');" data-spbwc-confirm="<?php echo esc_attr( __( 'Remove the sample custom order? This cannot be undone.', 'storelly-product-builder-for-woocommerce' ) ); ?>" data-spbwc-confirm-title="<?php echo esc_attr( __( 'Remove sample order', 'storelly-product-builder-for-woocommerce' ) ); ?>" data-spbwc-confirm-ok="<?php echo esc_attr( __( 'Remove', 'storelly-product-builder-for-woocommerce' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REMOVE ); ?>" />
				<input type="hidden" name="spbwc_co_after" value="orders" />
				<?php wp_nonce_field( self::ACTION_REMOVE ); ?>
				<button type="submit" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm">
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					<?php esc_html_e( 'Remove sample', 'storelly-product-builder-for-woocommerce' ); ?>
				</button>
			</form>
			<?php
			return (string) ob_get_clean();
		}

		/* ── admin-post handlers + back URL ───────────────────────── */

		protected static function guard() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'You do not have permission to do this.', 'storelly-product-builder-for-woocommerce' ) );
			}
		}

		/** Setup Wizard URL with a result flag for the post-redirect notice. */
		protected static function back_url( $flag, $extra = array() ) {
			$base = admin_url( 'admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '/global-import' );
			return add_query_arg( array_merge( array( 'co_sample' => $flag ), $extra ), $base );
		}

		public static function handle_add() {
			self::guard();
			check_admin_referer( self::ACTION_ADD );
			$res = self::seed();
			if ( is_wp_error( $res ) ) {
				wp_safe_redirect( self::back_url( 'error' ) );
				exit;
			}
			// When launched from the Custom Orders screen, land straight on the new
			// order's detail workspace instead of the Setup Wizard.
			$after = isset( $_POST['spbwc_co_after'] ) ? sanitize_key( wp_unslash( $_POST['spbwc_co_after'] ) ) : '';
			if ( 'view' === $after && ! empty( $res['order_id'] ) && class_exists( 'SPBWC_Custom_Order_Detail' ) ) {
				wp_safe_redirect( SPBWC_Custom_Order_Detail::url( (int) $res['order_id'] ) );
				exit;
			}
			wp_safe_redirect( self::back_url( $res['created'] ? 'added' : 'exists', array( 'view' => (int) $res['order_id'] ) ) );
			exit;
		}

		public static function handle_remove() {
			self::guard();
			check_admin_referer( self::ACTION_REMOVE );
			$res = self::remove_all();
			// Removed from the Custom Orders screen → return there, not the wizard.
			$after = isset( $_POST['spbwc_co_after'] ) ? sanitize_key( wp_unslash( $_POST['spbwc_co_after'] ) ) : '';
			if ( 'orders' === $after ) {
				wp_safe_redirect(
					add_query_arg(
						'spbwc_co_removed',
						(int) $res['orders'],
						admin_url( 'admin.php?page=' . SPBWC_PB_ORDERS_SLUG )
					)
				);
				exit;
			}
			wp_safe_redirect( self::back_url( 'removed', array( 'co_removed' => (int) $res['orders'] ) ) );
			exit;
		}
	}

	SPBWC_Custom_Order_Sample::init();
}
