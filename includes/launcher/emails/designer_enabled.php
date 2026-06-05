<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( !class_exists( 'SPBWC_Email_Designer_Enabled' ) ) {

    class SPBWC_Email_Designer_Enabled extends WC_Email {
        /**
         * Enable BCC copy.
         *
         * @var bool
         */
        public $enable_bcc = false;

        public function __construct() {
            $this->id               = 'spbwc_email_designer_enable';
            $this->title            = esc_html__( 'Designer Enable', 'storelly-product-builder-for-woocommerce' );
            $this->description      = esc_html__( 'This email is sent to a designer when his/her designer account enabled from admin settings', 'storelly-product-builder-for-woocommerce' );
            $this->heading          = esc_html__( 'Your designer account is activated', 'storelly-product-builder-for-woocommerce' );
            $this->subject          = esc_html__( '[{site_name}] Your account is activated', 'storelly-product-builder-for-woocommerce' );
            $this->template_html    = 'launcher/emails/designer_enabled.php';
            $this->template_plain   = 'launcher/emails/plain/designer_enabled.php';
            $this->template_base    = NBDESIGNER_PLUGIN_DIR.'/templates/';

            if( $this->enabled == 'no'){
                return;
            }

            add_action( 'spbwc_marketplace_designer_enabled', array( $this, 'trigger' ), 15, 1 );
            $this->customer_email = true;
            parent::__construct();
            $this->enable_bcc = $this->get_option( 'enable_bcc' );
            $this->enable_bcc = $this->enable_bcc == 'yes';
        }
        public function trigger( $designer_id ) {
            $this->setup_locale();

            $this->designer     = get_user_by( 'ID', $designer_id );
            $this->recipient    = $this->designer->user_email;
            if ( version_compare( WC()->version, '3.2.0', '<' ) ) {
                $this->find['site_name']        = '{site_name}';
                $this->find['display_name']     = '{display_name}';
                $this->replace['site_name']     = $this->get_from_name();
                $this->replace['display_name']  = $this->designer->display_name;
            }else{
                $this->placeholders['{site_name}']      = $this->get_from_name();
                $this->placeholders['{display_name}']   = $this->designer->display_name;
            }

            $this->send( $this->recipient, $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

            $this->restore_locale();
        }
        public function get_content_html() {
            ob_start();
            wc_get_template($this->template_html, array(
                'email_heading'     => $this->get_heading(),
                'email_title'       => str_replace( '{display_name}', $this->designer->display_name, $this->get_option( 'email-title' )),
                'email_description' => $this->get_option( 'email-description' ),
                'sent_to_admin'     => false,
                'plain_text'        => false,
                'email'             => $this
            ), '', $this->template_base );
            return ob_get_clean();
        }
        function get_content_plain() {
            ob_start();
            wc_get_template($this->template_plain, array(
                'email_heading'     => $this->get_heading(),
                'email_title'       => str_replace( '{display_name}', $this->designer->display_name, $this->get_option( 'email-title' )),
                'email_description' => $this->get_option( 'email-description' ),
                'sent_to_admin'     => false,
                'plain_text'        => true,
                'email'             => $this
            ), '', $this->template_base );
            return ob_get_clean();
        }
        public function get_from_name( $from_name = '' ) {
            $email_from_name = ( isset($this->settings['email_from_name']) && $this->settings['email_from_name'] != '' ) ? $this->settings['email_from_name'] : '';
            return wp_specialchars_decode( esc_html( $email_from_name ), ENT_QUOTES );
        }
        public function get_from_address( $from_email = '' ) {
            $email_from_email = ( isset($this->settings['email_from_email']) && $this->settings['email_from_email'] != '' ) ? $this->settings['email_from_email'] : '';
            return sanitize_email( $email_from_email );
        }
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => esc_html__( 'Enable/Disable', 'storelly-product-builder-for-woocommerce' ),
                    'type'        => 'checkbox',
                    'label'       => esc_html__( 'Enable this email notification', 'storelly-product-builder-for-woocommerce' ),
                    'default'     => 'yes',
                ),
                'email_from_name'    => array(
                    'title'       => esc_html__( '"From" Name', 'storelly-product-builder-for-woocommerce' ),
                    'type'        => 'text',
                    'description' => '',
                    'placeholder' => '',
                    'default'     => get_option( 'woocommerce_email_from_name' )
                ),
                'email_from_email'    => array(
                    'title'       => esc_html__( '"From" Email Address', 'storelly-product-builder-for-woocommerce' ),
                    'type'        => 'text',
                    'description' => '',
                    'placeholder' => '',
                    'default'     => get_option( 'woocommerce_email_from_address' )
                ),
                'subject'    => array(
                    'title'       => esc_html__( 'Subject', 'storelly-product-builder-for-woocommerce' ),
                    'type'        => 'text',
                    /* translators: %s: default email subject text, shown inside a <code> block */
                    'description' => sprintf( esc_html__( 'This field lets you modify the email subject line. Leave it blank to use the default subject text: <code>%s</code>. You can use {site_name} as a placeholder that will show the your site address.', 'storelly-product-builder-for-woocommerce' ), $this->subject ),
                    'placeholder' => $this->subject,
                    'default'     => ''
                ),
                'recipient'  => array(
                    'title'       => esc_html__( 'Bcc Recipient(s)', 'storelly-product-builder-for-woocommerce' ),
                    'type'        => 'text',
                    'description' => esc_html__( 'Enter futher recipients (separated by commas) for this email. By default email to the customer', 'storelly-product-builder-for-woocommerce' ),
                    'placeholder' => '',
                    'default'     => ''
                ),
                'enable_bcc'  => array(
                    'title'       => esc_html__( 'Send BCC copy', 'storelly-product-builder-for-woocommerce' ),
                    'type'        => 'checkbox',
                    'description' => esc_html__( 'Send a blind carbon copy to the administrator', 'storelly-product-builder-for-woocommerce' ),
                    'default'     => 'no'
                ),
                'heading'    => array(
                    'title'       => esc_html__( 'Email Heading', 'storelly-product-builder-for-woocommerce' ),
                    'type'        => 'text',
                    /* translators: %s: default email heading text, shown inside a <code> block */
                    'description' => sprintf( esc_html__( 'This field lets you change the main heading in email notification. Leave it blank to use default heading type: <code>%s</code>.', 'storelly-product-builder-for-woocommerce' ), $this->heading ),
                    'placeholder' => $this->heading,
                    'default'     => ''
                ),
                'email-title'    => array(
                    'title'       => esc_html__( 'Email Title', 'storelly-product-builder-for-woocommerce' ),
                    'type'        => 'text',
                    'placeholder' => esc_html__( 'Congratulations {display_name}!', 'storelly-product-builder-for-woocommerce' ),
                    'description' => esc_html__( 'This field lets you change the main title in email notification. Available placeholders: <code>{display_name}!</code>.', 'storelly-product-builder-for-woocommerce' ),
                    'default'     => esc_html__( 'Congratulations {display_name}!', 'storelly-product-builder-for-woocommerce' )
                ),
                'email-description'    => array(
                    'title'       => esc_html__( 'Email Description', 'storelly-product-builder-for-woocommerce' ),
                    'type'        => 'textarea',
                    'css'         => 'width:400px; height: 75px;',
                    'placeholder' => $this->description,
                    'default'     => ''
                ),
                'email_type' => array(
                    'title'         => esc_html__( 'Email type', 'storelly-product-builder-for-woocommerce' ),
                    'type'          => 'select',
                    'description'   => esc_html__( 'Choose email format.', 'storelly-product-builder-for-woocommerce' ),
                    'default'       => 'html',
                    'class'         => 'email_type wc-enhanced-select',
                    'options'       => $this->get_email_type_options()
                )
            );
        }
    }

}

return new SPBWC_Email_Designer_Enabled();