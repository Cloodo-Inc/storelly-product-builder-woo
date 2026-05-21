<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Email_Withdraw_Cancelled' ) ) {

    class SPBWC_Email_Withdraw_Cancelled  extends WC_Email {

        public function __construct() {
            $this->id               = 'nbdl_email_withdraw_cancelled';
            $this->title            = esc_html__( 'NBDesigner Withdraw cancelled', 'storelly-product-builder-for-woocommerce' );
            $this->description      = esc_html__( 'These emails are sent to designer when a designer withdraw request is cancelled', 'storelly-product-builder-for-woocommerce' );
            $this->template_html    = 'launcher/emails/withdraw_cancelled.php';
            $this->template_plain   = 'launcher/emails/plain/withdraw_cancelled.php';
            $this->template_base    = NBDESIGNER_PLUGIN_DIR.'/templates/';

            add_action( 'spbwc_marketplace_withdraw_request_cancelled', array( $this, 'trigger' ), 30, 2 );

            $this->customer_email = true;
            parent::__construct();
        }

        public function get_default_subject() {
            return esc_html__( '[{site_name}] Your withdrawal request was cancelled', 'storelly-product-builder-for-woocommerce' );
        }

        public function get_default_heading() {
            return esc_html__( 'Withdrawal request for {amount} is cancelled', 'storelly-product-builder-for-woocommerce' );
        }

        public function trigger( $user_id, $withdraw ) {
            if ( ! $this->is_enabled() ) {
                return;
            }
    
            $designer                    = get_user_by( 'id', $user_id );
            $this->object                = $designer;
            $this->find['username']      = '{user_name}';
            $this->find['amount']        = '{amount}';
            $this->find['profile_url']   = '{profile_url}';
            $this->find['withdraw_page'] = '{withdraw_page}';
            $this->find['site_name']     = '{site_name}';
            $this->find['site_url']      = '{site_url}';
            $this->find['note']          = '{note}';
    
            $this->replace['username']      = $designer->user_login;
            $this->replace['amount']        = wc_price( $withdraw->amount );
            $this->replace['profile_url']   = wc_get_endpoint_url( 'my-store', '', wc_get_page_permalink( 'myaccount' ) );
            $this->replace['withdraw_page'] = add_query_arg( array( 'tab' => 'withdraw' ), wc_get_endpoint_url( 'my-store', '', wc_get_page_permalink( 'myaccount' ) ) );
            $this->replace['site_name']     = $this->get_from_name();
            $this->replace['site_url']      = site_url();
            $this->replace['note']          = $withdraw->note;
    
            $this->setup_locale();
            $this->send( $designer->user_email, $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
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
                'subject' => array(
                    'title'         => esc_html__( 'Subject', 'storelly-product-builder-for-woocommerce' ),
                    'type'          => 'text',
                    'desc_tip'      => true,
                    'description'   => sprintf( esc_html__( 'Available placeholders: %s', 'storelly-product-builder-for-woocommerce' ), '<code>{site_name},{amount},{user_name}</code>' ),
                    'placeholder'   => $this->get_default_subject(),
                    'default'       => '',
                ),
                'heading' => array(
                    'title'         => esc_html__( 'Email heading', 'storelly-product-builder-for-woocommerce' ),
                    'type'          => 'text',
                    'desc_tip'      => true,
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

return new SPBWC_Email_Withdraw_Cancelled();