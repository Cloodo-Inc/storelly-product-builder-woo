<?php
/**
 * Quote PDF — hybrid renderer (P4.1).
 *
 * Two paths, picked automatically:
 *   1. LOCAL (always available, no external call, nothing bundled): a clean,
 *      self-contained printable HTML view of the quote that the merchant or
 *      buyer prints / saves as PDF from the browser. Output via a public,
 *      nonce-guarded query var so it carries no theme chrome.
 *   2. CLOUD (optional): when the Cloud2Print engine is opted in
 *      (`enable_cloud2print_api`), the `spbwc_quote_cloud_pdf` filter lets an
 *      adapter turn the same HTML into a real PDF file (attachable to email).
 *      The adapter is wired separately once the Cloud2Print document endpoint
 *      is confirmed; until then the local print view is used and nothing is
 *      sent off-site.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_PDF' ) ) {

    class SPBWC_Quote_PDF {

        const QUERY_VAR = 'spbwc_quote_print';

        public static function init() {
            add_action( 'init', array( __CLASS__, 'add_query_var' ) );
            add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
        }

        public static function add_query_var() {
            add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([0-9]+)' );
        }

        /**
         * Nonce-signed URL to the printable quote view.
         *
         * @param int $quote_id Quote post ID.
         * @return string
         */
        public static function print_url( $quote_id ) {
            $quote_id = absint( $quote_id );
            return add_query_arg(
                array(
                    self::QUERY_VAR => $quote_id,
                    '_wpnonce'      => wp_create_nonce( 'spbwc_quote_print_' . $quote_id ),
                ),
                home_url( '/' )
            );
        }

        /** Whether the Cloud2Print engine is opted in. */
        public static function cloud_enabled() {
            if ( class_exists( 'SPBWC_Order_PDF' ) ) {
                return SPBWC_Order_PDF::is_enabled();
            }
            $settings = get_option( 'spbwc_pb_settings', array() );
            return isset( $settings['enable_cloud2print_api'] ) && 'yes' === $settings['enable_cloud2print_api'];
        }

        /**
         * Resolve a real PDF file path for the quote when a cloud adapter is
         * available. Returns '' when no cloud PDF was produced (use the local
         * print view instead).
         *
         * Adapters hook `spbwc_quote_cloud_pdf`:
         *   add_filter( 'spbwc_quote_cloud_pdf', function ( $path, $quote_id, $html ) {
         *       // POST $html to the Cloud2Print document endpoint, save the
         *       // returned PDF, return its absolute path.
         *       return $path;
         *   }, 10, 3 );
         *
         * @param int $quote_id Quote post ID.
         * @return string Absolute file path or ''.
         */
        public static function cloud_pdf_path( $quote_id ) {
            if ( ! self::cloud_enabled() ) {
                return '';
            }
            $path = apply_filters( 'spbwc_quote_cloud_pdf', '', absint( $quote_id ), self::quote_html( $quote_id ) );
            return ( is_string( $path ) && '' !== $path && file_exists( $path ) ) ? $path : '';
        }

        /**
         * Email attachments for a quote (the cloud PDF when present).
         *
         * @param int $quote_id Quote post ID.
         * @return string[] Absolute file paths.
         */
        public static function email_attachments( $quote_id ) {
            $path = self::cloud_pdf_path( $quote_id );
            return $path ? array( $path ) : array();
        }

        /** Output the standalone printable view, then exit. */
        public static function maybe_render() {
            $quote_id = isset( $_GET[ self::QUERY_VAR ] ) ? absint( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : 0;
            if ( ! $quote_id ) {
                return;
            }
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! $nonce || ! wp_verify_nonce( $nonce, 'spbwc_quote_print_' . $quote_id ) ) {
                wp_die( esc_html__( 'Invalid or expired link.', 'storelly-product-builder-for-woocommerce' ), '', array( 'response' => 403 ) );
            }
            $post = get_post( $quote_id );
            if ( ! $post || SPBWC_Quote::POST_TYPE !== $post->post_type ) {
                wp_die( esc_html__( 'Quote not found.', 'storelly-product-builder-for-woocommerce' ), '', array( 'response' => 404 ) );
            }
            $is_owner = is_user_logged_in() && (int) $post->post_author === get_current_user_id();
            if ( ! $is_owner && ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( esc_html__( 'You do not have permission to view this quote.', 'storelly-product-builder-for-woocommerce' ), '', array( 'response' => 403 ) );
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- auto-print is a cosmetic flag.
            $auto = ! isset( $_GET['noprint'] );
            header( 'Content-Type: text/html; charset=utf-8' );
            echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
            echo '<title>' . esc_html( get_post_meta( $quote_id, SPBWC_Quote::META_NUMBER, true ) ) . '</title>';
            echo '<style>' . self::print_css() . '</style></head><body>';
            echo self::quote_html( $quote_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
            echo '<div class="spbwc-qp-actions no-print"><button onclick="window.print()">' . esc_html__( 'Print / Save as PDF', 'storelly-product-builder-for-woocommerce' ) . '</button></div>';
            if ( $auto ) {
                echo '<script>window.addEventListener("load",function(){setTimeout(function(){window.print();},300);});</script>';
            }
            echo '</body></html>';
            exit;
        }

        /** Self-contained print stylesheet. */
        protected static function print_css() {
            return '
            *{box-sizing:border-box}body{font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937;max-width:760px;margin:32px auto;padding:0 24px;font-size:14px;line-height:1.5}
            .spbwc-qp-head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #111827;padding-bottom:16px;margin-bottom:20px}
            .spbwc-qp-head h1{font-size:22px;margin:0 0 4px}.spbwc-qp-num{color:#6b7280;font-size:13px}
            .spbwc-qp-meta{margin:0 0 20px}.spbwc-qp-meta div{margin:2px 0}
            table.spbwc-qp{width:100%;border-collapse:collapse;margin:0 0 16px}
            table.spbwc-qp th,table.spbwc-qp td{padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:left}
            table.spbwc-qp th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280}
            .num{text-align:right;white-space:nowrap}
            .spbwc-qp-totals{margin-left:auto;width:300px}
            .spbwc-qp-totals div{display:flex;justify-content:space-between;padding:4px 0;color:#6b7280}
            .spbwc-qp-totals .grand{border-top:2px solid #e5e7eb;margin-top:6px;padding-top:10px;font-size:17px;font-weight:700;color:#111827}
            .spbwc-qp-terms{margin-top:18px;font-size:13px;color:#374151}.spbwc-qp-terms strong{color:#111827}
            .spbwc-qp-actions{margin-top:28px;text-align:center}
            .spbwc-qp-actions button{padding:10px 20px;border:0;border-radius:8px;background:#1d4ed8;color:#fff;font-size:14px;cursor:pointer}
            @media print{.no-print{display:none!important}body{margin:0}}';
        }

        /**
         * Build the quote document HTML (shared by local print + cloud render).
         *
         * @param int $quote_id Quote post ID.
         * @return string
         */
        public static function quote_html( $quote_id ) {
            $quote_id = absint( $quote_id );
            $number   = get_post_meta( $quote_id, SPBWC_Quote::META_NUMBER, true );
            $request  = get_post_meta( $quote_id, SPBWC_Quote::META_REQUEST, true );
            $request  = is_array( $request ) ? $request : array();
            $lines    = SPBWC_Quote::get_lines( $quote_id );
            $totals   = SPBWC_Quote::get_totals( $quote_id );
            $valid    = (string) get_post_meta( $quote_id, SPBWC_Quote::META_VALID_UNTIL, true );
            $note     = (string) get_post_meta( $quote_id, SPBWC_Quote::META_CUSTOMER_NOTE, true );
            $cur      = array( 'currency' => isset( $totals['currency'] ) && $totals['currency'] ? $totals['currency'] : get_woocommerce_currency() );
            $customer = class_exists( 'SPBWC_Quotes_List_Table' ) ? SPBWC_Quotes_List_Table::derive_customer( $request, (int) get_post_field( 'post_author', $quote_id ) ) : '';

            $out  = '<div class="spbwc-qp-head"><div><h1>' . esc_html( get_bloginfo( 'name' ) ) . '</h1>';
            $out .= '<div class="spbwc-qp-num">' . esc_html__( 'Quote', 'storelly-product-builder-for-woocommerce' ) . ' ' . esc_html( $number ? $number : '#' . $quote_id ) . '</div></div>';
            $out .= '<div class="spbwc-qp-num">' . esc_html( get_the_date( get_option( 'date_format' ), $quote_id ) ) . '</div></div>';

            $out .= '<div class="spbwc-qp-meta">';
            if ( $customer ) {
                $out .= '<div><strong>' . esc_html__( 'Prepared for:', 'storelly-product-builder-for-woocommerce' ) . '</strong> ' . esc_html( $customer ) . '</div>';
            }
            if ( ! empty( $request['email'] ) ) {
                $out .= '<div>' . esc_html( $request['email'] ) . '</div>';
            }
            $out .= '</div>';

            if ( ! empty( $lines ) ) {
                $out .= '<table class="spbwc-qp"><thead><tr><th>' . esc_html__( 'Item', 'storelly-product-builder-for-woocommerce' ) . '</th><th class="num">' . esc_html__( 'Qty', 'storelly-product-builder-for-woocommerce' ) . '</th><th class="num">' . esc_html__( 'Unit', 'storelly-product-builder-for-woocommerce' ) . '</th><th class="num">' . esc_html__( 'Total', 'storelly-product-builder-for-woocommerce' ) . '</th></tr></thead><tbody>';
                foreach ( $lines as $l ) {
                    $out .= '<tr><td>' . esc_html( isset( $l['label'] ) ? $l['label'] : '' );
                    if ( ! empty( $l['desc'] ) ) {
                        $out .= '<br><small style="color:#6b7280">' . esc_html( $l['desc'] ) . '</small>';
                    }
                    $out .= '</td><td class="num">' . esc_html( isset( $l['qty'] ) ? (string) ( 0 + $l['qty'] ) : '' ) . '</td>';
                    $out .= '<td class="num">' . wp_kses_post( wc_price( isset( $l['unit_price'] ) ? (float) $l['unit_price'] : 0, $cur ) ) . '</td>';
                    $out .= '<td class="num">' . wp_kses_post( wc_price( isset( $l['line_total'] ) ? (float) $l['line_total'] : 0, $cur ) ) . '</td></tr>';
                }
                $out .= '</tbody></table>';

                $out .= '<div class="spbwc-qp-totals">';
                $out .= '<div><span>' . esc_html__( 'Subtotal', 'storelly-product-builder-for-woocommerce' ) . '</span><span>' . wp_kses_post( wc_price( isset( $totals['subtotal'] ) ? (float) $totals['subtotal'] : 0, $cur ) ) . '</span></div>';
                if ( ! empty( $totals['discount'] ) ) {
                    $out .= '<div><span>' . esc_html__( 'Discount', 'storelly-product-builder-for-woocommerce' ) . '</span><span>-' . wp_kses_post( wc_price( (float) $totals['discount'], $cur ) ) . '</span></div>';
                }
                if ( ! empty( $totals['tax'] ) ) {
                    $out .= '<div><span>' . esc_html__( 'Tax', 'storelly-product-builder-for-woocommerce' ) . '</span><span>' . wp_kses_post( wc_price( (float) $totals['tax'], $cur ) ) . '</span></div>';
                }
                $out .= '<div class="grand"><span>' . esc_html__( 'Total', 'storelly-product-builder-for-woocommerce' ) . '</span><span>' . wp_kses_post( wc_price( isset( $totals['total'] ) ? (float) $totals['total'] : 0, $cur ) ) . '</span></div>';
                $out .= '</div>';
            }

            $out .= '<div class="spbwc-qp-terms">';
            if ( '' !== $valid ) {
                $out .= '<div><strong>' . esc_html__( 'Valid until:', 'storelly-product-builder-for-woocommerce' ) . '</strong> ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $valid ) ) ) . '</div>';
            }
            if ( '' !== $note ) {
                $out .= '<div style="margin-top:6px">' . nl2br( esc_html( $note ) ) . '</div>';
            }
            $out .= '</div>';

            return $out;
        }
    }
}
