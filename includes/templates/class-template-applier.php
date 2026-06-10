<?php
/**
 * Apply a bundled template to product(s) or category(ies) — forks the
 * template into a new row in wp_storelly_product_builder_options.
 *
 * Fork semantics: template_slug + template_version are stamped on the row
 * so admin can later see "Update available" badge or click "Reset to
 * template" — the JSON file under storage/print-templates/ is never modified.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Template_Applier' ) ) {

	class SPBWC_Template_Applier {

		protected static $instance = null;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Fork a bundled template into a brand-new options row.
		 *
		 * Enforces the single-source pricing-option assignment rules: a product
		 * can render at most one option at a time. When applying a product-level
		 * template to products that are already covered by another product-level
		 * option, the call returns a `conflict` payload unless `$force` is true;
		 * on force, the product is moved off the old option's product_ids
		 * (move semantics). See docs/SPEC_PRICING_OPTION_ASSIGNMENT.md §3.3.
		 *
		 * @param string $slug         template slug (matches catalog)
		 * @param string $apply_for    'p' (products) or 'c' (categories)
		 * @param int[]  $scope_ids    product IDs (if 'p') OR category term IDs (if 'c')
		 * @param string $custom_title optional override; defaults to template's localized name
		 * @param bool   $force        when true, skip the product-level conflict pre-check
		 *                             and move products off their previous option (used when
		 *                             the merchant has explicitly confirmed the replacement).
		 *
		 * @return array  When success: ['success' => true, 'option_id' => int, 'message' => string, 'edit_url' => string].
		 *                When conflict (apply_for='p' and !$force and conflicts found):
		 *                  ['success' => false, 'conflict' => true, 'conflicts' => [...], 'message' => string].
		 *                When error: ['success' => false, 'option_id' => 0, 'message' => string].
		 */
		public function apply( $slug, $apply_for, $scope_ids, $custom_title = '', $force = false ) {
			if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
				return $this->fail( __( 'Insufficient permissions.', 'storelly-product-builder-for-woocommerce' ) );
			}

			$catalog = SPBWC_Template_Catalog::instance();
			$meta    = $catalog->get_template_meta( $slug );
			if ( ! is_array( $meta ) ) {
				return $this->fail( __( 'Template not found in catalog.', 'storelly-product-builder-for-woocommerce' ) );
			}

			$apply_for = in_array( $apply_for, array( 'p', 'c', 'a' ), true ) ? $apply_for : 'p';

			$scope_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $scope_ids ) ) ) );
			if ( 'a' !== $apply_for && empty( $scope_ids ) ) {
				return $this->fail( __( 'Please select at least one product or category.', 'storelly-product-builder-for-woocommerce' ) );
			}

			// Conflict pre-check — only product-level apply enforces single
			// assignment at write time. Category overlap is resolved silently
			// by precedence at read time (Rule 5 in the spec).
			if ( 'p' === $apply_for && ! $force && ! empty( $scope_ids ) ) {
				$conflicts = $this->detect_conflicts( $scope_ids );
				if ( ! empty( $conflicts ) ) {
					return array(
						'success'   => false,
						'conflict'  => true,
						'conflicts' => $conflicts,
						'message'   => __( 'Some products are already assigned to another pricing option. Confirm to replace.', 'storelly-product-builder-for-woocommerce' ),
					);
				}
			}

			$template_data = $catalog->get_template_data( $slug );
			if ( ! is_array( $template_data ) ) {
				return $this->fail( __( 'Template file unreadable.', 'storelly-product-builder-for-woocommerce' ) );
			}

			$product_ids  = ( 'p' === $apply_for ) ? array_map( 'strval', $scope_ids ) : array();
			$product_cats = ( 'c' === $apply_for ) ? array_map( 'strval', $scope_ids ) : array();

			// Stamp scope onto the JSON blob so frontend renderer sees the same shape
			// existing options use.
			$template_data['id']           = '';
			$template_data['apply_for']    = $apply_for;
			$template_data['product_ids']  = $product_ids;
			$template_data['product_cats'] = $product_cats;

			// Bundled template JSON stores each field's general/appearance settings as descriptor
			// objects ( { title, type, value, ... } ). The renderer (PHP field loop + JS nbd_fields)
			// expects the flat value ( "y" ), so collapse descriptors to their value before
			// persisting — matching the shape produced by Global Import.
			$template_data = $this->flatten_field_descriptors( $template_data );

			$title = '' !== trim( (string) $custom_title )
				? sanitize_text_field( $custom_title )
				: $this->derive_title( $meta );

			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global.
			$table = $wpdb->prefix . 'storelly_product_builder_options';
			$now   = ( new DateTime() )->format( 'Y-m-d H:i:s' );
			$uid   = wp_get_current_user()->ID;

			$row     = array(
				'title'            => $title,
				'published'        => 1,
				'product_ids'      => serialize( $product_ids ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Existing column shape uses PHP-serialized arrays.
				'apply_for'        => $apply_for,
				'product_cats'     => serialize( $product_cats ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Existing column shape.
				'created'          => $now,
				'modified'         => $now,
				'created_by'       => $uid,
				'modified_by'      => $uid,
				'fields'           => serialize( $template_data ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Existing column shape.
				'builder'          => '',
				'template_slug'    => isset( $meta['slug'] ) ? (string) $meta['slug'] : '',
				'template_version' => isset( $meta['template_version'] ) ? (string) $meta['template_version'] : '1.0.0',
			);
			$formats = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; caches flushed below.
			$inserted = $wpdb->insert( $table, $row, $formats );
			if ( false === $inserted ) {
				return $this->fail( '' !== $wpdb->last_error ? $wpdb->last_error : __( 'Database insert failed.', 'storelly-product-builder-for-woocommerce' ) );
			}
			$option_id = (int) $wpdb->insert_id;

			if ( 'p' === $apply_for && ! empty( $scope_ids ) ) {
				foreach ( $scope_ids as $pid ) {
					// Move semantics — if the product was previously covered by
					// another product-level option, remove it from that option's
					// product_ids so it doesn't keep claiming the product
					// (Rule 4 in the spec). Skipped when the previous option
					// is the one we just inserted (defensive — shouldn't happen
					// since insert just generated $option_id, but harmless).
					$old_option_id = $this->current_product_level_option_id( $pid );
					if ( $old_option_id > 0 && $old_option_id !== $option_id ) {
						$this->remove_product_from_option( $old_option_id, $pid );
					}
					// No hard transient override — let the resolver recompute via
					// the new pointer (`_spbwc_option_id`). The transient is only
					// a derived cache now.
					delete_transient( 'spbwc_product_builder_' . $pid );
					update_post_meta( $pid, '_spbwc_option_id', $option_id );
					update_post_meta( $pid, '_storelly_pb_enable', 1 );
				}
			}

			/**
			 * Fires after a bundled template is applied as a new option.
			 *
			 * @param int    $option_id  The new row ID.
			 * @param string $slug       Template slug.
			 * @param array  $row        Inserted row data (post-serialization).
			 */
			do_action( 'spbwc_template_applied', $option_id, $slug, $row );

			return array(
				'success'   => true,
				'option_id' => $option_id,
				'message'   => __( 'Template applied successfully.', 'storelly-product-builder-for-woocommerce' ),
				'edit_url'  => esc_url_raw(
					add_query_arg(
						array(
							'page'   => SPBWC_PB_BUILDER_SLUG,
							'action' => 'edit',
							'id'     => $option_id,
						),
						admin_url( 'admin.php' )
					)
				),
			);
		}

		/**
		 * Install a bundled template as an UNATTACHED "sample" pricing option.
		 *
		 * Used by the Welcome Wizard: the merchant picks a few templates to explore,
		 * and we fork each into its own option row WITHOUT binding it to any product
		 * or category. Because the row carries no product_ids and no product points
		 * at it via `_spbwc_option_id`, the resolver never surfaces it on the
		 * storefront — it just appears in the Pricing Options list, ready to edit,
		 * assign, or delete. The title is suffixed "(Sample)" and the blob carries an
		 * `is_sample` marker so it reads unmistakably as throwaway demo data.
		 *
		 * Unlike apply(), there is NO conflict check and NO product mutation — there
		 * is nothing to conflict with.
		 *
		 * @param string $slug Template slug (matches catalog).
		 * @return array ['success'=>bool,'option_id'=>int,'title'=>string,'message'=>string]
		 */
		public function install_sample( $slug ) {
			if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
				return $this->fail( __( 'Insufficient permissions.', 'storelly-product-builder-for-woocommerce' ) );
			}

			$catalog = SPBWC_Template_Catalog::instance();
			$meta    = $catalog->get_template_meta( $slug );
			if ( ! is_array( $meta ) ) {
				return $this->fail( __( 'Template not found in catalog.', 'storelly-product-builder-for-woocommerce' ) );
			}
			$template_data = $catalog->get_template_data( $slug );
			if ( ! is_array( $template_data ) ) {
				return $this->fail( __( 'Template file unreadable.', 'storelly-product-builder-for-woocommerce' ) );
			}

			// Unattached: no product, no category. Stamp the shape the renderer uses.
			$template_data['id']           = '';
			$template_data['apply_for']    = 'p';
			$template_data['product_ids']  = array();
			$template_data['product_cats'] = array();
			$template_data                 = $this->flatten_field_descriptors( $template_data );
			// Marker so the option reads as sample/demo data anywhere it surfaces.
			$template_data['is_sample'] = 1;

			$name  = $this->derive_title( $meta );
			/* translators: %s: template name, e.g. "Business Cards". */
			$title = sprintf( __( '%s (Sample)', 'storelly-product-builder-for-woocommerce' ), $name );

			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global.
			$table = $wpdb->prefix . 'storelly_product_builder_options';
			$now   = ( new DateTime() )->format( 'Y-m-d H:i:s' );
			$uid   = wp_get_current_user()->ID;

			$row = array(
				'title'            => $title,
				'published'        => 1,
				'product_ids'      => serialize( array() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Existing column shape uses PHP-serialized arrays.
				'apply_for'        => 'p',
				'product_cats'     => serialize( array() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Existing column shape.
				'created'          => $now,
				'modified'         => $now,
				'created_by'       => $uid,
				'modified_by'      => $uid,
				'fields'           => serialize( $template_data ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Existing column shape.
				'builder'          => '',
				'template_slug'    => isset( $meta['slug'] ) ? (string) $meta['slug'] : '',
				'template_version' => isset( $meta['template_version'] ) ? (string) $meta['template_version'] : '1.0.0',
			);
			$formats = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert; no per-product caches to flush (unattached).
			$inserted = $wpdb->insert( $table, $row, $formats );
			if ( false === $inserted ) {
				return $this->fail( '' !== $wpdb->last_error ? $wpdb->last_error : __( 'Database insert failed.', 'storelly-product-builder-for-woocommerce' ) );
			}
			$option_id = (int) $wpdb->insert_id;

			/** This action also fires for normal applies — see apply(). */
			do_action( 'spbwc_template_applied', $option_id, $slug, $row );

			return array(
				'success'   => true,
				'option_id' => $option_id,
				'title'     => $title,
				'message'   => __( 'Sample option installed.', 'storelly-product-builder-for-woocommerce' ),
			);
		}

		/**
		 * Collapse field-config descriptor objects to their flat value within each field's
		 * general/appearance blocks, so the saved option matches what the renderer expects.
		 *
		 * @param array $template_data Decoded template JSON.
		 * @return array
		 */
		protected function flatten_field_descriptors( $template_data ) {
			if ( ! is_array( $template_data ) || empty( $template_data['fields'] ) || ! is_array( $template_data['fields'] ) ) {
				return $template_data;
			}
			foreach ( $template_data['fields'] as $idx => $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				if ( isset( $field['general'] ) && is_array( $field['general'] ) ) {
					$field['general'] = $this->collapse_descriptors( $field['general'] );
				}
				if ( isset( $field['appearance'] ) && is_array( $field['appearance'] ) ) {
					$field['appearance'] = $this->collapse_descriptors( $field['appearance'] );
				}
				$template_data['fields'][ $idx ] = $field;
			}
			return $template_data;
		}

		/**
		 * Recursively replace any { value, type, title } descriptor object with its 'value'.
		 *
		 * @param mixed $value Value to collapse.
		 * @return mixed
		 */
		protected function collapse_descriptors( $value ) {
			if ( is_array( $value )
				&& array_key_exists( 'value', $value )
				&& array_key_exists( 'type', $value )
				&& array_key_exists( 'title', $value ) ) {
				return $value['value'];
			}
			if ( is_array( $value ) ) {
				foreach ( $value as $key => $sub ) {
					$value[ $key ] = $this->collapse_descriptors( $sub );
				}
			}
			return $value;
		}

		/**
		 * For each product in $scope_ids, find whether it already has a
		 * product-level option assigned. Returns one entry per product that
		 * does, suitable for the AJAX conflict payload.
		 *
		 * @param int[] $scope_ids
		 * @return array<int, array{product_id:int, product_title:string, current_option_id:int, current_option_title:string}>
		 */
		protected function detect_conflicts( $scope_ids ) {
			$out = array();
			foreach ( $scope_ids as $pid ) {
				$pid = absint( $pid );
				if ( $pid <= 0 ) {
					continue;
				}
				$current_id = $this->current_product_level_option_id( $pid );
				if ( $current_id <= 0 ) {
					continue;
				}
				$out[] = array(
					'product_id'           => $pid,
					'product_title'        => (string) get_the_title( $pid ),
					'current_option_id'    => $current_id,
					'current_option_title' => $this->option_title( $current_id ),
				);
			}
			return $out;
		}

		/**
		 * Find the product-level ('p') option currently assigned to a product.
		 * Pointer-first (mirrors the resolver in spbwc_get_product_option) with
		 * a scan fallback for legacy data where the pointer was never written.
		 *
		 * @param int $pid Product post ID.
		 * @return int Option id, or 0 when no product-level option claims this product.
		 */
		protected function current_product_level_option_id( $pid ) {
			$pid = absint( $pid );
			if ( $pid <= 0 ) {
				return 0;
			}
			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global.
			$table = $wpdb->prefix . 'storelly_product_builder_options';

			// Pointer is authoritative when set and the option is still published.
			$pointer = (int) get_post_meta( $pid, '_spbwc_option_id', true );
			if ( $pointer > 0 ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name from $wpdb->prefix; value parameterized; lightweight single-row lookup.
				$published = (int) $wpdb->get_var( $wpdb->prepare( "SELECT published FROM {$table} WHERE id = %d LIMIT 1", $pointer ) );
				if ( 1 === $published ) {
					return $pointer;
				}
			}

			// Legacy scan — any published 'p' option that lists $pid.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name from $wpdb->prefix; values parameterized; small result set.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, product_ids FROM {$table} WHERE published = %d AND apply_for = %s ORDER BY id DESC", 1, 'p' ), ARRAY_A );
			if ( ! is_array( $rows ) ) {
				return 0;
			}
			foreach ( $rows as $row ) {
				$ids = maybe_unserialize( $row['product_ids'] );
				if ( ! is_array( $ids ) ) {
					continue;
				}
				$ids = array_map( 'absint', $ids );
				if ( in_array( $pid, $ids, true ) ) {
					return (int) $row['id'];
				}
			}
			return 0;
		}

		/**
		 * Look up the human-readable title of an option row. Cheap single-row
		 * read; used only for the conflict payload shown in the confirm dialog.
		 *
		 * @param int $option_id
		 * @return string Title, or '' when the option has no title or no row.
		 */
		protected function option_title( $option_id ) {
			$option_id = absint( $option_id );
			if ( $option_id <= 0 ) {
				return '';
			}
			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global.
			$table = $wpdb->prefix . 'storelly_product_builder_options';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name from $wpdb->prefix; value parameterized; single-row read.
			return (string) $wpdb->get_var( $wpdb->prepare( "SELECT title FROM {$table} WHERE id = %d LIMIT 1", $option_id ) );
		}

		/**
		 * Remove a product id from another option's product_ids and flush the
		 * caches that the resolver consults. Used by the move semantics when a
		 * merchant confirms replacing an existing product-level option.
		 *
		 * @param int $option_id Option row whose product_ids should be edited.
		 * @param int $pid       Product id to remove.
		 * @return void
		 */
		protected function remove_product_from_option( $option_id, $pid ) {
			$option_id = absint( $option_id );
			$pid       = absint( $pid );
			if ( $option_id <= 0 || $pid <= 0 ) {
				return;
			}
			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global.
			$table = $wpdb->prefix . 'storelly_product_builder_options';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name from $wpdb->prefix; value parameterized; single-row read followed by single-row update; caches flushed below.
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT product_ids FROM {$table} WHERE id = %d LIMIT 1", $option_id ), ARRAY_A );
			if ( ! is_array( $row ) || ! isset( $row['product_ids'] ) ) {
				return;
			}
			$ids = maybe_unserialize( $row['product_ids'] );
			if ( ! is_array( $ids ) ) {
				return;
			}
			$new_ids = array();
			foreach ( $ids as $existing ) {
				$existing_id = absint( $existing );
				if ( $existing_id > 0 && $existing_id !== $pid ) {
					$new_ids[] = (string) $existing_id;
				}
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write; caches/transients flushed below.
			$wpdb->update(
				$table,
				array( 'product_ids' => serialize( $new_ids ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Existing column shape uses PHP-serialized arrays.
				array( 'id' => $option_id ),
				array( '%s' ),
				array( '%d' )
			);
			SPBWC_Storelly_PB_Admin_Options::instance()->spbwc_flush_option_caches( $option_id, array( $pid ) );
		}

		protected function derive_title( $meta ) {
			$catalog = SPBWC_Template_Catalog::instance();
			$name    = $catalog->get_display_name( $meta );
			if ( '' === $name ) {
				$name = ucwords( str_replace( '-', ' ', $meta['slug'] ?? 'Template' ) );
			}
			return $name;
		}

		protected function fail( $message ) {
			return array(
				'success'   => false,
				'option_id' => 0,
				'message'   => (string) $message,
			);
		}
	}
}
