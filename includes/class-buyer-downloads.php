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
            add_action( 'woocommerce_order_item_meta_end', array( __CLASS__, 'render_download_link' ), 10, 4 );
        }

        /**
         * Render a "Download design preview" link under a custom line item.
         *
         * @param int           $item_id    Order item ID.
         * @param WC_Order_Item $item       Order item.
         * @param WC_Order      $order      Order.
         * @param bool          $plain_text Whether this is the plain-text email context.
         */
        public static function render_download_link( $item_id, $item, $order, $plain_text = false ) {
            if ( $plain_text ) {
                return; // Links are only meaningful in the HTML My Account / order views.
            }
            if ( ! is_user_logged_in() || ! ( $order instanceof WC_Order ) || ! is_callable( array( $item, 'get_meta' ) ) ) {
                return;
            }
            if ( (int) $order->get_customer_id() !== get_current_user_id() ) {
                return;
            }
            $folder = (string) $item->get_meta( '_pcpb_folder' );
            if ( '' === $folder ) {
                return;
            }
            $preview_path = SPBWC_PB_CUSTOMER_DIR . '/' . $folder . '/preview';
            $images       = SPBWC_Storelly_IO::spbwc_get_list_images( $preview_path, 1 );
            if ( empty( $images ) ) {
                return;
            }

            $url = wp_nonce_url(
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

            echo '<p class="spbwc-buyer-preview-dl"><a href="' . esc_url( $url ) . '">'
                . esc_html__( 'Download design preview', 'storelly-product-builder-for-woocommerce' )
                . '</a></p>';
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

            $zip_name = 'preview_' . $order_id . '_' . $item_id . '.zip';
            $zip_path = SPBWC_PB_DATA_DIR . '/download/' . $zip_name;
            if ( ! SPBWC_Storelly_PB_Util::spbwc_zip_files( $images, $zip_path ) ) {
                wp_die( esc_html__( 'Could not prepare the download.', 'storelly-product-builder-for-woocommerce' ), 500 );
            }

            self::stream_and_exit( $zip_path, $zip_name );
        }

        /**
         * Stream a file as an attachment, delete the temp copy, then exit.
         *
         * @param string $path          Absolute file path.
         * @param string $download_name Filename presented to the browser.
         */
        protected static function stream_and_exit( $path, $download_name ) {
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            global $wp_filesystem;
            if ( ! $wp_filesystem ) {
                WP_Filesystem();
            }
            $data = $wp_filesystem ? $wp_filesystem->get_contents( $path ) : false;
            wp_delete_file( $path ); // Don't leave a publicly reachable copy behind.

            if ( false === $data ) {
                wp_die( esc_html__( 'Could not read the download.', 'storelly-product-builder-for-woocommerce' ), 500 );
            }

            nocache_headers();
            header( 'Content-Type: application/zip' );
            header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $download_name ) . '"' );
            header( 'Content-Length: ' . strlen( $data ) );
            echo $data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw binary zip stream; escaping would corrupt it.
            exit;
        }
    }
}
