<?php if (!defined('ABSPATH')) exit; ?>
<div class="nbd-field-wrap" ng-repeat="(fieldIndex, field) in options.fields" id="{{field.id}}">
    <div class="nbd-nav">
        <div ng-dblclick="toggleExpandField($index, $event)" style="cursor: pointer;" title="<?php esc_html_e('Double click to expand option', 'web-to-print-online-designer') ?>">
            <ul nbd-tab ng-class="field.isExpand ? '' : 'left'" class="nbd-tab-nav">
                <li class="nbd-field-tab active" data-target="tab-general"><?php esc_html_e('General', 'web-to-print-online-designer') ?></li>
                <li class="nbd-field-tab" data-target="tab-conditional"><?php esc_html_e('Conditional', 'web-to-print-online-designer') ?></li>
                <li class="nbd-field-tab" data-target="tab-appearance"><?php esc_html_e('Appearance', 'web-to-print-online-designer') ?></li>
                <li ng-if="field.nbd_type" class="nbd-field-tab" data-target="tab-online-design"><?php esc_html_e('Online design', 'web-to-print-online-designer'); ?></li>
                <li ng-if="field.nbpb_type" class="nbd-field-tab" data-target="tab-product-builder"><?php esc_html_e('Product builder', 'web-to-print-online-designer'); ?></li>
                <li ng-if="field.nbe_type" class="nbd-field-tab" data-target="tab-extra-options"><?php esc_html_e('Extra options', 'web-to-print-online-designer'); ?></li>
            </ul>
            <input ng-hide="true" ng-model="field.id" name="options[fields][{{fieldIndex}}][id]"/>
            <span class="nbd-field-name" ng-class="[{true: '', false: 'left'}[field.isExpand], {'n': 'nbo_blur'}[field.general.enabled.value]]">
                <span>{{field.general.title.value}}</span>
                <span style="color: #0085ba;">{{get_field_group_name( field.id )}}</span>
            </span>
            <span class="nbdesigner-right field-action">
                <span class="nbo-type-label-wrap"><span class="nbo-type-label" ng-class="get_field_class((field.nbd_type != '' && field.nbd_type != null) ? field.nbd_type : field.nbpb_type)">{{get_field_type( (field.nbd_type != '' && field.nbd_type != null) ? field.nbd_type : ( (field.nbpb_type != '' && field.nbpb_type != null) ? field.nbpb_type : field.nbe_type ) )}}</span></span>
                <span class="nbo-sort-group">
                    <span ng-click="sort_field($index, 'up')" class="dashicons dashicons-arrow-up nbo-sort-up nbo-sort" title="<?php esc_html_e('Up', 'web-to-print-online-designer') ?>"></span>
                    <span ng-click="sort_field($index, 'down')" class="dashicons dashicons-arrow-down nbo-sort-down nbo-sort" title="<?php esc_html_e('Down', 'web-to-print-online-designer') ?>"></span>
                </span>
                <a class="nbd-field-btn nbd-mini-btn button" ng-click="delete_field($index)" title="<?php esc_html_e('Delete', 'web-to-print-online-designer'); ?>"><span class="dashicons dashicons-no-alt"></span></a>
                <a class="nbd-field-btn nbd-mini-btn button" ng-click="copy_field($index)" title="<?php esc_html_e('Copy', 'web-to-print-online-designer'); ?>"><span class="dashicons dashicons-admin-page"></span></a>
                <a class="nbd-field-btn nbd-mini-btn button" ng-click="toggleExpandField($index, $event)" title="<?php esc_html_e('Expand', 'web-to-print-online-designer'); ?>"><span ng-show="!field.isExpand" class="dashicons dashicons-arrow-down"></span><span ng-show="field.isExpand" class="dashicons dashicons-arrow-up"></span></a>
            </span>
        </div>
        <div class="clear"></div>
    </div>
    <ng-include src="'field_body'"></ng-include>
</div>
<div style="display: flex; justify-content: space-between;">
    <a style="background: rgba(170, 0, 0, 0.75);color: #fff;border-color: rgba(170, 0, 0, 0.75);" class="button" ng-click="clear_all_fields()"><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e('Clear All Fields', 'web-to-print-online-designer'); ?></a>
    <a class="button button-primary" ng-click="add_field()"><span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add Field', 'web-to-print-online-designer'); ?></a>
</div>
