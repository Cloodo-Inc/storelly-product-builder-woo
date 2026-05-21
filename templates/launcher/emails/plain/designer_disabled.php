if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "= " . $email_heading . " =\n\n";
?>

------------------------------------------------------------

<?php printf( esc_html__( 'Hello %s', 'storelly-product-builder-for-woocommerce' ), $data['display_name'] ); echo " \n\n";  ?>

------------------------------------------------------------

<?php 
esc_html_e( 'Sorry, your designer account is deactivated.', 'storelly-product-builder-for-woocommerce' ); 
echo " \n\n";
esc_html_e( 'You can\'t sell or upload design anymore. To activate your designer account please contact with the admin.', 'storelly-product-builder-for-woocommerce' );
echo " \n\n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );