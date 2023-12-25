<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly  
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
        <table class="form-table pc-table">
            <tbody>
                <tr valign="top">
                    <th class="titledesc">
                        <label><?php esc_html_e('Enable Storelly cloud api to create PDF', 'pc-product-builder'); ?><span class="storelly-help-tip"></span></label>
                    </th>
                    <td>
                        <p class="row">
                            <input type="radio" name="storelly_enable_cloud2print_api" value="yes" <?php echo esc_attr(isset($storelly_pb_settings['enable_cloud2print_api']) && $storelly_pb_settings['enable_cloud2print_api'] == 'yes' ? 'checked' : ''); ?> /><?php esc_html_e('Yes', 'pc-product-builder'); ?>
                        </p>
                        <p class="row">
                            <input type="radio" name="storelly_enable_cloud2print_api" value="no" <?php echo esc_attr(isset($storelly_pb_settings['enable_cloud2print_api']) && $storelly_pb_settings['enable_cloud2print_api'] == 'no' ? 'checked' : '');  ?> /><?php esc_html_e('No', 'pc-product-builder'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="submit">
            <button name="save" class="button-primary" type="submit" value="Save changes"><?php esc_html_e('Save changes', 'pc-product-builder'); ?></button>
            <input type="hidden" name="_action_storelly_settings" value="submit">
        </p>
    </form>
</div>