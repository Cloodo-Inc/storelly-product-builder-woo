<?php if (!defined('ABSPATH')) exit; ?>
<div class="pcpb-field-header">
    <label for='pcpb-field-<?php echo ($field['id']); ?>'>
        <?php echo ($field['general']['title']); ?>
        <?php if ($field['general']['required'] == 'y') : ?>
            <span class="nbd-required">*</span>
        <?php endif; ?>
    </label>
    <?php if ($field['general']['description'] != '') : ?>
        <span data-position="top" data-tip="<?php echo html_entity_decode($field['general']['description']); ?>" class="nbd-help-tip"></span>
    <?php endif; ?>
</div>