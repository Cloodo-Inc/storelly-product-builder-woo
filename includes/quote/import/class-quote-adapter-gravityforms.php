<?php
/**
 * Import adapter: Gravity Forms → quotes (Quote Import M3b).
 *
 * Read through the official GFAPI: GFAPI::get_forms() for the form + field list
 * (each field has id / label / type), GFAPI::get_entries() for submissions (an
 * entry is an array keyed by field id). Name fields store their parts under
 * sub-input ids "<id>.3" (first) and "<id>.6" (last), which entry_value()
 * recombines.
 *
 * NOTE: Gravity Forms is a commercial plugin; written against the public GFAPI.
 * Verify on a live install running a supported WordPress version.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Adapter_Gravityforms' ) ) {

    class SPBWC_Quote_Adapter_Gravityforms extends SPBWC_Quote_Form_Adapter {

        public function id() {
            return 'gravityforms';
        }

        public function label() {
            return __( 'Gravity Forms', 'storelly-product-builder-for-woocommerce' );
        }

        public function description() {
            return __( 'Entries from your Gravity Forms forms.', 'storelly-product-builder-for-woocommerce' );
        }

        public function is_available() {
            return class_exists( 'GFAPI' );
        }

        public function forms() {
            if ( ! class_exists( 'GFAPI' ) ) {
                return array();
            }
            $skip  = array( 'html', 'section', 'page', 'captcha', 'honeypot' );
            $forms = array();
            foreach ( (array) GFAPI::get_forms() as $form ) {
                $fields = array();
                foreach ( (array) ( isset( $form['fields'] ) ? $form['fields'] : array() ) as $field ) {
                    $type = isset( $field->type ) ? $field->type : '';
                    if ( in_array( $type, $skip, true ) ) {
                        continue;
                    }
                    $fields[] = array(
                        'key'   => (string) $field->id,
                        'label' => isset( $field->label ) && '' !== $field->label ? $field->label : (string) $field->id,
                        'type'  => $type,
                    );
                }
                $forms[] = array(
                    'id'     => (int) $form['id'],
                    'title'  => isset( $form['title'] ) ? $form['title'] : ( '#' . $form['id'] ),
                    'fields' => $fields,
                );
            }
            return $forms;
        }

        protected function fetch_entries( $form_id, $limit ) {
            if ( ! class_exists( 'GFAPI' ) ) {
                return array();
            }
            $paging  = array( 'offset' => 0, 'page_size' => $limit > 0 ? (int) $limit : 200 );
            $entries = GFAPI::get_entries( (int) $form_id, array(), null, $paging );
            return is_wp_error( $entries ) ? array() : (array) $entries;
        }

        protected function entry_id( $raw ) {
            return (int) $raw['id'];
        }

        protected function entry_value( $raw, $field_key ) {
            if ( isset( $raw[ $field_key ] ) && '' !== $raw[ $field_key ] ) {
                return (string) $raw[ $field_key ];
            }
            // Name (and similar composite) fields store parts under "<id>.n".
            $parts = array();
            foreach ( array( '.3', '.6', '.2', '.4', '.8' ) as $suffix ) {
                $k = $field_key . $suffix;
                if ( isset( $raw[ $k ] ) && '' !== $raw[ $k ] ) {
                    $parts[] = $raw[ $k ];
                }
            }
            return trim( implode( ' ', $parts ) );
        }

        protected function entry_date( $raw ) {
            return isset( $raw['date_created'] ) ? (string) $raw['date_created'] : '';
        }
    }
}
