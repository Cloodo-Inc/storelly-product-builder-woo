<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>
<p>
    <?php
    /* translators: %s: designer username */
    printf( esc_html__( 'Hi %s', 'storelly-product-builder-for-woocommerce' ), esc_html( $data['username'] ) );
    ?>
</p>
<p>
    <?php esc_html_e( 'Your withdraw request has been approved, congrats!', 'storelly-product-builder-for-woocommerce' ); ?>
</p>
<p>
    <?php esc_html_e( 'You sent a withdraw request of:', 'storelly-product-builder-for-woocommerce' ); ?>
    <br>
    <?php esc_html_e( 'Amount : ', 'storelly-product-builder-for-woocommerce' ); ?>
    <?php echo wp_kses_post( is_numeric( $data['amount'] ) && function_exists( 'wc_price' ) ? wc_price( (float) $data['amount'] ) : (string) $data['amount'] ); ?>
</p>
<p>
    <?php esc_html_e( 'We\'ll transfer this amount to your preferred payment method shortly.', 'storelly-product-builder-for-woocommerce' ); ?>

    <?php esc_html_e( 'Thanks for being with us.', 'storelly-product-builder-for-woocommerce' ); ?>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );