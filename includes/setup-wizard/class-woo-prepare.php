<?php
/**
 * Auto-first Woo migrate — prepare-then-publish (M4).
 *
 * The Setup Wizard's Import Woo Variations flow (SPBWC_Woo_Seed_Controller)
 * is user-driven and publishes option sets LIVE immediately. For the
 * auto-first onboarding (docs/SPEC_ONBOARDING_ACTIVATION.md §3.3) we want
 * the opposite default: the moment an engaged merchant opens the Welcome
 * dashboard we quietly turn their existing WooCommerce variable products
 * into Storelly option sets **in the background**, but kept as
 * `published = 0` so NOTHING changes on the storefront yet. The merchant
 * then reviews and flips everything live with one "Publish all" click —
 * or undoes it. Storelly options layer on top; the native Woo variation
 * form is never touched, so the whole thing is reversible.
 *
 * Why a separate class from the wizard controller:
 *   - The wizard is shipped and writes published=1 inline. Keeping the
 *     auto-prepare isolated means zero risk to that working flow.
 *   - We tag prepared rows with a DISTINCT template_slug prefix
 *     ('woo_prep_') so this Undo and the wizard's Undo never collide.
 *
 * Safety / correctness:
 *   - Prepared rows are published=0. The storefront resolver
 *     (SPBWC_Storelly_PB_Admin_Options::spbwc_get_product_option) only
 *     honours a product's `_spbwc_option_id` pointer when the option is
 *     published, so a prepared product shows nothing to buyers until the
 *     merchant clicks Publish all.
 *   - The scanner already excludes products that are already linked, so
 *     re-runs never double-import.
 *   - Heavy work runs via Action Scheduler (never in the activation hook).
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Woo_Prepare' ) ) {

	class SPBWC_Woo_Prepare {

		/** Durable state (survives across days, unlike the wizard's transients). */
		const OPTION_STATE = 'spbwc_woo_prepare';

		/** Action Scheduler hook + group for background batches. */
		const HOOK_RUN = 'spbwc_woo_prepare_run';
		const GROUP    = 'spbwc-woo-prepare';

		/** Nonce action for the Publish-all / Undo AJAX endpoints. */
		const NONCE = 'spbwc_woo_prepare';

		/** Products processed per batch. */
		const BATCH = 15;

		/** template_slug prefix for prepared rows (distinct from wizard's woo_seed_). */
		const SLUG_PREFIX = 'woo_prep_';

		protected static $instance = null;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public static function init() {
			$self = self::instance();
			add_action( self::HOOK_RUN, array( $self, 'run_batch_action' ), 10, 1 );
			add_action( 'wp_ajax_spbwc_woo_prepare_publish', array( $self, 'ajax_publish_all' ) );
			add_action( 'wp_ajax_spbwc_woo_prepare_undo', array( $self, 'ajax_undo' ) );
			add_action( 'wp_ajax_spbwc_woo_prepare_status', array( $self, 'ajax_status' ) );
		}

		/**
		 * Default import rules for the auto-prepare (merchant isn't asked the
		 * wizard's Step-2 questions). Chosen for safety:
		 *   - multi_attr_price = 'empty' → never lossy; merchant fills prices
		 *     on review before publishing.
		 *   - display_type = 's' (swatch), import variation images on.
		 *   - non-variation attributes off (usually specs, not buyer choices).
		 */
		public static function default_rules() {
			return array(
				'display_type'          => 's',
				'import_images'         => true,
				'multi_attr_price'      => 'empty',
				'include_non_variation' => false,
			);
		}

		// ────────────────────────────────────────────────────────────────────
		//  Scheduling (called once from the Overview Welcome render)
		// ────────────────────────────────────────────────────────────────────

		/**
		 * Kick the background prepare exactly once for an engaged merchant.
		 * Deliberately cheap: it only writes a tiny sentinel option and queues
		 * a background action. The catalogue scan itself runs in that
		 * background action (see run_batch) so a large store never slows the
		 * dashboard load. No-op if we've already run or WooCommerce is absent.
		 * Safe to call on every Overview load.
		 */
		public function maybe_schedule() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			// Already started / finished — never auto-rerun. (Merchant can use
			// the Setup Wizard to import more later.)
			if ( false !== get_option( self::OPTION_STATE, false ) ) {
				return;
			}
			if ( ! class_exists( 'SPBWC_Woo_Seed_Scanner' ) || ! function_exists( 'wc_get_product' ) ) {
				return;
			}

			$job_id = $this->new_job_id();
			update_option(
				self::OPTION_STATE,
				array(
					'job_id'     => $job_id,
					'status'     => 'scheduled', // scan happens in the background step
					'rules'      => self::default_rules(),
					'cursor'     => 0,
					'prepared'   => 0,
					'multi_attr' => 0,
					'total'      => 0,
					'created'    => time(),
				),
				false
			);

			$this->enqueue_next( $job_id );
		}

		/** Queue the next background batch (or run inline if Action Scheduler is absent). */
		protected function enqueue_next( $job_id ) {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( self::HOOK_RUN, array( $job_id ), self::GROUP );
				return;
			}
			// Fallback: no Action Scheduler — process inline. Bounded by the
			// batch loop inside run_batch(); we drive it to completion here.
			$guard = 0;
			do {
				$done = $this->run_batch( $job_id );
				$guard++;
			} while ( ! $done && $guard < 1000 );
		}

		// ────────────────────────────────────────────────────────────────────
		//  Background batch
		// ────────────────────────────────────────────────────────────────────

		/** Action Scheduler callback. */
		public function run_batch_action( $job_id ) {
			$done = $this->run_batch( (string) $job_id );
			if ( ! $done ) {
				$this->enqueue_next( (string) $job_id );
			}
		}

		/**
		 * Process one batch of eligible products into published=0 option sets
		 * and link them. Returns true when the whole job is finished.
		 *
		 * @param string $job_id
		 * @return bool done
		 */
		protected function run_batch( $job_id ) {
			$state = $this->get_state();
			if ( ! $state || ( isset( $state['job_id'] ) && $state['job_id'] !== $job_id ) ) {
				return true; // stale / superseded
			}

			// First background step: run the (potentially heavy) catalogue scan
			// here, off the dashboard request, then transition to 'preparing'.
			if ( 'scheduled' === $state['status'] ) {
				if ( ! class_exists( 'SPBWC_Woo_Seed_Scanner' ) || ! function_exists( 'wc_get_product' ) ) {
					return true;
				}
				$summary = ( new SPBWC_Woo_Seed_Scanner() )->scan();
				$ids     = isset( $summary['eligible_ids'] ) ? array_values( array_map( 'absint', $summary['eligible_ids'] ) ) : array();
				if ( empty( $ids ) ) {
					$state['status'] = 'empty';
					update_option( self::OPTION_STATE, $state, false );
					return true;
				}
				$state['status']     = 'preparing';
				$state['ids']        = $ids;
				$state['total']      = count( $ids );
				$state['multi_attr'] = isset( $summary['multi_attr'] ) ? (int) $summary['multi_attr'] : 0;
				update_option( self::OPTION_STATE, $state, false );
				return false; // continue into the batching steps
			}

			if ( 'preparing' !== $state['status'] ) {
				return true;
			}
			if ( ! class_exists( 'SPBWC_Woo_Seed_Mapper' ) ) {
				return true;
			}

			$mapper = new SPBWC_Woo_Seed_Mapper();
			$ids    = isset( $state['ids'] ) ? $state['ids'] : array();
			$cursor = (int) $state['cursor'];
			$end    = min( count( $ids ), $cursor + self::BATCH );

			for ( $i = $cursor; $i < $end; $i++ ) {
				$pid = (int) $ids[ $i ];

				// Skip if another flow linked it since the scan.
				if ( get_post_meta( $pid, '_spbwc_option_id', true ) ) {
					continue;
				}

				$set = $mapper->build_option_set( $pid, $state['rules'] );
				if ( null === $set ) {
					continue;
				}

				$row_id = $this->insert_pending_row( $set, $job_id );
				if ( ! $row_id ) {
					continue;
				}

				// Link the product to the (still unpublished) option set.
				update_post_meta( $pid, '_spbwc_option_id', $row_id );
				update_post_meta( $pid, '_storelly_pb_enable', 1 );
				delete_transient( 'spbwc_product_builder_' . $pid );

				$state['prepared'] = (int) $state['prepared'] + 1;
			}

			$state['cursor'] = $end;
			if ( $end >= count( $ids ) ) {
				$state['status'] = 'prepared';
				if ( class_exists( 'SPBWC_Woo_Seed_Scanner' ) ) {
					( new SPBWC_Woo_Seed_Scanner() )->clear_cache();
				}
			}
			update_option( self::OPTION_STATE, $state, false );

			return ( 'prepared' === $state['status'] );
		}

		/**
		 * Insert one option row with published = 0 (staged). Mirrors the
		 * wizard's column layout so it reads identically in the admin lists,
		 * but stays invisible on the storefront until Publish all.
		 *
		 * @param array  $set
		 * @param string $job_id
		 * @return int row id, or 0 on failure
		 */
		protected function insert_pending_row( array $set, $job_id ) {
			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global.
			$table = $wpdb->prefix . 'storelly_product_builder_options';
			$now   = current_time( 'mysql' );
			$uid   = (int) get_current_user_id();
			$ok    = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'title'            => $set['title'],
					'published'        => 0, // ← staged: invisible to buyers until Publish all.
					'product_ids'      => serialize( array( (int) $set['product_id'] ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- existing schema stores serialized arrays.
					'apply_for'        => 'p',
					'product_cats'     => serialize( array() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- existing schema stores serialized arrays.
					'created'          => $now,
					'modified'         => $now,
					'created_by'       => $uid,
					'modified_by'      => $uid,
					'fields'           => serialize( $set['fields_payload'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- existing schema stores serialized arrays.
					'template_slug'    => self::SLUG_PREFIX . $job_id,
					'template_version' => '1.0.0',
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
			);
			return $ok ? (int) $wpdb->insert_id : 0;
		}

		// ────────────────────────────────────────────────────────────────────
		//  Publish all / Undo (AJAX, admin-only)
		// ────────────────────────────────────────────────────────────────────

		public function ajax_publish_all() {
			$this->guard();
			$result = $this->publish_all();
			wp_send_json_success( $result );
		}

		public function ajax_undo() {
			$this->guard();
			$result = $this->undo();
			wp_send_json_success( $result );
		}

		public function ajax_status() {
			$this->guard();
			wp_send_json_success( $this->public_state() );
		}

		/**
		 * Flip every staged row for the current job to published=1 and bust
		 * the per-product resolver caches so the storefront picks them up.
		 *
		 * @return array ['published' => int]
		 */
		public function publish_all() {
			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global.
			$state = $this->get_state();
			if ( ! $state || empty( $state['job_id'] ) ) {
				return array( 'published' => 0 );
			}
			$table = $wpdb->prefix . 'storelly_product_builder_options';
			$slug  = self::SLUG_PREFIX . $state['job_id'];

			// Collect ids + product ids first so we can bust their caches after the flip.
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( "SELECT id, product_ids FROM {$table} WHERE template_slug = %s AND published = 0", $slug ),
				ARRAY_A
			);
			$count = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"UPDATE {$table} SET published = 1, modified = %s WHERE template_slug = %s AND published = 0",
					current_time( 'mysql' ),
					$slug
				)
			);
			$row_ids  = array();
			$prod_ids = array();
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$row_ids[] = (int) $row['id'];
					$pids      = maybe_unserialize( $row['product_ids'] );
					if ( is_array( $pids ) ) {
						foreach ( $pids as $pid ) {
							$prod_ids[] = (int) $pid;
						}
					}
				}
			}
			// Bust the per-option cache, every affected product's resolver
			// transient, and the published-options list. Without this, an
			// active object cache keeps serving the stale published=0 row and
			// the freshly published products stay hidden on the storefront.
			$this->flush_caches( $row_ids, $prod_ids );

			$state['status']       = 'published';
			$state['published']    = (int) $count;
			$state['published_at'] = time();
			update_option( self::OPTION_STATE, $state, false );

			return array( 'published' => (int) $count );
		}

		/**
		 * Delete every staged row for the current job and unlink the products.
		 * Native Woo variations are untouched, so the store reverts cleanly.
		 *
		 * @return array ['deleted' => int, 'unlinked' => int]
		 */
		public function undo() {
			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global.
			$state = $this->get_state();
			if ( ! $state || empty( $state['job_id'] ) ) {
				return array( 'deleted' => 0, 'unlinked' => 0 );
			}
			$table = $wpdb->prefix . 'storelly_product_builder_options';
			$slug  = self::SLUG_PREFIX . $state['job_id'];

			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( "SELECT id, product_ids FROM {$table} WHERE template_slug = %s", $slug ),
				ARRAY_A
			);
			$deleted  = 0;
			$unlinked = 0;
			$row_ids  = array();
			$prod_ids = array();
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$row_id    = (int) $row['id'];
					$row_ids[] = $row_id;
					$pids      = maybe_unserialize( $row['product_ids'] );
					if ( is_array( $pids ) ) {
						foreach ( $pids as $pid ) {
							$pid        = (int) $pid;
							$prod_ids[] = $pid;
							$linked     = (int) get_post_meta( $pid, '_spbwc_option_id', true );
							if ( $linked === $row_id ) {
								delete_post_meta( $pid, '_spbwc_option_id' );
								delete_post_meta( $pid, '_storelly_pb_enable' );
								$unlinked++;
							}
						}
					}
					$wpdb->delete( $table, array( 'id' => $row_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$deleted++;
				}
			}
			$this->flush_caches( $row_ids, $prod_ids );

			// Reset to 'undone' so the dashboard banner disappears but we don't
			// auto-rescan (merchant can re-run via the Setup Wizard).
			$state['status']   = 'undone';
			$state['prepared'] = 0;
			update_option( self::OPTION_STATE, $state, false );

			return array( 'deleted' => $deleted, 'unlinked' => $unlinked );
		}

		// ────────────────────────────────────────────────────────────────────
		//  State helpers (consumed by the Overview banner)
		// ────────────────────────────────────────────────────────────────────

		public function get_state() {
			$state = get_option( self::OPTION_STATE, false );
			return is_array( $state ) ? $state : false;
		}

		/** Trimmed state safe to expose to the dashboard JS. */
		public function public_state() {
			$state = $this->get_state();
			if ( ! $state ) {
				return array( 'status' => 'none' );
			}
			return array(
				'status'     => isset( $state['status'] ) ? (string) $state['status'] : 'none',
				'total'      => isset( $state['total'] ) ? (int) $state['total'] : 0,
				'prepared'   => isset( $state['prepared'] ) ? (int) $state['prepared'] : 0,
				'multi_attr' => isset( $state['multi_attr'] ) ? (int) $state['multi_attr'] : 0,
				'published'  => isset( $state['published'] ) ? (int) $state['published'] : 0,
			);
		}

		/** True when there are staged rows awaiting the merchant's Publish all. */
		public function has_pending() {
			$state = $this->get_state();
			return $state && 'prepared' === $state['status'] && (int) $state['prepared'] > 0;
		}

		/**
		 * Invalidate every cache layer the storefront option resolver
		 * (SPBWC_Storelly_PB_Admin_Options::spbwc_get_product_option) reads, so
		 * a publish/undo shows up immediately — correct even within one request
		 * and even with a persistent object cache (Redis/Memcache) active:
		 *   - each affected option's own cache entry (Step-1 pointer path)
		 *   - each affected product's resolver transient — via delete_transient()
		 *     (WP API) so it busts BOTH the stored value and WP's in-request
		 *     option cache; a direct-SQL delete would leave the in-memory copy
		 *     stale within the same request
		 *   - the published-options list cache (Step-2 fallback path)
		 *
		 * @param int[] $row_ids     Affected option row ids.
		 * @param int[] $product_ids Affected product ids.
		 */
		protected function flush_caches( array $row_ids, array $product_ids = array() ) {
			foreach ( $row_ids as $rid ) {
				wp_cache_delete( 'spbwc_option_' . (int) $rid, 'spbwc_product_builder' );
			}
			foreach ( $product_ids as $pid ) {
				delete_transient( 'spbwc_product_builder_' . (int) $pid );
			}
			wp_cache_delete( 'spbwc_published_options', 'spbwc_product_builder' );
		}

		protected function guard() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
			}
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
			}
		}

		/** Job id that doesn't depend on Math.random()/time-of-day collisions. */
		protected function new_job_id() {
			return substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 12 );
		}
	}

	SPBWC_Woo_Prepare::init();
}
