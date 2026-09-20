<?php
/**
 * Theme setup: supports, menus, image sizes, i18n.
 *
 * @package TopnotchMall
 */

declare( strict_types = 1 );

namespace TopnotchMall;

defined( 'ABSPATH' ) || exit;

/**
 * Registers core theme supports and navigation.
 */
final class Setup {

	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		add_action( 'after_setup_theme', array( $this, 'theme_supports' ) );
		add_action( 'after_setup_theme', array( $this, 'register_menus' ) );
		add_action( 'after_setup_theme', array( $this, 'image_sizes' ) );
		add_action( 'widgets_init', array( $this, 'register_sidebars' ) );
		add_filter( 'wp_nav_menu_objects', array( $this, 'dedupe_menu_objects' ), 10, 2 );
	}

	/**
	 * Declare theme feature supports.
	 */
	public function theme_supports(): void {
		load_theme_textdomain( 'topnotch-mall', TOPNOTCH_DIR . 'languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'customize-selective-refresh-widgets' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support(
			'html5',
			array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
		);
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 60,
				'width'       => 220,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);
	}

	/**
	 * Register navigation menus used across the header/footer.
	 */
	public function register_menus(): void {
		register_nav_menus(
			array(
				'primary'        => __( 'Primary Navigation', 'topnotch-mall' ),
				'vertical_cats'  => __( 'Hero Vertical Categories', 'topnotch-mall' ),
				'footer_company' => __( 'Footer: Company', 'topnotch-mall' ),
				'footer_service' => __( 'Footer: Customer Service', 'topnotch-mall' ),
				'footer_policies'=> __( 'Footer: Policies', 'topnotch-mall' ),
			)
		);
	}

	/**
	 * Register 1:1 product image size for uniform cards + hero.
	 */
	public function image_sizes(): void {
		add_image_size( 'topnotch-card', 600, 600, true );
		add_image_size( 'topnotch-hero', 1200, 500, true );
		add_image_size( 'topnotch-cat', 480, 360, true );
	}

	/**
	 * Register footer + shop sidebar widget areas.
	 */
	public function register_sidebars(): void {
		$defaults = array(
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget__title">',
			'after_title'   => '</h3>',
		);
		register_sidebar( array_merge( $defaults, array( 'name' => __( 'Shop Sidebar', 'topnotch-mall' ), 'id' => 'shop-sidebar' ) ) );
		foreach ( array( 1, 2, 3, 4 ) as $i ) {
			register_sidebar( array_merge( $defaults, array(
				'name' => sprintf( __( 'Footer Column %d', 'topnotch-mall' ), $i ),
				'id'   => 'footer-' . $i,
			) ) );
		}
	}

	/**
	 * Drop repeated entries from a menu as it renders.
	 *
	 * A menu can end up holding two copies of the same link (an interrupted
	 * rebuild, an import, or a hand edit in Appearance > Menus). This is the
	 * last line of defence: whatever the database holds, a visitor never sees
	 * the same page listed twice in one menu. Items with children are always
	 * kept, so a duplicate parent can never orphan a submenu.
	 *
	 * @param array $items Menu item objects.
	 * @param mixed $args  Menu arguments.
	 * @return array
	 */
	public function dedupe_menu_objects( $items, $args = null ) {
		if ( is_array( $items ) === false || count( $items ) < 2 ) {
			return $items;
		}

		$has_children = array();
		foreach ( $items as $item ) {
			$parent = isset( $item->menu_item_parent ) ? (int) $item->menu_item_parent : 0;
			if ( $parent > 0 ) {
				$has_children[ $parent ] = true;
			}
		}

		$seen = array();
		$kept = array();
		foreach ( $items as $item ) {
			$id     = isset( $item->ID ) ? (int) $item->ID : 0;
			$parent = isset( $item->menu_item_parent ) ? (int) $item->menu_item_parent : 0;
			$type   = isset( $item->type ) ? (string) $item->type : '';
			$object = isset( $item->object_id ) ? (int) $item->object_id : 0;
			$url    = isset( $item->url ) ? untrailingslashit( strtolower( (string) $item->url ) ) : '';

			$key = 'post_type' === $type || 'taxonomy' === $type
				? $parent . '|' . $type . '|' . $object
				: $parent . '|url|' . $url;

			if ( isset( $seen[ $key ] ) && empty( $has_children[ $id ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$kept[]       = $item;
		}

		return $kept;
	}
}
