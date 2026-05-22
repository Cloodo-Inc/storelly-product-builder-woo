<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
echo "= " . esc_html( $email_heading ) . " =\n\n";

/* translators: %s: designer username */
printf( esc_html__( 'Hi %s', 'storelly-product-builder-for-woocommerce' ), esc_html( $data['username'] ) );
echo "\n\n";

esc_html_e( 'Your withdraw request has been approved, congrats!', 'storelly-product-builder-for-woocommerce' );
echo "\n\n";

esc_html_e( 'You sent a withdraw request of:', 'storelly-product-builder-for-woocommerce' );
echo "\n";

esc_html_e( 'Amount : ', 'storelly-product-builder-for-woocommerce' );
echo esc_html( (string) $data['amount'] );
echo "\n\n";

esc_html_e( "We'll transfer this amount to your preferred destination shortly.", 'storelly-product-builder-for-woocommerce' );
echo "\n";

esc_html_e( 'Thanks for being with us.', 'storelly-product-builder-for-woocommerce' );
echo "\n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
