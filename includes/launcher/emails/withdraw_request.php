<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Email_Withdraw_Request' ) ) {

    class SPBWC_Email_Withdraw_Request  extends WC_Email {

        public function __construct() {
            $this->id               = 'spbwc_email_withdraw_request';
            $this->title            = esc_html__( 'NBDesigner Withdrawal Request', 'storelly-product-builder-for-woocommerce' );
            $this->description      = esc_html__( 'These emails are sent to chosen recipient(s) when a designer send request to withdraw', 'storelly-product-builder-for-woocommerce' );
            $this->template_html    = 'launcher/emails/withdraw_request.php';
            $this->template_plain   = 'launcher/emails/plain/withdraw_request.php';
            $this->template_base    = NBDESIGNER_PLUGIN_DIR.'/templates/';
    
            add_action( 'spbwc_marketplace_after_withdraw_request', array( $this, 'trigger' ), 30, 3 );
    
            parent::__construct();
    
            $this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
        }

        public function get_default_subject() {
            return esc_html__( '[{site_name}] A New withdrawal request is made by {user_name}', 'storelly-product-builder-for-woocommerce' );
        }

        public function get_default_heading() {
            return esc_html__( 'New Withdraw Request from - {user_name}', 'storelly-product-builder-for-woocommerce' );
        }

        public function trigger( $current_user, $amount ) {

            if ( ! $this->is_enabled() ) {
                return;
            }
    
            $designer                    = get_user_by( 'id', $current_user->ID );
            $this->object                = $current_user;
            $this->find['username']      = '{user_name}';
            $this->find['amount']        = '{amount}';
            $this->find['profile_url']   = '{profile_url}';
            $this->find['withdraw_page'] = '{withdraw_page}';
            $this->find['site_name']     = '{site_name}';
            $this->find['site_url']      = '{site_url}';
    
            $this->replace['username']      = $designer->user_login;
            $this->replace['amount']        = wc_price( $amount );
            $this->replace['profile_url']   = wc_get_endpoint_url( 'my-store', '', wc_get_page_permalink( 'myaccount' ) );
            $this->replace['withdraw_page'] = add_query_arg( array( 'tab' => 'withdraw' ), wc_get_endpoint_url( 'my-store', '', wc_get_page_permalink( 'myaccount' ) ) );
            $this->replace['site_name']     = $this->get_from_name();
            $this->replace['site_url']      = site_url();
    
            $this->setup_locale();
            $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
            $this->restore_locale();
    
        }

        public function get_content_html() {
            ob_start();
    
            wc_get_template( $this->template_html, array(
                'designer'      => $this->object,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => true,
                'plain_text'    => false,
                'email'         => $this,
                'data'          => $this->replace
            ), '', $this->template_base );
    
            return ob_get_clean();
        }

        public function get_content_plain() {
            ob_start();
    
            wc_get_template( $this->template_html, array(
                'designer'      => $this->object,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => true,
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
                'recipient' => array(
                    'title'         => esc_html__( 'Recipient(s)', 'storelly-product-builder-for-woocommerce' ),
                    'type'          => 'text',
                    /* translators: %s: site admin email address, shown inside a <code> block */
                    'description'   => sprintf( esc_html__( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'storelly-product-builder-for-woocommerce' ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
                    'placeholder'   => '',
                    'default'       => '',
                    'desc_tip'      => true,
                ),
                'subject' => array(
                    'title'         => esc_html__( 'Subject', 'storelly-product-builder-for-woocommerce' ),
                    'type'          => 'text',
                    'desc_tip'      => true,
                    /* translators: %s: list of available placeholder tags (site_name, amount, user_name) */
                    'description'   => sprintf( esc_html__( 'Available placeholders: %s', 'storelly-product-builder-for-woocommerce' ), '<code>{site_name},{amount},{user_name}</code>' ),
                    'placeholder'   => $this->get_default_subject(),
                    'default'       => '',
                ),
                'heading' => array(
                    'title'         => esc_html__( 'Email heading', 'storelly-product-builder-for-woocommerce' ),
                    'type'          => 'text',
                    'desc_tip'      => true,
                    /* translators: %s: list of available placeholder tags (site_name, amount, user_name) */
                    'description'   => sprintf( esc_html__( 'Available placeholders: %s', 'storelly-product-builder-for-woocommerce' ), '<code>{site_name},{amount},{user_name}</code>' ),
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

return new SPBWC_Email_Withdraw_Request();