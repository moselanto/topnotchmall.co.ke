<?php
/**
 * Hero: vertical category menu + slider.
 *
 * @package TopnotchMall
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="rk-hero">
	<div class="container">
		<?php
		// Every product category, ordered by size. The panel scrolls the full list
		// rather than showing only what happens to fit beside the slider.
		$rk_terms = function_exists( 'rk_cached_terms' ) ? rk_cached_terms( 'product_cat', 0 ) : array();
		$rk_shop_link = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		?>
		<aside class="rk-vertcat" aria-label="<?php esc_attr_e( 'Shop by category', 'topnotch-mall' ); ?>">
			<h2>
				<?php esc_html_e( 'All Categories', 'topnotch-mall' ); ?>
				<?php if ( ! empty( $rk_terms ) ) : ?>
					<span class="rk-vertcat__count"><?php echo esc_html( number_format_i18n( count( $rk_terms ) ) ); ?></span>
				<?php endif; ?>
			</h2>
			<?php
			// Always the full taxonomy, scrolled. A nav menu assigned to the
			// vertical_cats location used to win here and it only carries a dozen
			// items, which is why the panel never showed the whole shop.
			if ( ! empty( $rk_terms ) ) {
				echo '<ul class="rk-vertcat__list">';
				foreach ( $rk_terms as $t ) {
					printf(
						'<li><a href="%1$s"><span class="rk-vertcat__name">%2$s</span> <span class="rk-vertcat__qty">%3$s</span></a></li>',
						esc_url( get_term_link( $t ) ),
						esc_html( $t->name ),
						esc_html( number_format_i18n( (int) $t->count ) )
					);
				}
				echo '</ul>';
			} elseif ( has_nav_menu( 'vertical_cats' ) ) {
				wp_nav_menu( array( 'theme_location' => 'vertical_cats', 'container' => false, 'fallback_cb' => false, 'depth' => 1 ) );
			}
			?>
			<a class="rk-vertcat__all" href="<?php echo esc_url( $rk_shop_link ); ?>">
				<?php esc_html_e( 'Browse the full shop', 'topnotch-mall' ); ?> <span aria-hidden="true">&rarr;</span>
			</a>
		</aside>

		<div class="rk-slider" tabindex="0" aria-roledescription="carousel" aria-label="<?php esc_attr_e( 'Promotions', 'topnotch-mall' ); ?>">
			<?php
			$slides  = get_theme_mod( 'topnotch_slides', array() );
			$rk_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
			if ( empty( $slides ) || ! is_array( $slides ) ) {
				$slides = array(
					array(
						'img'   => TOPNOTCH_URI . 'assets/img/banner-tools.webp',
						'title' => __( 'Power tools built for the job', 'topnotch-mall' ),
						'text'  => __( 'Genuine brands, honest prices, and same-day dispatch on Nairobi orders placed before 5pm.', 'topnotch-mall' ),
						'url'   => $rk_shop,
					),
					array(
						'img'   => TOPNOTCH_URI . 'assets/img/banner-solar.webp',
						'title' => __( 'Solar that keeps you running', 'topnotch-mall' ),
						'text'  => __( 'Panels, hybrid inverters, deep-cycle batteries and street lights, in stock in Nairobi.', 'topnotch-mall' ),
						'url'   => $rk_shop,
					),
					array(
						'img'   => TOPNOTCH_URI . 'assets/img/banner-machinery.webp',
						'title' => __( 'Generators, welding & pumps', 'topnotch-mall' ),
						'text'  => __( 'Heavy-duty machinery delivered countrywide, backed by manufacturer warranty.', 'topnotch-mall' ),
						'url'   => $rk_shop,
					),
				);
			}
			foreach ( $slides as $i => $s ) {
				$img_attr = 0 === $i
					? 'width="1200" height="500" fetchpriority="high" decoding="async" loading="eager"'
					: 'width="1200" height="500" loading="lazy" decoding="async"';
				printf(
					'<div class="rk-slide%1$s"><img src="%2$s" alt="%3$s" %4$s><div class="rk-slide__promo"><h2>%3$s</h2><p>%5$s</p><a class="rk-btn rk-btn--primary" href="%6$s">%7$s</a></div></div>',
					0 === $i ? ' is-active' : '',
					esc_url( $s['img'] ),
					esc_attr( $s['title'] ),
					$img_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string.
					esc_html( $s['text'] ),
					esc_url( $s['url'] ),
					esc_html__( 'Shop Now', 'topnotch-mall' )
				);
			}
			?>
			<button class="rk-slider__arrow rk-slider__arrow--prev" aria-label="<?php esc_attr_e( 'Previous slide', 'topnotch-mall' ); ?>">&#8249;</button>
			<button class="rk-slider__arrow rk-slider__arrow--next" aria-label="<?php esc_attr_e( 'Next slide', 'topnotch-mall' ); ?>">&#8250;</button>
			<div class="rk-slider__dots"></div>
		</div>
	</div>
</section>
