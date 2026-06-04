<?php
/**
 * B2B tier pricing engine (M2).
 *
 * Applies a company's tier percentage discount to cart prices and surfaces the
 * "your company price" on product/shop/cart display. Fully additive — it does
 * NOT edit the existing Storelly option-price engine (class-frontend-options.php).
 *
 * Why `woocommerce_before_calculate_totals` and not `woocommerce_product_get_price`:
 * the Storelly engine captures a builder item's base via `get_price('edit')`,
 * which bypasses the get_price filter, then stores a composed `pcpb_meta['price']`.
 * So the discount has to land at cart-totals time. For builder items we discount
 * only the immutable `original_price` portion (kept in pcpb_meta) so repeated
 * fires never compound; for regular products we read a FRESH catalog price.
 *
 * Tiers are merchant-defined (option `spbwc_b2b_tiers`) — never hardcoded.
 * See docs/SPEC_B2B_CLIENT.md §6 + §3.4.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Pricing' ) ) {

    class SPBWC_B2B_Pricing {

        const OPTION_TIERS = 'spbwc_b2b_tiers';

        public static function init() {
            // Charged price (after Storelly's pri-1 set_product_prices).
            add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_cart_discount' ), 20 );
            // Display: "your company price" on product/shop/cart.
            add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 20, 2 );
            // Storefront stylesheet for the price note (only when a discount applies).
            add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ), 20 );
        }

        /** Enqueue b2b.css on the storefront when the buyer is an active company member. */
        public static function maybe_enqueue() {
            if ( ! self::current_company_id() ) {
                return;
            }
            wp_enqueue_style( 'spbwc-b2b', SPBWC_PB_CSS_URL . 'b2b.css', array(), SPBWC_PB_VERSION );
        }

        /**
         * The current user's active company id, or 0.
         *
         * @param int $user_id User (0 = current).
         * @return int
         */
        public static function current_company_id( $user_id = 0 ) {
            $company_id = SPBWC_Company::get_user_company_id( $user_id );
            return ( $company_id && SPBWC_Company::is_active( $company_id ) ) ? $company_id : 0;
        }

        /* ── Tier registry ────────────────────────────────────────── */

        /**
         * All merchant-defined tiers.
         *
         * @return array<string,array> slug => { label, discount_pct, min_order, terms, free_ship_over }
         */
        public static function get_tiers() {
            $tiers = get_option( self::OPTION_TIERS, array() );
            return is_array( $tiers ) ? $tiers : array();
        }

        /**
         * Persist the tier set (caller sanitizes).
         *
         * @param array $tiers Tier map.
         * @return void
         */
        public static function save_tiers( array $tiers ) {
            update_option( self::OPTION_TIERS, $tiers, false );
        }

        /**
         * Label for a tier slug (falls back to the slug).
         *
         * @param string $slug Tier slug.
         * @return string
         */
        public static function tier_label( $slug ) {
            $tiers = self::get_tiers();
            return isset( $tiers[ $slug ]['label'] ) ? (string) $tiers[ $slug ]['label'] : (string) $slug;
        }

        /* ── Discount resolution ──────────────────────────────────── */

        /**
         * Tier discount percentage for a user (0 when not an active company member
         * or the company has no tier / unknown tier).
         *
         * @param int $user_id User (0 = current).
         * @return float Percent (e.g. 15.0).
         */
        public static function discount_pct( $user_id = 0 ) {
            $company_id = self::current_company_id( $user_id );
            if ( ! $company_id ) {
                return 0.0;
            }
            return self::tier_pct_for_company( $company_id );
        }

        /**
         * Tier discount % assigned to a company (0 when no/unknown tier).
         *
         * @param int $company_id Company.
         * @return float
         */
        public static function tier_pct_for_company( $company_id ) {
            $tier = (string) get_post_meta( absint( $company_id ), SPBWC_Company::META_TIER, true );
            if ( '' === $tier ) {
                return 0.0;
            }
            $tiers = self::get_tiers();
            if ( ! isset( $tiers[ $tier ]['discount_pct'] ) ) {
                return 0.0;
            }
            return max( 0.0, min( 100.0, (float) $tiers[ $tier ]['discount_pct'] ) );
        }

        /**
         * Cascade-resolve the effective base unit price for a product and the
         * current (or given) user: per-company fixed > per-company pct > tier % >
         * retail. Returns $base unchanged when the user is not an active company
         * member or nothing applies. Quantity gates per-company rules' min_qty
         * (pass 0 for display, where min_qty is ignored).
         *
         * @param float $base       Retail base unit price.
         * @param int   $product_id Product/variation.
         * @param int   $qty        Cart quantity (0 = display).
         * @param int   $user_id    User (0 = current).
         * @return float
         */
        public static function resolve_unit_price( $base, $product_id, $qty = 0, $user_id = 0 ) {
            $orig       = (float) $base;
            $company_id = self::current_company_id( $user_id );
            if ( ! $company_id ) {
                return $orig;
            }
            // 1-2. Per-company product override (fixed or pct).
            if ( class_exists( 'SPBWC_B2B_Price_Rules' ) ) {
                $resolved = SPBWC_B2B_Price_Rules::resolve( $company_id, $product_id, $orig, $qty );
                if ( null !== $resolved ) {
                    return (float) apply_filters( 'spbwc_b2b_unit_price', (float) $resolved, $orig, $product_id, $company_id, $qty );
                }
            }
            // 3. Tier %.
            $pct  = self::tier_pct_for_company( $company_id );
            $final = ( $pct > 0 ) ? $orig * ( ( 100.0 - $pct ) / 100.0 ) : $orig;
            return (float) apply_filters( 'spbwc_b2b_unit_price', $final, $orig, $product_id, $company_id, $qty );
        }

        /* ── Cart price ───────────────────────────────────────────── */

        /**
         * Apply the tier discount to every cart line.
         *
         * @param WC_Cart $cart Cart.
         */
        public static function apply_cart_discount( $cart ) {
            if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
                return;
            }
            if ( ! $cart instanceof WC_Cart || ! self::current_company_id() ) {
                return;
            }
            $decimals = wc_get_price_decimals();

            foreach ( $cart->get_cart() as $cart_item ) {
                if ( empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
                    continue;
                }
                $product = $cart_item['data'];
                $pid     = $cart_item['variation_id'] ? (int) $cart_item['variation_id'] : (int) $cart_item['product_id'];
                $qty     = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;

                // Builder item: resolve the base (original_price), keep option surcharge.
                if ( ! empty( $cart_item['pcpb_meta'] ) && isset( $cart_item['pcpb_meta']['price'] ) ) {
                    $pm       = $cart_item['pcpb_meta'];
                    $base     = isset( $pm['original_price'] ) ? (float) $pm['original_price'] : 0.0;
                    $composed = (float) $pm['price'];
                    if ( $base <= 0 ) {
                        continue;
                    }
                    $resolved = self::resolve_unit_price( $base, $pid, $qty );
                    if ( $resolved === $base ) {
                        continue;
                    }
                    $new = $composed - ( $base - $resolved ); // Swap the base portion only.
                    $product->set_price( max( 0.0, round( $new, $decimals ) ) );
                    continue;
                }

                // Regular product: resolve a FRESH catalog price (avoid compounding
                // across multiple before_calculate_totals fires).
                $fresh = wc_get_product( $pid );
                if ( ! $fresh ) {
                    continue;
                }
                $base = (float) $fresh->get_price();
                if ( $base <= 0 ) {
                    continue;
                }
                $resolved = self::resolve_unit_price( $base, $pid, $qty );
                if ( $resolved !== $base ) {
                    $product->set_price( round( $resolved, $decimals ) );
                }
            }
        }

        /* ── Display ──────────────────────────────────────────────── */

        /**
         * Show the company price + a small note on product/shop/cart display.
         *
         * @param string     $html    Existing price HTML.
         * @param WC_Product $product Product.
         * @return string
         */
        public static function price_html( $html, $product ) {
            if ( ! $product instanceof WC_Product || ! self::current_company_id() ) {
                return $html;
            }
            $regular = (float) $product->get_price();
            if ( $regular <= 0 ) {
                return $html;
            }
            $resolved = self::resolve_unit_price( $regular, $product->get_id(), 0 );
            if ( $resolved >= $regular ) {
                return $html; // No company discount on this product.
            }
            $company = wc_price( $resolved );
            $note    = '<span class="spbwc-b2b-price-note">' . esc_html__( 'Your company price', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            return '<span class="spbwc-b2b-price"><del>' . wp_kses_post( $html ) . '</del> <ins>' . wp_kses_post( $company ) . '</ins> ' . $note . '</span>';
        }
    }
}
