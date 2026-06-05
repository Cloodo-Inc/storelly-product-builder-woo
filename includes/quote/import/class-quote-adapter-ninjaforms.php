<?php
/**
 * Import adapter: Ninja Forms → quotes (Quote Import M3b).
 *
 * Forms + fields are read through the Ninja_Forms() API. Ninja Forms 3 stores
 * each submission as the `nf_sub` CPT, with the form id in `_form_id` and each
 * field value in `_field_<fieldId>` post meta.
 *
 * NOTE: written against the documented Ninja Forms 3 API + nf_sub schema; verify
 * on a live install running a supported WordPress version.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Adapter_Ninjaforms' ) ) {

    class SPBWC_Quote_Adapter_Ninjaforms extends SPBWC_Quote_Form_Adapter {

        const CPT = 'nf_sub';

        public function id() {
            return 'ninjaforms';
        }

        public function label() {
            return __( 'Ninja Forms', 'storelly-product-builder-for-woocommerce' );
        }

        public function description() {
            return __( 'Submissions from your Ninja Forms forms.', 'storelly-product-builder-for-woocommerce' );
        }

        public function is_available() {
            return function_exists( 'Ninja_Forms' ) && post_type_exists( self::CPT );
        }

        public function forms() {
            if ( ! function_exists( 'Ninja_Forms' ) ) {
                return array();
            }
            $forms = array();
            foreach ( (array) Ninja_Forms()->form()->get_forms() as $form ) {
                if ( ! is_object( $form ) || ! method_exists( $form, 'get_id' ) ) {
                    continue;
                }
                $fid    = (int) $form->get_id();
                $fields = array();
                foreach ( (array) Ninja_Forms()->form( $fid )->get_fields() as $field ) {
                    if ( ! is_object( $field ) || ! method_exists( $field, 'get_id' ) ) {
                        continue;
                    }
                    $fields[] = array(
                        'key'   => (string) $field->get_id(),
                        'label' => (string) $field->get_setting( 'label' ),
                        'type'  => (string) $field->get_setting( 'type' ),
                    );
                }
                $forms[] = array(
                    'id'     => $fid,
                    'title'  => (string) $form->get_setting( 'title' ),
                    'fields' => $fields,
                );
            }
            return $forms;
        }

        protected function fetch_entries( $form_id, $limit ) {
            return get_posts(
                array(
                    'post_type'      => self::CPT,
                    'post_status'    => 'publish',
                    'posts_per_page' => $limit > 0 ? $limit : -1,
                    'orderby'        => 'date',
                    'order'          => 'ASC',
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-triggered import scan.
                    'meta_query'     => array(
                        array(
                            'key'   => '_form_id',
                            'value' => (int) $form_id,
                        ),
                    ),
                )
            );
        }

        protected function entry_id( $raw ) {
            return (int) $raw->ID;
        }

        protected function entry_value( $raw, $field_key ) {
            $value = get_post_meta( $raw->ID, '_field_' . $field_key, true );
            return $this->flatten_value( $value );
        }

        protected function entry_date( $raw ) {
            return $raw->post_date ? $raw->post_date : '';
        }
    }
}
