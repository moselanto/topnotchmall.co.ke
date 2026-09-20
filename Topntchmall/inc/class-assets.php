<?php
/**
 * Front-end + editor asset loading with performance defaults.
 *
 * @package TopnotchMall
 */

declare( strict_types = 1 );

namespace TopnotchMall;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues styles/scripts, preloads fonts, defers non-critical JS.
 */
final class Assets {

	public function hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_head', array( $this, 'preload_and_critical' ), 1 );
		add_action( 'wp_head', array( $this, 'favicons' ), 2 );
		add_filter( 'script_loader_tag', array( $this, 'defer_scripts' ), 10, 3 );
		// Trim WooCommerce bloat on non-woo pages (perf).
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_woo_bloat' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'trim_front_end_assets' ), 100 );
		add_action( 'wp_default_scripts', array( $this, 'drop_jquery_migrate' ) );
			add_action( 'init', array( $this, 'trim_head' ) );
	}

	/**
	 * Cache-busting version for a bundled asset.
	 *
	 * Returns the file's modification time, falling back to the theme
	 * version. Bumping TOPNOTCH_VERSION alone is easy to forget, and a
	 * stale CSS or JS file cached on a phone is indistinguishable from a
	 * bug that was never fixed. With the mtime in the query string, every
	 * edited asset is fetched fresh on the next reload, on desktop and
	 * mobile alike, without anyone clearing a cache by hand.
	 *
	 * @param string $rel Path relative to the theme root.
	 */
	private function asset_version( string $rel ): string {
		$path = TOPNOTCH_DIR . $rel;
		$mtime = file_exists( $path ) ? filemtime( $path ) : false;
		return false === $mtime ? TOPNOTCH_VERSION : TOPNOTCH_VERSION . '.' . (string) $mtime;
	}

	public function enqueue(): void {
		// One bundled stylesheet instead of four separate requests. The bundle is
		// theme.css + theme-industrial.css + theme-topnotch.css concatenated in
		// that exact cascade order, so the later layers still win. Each of those
		// requests was costing 500-800ms on mobile, and the old theme.min.css had
		// drifted out of sync with theme.css, meaning fixes made in the source
		// silently never shipped.
		$bundle = 'assets/css/theme-bundle.min.css';
		if ( file_exists( TOPNOTCH_DIR . $bundle ) ) {
			wp_enqueue_style( 'topnotch-theme', TOPNOTCH_URI . $bundle, array(), $this->asset_version( $bundle ) );
			wp_style_add_data( 'topnotch-theme', 'rtl', 'replace' );
		} else {
			// Fallback: the individual layers, in order.
			wp_enqueue_style( 'topnotch-theme', TOPNOTCH_URI . 'assets/css/theme.css', array(), $this->asset_version( 'assets/css/theme.css' ) );
			wp_style_add_data( 'topnotch-theme', 'rtl', 'replace' );
			wp_enqueue_style( 'topnotch-industrial', TOPNOTCH_URI . 'assets/css/theme-industrial.css', array( 'topnotch-theme' ), $this->asset_version( 'assets/css/theme-industrial.css' ) );
			wp_enqueue_style( 'topnotch-ui', TOPNOTCH_URI . 'assets/css/theme-topnotch.css', array( 'topnotch-industrial' ), $this->asset_version( 'assets/css/theme-topnotch.css' ) );
		}
		wp_enqueue_style( 'topnotch-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Oswald:wght@500;600;700&display=swap', array(), null );

		wp_enqueue_script( 'topnotch-theme', TOPNOTCH_URI . 'assets/js/theme.js', array(), $this->asset_version( 'assets/js/theme.js' ), true );

		if ( class_exists( 'WooCommerce' ) ) {
			wp_enqueue_script( 'topnotch-ajax', TOPNOTCH_URI . 'assets/js/ajax-cart.js', array( 'topnotch-theme' ), $this->asset_version( 'assets/js/ajax-cart.js' ), true );
			wp_localize_script(
				'topnotch-ajax',
				'TopnotchAjax',
				array(
					'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( 'topnotch_ajax' ),
					'cartUrl'   => wc_get_cart_url(),
					'i18n'      => array(
						'added'   => esc_html__( 'Added to cart', 'topnotch-mall' ),
						'adding'  => esc_html__( 'Adding...', 'topnotch-mall' ),
						'error'   => esc_html__( 'Something went wrong. Please try again.', 'topnotch-mall' ),
						'viewCart'=> esc_html__( 'View cart', 'topnotch-mall' ),
					),
				)
			);
		}

		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}

	/**
	 * Output the bundled Topnotch Mall favicon / app icons.
	 *
	 * Skipped when a Site Icon has been set in Settings > General, so an icon
	 * chosen in wp-admin always wins.
	 */
	public function favicons(): void {
		if ( function_exists( 'has_site_icon' ) && has_site_icon() ) {
			return;
		}
		$icons = array(
			array( 'rel' => 'icon', 'file' => 'favicon-32x32.png', 'type' => 'image/png', 'sizes' => '32x32' ),
			array( 'rel' => 'icon', 'file' => 'favicon-16x16.png', 'type' => 'image/png', 'sizes' => '16x16' ),
			array( 'rel' => 'icon', 'file' => 'favicon-96x96.png', 'type' => 'image/png', 'sizes' => '96x96' ),
			array( 'rel' => 'apple-touch-icon', 'file' => 'apple-touch-icon.png', 'type' => '', 'sizes' => '180x180' ),
			array( 'rel' => 'icon', 'file' => 'icon-192.png', 'type' => 'image/png', 'sizes' => '192x192' ),
		);
		foreach ( $icons as $icon ) {
			$path = TOPNOTCH_DIR . 'assets/img/' . $icon['file'];
			if ( file_exists( $path ) === false ) {
				continue;
			}
			printf(
				'<link rel="%1$s" href="%2$s"%3$s%4$s>' . "\n",
				esc_attr( $icon['rel'] ),
				esc_url( TOPNOTCH_URI . 'assets/img/' . $icon['file'] ),
				'' === $icon['type'] ? '' : ' type="' . esc_attr( $icon['type'] ) . '"',
				' sizes="' . esc_attr( $icon['sizes'] ) . '"'
			);
		}
		if ( file_exists( TOPNOTCH_DIR . 'assets/img/favicon.ico' ) ) {
			printf(
				'<link rel="shortcut icon" href="%s">' . "\n",
				esc_url( TOPNOTCH_URI . 'assets/img/favicon.ico' )
			);
		}
		printf( '<meta name="theme-color" content="%s">' . "\n", esc_attr( sanitize_hex_color( (string) get_theme_mod( 'topnotch_primary', '#0C7A3B' ) ) ) );
	}

	/**
	 * Inline minimal critical CSS for fast FCP. Uses system fonts (no webfont download).
	 */
	public function preload_and_critical(): void {
		// The first hero banner is the Largest Contentful Paint element on the
		// home page, so it is fetched alongside the stylesheet rather than after it.
		$hero = TOPNOTCH_DIR . 'assets/img/banner-tools.webp';
		if ( is_front_page() && file_exists( $hero ) ) {
			printf(
				'<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n",
				esc_url( TOPNOTCH_URI . 'assets/img/banner-tools.webp' )
			);
		}
		$bundle = TOPNOTCH_DIR . 'assets/css/theme-bundle.min.css';
		if ( file_exists( $bundle ) ) {
			printf(
				'<link rel="preload" as="style" href="%s">' . "\n",
				esc_url( TOPNOTCH_URI . 'assets/css/theme-bundle.min.css' )
			);
		}
		echo '<style id="topnotch-critical">:root{--rk-primary:#0C7A3B;--rk-navy:#0B2A1D}body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#17211B;background:#fff}.rk-header{background:var(--rk-navy)}img{max-width:100%;height:auto}</style>' . "\n";
	}

	/**
	 * Defer all theme JS to remove render-blocking.
	 */
	public function defer_scripts( $tag, $handle = '', $src = '' ) {
		$defer = array( 'topnotch-theme', 'topnotch-ajax' );
		if ( is_string( $tag ) && in_array( $handle, $defer, true ) && false === strpos( $tag, 'defer' ) ) {
			$tag = str_replace( ' src', ' defer src', $tag );
		}
		return $tag;
	}

	/**
	 * Only load WooCommerce cart/checkout assets where needed.
	 */
	public function dequeue_woo_bloat(): void {
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return;
		}
		if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
			wp_dequeue_style( 'wc-blocks-style' );
		}
	}

	/**
	 * Drop front-end CSS/JS that this storefront never uses.
	 *
	 * Measured on the live homepage: the Gutenberg block library stylesheet is
	 * 137KB, the single largest file on the page, and a WooCommerce storefront
	 * built from PHP templates barely touches it. Contact Form 7 adds a further
	 * 25KB of CSS and JS to every page including those with no form on them.
	 */
	public function trim_front_end_assets(): void {
		if ( is_admin() ) {
			return;
		}

		// Gutenberg block styles: only needed where a page actually has blocks.
		$needs_blocks = false;
		if ( is_singular() ) {
			$post = get_post();
			$needs_blocks = $post instanceof \WP_Post && function_exists( 'has_blocks' ) && has_blocks( $post );
		}
		if ( ! $needs_blocks ) {
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'wp-block-library-theme' );
			wp_dequeue_style( 'global-styles' );
			wp_dequeue_style( 'classic-theme-styles' );
		}

		// Contact Form 7: load only where a form is present.
		if ( defined( 'WPCF7_VERSION' ) ) {
			$has_form = false;
			if ( is_singular() ) {
				$post = get_post();
				$has_form = $post instanceof \WP_Post
					&& ( has_shortcode( (string) $post->post_content, 'contact-form-7' )
						|| false !== strpos( (string) $post->post_content, 'wpcf7' ) );
			}
			if ( ! $has_form ) {
				wp_dequeue_style( 'contact-form-7' );
				wp_dequeue_script( 'contact-form-7' );
				wp_dequeue_script( 'swv' );
			}
		}
	}

	/**
	 * Remove jQuery Migrate (13KB). It exists to shim jQuery 1.x-era code; a
	 * current WooCommerce stack does not need it on the front end.
	 *
	 * @param \WP_Scripts $scripts Script registry.
	 */
	public function drop_jquery_migrate( $scripts ): void {
		if ( is_admin() || empty( $scripts->registered['jquery'] ) ) {
			return;
		}
		$deps = $scripts->registered['jquery']->deps;
		$scripts->registered['jquery']->deps = array_diff( $deps, array( 'jquery-migrate' ) );
	}

	/**
	 * Strip front-end bloat: emoji detection, embed script, generator/rsd meta.
	 */
	public function trim_head(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		add_filter( 'emoji_svg_url', '__return_false' );
		add_filter(
			'tiny_mce_plugins',
			static function ( $plugins ) {
				return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : $plugins;
			}
		);
		add_action(
			'wp_footer',
			static function () {
				wp_dequeue_script( 'wp-embed' );
			},
			1
		);
	}

}
