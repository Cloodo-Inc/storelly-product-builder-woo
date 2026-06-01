<?php
/**
 * AJAX endpoints for the Template Library.
 *
 * Two actions:
 *   - spbwc_template_preview : returns light metadata + field summary for modal
 *   - spbwc_template_apply   : forks a template into a new option row
 *
 * Both require `spbwc_manage_product_builder` cap + nonce 'spbwc_template_library'.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Template_Ajax' ) ) {

	class SPBWC_Template_Ajax {

		protected static $instance = null;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function init() {
			add_action( 'wp_ajax_spbwc_template_preview', array( $this, 'ajax_preview' ) );
			add_action( 'wp_ajax_spbwc_template_apply', array( $this, 'ajax_apply' ) );
		}

		public function ajax_preview() {
			$this->verify_request();

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() called above.
			$slug    = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
			$catalog = SPBWC_Template_Catalog::instance();
			$meta    = $catalog->get_template_meta( $slug );
			if ( ! is_array( $meta ) ) {
				wp_send_json_error( array( 'message' => __( 'Template not found.', 'storelly-product-builder-for-woocommerce' ) ), 404 );
			}
			$data = $catalog->get_template_data( $slug );
			if ( ! is_array( $data ) ) {
				wp_send_json_error( array( 'message' => __( 'Template body unreadable.', 'storelly-product-builder-for-woocommerce' ) ), 500 );
			}

			$render = $this->build_render_data( $data );

			wp_send_json_success(
				array(
					'meta'   => array(
						'slug'             => $meta['slug'],
						'name'             => $catalog->get_display_name( $meta ),
						'category'         => $catalog->get_category_label( $meta['category'] ),
						'field_count'      => $meta['field_count'],
						'pricing_method'   => $meta['pricing_method'],
						'pricing_source'   => $meta['pricing_source'],
						'description'      => $meta['description'],
						'template_version' => $meta['template_version'],
					),
					'render' => $render,
				)
			);
		}

		public function ajax_apply() {
			$this->verify_request();

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() called above.
			$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() called above.
			$apply_for = isset( $_POST['apply_for'] ) ? sanitize_text_field( wp_unslash( $_POST['apply_for'] ) ) : 'p';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() called above.
			$custom_title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above; array values sanitized with absint on next line.
			$scope_raw = isset( $_POST['scope_ids'] ) ? (array) wp_unslash( $_POST['scope_ids'] ) : array();
			$scope_ids = array_map( 'absint', $scope_raw );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() called above.
			$force = isset( $_POST['force'] ) && '1' === (string) wp_unslash( $_POST['force'] );

			$result = SPBWC_Template_Applier::instance()->apply( $slug, $apply_for, $scope_ids, $custom_title, $force );

			// Conflict — HTTP 200 with success:false + conflict:true so the JS
			// client can render a confirm dialog without treating it as an error.
			// See docs/SPEC_PRICING_OPTION_ASSIGNMENT.md §3.4.
			if ( ! empty( $result['conflict'] ) ) {
				wp_send_json_success( $result );
			}
			if ( empty( $result['success'] ) ) {
				wp_send_json_error( array( 'message' => $result['message'] ?? '' ), 400 );
			}
			wp_send_json_success( $result );
		}

		protected function verify_request() {
			if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
				wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
			}
			$nonce = isset( $_POST['_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'spbwc_template_library' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
			}
		}

		/**
		 * Build render-ready field data for the classic preview mockup —
		 * shrunk-down version of the JSON optimized for client rendering
		 * (drops conditional rules, raw price arrays, sub_attributes, etc.).
		 *
		 * Output per field:
		 *   title, description, required, display, data_type, input_type,
		 *   nbd_type, price_type, attributes[] (name, color, preview_type,
		 *   price_label, selected, popular)
		 *
		 * @param array $data
		 * @return array
		 */
		protected function build_render_data( $data ) {
			$out = array(
				'title'           => (string) ( $data['title'] ?? '' ),
				'display_type'    => (string) ( $data['display_type'] ?? '4' ),
				'quantity_enable' => (string) ( $data['quantity_enable'] ?? 'n' ),
				'quantity_breaks' => array(),
				'fields'          => array(),
			);

			if ( ! empty( $data['quantity_breaks'] ) && is_array( $data['quantity_breaks'] ) ) {
				foreach ( $data['quantity_breaks'] as $qb ) {
					if ( ! is_array( $qb ) ) {
						continue;
					}
					$out['quantity_breaks'][] = array(
						'val' => (string) ( $qb['val'] ?? '' ),
						'dis' => (string) ( $qb['dis'] ?? '' ),
					);
				}
			}

			if ( empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
				return $out;
			}

			foreach ( $data['fields'] as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$g = isset( $field['general'] ) && is_array( $field['general'] ) ? $field['general'] : array();
				$a = isset( $field['appearance'] ) && is_array( $field['appearance'] ) ? $field['appearance'] : array();

				$title       = (string) ( $g['title']['value'] ?? '' );
				$description = (string) ( $g['description']['value'] ?? '' );
				$required    = 'y' === (string) ( $g['required']['value'] ?? 'n' );
				$display     = (string) ( $a['display_type']['value'] ?? 'd' );
				$data_type   = (string) ( $g['data_type']['value'] ?? 'm' );
				$input_type  = (string) ( $g['input_type']['value'] ?? 't' );
				$price_type  = (string) ( $g['price_type']['value'] ?? 'f' );

				$attrs = array();
				if ( ! empty( $g['attributes']['options'] ) && is_array( $g['attributes']['options'] ) ) {
					foreach ( $g['attributes']['options'] as $opt ) {
						if ( ! is_array( $opt ) ) {
							continue;
						}
						$attrs[] = array(
							'name'         => (string) ( $opt['name'] ?? '' ),
							'des'          => (string) ( $opt['des'] ?? '' ),
							'preview_type' => (string) ( $opt['preview_type'] ?? 'l' ),
							'color'        => (string) ( $opt['color'] ?? '' ),
							'price'        => $this->first_nonempty_price( $opt['price'] ?? array() ),
							'selected'     => isset( $opt['selected'] ) && 'on' === $opt['selected'],
						);
					}
				}

				$out['fields'][] = array(
					'title'       => $title,
					'description' => $description,
					'required'    => $required,
					'display'     => $display,
					'data_type'   => $data_type,
					'input_type'  => $input_type,
					'nbd_type'    => (string) ( $field['nbd_type'] ?? '' ),
					'price_type'  => $price_type,
					'attributes'  => $attrs,
				);
			}

			return $out;
		}

		protected function first_nonempty_price( $prices ) {
			if ( ! is_array( $prices ) ) {
				return '';
			}
			foreach ( $prices as $p ) {
				if ( '' !== $p && null !== $p && '0' !== $p ) {
					return (string) $p;
				}
			}
			return '';
		}
	}
}
