<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly  
?>
<div class="printcart-box">
    <h3 class="printcart-settings"><?php esc_html_e('Printcart settings', 'pc-product-builder'); ?></h3>
    <hr>
    <?php
    if ($message && $status) {
        echo '<div id="message" class="inline ' . esc_attr($status) . '" style="margin-left: 0;"><p><strong>' . esc_html($message) . '</strong></p></div>';
    }
    ?>
    <form class="printcart-form" method="post" action="" enctype="multipart/form-data">
        <table class="form-table pc-table">
            <tbody>
                <tr valign="top">
                    <th class="titledesc">
                        <label><?php esc_html_e('Enable Printcart cloud api to create PDF', 'pc-product-builder'); ?><span class="printcart-help-tip"></span></label>
                    </th>
                    <td>
                        <p class="row">
                            <input type="radio" name="printcart_enable_cloud2print_api" value="yes" <?php echo esc_attr(isset($printcart_pb_settings['enable_cloud2print_api']) && $printcart_pb_settings['enable_cloud2print_api'] == 'yes' ? 'checked' : ''); ?> /><?php esc_html_e('Yes', 'pc-product-builder'); ?>
                        </p>
                        <p class="row">
                            <input type="radio" name="printcart_enable_cloud2print_api" value="no" <?php echo esc_attr(isset($printcart_pb_settings['enable_cloud2print_api']) && $printcart_pb_settings['enable_cloud2print_api'] == 'no' ? 'checked' : '');  ?> /><?php esc_html_e('No', 'pc-product-builder'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="submit">
            <button name="save" class="button-primary" type="submit" value="Save changes"><?php esc_html_e('Save changes', 'pc-product-builder'); ?></button>
            <input type="hidden" name="_action_printcart_settings" value="submit">
        </p>
    </form>
</div>