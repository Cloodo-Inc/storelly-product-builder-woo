<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

echo "= " . esc_attr( $email_heading ) . " =\n\n";
?>
<?php esc_attr_e( 'Hi,', 'storelly-product-builder-for-woocommerce' );  echo " \n";?>

<?php esc_attr_e( 'A new withdraw request has been made by - '.$data['username'], 'storelly-product-builder-for-woocommerce' );  echo " \n";?>

<?php esc_attr_e( 'Request Amount : '.$data['amount'], 'storelly-product-builder-for-woocommerce' );  echo " \n";?>

<?php esc_attr_e( 'Username : '.$data['username'], 'storelly-product-builder-for-woocommerce' );  echo " \n";?>
<?php esc_attr_e( 'Profile : '.$data['profile_url'], 'storelly-product-builder-for-woocommerce' );  echo " \n";?>

<?php esc_attr_e( 'You can approve or deny it by going here : '.$data['withdraw_page'], 'storelly-product-builder-for-woocommerce' );  echo " \n";?>

<?php
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );