<?php if (!defined('ABSPATH')) exit; ?>

<form class="nbdl-form" method="post">
    <?php wp_nonce_field( 'spbwc_marketplace_withdraw', 'nbdl_withdraw_nonce' ); ?>
    <div class="nbdl-form-group">
        <label for="withdraw-amount" class="nbdl-form-label">
            <?php esc_html_e( 'Withdraw Amount', 'storelly-product-builder-for-woocommerce' ); ?>
        </label>
        <input name="witdraw_amount" required step="any"  min="0" class="nbdl-form-input" id="withdraw-amount" name="price" type="number" placeholder="0.00" value="" />
    </div>
    <div class="nbdl-form-group">
        <input type="submit" class="button nbdl-form-submit" value="<?php esc_attr_e( 'Submit Request', 'storelly-product-builder-for-woocommerce' ); ?>" name="nbdl_withdraw_submit">
    </div>
</form>