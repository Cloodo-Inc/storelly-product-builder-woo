<?php
/**
 * Registers the `spbwc_company` custom post type (headless).
 *
 * The CPT is storage-only (`show_ui` = false): all merchant UI is the custom
 * B2B Companies hub (SPBWC_B2B_Admin) and all buyer UI is the My-Account brand
 * store (SPBWC_B2B_Account). The public /store/<slug> page is served by a
 * dedicated rewrite in SPBWC_B2B_Storefront, NOT by the CPT's own permalink, so
 * the business status (meta) stays decoupled from WordPress publish state.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Company_Post_Type' ) ) {

    class SPBWC_B2B_Company_Post_Type {

        /** @var SPBWC_B2B_Company_Post_Type|null */
        protected static $instance;

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function init() {
            add_action( 'init', array( $this, 'register_post_type' ) );
        }

        public function register_post_type() {
            register_post_type(
                SPBWC_Company::POST_TYPE,
                array(
                    'labels'              => array(
                        'name'          => __( 'B2B Companies', 'storelly-product-builder-for-woocommerce' ),
                        'singular_name' => __( 'Company', 'storelly-product-builder-for-woocommerce' ),
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
    }
}
