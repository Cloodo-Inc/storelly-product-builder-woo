<?php
/**
 * Marketplace admin — Designer edit form.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$designer_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$user        = $designer_id ? get_userdata( $designer_id ) : null;

if ( ! $user ) {
    echo '<p>' . esc_html__( 'Designer not found.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
    return;
}

// Save handler.
$notice = '';
if ( isset( $_POST['spbwc_marketplace_save_designer'] ) ) {
    if ( ! current_user_can( SPBWC_Marketplace_Admin::CAPABILITY ) ) {
        wp_die( esc_html__( 'Permission denied.', 'storelly-product-builder-for-woocommerce' ) );
    }
    check_admin_referer( 'spbwc_marketplace_save_designer_' . $designer_id );

    $profile = get_user_meta( $designer_id, 'spbwc_marketplace_profile', true );
    if ( ! is_array( $profile ) ) {
        $profile = array();
    }

    $fields_text = array(
        'nbd_artist_name', 'nbd_artist_phone', 'nbd_artist_address',
        'nbd_artist_facebook', 'nbd_artist_twitter', 'nbd_artist_linkedin',
        'nbd_artist_youtube', 'nbd_artist_instagram', 'nbd_artist_flickr',
        'nbd_artist_commission', 'nbd_artist_commission2',
        'nbd_artist_commission_type', 'nbd_payment',
    );
    foreach ( $fields_text as $key ) {
        $profile[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
    }
    $profile['nbd_artist_description'] = isset( $_POST['nbd_artist_description'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['nbd_artist_description'] ) )
        : '';
    $profile['nbd_artist_banner'] = isset( $_POST['nbd_artist_banner'] ) ? absint( wp_unslash( $_POST['nbd_artist_banner'] ) ) : 0;
    $profile['gravatar']          = isset( $_POST['gravatar'] ) ? absint( wp_unslash( $_POST['gravatar'] ) ) : 0;

    update_user_meta( $designer_id, 'spbwc_marketplace_profile', $profile );

    if ( ! empty( $_POST['spbwc_marketplace_sell'] ) ) {
        update_user_meta( $designer_id, 'spbwc_marketplace_sell', 'on' );
    } else {
        delete_user_meta( $designer_id, 'spbwc_marketplace_sell' );
    }
    if ( ! empty( $_POST['spbwc_marketplace_featured'] ) ) {
        update_user_meta( $designer_id, 'spbwc_marketplace_featured', 'on' );
    } else {
        delete_user_meta( $designer_id, 'spbwc_marketplace_featured' );
    }

    $notice = __( 'Designer profile saved.', 'storelly-product-builder-for-woocommerce' );
}

$profile = get_user_meta( $designer_id, 'spbwc_marketplace_profile', true );
$profile = is_array( $profile ) ? $profile : array();

$get = function ( $key, $default = '' ) use ( $profile ) {
    return isset( $profile[ $key ] ) ? $profile[ $key ] : $default;
};
$sell     = (bool) get_user_meta( $designer_id, 'spbwc_marketplace_sell', true );
$featured = (bool) get_user_meta( $designer_id, 'spbwc_marketplace_featured', true );
$back_url = SPBWC_Marketplace_Admin::get_instance()->get_tab_url( 'designers' );
$commission_type = $get( 'nbd_artist_commission_type', 'percentage' );
?>
<?php if ( '' !== $notice ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
<?php endif; ?>
<h2><?php echo esc_html( sprintf( /* translators: %s: designer display name */ __( 'Edit designer: %s', 'storelly-product-builder-for-woocommerce' ), $user->display_name ) ); ?></h2>
<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to designers', 'storelly-product-builder-for-woocommerce' ); ?></a></p>

<form method="post">
    <?php wp_nonce_field( 'spbwc_marketplace_save_designer_' . $designer_id ); ?>
    <input type="hidden" name="spbwc_marketplace_save_designer" value="1" />

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="spbwc_marketplace_sell"><?php esc_html_e( 'Selling enabled', 'storelly-product-builder-for-woocommerce' ); ?></label></th>
            <td><label><input type="checkbox" id="spbwc_marketplace_sell" name="spbwc_marketplace_sell" value="1" <?php checked( $sell ); ?> /> <?php esc_html_e( 'Allow this user to sell designs in the marketplace.', 'storelly-product-builder-for-woocommerce' ); ?></label></td>
        </tr>
        <tr>
            <th scope="row"><label for="spbwc_marketplace_featured"><?php esc_html_e( 'Featured designer', 'storelly-product-builder-for-woocommerce' ); ?></label></th>
            <td><label><input type="checkbox" id="spbwc_marketplace_featured" name="spbwc_marketplace_featured" value="1" <?php checked( $featured ); ?> /> <?php esc_html_e( 'Surface this designer in featured listings/shortcodes.', 'storelly-product-builder-for-woocommerce' ); ?></label></td>
        </tr>
        <tr>
            <th scope="row"><label for="nbd_artist_name"><?php esc_html_e( 'Artist name', 'storelly-product-builder-for-woocommerce' ); ?></label></th>
            <td><input type="text" id="nbd_artist_name" name="nbd_artist_name" value="<?php echo esc_attr( $get( 'nbd_artist_name' ) ); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="nbd_artist_phone"><?php esc_html_e( 'Phone', 'storelly-product-builder-for-woocommerce' ); ?></label></th>
            <td><input type="text" id="nbd_artist_phone" name="nbd_artist_phone" value="<?php echo esc_attr( $get( 'nbd_artist_phone' ) ); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="nbd_artist_address"><?php esc_html_e( 'Address', 'storelly-product-builder-for-woocommerce' ); ?></label></th>
            <td><input type="text" id="nbd_artist_address" name="nbd_artist_address" value="<?php echo esc_attr( $get( 'nbd_artist_address' ) ); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="nbd_artist_description"><?php esc_html_e( 'Bio / description', 'storelly-product-builder-for-woocommerce' ); ?></label></th>
            <td><textarea id="nbd_artist_description" name="nbd_artist_description" rows="4" class="large-text"><?php echo esc_textarea( $get( 'nbd_artist_description' ) ); ?></textarea></td>
        </tr>

        <tr>
            <th scope="row"><label for="nbd_artist_commission_type"><?php esc_html_e( 'Commission type', 'storelly-product-builder-for-woocommerce' ); ?></label></th>
            <td>
                <select id="nbd_artist_commission_type" name="nbd_artist_commission_type">
                    <option value="percentage" <?php selected( $commission_type, 'percentage' ); ?>><?php esc_html_e( 'Percentage', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="flat"       <?php selected( $commission_type, 'flat' ); ?>><?php esc_html_e( 'Flat', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="combine"    <?php selected( $commission_type, 'combine' ); ?>><?php esc_html_e( 'Combine (percent | flat)', 'storelly-product-builder-for-woocommerce' ); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="nbd_artist_commission"><?php esc_html_e( 'Commission', 'storelly-product-builder-for-woocommerce' ); ?></label></th>
            <td><input type="text" id="nbd_artist_commission" name="nbd_artist_commission" value="<?php echo esc_attr( $get( 'nbd_artist_commission' ) ); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="nbd_artist_commission2"><?php esc_html_e( 'Commission (combine)', 'storelly-product-builder-for-woocommerce' ); ?></label></th>
            <td><input type="text" id="nbd_artist_commission2" name="nbd_artist_commission2" value="<?php echo esc_attr( $get( 'nbd_artist_commission2', '0|0' ) ); ?>" class="regular-text" placeholder="0|0" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="nbd_payment"><?php esc_html_e( 'Payment method', 'storelly-product-builder-for-woocommerce' ); ?></label></th>
            <td><input type="text" id="nbd_payment" name="nbd_payment" value="<?php echo esc_attr( $get( 'nbd_payment' ) ); ?>" class="regular-text" /></td>
        </tr>

        <tr><th colspan="2"><h3><?php esc_html_e( 'Social', 'storelly-product-builder-for-woocommerce' ); ?></h3></th></tr>
        <?php foreach ( array(
            'nbd_artist_facebook'   => __( 'Facebook URL', 'storelly-product-builder-for-woocommerce' ),
            'nbd_artist_twitter'    => __( 'Twitter / X URL', 'storelly-product-builder-for-woocommerce' ),
            'nbd_artist_linkedin'   => __( 'LinkedIn URL', 'storelly-product-builder-for-woocommerce' ),
            'nbd_artist_instagram'  => __( 'Instagram URL', 'storelly-product-builder-for-woocommerce' ),
            'nbd_artist_youtube'    => __( 'YouTube URL', 'storelly-product-builder-for-woocommerce' ),
            'nbd_artist_flickr'     => __( 'Flickr URL', 'storelly-product-builder-for-woocommerce' ),
        ) as $field => $label ) : ?>
            <tr>
                <th scope="row"><label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label></th>
                <td><input type="url" id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $get( $field ) ); ?>" class="regular-text" /></td>
            </tr>
        <?php endforeach; ?>

        <tr><th colspan="2"><h3><?php esc_html_e( 'Media', 'storelly-product-builder-for-woocommerce' ); ?></h3></th></tr>
        <tr>
            <th scope="row"><label for="nbd_artist_banner"><?php esc_html_e( 'Banner (attachment ID)', 'storelly-product-builder-for-woocommerce' ); ?></label></th>
            <td><input type="number" id="nbd_artist_banner" name="nbd_artist_banner" value="<?php echo esc_attr( $get( 'nbd_artist_banner', 0 ) ); ?>" class="small-text" min="0" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="gravatar"><?php esc_html_e( 'Avatar (attachment ID)', 'storelly-product-builder-for-woocommerce' ); ?></label></th>
            <td><input type="number" id="gravatar" name="gravatar" value="<?php echo esc_attr( $get( 'gravatar', 0 ) ); ?>" class="small-text" min="0" /></td>
        </tr>
    </table>

    <?php submit_button( __( 'Save designer', 'storelly-product-builder-for-woocommerce' ) ); ?>
</form>
