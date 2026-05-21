<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>
<p>
    <?php esc_html_e( 'Hi,', 'storelly-product-builder-for-woocommerce' ); ?>
</p>
<p>
    <?php esc_html_e( 'A new withdraw request has been made by', 'storelly-product-builder-for-woocommerce' ); ?> <?php echo esc_attr( $data ['username'] ); ?>.
</p>
<hr>
<ul>
    <li>
        <strong>
            <?php esc_html_e( 'Username : ', 'storelly-product-builder-for-woocommerce' ); ?>
        </strong>
        <?php
        printf( '<a href="%s">%s</a>', esc_attr( $data['profile_url'] ), esc_attr( $data['username'] ) ); ?>
    </li>
    <li>
        <strong>
            <?php esc_html_e( 'Request Amount:', 'storelly-product-builder-for-woocommerce' ); ?>
        </strong>
        <?php echo( $data['amount'] ); ?>
    </li>
</ul>

<?php echo sprintf( esc_html__( 'You can approve or deny it by going <a href="%s"> here </a>', 'storelly-product-builder-for-woocommerce' ), esc_attr( $data['withdraw_page'] ) ); ?>

<?php

do_action( 'woocommerce_email_footer', $email );