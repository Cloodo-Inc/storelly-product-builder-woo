<?php
/**
 * Quote templates (P3.12) — reusable sets of line items + terms.
 *
 * A merchant saves a priced reply as a named template (CPT spbwc_quote_template)
 * and loads it into the pricing-reply builder on later quotes. Templates are
 * copy-on-apply: deleting a template never affects already-sent quotes.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Template' ) ) {

    class SPBWC_Quote_Template {

        const POST_TYPE = 'spbwc_quote_template';
        const META_LINES = '_spbwc_qt_lines';
        const META_TERMS = '_spbwc_qt_terms';

        public static function init() {
            add_action( 'init', array( __CLASS__, 'register' ) );
        }

        public static function register() {
            register_post_type(
                self::POST_TYPE,
                array(
                    'labels'              => array(
                        'name'          => __( 'Quote templates', 'storelly-product-builder-for-woocommerce' ),
                        'singular_name' => __( 'Quote template', 'storelly-product-builder-for-woocommerce' ),
                    ),
                    'public'              => false,
                    'show_ui'             => false,
                    'show_in_menu'        => false,
                    'show_in_rest'        => false,
                    'exclude_from_search' => true,
                    'publicly_queryable'  => false,
                    'hierarchical'        => false,
                    'has_archive'         => false,
                    'rewrite'             => false,
                    'query_var'           => false,
                    'capability_type'     => 'post',
                    'map_meta_cap'        => true,
                    'supports'            => array( 'title', 'author' ),
                )
            );
        }

        /**
         * Normalise raw line rows to the stored shape.
         *
         * @param array $raw Raw rows (label/desc/qty/unit_price).
         * @return array
         */
        public static function sanitize_lines( array $raw ) {
            $clean = array();
            foreach ( $raw as $row ) {
                $label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
                $qty   = isset( $row['qty'] ) ? (float) $row['qty'] : 0;
                $unit  = isset( $row['unit_price'] ) ? (float) $row['unit_price'] : 0;
                if ( '' === $label && $qty <= 0 ) {
                    continue;
                }
                $clean[] = array(
                    'label'      => $label,
                    'desc'       => isset( $row['desc'] ) ? sanitize_textarea_field( $row['desc'] ) : '',
                    'qty'        => $qty,
                    'unit_price' => $unit,
                );
            }
            return $clean;
        }

        /**
         * Create a template.
         *
         * @param string $name  Template name.
         * @param array  $lines Raw line rows.
         * @param array  $terms { valid_days:int, payment_terms:string, note:string }.
         * @return int|WP_Error Template post ID.
         */
        public static function create( $name, array $lines, array $terms ) {
            $name = sanitize_text_field( $name );
            if ( '' === $name ) {
                return new WP_Error( 'spbwc_qt_no_name', __( 'Template name is required.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $post_id = wp_insert_post(
                array(
                    'post_type'   => self::POST_TYPE,
                    'post_status' => 'publish',
                    'post_title'  => $name,
                    'post_author' => get_current_user_id(),
                ),
                true
            );
            if ( is_wp_error( $post_id ) ) {
                return $post_id;
            }
            update_post_meta( $post_id, self::META_LINES, self::sanitize_lines( $lines ) );
            update_post_meta(
                $post_id,
                self::META_TERMS,
                array(
                    'valid_days'    => isset( $terms['valid_days'] ) ? max( 0, (int) $terms['valid_days'] ) : 0,
                    'payment_terms' => isset( $terms['payment_terms'] ) ? sanitize_key( $terms['payment_terms'] ) : 'prepay',
                    'note'          => isset( $terms['note'] ) ? sanitize_textarea_field( $terms['note'] ) : '',
                )
            );
            return $post_id;
        }

        /**
         * @param int $id Template post ID.
         * @return bool
         */
        public static function delete( $id ) {
            $id   = absint( $id );
            $post = $id ? get_post( $id ) : null;
            if ( ! $post || self::POST_TYPE !== $post->post_type ) {
                return false;
            }
            return (bool) wp_delete_post( $id, true );
        }

        /**
         * All templates as plain arrays for the admin UI / JS.
         *
         * @return array[] [ id, name, lines[], terms{} ]
         */
        public static function get_all() {
            $posts = get_posts(
                array(
                    'post_type'   => self::POST_TYPE,
                    'post_status' => 'publish',
                    'numberposts' => 100,
                    'orderby'     => 'title',
                    'order'       => 'ASC',
                )
            );
            $out = array();
            foreach ( $posts as $p ) {
                $lines = get_post_meta( $p->ID, self::META_LINES, true );
                $terms = get_post_meta( $p->ID, self::META_TERMS, true );
                $out[] = array(
                    'id'    => $p->ID,
                    'name'  => $p->post_title,
                    'lines' => is_array( $lines ) ? array_values( $lines ) : array(),
                    'terms' => is_array( $terms ) ? $terms : array( 'valid_days' => 0, 'payment_terms' => 'prepay', 'note' => '' ),
                );
            }
            return $out;
        }
    }
}
