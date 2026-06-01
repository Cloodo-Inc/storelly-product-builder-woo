<?php
/**
 * Registers the quote WC_Email classes and their trigger actions (M6).
 *
 * The actual WC_Email subclasses live in class-quote-email-types.php and are
 * only required from inside the `woocommerce_email_classes` filter, where
 * WC_Email is guaranteed to be loaded.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Emails' ) ) {

    class SPBWC_Quote_Emails {

        public static function init() {
            add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_classes' ) );
            add_filter( 'woocommerce_email_actions', array( __CLASS__, 'register_actions' ) );
        }

        /**
         * Tell WooCommerce which actions should boot the email system so our
         * `do_action()` triggers actually send.
         *
         * @param array $actions Action names.
         * @return array
         */
        public static function register_actions( $actions ) {
            $actions[] = 'spbwc_quote_new_notification';
            $actions[] = 'spbwc_quote_sent_notification';
            $actions[] = 'spbwc_quote_reminder_notification';
            $actions[] = 'spbwc_quote_accepted_notification';
            $actions[] = 'spbwc_quote_declined_notification';
            return $actions;
        }

        /**
         * Register the email classes (instantiated by WC_Emails).
         *
         * @param array $emails Email class map.
         * @return array
         */
        public static function register_classes( $emails ) {
            require_once SPBWC_PB_PLUGIN_DIR . 'includes/quote/class-quote-email-types.php';
            if ( class_exists( 'SPBWC_Email_Quote_New' ) ) {
                $emails['SPBWC_Email_Quote_New']      = new SPBWC_Email_Quote_New();
                $emails['SPBWC_Email_Quote_Sent']     = new SPBWC_Email_Quote_Sent();
                $emails['SPBWC_Email_Quote_Reminder'] = new SPBWC_Email_Quote_Reminder();
                $emails['SPBWC_Email_Quote_Accepted'] = new SPBWC_Email_Quote_Accepted();
                $emails['SPBWC_Email_Quote_Declined'] = new SPBWC_Email_Quote_Declined();
            }
            return $emails;
        }
    }
}
