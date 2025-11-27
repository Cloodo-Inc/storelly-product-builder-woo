<?php if (!defined('ABSPATH')) exit; ?>
<div class="pcpb-field-wrap" ng-repeat="(fieldIndex, field) in options.fields" id="{{field.id}}">
    <div class="nbd-nav">
        <div ng-dblclick="toggleExpandField($index, $event)" style="cursor: pointer;" title="<?php esc_html_e('Double click to expand option', 'spbwc-product-builder') ?>">
            <ul nbd-tab ng-class="field.isExpand ? '' : 'left'" class="nbd-tab-nav">
                <li class="pcpb-field-tab active" data-target="tab-general"><?php esc_html_e('General', 'spbwc-product-builder') ?></li>
                <li class="pcpb-field-tab" data-target="tab-appearance"><?php esc_html_e('Appearance', 'spbwc-product-builder') ?></li>
                <li ng-if="field.nbpb_type" class="pcpb-field-tab" data-target="tab-product-builder"><?php esc_html_e('Product builder', 'spbwc-product-builder'); ?></li>
            </ul>
            <input ng-hide="true" ng-model="field.id" name="options[fields][{{fieldIndex}}][id]" />
            <span class="pcpb-field-name" ng-class="[{true: '', false: 'left'}[field.isExpand], {'n': 'nbo_blur'}[field.general.enabled.value]]">
                <span>{{field.general.title.value}}</span>
            </span>
            <span class="nbstorelly-right field-action">
                <span class="nbo-type-label-wrap"><span class="nbo-type-label" ng-class="get_field_class( field.nbpb_type)">{{get_field_type( field.nbpb_type ) }}</span></span>
                <span class="nbo-sort-group">
                    <span ng-click="sort_field($index, 'up')" class="dashicons dashicons-arrow-up nbo-sort-up nbo-sort" title="<?php esc_html_e('Up', 'spbwc-product-builder') ?>"></span>
                    <span ng-click="sort_field($index, 'down')" class="dashicons dashicons-arrow-down nbo-sort-down nbo-sort" title="<?php esc_html_e('Down', 'spbwc-product-builder') ?>"></span>
                </span>
                <a class="pcpb-field-btn nbd-mini-btn button" ng-click="delete_field($index)" title="<?php esc_html_e('Delete', 'spbwc-product-builder'); ?>"><span class="dashicons dashicons-no-alt"></span></a>
                <a class="pcpb-field-btn nbd-mini-btn button" ng-click="copy_field($index)" title="<?php esc_html_e('Copy', 'spbwc-product-builder'); ?>"><span class="dashicons dashicons-admin-page"></span></a>
                <a class="pcpb-field-btn nbd-mini-btn button" ng-click="toggleExpandField($index, $event)" title="<?php esc_html_e('Expand', 'spbwc-product-builder'); ?>"><span ng-show="!field.isExpand" class="dashicons dashicons-arrow-down"></span><span ng-show="field.isExpand" class="dashicons dashicons-arrow-up"></span></a>
            </span>
        </div>
        <div class="clear"></div>
    </div>
    <ng-include src="'field_body'"></ng-include>
</div>
<div style="display: flex; justify-content: space-between;">
    <a style="background: rgba(170, 0, 0, 0.75);color: #fff;border-color: rgba(170, 0, 0, 0.75);" class="button" ng-click="clear_all_fields()"><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e('Clear All Fields', 'spbwc-product-builder'); ?></a>
    <a class="button button-primary" ng-click="add_field()"><span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add Field', 'spbwc-product-builder'); ?></a>
</div>
<?php
include 'templates/field-body.php';

include 'templates/nbpb_com.php';
include 'templates/nbpb_text.php';
include 'templates/nbpb_image.php';

include 'templates/field-body/title.php';
include 'templates/field-body/description.php';
include 'templates/field-body/data_type.php';
include 'templates/field-body/input_type.php';
include 'templates/field-body/input_option.php';
include 'templates/field-body/text_option.php';
include 'templates/field-body/upload_option.php';
include 'templates/field-body/enabled.php';
include 'templates/field-body/published.php';
include 'templates/field-body/required.php';
include 'templates/field-body/price_type.php';
include 'templates/field-body/price.php';
include 'templates/field-body/attributes.php';
