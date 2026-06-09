<?php
/**
 * Buyer-facing design downloads (My Account).
 *
 * Lets the buyer download a PREVIEW of the design they ordered from the My Account order
 * detail page, at any time after the order exists. Only the low-res preview images are
 * served — the full print files under customer-pdfs/ stay admin-only.
 *
 * Every request is nonce-protected and ownership-checked against the logged-in customer.
 * The zip is streamed and then deleted so no publicly reachable file lingers.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Buyer_Downloads' ) ) {

    class SPBWC_Buyer_Downloads {

        /** Query var / nonce action base for the preview download. */
        const ACTION = 'spbwc_dl_preview';

        public static function init() {
            add_action( 'init', array( __CLASS__, 'maybe_handle_download' ) );
            add_action( 'woocommerce_order_item_meta_start', array( __CLASS__, 'render_order_thumbnail' ), 10, 4 );
            add_action( 'woocommerce_order_item_meta_end', array( __CLASS__, 'render_download_link' ), 10, 4 );
            add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        }

        /**
         * Load the shared Custom Order stylesheet wherever its surfaces appear:
         * My Account (order detail, Saved designs tab) and the order-received page.
         */
        public static function enqueue_assets() {
            $is_account = function_exists( 'is_account_page' ) && is_account_page();
            $is_thanks  = function_exists( 'is_order_received_page' ) && is_order_received_page();
            $is_cart    = function_exists( 'is_cart' ) && is_cart();
            if ( ! $is_account && ! $is_thanks && ! $is_cart ) {
                return;
            }
            // Shared storefront design tokens load first; custom-order.css consumes them.
            // Version by filemtime (same pattern as the admin custom-order enqueue) so
            // CSS edits cache-bust immediately for returning users, not only on a
            // plugin version bump.
            $co_css = SPBWC_PB_ASSETS_DIR . 'css/custom-order.css';
            $co_ver = file_exists( $co_css ) ? filemtime( $co_css ) : SPBWC_PB_VERSION;
            wp_enqueue_style( 'spbwc-tokens-storefront', SPBWC_PB_CSS_URL . '_tokens-storefront.css', array(), SPBWC_PB_VERSION );
            wp_enqueue_style( 'spbwc-custom-order', SPBWC_PB_CSS_URL . 'custom-order.css', array( 'spbwc-tokens-storefront' ), $co_ver );
            // Progressive-enhancement confirm() for the saved-designs delete button (D4).
            if ( $is_account ) {
                wp_enqueue_script( 'spbwc-custom-order', SPBWC_PB_JS_URL . 'custom-order.js', array(), SPBWC_PB_VERSION, true );
                wp_localize_script(
                    'spbwc-custom-order',
                    'spbwcCustomOrder',
                    array( 'confirmDelete' => __( 'Delete this saved design? This cannot be undone.', 'storelly-product-builder-for-woocommerce' ) )
                );
            }
            // Cart *block* "Save design" button (option B — wp.data, no build). Only load
            // when the Cart page actually uses the block; the classic shortcode uses the PHP link.
            if ( $is_cart && self::cart_uses_block() ) {
                wp_enqueue_script( 'spbwc-cart-block-save', SPBWC_PB_JS_URL . 'cart-block-save.js', array( 'wp-data' ), SPBWC_PB_VERSION, true );
                wp_localize_script(
                    'spbwc-cart-block-save',
                    'spbwcCartSave',
                    array(
                        'loggedIn'  => is_user_logged_in(),
                        'loginUrl'  => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url(),
                        'i18nSave'  => __( 'Save design to my account', 'storelly-product-builder-for-woocommerce' ),
                        'i18nLogin' => __( 'Log in to save this design', 'storelly-product-builder-for-woocommerce' ),
                    )
                );
            }
        }

        /** True when the Cart page is rendered with the WooCommerce Cart block. */
        protected static function cart_uses_block() {
            $cart_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'cart' ) : 0;
            if ( $cart_page_id > 0 && function_exists( 'has_block' ) && has_block( 'woocommerce/cart', $cart_page_id ) ) {
                return true;
            }
            return false;
        }

        /**
         * Sorted preview images for an order item, but only when the current logged-in
         * user owns the order and the item carries a design folder. Empty array otherwise.
         *
         * @param int           $item_id Order item ID.
         * @param WC_Order_Item $item    Order item.
         * @param WC_Order      $order   Order.
         * @return array Absolute image paths (sorted), or [].
         */
        protected static function owned_item_preview_images( $item_id, $item, $order ) {
            if ( ! is_user_logged_in() || ! ( $order instanceof WC_Order ) || ! is_callable( array( $item, 'get_meta' ) ) ) {
                return array();
            }
            if ( (int) $order->get_customer_id() !== get_current_user_id() ) {
                return array();
            }
            $folder = (string) $item->get_meta( '_pcpb_folder' );
            if ( '' === $folder ) {
                return array();
            }
            $images = SPBWC_Storelly_IO::spbwc_get_list_images( SPBWC_PB_CUSTOMER_DIR . '/' . $folder . '/preview', 1 );
            if ( empty( $images ) ) {
                return array();
            }
            sort( $images );
            return $images;
        }

        /**
         * Show the buyer's actual design preview as a thumbnail on the order (D2).
         * The default storefront order items table renders no image at all.
         */
        public static function render_order_thumbnail( $item_id, $item, $order, $plain_text = false ) {
            if ( $plain_text ) {
                return;
            }
            $images = self::owned_item_preview_images( $item_id, $item, $order );
            if ( empty( $images ) ) {
                return;
            }
            $src = SPBWC_Storelly_IO::spbwc_convert_path_to_url( $images[0] );
            echo '<span class="spbwc-co-orderthumb"><img src="' . esc_url( $src ) . '" alt="' . esc_attr__( 'Your design', 'storelly-product-builder-for-woocommerce' ) . '" loading="lazy" /></span>';
        }

        /**
         * Render "View" + "Download" actions under a custom line item (D3).
         * View opens the (public) preview image; Download streams a PNG (single view)
         * or a zip (multiple views).
         */
        public static function render_download_link( $item_id, $item, $order, $plain_text = false ) {
            if ( $plain_text ) {
                return;
            }
            $images = self::owned_item_preview_images( $item_id, $item, $order );
            if ( empty( $images ) ) {
                return;
            }

            $view_url = SPBWC_Storelly_IO::spbwc_convert_path_to_url( $images[0] );
            $dl_url   = wp_nonce_url(
                add_query_arg(
                    array(
                        self::ACTION => 1,
                        'order_id'   => $order->get_id(),
                        'item_id'    => (int) $item_id,
                    ),
                    home_url( '/' )
                ),
                self::ACTION . '_' . $order->get_id() . '_' . (int) $item_id
            );

            $eye = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
            $dl  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';

            echo '<span class="spbwc-co-actions-label">' . esc_html__( 'Your design', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            echo '<p class="spbwc-co-action spbwc-buyer-preview-dl">';
            echo '<a class="spbwc-co-chip spbwc-co-chip--ghost" href="' . esc_url( $view_url ) . '" target="_blank" rel="noopener">'
                . $eye // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG icon.
                . '<span>' . esc_html__( 'View design', 'storelly-product-builder-for-woocommerce' ) . '</span></a> ';
            echo '<a class="spbwc-co-chip" href="' . esc_url( $dl_url ) . '">'
                . $dl // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG icon.
                . '<span>' . esc_html__( 'Download', 'storelly-product-builder-for-woocommerce' ) . '</span></a>';
            echo '</p>';
        }

        /**
         * Handle a preview download request (front-end GET on init).
         */
        public static function maybe_handle_download() {
            if ( ! isset( $_GET[ self::ACTION ] ) ) {
                return;
            }
            $order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
            $item_id  = isset( $_GET['item_id'] ) ? absint( wp_unslash( $_GET['item_id'] ) ) : 0;
            $nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

            if ( ! $order_id || ! $item_id || ! $nonce ) {
                return;
            }
            if ( ! wp_verify_nonce( $nonce, self::ACTION . '_' . $order_id . '_' . $item_id ) ) {
                wp_die( esc_html__( 'Security error.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }
            if ( ! is_user_logged_in() ) {
                wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }

            $order = wc_get_order( $order_id );
            if ( ! $order || (int) $order->get_customer_id() !== get_current_user_id() ) {
                wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }

            // The item must belong to this order, and carry a design folder.
            $belongs = false;
            foreach ( $order->get_items() as $loop_id => $loop_item ) {
                if ( (int) $loop_id === $item_id ) {
                    $belongs = true;
                    break;
                }
            }
            $folder = (string) wc_get_order_item_meta( $item_id, '_pcpb_folder', true );
            if ( ! $belongs || '' === $folder ) {
                wp_die( esc_html__( 'Design preview not found.', 'storelly-product-builder-for-woocommerce' ), 404 );
            }

            $preview_path = SPBWC_PB_CUSTOMER_DIR . '/' . $folder . '/preview';
            $images       = SPBWC_Storelly_IO::spbwc_get_list_images( $preview_path, 1 );
            if ( empty( $images ) ) {
                wp_die( esc_html__( 'Design preview not found.', 'storelly-product-builder-for-woocommerce' ), 404 );
            }
            sort( $images );

            // Count the download for the Overview activity stats (M14).
            update_option( 'spbwc_preview_download_count', (int) get_option( 'spbwc_preview_download_count', 0 ) + 1, false );
            wc_update_order_item_meta( $item_id, '_pcpb_preview_downloads', (int) wc_get_order_item_meta( $item_id, '_pcpb_preview_downloads', true ) + 1 );

            // Single view → stream the PNG directly (no needless 1-file zip). Multiple
            // views → bundle a zip. The PNG is the real preview file, so don't delete it.
            if ( count( $images ) === 1 ) {
                $ext  = pathinfo( $images[0], PATHINFO_EXTENSION );
                $name = 'preview_' . $order_id . '_' . $item_id . '.' . ( $ext ? $ext : 'png' );
                self::stream_and_exit( $images[0], $name, false );
            }

            $zip_name = 'preview_' . $order_id . '_' . $item_id . '.zip';
            $zip_path = SPBWC_PB_DATA_DIR . '/download/' . $zip_name;
            if ( ! SPBWC_Storelly_PB_Util::spbwc_zip_files( $images, $zip_path ) ) {
                wp_die( esc_html__( 'Could not prepare the download.', 'storelly-product-builder-for-woocommerce' ), 500 );
            }

            self::stream_and_exit( $zip_path, $zip_name, true );
        }

        /**
         * Stream a file as an attachment, delete the temp copy, then exit.
         *
         * @param string $path          Absolute file path.
         * @param string $download_name Filename presented to the browser.
         */
        protected static function stream_and_exit( $path, $download_name, $delete_after = true ) {
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            global $wp_filesystem;
            if ( ! $wp_filesystem ) {
                WP_Filesystem();
            }
            $data = $wp_filesystem ? $wp_filesystem->get_contents( $path ) : false;
            if ( $delete_after ) {
                wp_delete_file( $path ); // Temp zip — don't leave a publicly reachable copy.
            }

            if ( false === $data ) {
                wp_die( esc_html__( 'Could not read the download.', 'storelly-product-builder-for-woocommerce' ), 500 );
            }

            $ext   = strtolower( pathinfo( $download_name, PATHINFO_EXTENSION ) );
            $types = array( 'zip' => 'application/zip', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml' );
            $ctype = isset( $types[ $ext ] ) ? $types[ $ext ] : 'application/octet-stream';

            nocache_headers();
            header( 'Content-Type: ' . $ctype );
            header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $download_name ) . '"' );
            header( 'Content-Length: ' . strlen( $data ) );
            echo $data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw binary file stream; escaping would corrupt it.
            exit;
        }
    }
}
