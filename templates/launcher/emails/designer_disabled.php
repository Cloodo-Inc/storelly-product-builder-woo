<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>
<p>
    <?php printf( esc_html__( 'Hello %s', 'storelly-product-builder-for-woocommerce' ), $data['display_name'] ); ?>
</p>
<p>
    <?php esc_html_e( 'Sorry, your designer account is deactivated.', 'storelly-product-builder-for-woocommerce' ); ?>
</p>
<p>
    <?php esc_html_e( 'You can\'t sell or upload design anymore. To activate your designer account please contact with the admin.', 'storelly-product-builder-for-woocommerce' ); ?>
</p>
<?php
do_action( 'woocommerce_email_footer', $email );