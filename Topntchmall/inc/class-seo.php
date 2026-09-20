<?php
/**
 * Meta descriptions, social cards and titles.
 *
 * Measured on the live site: no SEO plugin is installed and no page emits a
 * meta description. Google then invents its own snippet from page text, and
 * Merchant Center's Misrepresentation review explicitly looks for a store
 * that follows SEO guidelines and whose product data matches the shop.
 *
 * This fills the gap at the theme layer, and gets out of the way the moment
 * a real SEO plugin is installed so nothing is ever emitted twice.
 *
 * @package TopnotchMall
 */

declare( strict_types = 1 );

namespace TopnotchMall;

defined( 'ABSPATH' ) || exit;

/**
 * Emits description, canonical-friendly social tags and product metadata.
 */
final class SEO {

	private const MAX_DESC = 155;

	public function hooks(): void {
		// Stand down if a dedicated SEO plugin owns this output.
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) {
			return;
		}
		add_action( 'wp_head', array( $this, 'output_meta' ), 2 );
		add_filter( 'document_title_parts', array( $this, 'title_parts' ), 10 );
		add_filter( 'document_title_separator', array( $this, 'separator' ) );
	}

	/**
	 * Use a pipe rather than an en dash: it reads better in a SERP and is
	 * what most retail listings use.
	 *
	 * @param string $sep Current separator.
	 */
	public function separator( $sep ) {
		return '|';
	}

	/**
	 * Give the front page a title that says what is sold and where.
	 *
	 * "Topnotch Mall shopping made simple" is the tagline. It is friendly but
	 * carries no product or location signal, and the front page is the single
	 * most valuable title on the site.
	 *
	 * @param mixed $parts Title parts.
	 * @return mixed
	 */
	public function title_parts( $parts ) {
		if ( ! is_array( $parts ) ) {
			return $parts;
		}
		if ( is_front_page() || is_home() ) {
			$parts['title']   = __( 'Power Tools, Solar, Generators & Water Pumps in Kenya', 'topnotch-mall' );
			$parts['tagline'] = get_bloginfo( 'name' );
		}
		return $parts;
	}

	/**
	 * Trim to a clean sentence boundary rather than mid-word.
	 *
	 * @param string $text Raw text.
	 */
	private function trim_desc( string $text ): string {
		$text = wp_strip_all_tags( strip_shortcodes( $text ), true );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		if ( '' === $text ) {
			return '';
		}
		if ( mb_strlen( $text ) <= self::MAX_DESC ) {
			return $text;
		}
		$cut = mb_substr( $text, 0, self::MAX_DESC );
		$sp  = mb_strrpos( $cut, ' ' );
		if ( false !== $sp && $sp > 60 ) {
			$cut = mb_substr( $cut, 0, $sp );
		}
		return rtrim( $cut, " ,.;:-" ) . '...';
	}

	/**
	 * Work out the best description for whatever is being viewed.
	 */
	private function description(): string {
		$shop = __( 'Topnotch Mall', 'topnotch-mall' );

		if ( is_front_page() || is_home() ) {
			return sprintf(
				/* translators: %s: shop name. */
				__( 'Buy genuine power tools, solar panels, generators, water pumps and welding machines in Kenya from %s. Nairobi shop, countrywide delivery, M-PESA accepted.', 'topnotch-mall' ),
				$shop
			);
		}

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
			if ( $product instanceof \WC_Product ) {
				$base = (string) $product->get_short_description();
				if ( '' === trim( $base ) ) {
					$base = (string) $product->get_description();
				}
				$desc = $this->trim_desc( $base );
				if ( '' !== $desc ) {
					return $desc;
				}
				return $this->trim_desc(
					sprintf(
						/* translators: 1: product name, 2: shop name. */
						__( 'Buy the %1$s in Kenya from %2$s. Genuine stock, clear pricing and fast countrywide delivery.', 'topnotch-mall' ),
						$product->get_name(),
						$shop
					)
				);
			}
		}

		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$desc = $this->trim_desc( (string) $term->description );
				if ( '' !== $desc ) {
					return $desc;
				}
				return $this->trim_desc(
					sprintf(
						/* translators: 1: category name, 2: shop name. */
						__( 'Shop %1$s in Kenya at %2$s. Genuine brands, honest prices and delivery countrywide from our Nairobi shop.', 'topnotch-mall' ),
						$term->name,
						$shop
					)
				);
			}
		}

		if ( is_singular() ) {
			$post = get_post();
			if ( $post instanceof \WP_Post ) {
				$base = has_excerpt( $post ) ? (string) $post->post_excerpt : (string) $post->post_content;
				$desc = $this->trim_desc( $base );
				if ( '' !== $desc ) {
					return $desc;
				}
			}
		}

		if ( is_search() ) {
			return $this->trim_desc(
				sprintf(
					/* translators: %s: search term. */
					__( 'Search results for %s at Topnotch Mall Kenya.', 'topnotch-mall' ),
					get_search_query()
				)
			);
		}

		return $this->trim_desc( (string) get_bloginfo( 'description' ) );
	}

	/**
	 * The image used for social cards.
	 */
	private function social_image(): string {
		if ( is_singular() && has_post_thumbnail() ) {
			$src = wp_get_attachment_image_url( (int) get_post_thumbnail_id(), 'large' );
			if ( is_string( $src ) && '' !== $src ) {
				return $src;
			}
		}
		$fallback = TOPNOTCH_DIR . 'assets/img/banner-tools.webp';
		if ( file_exists( $fallback ) ) {
			return TOPNOTCH_URI . 'assets/img/banner-tools.webp';
		}
		return '';
	}

	/**
	 * Print the tags.
	 */
	public function output_meta(): void {
		$desc = $this->description();
		$img  = $this->social_image();
		$url  = home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) );

		if ( '' !== $desc ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
			printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
			printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $desc ) );
		}

		printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
		printf( '<meta property="og:locale" content="%s">' . "\n", esc_attr( get_locale() ) );
		printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );

		$type = ( function_exists( 'is_product' ) && is_product() ) ? 'product' : ( is_singular() ? 'article' : 'website' );
		printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( $type ) );

		if ( '' !== $img ) {
			printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $img ) );
			printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $img ) );
			echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		} else {
			echo '<meta name="twitter:card" content="summary">' . "\n";
		}

		// Price and availability, so a shared product link shows real detail
		// and Merchant Center sees the same figures the page displays.
		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
			if ( $product instanceof \WC_Product ) {
				$price = $product->get_price();
				if ( '' !== (string) $price ) {
					printf( '<meta property="product:price:amount" content="%s">' . "\n", esc_attr( (string) $price ) );
					printf( '<meta property="product:price:currency" content="%s">' . "\n", esc_attr( get_woocommerce_currency() ) );
				}
				printf(
					'<meta property="product:availability" content="%s">' . "\n",
					esc_attr( $product->is_in_stock() ? 'in stock' : 'out of stock' )
				);
			}
		}

		// Search result pages carry no value for the index.
		if ( is_search() || is_404() ) {
			echo '<meta name="robots" content="noindex, follow">' . "\n";
		}
	}
}
