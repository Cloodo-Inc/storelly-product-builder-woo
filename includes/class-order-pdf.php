<?php
/**
 * Order print-PDF generation (decoupled + queued).
 *
 * Renders the print-ready PDF for every builder line item of an order and ties it to the
 * order, independent of the unrelated launcher "API sync" toggle. Generation runs in the
 * background via Action Scheduler (which ships with WooCommerce) so it never adds latency to
 * checkout; on this dev box WP-Cron is disabled and a Windows task drives the queue
 * (see project memory localhost_wpcron_fix).
 *
 * Gated by the dedicated "Enable cloud PDF rendering" opt-in (`enable_cloud2print_api`),
 * because the underlying engine calls the external Cloud2Print service.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Order_PDF' ) ) {

    class SPBWC_Order_PDF {

        /** Action Scheduler hook that renders an order's PDFs. */
        const GENERATE_HOOK = 'spbwc_generate_order_pdfs';

        /** Order meta flag marking that generation has already been queued. */
        const SCHEDULED_FLAG = '_pcpb_pdf_scheduled';

        /** Action Scheduler group. */
        const GROUP = 'spbwc-order-pdf';

        public static function init() {
            // payment_complete covers gateways that capture immediately; the status hooks
            // cover offline gateways (BACS/cheque/COD) that move to processing/completed
            // without ever firing payment_complete. The per-order flag de-dupes overlaps.
            add_action( 'woocommerce_payment_complete', array( __CLASS__, 'maybe_schedule' ) );
            add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'maybe_schedule' ) );
            add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'maybe_schedule' ) );
            add_action( self::GENERATE_HOOK, array( __CLASS__, 'generate' ) );

            // Housekeeping (M5): prune an order's design folders when the order is
            // PERMANENTLY deleted (not on trash, which is reversible). HPOS fires
            // woocommerce_before_delete_order; legacy CPT orders fire before_delete_post.
            add_action( 'woocommerce_before_delete_order', array( __CLASS__, 'cleanup_order_folders' ) );
            add_action( 'before_delete_post', array( __CLASS__, 'cleanup_legacy_order_folders' ) );

            // Admin: re-trigger PDF generation for an order (M5).
            add_action( 'admin_init', array( __CLASS__, 'maybe_regenerate' ) );
        }

        /** Nonce URL that re-queues PDF generation for an order (admin metabox). */
        public static function regenerate_url( $order_id ) {
            return wp_nonce_url(
                add_query_arg( 'spbwc_regen_pdf', (int) $order_id ),
                'spbwc_regen_pdf_' . (int) $order_id
            );
        }

        /** Handle the admin "Regenerate print PDFs" action. */
        public static function maybe_regenerate() {
            if ( ! isset( $_GET['spbwc_regen_pdf'] ) ) {
                return;
            }
            $order_id = absint( wp_unslash( $_GET['spbwc_regen_pdf'] ) );
            $nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! $order_id || ! $nonce || ! wp_verify_nonce( $nonce, 'spbwc_regen_pdf_' . $order_id ) ) {
                return;
            }
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }
            // Re-run generation now (admin-initiated, expects an immediate result).
            self::generate( $order_id );
            wp_safe_redirect( remove_query_arg( array( 'spbwc_regen_pdf', '_wpnonce' ) ) );
            exit;
        }

        /**
         * Delete the design folders owned by an order being permanently removed.
         * Each order owns its own copy-on-write folder, so this never touches another
         * order's or a saved design's clone.
         *
         * @param int $order_id Order ID.
         */
        public static function cleanup_order_folders( $order_id ) {
            $order = wc_get_order( absint( $order_id ) );
            if ( ! $order ) {
                return;
            }
            foreach ( $order->get_items() as $item ) {
                $folder = is_callable( array( $item, 'get_meta' ) ) ? (string) $item->get_meta( '_pcpb_folder' ) : '';
                if ( '' !== $folder && $folder === basename( $folder ) ) {
                    SPBWC_Storelly_IO::spbwc_delete_folder( SPBWC_PB_CUSTOMER_DIR . '/' . $folder );
                }
            }
        }

        /**
         * Legacy (non-HPOS) order permanent delete bridge.
         *
         * @param int $post_id Post ID being deleted.
         */
        public static function cleanup_legacy_order_folders( $post_id ) {
            if ( 'shop_order' === get_post_type( $post_id ) ) {
                self::cleanup_order_folders( $post_id );
            }
        }

        /** Whether the cloud PDF engine is opted in. */
        public static function is_enabled() {
            $settings = get_option( 'spbwc_pb_settings', array() );
            return isset( $settings['enable_cloud2print_api'] ) && 'yes' === $settings['enable_cloud2print_api'];
        }

        /**
         * Queue PDF generation for an order once it is paid/processing.
         *
         * @param int $order_id Order ID.
         */
        public static function maybe_schedule( $order_id ) {
            $order_id = absint( $order_id );
            if ( ! $order_id || ! self::is_enabled() ) {
                return;
            }
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return;
            }
            // Already queued/generated for this order — never enqueue twice.
            if ( $order->get_meta( self::SCHEDULED_FLAG ) ) {
                return;
            }
            // Only act on orders that actually carry a builder design.
            if ( ! self::order_has_design( $order ) ) {
                return;
            }

            $order->update_meta_data( self::SCHEDULED_FLAG, current_time( 'mysql' ) );
            $order->save();

            if ( function_exists( 'as_enqueue_async_action' ) ) {
                as_enqueue_async_action( self::GENERATE_HOOK, array( $order_id ), self::GROUP );
            } else {
                // No Action Scheduler available — render inline as a last resort.
                self::generate( $order_id );
            }
        }

        /**
         * True when at least one line item references a design folder.
         *
         * @param WC_Order $order Order object.
         * @return bool
         */
        protected static function order_has_design( $order ) {
            foreach ( $order->get_items() as $item ) {
                if ( '' !== (string) $item->get_meta( '_pcpb_folder' ) ) {
                    return true;
                }
            }
            return false;
        }

        /**
         * Render print PDFs for every builder line item of an order.
         *
         * Records a per-item status (`_pcpb_pdf_status` = done|failed) so the admin can spot
         * and re-trigger failed renders. Hidden from order screens via
         * `woocommerce_hidden_order_itemmeta`.
         *
         * @param int $order_id Order ID.
         */
        public static function generate( $order_id ) {
            $order_id = absint( $order_id );
            if ( ! $order_id || ! self::is_enabled() ) {
                return;
            }
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return;
            }

            $any_done = false;
            foreach ( $order->get_items() as $item_id => $item ) {
                $folder = (string) $item->get_meta( '_pcpb_folder' );
                if ( '' === $folder ) {
                    continue;
                }
                $design_path = SPBWC_PB_CUSTOMER_DIR . '/' . $folder;
                if ( ! is_dir( $design_path ) ) {
                    wc_update_order_item_meta( $item_id, '_pcpb_pdf_status', 'failed' );
                    continue;
                }

                SPBWC_Storelly_Export_PDF::spbwc_export_pdf( $folder );

                $pdf_path = $design_path . '/customer-pdfs';
                $pdfs     = SPBWC_Storelly_IO::spbwc_get_list_files_by_type( $pdf_path, 'pdf', 1 );
                $done     = ! empty( $pdfs );
                $any_done = $any_done || $done;
                wc_update_order_item_meta( $item_id, '_pcpb_pdf_status', $done ? 'done' : 'failed' );
            }

            // Notify the customer that the print-ready proof is available (E6a).
            // Fired once per order; the email itself is opt-out via WC settings.
            if ( $any_done && ! $order->get_meta( '_spbwc_proof_email_sent' ) ) {
                $order->update_meta_data( '_spbwc_proof_email_sent', current_time( 'mysql' ) );
                $order->save();
                do_action( 'spbwc_order_pdf_ready_notification', $order_id );
            }
        }
    }
}
