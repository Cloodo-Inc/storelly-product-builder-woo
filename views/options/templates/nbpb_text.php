<?php if (!defined('ABSPATH')) exit; ?>
<?php echo '<script type="text/ng-template" id="nbd.nbpb_text">'; ?>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <div><b><?php esc_html_e('Default text', 'pc-product-builder'); ?></b></div>
    </div>
    <div class="pcpb-field-info-2">
        <input type="text" ng-model="field.general.nbpb_text_configs.default_text" name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][default_text]" />
    </div>
</div>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <div><b><?php esc_html_e('Allow change font family', 'pc-product-builder'); ?></b></div>
    </div>
    <div class="pcpb-field-info-2">
        <select name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][allow_font_family]" ng-model="field.general.nbpb_text_configs.allow_font_family">
            <option value="y"><?php esc_html_e('Yes', 'pc-product-builder'); ?></option>
            <option value="n"><?php esc_html_e('No', 'pc-product-builder'); ?></option>
        </select>
    </div>
</div>
<div class="pcpb-field-info" ng-show="field.general.nbpb_text_configs.allow_font_family == 'y'">
    <div class="pcpb-field-info-1">
        <div><b><?php esc_html_e('Allow all fonts', 'pc-product-builder'); ?></b></div>
    </div>
    <div class="pcpb-field-info-2">
        <select name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][allow_all_font]" ng-model="field.general.nbpb_text_configs.allow_all_font">
            <option value="y"><?php esc_html_e('Yes', 'pc-product-builder'); ?></option>
            <option value="n"><?php esc_html_e('No', 'pc-product-builder'); ?></option>
        </select>
        <br /><?php esc_html_e('Manage fonts', 'pc-product-builder'); ?> <a target="_blank" href="<?php echo esc_url(admin_url('admin.php?page=nbdesigner_manager_fonts')); ?>"><?php esc_html_e('here', 'pc-product-builder'); ?></a>
    </div>
</div>
<div class="pcpb-field-info" ng-show="field.general.nbpb_text_configs.allow_font_family == 'y' && field.general.nbpb_text_configs.allow_all_font == 'n'">
    <div class="pcpb-field-info-1">
        <div><b><?php esc_html_e('Custom fonts', 'pc-product-builder'); ?></b></div>
    </div>
    <div class="pcpb-field-info-2">
        <?php
        $custom_fonts = array();
        if (file_exists(PRINTCART_PB_ASSETS_DIR . '/fonts.json')) {
            $custom_fonts = (array)json_decode(file_get_contents(PRINTCART_PB_ASSETS_DIR . '/fonts.json'));
        }
        ?>
        <select nbd-select2 name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][custom_fonts][]" ng-model="field.general.nbpb_text_configs.custom_fonts" multiple="multiple">
            <?php foreach ($custom_fonts as $font) : ?>
                <?php
                $font_id = $font->id;
                $font_name = $font->name;
                if ($enable_printcart_api) {
                    $font_id = isset($font['id']) ? $font['id'] : '';
                    $font_name = isset($font['name']) ? $font['name'] : '';
                }
                ?>
                <option value="<?php echo ($font_id); ?>"><?php echo ($font_name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="pcpb-field-info" ng-show="field.general.nbpb_text_configs.allow_font_family == 'y' && field.general.nbpb_text_configs.allow_all_font == 'n'">
    <div class="pcpb-field-info-1">
        <div><b><?php esc_html_e('Google fonts', 'pc-product-builder'); ?></b></div>
    </div>
    <div class="pcpb-field-info-2">
        <?php
        $google_fonts = array();
        if (file_exists(PRINTCART_PB_DATA_CONFIG_DIR . '/googlefonts.json')) {
            $google_fonts = (array)json_decode(file_get_contents(PRINTCART_PB_DATA_CONFIG_DIR . '/googlefonts.json'));
        }
        ?>
        <select nbd-select2 name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][google_fonts][]" ng-model="field.general.nbpb_text_configs.google_fonts" multiple="multiple">
            <?php foreach ($google_fonts as $font) : ?>
                <option value="<?php echo ($font->id); ?>"><?php echo ($font->name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <div><b><?php esc_html_e('Allow change color', 'pc-product-builder'); ?></b></div>
    </div>
    <div class="pcpb-field-info-2">
        <select name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][allow_change_color]" ng-model="field.general.nbpb_text_configs.allow_change_color">
            <option value="y"><?php esc_html_e('Yes', 'pc-product-builder'); ?></option>
            <option value="n"><?php esc_html_e('No', 'pc-product-builder'); ?></option>
        </select>
    </div>
</div>
<div class="pcpb-field-info" ng-show="field.general.nbpb_text_configs.allow_change_color == 'y'">
    <div class="pcpb-field-info-1">
        <div><b><?php esc_html_e('Allow all colors', 'pc-product-builder'); ?></b></div>
    </div>
    <div class="pcpb-field-info-2">
        <select name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][allow_all_color]" ng-model="field.general.nbpb_text_configs.allow_all_color">
            <option value="y"><?php esc_html_e('Yes', 'pc-product-builder'); ?></option>
            <option value="n"><?php esc_html_e('No', 'pc-product-builder'); ?></option>
        </select>
    </div>
</div>
<div class="pcpb-field-info" ng-show="field.general.nbpb_text_configs.allow_change_color == 'y' && field.general.nbpb_text_configs.allow_all_color == 'n'">
    <div class="pcpb-field-info-1">
        <div><b><?php esc_html_e('Colors', 'pc-product-builder'); ?></b></div>
    </div>
    <div class="pcpb-field-info-2">
        <div class="nbd-table-wrap">
            <table class="nbd-table nbpb-text-configs" style="text-align: center;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Color name', 'pc-product-builder'); ?></th>
                        <th><?php esc_html_e('Color', 'pc-product-builder'); ?></th>
                        <th><?php esc_html_e('Action', 'pc-product-builder'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr ng-repeat="(clIndex, color) in field.general.nbpb_text_configs.colors">
                        <td>
                            <input type="text" class="nbd-short-ip" ng-model="color.name" name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][colors][{{clIndex}}][name]" />
                        </td>
                        <td>
                            <input type="text" class="nbd-short-ip" nbd-color-picker="color.color" ng-model="color.color" name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][colors][{{clIndex}}][color]" />
                        </td>
                        <td>
                            <a class="button nbd-mini-btn" ng-click="remove_text_configs_color(fieldIndex, clIndex)" title="<?php esc_html_e('Delete', 'pc-product-builder'); ?>"><span class="dashicons dashicons-no-alt"></span></a>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: left;">
                            <a ng-click="add_text_configs_color(fieldIndex)" class="button button-primary"><?php esc_html_e('Add color', 'pc-product-builder'); ?></a>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<div class="pcpb-field-info">
    <div class="pcpb-field-info-1">
        <div><b><?php esc_html_e('Show in view', 'pc-product-builder'); ?></b></div>
    </div>
    <div class="pcpb-field-info-2">
        <div class="nbd-table-wrap">
            <table class="nbd-table" style="text-align: center;">
                <thead>
                    <tr>
                        <th ng-repeat="view in options.views">{{view.name}}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td ng-repeat="view in options.views">
                            <input ng-model="field.general.nbpb_text_configs.views[$index].display" name="options[fields][{{fieldIndex}}][general][nbpb_text_configs][views][{{$index}}][display]" type="checkbox" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php echo '</script>';
