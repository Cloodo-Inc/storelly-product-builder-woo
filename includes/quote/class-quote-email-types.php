<?php
/**
 * WC_Email subclasses for B2B quote events (M6).
 *
 * This file is required from inside the `woocommerce_email_classes` filter, so
 * WC_Email is guaranteed to exist. Each class renders branded HTML by wrapping
 * its body in WooCommerce's own email header/footer templates.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
    return;
}

if ( ! class_exists( 'SPBWC_Quote_Email_Base' ) ) {

    /**
     * Shared behaviour for all quote emails.
     */
    abstract class SPBWC_Quote_Email_Base extends WC_Email {

        /** @var int */
        protected $quote_id = 0;

        protected function load_quote( $quote_id ) {
            $this->quote_id                       = absint( $quote_id );
            $this->object                         = get_post( $this->quote_id );
            $this->placeholders['{quote_number}'] = (string) get_post_meta( $this->quote_id, SPBWC_Quote::META_NUMBER, true );
        }

        protected function quote_request() {
            $r = get_post_meta( $this->quote_id, SPBWC_Quote::META_REQUEST, true );
            return is_array( $r ) ? $r : array();
        }

        protected function customer_email() {
            $r = $this->quote_request();
            return isset( $r['email'] ) ? sanitize_email( $r['email'] ) : '';
        }

        protected function admin_recipient() {
            $settings = get_option( 'spbwc_quote_settings', array() );
            $email    = isset( $settings['admin_email'] ) ? sanitize_email( $settings['admin_email'] ) : get_option( 'admin_email' );
            return is_email( $email ) ? $email : get_option( 'admin_email' );
        }

        protected function customer_name() {
            $r = $this->quote_request();
            if ( class_exists( 'SPBWC_Quotes_List_Table' ) ) {
                return SPBWC_Quotes_List_Table::derive_customer( $r, (int) get_post_field( 'post_author', $this->quote_id ) );
            }
            return '';
        }

        protected function view_quote_url() {
            return wc_get_endpoint_url( 'view-quote', $this->quote_id, wc_get_page_permalink( 'myaccount' ) );
        }

        protected function admin_quote_url() {
            return class_exists( 'SPBWC_Quote_Admin' ) ? SPBWC_Quote_Admin::page_url( array( 'quote' => $this->quote_id ) ) : admin_url();
        }

        /** Render the priced line-item summary as an HTML table. */
        protected function summary_html() {
            $lines  = SPBWC_Quote::get_lines( $this->quote_id );
            $totals = SPBWC_Quote::get_totals( $this->quote_id );
            $cur    = array( 'currency' => isset( $totals['currency'] ) && $totals['currency'] ? $totals['currency'] : get_woocommerce_currency() );
            if ( empty( $lines ) ) {
                return '';
            }
            $out  = '<table cellspacing="0" cellpadding="6" border="1" style="width:100%;border-collapse:collapse;margin:0 0 16px;">';
            $out .= '<thead><tr><th align="left">' . esc_html__( 'Item', 'storelly-product-builder-for-woocommerce' ) . '</th><th align="right">' . esc_html__( 'Qty', 'storelly-product-builder-for-woocommerce' ) . '</th><th align="right">' . esc_html__( 'Total', 'storelly-product-builder-for-woocommerce' ) . '</th></tr></thead><tbody>';
            foreach ( $lines as $line ) {
                $out .= '<tr><td>' . esc_html( isset( $line['label'] ) ? $line['label'] : '' ) . '</td>';
                $out .= '<td align="right">' . esc_html( isset( $line['qty'] ) ? (string) ( 0 + $line['qty'] ) : '' ) . '</td>';
                $out .= '<td align="right">' . wp_kses_post( wc_price( isset( $line['line_total'] ) ? (float) $line['line_total'] : 0, $cur ) ) . '</td></tr>';
            }
            $out .= '</tbody><tfoot><tr><th colspan="2" align="right">' . esc_html__( 'Total', 'storelly-product-builder-for-woocommerce' ) . '</th><th align="right">' . wp_kses_post( wc_price( isset( $totals['total'] ) ? (float) $totals['total'] : 0, $cur ) ) . '</th></tr></tfoot></table>';
            return $out;
        }

        protected function wrap( $inner ) {
            ob_start();
            wc_get_template( 'emails/email-header.php', array( 'email_heading' => $this->get_heading(), 'email' => $this ) );
            echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
            wc_get_template( 'emails/email-footer.php', array( 'email' => $this ) );
            return ob_get_clean();
        }

        public function get_content_html() {
            return $this->wrap( $this->build_body() );
        }

        public function get_content_plain() {
            return trim( wp_strip_all_tags( $this->build_body() ) );
        }

        /** @return string HTML body (between header and footer). */
        abstract protected function build_body();

        protected function dispatch( $quote_id, $recipient ) {
            $this->setup_locale();
            $this->load_quote( $quote_id );
            $this->recipient = $recipient;
            if ( $this->is_enabled() && $this->get_recipient() ) {
                $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
            }
            $this->restore_locale();
        }
    }
}

/* ── New request → admin ──────────────────────────────────────────────── */
if ( ! class_exists( 'SPBWC_Email_Quote_New' ) ) {
    class SPBWC_Email_Quote_New extends SPBWC_Quote_Email_Base {
        public function __construct() {
            $this->id          = 'spbwc_quote_new';
            $this->title       = __( 'Quote — new request (admin)', 'storelly-product-builder-for-woocommerce' );
            $this->description  = __( 'Notifies the store when a customer submits a quote request.', 'storelly-product-builder-for-woocommerce' );
            $this->heading     = __( 'New quote request', 'storelly-product-builder-for-woocommerce' );
            $this->subject     = __( '[{site_title}] New quote request {quote_number}', 'storelly-product-builder-for-woocommerce' );
            add_action( 'spbwc_quote_new_notification', array( $this, 'trigger' ), 10, 1 );
            parent::__construct();
        }
        public function trigger( $quote_id ) {
            $this->dispatch( $quote_id, $this->admin_recipient() );
        }
        protected function build_body() {
            $r    = $this->quote_request();
            $body = '<p>' . esc_html__( 'A new quote request has come in.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
            $body .= '<p>';
            $body .= '<strong>' . esc_html__( 'Customer:', 'storelly-product-builder-for-woocommerce' ) . '</strong> ' . esc_html( $this->customer_name() ) . '<br>';
            if ( ! empty( $r['email'] ) ) {
                $body .= '<strong>' . esc_html__( 'Email:', 'storelly-product-builder-for-woocommerce' ) . '</strong> ' . esc_html( $r['email'] ) . '<br>';
            }
            if ( ! empty( $r['product_name'] ) ) {
                $body .= '<strong>' . esc_html__( 'Product:', 'storelly-product-builder-for-woocommerce' ) . '</strong> ' . esc_html( $r['product_name'] . ' × ' . ( isset( $r['quantity'] ) ? (int) $r['quantity'] : 1 ) ) . '<br>';
            }
            if ( ! empty( $r['message'] ) ) {
                $body .= '<strong>' . esc_html__( 'Message:', 'storelly-product-builder-for-woocommerce' ) . '</strong> ' . esc_html( $r['message'] );
            }
            $body .= '</p>';
            $body .= '<p><a href="' . esc_url( $this->admin_quote_url() ) . '">' . esc_html__( 'Open and reply with pricing', 'storelly-product-builder-for-woocommerce' ) . '</a></p>';
            return $body;
        }
    }
}

/* ── Quote sent → customer ────────────────────────────────────────────── */
if ( ! class_exists( 'SPBWC_Email_Quote_Sent' ) ) {
    class SPBWC_Email_Quote_Sent extends SPBWC_Quote_Email_Base {
        public function __construct() {
            $this->id            = 'spbwc_quote_sent';
            $this->customer_email = true;
            $this->title         = __( 'Quote — ready (customer)', 'storelly-product-builder-for-woocommerce' );
            $this->description    = __( 'Sent to the customer when you reply with pricing.', 'storelly-product-builder-for-woocommerce' );
            $this->heading       = __( 'Your quote is ready', 'storelly-product-builder-for-woocommerce' );
            $this->subject       = __( 'Your quote {quote_number} is ready', 'storelly-product-builder-for-woocommerce' );
            add_action( 'spbwc_quote_sent_notification', array( $this, 'trigger' ), 10, 1 );
            parent::__construct();
        }
        public function trigger( $quote_id ) {
            $this->dispatch( $quote_id, $this->customer_email() );
        }
        protected function build_body() {
            $valid = (string) get_post_meta( $this->quote_id, SPBWC_Quote::META_VALID_UNTIL, true );
            $note  = (string) get_post_meta( $this->quote_id, SPBWC_Quote::META_CUSTOMER_NOTE, true );
            $body  = '<p>' . esc_html__( 'Thank you for your request — here is your quote.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
            $body .= $this->summary_html();
            if ( '' !== $valid ) {
                $body .= '<p><strong>' . esc_html__( 'Valid until:', 'storelly-product-builder-for-woocommerce' ) . '</strong> ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $valid ) ) ) . '</p>';
            }
            if ( '' !== $note ) {
                $body .= '<p>' . nl2br( esc_html( $note ) ) . '</p>';
            }
            $body .= '<p><a href="' . esc_url( $this->view_quote_url() ) . '" style="display:inline-block;padding:10px 18px;background:#1d4ed8;color:#fff;text-decoration:none;border-radius:6px;">' . esc_html__( 'Review &amp; accept your quote', 'storelly-product-builder-for-woocommerce' ) . '</a></p>';
            return $body;
        }
    }
}

/* ── Expiry reminder → customer ───────────────────────────────────────── */
if ( ! class_exists( 'SPBWC_Email_Quote_Reminder' ) ) {
    class SPBWC_Email_Quote_Reminder extends SPBWC_Quote_Email_Base {
        public function __construct() {
            $this->id            = 'spbwc_quote_reminder';
            $this->customer_email = true;
            $this->title         = __( 'Quote — expiry reminder (customer)', 'storelly-product-builder-for-woocommerce' );
            $this->description    = __( 'Reminds the customer that a sent quote is about to expire.', 'storelly-product-builder-for-woocommerce' );
            $this->heading       = __( 'Your quote is expiring soon', 'storelly-product-builder-for-woocommerce' );
            $this->subject       = __( 'Reminder: quote {quote_number} expires soon', 'storelly-product-builder-for-woocommerce' );
            add_action( 'spbwc_quote_reminder_notification', array( $this, 'trigger' ), 10, 2 );
            parent::__construct();
        }
        public function trigger( $quote_id, $days_left = 0 ) {
            $this->dispatch( $quote_id, $this->customer_email() );
        }
        protected function build_body() {
            $valid = (string) get_post_meta( $this->quote_id, SPBWC_Quote::META_VALID_UNTIL, true );
            $body  = '<p>' . esc_html__( 'Just a reminder that your quote is still available, but not for long.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
            $body .= $this->summary_html();
            if ( '' !== $valid ) {
                $body .= '<p><strong>' . esc_html__( 'Valid until:', 'storelly-product-builder-for-woocommerce' ) . '</strong> ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $valid ) ) ) . '</p>';
            }
            $body .= '<p><a href="' . esc_url( $this->view_quote_url() ) . '" style="display:inline-block;padding:10px 18px;background:#1d4ed8;color:#fff;text-decoration:none;border-radius:6px;">' . esc_html__( 'Accept your quote', 'storelly-product-builder-for-woocommerce' ) . '</a></p>';
            return $body;
        }
    }
}

/* ── Accepted → admin ─────────────────────────────────────────────────── */
if ( ! class_exists( 'SPBWC_Email_Quote_Accepted' ) ) {
    class SPBWC_Email_Quote_Accepted extends SPBWC_Quote_Email_Base {
        public function __construct() {
            $this->id          = 'spbwc_quote_accepted';
            $this->title       = __( 'Quote — accepted (admin)', 'storelly-product-builder-for-woocommerce' );
            $this->description  = __( 'Notifies the store when a customer accepts a quote.', 'storelly-product-builder-for-woocommerce' );
            $this->heading     = __( 'Quote accepted', 'storelly-product-builder-for-woocommerce' );
            $this->subject     = __( '[{site_title}] Quote {quote_number} accepted', 'storelly-product-builder-for-woocommerce' );
            add_action( 'spbwc_quote_accepted_notification', array( $this, 'trigger' ), 10, 1 );
            parent::__construct();
        }
        public function trigger( $quote_id ) {
            $this->dispatch( $quote_id, $this->admin_recipient() );
        }
        protected function build_body() {
            $order_id = (int) get_post_meta( $this->quote_id, SPBWC_Quote::META_ORDER_ID, true );
            $body     = '<p>' . esc_html( sprintf( /* translators: %s: customer name */ __( '%s accepted the quote.', 'storelly-product-builder-for-woocommerce' ), $this->customer_name() ) ) . '</p>';
            $body    .= $this->summary_html();
            if ( $order_id ) {
                $body .= '<p>' . esc_html( sprintf( /* translators: %d: order ID */ __( 'Order #%d was created.', 'storelly-product-builder-for-woocommerce' ), $order_id ) ) . ' <a href="' . esc_url( get_edit_post_link( $order_id ) ) . '">' . esc_html__( 'View order', 'storelly-product-builder-for-woocommerce' ) . '</a></p>';
            }
            return $body;
        }
    }
}

/* ── Declined → admin ─────────────────────────────────────────────────── */
if ( ! class_exists( 'SPBWC_Email_Quote_Declined' ) ) {
    class SPBWC_Email_Quote_Declined extends SPBWC_Quote_Email_Base {
        public function __construct() {
            $this->id          = 'spbwc_quote_declined';
            $this->title       = __( 'Quote — declined (admin)', 'storelly-product-builder-for-woocommerce' );
            $this->description  = __( 'Notifies the store when a customer declines a quote.', 'storelly-product-builder-for-woocommerce' );
            $this->heading     = __( 'Quote declined', 'storelly-product-builder-for-woocommerce' );
            $this->subject     = __( '[{site_title}] Quote {quote_number} declined', 'storelly-product-builder-for-woocommerce' );
            add_action( 'spbwc_quote_declined_notification', array( $this, 'trigger' ), 10, 1 );
            parent::__construct();
        }
        public function trigger( $quote_id ) {
            $this->dispatch( $quote_id, $this->admin_recipient() );
        }
        protected function build_body() {
            $reason = (string) get_post_meta( $this->quote_id, SPBWC_Quote::META_DECLINE_REASON, true );
            $body   = '<p>' . esc_html( sprintf( /* translators: %s: customer name */ __( '%s declined the quote.', 'storelly-product-builder-for-woocommerce' ), $this->customer_name() ) ) . '</p>';
            if ( '' !== $reason ) {
                $body .= '<p><strong>' . esc_html__( 'Reason:', 'storelly-product-builder-for-woocommerce' ) . '</strong> ' . esc_html( $reason ) . '</p>';
            }
            $body .= '<p><a href="' . esc_url( $this->admin_quote_url() ) . '">' . esc_html__( 'View quote', 'storelly-product-builder-for-woocommerce' ) . '</a></p>';
            return $body;
        }
    }
}
