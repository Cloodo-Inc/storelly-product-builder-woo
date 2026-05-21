<?php if (!defined('ABSPATH')) exit; ?>
<div class="nbdl-notification nbdl-notification-<?php echo esc_attr( $type ); ?>">
    <div>
        <?php echo wp_kses_post( $message ); ?>
    </div>
</div>