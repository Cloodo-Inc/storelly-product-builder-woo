<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Get API key data
$api_key_raw = get_option('spbwc_connect_api_keys');
$api_key = is_array($api_key_raw) ? $api_key_raw : array();

$consumer_key      = isset($api_key['consumer_key']) ? $api_key['consumer_key'] : '';
$consumer_secret   = isset($api_key['consumer_secret']) ? $api_key['consumer_secret'] : '';
$unauth_token      = isset($api_key['unauth_token']) ? $api_key['unauth_token'] : '';
$storelly_username = isset($api_key['username']) ? $api_key['username'] : '';
$is_connected      = !empty($unauth_token) && !empty($storelly_username);

// Get other settings
$storelly_pb_settings = get_option('spbwc_pb_settings', array());
$enable_cloud2print_api = isset($storelly_pb_settings['enable_cloud2print_api']) ? $storelly_pb_settings['enable_cloud2print_api'] : 'no';

?>
<div class="wrap storelly-wrap">
    <h1><?php esc_html_e('Storelly Settings', 'storelly-product-builder-for-woocommerce'); ?></h1>

    <div id="storelly-admin-notices"></div>

    <div class="storelly-box">
        <h2 class="nav-tab-wrapper">
            <a href="#connect" class="nav-tab nav-tab-active"><?php esc_html_e('Connection', 'storelly-product-builder-for-woocommerce'); ?></a>
            <a href="#general" class="nav-tab"><?php esc_html_e('General', 'storelly-product-builder-for-woocommerce'); ?></a>
        </h2>

        <div id="connect" class="tab-content active">
            <h3><?php esc_html_e('Connect to Storelly', 'storelly-product-builder-for-woocommerce'); ?></h3>
            <p><?php esc_html_e('Connect your store to the Storelly dashboard to sync orders and manage designs.', 'storelly-product-builder-for-woocommerce'); ?></p>

            <table class="form-table">
                <tbody>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label><?php esc_html_e('Connection Status', 'storelly-product-builder-for-woocommerce'); ?></label>
                        </th>
                        <td class="forminp">
                            <?php if ($is_connected) : ?>
                                <p style="color: #2271b1;">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <strong><?php printf(esc_html__('Connected as %s', 'storelly-product-builder-for-woocommerce'), esc_html($storelly_username)); ?></strong>
                                </p>
                                <button id="storelly-disconnect" class="button button-secondary"><?php esc_html_e('Disconnect', 'storelly-product-builder-for-woocommerce'); ?></button>
                            <?php else : ?>
                                <p style="color: #d63638;">
                                    <span class="dashicons dashicons-warning"></span>
                                    <strong><?php esc_html_e('Not Connected', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <hr/>

            <h4><?php esc_html_e('Connection Method', 'storelly-product-builder-for-woocommerce'); ?></h4>

            <div class="storelly-connect-method">
                <button id="storelly-auto-connect" class="button button-primary">
                    <?php esc_html_e('Generate Keys & Connect Automatically', 'storelly-product-builder-for-woocommerce'); ?>
                </button>
                <p class="description"><?php esc_html_e('Recommended. This will automatically generate WooCommerce API keys and connect to your Storelly account.', 'storelly-product-builder-for-woocommerce'); ?></p>
            </div>

            <div class="storelly-connect-method">
                <h4><?php esc_html_e('Or connect manually', 'storelly-product-builder-for-woocommerce'); ?></h4>
                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label for="storelly_consumer_key"><?php esc_html_e('WooCommerce Consumer Key', 'storelly-product-builder-for-woocommerce'); ?></label>
                            </th>
                            <td class="forminp">
                                <input type="text" id="storelly_consumer_key" name="storelly_consumer_key" class="regular-text" value="<?php echo esc_attr($consumer_key); ?>" placeholder="ck_...">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label for="storelly_consumer_secret"><?php esc_html_e('WooCommerce Consumer Secret', 'storelly-product-builder-for-woocommerce'); ?></label>
                            </th>
                            <td class="forminp">
                                <input type="password" id="storelly_consumer_secret" name="storelly_consumer_secret" class="regular-text" value="<?php echo esc_attr($consumer_secret); ?>" placeholder="cs_...">
                            </td>
                        </tr>
                    </tbody>
                </table>
                <button id="storelly-manual-connect" class="button button-secondary"><?php esc_html_e('Save & Connect', 'storelly-product-builder-for-woocommerce'); ?></button>
            </div>
        </div>

        <div id="general" class="tab-content">
            <h3><?php esc_html_e('General Settings', 'storelly-product-builder-for-woocommerce'); ?></h3>
            <form id="storelly-general-settings-form">
                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('Enable Storelly Cloud PDF API', 'storelly-product-builder-for-woocommerce'); ?></label>
                            </th>
                            <td class="forminp">
                                <fieldset>
                                    <label>
                                        <input type="radio" name="storelly_enable_cloud2print_api" value="yes" <?php checked($enable_cloud2print_api, 'yes'); ?> />
                                        <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="radio" name="storelly_enable_cloud2print_api" value="no" <?php checked($enable_cloud2print_api, 'no'); ?> />
                                        <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Changes', 'storelly-product-builder-for-woocommerce'); ?></button>
                </p>
            </form>
        </div>
    </div>
    <?php wp_nonce_field('spbwc_connect_action', 'spbwc_connect_nonce'); ?>
</div>
