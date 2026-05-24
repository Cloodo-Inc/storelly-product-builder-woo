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
			add_action( 'wp_ajax_spbwc_template_apply',   array( $this, 'ajax_apply' ) );
		}

		public function ajax_preview() {
			$this->verify_request();

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

			$summary = $this->summarize_fields( $data );

			wp_send_json_success( array(
				'meta'    => array(
					'slug'             => $meta['slug'],
					'name'             => $catalog->get_display_name( $meta ),
					'category'         => $catalog->get_category_label( $meta['category'] ),
					'field_count'      => $meta['field_count'],
					'pricing_method'   => $meta['pricing_method'],
					'pricing_source'   => $meta['pricing_source'],
					'description'      => $meta['description'],
					'template_version' => $meta['template_version'],
				),
				'summary' => $summary,
			) );
		}

		public function ajax_apply() {
			$this->verify_request();

			$slug         = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
			$apply_for    = isset( $_POST['apply_for'] ) ? sanitize_text_field( wp_unslash( $_POST['apply_for'] ) ) : 'p';
			$custom_title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
			$scope_raw    = isset( $_POST['scope_ids'] ) ? (array) wp_unslash( $_POST['scope_ids'] ) : array();
			$scope_ids    = array_map( 'absint', $scope_raw );

			$result = SPBWC_Template_Applier::instance()->apply( $slug, $apply_for, $scope_ids, $custom_title );

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
		 * Build a compact field summary for the preview modal — title, display
		 * type, attribute count. Avoids dumping the full ~80KB JSON to the wire.
		 *
		 * @param array $data
		 * @return array
		 */
		protected function summarize_fields( $data ) {
			$out = array();
			if ( empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
				return $out;
			}
			foreach ( $data['fields'] as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$title    = isset( $field['general']['title']['value'] ) ? (string) $field['general']['title']['value'] : '';
				$dtype    = isset( $field['appearance']['display_type']['value'] ) ? (string) $field['appearance']['display_type']['value'] : '';
				$nbd_type = isset( $field['nbd_type'] ) ? (string) $field['nbd_type'] : '';
				$attrs    = isset( $field['general']['attributes']['options'] ) && is_array( $field['general']['attributes']['options'] )
					? count( $field['general']['attributes']['options'] )
					: 0;
				$out[] = array(
					'title'        => $title,
					'nbd_type'     => $nbd_type,
					'display_type' => $dtype,
					'attr_count'   => $attrs,
				);
			}
			return $out;
		}
	}
}
