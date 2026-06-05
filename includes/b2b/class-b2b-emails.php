<?php
/**
 * Registers the B2B WC_Email classes and their trigger actions (E2).
 *
 * The actual WC_Email subclasses live in class-b2b-email-types.php and are only
 * required from inside the `woocommerce_email_classes` filter, where WC_Email is
 * guaranteed to be loaded. Mirrors SPBWC_Quote_Emails.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Emails' ) ) {

    class SPBWC_B2B_Emails {

        public static function init() {
            add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_classes' ) );
            add_filter( 'woocommerce_email_actions', array( __CLASS__, 'register_actions' ) );
        }

        /**
         * Actions that should boot the WC email system so our do_action()
         * triggers actually send.
         *
         * @param array $actions Action names.
         * @return array
         */
        public static function register_actions( $actions ) {
            $actions[] = 'spbwc_b2b_invite_notification';
            $actions[] = 'spbwc_b2b_approval_needed_notification';
            $actions[] = 'spbwc_b2b_approval_outcome_notification';
            $actions[] = 'spbwc_b2b_company_ready_notification';
            return $actions;
        }

        /**
         * Register the email classes (instantiated by WC_Emails).
         *
         * @param array $emails Email class map.
         * @return array
         */
        public static function register_classes( $emails ) {
            require_once SPBWC_PB_PLUGIN_DIR . 'includes/b2b/class-b2b-email-types.php';
            if ( class_exists( 'SPBWC_Email_B2B_Invite' ) ) {
                $emails['SPBWC_Email_B2B_Invite']           = new SPBWC_Email_B2B_Invite();
                $emails['SPBWC_Email_B2B_Approval_Needed']  = new SPBWC_Email_B2B_Approval_Needed();
                $emails['SPBWC_Email_B2B_Approval_Outcome'] = new SPBWC_Email_B2B_Approval_Outcome();
                $emails['SPBWC_Email_B2B_Company_Ready']    = new SPBWC_Email_B2B_Company_Ready();
            }
            return $emails;
        }
    }
}
