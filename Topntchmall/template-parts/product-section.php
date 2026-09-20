<?php
/**
 * A single homepage product row: 6 products from one category + "View More".
 *
 * @package TopnotchMall
 */

defined( 'ABSPATH' ) || exit;

$slug = (string) get_query_var( 'rk_cat_slug' );
if ( '' === $slug || ! taxonomy_exists( 'product_cat' ) ) {
	return;
}
$term = get_term_by( 'slug', $slug, 'product_cat' );
if ( ! $term ) {
	return;
}

/**
 * Rotate the six products shown for this category on every page load, so a
 * refresh surfaces different stock instead of the same six items forever.
 * Filter to 'date' (or any WP_Query orderby) to pin the row to newest first.
 */
$rk_orderby = apply_filters( 'topnotch_product_section_orderby', 'rand', $slug );

$rk_args = array(
	'post_type'           => 'product',
	'posts_per_page'      => (int) apply_filters( 'topnotch_product_section_count', 6, $slug ),
	'no_found_rows'       => true,
	'ignore_sticky_posts' => true,
	'post_status'         => 'publish',
	'orderby'             => $rk_orderby,
	'tax_query'           => array( // phpcs:ignore WordPress.DB.SlowDBQuery
		array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $slug ),
	),
);

// Keep out-of-stock items out of the rotation when WooCommerce is set to hide them.
if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
	$rk_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
		array( 'key' => '_stock_status', 'value' => 'instock' ),
	);
}

$q = new WP_Query( $rk_args );
if ( ! $q->have_posts() ) {
	wp_reset_postdata();
	return;
}
?>
<section class="rk-section rk-section--products">
	<div class="container">
		<div class="rk-section__head">
			<h2><?php echo esc_html( $term->name ); ?></h2>
			<a class="rk-viewmore" href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php esc_html_e( 'View more', 'topnotch-mall' ); ?> &rarr;</a>
		</div>
		<div class="rk-products">
			<?php
			while ( $q->have_posts() ) {
				$q->the_post();
				wc_get_template_part( 'content', 'product' );
			}
			?>
		</div>
	</div>
</section>
<?php
wp_reset_postdata();
