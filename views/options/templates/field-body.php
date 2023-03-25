<?php if (!defined('ABSPATH')) exit; ?>
<?php echo '<script type="text/ng-template" id="field_body">'; ?>
<div ng-show="field.isExpand">
    <div class="tab-general pcpb-field-content active">
        <ng-include src="'field_body_title'"></ng-include>
        <ng-include src="'field_body_description'"></ng-include>
        <ng-include src="'field_body_data_type'"></ng-include>
        <ng-include src="'field_body_input_type'"></ng-include>
        <ng-include src="'field_body_input_option'"></ng-include>
        <ng-include src="'field_body_text_option'"></ng-include>
        <!-- <ng-include src="'field_body_placeholder'"></ng-include> -->
        <ng-include src="'field_body_upload_option'"></ng-include>
        <ng-include src="'field_body_enabled'"></ng-include>
        <ng-include src="'field_body_published'"></ng-include>
        <ng-include src="'field_body_required'"></ng-include>
        <ng-include src="'field_body_price_type'"></ng-include>
        <!-- <ng-include src="'field_body_depend_qty'"></ng-include> -->
        <!-- <ng-include src="'field_body_depend_quantity'"></ng-include> -->
        <ng-include src="'field_body_price'"></ng-include>
        <!-- <ng-include src="'field_body_price_breaks'"></ng-include> -->
        <ng-include src="'field_body_attributes'"></ng-include>
    </div>
    <div class="tab-appearance pcpb-field-content">
        <div class="pcpb-field-info" ng-repeat="(key, data) in field.appearance">
            <div class="pcpb-field-info-1">
                <div><label><b>{{data.title}}</b> <nbd-tip ng-if="data.description != ''" data-tip="{{data.description}}"></nbd-tip></label></div>
            </div>
            <div class="pcpb-field-info-2">
                <div ng-if="data.type == 'dropdown'">
                    <select name="options[fields][{{fieldIndex}}][appearance][{{key}}]" ng-model="data.value">
                        <option ng-repeat="op in data.options" value="{{op.key}}">{{op.text}}</option>
                    </select>
                </div>
                <div ng-if="data.type == 'dropdown_group'">
                    <select name="options[fields][{{fieldIndex}}][appearance][{{key}}]" ng-model="data.value">
                        <optgroup ng-repeat="gr in data.options" label={{gr.title}}>
                            <option ng-repeat="op in gr.value" value="{{op.key}}">{{op.text}}</option>
                        </optgroup>
                    </select>
                </div>
                <div ng-if="data.type == 'text'">
                    <input type="text" name="options[fields][{{fieldIndex}}][appearance][{{key}}]" ng-model="data.value">
                </div>
            </div>
        </div>
    </div>
    <div class="tab-product-builder pcpb-field-content" ng-if="field.nbpb_type">
        <input ng-hide="true" name="options[fields][{{fieldIndex}}][nbpb_type]" ng-model="field.nbpb_type">
        <ng-include src="field.nbd_template"></ng-include>
    </div>
</div>
<?php echo '</script>';
