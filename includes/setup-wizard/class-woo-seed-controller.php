<?php
/**
 * Woo Variation Seed — AJAX controller.
 *
 * Endpoints (all admin-ajax, all nonce + manage_options gated):
 *   - spbwc_woo_seed_scan : return scan summary
 *   - spbwc_woo_seed_run  : process one batch of products
 *   - spbwc_woo_seed_log  : poll job state (progress + last log lines)
 *   - spbwc_woo_seed_undo : delete option sets tagged for a job and reset
 *                           the product links
 *
 * Job state is persisted in a transient keyed by job_id, so refreshing
 * the wizard mid-run lets the UI resume polling.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Woo_Seed_Controller' ) ) {

	class SPBWC_Woo_Seed_Controller {

		const NONCE_ACTION = 'spbwc_woo_seed';
		const BATCH_SIZE   = 15;
		const JOB_TTL      = HOUR_IN_SECONDS;
		const LOG_CAP      = 200;

		protected static $instance = null;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function init() {
			add_action( 'wp_ajax_spbwc_woo_seed_scan', array( $this, 'ajax_scan' ) );
			add_action( 'wp_ajax_spbwc_woo_seed_run',  array( $this, 'ajax_run' ) );
			add_action( 'wp_ajax_spbwc_woo_seed_log',  array( $this, 'ajax_log' ) );
			add_action( 'wp_ajax_spbwc_woo_seed_undo', array( $this, 'ajax_undo' ) );
			add_action( 'admin_enqueue_scripts',       array( $this, 'enqueue_assets' ) );
		}

		/**
		 * Enqueue assets for the Setup Wizard pages (landing + Woo seed tab).
		 * Matches the hook-needle pattern used by the existing Sample Products
		 * importer so we layer onto the same admin screen without colliding.
		 *
		 * Loads in two tiers:
		 *   - Landing / Woo tab : design tokens + shared admin-ui component
		 *                         library, so .spbwc-page-hero, .spbwc-quick-card,
		 *                         .spbwc-block, .spbwc-cta-btn etc. all resolve.
		 *   - Woo tab only      : wizard-specific stylesheet + the JS app.
		 *
		 * The Sample Products sub-tab keeps its own asset stack and is left
		 * untouched here.
		 *
		 * @param string $hook
		 */
		public function enqueue_assets( $hook ) {
			$needle = '_page_' . SPBWC_PB_OPTIONS_SLUG . '/global-import';
			if ( false === strpos( (string) $hook, $needle ) ) {
				return;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab check; no state change.
			$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
			// 'sample' has its own asset stack — bail without touching it.
			if ( 'sample' === $tab ) {
				return;
			}

			// Tokens must load before any other Storelly stylesheet so var()
			// references resolve. Register defensively in case no earlier code
			// path queued them on this screen.
			if ( ! wp_style_is( 'spbwc-tokens', 'registered' ) ) {
				wp_register_style( 'spbwc-tokens', SPBWC_PB_CSS_URL . '_tokens.css', array(), SPBWC_PB_VERSION );
			}
			if ( ! wp_style_is( 'spbwc-admin-ui', 'registered' ) ) {
				wp_register_style(
					'spbwc-admin-ui',
					SPBWC_PB_CSS_URL . 'storelly-admin-ui.css',
					array( 'spbwc-tokens', 'dashicons' ),
					SPBWC_PB_VERSION
				);
			}
			wp_enqueue_style( 'spbwc-tokens' );
			wp_enqueue_style( 'spbwc-admin-ui' );

			// Wizard-specific styles + JS only on the Woo tab.
			if ( 'woo' !== $tab ) {
				return;
			}

			$css      = SPBWC_PB_PLUGIN_DIR . 'static/css/woo-seed.css';
			$css_ver  = file_exists( $css ) ? (string) filemtime( $css ) : SPBWC_PB_VERSION;
			wp_enqueue_style(
				'spbwc-woo-seed',
				SPBWC_PB_CSS_URL . 'woo-seed.css',
				array( 'spbwc-admin-ui' ),
				$css_ver
			);

			$js = SPBWC_PB_PLUGIN_DIR . 'static/js/woo-seed-app.js';
			$js_ver = file_exists( $js ) ? (string) filemtime( $js ) : SPBWC_PB_VERSION;
			wp_enqueue_script(
				'spbwc-woo-seed-app',
				SPBWC_PB_JS_URL . 'woo-seed-app.js',
				array(),
				$js_ver,
				true
			);
			wp_localize_script(
				'spbwc-woo-seed-app',
				'spbwcWooSeed',
				array(
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( self::NONCE_ACTION ),
					'landingUrl' => admin_url( 'admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '/global-import' ),
					'i18n'       => array(
						'scanning'           => esc_html__( 'Scanning your products…', 'storelly-product-builder-for-woocommerce' ),
						'scanResults'        => esc_html__( 'Scan results', 'storelly-product-builder-for-woocommerce' ),
						'eligible'           => esc_html__( 'Eligible (variable products)', 'storelly-product-builder-for-woocommerce' ),
						'alreadyLinked'      => esc_html__( 'Already linked to a Storelly option', 'storelly-product-builder-for-woocommerce' ),
						'willSkip'           => esc_html__( '(will be skipped)', 'storelly-product-builder-for-woocommerce' ),
						'simpleSkipped'      => esc_html__( 'Simple / no variation', 'storelly-product-builder-for-woocommerce' ),
						'attrTypes'          => esc_html__( 'Attribute types', 'storelly-product-builder-for-woocommerce' ),
						'totalVariations'    => esc_html__( 'Total variations', 'storelly-product-builder-for-woocommerce' ),
						'withImage'          => esc_html__( 'Variations with image', 'storelly-product-builder-for-woocommerce' ),
						'multiAttr'          => esc_html__( 'Multi-attribute products', 'storelly-product-builder-for-woocommerce' ),
						'lossyNote'          => esc_html__( 'price math will be lossy', 'storelly-product-builder-for-woocommerce' ),
						'viewList'           => esc_html__( 'View product list', 'storelly-product-builder-for-woocommerce' ),
						/* translators: 1: number of products shown in preview, 2: total products available. */
						'previewMore'        => esc_html__( 'Showing %1$d of %2$d.', 'storelly-product-builder-for-woocommerce' ),

						'rules'              => esc_html__( 'Import rules', 'storelly-product-builder-for-woocommerce' ),
						'displayTypeLabel'   => esc_html__( 'Default display type', 'storelly-product-builder-for-woocommerce' ),
						'displayTypeHelp'    => esc_html__( 'You can switch per field after import.', 'storelly-product-builder-for-woocommerce' ),
						'dropdown'           => esc_html__( 'Dropdown', 'storelly-product-builder-for-woocommerce' ),
						'radio'              => esc_html__( 'Radio', 'storelly-product-builder-for-woocommerce' ),
						'swatch'             => esc_html__( 'Swatch', 'storelly-product-builder-for-woocommerce' ),
						'importImagesLabel'  => esc_html__( 'Import variation images as option swatch images', 'storelly-product-builder-for-woocommerce' ),
						/* translators: %d: number of Woo variations that have an image attached. */
						'importImagesHelp'   => esc_html__( '%d variations have images.', 'storelly-product-builder-for-woocommerce' ),
						'priceRuleLabel'     => esc_html__( 'Multi-attribute price rule', 'storelly-product-builder-for-woocommerce' ),
						'priceRuleAvg'       => esc_html__( 'Average across variations', 'storelly-product-builder-for-woocommerce' ),
						'priceRuleEmpty'     => esc_html__( 'Leave price empty (fill in manually)', 'storelly-product-builder-for-woocommerce' ),
						/* translators: %d: number of multi-attribute products affected by the chosen price rule. */
						'priceRuleHelp'      => esc_html__( 'Affects %d products. Final total may differ ±a few units vs original Woo combinations.', 'storelly-product-builder-for-woocommerce' ),
						'nonVariationLabel'  => esc_html__( 'Non-variation attributes (is_visible only)', 'storelly-product-builder-for-woocommerce' ),
						'nonVariationCheck'  => esc_html__( 'Also import as 0-price dropdown', 'storelly-product-builder-for-woocommerce' ),
						'nonVariationHelp'   => esc_html__( 'Off by default — they are typically product specs, not buyer choices.', 'storelly-product-builder-for-woocommerce' ),
						'autoTitle'          => esc_html__( 'Auto', 'storelly-product-builder-for-woocommerce' ),
						'manualTitle'        => esc_html__( "You'll do after", 'storelly-product-builder-for-woocommerce' ),
						'autoList'           => array(
							esc_html__( 'Field per is_variation attribute', 'storelly-product-builder-for-woocommerce' ),
							esc_html__( 'Term name → option name', 'storelly-product-builder-for-woocommerce' ),
							esc_html__( 'Variation image → option image', 'storelly-product-builder-for-woocommerce' ),
							esc_html__( 'Price delta (per rule above)', 'storelly-product-builder-for-woocommerce' ),
							esc_html__( 'Tag option set: woo_seed_<job_id>', 'storelly-product-builder-for-woocommerce' ),
						),
						'manualList'         => array(
							esc_html__( 'Pick hex colors for swatch options', 'storelly-product-builder-for-woocommerce' ),
							esc_html__( 'Swap field display type if needed', 'storelly-product-builder-for-woocommerce' ),
							esc_html__( 'Tweak prices on multi-attribute products', 'storelly-product-builder-for-woocommerce' ),
							esc_html__( 'Decide co-exist vs hide native Woo variation form', 'storelly-product-builder-for-woocommerce' ),
						),

						'readyToImport'      => esc_html__( 'Ready to import', 'storelly-product-builder-for-woocommerce' ),
						/* translators: %d: number of Storelly option sets the seed will create. */
						'summaryCreate'      => esc_html__( 'Create %d Storelly option set(s) (1 per product)', 'storelly-product-builder-for-woocommerce' ),
						/* translators: %d: number of products that will be skipped because they are already linked. */
						'summarySkip'        => esc_html__( 'Skip %d product(s) already linked', 'storelly-product-builder-for-woocommerce' ),
						'summaryDisplay'     => esc_html__( 'Display type:', 'storelly-product-builder-for-woocommerce' ),
						'summaryImages'      => esc_html__( 'Import images:', 'storelly-product-builder-for-woocommerce' ),
						'summaryMultiAttr'   => esc_html__( 'Multi-attr price:', 'storelly-product-builder-for-woocommerce' ),
						'on'                 => esc_html__( 'On', 'storelly-product-builder-for-woocommerce' ),
						'off'                => esc_html__( 'Off', 'storelly-product-builder-for-woocommerce' ),
						'stockWarning'       => esc_html__( 'Stock / SKU per variation will NOT carry over. Woo variations still exist — you can disable them later.', 'storelly-product-builder-for-woocommerce' ),
						'acknowledge'        => esc_html__( 'I understand this is one-time, not a live sync.', 'storelly-product-builder-for-woocommerce' ),
						/* translators: %d: number of products the import button will process. */
						'runBtn'             => esc_html__( 'Import %d products', 'storelly-product-builder-for-woocommerce' ),

						'running'            => esc_html__( 'Importing…', 'storelly-product-builder-for-woocommerce' ),
						/* translators: 1: processed count, 2: skipped count, 3: error count, 4: total products to import. */
						'runningCounts'      => esc_html__( '%1$d processed · %2$d skipped · %3$d errors · %4$d total', 'storelly-product-builder-for-woocommerce' ),
						'log'                => esc_html__( 'Log', 'storelly-product-builder-for-woocommerce' ),
						'stop'               => esc_html__( 'Stop', 'storelly-product-builder-for-woocommerce' ),
						'stopping'           => esc_html__( 'Finishing current batch…', 'storelly-product-builder-for-woocommerce' ),

						'done'               => esc_html__( 'Done', 'storelly-product-builder-for-woocommerce' ),
						/* translators: %d: number of products successfully imported. */
						'doneProcessed'      => esc_html__( '%d imported', 'storelly-product-builder-for-woocommerce' ),
						/* translators: %d: number of products skipped because they were already linked. */
						'doneSkipped'        => esc_html__( '%d skipped (already linked)', 'storelly-product-builder-for-woocommerce' ),
						/* translators: %d: number of products that failed during import. */
						'doneErrors'         => esc_html__( '%d errors', 'storelly-product-builder-for-woocommerce' ),
						'openPricing'        => esc_html__( 'Open Pricing Options', 'storelly-product-builder-for-woocommerce' ),
						'undoBtn'            => esc_html__( 'Undo this seed', 'storelly-product-builder-for-woocommerce' ),
						/* translators: %d: number of option sets that will be deleted by the undo action. */
						'undoConfirm'        => esc_html__( 'Delete %d Storelly option sets created by this seed? Linked products will be unlinked.', 'storelly-product-builder-for-woocommerce' ),
						/* translators: 1: number of option sets deleted, 2: number of products unlinked. */
						'undoOk'             => esc_html__( 'Undone: %1$d sets deleted, %2$d products unlinked.', 'storelly-product-builder-for-woocommerce' ),

						'back'               => esc_html__( 'Back', 'storelly-product-builder-for-woocommerce' ),
						'next'               => esc_html__( 'Next', 'storelly-product-builder-for-woocommerce' ),
						'cancel'             => esc_html__( 'Cancel', 'storelly-product-builder-for-woocommerce' ),
						'rescan'             => esc_html__( 'Re-scan', 'storelly-product-builder-for-woocommerce' ),
						/* translators: 1: current step number, 2: total steps. */
						'stepOf'             => esc_html__( 'Step %1$d of %2$d', 'storelly-product-builder-for-woocommerce' ),

						'scanFailed'         => esc_html__( 'Scan failed. Reload the page to try again.', 'storelly-product-builder-for-woocommerce' ),
						'runFailed'          => esc_html__( 'Import failed.', 'storelly-product-builder-for-woocommerce' ),
						'undoFailed'         => esc_html__( 'Undo failed.', 'storelly-product-builder-for-woocommerce' ),
						'error'              => esc_html__( 'Error:', 'storelly-product-builder-for-woocommerce' ),
					),
				)
			);
		}

		// ────────────────────────────────────────────────────────────────────
		//  AJAX endpoints
		// ────────────────────────────────────────────────────────────────────

		public function ajax_scan() {
			$this->guard();
			$scanner = new SPBWC_Woo_Seed_Scanner();
			$summary = $scanner->scan();
			// Strip the heavy eligible_ids list from the wire payload — it is
			// only needed server-side for the run-batch sequence.
			$response = $summary;
			unset( $response['eligible_ids'] );
			wp_send_json_success( $response );
		}

		public function ajax_run() {
			$this->guard();

			$job_id   = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
			$rules_in = isset( $_POST['rules'] )  ? wp_unslash( $_POST['rules'] )  : array();
			$rules    = $this->sanitize_rules( is_array( $rules_in ) ? $rules_in : array() );

			$state = $job_id ? $this->load_job( $job_id ) : null;
			if ( ! $state ) {
				$state = $this->create_job( $rules );
				if ( is_wp_error( $state ) ) {
					wp_send_json_error( array( 'message' => $state->get_error_message() ) );
				}
				$job_id = $state['job_id'];
			}

			if ( 'done' === $state['status'] ) {
				wp_send_json_success( $this->job_response( $state ) );
			}

			$state = $this->run_batch( $state );
			$this->save_job( $state );

			if ( 'done' === $state['status'] ) {
				$scanner = new SPBWC_Woo_Seed_Scanner();
				$scanner->record_seed_completion( $state['job_id'], $state['processed'] );
				$scanner->clear_cache();
			}

			wp_send_json_success( $this->job_response( $state ) );
		}

		public function ajax_log() {
			$this->guard();
			$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
			$state  = $job_id ? $this->load_job( $job_id ) : null;
			if ( ! $state ) {
				wp_send_json_error( array( 'message' => __( 'Job not found.', 'storelly-product-builder-for-woocommerce' ) ) );
			}
			wp_send_json_success( $this->job_response( $state ) );
		}

		public function ajax_undo() {
			$this->guard();
			$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
			if ( '' === $job_id ) {
				wp_send_json_error( array( 'message' => __( 'Missing job id.', 'storelly-product-builder-for-woocommerce' ) ) );
			}
			$result = $this->undo_job( $job_id );
			wp_send_json_success( $result );
		}

		// ────────────────────────────────────────────────────────────────────
		//  Guards + sanitization
		// ────────────────────────────────────────────────────────────────────

		protected function guard() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
			}
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
			}
		}

		protected function sanitize_rules( array $raw ) {
			$defaults = SPBWC_Woo_Seed_Mapper::default_rules();
			$display  = isset( $raw['display_type'] ) ? sanitize_text_field( (string) $raw['display_type'] ) : $defaults['display_type'];
			if ( ! in_array( $display, array( 'd', 'r', 's' ), true ) ) {
				$display = $defaults['display_type'];
			}
			$price_rule = isset( $raw['multi_attr_price'] ) ? sanitize_text_field( (string) $raw['multi_attr_price'] ) : $defaults['multi_attr_price'];
			if ( ! in_array( $price_rule, array( 'avg', 'empty' ), true ) ) {
				$price_rule = $defaults['multi_attr_price'];
			}
			return array(
				'display_type'          => $display,
				'import_images'         => ! empty( $raw['import_images'] ),
				'multi_attr_price'      => $price_rule,
				'include_non_variation' => ! empty( $raw['include_non_variation'] ),
			);
		}

		// ────────────────────────────────────────────────────────────────────
		//  Job lifecycle
		// ────────────────────────────────────────────────────────────────────

		protected function create_job( array $rules ) {
			$scanner = new SPBWC_Woo_Seed_Scanner();
			$summary = $scanner->get_or_scan();
			if ( empty( $summary['eligible_ids'] ) ) {
				return new WP_Error( 'no_eligible', __( 'No eligible products were found.', 'storelly-product-builder-for-woocommerce' ) );
			}
			$job_id = $this->generate_job_id();
			$state  = array(
				'job_id'       => $job_id,
				'created'      => time(),
				'rules'        => $rules,
				'eligible_ids' => array_values( array_map( 'absint', $summary['eligible_ids'] ) ),
				'cursor'       => 0,
				'processed'    => 0,
				'skipped'      => 0,
				'errors'       => 0,
				'status'       => 'running',
				'log'          => array(
					$this->fmt_log_line( 'BEGIN', sprintf( 'Seed started — %d eligible products', count( $summary['eligible_ids'] ) ) ),
				),
			);
			$this->save_job( $state );
			return $state;
		}

		protected function run_batch( array $state ) {
			$mapper  = new SPBWC_Woo_Seed_Mapper();
			$ids     = $state['eligible_ids'];
			$end     = min( count( $ids ), $state['cursor'] + self::BATCH_SIZE );

			for ( $i = $state['cursor']; $i < $end; $i++ ) {
				$pid = $ids[ $i ];

				// Defensive: re-check the linked-skip rule, in case another
				// admin assigned an option set after the initial scan.
				if ( get_post_meta( $pid, '_spbwc_option_id', true ) ) {
					$state['skipped']++;
					$state['log'][] = $this->fmt_log_line( 'SKIP', sprintf( '#%d already linked', $pid ) );
					continue;
				}

				$set = $mapper->build_option_set( $pid, $state['rules'] );
				if ( null === $set ) {
					$state['skipped']++;
					$state['log'][] = $this->fmt_log_line( 'SKIP', sprintf( '#%d nothing to map', $pid ) );
					continue;
				}

				$row_id = $this->insert_option_row( $set, $state['job_id'] );
				if ( ! $row_id ) {
					$state['errors']++;
					$state['log'][] = $this->fmt_log_line( 'ERR ', sprintf( '#%d DB insert failed', $pid ) );
					continue;
				}

				update_post_meta( $pid, '_spbwc_option_id', $row_id );
				update_post_meta( $pid, '_storelly_pb_enable', 1 );
				delete_transient( 'spbwc_product_builder_' . $pid );

				$state['processed']++;
				$state['log'][] = $this->fmt_log_line(
					'OK  ',
					sprintf(
						'#%d %s — %d field%s, %d option%s%s',
						$pid,
						$set['title'],
						$set['meta']['attr_count'],
						$set['meta']['attr_count'] === 1 ? '' : 's',
						$set['meta']['option_count'],
						$set['meta']['option_count'] === 1 ? '' : 's',
						$set['meta']['is_multi_attr'] ? ' (price=avg)' : ''
					)
				);
			}

			$state['cursor'] = $end;
			if ( $end >= count( $ids ) ) {
				$state['status'] = 'done';
				$state['log'][]  = $this->fmt_log_line(
					'DONE',
					sprintf(
						'Finished — %d processed, %d skipped, %d errors',
						$state['processed'],
						$state['skipped'],
						$state['errors']
					)
				);
			}

			$state['log'] = $this->trim_log( $state['log'] );
			return $state;
		}

		/**
		 * Insert one row into wp_storelly_product_builder_options.
		 * Returns the new row id, or 0 on failure.
		 *
		 * @param array  $set
		 * @param string $job_id
		 * @return int
		 */
		protected function insert_option_row( array $set, $job_id ) {
			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global.
			$table = $wpdb->prefix . 'storelly_product_builder_options';
			$now   = current_time( 'mysql' );
			$uid   = (int) get_current_user_id();
			$ok    = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'title'            => $set['title'],
					'published'        => 1,
					'product_ids'      => serialize( array( (int) $set['product_id'] ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Existing schema stores serialized arrays here.
					'apply_for'        => 'p',
					'product_cats'     => serialize( array() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Existing schema stores serialized arrays here.
					'created'          => $now,
					'modified'         => $now,
					'created_by'       => $uid,
					'modified_by'      => $uid,
					'fields'           => serialize( $set['fields_payload'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Existing schema stores serialized arrays here.
					'template_slug'    => 'woo_seed_' . $job_id,
					'template_version' => '1.0.0',
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
			);
			if ( ! $ok ) {
				return 0;
			}
			return (int) $wpdb->insert_id;
		}

		// ────────────────────────────────────────────────────────────────────
		//  Undo
		// ────────────────────────────────────────────────────────────────────

		/**
		 * Delete all option rows tagged for $job_id and clear the
		 * _spbwc_option_id link on every product that pointed at them.
		 *
		 * @param string $job_id
		 * @return array  ['deleted','unlinked']
		 */
		protected function undo_job( $job_id ) {
			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global.
			$table = $wpdb->prefix . 'storelly_product_builder_options';
			$slug  = 'woo_seed_' . $job_id;
			$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( "SELECT id, product_ids FROM {$table} WHERE template_slug = %s", $slug ),
				ARRAY_A
			);
			$deleted  = 0;
			$unlinked = 0;
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$row_id = (int) $row['id'];
					$pids   = maybe_unserialize( $row['product_ids'] );
					if ( is_array( $pids ) ) {
						foreach ( $pids as $pid ) {
							$pid    = (int) $pid;
							$linked = (int) get_post_meta( $pid, '_spbwc_option_id', true );
							if ( $linked === $row_id ) {
								delete_post_meta( $pid, '_spbwc_option_id' );
								delete_post_meta( $pid, '_storelly_pb_enable' );
								delete_transient( 'spbwc_product_builder_' . $pid );
								$unlinked++;
							}
						}
					}
					$wpdb->delete( $table, array( 'id' => $row_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$deleted++;
				}
			}
			delete_transient( 'spbwc_woo_seed_job_' . $job_id );

			// Only clear the last-seed marker if it points at this job — never
			// nuke an unrelated seed's marker.
			$scanner = new SPBWC_Woo_Seed_Scanner();
			$last    = $scanner->get_last_seed();
			if ( is_array( $last ) && isset( $last['job_id'] ) && $last['job_id'] === $job_id ) {
				$scanner->clear_last_seed_marker();
			}
			$scanner->clear_cache();

			return array(
				'deleted'  => $deleted,
				'unlinked' => $unlinked,
			);
		}

		// ────────────────────────────────────────────────────────────────────
		//  Job state I/O
		// ────────────────────────────────────────────────────────────────────

		protected function load_job( $job_id ) {
			$key = 'spbwc_woo_seed_job_' . $job_id;
			$val = get_transient( $key );
			if ( is_array( $val ) && isset( $val['job_id'] ) ) {
				return $val;
			}
			return null;
		}

		protected function save_job( array $state ) {
			$key = 'spbwc_woo_seed_job_' . $state['job_id'];
			set_transient( $key, $state, self::JOB_TTL );
		}

		protected function job_response( array $state ) {
			$total = count( $state['eligible_ids'] );
			return array(
				'job_id'    => $state['job_id'],
				'status'    => $state['status'],
				'processed' => $state['processed'],
				'skipped'   => $state['skipped'],
				'errors'    => $state['errors'],
				'total'     => $total,
				'progress'  => $total > 0 ? min( 100, (int) round( 100 * $state['cursor'] / $total ) ) : 100,
				'log'       => array_values( $state['log'] ),
			);
		}

		protected function generate_job_id() {
			return 'ws' . wp_generate_password( 8, false, false );
		}

		protected function fmt_log_line( $tag, $msg ) {
			return sprintf( '[%s] %s %s', gmdate( 'H:i:s' ), $tag, $msg );
		}

		protected function trim_log( array $log ) {
			$n = count( $log );
			if ( $n <= self::LOG_CAP ) {
				return $log;
			}
			return array_slice( $log, $n - self::LOG_CAP );
		}
	}
}
