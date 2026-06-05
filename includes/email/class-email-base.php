<?php
/**
 * Shared base for all Storelly WC_Email subclasses (B2B, Order, Designer-message).
 *
 * Wraps a built HTML body in WooCommerce's own header/footer, derives the plain
 * variant, and dispatches to a resolved recipient. Subclasses set their context
 * in trigger() then call dispatch(). Fully local — sends via wp_mail()/WC.
 *
 * Required from inside the `woocommerce_email_classes` filter (each email
 * registrar require_once's it), so WC_Email is guaranteed to exist.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
    return;
}

if ( ! class_exists( 'SPBWC_Email_Base' ) ) {

    abstract class SPBWC_Email_Base extends WC_Email {

        /**
         * Wrap inner HTML in the WooCommerce branded header/footer.
         *
         * @param string $inner Pre-escaped HTML body.
         * @return string
         */
        protected function wrap( $inner ) {
            ob_start();
            wc_get_template( 'emails/email-header.php', array( 'email_heading' => $this->get_heading(), 'email' => $this ) );
            echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts in build_body().
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

        /**
         * Send to a resolved recipient (string, or comma-separated list).
         *
         * @param string $recipient Recipient email(s).
         */
        protected function dispatch( $recipient ) {
            $this->setup_locale();
            $this->recipient = $recipient;
            if ( $this->is_enabled() && $this->get_recipient() ) {
                $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
            }
            $this->restore_locale();
        }

        protected function myaccount_endpoint_url( $endpoint ) {
            return wc_get_endpoint_url( $endpoint, '', wc_get_page_permalink( 'myaccount' ) );
        }

        protected function cta( $url, $label, $color = '#1d4ed8' ) {
            return '<p><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:10px 18px;background:' . esc_attr( $color ) . ';color:#fff;text-decoration:none;border-radius:6px;">' . esc_html( $label ) . '</a></p>';
        }
    }
}
