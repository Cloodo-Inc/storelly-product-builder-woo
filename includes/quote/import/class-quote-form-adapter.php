<?php
/**
 * Base class for a contact-form import source (Quote Import & Sync, M3).
 *
 * Contact-form plugins (CF7+Flamingo, Fluent Forms, …) store submissions with
 * arbitrary field structures, so unlike the order-based sources a form source:
 *   1. enumerates its forms + each form's fields (forms()),
 *   2. lets the merchant MAP those fields onto the quote fields (name / email /
 *      phone / company / message) — auto-guessed, then editable in the UI,
 *   3. imports each un-imported entry through the saved mapping.
 *
 * Dedupe is canonical (the created quote's `_spbwc_imported_from` meta), so form
 * adapters do not need a writable per-entry flag — fetch_batch() skips refs the
 * controller already knows.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Form_Adapter' ) ) {

    abstract class SPBWC_Quote_Form_Adapter extends SPBWC_Quote_Source_Adapter {

        /** Quote target fields a form field can be mapped onto. */
        const TARGETS = array( 'name', 'email', 'phone', 'company', 'message' );

        /** Optional: limit a scan/import to one form id ('' = all mapped forms). */
        public $scope_form = '';

        /* ── Concrete adapters implement these ───────────────────────── */

        /**
         * @return array[] [ 'id'=>string|int, 'title'=>string,
         *                   'fields'=>[ ['key'=>, 'label'=>, 'type'=>] ] ]
         */
        abstract public function forms();

        /** Raw entry rows for a form (plugin-native objects/arrays). */
        abstract protected function fetch_entries( $form_id, $limit );

        /** Stable entry id within a form. */
        abstract protected function entry_id( $raw );

        /** Value of one mapped field key within a raw entry. */
        abstract protected function entry_value( $raw, $field_key );

        /** Entry creation date as 'Y-m-d H:i:s', or '' if unknown. */
        abstract protected function entry_date( $raw );

        /* ── Shared form-source behaviour ────────────────────────────── */

        /** Forms that have a saved field mapping (only these are importable). */
        protected function mapped_forms() {
            $out = array();
            foreach ( $this->forms() as $form ) {
                if ( '' !== $this->scope_form && (string) $form['id'] !== (string) $this->scope_form ) {
                    continue;
                }
                $map = SPBWC_Quote_Import::get_mapping( $this->id(), (string) $form['id'] );
                if ( ! empty( $map['email'] ) || ! empty( $map['name'] ) ) {
                    $out[] = $form;
                }
            }
            return $out;
        }

        /** Build normalized envelopes for un-imported entries across mapped forms. */
        protected function collect( $limit ) {
            $seen = SPBWC_Quote_Import::imported_refs( $this->id() );
            $rows = array();
            foreach ( $this->mapped_forms() as $form ) {
                $fid = (string) $form['id'];
                foreach ( (array) $this->fetch_entries( $fid, $limit > 0 ? $limit : -1 ) as $raw ) {
                    $ref = 'form' . $fid . ':entry' . $this->entry_id( $raw );
                    if ( isset( $seen[ $ref ] ) ) {
                        continue;
                    }
                    $rows[] = array(
                        'ref'  => $ref,
                        'form' => $fid,
                        'date' => $this->entry_date( $raw ),
                        'raw'  => $raw,
                    );
                    if ( $limit > 0 && count( $rows ) >= $limit ) {
                        return $rows;
                    }
                }
            }
            return $rows;
        }

        public function count_importable() {
            return count( $this->collect( -1 ) );
        }

        public function fetch_batch( $limit ) {
            return $this->collect( max( 1, (int) $limit ) );
        }

        public function source_ref( $row ) {
            return isset( $row['ref'] ) ? $row['ref'] : '';
        }

        /** Canonical dedupe handles it; nothing to flag on the source side. */
        public function mark_imported( $row, $quote_id ) {}

        public function map_to_quote( $row ) {
            if ( empty( $row['raw'] ) || empty( $row['form'] ) ) {
                return array();
            }
            $map = SPBWC_Quote_Import::get_mapping( $this->id(), (string) $row['form'] );
            if ( empty( $map ) ) {
                return array();
            }
            $val = function ( $target ) use ( $map, $row ) {
                return ( ! empty( $map[ $target ] ) ) ? trim( (string) $this->entry_value( $row['raw'], $map[ $target ] ) ) : '';
            };

            $request = array(
                'email'   => $val( 'email' ),
                'phone'   => $val( 'phone' ),
                'company' => $val( 'company' ),
                'message' => $val( 'message' ),
            );
            // A single "name" field → split into first / last.
            $name = $val( 'name' );
            if ( '' !== $name ) {
                $parts                 = explode( ' ', $name, 2 );
                $request['first_name'] = $parts[0];
                $request['last_name']  = isset( $parts[1] ) ? $parts[1] : '';
            }

            return array(
                'request' => $request,
                'user_id' => 0,
                'lines'   => array(), // a form request has no priced line items yet.
                'status'  => SPBWC_Quote::STATUS_NEW,
                'date'    => isset( $row['date'] ) ? $row['date'] : '',
            );
        }

        /* ── Auto-mapping heuristic (UI pre-fill) ────────────────────── */

        /**
         * Best-guess mapping target => field key from a form's field list.
         *
         * @param array $fields [ ['key'=>, 'label'=>, 'type'=>] ]
         * @return array target => field key
         */
        public static function auto_map( array $fields ) {
            $map  = array();
            $find = function ( $needles, $types = array() ) use ( $fields, &$map ) {
                foreach ( $fields as $f ) {
                    $key   = isset( $f['key'] ) ? (string) $f['key'] : '';
                    $label = strtolower( ( isset( $f['label'] ) ? $f['label'] : '' ) . ' ' . $key );
                    $type  = isset( $f['type'] ) ? strtolower( (string) $f['type'] ) : '';
                    if ( in_array( $type, $types, true ) ) {
                        return $key;
                    }
                    foreach ( $needles as $n ) {
                        if ( false !== strpos( $label, $n ) ) {
                            return $key;
                        }
                    }
                }
                return '';
            };
            $map['email']   = $find( array( 'email', 'e-mail' ), array( 'email' ) );
            $map['name']    = $find( array( 'your-name', 'full name', 'name' ), array( 'name' ) );
            $map['phone']   = $find( array( 'phone', 'tel', 'mobile' ), array( 'tel', 'phone' ) );
            $map['company'] = $find( array( 'company', 'organization', 'organisation', 'business' ) );
            $map['message'] = $find( array( 'message', 'comment', 'detail', 'note', 'requirement', 'enquiry', 'inquiry' ), array( 'textarea' ) );
            return array_filter( $map );
        }
    }
}
