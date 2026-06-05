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
         * Layout (top → bottom): a banner-forward brand hero (banner image layer +
         * brand-gradient overlay, with logo/monogram + identity), a row of
         * brand-safe badges assigned to the store (tier name, industry, catalogue
         * size, team size), the description, then the pre-approved product grid.
         * Everything is driven by the storefront design tokens; the company's brand
         * colours are injected as CSS custom properties so the whole page re-tints.
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

            // Prepare the visible product list once (re-used for the count badge).
            $products = self::visible_products( $company_id );

            $style = '--spbwc-brand-primary:' . esc_attr( $primary ) . ';--spbwc-brand-secondary:' . esc_attr( $secondary ) . ';';

            echo '<div class="spbwc-store" style="' . esc_attr( $style ) . '">';

            // ── Brand hero (banner-forward) ──────────────────────────
            echo '<header class="spbwc-store__hero' . ( $banner ? ' has-banner' : '' ) . '">';
            if ( $banner ) {
                echo '<div class="spbwc-store__banner" style="background-image:url(\'' . esc_url( $banner ) . '\')" role="img" aria-label="' . esc_attr( $name ) . '"></div>';
            }
            echo '<div class="spbwc-store__hero-veil" aria-hidden="true"></div>';
            echo '<div class="spbwc-store__hero-inner">';

            // Logo, with a brand-tinted monogram fallback when none is uploaded.
            echo '<div class="spbwc-store__logo-wrap">';
            if ( $logo_id ) {
                echo wp_get_attachment_image( $logo_id, 'medium', false, array( 'class' => 'spbwc-store__logo', 'alt' => esc_attr( $name ) ) );
            } else {
                echo '<span class="spbwc-store__monogram" aria-hidden="true">' . esc_html( self::monogram( $name ) ) . '</span>';
            }
            echo '</div>';

            echo '<div class="spbwc-store__id">';
            echo '<span class="spbwc-store__eyebrow">' . esc_html__( 'Brand Store', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            echo '<h1 class="spbwc-store__title">' . esc_html( $name ) . '</h1>';
            if ( '' !== $tagline ) {
                echo '<p class="spbwc-store__tagline">' . esc_html( $tagline ) . '</p>';
            }
            echo '</div></div></header>';

            // ── Brand-safe badge row (cards assigned to the store) ───
            self::render_badges( $company_id, count( $products ) );

            // ── Body: description + catalogue ────────────────────────
            echo '<div class="spbwc-store__body">';
            if ( '' !== $desc ) {
                echo '<p class="spbwc-store__desc">' . esc_html( $desc ) . '</p>';
            }

            self::render_products( $products );

            echo '</div></div>';
        }

        /**
         * Build a 1–2 letter monogram from the company name (logo fallback).
         *
         * @param string $name Company name.
         * @return string
         */
        protected static function monogram( $name ) {
            $name  = trim( wp_strip_all_tags( (string) $name ) );
            if ( '' === $name ) {
                return '#';
            }
            $parts = preg_split( '/\s+/', $name );
            $first = function_exists( 'mb_substr' ) ? mb_substr( $parts[0], 0, 1 ) : substr( $parts[0], 0, 1 );
            $last  = '';
            if ( count( $parts ) > 1 ) {
                $tail = $parts[ count( $parts ) - 1 ];
                $last = function_exists( 'mb_substr' ) ? mb_substr( $tail, 0, 1 ) : substr( $tail, 0, 1 );
            }
            $mono = $first . $last;
            return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $mono ) : strtoupper( $mono );
        }

        /**
         * Render the brand-safe badge row.
         *
         * Deliberately public-safe: tier NAME (no discount %), industry, catalogue
         * size and team size. Commercial terms (discount, payment terms, approval
         * thresholds) are intentionally NOT exposed on this public page.
         *
         * @param int $company_id    Company.
         * @param int $product_count Visible pre-approved products.
         */
        protected static function render_badges( $company_id, $product_count ) {
            $badges = array();

            // Tier (name only — never the discount %).
            $tier = (string) get_post_meta( $company_id, SPBWC_Company::META_TIER, true );
            if ( '' !== $tier && class_exists( 'SPBWC_B2B_Pricing' ) ) {
                $label = SPBWC_B2B_Pricing::tier_label( $tier );
                if ( '' !== (string) $label ) {
                    $badges[] = array( 'icon' => 'awards', 'mod' => 'tier', 'text' => $label );
                }
            }

            // Industry (from the company profile).
            $profile = get_post_meta( $company_id, SPBWC_Company::META_PROFILE, true );
            if ( is_array( $profile ) && ! empty( $profile['industry'] ) ) {
                $badges[] = array( 'icon' => 'building', 'mod' => 'industry', 'text' => (string) $profile['industry'] );
            }

            // Catalogue size.
            $badges[] = array(
                'icon' => 'cart',
                'mod'  => 'catalogue',
                /* translators: %s: number of products. */
                'text' => sprintf( _n( '%s product', '%s products', $product_count, 'storelly-product-builder-for-woocommerce' ), number_format_i18n( $product_count ) ),
            );

            // Team size.
            $members = SPBWC_Company::count_members( $company_id );
            $badges[] = array(
                'icon' => 'groups',
                'mod'  => 'team',
                /* translators: %s: number of team members. */
                'text' => sprintf( _n( '%s team member', '%s team members', $members, 'storelly-product-builder-for-woocommerce' ), number_format_i18n( $members ) ),
            );

            echo '<div class="spbwc-store__badges">';
            foreach ( $badges as $b ) {
                echo '<span class="spbwc-store__badge spbwc-store__badge--' . esc_attr( $b['mod'] ) . '">';
                echo '<span class="spbwc-store__badge-ic dashicons dashicons-' . esc_attr( $b['icon'] ) . '" aria-hidden="true"></span>';
                echo '<span class="spbwc-store__badge-tx">' . esc_html( $b['text'] ) . '</span>';
                echo '</span>';
            }
            echo '</div>';
        }

        /**
         * The pre-approved products that are actually visible, in allow-list order.
         *
         * @param int $company_id Company.
         * @return WC_Product[]
         */
        protected static function visible_products( $company_id ) {
            $allowed = get_post_meta( $company_id, SPBWC_Company::META_ALLOWED_PRODUCTS, true );
            $allowed = is_array( $allowed ) ? array_map( 'absint', $allowed ) : array();
            $out     = array();
            foreach ( $allowed as $product_id ) {
                $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
                if ( $product && $product->is_visible() ) {
                    $out[] = $product;
                }
            }
            return $out;
        }

        /**
         * Render the pre-approved products grid (allow-list).
         *
         * @param WC_Product[] $products Prepared, visible products.
         */
        protected static function render_products( $products ) {
            echo '<div class="spbwc-store__section-head">';
            echo '<h2 class="spbwc-store__section-title">' . esc_html__( 'Pre-approved products', 'storelly-product-builder-for-woocommerce' ) . '</h2>';
            if ( ! empty( $products ) ) {
                /* translators: %s: number of products. */
                echo '<span class="spbwc-store__section-count">' . esc_html( sprintf( _n( '%s item', '%s items', count( $products ), 'storelly-product-builder-for-woocommerce' ), number_format_i18n( count( $products ) ) ) ) . '</span>';
            }
            echo '</div>';

            if ( empty( $products ) ) {
                echo '<p class="spbwc-store__empty">' . esc_html__( 'No products have been added to this Brand Store yet.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                return;
            }

            echo '<ul class="spbwc-store__grid">';
            foreach ( $products as $product ) {
                $product_id = $product->get_id();
                $on_sale    = $product->is_on_sale();
                echo '<li class="spbwc-store__card">';
                echo '<a href="' . esc_url( get_permalink( $product_id ) ) . '" class="spbwc-store__card-link">';
                echo '<span class="spbwc-store__card-thumb">';
                if ( $on_sale ) {
                    echo '<span class="spbwc-store__card-flag">' . esc_html__( 'Sale', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                }
                echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) );
                echo '</span>';
                echo '<span class="spbwc-store__card-body">';
                echo '<span class="spbwc-store__card-name">' . esc_html( $product->get_name() ) . '</span>';
                echo '<span class="spbwc-store__card-price">' . wp_kses_post( $product->get_price_html() ) . '</span>';
                echo '<span class="spbwc-store__card-cta">' . esc_html__( 'View product', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                echo '</span>';
                echo '</a></li>';
            }
            echo '</ul>';
        }
    }
}
