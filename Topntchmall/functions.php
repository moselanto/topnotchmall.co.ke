<?php
/**
 * Topnotch Mall theme bootstrap.
 *
 * @package TopnotchMall
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

define( 'TOPNOTCH_VERSION', '1.26.0' );
define( 'TOPNOTCH_DIR', trailingslashit( get_template_directory() ) );
define( 'TOPNOTCH_URI', trailingslashit( get_template_directory_uri() ) );

/**
 * PSR-4-style autoloader for the TopnotchMall\ namespace (inc/ directory).
 */
spl_autoload_register(
	static function ( $class ) {
		if ( ! is_string( $class ) ) {
			return;
		}
		$prefix = 'TopnotchMall\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$relative = strtolower( str_replace( array( '\\', '_' ), array( '/', '-' ), $relative ) );
		$file     = TOPNOTCH_DIR . 'inc/class-' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

/**
 * Boot the theme. Any failure is logged rather than fatally white-screening
 * the entire site, and (in the admin) surfaced as a dismissible notice.
 */
try {
	require TOPNOTCH_DIR . 'inc/bootstrap.php';
} catch ( \Throwable $e ) {
	error_log( 'Topnotch Mall bootstrap error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
	if ( is_admin() ) {
		add_action(
			'admin_notices',
			static function () use ( $e ) {
				printf(
					'<div class="notice notice-error"><p><strong>Topnotch Mall:</strong> %s</p></div>',
					esc_html( $e->getMessage() )
				);
			}
		);
	}
}
