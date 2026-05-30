<?php
/**
 * Woo Variation Seed — Scanner.
 *
 * Read-only inspection of the store's products to build the eligibility
 * summary shown on Setup Wizard › Import Woo Variations › Step 1.
 *
 * Outputs:
 *   - counts (eligible / linked / simple)
 *   - aggregate attribute info (top types + term counts)
 *   - totals (variations, variations with image, multi-attribute products)
 *   - top-5 preview rows for the collapse list
 *   - last-seed marker (to drive re-entry / resume copy)
 *
 * Never writes anything. Safe to call multiple times.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Woo_Seed_Scanner' ) ) {

	class SPBWC_Woo_Seed_Scanner {

		/** Option key holding the last completed seed marker. */
		const LAST_SEED_OPTION = 'spbwc_woo_seed_last';

		/** How many products to surface in the collapsed preview list. */
		const PREVIEW_LIMIT = 5;

		/**
		 * Run a full scan. Returns the summary structure consumed by the
		 * Step-1 UI; the result is also stored in a short-lived transient so
		 * the same shape can be reused by Step 3 ("Ready to import").
		 *
		 * @return array
		 */
		public function scan() {
			if ( ! function_exists( 'wc_get_product' ) ) {
				return $this->empty_summary();
			}

			$products = $this->query_published_products();

			$summary = array(
				'eligible'         => 0,
				'linked'           => 0,
				'simple'           => 0,
				'attribute_types'  => array(), // label → ['label','is_taxonomy','term_count']
				'total_variations' => 0,
				'with_image'       => 0,
				'multi_attr'       => 0,
				'preview_top'      => array(),
				'eligible_ids'     => array(), // used by run-batch
				'last_seed'        => $this->get_last_seed(),
			);

			foreach ( $products as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				// "Already linked" is a higher-priority bucket than simple/variable
				// because we want to surface that the seed will SKIP these.
				$linked = get_post_meta( $product_id, '_spbwc_option_id', true );
				if ( $linked ) {
					$summary['linked']++;
					continue;
				}

				if ( ! $product->is_type( 'variable' ) ) {
					$summary['simple']++;
					continue;
				}

				$attrs = $this->variation_attributes( $product );
				if ( empty( $attrs ) ) {
					// Variable product with zero is_variation attributes — treat as
					// not eligible (nothing to seed).
					$summary['simple']++;
					continue;
				}

				$summary['eligible']++;
				$summary['eligible_ids'][] = $product_id;

				$variation_ids   = $product->get_children();
				$variation_count = count( $variation_ids );
				$with_image      = 0;
				foreach ( $variation_ids as $vid ) {
					$v = wc_get_product( $vid );
					if ( ! $v ) {
						continue;
					}
					if ( $v->get_image_id() ) {
						$with_image++;
					}
				}

				$summary['total_variations'] += $variation_count;
				$summary['with_image']       += $with_image;
				if ( count( $attrs ) > 1 ) {
					$summary['multi_attr']++;
				}

				foreach ( $attrs as $attr ) {
					$this->merge_attribute_type( $summary['attribute_types'], $attr );
				}

				if ( count( $summary['preview_top'] ) < self::PREVIEW_LIMIT ) {
					$summary['preview_top'][] = array(
						'id'              => $product_id,
						'name'            => $product->get_name(),
						'variation_count' => $variation_count,
						'attr_count'      => count( $attrs ),
						'with_image'      => $with_image,
					);
				}
			}

			// Stable order for the attribute types list (most common first).
			usort(
				$summary['attribute_types'],
				function ( $a, $b ) {
					return $b['product_count'] <=> $a['product_count'];
				}
			);

			set_transient( 'spbwc_woo_seed_scan_cache', $summary, 5 * MINUTE_IN_SECONDS );
			return $summary;
		}

		/**
		 * Return the cached scan if fresh, else run a new one. Used by
		 * Step 3 to avoid double-scanning.
		 *
		 * @return array
		 */
		public function get_or_scan() {
			$cached = get_transient( 'spbwc_woo_seed_scan_cache' );
			if ( is_array( $cached ) && isset( $cached['eligible_ids'] ) ) {
				return $cached;
			}
			return $this->scan();
		}

		public function clear_cache() {
			delete_transient( 'spbwc_woo_seed_scan_cache' );
		}

		/**
		 * Last completed seed marker, or null if none.
		 *
		 * @return array|null  ['job_id','timestamp','count']
		 */
		public function get_last_seed() {
			$raw = get_option( self::LAST_SEED_OPTION, '' );
			if ( ! is_array( $raw ) || empty( $raw['job_id'] ) ) {
				return null;
			}
			return array(
				'job_id'    => (string) $raw['job_id'],
				'timestamp' => isset( $raw['timestamp'] ) ? (int) $raw['timestamp'] : 0,
				'count'     => isset( $raw['count'] ) ? (int) $raw['count'] : 0,
			);
		}

		/**
		 * Persist a marker that a seed run has finished. Idempotent —
		 * overwrites the previous marker.
		 *
		 * @param string $job_id
		 * @param int    $count
		 */
		public function record_seed_completion( $job_id, $count ) {
			update_option(
				self::LAST_SEED_OPTION,
				array(
					'job_id'    => sanitize_text_field( $job_id ),
					'timestamp' => time(),
					'count'     => max( 0, (int) $count ),
				),
				false
			);
		}

		public function clear_last_seed_marker() {
			delete_option( self::LAST_SEED_OPTION );
		}

		/**
		 * Lightweight query — published product post IDs only. Variations
		 * (post_type=product_variation) are excluded; we walk children per
		 * parent product instead.
		 *
		 * @return int[]
		 */
		protected function query_published_products() {
			$args = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// Allow other plugins to scope the scan (e.g. exclude a category).
				'suppress_filters' => false,
			);
			$args = apply_filters( 'spbwc_woo_seed_scan_query_args', $args );

			$q = new WP_Query( $args );
			return is_array( $q->posts ) ? array_map( 'absint', $q->posts ) : array();
		}

		/**
		 * Get the WC product attributes flagged is_variation=true. Each entry
		 * is a small array (label, taxonomy flag, slug, options[]).
		 *
		 * @param WC_Product $product
		 * @return array[]
		 */
		protected function variation_attributes( $product ) {
			$out = array();
			$attrs = $product->get_attributes();
			if ( ! is_array( $attrs ) ) {
				return $out;
			}
			foreach ( $attrs as $attr ) {
				if ( ! is_object( $attr ) || ! method_exists( $attr, 'get_variation' ) ) {
					continue;
				}
				if ( ! $attr->get_variation() ) {
					continue;
				}
				$name        = $attr->get_name();
				$is_taxonomy = $attr->is_taxonomy();
				$label       = $is_taxonomy && function_exists( 'wc_attribute_label' )
					? wc_attribute_label( $name )
					: $name;
				$options     = $attr->get_options();
				$out[]       = array(
					'name'        => $name,
					'label'       => $label,
					'is_taxonomy' => $is_taxonomy,
					'term_count'  => is_array( $options ) ? count( $options ) : 0,
				);
			}
			return $out;
		}

		/**
		 * Accumulate one attribute occurrence into the cross-store roll-up
		 * keyed by attribute label.
		 *
		 * @param array $bucket  Mutated in place.
		 * @param array $attr
		 */
		protected function merge_attribute_type( array &$bucket, array $attr ) {
			$key = strtolower( $attr['name'] );
			if ( ! isset( $bucket[ $key ] ) ) {
				$bucket[ $key ] = array(
					'name'          => $attr['name'],
					'label'         => $attr['label'],
					'is_taxonomy'   => (bool) $attr['is_taxonomy'],
					'term_count'    => (int) $attr['term_count'],
					'product_count' => 0,
				);
			}
			$bucket[ $key ]['product_count']++;
			// Term count is per-product for custom attributes; for taxonomies
			// it's the global term set — keep the max we've seen.
			if ( $attr['term_count'] > $bucket[ $key ]['term_count'] ) {
				$bucket[ $key ]['term_count'] = (int) $attr['term_count'];
			}
		}

		protected function empty_summary() {
			return array(
				'eligible'         => 0,
				'linked'           => 0,
				'simple'           => 0,
				'attribute_types'  => array(),
				'total_variations' => 0,
				'with_image'       => 0,
				'multi_attr'       => 0,
				'preview_top'      => array(),
				'eligible_ids'     => array(),
				'last_seed'        => $this->get_last_seed(),
			);
		}
	}
}
