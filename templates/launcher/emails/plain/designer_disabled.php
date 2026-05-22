<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "= " . esc_html( $email_heading ) . " =\n\n";

echo "------------------------------------------------------------\n\n";

/* translators: %s: designer display name */
printf( esc_html__( 'Hello %s', 'storelly-product-builder-for-woocommerce' ), esc_html( $data['display_name'] ) );
echo "\n\n";

echo "------------------------------------------------------------\n\n";

esc_html_e( 'Sorry, your designer account is deactivated.', 'storelly-product-builder-for-woocommerce' );
echo "\n\n";

esc_html_e( "You can't sell or upload design anymore. To activate your designer account please contact with the admin.", 'storelly-product-builder-for-woocommerce' );
echo "\n\n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
