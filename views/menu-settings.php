<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly  

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$api_key_raw = maybe_unserialize(get_option('spbwc_connect_api_keys'));
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$api_key = is_array($api_key_raw) ? $api_key_raw : array();

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$sid = isset($api_key['consumer_key']) ? $api_key['consumer_key'] : '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$secret = isset($api_key['consumer_secret']) ? $api_key['consumer_secret'] : '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$unauth_token = isset($api_key['unauth_token']) ? $api_key['unauth_token'] : '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$storelly_username = isset($api_key['username']) ? $api_key['username'] : '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$api_log = isset($api_key['log']) ? $api_key['log'] : esc_html__('no logs', 'storelly-product-builder-for-woocommerce');
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$url_new_product = get_home_url() . '/wp-admin/post-new.php?post_type=product';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$stt_yes_cloud2print_api = isset($storelly_pb_settings['enable_cloud2print_api']) && $storelly_pb_settings['enable_cloud2print_api'] == 'yes' ? 'checked' : '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$stt_no_cloud2print_api = isset($storelly_pb_settings['enable_cloud2print_api']) && $storelly_pb_settings['enable_cloud2print_api'] == 'no' ? 'checked' : '';
?>

<div class="storelly-box">
    <h3 class="storelly-settings"><?php esc_html_e('Storelly settings', 'storelly-product-builder-for-woocommerce'); ?></h3>
    <hr>
    <?php
    if ($message && $status) {
        echo '<div id="message" class="inline ' . esc_attr($status) . '" style="margin-left: 0;"><p><strong>' . esc_html($message) . '</strong></p></div>';
    }
    ?>
    <form class="storelly-form" method="post" action="" enctype="multipart/form-data">
        <div class="content-form">
            <table class="form-table pc-table">
                <tbody>
                    <tr valign="top">
                        <th class="titledesc">
                            <label><?php esc_html_e('Enable Storelly cloud api to create PDF', 'storelly-product-builder-for-woocommerce'); ?><span class="storelly-help-tip"></span></label>
                        </th>
                        <td>
                            <p class="row">
                                <input type="radio" name="storelly_enable_cloud2print_api" value="yes" <?php echo esc_attr($stt_yes_cloud2print_api); ?> /><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?>
                            </p>
                            <p class="row">
                                <input type="radio" name="storelly_enable_cloud2print_api" value="no" <?php echo esc_attr($stt_no_cloud2print_api);  ?> /><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="box-key">
                <h4><?php esc_html_e('Manually enter an API Key', 'storelly-product-builder-for-woocommerce'); ?></h4>
                <hr />
                <div class="grup-box">
                    <div class="desc-key">
                        <p><?php esc_html_e('Sid : ', 'storelly-product-builder-for-woocommerce'); ?></p>
                    </div>
                    <div class="code-key">
                        <input name="storelly_consumer_key" placeholder="Code SID" value="<?php echo esc_attr($sid); ?>" />
                        <p><?php esc_html_e('Enter your storelly sid API Key', 'storelly-product-builder-for-woocommerce'); ?></p>
                    </div>
                </div>
                <div class="grup-box">
                    <div class="desc-key">
                        <p><?php esc_html_e('Secret :', 'storelly-product-builder-for-woocommerce'); ?></p>
                    </div>
                    <div class="code-key">
                        <input name="storelly_consumer_secret" placeholder="Code secret API" value="<?php  echo esc_attr($secret); ?>" />
                        <p><?php esc_html_e('Enter your storelly secret API Key', 'storelly-product-builder-for-woocommerce'); ?></p>
                    </div>
                </div>
                <div class="grup-box">
                    <div class="desc-key">
                        <p><?php esc_html_e('Unauth token :', 'storelly-product-builder-for-woocommerce'); ?></p>
                    </div>
                    <div class="code-key">
                        <input placeholder="" value="<?php echo esc_attr($unauth_token); ?>" disabled />
                        <p><?php esc_html_e('Unauth token off store Storelly (Automatically generated when you enter sid and secret)', 'storelly-product-builder-for-woocommerce'); ?></p>
                    </div>
                </div>
                <div class="grup-box">
                    <div class="desc-key">
                        <p><?php esc_html_e('User name', 'storelly-product-builder-for-woocommerce'); ?> :</p>
                    </div>
                    <div class="code-key">
                        <input placeholder="" value="<?php echo esc_attr($storelly_username); ?>" />
                    </div>
                </div>
                <div class="grup-box">
                    <div class="desc-key">
                        <p><?php esc_html_e('Password', 'storelly-product-builder-for-woocommerce'); ?> :</p>
                    </div>
                    <div class="code-key">
                        <input placeholder="" value="<?php echo esc_attr($storelly_username); ?>" />
                        <p class="desc_sync "><?php esc_html_e('Please log in with the above account and password', 'storelly-product-builder-for-woocommerce'); ?></p>
                    </div> 
                </div>
                
                <?php if ($storelly_username) : ?> 
                    <div class="grup-box">
                        <div class="desc-key">
                            <p><?php esc_html_e('Check connection to Storelly Dashboard :', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                        <div class="code-key">
                            <a href="https://app.storelly.com/login?redirect=woocomerce"><?php esc_html_e('Click To Login', 'storelly-product-builder-for-woocommerce'); ?></a>
                            <a href="<?php echo esc_url($url_new_product)?>"><?php esc_html_e('Create your first product', 'storelly-product-builder-for-woocommerce'); ?></a>
                            <p class="desc_sync "><?php esc_html_e('Login to sync products', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="grup-box">
                    <div class="desc-key">
                        <p><?php esc_html_e('Log', 'storelly-product-builder-for-woocommerce'); ?> :</p>
                    </div>
                    <div class="code-key">
                    <p><?php echo esc_html($api_log); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="submit">
            <button name="save" class="button-primary" type="submit" value="Save changes"><?php esc_html_e('Save changes', 'storelly-product-builder-for-woocommerce'); ?></button>
            <input type="hidden" name="_action_storelly_settings" value="submit">
            <?php wp_nonce_field( 'spbwc_settings_action', 'spbwc_settings_nonce' ); ?>
        </div>
    </form>
</div>