<?php if (!defined('ABSPATH')) exit; ?>
<div class="pcpb-field-wrap" ng-repeat="(fieldIndex, field) in options.fields" id="{{field.id}}" ng-class="{'is-expanded': field.isExpand, 'is-disabled': field.general.enabled.value === 'n', 'is-nbpb': !!field.nbpb_type}" data-field-index="{{fieldIndex}}">
    <div class="nbd-nav">
        <!-- Drag handle for sortable reorder. Wired up to jQuery UI sortable
             from admin-options.js (boot at controller init). -->
        <span class="v2-drag-handle" aria-label="<?php esc_attr_e('Drag to reorder', 'storelly-product-builder-for-woocommerce'); ?>"
              title="<?php esc_attr_e('Drag to reorder', 'storelly-product-builder-for-woocommerce'); ?>"
              ng-click="$event.stopPropagation()"
              aria-hidden="false">
            <span class="v2-drag-handle__icon" aria-hidden="true">⋮⋮</span>
        </span>
        <div ng-click="onHeaderClick(fieldIndex, $event)" ng-dblclick="toggleExpandField($index, $event)" title="<?php esc_attr_e('Click to expand · double-click anywhere', 'storelly-product-builder-for-woocommerce'); ?>">
            <input ng-hide="true" ng-model="field.id" name="options[fields][{{fieldIndex}}][id]" />
            <span class="pcpb-field-name" ng-class="[{true: '', false: 'left'}[field.isExpand], {'n': 'nbo_blur'}[field.general.enabled.value]]">
                <!-- Inline rename: click to edit. Bound to same model as the
                     title input inside the expanded body so they stay in sync.
                     No `name` attribute — the body input owns form submission. -->
                <input type="text"
                       class="pcpb-field-name__title pcpb-field-name__title--editable"
                       ng-model="field.general.title.value"
                       placeholder="<?php esc_attr_e('Untitled field', 'storelly-product-builder-for-woocommerce'); ?>"
                       aria-label="<?php esc_attr_e('Field name', 'storelly-product-builder-for-woocommerce'); ?>"
                       ng-click="$event.stopPropagation()"
                       ng-dblclick="$event.stopPropagation()" />
                <!-- Meta line — small muted text under the title. Renders only
                     when the card is collapsed. Corporate, scannable, no chips. -->
                <span class="pcpb-field-name__meta" ng-hide="field.isExpand">
                    <span class="pcpb-field-meta__item">{{ get_field_label(field) }}</span>
                    <span class="pcpb-field-meta__sep" ng-show="get_field_attr_count(field) > 0" aria-hidden="true">·</span>
                    <span class="pcpb-field-meta__item" ng-show="get_field_attr_count(field) > 0">
                        {{ get_field_attr_count(field) }} <?php esc_html_e('attributes', 'storelly-product-builder-for-woocommerce'); ?>
                    </span>
                    <span class="pcpb-field-meta__sep" ng-show="field.general.required.value === 'y'" aria-hidden="true">·</span>
                    <span class="pcpb-field-meta__item pcpb-field-meta__item--required" ng-show="field.general.required.value === 'y'">
                        <?php esc_html_e('Required', 'storelly-product-builder-for-woocommerce'); ?>
                    </span>
                    <span class="pcpb-field-meta__sep" ng-show="field.general.enabled.value === 'n'" aria-hidden="true">·</span>
                    <span class="pcpb-field-meta__item pcpb-field-meta__item--disabled" ng-show="field.general.enabled.value === 'n'">
                        <?php esc_html_e('Disabled', 'storelly-product-builder-for-woocommerce'); ?>
                    </span>
                    <span class="pcpb-field-meta__sep" ng-show="field.general.published.value === 'n'" aria-hidden="true">·</span>
                    <span class="pcpb-field-meta__item pcpb-field-meta__item--hidden" ng-show="field.general.published.value === 'n'">
                        <?php esc_html_e('Hidden in summary', 'storelly-product-builder-for-woocommerce'); ?>
                    </span>
                </span>
            </span>
            <span class="nbstorelly-right field-action">
                <span class="nbo-type-label-wrap" ng-show="field.isExpand"><span class="nbo-type-label" ng-class="get_field_class( field.nbpb_type)">{{get_field_type( field.nbpb_type ) }}</span></span>
                <span class="nbo-sort-group" role="group" aria-label="<?php esc_attr_e('Reorder field', 'storelly-product-builder-for-woocommerce'); ?>">
                    <span ng-click="sort_field($index, 'up')" class="dashicons dashicons-arrow-up nbo-sort-up nbo-sort"
                          role="button" tabindex="0"
                          title="<?php esc_attr_e('Move up', 'storelly-product-builder-for-woocommerce'); ?>"
                          aria-label="<?php esc_attr_e('Move field up', 'storelly-product-builder-for-woocommerce'); ?>"></span>
                    <span ng-click="sort_field($index, 'down')" class="dashicons dashicons-arrow-down nbo-sort-down nbo-sort"
                          role="button" tabindex="0"
                          title="<?php esc_attr_e('Move down', 'storelly-product-builder-for-woocommerce'); ?>"
                          aria-label="<?php esc_attr_e('Move field down', 'storelly-product-builder-for-woocommerce'); ?>"></span>
                </span>
                <a class="pcpb-field-btn nbd-mini-btn button" ng-click="copy_field($index)"
                   title="<?php esc_attr_e('Duplicate this field', 'storelly-product-builder-for-woocommerce'); ?>"
                   aria-label="<?php esc_attr_e('Duplicate this field', 'storelly-product-builder-for-woocommerce'); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></a>
                <a class="pcpb-field-btn nbd-mini-btn button" ng-click="delete_field($index)"
                   title="<?php esc_attr_e('Delete this field', 'storelly-product-builder-for-woocommerce'); ?>"
                   aria-label="<?php esc_attr_e('Delete this field', 'storelly-product-builder-for-woocommerce'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></a>
                <a class="pcpb-field-btn nbd-mini-btn button" ng-click="toggleExpandField($index, $event)"
                   ng-attr-title="{{ field.isExpand ? '<?php echo esc_js(__('Collapse field settings', 'storelly-product-builder-for-woocommerce')); ?>' : '<?php echo esc_js(__('Expand field settings', 'storelly-product-builder-for-woocommerce')); ?>' }}"
                   ng-attr-aria-label="{{ field.isExpand ? '<?php echo esc_js(__('Collapse field settings', 'storelly-product-builder-for-woocommerce')); ?>' : '<?php echo esc_js(__('Expand field settings', 'storelly-product-builder-for-woocommerce')); ?>' }}"
                   ng-attr-aria-expanded="{{ field.isExpand ? 'true' : 'false' }}"><span ng-show="!field.isExpand" class="dashicons dashicons-arrow-down" aria-hidden="true"></span><span ng-show="field.isExpand" class="dashicons dashicons-arrow-up" aria-hidden="true"></span></a>
            </span>
        </div>
        <div class="clear"></div>
    </div>
    <!-- Tabs moved INTO the body wrapper — only visible when expanded.
         This keeps the collapsed header clean (title + meta + actions only). -->
    <div class="v2-field-body-wrap" ng-show="field.isExpand">
        <ul nbd-tab class="nbd-tab-nav v2-field-tabs">
            <li class="pcpb-field-tab active" data-target="tab-general"><?php esc_html_e('General', 'storelly-product-builder-for-woocommerce') ?></li>
            <li class="pcpb-field-tab" data-target="tab-appearance"><?php esc_html_e('Appearance', 'storelly-product-builder-for-woocommerce') ?></li>
            <li ng-if="field.nbpb_type" class="pcpb-field-tab" data-target="tab-product-builder"><?php esc_html_e('Product builder', 'storelly-product-builder-for-woocommerce'); ?></li>
        </ul>
        <ng-include src="'field_body'"></ng-include>

        <!-- Per-field sticky action bar: lives at the bottom of the body
             wrapper. Floats during scroll, shows Done / Duplicate / Delete
             so the user does not have to scroll to global savebar. -->
        <div class="v2-field-actionbar">
            <span class="v2-field-actionbar__hint">
                <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                <?php esc_html_e('Changes save when you click Update / Publish above.', 'storelly-product-builder-for-woocommerce'); ?>
            </span>
            <button type="button" class="v2-btn v2-btn--secondary v2-field-actionbar__btn"
                    ng-click="copy_field($index)">
                <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                <?php esc_html_e('Duplicate', 'storelly-product-builder-for-woocommerce'); ?>
            </button>
            <button type="button" class="v2-btn v2-btn--danger-link v2-field-actionbar__btn"
                    ng-click="delete_field($index)">
                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                <?php esc_html_e('Delete field', 'storelly-product-builder-for-woocommerce'); ?>
            </button>
            <button type="button" class="v2-btn v2-btn--primary v2-field-actionbar__btn"
                    ng-click="toggleExpandField($index, $event)">
                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                <?php esc_html_e('Done', 'storelly-product-builder-for-woocommerce'); ?>
            </button>
        </div>
    </div>
</div>
<div class="pcpb-fields-actions">
    <a class="button pcpb-fields-actions__clear" ng-click="clear_all_fields()"><span class="dashicons dashicons-trash"></span> <?php esc_html_e('Clear All Fields', 'storelly-product-builder-for-woocommerce'); ?></a>
    <a class="button button-primary pcpb-fields-actions__add" ng-click="add_field()"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Add Field', 'storelly-product-builder-for-woocommerce'); ?></a>
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
include 'templates/field-body/placeholder.php';
include 'templates/field-body/upload_option.php';
include 'templates/field-body/enabled.php';
include 'templates/field-body/published.php';
include 'templates/field-body/required.php';
include 'templates/field-body/price_type.php';
include 'templates/field-body/depend_qty.php';
include 'templates/field-body/depend_quantity.php';
include 'templates/field-body/price.php';
include 'templates/field-body/price_breaks.php';
include 'templates/field-body/attributes.php';
include 'templates/field-body/conditional_depend.php';
