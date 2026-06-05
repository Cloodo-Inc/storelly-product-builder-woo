<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( !class_exists( 'SPBWC_Email_Designer_Disabled' ) ) {

    class SPBWC_Email_Designer_Disabled extends WC_Email {

        public function __construct() {
            $this->id               = 'spbwc_email_designer_disable';
            $this->title            = esc_html__( 'Designer Disable', 'storelly-product-builder-for-woocommerce' );
            $this->description      = esc_html__( 'This email is sent to a designer when his/her designer account is deactivated by admin', 'storelly-product-builder-for-woocommerce' );
            $this->template_html    = 'launcher/emails/designer_disabled.php';
            $this->template_plain   = 'launcher/emails/plain/designer_disabled.php';
            $this->template_base    = NBDESIGNER_PLUGIN_DIR.'/templates/';

            add_action( 'spbwc_marketplace_designer_disabled', array( $this, 'trigger' ) );

            $this->customer_email = true;
            parent::__construct();
        }

        public function get_default_subject() {
            return esc_html__( '[{site_name}] Your account is deactivated', 'storelly-product-builder-for-woocommerce' );
        }

        public function get_default_heading() {
            return esc_html__( 'Your designer account is deactivated', 'storelly-product-builder-for-woocommerce' );
        }

        public function trigger( $designer_id ) {
            if ( ! $this->is_enabled() ) {
                return;
            }

            $this->setup_locale();

            $designer       = get_user_by( 'ID', $designer_id );
            $designer_email = $designer->user_email;

            $this->find['site_name']        = '{site_name}';
            $this->find['first_name']       = '{first_name}';
            $this->find['last_name']        = '{last_name}';
            $this->find['display_name']     = '{display_name}';

            $this->replace['site_name']     = $this->get_from_name();
            $this->replace['first_name']    = $designer->first_name;
            $this->replace['last_name']     = $designer->last_name;
            $this->replace['display_name']  = $designer->display_name;

            $this->send( $designer_email, $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

            $this->restore_locale();
        }

        public function get_content_html() {
            ob_start();
            wc_get_template( $this->template_html, array(
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => false,
                'email'         => $this,
                'data'          => $this->replace
            ), '', $this->template_base );
            return ob_get_clean();
        }

        public function get_content_plain() {
            ob_start();
            wc_get_template( $this->template_html, array(
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => true,
                'email'         => $this,
                'data'          => $this->replace
            ), '', $this->template_base );
            return ob_get_clean();
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'         => esc_html__( 'Enable/Disable', 'storelly-product-builder-for-woocommerce' ),
                    'type'          => 'checkbox',
                    'label'         => esc_html__( 'Enable this email notification', 'storelly-product-builder-for-woocommerce' ),
                    'default'       => 'yes',
                ),

                'subject' => array(
                    'title'         => esc_html__( 'Subject', 'storelly-product-builder-for-woocommerce' ),
                    'type'          => 'text',
                    'desc_tip'      => true,
                    /* translators: %s: comma-separated list of placeholder tokens like {title}, {site_name} */
                    'description'   => sprintf( esc_html__( 'Available placeholders: %s', 'storelly-product-builder-for-woocommerce' ), '<code>{title}, {message}, {site_name}</code>' ),
                    'placeholder'   => $this->get_default_subject(),
                    'default'       => '',
                ),
                'heading' => array(
                    'title'         => esc_html__( 'Email heading', 'storelly-product-builder-for-woocommerce' ),
                    'type'          => 'text',
                    'desc_tip'      => true,
                    /* translators: %s: comma-separated list of placeholder tokens like {title}, {site_name} */
                    'description'   => sprintf( esc_html__( 'Available placeholders: %s', 'storelly-product-builder-for-woocommerce' ), '<code>{title}, {message}, {site_name}</code>' ),
                    'placeholder'   => $this->get_default_heading(),
                    'default'       => '',
                ),
                'email_type' => array(
                    'title'         => esc_html__( 'Email type', 'storelly-product-builder-for-woocommerce' ),
                    'type'          => 'select',
                    'description'   => esc_html__( 'Choose which format of email to send.', 'storelly-product-builder-for-woocommerce' ),
                    'default'       => 'html',
                    'class'         => 'email_type wc-enhanced-select',
                    'options'       => $this->get_email_type_options(),
                    'desc_tip'      => true,
                ),
            );
        }
    }

}

return new SPBWC_Email_Designer_Disabled();