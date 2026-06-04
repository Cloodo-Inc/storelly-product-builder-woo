<?php
/**
 * Public Brand Store page — /store/<slug>.
 *
 * A company gets its own branded storefront URL that lists the products its team
 * may order ("pre-approved products"). Served via a dedicated rewrite rule that
 * resolves the slug → company, then rendered inside the active theme with
 * get_header()/get_footer(). Decoupled from the CPT permalink so the business
 * status (meta) gates visibility: only `active` companies are viewable; others
 * 404. See docs/SPEC_B2B_CLIENT.md §4.2.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Storefront' ) ) {

    class SPBWC_B2B_Storefront {

        const QUERY_VAR = 'spbwc_store';
        const FLUSH_FLAG = 'spbwc_b2b_store_flushed';

        public static function init() {
            add_action( 'init', array( __CLASS__, 'add_rewrite' ) );
            add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );
            add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
        }

        /** Register the /store/<slug> rewrite, flushing once. */
        public static function add_rewrite() {
            add_rewrite_rule( '^store/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
            if ( 'yes' !== get_option( self::FLUSH_FLAG ) ) {
                flush_rewrite_rules( false );
                update_option( self::FLUSH_FLAG, 'yes' );
            }
        }

        /**
         * @param string[] $vars Query vars.
         * @return string[]
         */
        public static function add_query_var( $vars ) {
            $vars[] = self::QUERY_VAR;
            return $vars;
        }

        /** Render the brand store when the query var is present. */
        public static function maybe_render() {
            $slug = get_query_var( self::QUERY_VAR );
            if ( '' === $slug || null === $slug ) {
                return;
            }
            $company_id = SPBWC_Company::get_by_slug( $slug );
            if ( ! $company_id || ! SPBWC_Company::is_active( $company_id ) ) {
                self::render_404();
            }

            status_header( 200 );
            self::enqueue();
            get_header();
            self::render_store( $company_id );
            get_footer();
            exit;
        }

        /** Send a clean 404 and stop. */
        protected static function render_404() {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            nocache_headers();
            get_template_part( 404 );
            exit;
        }

        /** Enqueue the brand-store stylesheet (+ token dependency). */
        protected static function enqueue() {
            SPBWC_B2B_Assets::storefront();
        }

        /**
         * Render the branded store body.
         *
         * @param int $company_id Company.
         */
        protected static function render_store( $company_id ) {
            $name      = get_the_title( $company_id );
            $tagline   = (string) get_post_meta( $company_id, SPBWC_Company::META_TAGLINE, true );
            $desc      = (string) get_post_meta( $company_id, SPBWC_Company::META_DESCRIPTION, true );
            $logo_id   = (int) get_post_meta( $company_id, SPBWC_Company::META_LOGO, true );
            $banner_id = (int) get_post_meta( $company_id, SPBWC_Company::META_BANNER, true );
            $primary   = SPBWC_Company::brand_primary( $company_id );
            $secondary = SPBWC_Company::brand_secondary( $company_id );
            $banner    = $banner_id ? wp_get_attachment_image_url( $banner_id, 'large' ) : '';

            $style = '--spbwc-brand-primary:' . esc_attr( $primary ) . ';--spbwc-brand-secondary:' . esc_attr( $secondary ) . ';';
            if ( $banner ) {
                $style .= "--spbwc-brand-banner:url('" . esc_url( $banner ) . "');";
            }

            echo '<div class="spbwc-store" style="' . esc_attr( $style ) . '">';

            // Brand Page Header (Pattern 9).
            echo '<header class="spbwc-store__header' . ( $banner ? ' has-banner' : '' ) . '">';
            echo '<div class="spbwc-store__header-inner">';
            if ( $logo_id ) {
                echo wp_get_attachment_image( $logo_id, 'thumbnail', false, array( 'class' => 'spbwc-store__logo', 'alt' => esc_attr( $name ) ) );
            }
            echo '<div class="spbwc-store__id">';
            echo '<span class="spbwc-store__eyebrow">' . esc_html__( 'Brand Store', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            echo '<h1 class="spbwc-store__title">' . esc_html( $name ) . '</h1>';
            if ( '' !== $tagline ) {
                echo '<p class="spbwc-store__tagline">' . esc_html( $tagline ) . '</p>';
            }
            echo '</div></div></header>';

            echo '<div class="spbwc-store__body">';
            if ( '' !== $desc ) {
                echo '<p class="spbwc-store__desc">' . esc_html( $desc ) . '</p>';
            }

            self::render_products( $company_id );

            echo '</div></div>';
        }

        /**
         * Render the pre-approved products grid (allow-list).
         *
         * @param int $company_id Company.
         */
        protected static function render_products( $company_id ) {
            $allowed = get_post_meta( $company_id, SPBWC_Company::META_ALLOWED_PRODUCTS, true );
            $allowed = is_array( $allowed ) ? array_map( 'absint', $allowed ) : array();

            echo '<h2 class="spbwc-store__section-title">' . esc_html__( 'Pre-approved products', 'storelly-product-builder-for-woocommerce' ) . '</h2>';

            if ( empty( $allowed ) ) {
                echo '<p class="spbwc-store__empty">' . esc_html__( 'No products have been added to this Brand Store yet.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                return;
            }

            echo '<ul class="spbwc-store__grid">';
            foreach ( $allowed as $product_id ) {
                $product = wc_get_product( $product_id );
                if ( ! $product || ! $product->is_visible() ) {
                    continue;
                }
                echo '<li class="spbwc-store__card">';
                echo '<a href="' . esc_url( get_permalink( $product_id ) ) . '" class="spbwc-store__card-link">';
                echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) );
                echo '<span class="spbwc-store__card-name">' . esc_html( $product->get_name() ) . '</span>';
                echo '<span class="spbwc-store__card-price">' . wp_kses_post( $product->get_price_html() ) . '</span>';
                echo '</a></li>';
            }
            echo '</ul>';
        }
    }
}
