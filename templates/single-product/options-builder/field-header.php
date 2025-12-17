<?php 
if (!defined('ABSPATH')) exit; 
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables used in local scope.
?>
<div class="pcpb-field-header">
    <label for='pcpb-field-<?php echo esc_attr($field['id']); ?>'>
        <?php echo esc_html($field['general']['title']); ?>
        <?php if ($field['general']['required'] == 'y') : ?>
            <span class="nbd-required">*</span>
        <?php endif; ?>
    </label>
    <?php if ($field['general']['description'] != '') : ?> 
        <span data-position="top" data-tip="<?php echo esc_attr($field['general']['description']); ?>" class="nbd-help-tip"></span>
    <?php endif; ?>
</div>