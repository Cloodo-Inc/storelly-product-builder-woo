<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
do_action( 'woocommerce_email_header', $email_heading, $email );
?>
<p><?php echo esc_html( $email_title ); ?></p>
<p><?php echo esc_html( $email_description ); ?></p>
<p>
    <?php
    $login_link = '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" target="_blank">' . esc_html__( 'login here', 'storelly-product-builder-for-woocommerce' ) . '</a>';
    /* translators: %s: login link wrapped in an <a> tag */
    printf( wp_kses_post( __( 'You can %s', 'storelly-product-builder-for-woocommerce' ) ), $login_link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $login_link is built from escaped components.
    ?>
</p>
<?php
do_action( 'woocommerce_email_footer', $email );
