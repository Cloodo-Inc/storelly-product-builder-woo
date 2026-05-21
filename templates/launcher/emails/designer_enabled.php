<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
do_action( 'woocommerce_email_header', $email_heading, $email );
?>
<p><?php echo( $email_title ); ?></p>
<p><?php echo( $email_description ); ?></p>
<p>
    <?php echo sprintf( esc_html__( 'You can <a href="%s" target="_blank">login here</a> ', 'storelly-product-builder-for-woocommerce' ), wc_get_page_permalink( 'myaccount' ) ) ; ?>
</p>
<?php
do_action( 'woocommerce_email_footer', $email );
