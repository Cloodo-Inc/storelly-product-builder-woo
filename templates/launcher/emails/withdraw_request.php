<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>
<p>
    <?php esc_html_e( 'Hi,', 'storelly-product-builder-for-woocommerce' ); ?>
</p>
<p>
    <?php esc_html_e( 'A new withdraw request has been made by', 'storelly-product-builder-for-woocommerce' ); ?> <?php echo esc_html( $data['username'] ); ?>.
</p>
<hr>
<ul>
    <li>
        <strong>
            <?php esc_html_e( 'Username : ', 'storelly-product-builder-for-woocommerce' ); ?>
        </strong>
        <?php
        printf( '<a href="%s">%s</a>', esc_url( $data['profile_url'] ), esc_html( $data['username'] ) );
        ?>
    </li>
    <li>
        <strong>
            <?php esc_html_e( 'Request Amount:', 'storelly-product-builder-for-woocommerce' ); ?>
        </strong>
        <?php echo wp_kses_post( is_numeric( $data['amount'] ) && function_exists( 'wc_price' ) ? wc_price( (float) $data['amount'] ) : (string) $data['amount'] ); ?>
    </li>
</ul>

<?php
$withdraw_link = '<a href="' . esc_url( $data['withdraw_page'] ) . '">' . esc_html__( 'here', 'storelly-product-builder-for-woocommerce' ) . '</a>';
/* translators: %s: link wrapped in an <a> tag */
printf( wp_kses_post( __( 'You can approve or deny it by going %s', 'storelly-product-builder-for-woocommerce' ) ), $withdraw_link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $withdraw_link is built from escaped components.
?>

<?php

do_action( 'woocommerce_email_footer', $email );