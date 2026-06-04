<?php
/**
 * B2B Reorders — My-Account "Reorders" tab + one-click reorder (M3).
 *
 * Lists the buyer's past purchased Storelly designs (order line items carrying
 * `_pcpb_folder`) and lets them re-add one to the cart in a click. Reuses the
 * proven copy-on-write clone + pcpb_meta rebuild used by Order-again / Saved
 * designs, so each reorder is an INDEPENDENT design instance. Company tier
 * pricing (M2) applies automatically once the item lands in the cart.
 *
 * No pricing-engine changes. HPOS-safe order discovery via wc_get_orders.
 * See docs/SPEC_B2B_CLIENT.md §7.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Reorders' ) ) {

    class SPBWC_B2B_Reorders {

        const ENDPOINT   = 'reorders';
        const FLUSH_FLAG = 'spbwc_b2b_reorders_flushed';

        public static function init() {
            add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
            add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
            add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_endpoint' ) );
            // Priority 50 so WooCommerce has its session + cart ready (matches Saved designs).
            add_action( 'wp_loaded', array( __CLASS__, 'handle_reorder' ), 50 );
        }

        public static function add_endpoint() {
            add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
            if ( 'yes' !== get_option( self::FLUSH_FLAG ) ) {
                flush_rewrite_rules( false );
                update_option( self::FLUSH_FLAG, 'yes' );
            }
        }

        /** Insert "Reorders" before logout. */
        public static function add_menu_item( $items ) {
            if ( ! is_user_logged_in() ) {
                return $items;
            }
            $logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null;
            if ( null !== $logout ) {
                unset( $items['customer-logout'] );
            }
            $items[ self::ENDPOINT ] = __( 'Reorders', 'storelly-product-builder-for-woocommerce' );
            if ( null !== $logout ) {
                $items['customer-logout'] = $logout;
            }
            return $items;
        }

        /* ── Collect reorderable designs ──────────────────────────── */

        /**
         * Build the buyer's reorderable list — one entry per purchased Storelly
         * design line, newest first, de-duplicated by design folder.
         *
         * @param int $user_id Buyer.
         * @return array[] { order_id, item_id, folder, product_id, name, qty, date, preview }
         */
        public static function collect( $user_id ) {
            $user_id = absint( $user_id );
            if ( ! $user_id ) {
                return array();
            }
            $orders = wc_get_orders(
                array(
                    'customer_id' => $user_id,
                    'limit'       => 50,
                    'orderby'     => 'date',
                    'order'       => 'DESC',
                    'status'      => array( 'wc-completed', 'wc-processing', 'wc-on-hold' ),
                )
            );
            $out  = array();
            $seen = array();
            foreach ( $orders as $order ) {
                if ( ! $order instanceof WC_Order ) {
                    continue;
                }
                foreach ( $order->get_items() as $item_id => $item ) {
                    $folder = (string) wc_get_order_item_meta( $item_id, '_pcpb_folder', true );
                    if ( '' === $folder || isset( $seen[ $folder ] ) ) {
                        continue;
                    }
                    $seen[ $folder ] = true;
                    $product_id = (int) $item->get_variation_id() ? (int) $item->get_variation_id() : (int) $item->get_product_id();
                    $out[]      = array(
                        'order_id'   => $order->get_id(),
                        'item_id'    => (int) $item_id,
                        'folder'     => $folder,
                        'product_id' => $product_id,
                        'name'       => $item->get_name(),
                        'qty'        => (int) $item->get_quantity(),
                        'date'       => $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '',
                        'preview'    => self::preview_url( $folder ),
                    );
                }
            }
            return $out;
        }

        /* ── Render ───────────────────────────────────────────────── */

        public static function render_endpoint() {
            if ( ! is_user_logged_in() ) {
                wc_get_template( 'myaccount/form-login.php' );
                return;
            }
            SPBWC_B2B_Assets::storefront();
            self::print_notice();

            $items = self::collect( get_current_user_id() );

            echo '<div class="spbwc-reorders">';
            echo '<h2>' . esc_html__( 'Reorders', 'storelly-product-builder-for-woocommerce' ) . '</h2>';
            echo '<p class="description">' . esc_html__( 'Reorder a past design with the same artwork and specs. Your company pricing is applied automatically at checkout.', 'storelly-product-builder-for-woocommerce' ) . '</p>';

            if ( empty( $items ) ) {
                echo '<p class="spbwc-store__empty">' . esc_html__( 'You have no past designs to reorder yet.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
                return;
            }

            echo '<ul class="spbwc-reorders__grid">';
            foreach ( $items as $it ) {
                $product = wc_get_product( $it['product_id'] );
                $can     = $product && $product->is_purchasable() && $product->is_in_stock();

                echo '<li class="spbwc-reorders__card">';
                if ( '' !== $it['preview'] ) {
                    echo '<img class="spbwc-reorders__thumb" src="' . esc_url( $it['preview'] ) . '" alt="" loading="lazy" />';
                }
                echo '<div class="spbwc-reorders__body">';
                echo '<span class="spbwc-reorders__name">' . esc_html( $it['name'] ) . '</span>';
                echo '<span class="spbwc-reorders__meta">' . esc_html(
                    sprintf(
                        /* translators: 1: quantity, 2: date. */
                        __( 'Last ordered ×%1$d · %2$s', 'storelly-product-builder-for-woocommerce' ),
                        $it['qty'],
                        $it['date']
                    )
                ) . '</span>';

                if ( $can ) {
                    echo '<form method="post" class="spbwc-reorders__form" action="' . esc_url( wc_get_endpoint_url( self::ENDPOINT, '', wc_get_page_permalink( 'myaccount' ) ) ) . '">';
                    wp_nonce_field( 'spbwc_reorder_' . $it['order_id'] . '_' . $it['item_id'], '_spbwc_reorder_nonce' );
                    echo '<input type="hidden" name="spbwc_reorder" value="1" />';
                    echo '<input type="hidden" name="order_id" value="' . esc_attr( $it['order_id'] ) . '" />';
                    echo '<input type="hidden" name="item_id" value="' . esc_attr( $it['item_id'] ) . '" />';
                    echo '<label class="spbwc-reorders__qty"><span>' . esc_html__( 'Qty', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                    echo '<input type="number" name="qty" min="1" value="' . esc_attr( max( 1, $it['qty'] ) ) . '" /></label>';
                    echo '<button type="submit" class="button">' . esc_html__( 'Reorder', 'storelly-product-builder-for-woocommerce' ) . '</button>';
                    echo '</form>';
                } else {
                    echo '<span class="spbwc-reorders__unavailable">' . esc_html__( 'Product unavailable', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                }
                echo '</div></li>';
            }
            echo '</ul></div>';
        }

        /* ── Reorder action ───────────────────────────────────────── */

        public static function handle_reorder() {
            if ( ! isset( $_POST['spbwc_reorder'] ) ) {
                return;
            }
            $order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
            $item_id  = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
            $qty      = isset( $_POST['qty'] ) ? max( 1, absint( wp_unslash( $_POST['qty'] ) ) ) : 1;
            $nonce    = isset( $_POST['_spbwc_reorder_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_spbwc_reorder_nonce'] ) ) : '';
            if ( ! $order_id || ! $item_id || ! wp_verify_nonce( $nonce, 'spbwc_reorder_' . $order_id . '_' . $item_id ) ) {
                return;
            }
            if ( ! is_user_logged_in() ) {
                wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }
            $order = wc_get_order( $order_id );
            if ( ! $order || (int) $order->get_customer_id() !== get_current_user_id() ) {
                wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }
            $item = $order->get_item( $item_id );
            $folder = (string) wc_get_order_item_meta( $item_id, '_pcpb_folder', true );
            if ( ! $item || '' === $folder ) {
                self::redirect_with_notice( 'error' );
            }

            $product_id = (int) $item->get_variation_id() ? (int) $item->get_variation_id() : (int) $item->get_product_id();
            $product    = wc_get_product( $product_id );
            if ( ! $product || ! $product->is_purchasable() ) {
                self::redirect_with_notice( 'error' );
            }

            // Clone the design folder so the reorder is an independent instance.
            $clone = SPBWC_Storelly_IO::spbwc_clone_design_folder( $folder );
            if ( '' === $clone ) {
                self::redirect_with_notice( 'error' );
            }

            $option_price   = wc_get_order_item_meta( $item_id, '_pcpb_option_price', true );
            $original_price = (float) wc_get_order_item_meta( $item_id, '_pcpb_original_price', true );
            $total_price    = is_array( $option_price ) && isset( $option_price['total_price'] ) ? (float) $option_price['total_price'] : 0;
            $discount_price = is_array( $option_price ) && isset( $option_price['discount_price'] ) ? (float) $option_price['discount_price'] : 0;

            $pcpb_meta = array(
                'option_price'   => is_array( $option_price ) ? $option_price : array(),
                'field'          => wc_get_order_item_meta( $item_id, '_pcpb_field', true ),
                'options'        => wc_get_order_item_meta( $item_id, '_pcpb_options', true ),
                'original_price' => $original_price,
                'price'          => $original_price + $total_price - $discount_price,
                'pcpb'           => $clone,
            );
            $preview = self::preview_url( $clone );
            if ( '' !== $preview ) {
                if ( ! is_array( $pcpb_meta['option_price'] ) ) {
                    $pcpb_meta['option_price'] = array();
                }
                $pcpb_meta['option_price']['cart_image'] = $preview;
            }

            $variation_id = $product->is_type( 'variation' ) ? $product_id : 0;
            $parent_id    = $variation_id ? $product->get_parent_id() : $product_id;

            if ( function_exists( 'WC' ) && WC()->cart ) {
                $added = WC()->cart->add_to_cart( $parent_id, $qty, $variation_id, array(), array( 'pcpb_meta' => $pcpb_meta ) );
                if ( $added ) {
                    wp_safe_redirect( wc_get_cart_url() );
                    exit;
                }
                // Add failed — clean up the orphan clone.
                SPBWC_Storelly_IO::spbwc_delete_folder( SPBWC_PB_CUSTOMER_DIR . '/' . $clone );
            }
            self::redirect_with_notice( 'error' );
        }

        /* ── Helpers ──────────────────────────────────────────────── */

        protected static function preview_url( $folder ) {
            $images = SPBWC_Storelly_IO::spbwc_get_list_images( SPBWC_PB_CUSTOMER_DIR . '/' . $folder . '/preview', 1 );
            if ( empty( $images ) ) {
                return '';
            }
            sort( $images );
            return SPBWC_Storelly_IO::spbwc_convert_path_to_url( reset( $images ) );
        }

        protected static function redirect_with_notice( $code ) {
            $url = add_query_arg( 'spbwc_reorder_msg', $code, wc_get_endpoint_url( self::ENDPOINT, '', wc_get_page_permalink( 'myaccount' ) ) );
            wp_safe_redirect( $url );
            exit;
        }

        protected static function print_notice() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash.
            if ( ! isset( $_GET['spbwc_reorder_msg'] ) ) {
                return;
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash.
            $code = sanitize_key( wp_unslash( $_GET['spbwc_reorder_msg'] ) );
            if ( 'error' === $code ) {
                echo '<div class="woocommerce-error" role="alert">' . esc_html__( 'Could not reorder that design. Please try again.', 'storelly-product-builder-for-woocommerce' ) . '</div>';
            }
        }
    }
}
