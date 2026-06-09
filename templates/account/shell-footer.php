<?php
/**
 * Account shell — footer / close.
 *
 * Closes the wrapper opened by shell-header.php (User Menu M4). Override in a theme
 * by copying to: yourtheme/storelly/account/shell-footer.php
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>
	</div><!-- .spbwc-account__body -->

	<footer class="spbwc-account__foot">
		<p class="spbwc-account__foot-help"><?php esc_html_e( 'Need a hand? Reach out to the store team anytime.', 'storelly-product-builder-for-woocommerce' ); ?></p>
		<?php if ( $shop_url ) : ?>
			<a class="spbwc-account__foot-shop" href="<?php echo esc_url( $shop_url ); ?>">
				<?php esc_html_e( 'Continue shopping', 'storelly-product-builder-for-woocommerce' ); ?>
				<span class="spbwc-account__foot-arrow" aria-hidden="true">&rarr;</span>
			</a>
		<?php endif; ?>
	</footer>
</div><!-- .spbwc-account -->
