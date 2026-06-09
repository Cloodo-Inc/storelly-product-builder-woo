<?php
/**
 * Account shell — header / open.
 *
 * Shared chrome for every Storelly My-Account endpoint (User Menu M4). Override in
 * a theme by copying to: yourtheme/storelly/account/shell-header.php
 *
 * @var string $title       Page title (required).
 * @var string $eyebrow     Optional small-caps context label above the title.
 * @var string $subtitle    Optional sub-heading.
 * @var string $actions     Optional pre-escaped HTML for the top-right actions slot.
 * @var array  $breadcrumb  Optional list of array{ label:string, url?:string }.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title      = isset( $title ) ? (string) $title : '';
$eyebrow    = isset( $eyebrow ) ? (string) $eyebrow : '';
$subtitle   = isset( $subtitle ) ? (string) $subtitle : '';
$actions    = isset( $actions ) ? (string) $actions : '';
$breadcrumb = isset( $breadcrumb ) && is_array( $breadcrumb ) ? $breadcrumb : array();
?>
<div class="spbwc-account">
	<?php if ( ! empty( $breadcrumb ) ) : ?>
		<nav class="spbwc-account__breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'storelly-product-builder-for-woocommerce' ); ?>">
			<?php
			$last = count( $breadcrumb ) - 1;
			foreach ( $breadcrumb as $i => $crumb ) {
				$label = isset( $crumb['label'] ) ? (string) $crumb['label'] : '';
				$url   = isset( $crumb['url'] ) ? (string) $crumb['url'] : '';
				if ( '' === $label ) {
					continue;
				}
				if ( $url && $i !== $last ) {
					echo '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
				} else {
					echo '<span aria-current="page">' . esc_html( $label ) . '</span>';
				}
				if ( $i !== $last ) {
					echo '<span class="spbwc-account__breadcrumb-sep" aria-hidden="true">/</span>';
				}
			}
			?>
		</nav>
	<?php endif; ?>

	<header class="spbwc-account__head">
		<div class="spbwc-account__titles">
			<?php if ( '' !== $eyebrow ) : ?>
				<span class="spbwc-account__eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $title ) : ?>
				<h2 class="spbwc-account__title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<?php if ( '' !== $subtitle ) : ?>
				<p class="spbwc-account__subtitle"><?php echo esc_html( $subtitle ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $actions ) : ?>
			<div class="spbwc-account__actions"><?php echo wp_kses_post( $actions ); ?></div>
		<?php endif; ?>
	</header>

	<div class="spbwc-account__body">
