<?php if (!defined('ABSPATH')) exit; ?>
<!-- No inline scripts or styles unless dynamic. -->
<?php echo '<script type="text/ng-template" id="field_body">'; ?>
<div ng-show="field.isExpand">
    <div class="tab-general pcpb-field-content active">
        <ng-include src="'field_body_title'"></ng-include>
        <ng-include src="'field_body_description'"></ng-include>
        <ng-include src="'field_body_data_type'"></ng-include>
        <ng-include src="'field_body_input_type'"></ng-include>
        <ng-include src="'field_body_input_option'"></ng-include>
        <ng-include src="'field_body_text_option'"></ng-include>
        <ng-include src="'field_body_placeholder'"></ng-include>
        <ng-include src="'field_body_upload_option'"></ng-include>
        <ng-include src="'field_body_enabled'"></ng-include>
        <ng-include src="'field_body_published'"></ng-include>
        <ng-include src="'field_body_required'"></ng-include>
        <ng-include src="'field_body_price_type'"></ng-include>
        <ng-include src="'field_body_depend_qty'"></ng-include>
        <ng-include src="'field_body_depend_quantity'"></ng-include>
        <ng-include src="'field_body_price'"></ng-include>
        <ng-include src="'field_body_price_breaks'"></ng-include>
        <ng-include src="'field_body_attributes'"></ng-include>
        <ng-include src="'field_body_conditional_depend'"></ng-include>
    </div>
    <div class="tab-appearance pcpb-field-content">
        <div class="pcpb-field-info" ng-repeat="(key, data) in field.appearance">
            <div class="pcpb-field-info-1">
                <label><b>{{data.title}}</b></label>
                <p class="v2-form-help" ng-if="data.description != ''">{{data.description}}</p>
            </div>
            <div class="pcpb-field-info-2">
                <!-- display_type → visual swatch cards with mini preview per variant -->
                <div ng-if="data.type == 'dropdown' && key == 'display_type'">
                    <div class="v2-display-cards" role="radiogroup" aria-label="{{data.title}}">
                        <button type="button"
                                ng-repeat="op in data.options"
                                class="v2-display-card v2-display-card--{{op.key}}"
                                ng-class="{'is-active': data.value === op.key}"
                                ng-click="data.value = op.key"
                                title="{{op.text}}">
                            <span class="v2-display-card__preview" aria-hidden="true"></span>
                            <span class="v2-display-card__label">{{op.text}}</span>
                        </button>
                    </div>
                    <select class="v2-hidden-legacy" name="options[fields][{{fieldIndex}}][appearance][{{key}}]" ng-model="data.value">
                        <option ng-repeat="op in data.options" value="{{op.key}}">{{op.text}}</option>
                    </select>
                </div>
                <!-- Generic dropdowns with ≤3 options → segmented control -->
                <div ng-if="data.type == 'dropdown' && key != 'display_type' && data.options.length > 0 && data.options.length <= 3">
                    <div class="v2-segmented" role="radiogroup" aria-label="{{data.title}}">
                        <button type="button"
                                ng-repeat="op in data.options"
                                class="v2-segmented__btn"
                                ng-class="{'is-active': data.value === op.key}"
                                ng-click="data.value = op.key">
                            {{ op.text }}
                        </button>
                    </div>
                    <select class="v2-hidden-legacy" name="options[fields][{{fieldIndex}}][appearance][{{key}}]" ng-model="data.value">
                        <option ng-repeat="op in data.options" value="{{op.key}}">{{op.text}}</option>
                    </select>
                </div>
                <!-- Other dropdowns: keep native select but styled -->
                <div ng-if="data.type == 'dropdown' && key != 'display_type' && data.options.length > 3">
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
