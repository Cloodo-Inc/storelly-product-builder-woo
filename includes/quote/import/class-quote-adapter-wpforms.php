<?php
/**
 * Import adapter: WPForms → quotes (Quote Import M3b).
 *
 * Forms live in the `wpforms` CPT (post_content is JSON: fields keyed by id).
 * Entries are stored by WPForms (Pro only — Lite does not persist) and read
 * through the official entry handler `wpforms()->entry->get_entries()`; each
 * entry's `fields` column is JSON keyed by field id ({ id => { value, … } }).
 *
 * NOTE: requires WPForms with entry storage (Pro). Verify on a supported
 * WordPress version — written against the documented WPForms API.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Adapter_Wpforms' ) ) {

    class SPBWC_Quote_Adapter_Wpforms extends SPBWC_Quote_Form_Adapter {

        public function id() {
            return 'wpforms';
        }

        public function label() {
            return __( 'WPForms', 'storelly-product-builder-for-woocommerce' );
        }

        public function description() {
            return __( 'Entries from your WPForms forms (requires WPForms entry storage).', 'storelly-product-builder-for-woocommerce' );
        }

        public function is_available() {
            return function_exists( 'wpforms' ) && is_object( wpforms() ) && ! empty( wpforms()->entry ) && method_exists( wpforms()->entry, 'get_entries' );
        }

        public function forms() {
            $posts = get_posts(
                array(
                    'post_type'      => 'wpforms',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                )
            );
            $forms = array();
            foreach ( $posts as $post ) {
                $data   = json_decode( $post->post_content, true );
                $fields = array();
                if ( is_array( $data ) && ! empty( $data['fields'] ) ) {
                    foreach ( $data['fields'] as $fid => $field ) {
                        $fields[] = array(
                            'key'   => (string) $fid,
                            'label' => isset( $field['label'] ) && '' !== $field['label'] ? $field['label'] : ( isset( $field['type'] ) ? $field['type'] : (string) $fid ),
                            'type'  => isset( $field['type'] ) ? $field['type'] : '',
                        );
                    }
                }
                $forms[] = array(
                    'id'     => (int) $post->ID,
                    'title'  => $post->post_title,
                    'fields' => $fields,
                );
            }
            return $forms;
        }

        protected function fetch_entries( $form_id, $limit ) {
            if ( ! $this->is_available() ) {
                return array();
            }
            return (array) wpforms()->entry->get_entries(
                array(
                    'form_id' => (int) $form_id,
                    'number'  => $limit > 0 ? (int) $limit : -1,
                    'order'   => 'ASC',
                )
            );
        }

        protected function entry_id( $raw ) {
            return (int) $raw->entry_id;
        }

        protected function entry_value( $raw, $field_key ) {
            $fields = json_decode( isset( $raw->fields ) ? $raw->fields : '', true );
            if ( ! is_array( $fields ) || ! isset( $fields[ $field_key ] ) ) {
                return '';
            }
            $field = $fields[ $field_key ];
            return isset( $field['value'] ) ? $this->flatten_value( $field['value'] ) : '';
        }

        protected function entry_date( $raw ) {
            return isset( $raw->date ) ? (string) $raw->date : '';
        }
    }
}
