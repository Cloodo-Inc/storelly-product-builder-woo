<?php
/**
 * Designer direct message email (E3).
 *
 * Replaces the raw wp_mail() call in the designer REST endpoint
 * (`SPBWC_Designer_API::send_email_to_designer`). The admin composes a free-form
 * subject + message; this WC_Email wraps it in the branded header/footer and logs
 * it via the standard pipeline. Subject/heading come from the composed message
 * (not from WC settings), so only enable/disable + format are configurable.
 *
 * Returned as an instance from inside `woocommerce_email_classes` (launcher
 * registrar uses include()), so WC_Email exists here.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once SPBWC_PB_PLUGIN_DIR . 'includes/email/class-email-base.php';

if ( ! class_exists( 'SPBWC_Email_Designer_Message' ) && class_exists( 'SPBWC_Email_Base' ) ) {

    class SPBWC_Email_Designer_Message extends SPBWC_Email_Base {

        /** @var string */
        protected $custom_subject = '';
        /** @var string */
        protected $custom_message = '';

        public function __construct() {
            $this->id             = 'spbwc_email_designer_message';
            $this->customer_email = true;
            $this->title          = __( 'Designer — direct message', 'storelly-product-builder-for-woocommerce' );
            $this->description     = __( 'Sent when the store emails a designer a custom message from the Designers admin.', 'storelly-product-builder-for-woocommerce' );
            $this->heading        = __( 'A message from the store', 'storelly-product-builder-for-woocommerce' );
            $this->subject        = __( 'A message from {site_title}', 'storelly-product-builder-for-woocommerce' );
            add_action( 'spbwc_designer_message_notification', array( $this, 'trigger' ), 10, 3 );
            parent::__construct();
        }

        /**
         * @param int    $designer_id Designer user ID.
         * @param string $subject     Admin-composed subject (sanitized upstream).
         * @param string $message     Admin-composed HTML message (sanitized upstream).
         */
        public function trigger( $designer_id, $subject, $message ) {
            $designer = get_user_by( 'ID', absint( $designer_id ) );
            if ( ! $designer || ! is_email( $designer->user_email ) ) {
                return;
            }
            $this->custom_subject = sanitize_text_field( $subject );
            $this->custom_message = wp_kses_post( $message );
            if ( '' === $this->custom_subject || '' === trim( wp_strip_all_tags( $this->custom_message ) ) ) {
                return;
            }
            $this->dispatch( $designer->user_email );
        }

        /** Free-form: the composed subject wins over any saved WC setting. */
        public function get_subject() {
            return $this->custom_subject ? $this->custom_subject : $this->format_string( $this->get_default_subject() );
        }

        public function get_heading() {
            return $this->custom_subject ? $this->custom_subject : $this->format_string( $this->get_default_heading() );
        }

        protected function build_body() {
            return wpautop( $this->custom_message );
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled'    => array(
                    'title'   => esc_html__( 'Enable/Disable', 'storelly-product-builder-for-woocommerce' ),
                    'type'    => 'checkbox',
                    'label'   => esc_html__( 'Enable this email notification', 'storelly-product-builder-for-woocommerce' ),
                    'default' => 'yes',
                ),
                'email_type' => array(
                    'title'       => esc_html__( 'Email type', 'storelly-product-builder-for-woocommerce' ),
                    'type'        => 'select',
                    'description' => esc_html__( 'Choose which format of email to send.', 'storelly-product-builder-for-woocommerce' ),
                    'default'     => 'html',
                    'class'       => 'email_type wc-enhanced-select',
                    'options'     => $this->get_email_type_options(),
                    'desc_tip'    => true,
                ),
            );
        }
    }
}

return class_exists( 'SPBWC_Email_Designer_Message' ) ? new SPBWC_Email_Designer_Message() : null;
