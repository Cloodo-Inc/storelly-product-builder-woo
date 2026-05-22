<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "= " . esc_html( $email_heading ) . " =\n\n";

esc_html_e( 'Hi,', 'storelly-product-builder-for-woocommerce' );
echo "\n\n";

/* translators: %s: designer username */
printf( esc_html__( 'A new withdraw request has been made by - %s', 'storelly-product-builder-for-woocommerce' ), esc_html( $data['username'] ) );
echo "\n\n";

esc_html_e( 'Request Amount : ', 'storelly-product-builder-for-woocommerce' );
echo esc_html( (string) $data['amount'] );
echo "\n";

esc_html_e( 'Username : ', 'storelly-product-builder-for-woocommerce' );
echo esc_html( $data['username'] );
echo "\n";

esc_html_e( 'Profile : ', 'storelly-product-builder-for-woocommerce' );
echo esc_url( $data['profile_url'] );
echo "\n\n";

esc_html_e( 'You can approve or deny it by going here : ', 'storelly-product-builder-for-woocommerce' );
echo esc_url( $data['withdraw_page'] );
echo "\n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
