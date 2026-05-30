<?php
/**
 * Woo Variation Seed — Mapper.
 *
 * Pure transform: given a single variable product + admin-chosen rules,
 * produce a Storelly option-set payload (descriptor-shape) that the
 * controller can serialize into the wp_storelly_product_builder_options
 * table.
 *
 * Pricing model (matches the engine, which only does surcharges):
 *   baseline       = min variation price across the product
 *   delta(option)  = price(option) − baseline
 *   single-attr    : price(option) = the matching variation's price
 *   multi-attr     : price(option) = avg(prices of variations where attr=v)
 *                                    OR empty if rule = 'empty'
 *   wildcard ("Any") variations are skipped in per-level aggregation —
 *   they don't help distinguish levels and would bias the avg.
 *
 * Variation images map onto option images per first-match (first variation
 * for that level that carries a thumbnail). Merchant can swap later.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Woo_Seed_Mapper' ) ) {

	class SPBWC_Woo_Seed_Mapper {

		/**
		 * Default rules — used when called from non-UI contexts (CLI, tests).
		 *
		 * @return array
		 */
		public static function default_rules() {
			return array(
				'display_type'          => 's', // d|r|s
				'import_images'         => true,
				'multi_attr_price'      => 'avg', // avg|empty
				'include_non_variation' => false,
			);
		}

		/**
		 * Build the option-set payload for one product. Returns null if the
		 * product is not eligible (not variable, no variation attributes,
		 * already linked, etc.) — caller logs the reason.
		 *
		 * @param int   $product_id
		 * @param array $rules
		 * @return array|null  ['title', 'fields_payload', 'product_id', 'meta']
		 */
		public function build_option_set( $product_id, array $rules ) {
			$rules   = array_merge( self::default_rules(), $rules );
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
			if ( ! $product || ! $product->is_type( 'variable' ) ) {
				return null;
			}

			$attrs = $this->collect_attributes( $product, $rules );
			if ( empty( $attrs ) ) {
				return null;
			}

			$variations = $this->collect_variations( $product );
			$baseline   = $this->baseline_price( $variations );

			$fields = array();
			$index  = 0;
			$any_image_field = false;
			foreach ( $attrs as $attr ) {
				$options = $this->build_options_for_attr( $attr, $variations, $baseline, $rules, count( $attrs ) > 1 );
				if ( empty( $options ) ) {
					continue;
				}
				$has_image = false;
				foreach ( $options as $opt ) {
					if ( ! empty( $opt['image'] ) && '0' !== $opt['image'] ) {
						$has_image = true;
						break;
					}
				}
				if ( $has_image ) {
					$any_image_field = true;
				}
				$field_id = 'fw' . $product_id . '_' . $index;
				$fields[] = $this->build_field_descriptor(
					array(
						'id'                   => $field_id,
						'title'                => $attr['label'],
						'options'              => $options,
						'display_type'         => $rules['display_type'],
						'change_image_product' => $has_image ? 'y' : 'n',
					)
				);
				$index++;
			}

			if ( empty( $fields ) ) {
				return null;
			}

			$payload = $this->build_option_set_envelope( $fields );

			return array(
				'product_id'     => $product_id,
				'title'          => $this->seed_title( $product ),
				'fields_payload' => $payload,
				'meta'           => array(
					'attr_count'    => count( $fields ),
					'option_count'  => array_sum( array_map( function ( $f ) {
						return isset( $f['general']['attributes']['options'] )
							? count( $f['general']['attributes']['options'] )
							: 0;
					}, $fields ) ),
					'has_image'     => $any_image_field,
					'is_multi_attr' => count( $fields ) > 1,
					'baseline'      => $baseline,
				),
			);
		}

		// ────────────────────────────────────────────────────────────────────
		//  Attribute & variation collection
		// ────────────────────────────────────────────────────────────────────

		/**
		 * Return the variation-driving attributes (and, if requested, the
		 * visible non-variation ones with 0 price).
		 *
		 * @param WC_Product $product
		 * @param array      $rules
		 * @return array[]   each: ['name','label','is_taxonomy','values'=>[ ['key','label'], ... ], 'is_priced']
		 */
		protected function collect_attributes( $product, array $rules ) {
			$out = array();
			$attrs = $product->get_attributes();
			if ( ! is_array( $attrs ) ) {
				return $out;
			}
			foreach ( $attrs as $attr ) {
				if ( ! is_object( $attr ) ) {
					continue;
				}
				$is_variation = method_exists( $attr, 'get_variation' ) ? $attr->get_variation() : false;
				$is_visible   = method_exists( $attr, 'get_visible' ) ? $attr->get_visible() : false;
				if ( ! $is_variation ) {
					if ( ! $rules['include_non_variation'] || ! $is_visible ) {
						continue;
					}
				}
				$name        = $attr->get_name();
				$is_taxonomy = $attr->is_taxonomy();
				$label       = $is_taxonomy && function_exists( 'wc_attribute_label' )
					? wc_attribute_label( $name )
					: $name;
				$values      = $this->attribute_values( $attr );
				if ( empty( $values ) ) {
					continue;
				}
				$out[] = array(
					'name'        => $name,
					'label'       => $label,
					'is_taxonomy' => $is_taxonomy,
					'values'      => $values,
					'is_priced'   => (bool) $is_variation,
				);
			}
			return $out;
		}

		/**
		 * Resolve an attribute's options to a list of [key, label] pairs.
		 * For taxonomies, key = term slug, label = term name. For custom,
		 * key = label = the raw string.
		 *
		 * @param WC_Product_Attribute $attr
		 * @return array[]
		 */
		protected function attribute_values( $attr ) {
			$out = array();
			$options = $attr->get_options();
			if ( ! is_array( $options ) || empty( $options ) ) {
				return $out;
			}
			if ( $attr->is_taxonomy() ) {
				foreach ( $options as $term_id ) {
					$term = get_term( (int) $term_id );
					if ( $term && ! is_wp_error( $term ) ) {
						$out[] = array(
							'key'   => $term->slug,
							'label' => $term->name,
						);
					}
				}
			} else {
				foreach ( $options as $val ) {
					$val = (string) $val;
					if ( '' === $val ) {
						continue;
					}
					$out[] = array(
						'key'   => sanitize_title( $val ),
						'label' => $val,
					);
				}
			}
			return $out;
		}

		/**
		 * Collect each child variation as a compact array:
		 *   ['id','price','image_id','attrs'=>['pa_color'=>'red','size'=>'Large']]
		 *
		 * @param WC_Product_Variable $product
		 * @return array[]
		 */
		protected function collect_variations( $product ) {
			$out = array();
			$child_ids = $product->get_children();
			foreach ( $child_ids as $vid ) {
				$v = wc_get_product( $vid );
				if ( ! $v ) {
					continue;
				}
				$price = $v->get_price();
				if ( '' === $price || null === $price ) {
					continue;
				}
				$va = $v->get_attributes();
				$attrs_norm = array();
				if ( is_array( $va ) ) {
					foreach ( $va as $k => $val ) {
						$attrs_norm[ $this->normalize_attr_key( $k ) ] = (string) $val;
					}
				}
				$out[] = array(
					'id'       => (int) $vid,
					'price'    => (float) $price,
					'image_id' => (int) $v->get_image_id(),
					'attrs'    => $attrs_norm,
				);
			}
			return $out;
		}

		protected function baseline_price( array $variations ) {
			$min = null;
			foreach ( $variations as $v ) {
				if ( null === $min || $v['price'] < $min ) {
					$min = $v['price'];
				}
			}
			return null === $min ? 0.0 : (float) $min;
		}

		/**
		 * Build the option entries (the dropdown/swatch choices) for one
		 * attribute. Computes the per-option delta against the baseline.
		 *
		 * @param array   $attr
		 * @param array[] $variations
		 * @param float   $baseline
		 * @param array   $rules
		 * @param bool    $is_multi  Whether this product has >1 variation attribute.
		 * @return array[]  option descriptor entries (descriptor shape)
		 */
		protected function build_options_for_attr( array $attr, array $variations, $baseline, array $rules, $is_multi ) {
			$key      = $this->normalize_attr_key( $attr['name'] );
			$priced   = (bool) $attr['is_priced'];
			$out      = array();
			$has_default = false;
			foreach ( $attr['values'] as $i => $value ) {
				$value_key = $this->normalize_value_for_match( $value['key'] );

				$delta     = '';
				$image_id  = 0;
				if ( $priced ) {
					$matches = $this->variations_for_value( $variations, $key, $value_key );
					$image_id = $this->first_image_id( $matches );
					if ( ! empty( $matches ) ) {
						if ( $is_multi && 'empty' === $rules['multi_attr_price'] ) {
							$delta = '';
						} else {
							$price = $is_multi
								? $this->avg_price( $matches )
								: $this->first_concrete_price( $matches, $key );
							if ( null !== $price ) {
								$d = round( $price - $baseline, 2 );
								// Negative deltas can happen if baseline includes a
								// cross-cutting wildcard cheaper than all concrete
								// variations of this level — clamp to 0 so we don't
								// surface confusing negative surcharges.
								$delta = $d < 0 ? '0' : (string) $d;
								// Trim trailing zeros only when no fractional part.
								if ( '0' === $delta ) {
									$delta = '';
								}
							}
						}
					}
				}

				$preview_type = ( $rules['import_images'] && $image_id )
					? 'i'
					: ( 's' === $rules['display_type'] ? 'c' : 'l' );

				$entry = array(
					'preview_type'       => $preview_type,
					'image'              => $rules['import_images'] && $image_id ? (string) $image_id : '0',
					'color'              => '#ffffff',
					'name'               => $value['label'],
					'des'                => '',
					'price'              => array( (string) $delta ),
					'implicit_value'     => '',
					'enable_subattr'     => 0,
					'sub_attributes'     => array(),
					'sattr_display_type' => 's',
					'enable_con'         => 0,
					'con_show'           => 'n',
					'con_logic'          => 'a',
					'depend'             => array(
						array(
							'id'       => '',
							'operator' => 'i',
							'val'      => '',
							'subval'   => '',
						),
					),
					'image_url'          => '',
					'isExpand'           => false,
				);
				if ( ! $has_default ) {
					$entry['selected'] = 'on';
					$has_default = true;
				}
				$out[] = $entry;
				unset( $i );
			}
			return $out;
		}

		/**
		 * Variations whose value for $key matches $value (ignoring wildcards).
		 *
		 * @return array[]
		 */
		protected function variations_for_value( array $variations, $key, $value ) {
			$matches = array();
			foreach ( $variations as $v ) {
				if ( ! isset( $v['attrs'][ $key ] ) ) {
					continue;
				}
				$cell = $this->normalize_value_for_match( $v['attrs'][ $key ] );
				if ( '' === $cell ) {
					continue; // wildcard "Any"
				}
				if ( $cell === $value ) {
					$matches[] = $v;
				}
			}
			return $matches;
		}

		protected function first_concrete_price( array $matches, $key ) {
			foreach ( $matches as $m ) {
				if ( isset( $m['price'] ) ) {
					return (float) $m['price'];
				}
			}
			unset( $key );
			return null;
		}

		protected function avg_price( array $matches ) {
			if ( empty( $matches ) ) {
				return null;
			}
			$sum = 0.0;
			$n   = 0;
			foreach ( $matches as $m ) {
				$sum += (float) $m['price'];
				$n++;
			}
			return $n > 0 ? $sum / $n : null;
		}

		protected function first_image_id( array $matches ) {
			foreach ( $matches as $m ) {
				if ( ! empty( $m['image_id'] ) ) {
					return (int) $m['image_id'];
				}
			}
			return 0;
		}

		/**
		 * Variation meta keys come as 'attribute_pa_color' from the variation
		 * post itself, but as 'pa_color' from get_attributes() on the
		 * variation (which is what we use). Custom attributes come as the
		 * raw key. Normalize to lowercase for consistent lookup.
		 */
		protected function normalize_attr_key( $key ) {
			return strtolower( str_replace( 'attribute_', '', (string) $key ) );
		}

		protected function normalize_value_for_match( $value ) {
			return strtolower( trim( (string) $value ) );
		}

		protected function seed_title( $product ) {
			/* translators: %s: product name */
			return sprintf( __( '%s (Woo seed)', 'storelly-product-builder-for-woocommerce' ), $product->get_name() );
		}

		// ────────────────────────────────────────────────────────────────────
		//  Descriptor builders — full admin schema shape
		// ────────────────────────────────────────────────────────────────────

		/**
		 * Top-level option set envelope. Quantity disabled (single tier) so
		 * each option's price[] is a one-element array.
		 *
		 * @param array[] $fields
		 * @return array
		 */
		protected function build_option_set_envelope( array $fields ) {
			return array(
				'version'                => '120',
				'quantity_enable'        => 'n',
				'quantity_type'          => 'd',
				'quantity_min'           => '1',
				'quantity_max'           => '10000',
				'quantity_step'          => '1',
				'quantity_discount_type' => 'f',
				'quantity_breaks'        => array(
					array(
						'val'     => '1',
						'dis'     => '',
						'default' => 'on',
					),
				),
				'fields'                 => $fields,
			);
		}

		/**
		 * Build the descriptor block for one field. Constant parts mirror
		 * the bundled print templates exactly so the admin builder picks
		 * the shape up without needing the safety-net flatten.
		 *
		 * @param array $spec  ['id','title','options','display_type','change_image_product']
		 * @return array
		 */
		protected function build_field_descriptor( array $spec ) {
			return array(
				'id'          => (string) $spec['id'],
				'general'     => array(
					'title'           => $this->leaf_text( __( 'Option name', 'storelly-product-builder-for-woocommerce' ), $spec['title'] ),
					'description'     => $this->leaf_textarea( '' ),
					'data_type'       => $this->leaf_dropdown(
						__( 'Data type', 'storelly-product-builder-for-woocommerce' ),
						'm',
						array(
							array( 'key' => 'i', 'text' => __( 'Custom input', 'storelly-product-builder-for-woocommerce' ) ),
							array( 'key' => 'm', 'text' => __( 'Multiple options', 'storelly-product-builder-for-woocommerce' ) ),
						)
					),
					'input_type'      => $this->leaf_input_type(),
					'input_option'    => $this->leaf_input_option(),
					'text_option'     => $this->leaf_text_option(),
					'placeholder'     => $this->leaf_placeholder(),
					'upload_option'   => $this->leaf_upload_option(),
					'enabled'         => $this->leaf_yes_no( __( 'Enabled', 'storelly-product-builder-for-woocommerce' ), 'y' ),
					'published'       => $this->leaf_yes_no( __( 'Published', 'storelly-product-builder-for-woocommerce' ), 'y' ),
					'required'        => $this->leaf_required(),
					'price_type'      => $this->leaf_price_type(),
					'depend_qty'      => $this->leaf_depend_qty(),
					'depend_quantity' => $this->leaf_depend_quantity(),
					'price'           => $this->leaf_additional_price(),
					'price_breaks'    => $this->leaf_price_breaks(),
					'attributes'      => array(
						'title'           => __( 'Attributes', 'storelly-product-builder-for-woocommerce' ),
						'description'     => '',
						'type'            => 'attributes',
						'same_size'       => 'y',
						'bg_type'         => 'i',
						'show_as_pt'      => 'n',
						'number_of_sides' => 2,
						'depend'          => array(
							array( 'field' => 'data_type', 'operator' => '=', 'value' => 'm' ),
						),
						'options'         => $spec['options'],
					),
				),
				'conditional' => array(
					'enable' => 'n',
					'show'   => 'n',
					'logic'  => 'a',
					'depend' => array(
						array( 'id' => '', 'operator' => 'i', 'val' => '' ),
					),
				),
				'appearance'  => array(
					'display_type'         => $this->leaf_display_type( $spec['display_type'] ),
					'change_image_product' => $this->leaf_yes_no( __( 'Changes product image', 'storelly-product-builder-for-woocommerce' ), $spec['change_image_product'] ),
					'show_in_archives'     => $this->leaf_yes_no( __( 'Show in archive pages', 'storelly-product-builder-for-woocommerce' ), 'n' ),
					'css_class'            => $this->leaf_text( __( 'CSS Class', 'storelly-product-builder-for-woocommerce' ), '' ),
				),
				'isExpand'    => false,
			);
		}

		// ── Leaf builders (descriptor objects with title/desc/value/type) ──

		protected function leaf_text( $title, $value ) {
			return array(
				'title'       => $title,
				'description' => '',
				'value'       => (string) $value,
				'type'        => 'text',
			);
		}

		protected function leaf_textarea( $value ) {
			return array(
				'title'       => __( 'Description', 'storelly-product-builder-for-woocommerce' ),
				'description' => '',
				'value'       => (string) $value,
				'type'        => 'textarea',
			);
		}

		protected function leaf_dropdown( $title, $value, $options ) {
			return array(
				'title'       => $title,
				'description' => '',
				'value'       => $value,
				'type'        => 'dropdown',
				'options'     => $options,
			);
		}

		protected function leaf_yes_no( $title, $value ) {
			return array(
				'title'       => $title,
				'description' => '',
				'value'       => $value,
				'type'        => 'dropdown',
				'options'     => array(
					array( 'key' => 'y', 'text' => __( 'Yes', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 'n', 'text' => __( 'No', 'storelly-product-builder-for-woocommerce' ) ),
				),
			);
		}

		protected function leaf_required() {
			$leaf = $this->leaf_yes_no( __( 'Required', 'storelly-product-builder-for-woocommerce' ), 'n' );
			$leaf['depend'] = array(
				array( 'field' => 'published', 'operator' => '#', 'value' => 'n' ),
			);
			return $leaf;
		}

		protected function leaf_input_type() {
			return array(
				'title'       => __( 'Input type', 'storelly-product-builder-for-woocommerce' ),
				'description' => '',
				'value'       => 't',
				'type'        => 'dropdown',
				'depend'      => array(
					array( 'field' => 'data_type', 'operator' => '=', 'value' => 'i' ),
				),
				'options'     => array(
					array( 'key' => 't', 'text' => __( 'Text', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 'n', 'text' => __( 'Number', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 'r', 'text' => __( 'Number range', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 'u', 'text' => __( 'Upload', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 'a', 'text' => __( 'Textarea', 'storelly-product-builder-for-woocommerce' ) ),
				),
			);
		}

		protected function leaf_input_option() {
			return array(
				'title'       => __( 'Input option', 'storelly-product-builder-for-woocommerce' ),
				'description' => '',
				'value'       => array(
					'min'     => '1',
					'max'     => '100',
					'step'    => '1',
					'default' => '1',
				),
				'type'        => 'table',
				'depend'      => array(
					array( 'field' => 'data_type', 'operator' => '=', 'value' => 'i' ),
					array( 'field' => 'input_type', 'operator' => '#', 'value' => 't' ),
					array( 'field' => 'input_type', 'operator' => '#', 'value' => 'u' ),
					array( 'field' => 'input_type', 'operator' => '#', 'value' => 'a' ),
				),
			);
		}

		protected function leaf_text_option() {
			return array(
				'title'       => __( 'Text input option', 'storelly-product-builder-for-woocommerce' ),
				'description' => '',
				'value'       => array( 'min' => '0', 'max' => '999' ),
				'type'        => 'table',
				'depend'      => array(
					array( 'field' => 'data_type', 'operator' => '=', 'value' => 'i' ),
					array( 'field' => 'input_type', 'operator' => '=', 'value' => 't,a' ),
				),
			);
		}

		protected function leaf_placeholder() {
			return array(
				'title'       => __( 'Placeholder', 'storelly-product-builder-for-woocommerce' ),
				'description' => '',
				'value'       => '',
				'type'        => 'text',
				'depend'      => array(
					array( 'field' => 'data_type', 'operator' => '=', 'value' => 'i' ),
					array( 'field' => 'input_type', 'operator' => '=', 'value' => 't,a' ),
				),
			);
		}

		protected function leaf_upload_option() {
			return array(
				'title'       => __( 'Upload file option', 'storelly-product-builder-for-woocommerce' ),
				'description' => '',
				'value'       => array(
					'min_size'   => '0',
					'max_size'   => '256',
					'allow_type' => 'png,jpg,jpeg',
				),
				'type'        => 'table',
				'depend'      => array(
					array( 'field' => 'data_type', 'operator' => '=', 'value' => 'i' ),
					array( 'field' => 'input_type', 'operator' => '=', 'value' => 'u' ),
				),
			);
		}

		protected function leaf_price_type() {
			return array(
				'title'       => __( 'Price type', 'storelly-product-builder-for-woocommerce' ),
				'description' => '',
				'value'       => 'f',
				'type'        => 'dropdown',
				'options'     => array(
					array( 'key' => 'f', 'text' => __( 'Fixed amount', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 'p', 'text' => __( 'Percent of the original price', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 'p+', 'text' => __( 'Percent of the original price + options', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 'mf', 'text' => __( 'Math formula', 'storelly-product-builder-for-woocommerce' ) ),
				),
			);
		}

		protected function leaf_depend_qty() {
			return array(
				'title'       => __( 'Depend quantity', 'storelly-product-builder-for-woocommerce' ),
				'description' => '',
				'value'       => 'y',
				'type'        => 'dropdown',
				'options'     => array(
					array( 'key' => 'y',  'text' => __( 'Yes', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 'n',  'text' => __( 'No, the additional price is cart item fee', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 'n2', 'text' => __( 'No, the additional price is fixed amount for all items', 'storelly-product-builder-for-woocommerce' ) ),
				),
			);
		}

		protected function leaf_depend_quantity() {
			return array(
				'title'       => __( 'Depend quantity breaks', 'storelly-product-builder-for-woocommerce' ),
				'description' => '',
				'value'       => 'n',
				'type'        => 'dropdown',
				'options'     => array(
					array( 'key' => 'y', 'text' => __( 'Yes', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 'n', 'text' => __( 'No', 'storelly-product-builder-for-woocommerce' ) ),
				),
			);
		}

		protected function leaf_additional_price() {
			return array(
				'title'       => __( 'Additional Price', 'storelly-product-builder-for-woocommerce' ),
				'description' => '',
				'value'       => '',
				'type'        => 'number',
				'depend'      => array(
					array( 'field' => 'depend_quantity', 'operator' => '#', 'value' => 'y' ),
					array( 'field' => 'data_type',       'operator' => '=', 'value' => 'i' ),
				),
			);
		}

		protected function leaf_price_breaks() {
			return array(
				'title'       => __( 'Price depend quantity breaks', 'storelly-product-builder-for-woocommerce' ),
				'description' => '',
				'value'       => array( '' ),
				'type'        => 'single_quantity_depend',
				'depend'      => array(
					array( 'field' => 'depend_quantity', 'operator' => '=', 'value' => 'y' ),
					array( 'field' => 'data_type',       'operator' => '=', 'value' => 'i' ),
				),
			);
		}

		protected function leaf_display_type( $value ) {
			$value = in_array( $value, array( 'd', 'r', 's', 'l', 'ad', 'xl' ), true ) ? $value : 's';
			return array(
				'title'       => __( 'Display type', 'storelly-product-builder-for-woocommerce' ),
				'description' => '',
				'value'       => $value,
				'type'        => 'dropdown',
				'options'     => array(
					array( 'key' => 'd',  'text' => __( 'Dropdown', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 'r',  'text' => __( 'Radio button', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 's',  'text' => __( 'Swatch', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 'l',  'text' => __( 'Label', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 'ad', 'text' => __( 'Advanced Dropdown', 'storelly-product-builder-for-woocommerce' ) ),
					array( 'key' => 'xl', 'text' => __( 'Large label', 'storelly-product-builder-for-woocommerce' ) ),
				),
			);
		}
	}
}
