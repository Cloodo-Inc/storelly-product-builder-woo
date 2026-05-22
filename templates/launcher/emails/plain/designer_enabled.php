<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo esc_html( $email_heading ) . "\n\n";
echo esc_html( $email_title ) . "\n\n";
echo esc_html( $email_description ) . "\n\n";
echo "\n----------------------------------------------------\n\n";
echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );