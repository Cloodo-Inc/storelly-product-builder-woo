<?php
/**
 * Registers the `spbwc_quote` custom post type and its custom post statuses.
 *
 * Headless at M1 (`show_ui` = false): the CPT is storage only. The admin list
 * and detail screens are introduced in M2, the storefront writer in M3, and
 * the buyer My-Account views in M4. Registering the statuses now lets the
 * model's state machine operate before any UI exists.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Post_Type' ) ) {

    class SPBWC_Quote_Post_Type {

        /** @var SPBWC_Quote_Post_Type|null */
        protected static $instance;

        /**
         * @return SPBWC_Quote_Post_Type
         */
        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Attach hooks.
         */
        public function init() {
            add_action( 'init', array( $this, 'register_post_type' ) );
            add_action( 'init', array( $this, 'register_post_statuses' ) );
        }

        /**
         * Register the quote CPT (storage-only for now).
         */
        public function register_post_type() {
            register_post_type(
                SPBWC_Quote::POST_TYPE,
                array(
                    'labels'              => array(
                        'name'          => __( 'Quotes', 'storelly-product-builder-for-woocommerce' ),
                        'singular_name' => __( 'Quote', 'storelly-product-builder-for-woocommerce' ),
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
                    'supports'            => array( 'title', 'author', 'custom-fields' ),
                )
            );
        }

        /**
         * Register the ten quote post statuses (spec §14.2).
         */
        public function register_post_statuses() {
            foreach ( SPBWC_Quote::statuses() as $slug => $label ) {
                register_post_status(
                    $slug,
                    array(
                        'label'                     => $label,
                        'public'                    => false,
                        'internal'                  => false,
                        'protected'                 => true,
                        'exclude_from_search'       => true,
                        'show_in_admin_all_list'    => true,
                        'show_in_admin_status_list' => true,
                    )
                );
            }
        }
    }
}
