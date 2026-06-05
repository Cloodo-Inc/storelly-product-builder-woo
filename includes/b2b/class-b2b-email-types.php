<?php
/**
 * WC_Email subclasses for B2B team / company events.
 *
 * Migrates the B2B notifications that previously called wp_mail() directly
 * (team invite, procurement approval-needed, procurement outcome, company
 * ready) onto the standard WooCommerce email pipeline: branded HTML + plain
 * variants, a WooCommerce > Settings > Emails entry per type (enable/disable,
 * subject, heading, format), and i18n. See docs/SPEC_EMAIL_SYSTEM.md (E2).
 *
 * Fully local — these mailers send via wp_mail()/WC, never phone home.
 *
 * This file is required from inside the `woocommerce_email_classes` filter, so
 * WC_Email is guaranteed to exist.
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

    /**
     * Generic shared behaviour for Storelly WC_Email subclasses: wrap a built
     * HTML body in WooCommerce's own header/footer, derive the plain variant,
     * and dispatch to a resolved recipient. Subclasses set their context in
     * trigger() then call dispatch().
     */
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

/* ── Team invite → invited person ─────────────────────────────────────── */
if ( ! class_exists( 'SPBWC_Email_B2B_Invite' ) ) {
    class SPBWC_Email_B2B_Invite extends SPBWC_Email_Base {

        /** @var int */
        protected $company_id = 0;
        /** @var string */
        protected $token = '';

        public function __construct() {
            $this->id             = 'spbwc_b2b_invite';
            $this->customer_email = true;
            $this->title          = __( 'B2B — team invitation', 'storelly-product-builder-for-woocommerce' );
            $this->description    = __( 'Invites a person to join a B2B company as a team member.', 'storelly-product-builder-for-woocommerce' );
            $this->heading        = __( 'You are invited to join the team', 'storelly-product-builder-for-woocommerce' );
            $this->subject        = __( 'You are invited to join {company_name}', 'storelly-product-builder-for-woocommerce' );
            add_action( 'spbwc_b2b_invite_notification', array( $this, 'trigger' ), 10, 3 );
            parent::__construct();
        }

        public function trigger( $company_id, $email, $token ) {
            $this->company_id                     = absint( $company_id );
            $this->token                          = (string) $token;
            $this->placeholders['{company_name}'] = get_the_title( $this->company_id );
            $this->dispatch( sanitize_email( $email ) );
        }

        protected function build_body() {
            $name   = get_the_title( $this->company_id );
            $accept = add_query_arg(
                array(
                    'spbwc_accept_invite' => rawurlencode( $this->token ),
                    'company'             => $this->company_id,
                ),
                wc_get_page_permalink( 'myaccount' )
            );
            $body  = '<p>' . esc_html( sprintf( /* translators: %s: company name. */ __( "You've been invited to join %s as a team member.", 'storelly-product-builder-for-woocommerce' ), $name ) ) . '</p>';
            $body .= '<p>' . esc_html__( 'Log in or create an account first, then accept your invitation:', 'storelly-product-builder-for-woocommerce' ) . '</p>';
            $body .= $this->cta( $accept, __( 'Accept invitation', 'storelly-product-builder-for-woocommerce' ) );
            return $body;
        }
    }
}

/* ── Procurement: approval needed → approvers ─────────────────────────── */
if ( ! class_exists( 'SPBWC_Email_B2B_Approval_Needed' ) ) {
    class SPBWC_Email_B2B_Approval_Needed extends SPBWC_Email_Base {

        /** @var int */
        protected $request_id = 0;

        public function __construct() {
            $this->id             = 'spbwc_b2b_approval_needed';
            $this->customer_email = true;
            $this->title          = __( 'B2B — order needs approval', 'storelly-product-builder-for-woocommerce' );
            $this->description    = __( 'Notifies company approvers that a team order is waiting for review.', 'storelly-product-builder-for-woocommerce' );
            $this->heading        = __( 'A team order needs your approval', 'storelly-product-builder-for-woocommerce' );
            $this->subject        = __( 'A team order needs your approval', 'storelly-product-builder-for-woocommerce' );
            add_action( 'spbwc_b2b_approval_needed_notification', array( $this, 'trigger' ), 10, 1 );
            parent::__construct();
        }

        public function trigger( $request_id ) {
            $this->request_id = absint( $request_id );
            $company_id       = (int) get_post_meta( $this->request_id, SPBWC_B2B_Procurement::META_COMPANY, true );
            $recipients       = array();
            if ( class_exists( 'SPBWC_Company' ) ) {
                foreach ( SPBWC_Company::get_members( $company_id ) as $m ) {
                    if ( SPBWC_Company::user_can_approve( $m->ID ) && is_email( $m->user_email ) ) {
                        $recipients[] = $m->user_email;
                    }
                }
            }
            $this->dispatch( implode( ',', array_unique( $recipients ) ) );
        }

        protected function build_body() {
            $total = wp_strip_all_tags( wc_price( (float) get_post_meta( $this->request_id, SPBWC_B2B_Procurement::META_TOTAL, true ) ) );
            $url   = $this->myaccount_endpoint_url( SPBWC_B2B_Procurement::ENDPOINT );
            $body  = '<p>' . esc_html( sprintf( /* translators: %s: order total. */ __( 'A team member submitted an order of %s for approval.', 'storelly-product-builder-for-woocommerce' ), $total ) ) . '</p>';
            $body .= $this->cta( $url, __( 'Review the request', 'storelly-product-builder-for-woocommerce' ) );
            return $body;
        }
    }
}

/* ── Procurement: outcome → requester ─────────────────────────────────── */
if ( ! class_exists( 'SPBWC_Email_B2B_Approval_Outcome' ) ) {
    class SPBWC_Email_B2B_Approval_Outcome extends SPBWC_Email_Base {

        /** @var int */
        protected $request_id = 0;
        /** @var string */
        protected $outcome = '';
        /** @var int */
        protected $order_id = 0;

        public function __construct() {
            $this->id             = 'spbwc_b2b_approval_outcome';
            $this->customer_email = true;
            $this->title          = __( 'B2B — procurement outcome', 'storelly-product-builder-for-woocommerce' );
            $this->description    = __( 'Tells the requester whether their team order was approved or rejected.', 'storelly-product-builder-for-woocommerce' );
            $this->heading        = __( 'An update on your team order', 'storelly-product-builder-for-woocommerce' );
            $this->subject        = __( 'An update on your team order', 'storelly-product-builder-for-woocommerce' );
            add_action( 'spbwc_b2b_approval_outcome_notification', array( $this, 'trigger' ), 10, 3 );
            parent::__construct();
        }

        public function trigger( $request_id, $outcome, $order_id ) {
            $this->request_id = absint( $request_id );
            $this->outcome    = ( 'approved' === $outcome ) ? 'approved' : 'rejected';
            $this->order_id   = absint( $order_id );
            $requester        = get_userdata( (int) get_post_meta( $this->request_id, SPBWC_B2B_Procurement::META_REQUESTER, true ) );
            if ( ! $requester || ! is_email( $requester->user_email ) ) {
                return;
            }
            $this->dispatch( $requester->user_email );
        }

        protected function build_body() {
            if ( 'approved' === $this->outcome ) {
                $order = $this->order_id ? wc_get_order( $this->order_id ) : null;
                $pay   = ( $order && $order->needs_payment() ) ? $order->get_checkout_payment_url() : $this->myaccount_endpoint_url( SPBWC_B2B_Procurement::ENDPOINT );
                $body  = '<p>' . esc_html__( 'Your procurement request was approved and an order was created.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                $body .= $this->cta( $pay, __( 'Complete your order', 'storelly-product-builder-for-woocommerce' ), '#16a34a' );
                return $body;
            }
            return '<p>' . esc_html__( 'Your procurement request was not approved. Please contact your company administrator.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
        }
    }
}

/* ── Company ready → new owner ────────────────────────────────────────── */
if ( ! class_exists( 'SPBWC_Email_B2B_Company_Ready' ) ) {
    class SPBWC_Email_B2B_Company_Ready extends SPBWC_Email_Base {

        /** @var int */
        protected $company_id = 0;

        public function __construct() {
            $this->id             = 'spbwc_b2b_company_ready';
            $this->customer_email = true;
            $this->title          = __( 'B2B — Brand Store ready', 'storelly-product-builder-for-woocommerce' );
            $this->description    = __( 'Notifies a new company owner that their B2B Brand Store is ready to set up.', 'storelly-product-builder-for-woocommerce' );
            $this->heading        = __( 'Your B2B Brand Store is ready', 'storelly-product-builder-for-woocommerce' );
            $this->subject        = __( 'Your B2B Brand Store "{company_name}" is ready', 'storelly-product-builder-for-woocommerce' );
            add_action( 'spbwc_b2b_company_ready_notification', array( $this, 'trigger' ), 10, 2 );
            parent::__construct();
        }

        public function trigger( $company_id, $owner_id ) {
            $this->company_id                     = absint( $company_id );
            $this->placeholders['{company_name}'] = get_the_title( $this->company_id );
            $owner                                = get_userdata( absint( $owner_id ) );
            if ( ! $owner || ! is_email( $owner->user_email ) ) {
                return;
            }
            $this->dispatch( $owner->user_email );
        }

        protected function build_body() {
            $name  = get_the_title( $this->company_id );
            $store = $this->myaccount_endpoint_url( SPBWC_B2B_Account::ENDPOINT );
            $body  = '<p>' . esc_html( sprintf( /* translators: %s: company name. */ __( 'Good news! Your account has been upgraded to a B2B company: %s.', 'storelly-product-builder-for-woocommerce' ), $name ) ) . '</p>';
            $body .= '<p>' . esc_html__( 'Set up your Brand Store profile to get started:', 'storelly-product-builder-for-woocommerce' ) . '</p>';
            $body .= $this->cta( $store, __( 'Set up your Brand Store', 'storelly-product-builder-for-woocommerce' ) );
            return $body;
        }
    }
}
