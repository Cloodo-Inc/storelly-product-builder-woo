<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly  
?>
<?php
$api_key = maybe_unserialize(get_option('storelly_connect_api_keys'));

$sid = isset($api_key['consumer_key']) ? $api_key['consumer_key'] : '';
$secret = isset($api_key['consumer_secret']) ? $api_key['consumer_secret'] : '';
$url_new_product = get_home_url() . '/wp-admin/post-new.php?post_type=product';
$stt_yes_cloud2print_api = isset($storelly_pb_settings['enable_cloud2print_api']) && $storelly_pb_settings['enable_cloud2print_api'] == 'yes' ? 'checked' : '';
$stt_no_cloud2print_api = isset($storelly_pb_settings['enable_cloud2print_api']) && $storelly_pb_settings['enable_cloud2print_api'] == 'no' ? 'checked' : '';
?>

<div class="storelly-box">
    <h3 class="storelly-settings"><?php esc_html_e('Storelly settings', 'pc-product-builder'); ?></h3>
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
                            <label><?php esc_html_e('Enable Storelly cloud api to create PDF', 'pc-product-builder'); ?><span class="storelly-help-tip"></span></label>
                        </th>
                        <td>
                            <p class="row">
                                <input type="radio" name="storelly_enable_cloud2print_api" value="yes" <?php echo esc_attr($stt_yes_cloud2print_api); ?> /><?php esc_html_e('Yes', 'pc-product-builder'); ?>
                            </p>
                            <p class="row">
                                <input type="radio" name="storelly_enable_cloud2print_api" value="no" <?php echo esc_attr($stt_no_cloud2print_api);  ?> /><?php esc_html_e('No', 'pc-product-builder'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="box-key">
                <h4><?php esc_html_e('Manually enter an API Key', 'pc-product-builder'); ?></h4>
                <hr />
                <div class="grup-box">
                    <div class="desc-key">
                        <p><?php esc_html_e('Sid : ', 'pc-product-builder'); ?></p>
                    </div>
                    <div class="code-key">
                        <input placeholder="Code SID" value="<?php echo esc_attr($sid); ?>" />
                        <p><?php esc_html_e('Enter your storelly sid API Key', 'pc-product-builder'); ?></p>
                    </div>
                </div>
                <div class="grup-box">
                    <div class="desc-key">
                        <p><?php esc_html_e('Secret :', 'pc-product-builder'); ?></p>
                    </div>
                    <div class="code-key">
                        <input placeholder="Code secret API" value="<?php  echo esc_attr($secret); ?>" />
                        <p><?php esc_html_e('Enter your storelly secret API Key', 'pc-product-builder'); ?></p>
                    </div>
                </div>
                <div class="grup-box">
                    <div class="desc-key">
                        <p><?php esc_html_e('Unauth token :', 'pc-product-builder'); ?></p>
                    </div>
                    <div class="code-key">
                        <input placeholder="" value="<?php echo esc_attr($api_key['unauth_token'] ?? ''); ?>" disabled />
                        <p><?php esc_html_e('Unauth token off store Storelly (Automatically generated when you enter calid sid end secret)', 'pc-product-builder'); ?></p>
                    </div>
                </div>
                <div class="grup-box">
                    <div class="desc-key">
                        <p><?php esc_html_e('User name', 'pc-product-builder'); ?> :</p>
                    </div>
                    <div class="code-key">
                        <input placeholder="" value="<?php echo esc_attr($api_key['username'] ?? ''); ?>" />
                    </div>
                </div>
                <div class="grup-box">
                    <div class="desc-key">
                        <p><?php esc_html_e('Password', 'pc-product-builder'); ?> :</p>
                    </div>
                    <div class="code-key">
                        <input placeholder="" value="<?php echo esc_attr($api_key['username'] ?? ''); ?>" />
                        <p class="desc_sync "><?php esc_html_e('Please log in with the above account and password', 'pc-product-builder'); ?></p>
                    </div> 
                </div>

                <?php if (!empty($api_key['username'])) : ?>
                    <div class="grup-box">
                        <div class="desc-key">
                            <p><?php esc_html_e('Check connection to Storelly Dashboard :', 'pc-product-builder'); ?></p>
                        </div>
                        <div class="code-key">
                            <a href="https://dashboard.storelly.com/login?redirect=woocomerce"><?php esc_html_e('Click To Login', 'pc-product-builder'); ?></a>
                            <a href="<?php echo esc_url($url_new_product)?>"><?php esc_html_e('Create your first product', 'pc-product-builder'); ?></a>
                            <p class="desc_sync "><?php esc_html_e('Login to sync products', 'pc-product-builder'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="grup-box">
                    <div class="desc-key">
                        <p><?php esc_html_e('Log', 'pc-product-builder'); ?> :</p>
                    </div>
                    <div class="code-key">
                    <p><?php  if (isset($api_key['log'])) { 
                            echo esc_html($api_key['log']);  
                        } else {
                            echo esc_html("no logs"); 
                        } ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="submit">
            <button name="save" class="button-primary" type="submit" value="Save changes"><?php esc_html_e('Save changes', 'pc-product-builder'); ?></button>
            <input type="hidden" name="_action_storelly_settings" value="submit">
        </div>
    </form>
</div>