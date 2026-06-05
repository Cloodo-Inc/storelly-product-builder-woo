<?php
/**
 * Import adapter: Forminator → quotes (Quote Import M3b).
 *
 * Read through the official Forminator_API for the form + field list (each field
 * model exposes a slug/element id + label) and Forminator_Form_Entry_Model for
 * the stored entries (values fetched by element id via get_meta()).
 *
 * NOTE: written against the documented Forminator API; verify on a live install
 * running a supported WordPress version.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Adapter_Forminator' ) ) {

    class SPBWC_Quote_Adapter_Forminator extends SPBWC_Quote_Form_Adapter {

        public function id() {
            return 'forminator';
        }

        public function label() {
            return __( 'Forminator', 'storelly-product-builder-for-woocommerce' );
        }

        public function description() {
            return __( 'Submissions from your Forminator forms.', 'storelly-product-builder-for-woocommerce' );
        }

        public function is_available() {
            return class_exists( 'Forminator_API' ) && class_exists( 'Forminator_Form_Entry_Model' );
        }

        public function forms() {
            if ( ! class_exists( 'Forminator_API' ) ) {
                return array();
            }
            $models = Forminator_API::get_forms( null, 1, 999 );
            if ( is_wp_error( $models ) || ! is_array( $models ) ) {
                return array();
            }
            $forms = array();
            foreach ( $models as $model ) {
                if ( ! is_object( $model ) || ! method_exists( $model, 'get_fields' ) ) {
                    continue;
                }
                $fields = array();
                foreach ( (array) $model->get_fields() as $field ) {
                    $slug = isset( $field->slug ) ? (string) $field->slug : '';
                    if ( '' === $slug ) {
                        continue;
                    }
                    $label = method_exists( $field, 'get_label_for_entry' ) ? $field->get_label_for_entry() : '';
                    if ( '' === $label && isset( $field->raw['field_label'] ) ) {
                        $label = $field->raw['field_label'];
                    }
                    $fields[] = array(
                        'key'   => $slug,
                        'label' => '' !== $label ? $label : $slug,
                        'type'  => isset( $field->raw['type'] ) ? $field->raw['type'] : '',
                    );
                }
                $title   = isset( $model->settings['formName'] ) ? $model->settings['formName'] : ( isset( $model->name ) ? $model->name : ( '#' . $model->id ) );
                $forms[] = array(
                    'id'     => (int) $model->id,
                    'title'  => $title,
                    'fields' => $fields,
                );
            }
            return $forms;
        }

        protected function fetch_entries( $form_id, $limit ) {
            if ( ! class_exists( 'Forminator_Form_Entry_Model' ) ) {
                return array();
            }
            $entries = Forminator_Form_Entry_Model::get_entries( (int) $form_id );
            $entries = is_array( $entries ) ? $entries : array();
            return ( $limit > 0 ) ? array_slice( $entries, 0, $limit ) : $entries;
        }

        protected function entry_id( $raw ) {
            return (int) $raw->entry_id;
        }

        protected function entry_value( $raw, $field_key ) {
            if ( ! method_exists( $raw, 'get_meta' ) ) {
                return '';
            }
            $value = $raw->get_meta( $field_key, '' );
            return $this->flatten_value( $value );
        }

        protected function entry_date( $raw ) {
            return isset( $raw->date_created_sql ) ? (string) $raw->date_created_sql : '';
        }
    }
}
