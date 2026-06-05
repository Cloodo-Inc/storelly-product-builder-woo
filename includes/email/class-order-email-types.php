<?php
/**
 * Custom Order emails (E6a).
 *
 *  - SPBWC_Email_Order_Received: local confirmation when a customer places an
 *    order that carries a Storelly custom design. Sent regardless of cloud.
 *  - SPBWC_Email_Order_Proof: sent when the print-ready proof PDF has rendered
 *    (cloud feature), attaching the PDF(s) for the customer to download.
 *
 * Required from inside `woocommerce_email_classes`, so WC_Email exists.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
    return;
}

require_once SPBWC_PB_PLUGIN_DIR . 'includes/email/class-email-base.php';

if ( ! class_exists( 'SPBWC_Order_Email_Base' ) && class_exists( 'SPBWC_Email_Base' ) ) {

    abstract class SPBWC_Order_Email_Base extends SPBWC_Email_Base {

        protected function load_order( $order_id ) {
            $this->object = wc_get_order( absint( $order_id ) );
            if ( $this->object ) {
                $this->placeholders['{order_number}'] = $this->object->get_order_number();
                $this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
            }
        }

        protected function customer_recipient() {
            return $this->object ? sanitize_email( $this->object->get_billing_email() ) : '';
        }

        protected function order_dispatch( $order_id ) {
            $this->setup_locale();
            $this->load_order( $order_id );
            if ( $this->object ) {
                $this->recipient = $this->customer_recipient();
                if ( $this->is_enabled() && $this->get_recipient() ) {
                    $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
                }
            }
            $this->restore_locale();
        }
    }
}

/* ── Custom order received → customer (local) ─────────────────────────── */
if ( ! class_exists( 'SPBWC_Email_Order_Received' ) && class_exists( 'SPBWC_Order_Email_Base' ) ) {
    class SPBWC_Email_Order_Received extends SPBWC_Order_Email_Base {
        public function __construct() {
            $this->id             = 'spbwc_order_received';
            $this->customer_email = true;
            $this->title          = __( 'Custom order — received (customer)', 'storelly-product-builder-for-woocommerce' );
            $this->description     = __( 'Confirms a customer order that contains a personalised design. Sent locally, no cloud needed.', 'storelly-product-builder-for-woocommerce' );
            $this->heading        = __( 'We received your custom order', 'storelly-product-builder-for-woocommerce' );
            $this->subject        = __( 'Your custom order {order_number} is received', 'storelly-product-builder-for-woocommerce' );
            add_action( 'spbwc_order_received_notification', array( $this, 'trigger' ), 10, 1 );
            parent::__construct();
        }
        public function trigger( $order_id ) {
            $this->order_dispatch( $order_id );
        }
        protected function build_body() {
            $body  = '<p>' . esc_html__( 'Thank you — we have received your custom order and our team is preparing your design.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
            if ( $this->object ) {
                $body .= '<p><strong>' . esc_html__( 'Order:', 'storelly-product-builder-for-woocommerce' ) . '</strong> ' . esc_html( $this->object->get_order_number() ) . '</p>';
                $body .= $this->cta( $this->object->get_view_order_url(), __( 'View your order', 'storelly-product-builder-for-woocommerce' ) );
            }
            return $body;
        }
    }
}

/* ── Design proof ready → customer (cloud) ────────────────────────────── */
if ( ! class_exists( 'SPBWC_Email_Order_Proof' ) && class_exists( 'SPBWC_Order_Email_Base' ) ) {
    class SPBWC_Email_Order_Proof extends SPBWC_Order_Email_Base {
        public function __construct() {
            $this->id             = 'spbwc_order_proof';
            $this->customer_email = true;
            $this->title          = __( 'Custom order — proof ready (customer)', 'storelly-product-builder-for-woocommerce' );
            $this->description     = __( 'Sent when the print-ready proof PDF has been generated, attaching it for the customer.', 'storelly-product-builder-for-woocommerce' );
            $this->heading        = __( 'Your design proof is ready', 'storelly-product-builder-for-woocommerce' );
            $this->subject        = __( 'Your design proof for order {order_number} is ready', 'storelly-product-builder-for-woocommerce' );
            add_action( 'spbwc_order_pdf_ready_notification', array( $this, 'trigger' ), 10, 1 );
            parent::__construct();
        }
        public function trigger( $order_id ) {
            $this->order_dispatch( $order_id );
        }
        protected function build_body() {
            $body = '<p>' . esc_html__( 'Good news — your print-ready design proof is attached. Please review it.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
            if ( $this->object ) {
                $body .= $this->cta( $this->object->get_view_order_url(), __( 'View your order', 'storelly-product-builder-for-woocommerce' ) );
            }
            return $body;
        }

        /** Attach every rendered customer PDF tied to the order's design folders. */
        public function get_attachments() {
            $files = array();
            if ( ! $this->object || ! class_exists( 'SPBWC_Storelly_IO' ) || ! defined( 'SPBWC_PB_CUSTOMER_DIR' ) ) {
                return $files;
            }
            foreach ( $this->object->get_items() as $item ) {
                $folder = is_callable( array( $item, 'get_meta' ) ) ? (string) $item->get_meta( '_pcpb_folder' ) : '';
                if ( '' === $folder || $folder !== basename( $folder ) ) {
                    continue;
                }
                $pdf_dir = SPBWC_PB_CUSTOMER_DIR . '/' . $folder . '/customer-pdfs';
                if ( ! is_dir( $pdf_dir ) ) {
                    continue;
                }
                foreach ( (array) SPBWC_Storelly_IO::spbwc_get_list_files_by_type( $pdf_dir, 'pdf', 5 ) as $pdf ) {
                    $path = $pdf_dir . '/' . basename( $pdf );
                    if ( is_file( $path ) ) {
                        $files[] = $path;
                    }
                }
            }
            return $files;
        }
    }
}
