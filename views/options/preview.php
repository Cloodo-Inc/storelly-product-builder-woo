<?php if (!defined('ABSPATH')) exit; ?>
<div class="frontend-prview" ng-class="previewWide ? 'wide' + (!showPreview ? 'off-preview' : '') : (!showPreview ? 'off-preview' : '')">
    <div class="create-pre-builder-wrap" ng-if="has_product_builder_field">
        <a class="button button-primary button-large" target="_blank" href="<?php echo ($link_create_pre_builder); ?> "><?php esc_html_e('Create Pre builder', 'pc-product-builder'); ?></a>
    </div>
</div>