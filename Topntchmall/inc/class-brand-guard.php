<?php
/**
 * Brand guard: never render another company's name.
 *
 * Roughly 64% of the 846 products on this site carry a short description of
 * the form "Buy the PRODUCT in Kenya from TopTech Machinery." - boilerplate
 * that credits a different retailer. Google Merchant Center treats product
 * data naming another merchant as Misrepresentation, which is one of the
 * faster routes to a suspension and a hard one to appeal.
 *
 * The permanent fix is a database search and replace. This class is the
 * belt-and-braces layer: it rewrites the name on the way out, so no page,
 * feed or structured-data blob can render it even if a stale row survives
 * the database pass or a future import reintroduces it.
 *
 * @package TopnotchMall
 */

declare( strict_types = 1 );

namespace TopnotchMall;

defined( 'ABSPATH' ) || exit;

/**
 * Rewrites competitor / wrong-brand names in front-end output.
 */
final class Brand_Guard {

	/**
	 * Wrong name => correct name. Longest phrases first: replacing the bare
	 * token before the full phrase would turn "TopTech Machinery" into
	 * "Topnotch Mall Machinery".
	 *
	 * @var array<string,string>
	 */
	private const REPLACEMENTS = array(
		'TopTech Machinery' => 'Topnotch Mall',
		'Toptech Machinery' => 'Topnotch Mall',
		'TopTech'           => 'Topnotch Mall',
		'Toptech'           => 'Topnotch Mall',
	);

	public function hooks(): void {
		// Product and page copy.
		add_filter( 'the_content', array( $this, 'filter_text' ), 20 );
		add_filter( 'the_excerpt', array( $this, 'filter_text' ), 20 );
		add_filter( 'get_the_excerpt', array( $this, 'filter_text' ), 20 );
		add_filter( 'the_title', array( $this, 'filter_text' ), 20 );
		add_filter( 'woocommerce_short_description', array( $this, 'filter_text' ), 20 );
		add_filter( 'woocommerce_product_get_short_description', array( $this, 'filter_text' ), 20 );
		add_filter( 'woocommerce_product_get_description', array( $this, 'filter_text' ), 20 );
		add_filter( 'woocommerce_product_get_name', array( $this, 'filter_text' ), 20 );

		// Anything Google reads directly.
		add_filter( 'woocommerce_structured_data_product', array( $this, 'filter_structured_data' ), 20 );
		add_filter( 'document_title_parts', array( $this, 'filter_title_parts' ), 20 );
		add_filter( 'wpseo_metadesc', array( $this, 'filter_text' ), 20 );
		add_filter( 'wpseo_opengraph_desc', array( $this, 'filter_text' ), 20 );
		add_filter( 'rank_math/frontend/description', array( $this, 'filter_text' ), 20 );
	}

	/**
	 * Replace the wrong brand name in a string.
	 *
	 * @param mixed $text Value passed by the filter; non-strings pass through.
	 * @return mixed
	 */
	public function filter_text( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return $text;
		}
		if ( false === stripos( $text, 'toptech' ) ) {
			return $text;
		}
		return str_replace(
			array_keys( self::REPLACEMENTS ),
			array_values( self::REPLACEMENTS ),
			$text
		);
	}

	/**
	 * Walk WooCommerce's JSON-LD product data and clean every string in it.
	 *
	 * @param mixed $data Structured data array.
	 * @return mixed
	 */
	public function filter_structured_data( $data ) {
		if ( is_string( $data ) ) {
			return $this->filter_text( $data );
		}
		if ( ! is_array( $data ) ) {
			return $data;
		}
		foreach ( $data as $key => $value ) {
			$data[ $key ] = $this->filter_structured_data( $value );
		}
		return $data;
	}

	/**
	 * Clean the document title parts.
	 *
	 * @param mixed $parts Title parts.
	 * @return mixed
	 */
	public function filter_title_parts( $parts ) {
		if ( ! is_array( $parts ) ) {
			return $parts;
		}
		foreach ( $parts as $key => $value ) {
			if ( is_string( $value ) ) {
				$parts[ $key ] = $this->filter_text( $value );
			}
		}
		return $parts;
	}
}
