<?php
/**
 * One-time database brand migration: TopTech Machinery -> Topnotch Mall.
 *
 * Brand_Guard rewrites the brand name on OUTPUT, which is enough for a human
 * reading the site. A Google Merchant Center product feed, however, is built
 * from DATABASE ROWS and never passes through those output filters, so the
 * feed would advertise "TopTech Machinery" while the landing page says
 * "Topnotch Mall". Google treats a feed-to-landing-page mismatch as
 * Misrepresentation, so the stored values have to be corrected at source.
 *
 * Safety notes:
 * - Matching is CASE-SENSITIVE and deliberately so. The CamelCase forms
 *   ("TopTech Machinery", "TopTech") only ever appear in human-readable
 *   text. The lowercase form ("toptech") is what appears in post slugs,
 *   image filenames and URLs, and those must NOT change or we break
 *   permalinks and image sources. Leaving lowercase alone gives us
 *   URL safety for free.
 * - post_name and guid are never touched, for the same reason.
 * - Values are unserialized, replaced recursively, then re-serialized, so
 *   serialized arrays whose string lengths change are not corrupted.
 * - Work is batched per admin_init request, because the site's response
 *   time makes a single large pass likely to hit the PHP time limit.
 *
 * @package Topnotch_Mall
 */

declare( strict_types = 1 );

namespace TopnotchMall;

defined( 'ABSPATH' ) || exit;

class Brand_Migration {

	const FLAG   = 'topnotch_brand_db_migration_v1';
	const REPORT = 'topnotch_brand_db_migration_v1_report';
	const BATCH  = 200;

	/**
	 * Replacement pairs, longest first so the qualified name wins.
	 *
	 * @var array<string,string>
	 */
	private $pairs = array(
		'TopTech Machinery' => 'Topnotch Mall',
		'TopTech'           => 'Topnotch Mall',
	);

	/**
	 * Hook the migration.
	 */
	public function hooks(): void {
		add_action( 'admin_init', array( $this, 'maybe_run' ) );
	}

	/**
	 * Run a batch if the migration has not finished yet.
	 */
	public function maybe_run() {
		if ( 'done' === get_option( self::FLAG ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$report = $this->run_batch();

		$previous = get_option( self::REPORT );
		if ( is_array( $previous ) ) {
			foreach ( $previous as $key => $value ) {
				if ( isset( $report[ $key ] ) && is_int( $value ) ) {
					$report[ $key ] += $value;
				}
			}
		}
		update_option( self::REPORT, $report, false );

		if ( empty( $report['remaining'] ) ) {
			update_option( self::FLAG, 'done', false );
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}
		}
	}

	/**
	 * Process one batch across posts, postmeta, options and terms.
	 *
	 * @return array<string,int>
	 */
	private function run_batch() {
		$counts = array(
			'posts'     => $this->migrate_posts(),
			'postmeta'  => $this->migrate_postmeta(),
			'options'   => $this->migrate_options(),
			'terms'     => $this->migrate_terms(),
			'remaining' => 0,
		);

		$counts['remaining'] = $this->count_remaining();

		return $counts;
	}

	/**
	 * Replace inside a value of any shape, preserving structure.
	 *
	 * @param mixed $value Value to filter.
	 * @return mixed
	 */
	private function deep_replace( $value ) {
		if ( is_string( $value ) ) {
			return str_replace( array_keys( $this->pairs ), array_values( $this->pairs ), $value );
		}

		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $item ) {
				$out[ $key ] = $this->deep_replace( $item );
			}
			return $out;
		}

		if ( is_object( $value ) ) {
			$clone = clone $value;
			foreach ( get_object_vars( $clone ) as $key => $item ) {
				$clone->$key = $this->deep_replace( $item );
			}
			return $clone;
		}

		return $value;
	}

	/**
	 * SQL LIKE fragment matching any of the search terms.
	 *
	 * @param string $column Column name, already safe.
	 * @return string
	 */
	private function like_clause( $column ) {
		global $wpdb;

		$parts = array();
		foreach ( array_keys( $this->pairs ) as $needle ) {
			$parts[] = $wpdb->prepare( $column . ' LIKE %s', '%' . $wpdb->esc_like( $needle ) . '%' );
		}

		return '(' . implode( ' OR ', $parts ) . ')';
	}

	/**
	 * Titles, content and excerpts. Slugs and guids are left alone.
	 *
	 * @return int Rows updated.
	 */
	private function migrate_posts() {
		global $wpdb;

		$where = $this->like_clause( 'post_title' ) . ' OR ' . $this->like_clause( 'post_content' ) . ' OR ' . $this->like_clause( 'post_excerpt' );
		$rows  = $wpdb->get_results(
			"SELECT ID, post_title, post_content, post_excerpt FROM {$wpdb->posts} WHERE {$where} LIMIT " . (int) self::BATCH
		);

		$done = 0;
		foreach ( $rows as $row ) {
			$data = array(
				'post_title'   => $this->deep_replace( $row->post_title ),
				'post_content' => $this->deep_replace( $row->post_content ),
				'post_excerpt' => $this->deep_replace( $row->post_excerpt ),
			);

			$wpdb->update( $wpdb->posts, $data, array( 'ID' => (int) $row->ID ) );
			clean_post_cache( (int) $row->ID );
			$done++;
		}

		return $done;
	}

	/**
	 * Post meta, including serialized values.
	 *
	 * @return int Rows updated.
	 */
	private function migrate_postmeta() {
		global $wpdb;

		$where = $this->like_clause( 'meta_value' );
		$rows  = $wpdb->get_results(
			"SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE {$where} LIMIT " . (int) self::BATCH
		);

		$done = 0;
		foreach ( $rows as $row ) {
			$value = $this->deep_replace( maybe_unserialize( $row->meta_value ) );

			$wpdb->update(
				$wpdb->postmeta,
				array( 'meta_value' => maybe_serialize( $value ) ),
				array( 'meta_id' => (int) $row->meta_id )
			);
			$done++;
		}

		return $done;
	}

	/**
	 * Options, skipping transients so we do not churn caches.
	 *
	 * @return int Rows updated.
	 */
	private function migrate_options() {
		global $wpdb;

		$where = $this->like_clause( 'option_value' );
		$rows  = $wpdb->get_results(
			"SELECT option_name, option_value FROM {$wpdb->options}
			 WHERE {$where}
			 AND option_name NOT LIKE '\_transient%'
			 AND option_name NOT LIKE '\_site\_transient%'
			 LIMIT " . (int) self::BATCH
		);

		$done = 0;
		foreach ( $rows as $row ) {
			$value = $this->deep_replace( maybe_unserialize( $row->option_value ) );
			update_option( $row->option_name, $value );
			$done++;
		}

		return $done;
	}

	/**
	 * Term names and descriptions (categories, brands, tags).
	 *
	 * @return int Rows updated.
	 */
	private function migrate_terms() {
		global $wpdb;

		$done = 0;

		$where = $this->like_clause( 'name' );
		$terms = $wpdb->get_results(
			"SELECT term_id, name FROM {$wpdb->terms} WHERE {$where} LIMIT " . (int) self::BATCH
		);
		foreach ( $terms as $term ) {
			$wpdb->update(
				$wpdb->terms,
				array( 'name' => $this->deep_replace( $term->name ) ),
				array( 'term_id' => (int) $term->term_id )
			);
			clean_term_cache( (int) $term->term_id );
			$done++;
		}

		$where = $this->like_clause( 'description' );
		$taxes = $wpdb->get_results(
			"SELECT term_taxonomy_id, description FROM {$wpdb->term_taxonomy} WHERE {$where} LIMIT " . (int) self::BATCH
		);
		foreach ( $taxes as $tax ) {
			$wpdb->update(
				$wpdb->term_taxonomy,
				array( 'description' => $this->deep_replace( $tax->description ) ),
				array( 'term_taxonomy_id' => (int) $tax->term_taxonomy_id )
			);
			$done++;
		}

		return $done;
	}

	/**
	 * How many rows still contain the old brand.
	 *
	 * @return int
	 */
	private function count_remaining() {
		global $wpdb;

		$total = 0;

		$where  = $this->like_clause( 'post_title' ) . ' OR ' . $this->like_clause( 'post_content' ) . ' OR ' . $this->like_clause( 'post_excerpt' );
		$total += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$where}" );

		$where  = $this->like_clause( 'meta_value' );
		$total += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE {$where}" );

		$where  = $this->like_clause( 'option_value' );
		$total += (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options}
			 WHERE {$where}
			 AND option_name NOT LIKE '\_transient%'
			 AND option_name NOT LIKE '\_site\_transient%'"
		);

		$where  = $this->like_clause( 'name' );
		$total += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE {$where}" );

		$where  = $this->like_clause( 'description' );
		$total += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE {$where}" );

		return $total;
	}
}
