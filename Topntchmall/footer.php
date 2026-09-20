<?php
/**
 * Site footer.
 *
 * @package TopnotchMall
 */

defined( 'ABSPATH' ) || exit;

$rk_phone    = get_theme_mod( 'topnotch_phone', '+254 708 777192' );
$rk_email    = get_theme_mod( 'topnotch_email', 'info@topnotchmall.co.ke' );
$rk_address  = get_theme_mod( 'topnotch_address', 'Magomano House, Tom Mboya Street, Nairobi, Kenya' );
$rk_whatsapp = get_theme_mod( 'topnotch_whatsapp', '254708777192' );
$rk_hours    = get_theme_mod( 'topnotch_hours', 'Mon - Sat, 9AM - 5PM' );
$rk_cutoff   = get_theme_mod( 'topnotch_cutoff', '5:00pm' );
?>
</div><!-- #content -->
<footer class="rk-footer">
	<div class="container">
		<div class="rk-footer__cols">
			<div>
				<h3><?php bloginfo( 'name' ); ?></h3>
				<p><strong><?php esc_html_e( 'Address:', 'topnotch-mall' ); ?></strong><br><?php echo esc_html( $rk_address ); ?></p>
				<p><strong><?php esc_html_e( 'Opening hours:', 'topnotch-mall' ); ?></strong><br><?php echo esc_html( $rk_hours ); ?><br><?php echo esc_html( sprintf( __( 'Same-day dispatch for orders confirmed before %s.', 'topnotch-mall' ), $rk_cutoff ) ); ?></p>
				<p><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $rk_phone ) ); ?>"><?php echo esc_html( $rk_phone ); ?></a><br><a href="https://wa.me/<?php echo esc_attr( $rk_whatsapp ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'WhatsApp us', 'topnotch-mall' ); ?></a><br><a href="mailto:<?php echo esc_attr( $rk_email ); ?>"><?php echo esc_html( $rk_email ); ?></a></p>
			</div>
			<div>
				<h3><?php esc_html_e( 'Customer Service', 'topnotch-mall' ); ?></h3>
				<?php wp_nav_menu( array( 'theme_location' => 'footer_service', 'container' => false, 'fallback_cb' => false, 'depth' => 1 ) ); ?>
			</div>
			<div>
				<h3><?php esc_html_e( 'Policies', 'topnotch-mall' ); ?></h3>
				<?php wp_nav_menu( array( 'theme_location' => 'footer_policies', 'container' => false, 'fallback_cb' => false, 'depth' => 1 ) ); ?>
			</div>
			<div>
				<h3><?php esc_html_e( 'We Accept', 'topnotch-mall' ); ?></h3>
				<div class="rk-payments">
					<span>M-PESA</span><span>Visa</span><span>Mastercard</span><span>Cash on Delivery</span>
				</div>
				<h3 style="margin-top:18px"><?php esc_html_e( 'Secure Shopping', 'topnotch-mall' ); ?></h3>
				<div class="rk-payments"><span>SSL Secured</span><span>Verified Business</span></div>
			</div>
		</div>
	</div>
	<div class="rk-footer__bar">
		<div class="container">
			&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'topnotch-mall' ); ?>
		</div>
	</div>
</footer>

<a class="rk-whatsapp" href="https://wa.me/<?php echo esc_attr( $rk_whatsapp ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Chat on WhatsApp', 'topnotch-mall' ); ?>">
	<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15l-1.4 5 5.1-1.3A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-3.9-4.7-4.1-.1-.2-1-1.4-1-2.6s.6-1.8.9-2.1c.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 1.9c.1.2.1.4 0 .5l-.4.5c-.2.2-.3.4-.1.6.2.4.9 1.4 1.9 2.3 1.3 1.1 2.3 1.4 2.5 1.5.2.1.4.1.6-.1l.7-.9c.2-.3.4-.2.6-.1l1.8.9c.2.1.4.2.5.3.1.3.1.7-.1 1.3Z"/></svg>
</a>
<button class="rk-backtop" aria-label="<?php esc_attr_e( 'Back to top', 'topnotch-mall' ); ?>">&uarr;</button>

<?php if ( function_exists( 'woocommerce_mini_cart' ) ) : ?>
<div class="rk-drawer" aria-hidden="true">
	<div class="rk-drawer__overlay" data-rk-drawer-close></div>
	<aside class="rk-drawer__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Shopping cart', 'topnotch-mall' ); ?>">
		<div class="rk-drawer__head">
			<h3><?php esc_html_e( 'Your Cart', 'topnotch-mall' ); ?></h3>
			<button type="button" class="rk-drawer__close" data-rk-drawer-close aria-label="<?php esc_attr_e( 'Close cart', 'topnotch-mall' ); ?>">&times;</button>
		</div>
		<div class="rk-drawer__body">
			<div class="widget_shopping_cart_content"><?php woocommerce_mini_cart(); ?></div>
		</div>
		<div class="rk-drawer__foot">
			<button type="button" class="rk-btn rk-btn--ghost rk-btn--block" data-rk-drawer-close><?php esc_html_e( 'Continue shopping', 'topnotch-mall' ); ?></button>
			<a class="rk-btn rk-btn--primary rk-btn--block rk-drawer__checkout" href="<?php echo esc_url( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '' ); ?>"><?php esc_html_e( 'Proceed to checkout', 'topnotch-mall' ); ?></a>
		</div>
		<div class="rk-drawer__spin" aria-hidden="true"><span class="rk-spinner"></span></div>
	</aside>
</div>
<?php endif; ?>

<?php
/**
 * Static mobile tab bar: five thumb-reachable destinations, always on screen.
 * Hidden on large viewports in CSS. The cart tab shares the header's live count.
 */
$rk_tab_cart  = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
$rk_tab_count = ( function_exists( 'WC' ) && WC() && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
?>
<nav class="rk-tabbar" aria-label="<?php esc_attr_e( 'Quick navigation', 'topnotch-mall' ); ?>">
	<a class="rk-tabbar__item<?php echo is_front_page() ? ' is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6h-4v6H5a1 1 0 0 1-1-1z"/></svg>
		<span><?php esc_html_e( 'Home', 'topnotch-mall' ); ?></span>
	</a>
	<button type="button" class="rk-tabbar__item" data-rk-mob-open aria-expanded="false" aria-controls="rk-mobile">
		<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h7v7H4zM13 5h7v7h-7zM4 14h7v5H4zM13 14h7v5h-7z"/></svg>
		<span><?php esc_html_e( 'Categories', 'topnotch-mall' ); ?></span>
	</button>
	<button type="button" class="rk-tabbar__item" data-rk-search-focus>
		<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.5 3a7.5 7.5 0 1 0 4.6 13.4l4.2 4.2 1.5-1.5-4.2-4.2A7.5 7.5 0 0 0 10.5 3Zm0 2a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11Z"/></svg>
		<span><?php esc_html_e( 'Search', 'topnotch-mall' ); ?></span>
	</button>
	<a class="rk-tabbar__item rk-tabbar__item--wa" href="https://wa.me/<?php echo esc_attr( $rk_whatsapp ); ?>" target="_blank" rel="noopener">
		<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15l-1.4 5 5.1-1.3A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-3.9-4.7-4.1-.1-.2-1-1.4-1-2.6s.6-1.8.9-2.1c.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 1.9c.1.2.1.4 0 .5l-.4.5c-.2.2-.3.4-.1.6.2.4.9 1.4 1.9 2.3 1.3 1.1 2.3 1.4 2.5 1.5.2.1.4.1.6-.1l.7-.9c.2-.3.4-.2.6-.1l1.8.9c.2.1.4.2.5.3.1.3.1.7-.1 1.3Z"/></svg>
		<span><?php esc_html_e( 'WhatsApp', 'topnotch-mall' ); ?></span>
	</a>
	<a class="rk-tabbar__item" href="<?php echo esc_url( $rk_tab_cart ); ?>" data-rk-drawer-open>
		<span class="rk-tabbar__icon">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4H5L4 6H2v2h1.6l2.5 9h11l2.4-8H7.3l-.5-2H21V4H7Zm1 15a2 2 0 1 0 2 2 2 2 0 0 0-2-2Zm9 0a2 2 0 1 0 2 2 2 2 0 0 0-2-2Z"/></svg>
			<span class="rk-cart-count rk-tabbar__count" data-count="<?php echo esc_attr( $rk_tab_count ); ?>"><?php echo esc_html( $rk_tab_count ); ?></span>
		</span>
		<span><?php esc_html_e( 'Cart', 'topnotch-mall' ); ?></span>
	</a>
</nav>

<?php wp_footer(); ?>
</body>
</html>
