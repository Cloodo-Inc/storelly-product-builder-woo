<?php
/**
 * Import adapter: Contact Form 7 (via Flamingo) → quotes (Quote Import M3).
 *
 * Contact Form 7 does not store submissions itself; the companion Flamingo plugin
 * does, as the `flamingo_inbound` CPT grouped by the `flamingo_inbound_channel`
 * taxonomy (one term per form). Field values are saved as `_field_<name>` post
 * meta, with the sender in `_from_name` / `_from_email`.
 *
 * Each Flamingo channel is exposed as a "form"; its fields are the union of
 * `_field_*` keys seen on recent messages plus the synthetic sender fields. The
 * merchant maps them to the quote fields in the Import tab.
 *
 * NOTE: validate on a CF7+Flamingo install running a WordPress version those
 * plugins support (the dev box runs a pre-release WP they fatal on).
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Adapter_Flamingo' ) ) {

    class SPBWC_Quote_Adapter_Flamingo extends SPBWC_Quote_Form_Adapter {

        const CPT      = 'flamingo_inbound';
        const TAXONOMY = 'flamingo_inbound_channel';

        public function id() {
            return 'cf7_flamingo';
        }

        public function label() {
            return __( 'Contact Form 7 (Flamingo)', 'storelly-product-builder-for-woocommerce' );
        }

        public function description() {
            return __( 'Form submissions saved by Flamingo from your Contact Form 7 forms.', 'storelly-product-builder-for-woocommerce' );
        }

        public function is_available() {
            return post_type_exists( self::CPT );
        }

        public function forms() {
            if ( ! $this->is_available() ) {
                return array();
            }
            $terms = get_terms(
                array(
                    'taxonomy'   => self::TAXONOMY,
                    'hide_empty' => true,
                )
            );
            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                return array();
            }
            $forms = array();
            foreach ( $terms as $term ) {
                $forms[] = array(
                    'id'     => $term->slug,
                    'title'  => $term->name,
                    'fields' => $this->channel_fields( $term->slug ),
                );
            }
            return $forms;
        }

        /** Sample recent messages in a channel to discover its field keys. */
        protected function channel_fields( $channel ) {
            $sample = get_posts(
                array(
                    'post_type'      => self::CPT,
                    'post_status'    => 'publish',
                    'posts_per_page' => 20,
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Admin-triggered import scan.
                    'tax_query'      => array(
                        array(
                            'taxonomy' => self::TAXONOMY,
                            'field'    => 'slug',
                            'terms'    => $channel,
                        ),
                    ),
                )
            );
            $keys   = array();
            foreach ( $sample as $post ) {
                foreach ( get_post_meta( $post->ID ) as $mkey => $unused ) {
                    if ( 0 === strpos( $mkey, '_field_' ) ) {
                        $keys[ substr( $mkey, strlen( '_field_' ) ) ] = true;
                    }
                }
            }
            $fields = array(
                array( 'key' => '__from_email', 'label' => __( 'Sender email', 'storelly-product-builder-for-woocommerce' ), 'type' => 'email' ),
                array( 'key' => '__from_name', 'label' => __( 'Sender name', 'storelly-product-builder-for-woocommerce' ), 'type' => 'name' ),
            );
            foreach ( array_keys( $keys ) as $k ) {
                $fields[] = array( 'key' => $k, 'label' => $k, 'type' => '' );
            }
            return $fields;
        }

        protected function fetch_entries( $form_id, $limit ) {
            return get_posts(
                array(
                    'post_type'      => self::CPT,
                    'post_status'    => 'publish',
                    'posts_per_page' => $limit > 0 ? $limit : -1,
                    'orderby'        => 'date',
                    'order'          => 'ASC',
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Admin-triggered import scan.
                    'tax_query'      => array(
                        array(
                            'taxonomy' => self::TAXONOMY,
                            'field'    => 'slug',
                            'terms'    => $form_id,
                        ),
                    ),
                )
            );
        }

        protected function entry_id( $raw ) {
            return (int) $raw->ID;
        }

        protected function entry_value( $raw, $field_key ) {
            if ( '__from_email' === $field_key ) {
                return (string) get_post_meta( $raw->ID, '_from_email', true );
            }
            if ( '__from_name' === $field_key ) {
                return (string) get_post_meta( $raw->ID, '_from_name', true );
            }
            $value = get_post_meta( $raw->ID, '_field_' . $field_key, true );
            return is_array( $value ) ? implode( ', ', $value ) : (string) $value;
        }

        protected function entry_date( $raw ) {
            return $raw->post_date ? $raw->post_date : '';
        }
    }
}
