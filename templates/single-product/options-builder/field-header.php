<?php if (!defined('ABSPATH')) exit; ?>
<div class="nbd-field-header">
    <label for='nbd-field-<?php echo ($field['id']); ?>'>
        <?php echo ($field['general']['title']); ?>
        <?php if ($field['general']['required'] == 'y') : ?>
            <span class="nbd-required">*</span>
        <?php endif; ?>
    </label>
    <?php if ($field['general']['description'] != '') : ?>
        <span data-position="<?php echo ($tooltip_position); ?>" data-tip="<?php echo html_entity_decode($field['general']['description']); ?>" class="nbd-help-tip"></span>
    <?php endif; ?>
</div>