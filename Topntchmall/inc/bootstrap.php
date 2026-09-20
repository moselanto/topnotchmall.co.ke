<?php
/**
 * Instantiate theme modules on load (each guarded).
 *
 * @package TopnotchMall
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

require TOPNOTCH_DIR . 'inc/helpers.php';

$topnotch_modules = array(
	'TopnotchMall\\Setup',
	'TopnotchMall\\Assets',
	'TopnotchMall\\Security',
	'TopnotchMall\\Brand_Guard',
	'TopnotchMall\\Brand_Migration',
	'TopnotchMall\\SEO',
	'TopnotchMall\\WooCommerce_Support',
	'TopnotchMall\\Ajax',
	'TopnotchMall\\Customizer',
	'TopnotchMall\\Schema',
	'TopnotchMall\\Content_Installer',
	'TopnotchMall\\Demo_Import',
	'TopnotchMall\\Single_Product',
	'TopnotchMall\\Merchant_Inspector',
);

foreach ( $topnotch_modules as $topnotch_class ) {
	try {
		if ( class_exists( $topnotch_class ) ) {
			( new $topnotch_class() )->hooks();
		}
	} catch ( \Throwable $e ) {
		error_log( 'Topnotch Mall module ' . $topnotch_class . ' failed: ' . $e->getMessage() );
	}
}

require TOPNOTCH_DIR . 'inc/required-plugins.php';
