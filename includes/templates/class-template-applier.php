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
		 * @param string $slug         template slug (matches catalog)
		 * @param string $apply_for    'p' (products) or 'c' (categories)
		 * @param int[]  $scope_ids    product IDs (if 'p') OR category term IDs (if 'c')
		 * @param string $custom_title optional override; defaults to template's localized name
		 *
		 * @return array  ['success' => bool, 'option_id' => int, 'message' => string]
		 */
		public function apply( $slug, $apply_for, $scope_ids, $custom_title = '' ) {
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
					delete_transient( 'spbwc_product_builder_' . $pid );
					set_transient( 'spbwc_product_builder_' . $pid, $option_id, HOUR_IN_SECONDS );
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
