<?php
/**
 * Saved designs — let a buyer persist a configured design to their account and reload it.
 *
 * A saved design is a `spbwc_saved_design` post authored by the buyer. It stores a
 * copy-on-write CLONE of the design folder plus the option payload, so loading it back into
 * the cart never touches the artwork of any order it came from.
 *
 * Surfaces:
 *   - "Save design" link on each custom line item of a My Account order (entry point — OD-3).
 *   - "Saved designs" My Account tab listing previews with Load (→ add to cart) and Delete.
 *
 * Every action is nonce-protected and ownership-checked (post_author / order customer).
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Saved_Designs' ) ) {

    class SPBWC_Saved_Designs {

        const POST_TYPE     = 'spbwc_saved_design';
        const ENDPOINT      = 'saved-designs';
        const META_FOLDER   = '_pcpb_saved_folder';
        const META_FIELD    = '_pcpb_saved_field';
        const META_OPTIONS  = '_pcpb_saved_options';
        const META_PRICE    = '_pcpb_saved_original_price';
        const META_OPTPRICE = '_pcpb_saved_option_price';
        const META_PRODUCT  = '_pcpb_saved_product_id';
        const META_PREVIEW  = '_pcpb_saved_preview';
        const FLUSH_FLAG    = 'spbwc_saved_designs_flushed';

        public static function init() {
            add_action( 'init', array( __CLASS__, 'register_post_type' ) );
            add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
            add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
            add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_endpoint' ) );
            add_action( 'woocommerce_order_item_meta_end', array( __CLASS__, 'render_save_link' ), 20, 4 );
            // Save a design straight from the cart (D1 / cart-only entry point).
            add_action( 'woocommerce_after_cart_item_name', array( __CLASS__, 'render_cart_save_link' ), 10, 2 );
            // Cart *block* (Store API) integration — feed design data to cart-block-save.js (E1).
            add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register_store_api' ) );
            // Action handlers (save links = GET, load/delete = POST). Priority 50 so
            // WooCommerce has set up the session + cart (its own wp_loaded init) before
            // the cart-save handler reads WC()->cart->get_cart_item().
            add_action( 'wp_loaded', array( __CLASS__, 'handle_actions' ), 50 );
        }

        /** Register the headless saved-design CPT. */
        public static function register_post_type() {
            register_post_type(
                self::POST_TYPE,
                array(
                    'public'              => false,
                    'show_ui'             => false,
                    'show_in_menu'        => false,
                    'exclude_from_search' => true,
                    'publicly_queryable'  => false,
                    'rewrite'             => false,
                    'query_var'           => false,
                    'supports'            => array( 'title', 'author' ),
                    'capability_type'     => 'post',
                    'map_meta_cap'        => true,
                )
            );
        }

        /** Register the My Account endpoint, flushing rewrite rules once. */
        public static function add_endpoint() {
            add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
            if ( 'yes' !== get_option( self::FLUSH_FLAG ) ) {
                flush_rewrite_rules( false );
                update_option( self::FLUSH_FLAG, 'yes' );
            }
        }

        /** Insert the "Saved designs" item before logout. */
        public static function add_menu_item( $items ) {
            $logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null;
            if ( null !== $logout ) {
                unset( $items['customer-logout'] );
            }
            $items[ self::ENDPOINT ] = __( 'Saved designs', 'storelly-product-builder-for-woocommerce' );
            if ( null !== $logout ) {
                $items['customer-logout'] = $logout;
            }
            return $items;
        }

        /**
         * Maximum saved designs per user. 0 = unlimited (default). Filterable so a
         * merchant can cap storage; an advertised cap must be enforced here (compliance).
         *
         * @return int
         */
        protected static function max_per_user() {
            return (int) apply_filters( 'spbwc_saved_design_max', 0 );
        }

        /**
         * Whether a Save-design entry point is enabled (User Account settings, default on).
         *
         * @param string $where 'cart' | 'order' | 'builder'.
         * @return bool
         */
        public static function entry_enabled( $where ) {
            $settings = get_option( 'spbwc_pb_settings', array() );
            $key = 'save_on_' . $where;
            // Default to enabled when the setting was never saved.
            return ! ( isset( $settings[ $key ] ) && 'no' === $settings[ $key ] );
        }

        // ------------------------------------------------------------------
        //  Entry point: "Save design" link on a My Account order line item
        // ------------------------------------------------------------------

        /**
         * @param int           $item_id    Order item ID.
         * @param WC_Order_Item $item       Order item.
         * @param WC_Order      $order      Order.
         * @param bool          $plain_text Plain-text email context.
         */
        public static function render_save_link( $item_id, $item, $order, $plain_text = false ) {
            if ( ! self::entry_enabled( 'order' ) ) {
                return;
            }
            if ( $plain_text || ! is_user_logged_in() || ! ( $order instanceof WC_Order ) || ! is_callable( array( $item, 'get_meta' ) ) ) {
                return;
            }
            if ( (int) $order->get_customer_id() !== get_current_user_id() ) {
                return;
            }
            if ( '' === (string) $item->get_meta( '_pcpb_folder' ) ) {
                return;
            }
            $url = wp_nonce_url(
                add_query_arg(
                    array(
                        'spbwc_save_design' => 1,
                        'order_id'          => $order->get_id(),
                        'item_id'           => (int) $item_id,
                    ),
                    home_url( '/' )
                ),
                'spbwc_save_design_' . $order->get_id() . '_' . (int) $item_id
            );
            $icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>';
            echo '<p class="spbwc-co-action spbwc-saved-design-save"><a class="spbwc-co-chip spbwc-co-chip--ghost" href="' . esc_url( $url ) . '">'
                . $icon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG icon, no dynamic data.
                . '<span>' . esc_html__( 'Save design to my account', 'storelly-product-builder-for-woocommerce' ) . '</span>'
                . '</a></p>';
        }

        /**
         * "Save design" link under a cart line item (D1, cart-only entry point).
         *
         * @param array  $cart_item     Cart item.
         * @param string $cart_item_key Cart item key.
         */
        public static function render_cart_save_link( $cart_item, $cart_item_key ) {
            if ( ! self::entry_enabled( 'cart' ) ) {
                return;
            }
            if ( ! is_array( $cart_item ) || empty( $cart_item['pcpb_meta'] ) || empty( $cart_item['pcpb_meta']['pcpb'] ) ) {
                return;
            }
            // Guests have no account to save into — offer a login link instead (OD-8).
            if ( ! is_user_logged_in() ) {
                echo '<p class="spbwc-co-action spbwc-cart-save"><a class="spbwc-co-chip spbwc-co-chip--ghost" href="'
                    . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">'
                    . esc_html__( 'Log in to save this design', 'storelly-product-builder-for-woocommerce' )
                    . '</a></p>';
                return;
            }
            $icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>';
            $url  = self::cart_save_url( $cart_item_key );
            echo '<p class="spbwc-co-action spbwc-cart-save"><a class="spbwc-co-chip spbwc-co-chip--ghost" href="' . esc_url( $url ) . '">'
                . $icon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG icon.
                . '<span>' . esc_html__( 'Save design to my account', 'storelly-product-builder-for-woocommerce' ) . '</span>'
                . '</a></p>';
        }

        /**
         * Nonce-protected URL that saves a cart item's design to the account (D1).
         * Shared by the classic-cart link and the Cart-block Store API data (E1).
         *
         * @param string $cart_item_key Cart item key.
         * @return string
         */
        public static function cart_save_url( $cart_item_key ) {
            // Build a RAW URL (not wp_nonce_url(), which esc_html()s the result and would
            // double-encode once it passes through Store API JSON → JS .href). The classic
            // link applies esc_url() at echo time; the block JS assigns it to .href directly.
            $url = add_query_arg(
                array(
                    'spbwc_save_cart_design' => 1,
                    'cart_item_key'          => $cart_item_key,
                ),
                home_url( '/' )
            );
            return add_query_arg( '_wpnonce', wp_create_nonce( 'spbwc_save_cart_design_' . $cart_item_key ), $url );
        }

        // ------------------------------------------------------------------
        //  Cart block (Store API) integration — E1
        // ------------------------------------------------------------------

        /**
         * Expose per-cart-item design data to the Store API so the Cart *block*
         * (React) can render a "Save design" button. Fed to cart-block-save.js.
         */
        public static function register_store_api() {
            if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' )
                || ! class_exists( '\\Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CartItemSchema' ) ) {
                return;
            }
            woocommerce_store_api_register_endpoint_data(
                array(
                    'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
                    'namespace'       => 'storelly',
                    'data_callback'   => array( __CLASS__, 'store_api_item_data' ),
                    'schema_callback' => array( __CLASS__, 'store_api_item_schema' ),
                    'schema_type'     => ARRAY_A,
                )
            );
        }

        /**
         * @param array $cart_item WooCommerce cart item.
         * @return array
         */
        public static function store_api_item_data( $cart_item ) {
            $folder = isset( $cart_item['pcpb_meta']['pcpb'] ) ? (string) $cart_item['pcpb_meta']['pcpb'] : '';
            // Respect the cart entry-point toggle for the block cart too.
            $is_design = ( '' !== $folder ) && self::entry_enabled( 'cart' );
            $key = isset( $cart_item['key'] ) ? (string) $cart_item['key'] : '';
            return array(
                'is_design' => $is_design,
                'save_url'  => ( $is_design && $key && is_user_logged_in() ) ? self::cart_save_url( $key ) : '',
                'preview'   => $is_design ? self::first_preview_url( $folder ) : '',
            );
        }

        /**
         * @return array Store API schema for the storelly cart-item extension.
         */
        public static function store_api_item_schema() {
            return array(
                'is_design' => array(
                    'description' => __( 'Whether this cart item carries a Storelly design.', 'storelly-product-builder-for-woocommerce' ),
                    'type'        => 'boolean',
                    'readonly'    => true,
                ),
                'save_url'  => array(
                    'description' => __( 'Nonce URL to save this design to the account.', 'storelly-product-builder-for-woocommerce' ),
                    'type'        => 'string',
                    'readonly'    => true,
                ),
                'preview'   => array(
                    'description' => __( 'Design preview image URL.', 'storelly-product-builder-for-woocommerce' ),
                    'type'        => 'string',
                    'readonly'    => true,
                ),
            );
        }

        // ------------------------------------------------------------------
        //  Saved designs listing (My Account tab)
        // ------------------------------------------------------------------

        public static function render_endpoint() {
            if ( ! is_user_logged_in() ) {
                wc_get_template( 'myaccount/form-login.php' );
                return;
            }
            self::print_notice();

            $designs = get_posts(
                array(
                    'post_type'   => self::POST_TYPE,
                    'post_status' => 'publish',
                    'author'      => get_current_user_id(),
                    'numberposts' => 100,
                    'orderby'     => 'modified',
                    'order'       => 'DESC',
                )
            );

            $use_shell = class_exists( 'SPBWC_Account_Shell' );
            if ( $use_shell ) {
                SPBWC_Account_Shell::open(
                    array(
                        'eyebrow'  => __( 'Design library', 'storelly-product-builder-for-woocommerce' ),
                        'title'    => __( 'Saved designs', 'storelly-product-builder-for-woocommerce' ),
                        'subtitle' => __( 'Reload a saved design straight into your cart.', 'storelly-product-builder-for-woocommerce' ),
                    )
                );
            } else {
                echo '<h2>' . esc_html__( 'Saved designs', 'storelly-product-builder-for-woocommerce' ) . '</h2>';
            }
            echo '<div class="spbwc-co spbwc-saved-designs">';
            if ( empty( $designs ) ) {
                if ( $use_shell ) {
                    $shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
                    SPBWC_Account_Shell::empty_state(
                        array(
                            'icon'      => '🎨',
                            'title'     => __( 'No saved designs yet', 'storelly-product-builder-for-woocommerce' ),
                            'text'      => __( 'Customise a product, then hit Save to keep your design here and reuse it anytime.', 'storelly-product-builder-for-woocommerce' ),
                            'cta_label' => __( 'Browse products', 'storelly-product-builder-for-woocommerce' ),
                            'cta_url'   => $shop_url,
                        )
                    );
                } else {
                    echo '<p>' . esc_html__( 'You have not saved any designs yet.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                }
                echo '</div>';
                if ( $use_shell ) {
                    SPBWC_Account_Shell::close();
                }
                return;
            }

            echo '<ul class="spbwc-saved-designs__list">';
            foreach ( $designs as $design ) {
                $preview    = (string) get_post_meta( $design->ID, self::META_PREVIEW, true );
                $product_id = (int) get_post_meta( $design->ID, self::META_PRODUCT, true );
                $product    = $product_id ? wc_get_product( $product_id ) : null;
                $can_load   = $product && $product->is_purchasable() && $product->is_in_stock();

                echo '<li class="spbwc-saved-designs__item">';
                if ( '' !== $preview ) {
                    echo '<img class="spbwc-saved-designs__thumb" src="' . esc_url( $preview ) . '" alt="" loading="lazy" />';
                }
                echo '<div class="spbwc-saved-designs__body">';
                echo '<span class="spbwc-saved-designs__title">' . esc_html( get_the_title( $design ) ) . '</span>';
                echo '<span class="spbwc-saved-designs__date">' . esc_html( get_the_date( get_option( 'date_format' ), $design ) ) . '</span>';
                echo '<div class="spbwc-saved-designs__actions">';

                // Load → add to cart.
                echo '<form method="post" class="spbwc-saved-designs__form">';
                wp_nonce_field( 'spbwc_saved_' . $design->ID, 'spbwc_saved_nonce' );
                echo '<input type="hidden" name="spbwc_saved_action" value="load" />';
                echo '<input type="hidden" name="design_id" value="' . esc_attr( $design->ID ) . '" />';
                if ( $can_load ) {
                    echo '<button type="submit" class="button">' . esc_html__( 'Add to cart', 'storelly-product-builder-for-woocommerce' ) . '</button>';
                } else {
                    echo '<span class="spbwc-saved-designs__unavailable">' . esc_html__( 'Product unavailable', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                }
                echo '</form>';

                // Delete.
                echo '<form method="post" class="spbwc-saved-designs__form">';
                wp_nonce_field( 'spbwc_saved_' . $design->ID, 'spbwc_saved_nonce' );
                echo '<input type="hidden" name="spbwc_saved_action" value="delete" />';
                echo '<input type="hidden" name="design_id" value="' . esc_attr( $design->ID ) . '" />';
                echo '<button type="submit" class="button spbwc-saved-designs__delete">' . esc_html__( 'Delete', 'storelly-product-builder-for-woocommerce' ) . '</button>';
                echo '</form>';

                echo '</div></div></li>';
            }
            echo '</ul></div>';
        }

        // ------------------------------------------------------------------
        //  Action dispatch
        // ------------------------------------------------------------------

        public static function handle_actions() {
            // Save (GET link from an order line item).
            if ( isset( $_GET['spbwc_save_design'] ) ) {
                self::handle_save();
                return;
            }
            // Save (GET link from a cart line item — D1).
            if ( isset( $_GET['spbwc_save_cart_design'] ) ) {
                self::handle_cart_save();
                return;
            }
            // Load / Delete (POST forms from the saved-designs tab).
            if ( isset( $_POST['spbwc_saved_action'] ) ) {
                self::handle_post_action();
            }
        }

        /** Clone a CART item's design into a new saved-design post (D1). */
        protected static function handle_cart_save() {
            $key   = isset( $_GET['cart_item_key'] ) ? sanitize_text_field( wp_unslash( $_GET['cart_item_key'] ) ) : '';
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( '' === $key || '' === $nonce ) {
                return;
            }
            if ( ! wp_verify_nonce( $nonce, 'spbwc_save_cart_design_' . $key ) ) {
                wp_die( esc_html__( 'Security error.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }
            if ( ! is_user_logged_in() ) {
                wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }
            $user_id = get_current_user_id();
            if ( ! function_exists( 'WC' ) ) {
                self::redirect_with_notice( 'error' );
            }
            // This runs on wp_loaded, which can fire before WooCommerce has populated the
            // cart from the session — force the load so get_cart_item() can find the row.
            if ( function_exists( 'wc_load_cart' ) ) {
                wc_load_cart();
            }
            if ( ! WC()->cart ) {
                self::redirect_with_notice( 'error' );
            }
            $cart_item = WC()->cart->get_cart_item( $key );
            if ( ! $cart_item || empty( $cart_item['pcpb_meta'] ) || empty( $cart_item['pcpb_meta']['pcpb'] ) ) {
                wp_die( esc_html__( 'Design not found.', 'storelly-product-builder-for-woocommerce' ), 404 );
            }

            $max = self::max_per_user();
            if ( $max > 0 && self::count_for_user( $user_id ) >= $max ) {
                self::redirect_with_notice( 'limit' );
            }

            $pm    = $cart_item['pcpb_meta'];
            $clone = SPBWC_Storelly_IO::spbwc_clone_design_folder( (string) $pm['pcpb'] );
            if ( '' === $clone ) {
                self::redirect_with_notice( 'error' );
            }

            $variation  = isset( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0;
            $product_id = $variation ? $variation : (int) $cart_item['product_id'];
            $product    = wc_get_product( $product_id );
            $title      = $product ? $product->get_name() : __( 'Saved design', 'storelly-product-builder-for-woocommerce' );

            $post_id = wp_insert_post( array(
                'post_type'   => self::POST_TYPE,
                'post_status' => 'publish',
                'post_author' => $user_id,
                'post_title'  => $title,
            ), true );
            if ( is_wp_error( $post_id ) || ! $post_id ) {
                SPBWC_Storelly_IO::spbwc_delete_folder( SPBWC_PB_CUSTOMER_DIR . '/' . $clone );
                self::redirect_with_notice( 'error' );
            }

            update_post_meta( $post_id, self::META_FOLDER, $clone );
            update_post_meta( $post_id, self::META_PRODUCT, $product_id );
            update_post_meta( $post_id, self::META_FIELD, isset( $pm['field'] ) ? $pm['field'] : '' );
            update_post_meta( $post_id, self::META_OPTIONS, isset( $pm['options'] ) ? $pm['options'] : '' );
            update_post_meta( $post_id, self::META_OPTPRICE, isset( $pm['option_price'] ) ? $pm['option_price'] : '' );
            update_post_meta( $post_id, self::META_PRICE, isset( $pm['original_price'] ) ? $pm['original_price'] : 0 );

            $preview = self::first_preview_url( $clone );
            if ( '' !== $preview ) {
                update_post_meta( $post_id, self::META_PREVIEW, $preview );
            }

            self::redirect_with_notice( 'saved' );
        }

        /** Clone an order item's design into a new saved-design post. */
        protected static function handle_save() {
            $order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
            $item_id  = isset( $_GET['item_id'] ) ? absint( wp_unslash( $_GET['item_id'] ) ) : 0;
            $nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! $order_id || ! $item_id || ! $nonce ) {
                return;
            }
            if ( ! wp_verify_nonce( $nonce, 'spbwc_save_design_' . $order_id . '_' . $item_id ) ) {
                wp_die( esc_html__( 'Security error.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }
            if ( ! is_user_logged_in() ) {
                wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }
            $user_id = get_current_user_id();
            $order   = wc_get_order( $order_id );
            if ( ! $order || (int) $order->get_customer_id() !== $user_id ) {
                wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }

            // Enforce optional per-user cap.
            $max = self::max_per_user();
            if ( $max > 0 && self::count_for_user( $user_id ) >= $max ) {
                self::redirect_with_notice( 'limit' );
            }

            $folder = (string) wc_get_order_item_meta( $item_id, '_pcpb_folder', true );
            $item   = $order->get_item( $item_id );
            if ( '' === $folder || ! $item ) {
                wp_die( esc_html__( 'Design not found.', 'storelly-product-builder-for-woocommerce' ), 404 );
            }

            $clone = SPBWC_Storelly_IO::spbwc_clone_design_folder( $folder );
            if ( '' === $clone ) {
                self::redirect_with_notice( 'error' );
            }

            $product_id = (int) $item->get_product_id();
            $variation  = (int) $item->get_variation_id();
            $product_id = $variation ? $variation : $product_id;

            $post_id = wp_insert_post(
                array(
                    'post_type'   => self::POST_TYPE,
                    'post_status' => 'publish',
                    'post_author' => $user_id,
                    /* translators: %s: product name. */
                    'post_title'  => $item->get_name(),
                ),
                true
            );
            if ( is_wp_error( $post_id ) || ! $post_id ) {
                // Roll back the clone we just made so we don't orphan a folder.
                SPBWC_Storelly_IO::spbwc_delete_folder( SPBWC_PB_CUSTOMER_DIR . '/' . $clone );
                self::redirect_with_notice( 'error' );
            }

            update_post_meta( $post_id, self::META_FOLDER, $clone );
            update_post_meta( $post_id, self::META_PRODUCT, $product_id );
            update_post_meta( $post_id, self::META_FIELD, wc_get_order_item_meta( $item_id, '_pcpb_field', true ) );
            update_post_meta( $post_id, self::META_OPTIONS, wc_get_order_item_meta( $item_id, '_pcpb_options', true ) );
            update_post_meta( $post_id, self::META_OPTPRICE, wc_get_order_item_meta( $item_id, '_pcpb_option_price', true ) );
            update_post_meta( $post_id, self::META_PRICE, wc_get_order_item_meta( $item_id, '_pcpb_original_price', true ) );

            $preview = self::first_preview_url( $clone );
            if ( '' !== $preview ) {
                update_post_meta( $post_id, self::META_PREVIEW, $preview );
            }

            self::redirect_with_notice( 'saved' );
        }

        /** Dispatch the Load / Delete POST actions. */
        protected static function handle_post_action() {
            $action    = sanitize_text_field( wp_unslash( $_POST['spbwc_saved_action'] ) );
            $design_id = isset( $_POST['design_id'] ) ? absint( wp_unslash( $_POST['design_id'] ) ) : 0;
            $nonce     = isset( $_POST['spbwc_saved_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['spbwc_saved_nonce'] ) ) : '';
            if ( ! $design_id || ! $nonce || ! wp_verify_nonce( $nonce, 'spbwc_saved_' . $design_id ) ) {
                return;
            }
            if ( ! is_user_logged_in() ) {
                wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }
            $design = get_post( $design_id );
            if ( ! $design || self::POST_TYPE !== $design->post_type || (int) $design->post_author !== get_current_user_id() ) {
                wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }

            if ( 'delete' === $action ) {
                self::delete_design( $design_id );
                self::redirect_with_notice( 'deleted' );
            } elseif ( 'load' === $action ) {
                self::load_design( $design_id );
            }
        }

        /** Delete a saved design and its cloned folder. */
        protected static function delete_design( $design_id ) {
            $folder = (string) get_post_meta( $design_id, self::META_FOLDER, true );
            if ( '' !== $folder && $folder === basename( $folder ) ) {
                SPBWC_Storelly_IO::spbwc_delete_folder( SPBWC_PB_CUSTOMER_DIR . '/' . $folder );
            }
            wp_delete_post( $design_id, true );
        }

        /** Clone the saved design into a fresh cart item and go to the cart. */
        protected static function load_design( $design_id ) {
            $product_id = (int) get_post_meta( $design_id, self::META_PRODUCT, true );
            $product    = $product_id ? wc_get_product( $product_id ) : null;
            if ( ! $product || ! $product->is_purchasable() ) {
                self::redirect_with_notice( 'error' );
            }

            $folder = (string) get_post_meta( $design_id, self::META_FOLDER, true );
            $clone  = '' !== $folder ? SPBWC_Storelly_IO::spbwc_clone_design_folder( $folder ) : '';

            $option_price   = get_post_meta( $design_id, self::META_OPTPRICE, true );
            $original_price  = (float) get_post_meta( $design_id, self::META_PRICE, true );
            $total_price    = is_array( $option_price ) && isset( $option_price['total_price'] ) ? (float) $option_price['total_price'] : 0;
            $discount_price = is_array( $option_price ) && isset( $option_price['discount_price'] ) ? (float) $option_price['discount_price'] : 0;

            $pcpb_meta = array(
                'option_price'   => $option_price,
                'field'          => get_post_meta( $design_id, self::META_FIELD, true ),
                'options'        => get_post_meta( $design_id, self::META_OPTIONS, true ),
                'original_price' => $original_price,
                'price'          => $original_price + $total_price - $discount_price,
            );
            if ( '' !== $clone ) {
                $pcpb_meta['pcpb'] = $clone;
                $preview = self::first_preview_url( $clone );
                if ( '' !== $preview ) {
                    if ( ! is_array( $pcpb_meta['option_price'] ) ) {
                        $pcpb_meta['option_price'] = array();
                    }
                    $pcpb_meta['option_price']['cart_image'] = $preview;
                }
            }

            $variation_id = $product->is_type( 'variation' ) ? $product_id : 0;
            $parent_id    = $variation_id ? $product->get_parent_id() : $product_id;

            if ( function_exists( 'WC' ) && WC()->cart ) {
                $added = WC()->cart->add_to_cart( $parent_id, 1, $variation_id, array(), array( 'pcpb_meta' => $pcpb_meta ) );
                if ( $added ) {
                    if ( function_exists( 'wc_add_notice' ) ) {
                        wc_add_notice( __( 'Design added to your cart.', 'storelly-product-builder-for-woocommerce' ) );
                    }
                    wp_safe_redirect( wc_get_cart_url() );
                    exit;
                }
                // Add failed (e.g. a third-party validation veto) — clean up the orphan clone.
                if ( '' !== $clone ) {
                    SPBWC_Storelly_IO::spbwc_delete_folder( SPBWC_PB_CUSTOMER_DIR . '/' . $clone );
                }
            }
            self::redirect_with_notice( 'error' );
        }

        // ------------------------------------------------------------------
        //  Helpers
        // ------------------------------------------------------------------

        protected static function count_for_user( $user_id ) {
            $ids = get_posts(
                array(
                    'post_type'   => self::POST_TYPE,
                    'post_status' => 'publish',
                    'author'      => $user_id,
                    'numberposts' => -1,
                    'fields'      => 'ids',
                )
            );
            return count( $ids );
        }

        protected static function first_preview_url( $folder ) {
            $images = SPBWC_Storelly_IO::spbwc_get_list_images( SPBWC_PB_CUSTOMER_DIR . '/' . $folder . '/preview', 1 );
            if ( empty( $images ) ) {
                return '';
            }
            sort( $images );
            return SPBWC_Storelly_IO::spbwc_convert_path_to_url( reset( $images ) );
        }

        protected static function redirect_with_notice( $code ) {
            $url = add_query_arg( 'spbwc_saved_msg', $code, wc_get_endpoint_url( self::ENDPOINT, '', wc_get_page_permalink( 'myaccount' ) ) );
            wp_safe_redirect( $url );
            exit;
        }

        protected static function print_notice() {
            if ( ! isset( $_GET['spbwc_saved_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash message, no state change.
                return;
            }
            $code = sanitize_key( wp_unslash( $_GET['spbwc_saved_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash message.
            $map  = array(
                'saved'   => array( 'success', __( 'Design saved to your account.', 'storelly-product-builder-for-woocommerce' ) ),
                'deleted' => array( 'success', __( 'Saved design deleted.', 'storelly-product-builder-for-woocommerce' ) ),
                'limit'   => array( 'error', __( 'You have reached your saved-design limit.', 'storelly-product-builder-for-woocommerce' ) ),
                'error'   => array( 'error', __( 'Something went wrong. Please try again.', 'storelly-product-builder-for-woocommerce' ) ),
            );
            if ( ! isset( $map[ $code ] ) ) {
                return;
            }
            $type = 'error' === $map[ $code ][0] ? 'woocommerce-error' : 'woocommerce-message';
            echo '<div class="' . esc_attr( $type ) . '" role="alert">' . esc_html( $map[ $code ][1] ) . '</div>';
        }
    }
}
