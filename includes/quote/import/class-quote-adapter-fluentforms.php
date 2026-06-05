<?php
/**
 * Import adapter: Fluent Forms → quotes (Quote Import M3).
 *
 * Fluent Forms (free) stores form definitions in `{prefix}fluentform_forms`
 * (the `form_fields` column is JSON) and submissions in
 * `{prefix}fluentform_submissions` (the `response` column is JSON keyed by the
 * field input name). We enumerate each form's input fields, let the merchant map
 * them to the quote fields, then import each submission.
 *
 * Name/address style fields store a sub-object (e.g. {first_name,last_name});
 * entry_value() flattens those to a readable string.
 *
 * NOTE: validate on a Fluent Forms install running a supported WordPress version
 * (the dev box runs a pre-release WP it fatals on).
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Adapter_Fluentforms' ) ) {

    class SPBWC_Quote_Adapter_Fluentforms extends SPBWC_Quote_Form_Adapter {

        public function id() {
            return 'fluentform';
        }

        public function label() {
            return __( 'Fluent Forms', 'storelly-product-builder-for-woocommerce' );
        }

        public function description() {
            return __( 'Submissions from your Fluent Forms contact / quote forms.', 'storelly-product-builder-for-woocommerce' );
        }

        public function is_available() {
            return function_exists( 'wpFluent' ) || defined( 'FLUENTFORM' );
        }

        protected function db() {
            return function_exists( 'wpFluent' ) ? wpFluent() : null;
        }

        public function forms() {
            $db = $this->db();
            if ( ! $db ) {
                return array();
            }
            $rows  = $db->table( 'fluentform_forms' )->select( array( 'id', 'title', 'form_fields' ) )->get();
            $forms = array();
            foreach ( (array) $rows as $row ) {
                $forms[] = array(
                    'id'     => (int) $row->id,
                    'title'  => $row->title,
                    'fields' => $this->parse_fields( $row->form_fields ),
                );
            }
            return $forms;
        }

        /** Walk the form_fields JSON and collect input [key,label,type]. */
        protected function parse_fields( $json ) {
            $data   = json_decode( (string) $json, true );
            $fields = array();
            if ( ! is_array( $data ) ) {
                return $fields;
            }
            $walk = function ( $nodes ) use ( &$walk, &$fields ) {
                foreach ( (array) $nodes as $node ) {
                    if ( ! is_array( $node ) ) {
                        continue;
                    }
                    if ( ! empty( $node['fields'] ) ) {
                        $walk( $node['fields'] ); // container (e.g. name/address sub-fields, columns).
                    }
                    if ( ! empty( $node['columns'] ) ) {
                        foreach ( (array) $node['columns'] as $col ) {
                            if ( ! empty( $col['fields'] ) ) {
                                $walk( $col['fields'] );
                            }
                        }
                    }
                    $name = isset( $node['attributes']['name'] ) ? (string) $node['attributes']['name'] : '';
                    if ( '' === $name ) {
                        continue;
                    }
                    $label = '';
                    if ( isset( $node['settings']['label'] ) && '' !== $node['settings']['label'] ) {
                        $label = (string) $node['settings']['label'];
                    } elseif ( isset( $node['settings']['admin_field_label'] ) ) {
                        $label = (string) $node['settings']['admin_field_label'];
                    }
                    $type = isset( $node['attributes']['type'] ) ? (string) $node['attributes']['type'] : ( isset( $node['element'] ) ? (string) $node['element'] : '' );
                    $fields[ $name ] = array( 'key' => $name, 'label' => '' !== $label ? $label : $name, 'type' => $type );
                }
            };
            $walk( isset( $data['fields'] ) ? $data['fields'] : $data );
            return array_values( $fields );
        }

        protected function fetch_entries( $form_id, $limit ) {
            $db = $this->db();
            if ( ! $db ) {
                return array();
            }
            $q = $db->table( 'fluentform_submissions' )
                ->where( 'form_id', (int) $form_id )
                ->orderBy( 'id', 'ASC' );
            if ( $limit > 0 ) {
                $q->limit( $limit );
            }
            return (array) $q->get();
        }

        protected function entry_id( $raw ) {
            return (int) $raw->id;
        }

        protected function entry_value( $raw, $field_key ) {
            $resp = json_decode( (string) $raw->response, true );
            if ( ! is_array( $resp ) || ! isset( $resp[ $field_key ] ) ) {
                return '';
            }
            return $this->flatten( $resp[ $field_key ] );
        }

        /** Flatten a Fluent value (scalar, name sub-object, or list) to a string. */
        protected function flatten( $value ) {
            if ( is_scalar( $value ) ) {
                return (string) $value;
            }
            if ( is_array( $value ) ) {
                if ( isset( $value['first_name'] ) || isset( $value['last_name'] ) ) {
                    return trim( ( isset( $value['first_name'] ) ? $value['first_name'] : '' ) . ' ' . ( isset( $value['last_name'] ) ? $value['last_name'] : '' ) );
                }
                $parts = array();
                foreach ( $value as $v ) {
                    if ( is_scalar( $v ) && '' !== (string) $v ) {
                        $parts[] = (string) $v;
                    }
                }
                return implode( ', ', $parts );
            }
            return '';
        }

        protected function entry_date( $raw ) {
            return isset( $raw->created_at ) ? (string) $raw->created_at : '';
        }
    }
}
