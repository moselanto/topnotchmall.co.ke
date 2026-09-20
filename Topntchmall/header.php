<?php
/**
 * Site header: top bar, logo + search + actions, primary nav, sticky.
 *
 * @package TopnotchMall
 */

defined( 'ABSPATH' ) || exit;

$rk_phone    = get_theme_mod( 'topnotch_phone', '+254 708 777192' );
$rk_email    = get_theme_mod( 'topnotch_email', 'info@topnotchmall.co.ke' );
$rk_hours    = get_theme_mod( 'topnotch_hours', 'Mon - Sat, 9AM - 5PM' );
$rk_whatsapp = get_theme_mod( 'topnotch_whatsapp', '254708777192' );
$rk_cutoff   = get_theme_mod( 'topnotch_cutoff', '5:00pm' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="sr-only" href="#primary"><?php esc_html_e( 'Skip to content', 'topnotch-mall' ); ?></a>

<header class="rk-header">
	<div class="rk-header__top">
		<div class="container">
			<div class="rk-header__hours">
				<?php echo esc_html( $rk_hours ); ?>
				<?php if ( '' !== trim( (string) $rk_cutoff ) ) : ?>
					<span class="rk-header__cutoff"><?php echo esc_html( sprintf( __( 'Order before %s for same-day dispatch', 'topnotch-mall' ), $rk_cutoff ) ); ?></span>
				<?php endif; ?>
			</div>
			<div class="rk-header__contact">
				<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $rk_phone ) ); ?>"><?php echo esc_html( __( 'Call: ', 'topnotch-mall' ) . $rk_phone ); ?></a>
				<a href="https://wa.me/<?php echo esc_attr( $rk_whatsapp ); ?>" rel="noopener" target="_blank">WhatsApp</a>
				<a href="mailto:<?php echo esc_attr( $rk_email ); ?>"><?php echo esc_html( $rk_email ); ?></a>
			</div>
		</div>
	</div>

	<div class="rk-header__mid">
		<div class="container">
			<button class="rk-nav-toggle" type="button" data-rk-mob-open aria-expanded="false" aria-controls="rk-mobile" aria-label="<?php esc_attr_e( 'Menu', 'topnotch-mall' ); ?>"><span class="rk-burger"></span></button>
			<div class="rk-logo">
				<?php
				// The header bar is dark, so the bundled white wordmark is used by
				// default. A coloured Customizer logo reads badly against it. Sites
				// that want their uploaded logo back can return true from the filter.
				$rk_use_custom = (bool) apply_filters( 'topnotch_header_use_custom_logo', false );
				$rk_white      = TOPNOTCH_DIR . 'assets/img/logo-white.png';

				if ( $rk_use_custom && function_exists( 'the_custom_logo' ) && has_custom_logo() ) {
					the_custom_logo();
				} elseif ( file_exists( $rk_white ) ) {
					$rk_2x = TOPNOTCH_DIR . 'assets/img/logo-white-2x.png';
					printf(
						'<a class="rk-logo__link" href="%1$s" rel="home"><img class="rk-logo__img" src="%2$s"%3$s width="300" height="81" alt="%4$s" fetchpriority="high" decoding="async"></a>',
						esc_url( home_url( '/' ) ),
						esc_url( TOPNOTCH_URI . 'assets/img/logo-white.png' ),
						file_exists( $rk_2x ) ? ' srcset="' . esc_url( TOPNOTCH_URI . 'assets/img/logo-white.png' ) . ' 1x, ' . esc_url( TOPNOTCH_URI . 'assets/img/logo-white-2x.png' ) . ' 2x"' : '',
						esc_attr( get_bloginfo( 'name' ) )
					);
				} else {
					printf( '<a class="rk-logo__text" href="%s">%s</a>', esc_url( home_url( '/' ) ), esc_html( get_bloginfo( 'name' ) ) );
				}
				?>
			</div>

			<div class="rk-search">
				<?php get_search_form(); ?>
				<div class="rk-search__panel" role="listbox" aria-label="<?php esc_attr_e( 'Search suggestions', 'topnotch-mall' ); ?>"></div>
			</div>

			<div class="rk-actions">
				<?php
				$rk_has_wc    = function_exists( 'wc_get_page_permalink' );
				$rk_account   = $rk_has_wc ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
				$rk_cart_url  = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );
				$rk_cart_ct   = ( function_exists( 'WC' ) && WC() && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
				?>
				<a class="rk-actions__item" href="<?php echo esc_url( $rk_account ); ?>">
					<?php echo rk_icon( 'user' ); // phpcs:ignore ?>
					<span><?php esc_html_e( 'Account', 'topnotch-mall' ); ?></span>
				</a>
				<?php if ( $rk_has_wc ) : ?>
				<a class="rk-actions__item" href="<?php echo esc_url( $rk_cart_url ); ?>" data-rk-drawer-open>
					<?php echo rk_icon( 'cart' ); // phpcs:ignore ?>
					<span class="rk-cart-count" data-count="<?php echo esc_attr( $rk_cart_ct ); ?>"><?php echo esc_html( $rk_cart_ct ); ?></span>
					<span><?php esc_html_e( 'Cart', 'topnotch-mall' ); ?></span>
				</a>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<nav class="rk-nav" aria-label="<?php esc_attr_e( 'Primary', 'topnotch-mall' ); ?>">
		<div class="container">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_id'        => 'rk-primary-menu',
					'fallback_cb'    => false,
					'depth'          => 2,
				)
			);
			?>
		</div>
	</nav>
	<nav class="rk-mobile" id="rk-mobile" aria-label="Shop by category" aria-hidden="true">
		<div class="rk-mobile__head">
			<span><?php esc_html_e( 'Shop by Category', 'topnotch-mall' ); ?></span>
			<button type="button" class="rk-mobile__close" data-rk-mob-close aria-label="<?php esc_attr_e( 'Close menu', 'topnotch-mall' ); ?>">&times;</button>
		</div>
		<ul class="rk-mobile__cats">
			<?php
			if ( taxonomy_exists( 'product_cat' ) ) {
				$rk_cats = function_exists( 'rk_cached_terms' ) ? rk_cached_terms( 'product_cat', 40 ) : array();
				if ( ! is_wp_error( $rk_cats ) ) {
					foreach ( $rk_cats as $rk_c ) {
						printf(
							'<li><a href="%1$s"><span>%2$s</span><span class="rk-mobile__count">%3$d</span></a></li>',
							esc_url( get_term_link( $rk_c ) ),
							esc_html( $rk_c->name ),
							(int) $rk_c->count
						);
					}
				}
			}
			?>
		</ul>
		<?php
		if ( has_nav_menu( 'primary' ) ) {
			echo '<div class="rk-mobile__links">';
			wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'menu_class' => 'rk-mobile__toplinks', 'fallback_cb' => false, 'depth' => 1 ) );
			echo '</div>';
		}
		?>
	</nav>
	<div class="rk-mobile__overlay" data-rk-mob-close></div>
</header>
<div id="content" class="site-content">
