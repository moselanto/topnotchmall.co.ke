<?php
/**
 * Homepage: hero + vertical categories, featured categories, per-category product rows.
 *
 * @package TopnotchMall
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="site-main">

	<?php get_template_part( 'template-parts/hero' ); ?>

	<?php get_template_part( 'template-parts/trust-band' ); ?>

	<?php get_template_part( 'template-parts/featured-categories' ); ?>

	<?php
	/**
	 * Product rows down the homepage.
	 *
	 * The lead categories run first, in the order given, then the rest of the
	 * catalogue follows automatically by size, so new categories appear on the
	 * homepage without anyone editing a template. Cap and order are filterable.
	 */
	$rk_lead = apply_filters(
		'topnotch_homepage_lead_categories',
		array( 'water-pumps', 'power-tools', 'solar-panels', 'welding-machines', 'generators', 'batteries' )
	);
	$rk_max = (int) apply_filters( 'topnotch_homepage_category_count', 14 );

	$rk_sections = array();
	foreach ( $rk_lead as $rk_slug ) {
		if ( term_exists( $rk_slug, 'product_cat' ) ) {
			$rk_sections[] = $rk_slug;
		}
	}

	// Fill the rest of the page with the remaining categories, largest first.
	if ( count( $rk_sections ) < $rk_max && function_exists( 'rk_cached_terms' ) ) {
		foreach ( rk_cached_terms( 'product_cat', 60 ) as $rk_term ) {
			if ( count( $rk_sections ) >= $rk_max ) {
				break;
			}
			if ( in_array( $rk_term->slug, $rk_sections, true ) ) {
				continue;
			}
			if ( (int) $rk_term->count < 1 ) {
				continue;
			}
			$rk_sections[] = $rk_term->slug;
		}
	}

	$rk_sections = array_slice( apply_filters( 'topnotch_homepage_categories', $rk_sections ), 0, $rk_max );

	foreach ( $rk_sections as $rk_slug ) {
		set_query_var( 'rk_cat_slug', $rk_slug );
		get_template_part( 'template-parts/product-section' );
	}
	?>

</main>
<?php
get_footer();
