<?php
/**
 * Merchant Compliance Inspector.
 *
 * Admin tool (WooCommerce > Merchant Compliance) that audits the store against Google
 * Merchant Center policies with an emphasis on Misrepresentation: verifiable business
 * identity, contactability, the required policy pages, a secure & functional checkout,
 * and honest product data. Each finding carries a severity and a concrete fix, and the
 * page links out to request a Merchant Center review once criticals are cleared.
 *
 * @package TopnotchMall
 */

declare( strict_types = 1 );

namespace TopnotchMall;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the compliance report on an admin screen.
 */
final class Merchant_Inspector {

	const SLUG = 'topnotch-merchant-inspector';

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 90 );
	}

	public function menu(): void {
		$parent = class_exists( 'WooCommerce' ) ? 'woocommerce' : 'tools.php';
		$cap    = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
		add_submenu_page(
			$parent,
			__( 'Merchant Compliance', 'topnotch-mall' ),
			__( 'Merchant Compliance', 'topnotch-mall' ),
			$cap,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Build one check row.
	 */
	private function check( string $group, string $label, string $status, string $detail, string $fix, string $link = '', string $link_label = '' ): array {
		return array(
			'group'      => $group,
			'label'      => $label,
			'status'     => $status,
			'detail'     => $detail,
			'fix'        => $fix,
			'link'       => $link,
			'link_label' => $link_label,
		);
	}

	private function brand_taxonomy(): string {
		foreach ( array( 'product_brand', 'pwb-brand', 'product-brand', 'yith_product_brand' ) as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				return $tax;
			}
		}
		return '';
	}

	/**
	 * Resolve the URL of the first published page matching any of the candidate slugs.
	 */
	private function page_url( array $slugs ): string {
		$ids = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_name__in'  => $slugs,
				'numberposts'    => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'suppress_filters' => false,
			)
		);
		return ! empty( $ids ) ? (string) get_permalink( $ids[0] ) : '';
	}

	/**
	 * Count published products failing a meta condition.
	 */
	private function count_products_meta( array $meta_query ): int {
		$q = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * Run every compliance check and return the rows.
	 */
	private function run_checks(): array {
		$checks   = array();
		$admin    = admin_url();
		$has_wc   = class_exists( 'WooCommerce' );

		/* ---- Trust & identity ---- */
		$https = is_ssl() || 0 === strpos( home_url(), 'https://' );
		$checks[] = $this->check(
			'Trust & identity',
			__( 'Secure site (HTTPS)', 'topnotch-mall' ),
			$https ? 'pass' : 'critical',
			$https ? __( 'The store loads over HTTPS.', 'topnotch-mall' ) : __( 'The site is not served over HTTPS. Merchant Center requires a secure checkout.', 'topnotch-mall' ),
			__( 'Install an SSL certificate (free via your host / LiteSpeed) and set WordPress Address + Site Address to https:// under Settings > General.', 'topnotch-mall' ),
			admin_url( 'options-general.php' ),
			__( 'Open General settings', 'topnotch-mall' )
		);

		$indexable = '1' === (string) get_option( 'blog_public', '1' );
		$checks[] = $this->check(
			'Trust & identity',
			__( 'Search engines allowed to index', 'topnotch-mall' ),
			$indexable ? 'pass' : 'critical',
			$indexable ? __( 'The site is indexable.', 'topnotch-mall' ) : __( '"Discourage search engines" is ON — this blocks Google and can fail Merchant review.', 'topnotch-mall' ),
			__( 'Settings > Reading: uncheck "Discourage search engines from indexing this site".', 'topnotch-mall' ),
			admin_url( 'options-reading.php' ),
			__( 'Open Reading settings', 'topnotch-mall' )
		);

		$name    = trim( (string) get_bloginfo( 'name' ) );
		$name_ok = '' !== $name && 0 !== strcasecmp( $name, 'My Site' );
		$checks[] = $this->check(
			'Trust & identity',
			__( 'Business name set', 'topnotch-mall' ),
			$name_ok ? 'pass' : 'warning',
			$name_ok ? sprintf( __( 'Site title: %s', 'topnotch-mall' ), $name ) : __( 'Site title is empty or a placeholder.', 'topnotch-mall' ),
			__( 'Settings > General: set the Site Title to your registered business name.', 'topnotch-mall' ),
			admin_url( 'options-general.php' ),
			__( 'Open General settings', 'topnotch-mall' )
		);

		$addr    = trim( (string) get_option( 'woocommerce_store_address' ) );
		$city    = trim( (string) get_option( 'woocommerce_store_city' ) );
		$addr_ok = '' !== $addr && '' !== $city;
		$checks[] = $this->check(
			'Trust & identity',
			__( 'Physical business address', 'topnotch-mall' ),
			$addr_ok ? 'pass' : 'critical',
			$addr_ok ? __( 'A store address is configured.', 'topnotch-mall' ) : __( 'No store address set. A verifiable physical address is central to the Misrepresentation policy.', 'topnotch-mall' ),
			__( 'WooCommerce > Settings > General: complete the Store Address, and show it in the footer/Contact page.', 'topnotch-mall' ),
			$has_wc ? admin_url( 'admin.php?page=wc-settings' ) : $admin,
			__( 'Open WooCommerce settings', 'topnotch-mall' )
		);

		$phone    = trim( (string) get_theme_mod( 'topnotch_phone', '' ) );
		$email    = trim( (string) get_theme_mod( 'topnotch_email', '' ) );
		$contact  = ( '' !== $phone ) || ( '' !== $email );
		$checks[] = $this->check(
			'Trust & identity',
			__( 'Contact details visible (phone/email)', 'topnotch-mall' ),
			$contact ? 'pass' : 'warning',
			$contact ? __( 'Contact phone/email are set and shown in the header/footer.', 'topnotch-mall' ) : __( 'No contact phone or email configured.', 'topnotch-mall' ),
			__( 'Appearance > Customize > set the Topnotch Mall phone and email so shoppers can reach a real business.', 'topnotch-mall' ),
			admin_url( 'customize.php' ),
			__( 'Open Customizer', 'topnotch-mall' )
		);

		/* ---- Required policies ---- */
		$privacy_id = (int) get_option( 'wp_page_for_privacy_policy' );
		$privacy    = $privacy_id > 0 ? get_permalink( $privacy_id ) : $this->page_url( array( 'privacy-policy', 'privacy' ) );
		$checks[] = $this->check(
			'Required policies',
			__( 'Privacy Policy page', 'topnotch-mall' ),
			$privacy ? 'pass' : 'critical',
			$privacy ? __( 'A Privacy Policy is published.', 'topnotch-mall' ) : __( 'No Privacy Policy page found.', 'topnotch-mall' ),
			__( 'Settings > Privacy: create/assign a Privacy Policy page, then link it in the footer.', 'topnotch-mall' ),
			$privacy ? $privacy : admin_url( 'options-privacy.php' ),
			$privacy ? __( 'View page', 'topnotch-mall' ) : __( 'Create policy', 'topnotch-mall' )
		);

		$terms_id = function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : 0;
		$terms    = $terms_id > 0 ? get_permalink( $terms_id ) : $this->page_url( array( 'terms', 'terms-and-conditions', 'terms-conditions' ) );
		$checks[] = $this->check(
			'Required policies',
			__( 'Terms & Conditions page', 'topnotch-mall' ),
			$terms ? 'pass' : 'critical',
			$terms ? __( 'Terms & Conditions are published.', 'topnotch-mall' ) : __( 'No Terms & Conditions page found.', 'topnotch-mall' ),
			__( 'Create a Terms & Conditions page and assign it under WooCommerce > Settings > Advanced.', 'topnotch-mall' ),
			$terms ? $terms : admin_url( 'post-new.php?post_type=page' ),
			$terms ? __( 'View page', 'topnotch-mall' ) : __( 'Create page', 'topnotch-mall' )
		);

		$refund = $this->page_url( array( 'refund_returns', 'refund-returns', 'refund-policy', 'return-policy', 'returns', 'return-refund-policy', 'returns-refunds' ) );
		$checks[] = $this->check(
			'Required policies',
			__( 'Return & Refund Policy page', 'topnotch-mall' ),
			$refund ? 'pass' : 'critical',
			$refund ? __( 'A Return/Refund policy is published.', 'topnotch-mall' ) : __( 'No Return/Refund policy found. Merchant Center requires clear returns terms.', 'topnotch-mall' ),
			__( 'Publish a Return & Refund Policy page (window, conditions, how to request) and link it in the footer.', 'topnotch-mall' ),
			$refund ? $refund : admin_url( 'post-new.php?post_type=page' ),
			$refund ? __( 'View page', 'topnotch-mall' ) : __( 'Create page', 'topnotch-mall' )
		);

		$shipping_pg = $this->page_url( array( 'shipping', 'delivery', 'shipping-policy', 'shipping-delivery-policy', 'shipping-delivery', 'delivery-policy' ) );
		$checks[] = $this->check(
			'Required policies',
			__( 'Shipping & Delivery Policy page', 'topnotch-mall' ),
			$shipping_pg ? 'pass' : 'critical',
			$shipping_pg ? __( 'A Shipping/Delivery policy is published.', 'topnotch-mall' ) : __( 'No Shipping/Delivery policy found.', 'topnotch-mall' ),
			__( 'Publish a Shipping & Delivery Policy (areas, timelines, costs) and link it in the footer.', 'topnotch-mall' ),
			$shipping_pg ? $shipping_pg : admin_url( 'post-new.php?post_type=page' ),
			$shipping_pg ? __( 'View page', 'topnotch-mall' ) : __( 'Create page', 'topnotch-mall' )
		);

		$contact_pg = $this->page_url( array( 'contact', 'contact-us' ) );
		$checks[] = $this->check(
			'Required policies',
			__( 'Contact Us page', 'topnotch-mall' ),
			$contact_pg ? 'pass' : 'warning',
			$contact_pg ? __( 'A Contact page is published.', 'topnotch-mall' ) : __( 'No dedicated Contact page found.', 'topnotch-mall' ),
			__( 'Publish a Contact Us page with address, phone, email and hours.', 'topnotch-mall' ),
			$contact_pg ? $contact_pg : admin_url( 'post-new.php?post_type=page' ),
			$contact_pg ? __( 'View page', 'topnotch-mall' ) : __( 'Create page', 'topnotch-mall' )
		);

		/* ---- Store function ---- */
		$missing_pages = array();
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'shop' => 'Shop', 'cart' => 'Cart', 'checkout' => 'Checkout', 'myaccount' => 'My Account' ) as $k => $lbl ) {
				if ( wc_get_page_id( $k ) < 1 ) {
					$missing_pages[] = $lbl;
				}
			}
		}
		$checks[] = $this->check(
			'Store function',
			__( 'WooCommerce core pages set', 'topnotch-mall' ),
			empty( $missing_pages ) ? 'pass' : 'critical',
			empty( $missing_pages ) ? __( 'Shop, Cart, Checkout and My Account are configured.', 'topnotch-mall' ) : sprintf( __( 'Missing: %s', 'topnotch-mall' ), implode( ', ', $missing_pages ) ),
			__( 'WooCommerce > Settings > Advanced: assign the Cart, Checkout and My Account pages.', 'topnotch-mall' ),
			$has_wc ? admin_url( 'admin.php?page=wc-settings&tab=advanced' ) : $admin,
			__( 'Open Advanced settings', 'topnotch-mall' )
		);

		$enabled_gw = 0;
		if ( function_exists( 'WC' ) && WC()->payment_gateways ) {
			foreach ( WC()->payment_gateways->payment_gateways() as $g ) {
				if ( isset( $g->enabled ) && 'yes' === $g->enabled ) {
					$enabled_gw++;
				}
			}
		}
		$checks[] = $this->check(
			'Store function',
			__( 'Payment method enabled', 'topnotch-mall' ),
			$enabled_gw > 0 ? 'pass' : 'critical',
			$enabled_gw > 0 ? sprintf( __( '%d payment method(s) enabled.', 'topnotch-mall' ), $enabled_gw ) : __( 'No payment method is enabled — shoppers cannot pay.', 'topnotch-mall' ),
			__( 'WooCommerce > Settings > Payments: enable M-PESA / card / cash on delivery.', 'topnotch-mall' ),
			$has_wc ? admin_url( 'admin.php?page=wc-settings&tab=checkout' ) : $admin,
			__( 'Open Payments', 'topnotch-mall' )
		);

		$has_shipping = false;
		if ( class_exists( 'WC_Shipping_Zones' ) ) {
			foreach ( \WC_Shipping_Zones::get_zones() as $z ) {
				if ( ! empty( $z['shipping_methods'] ) ) {
					$has_shipping = true;
					break;
				}
			}
			if ( ! $has_shipping ) {
				$z0 = \WC_Shipping_Zones::get_zone( 0 );
				if ( $z0 && $z0->get_shipping_methods() ) {
					$has_shipping = true;
				}
			}
		}
		$checks[] = $this->check(
			'Store function',
			__( 'Shipping configured', 'topnotch-mall' ),
			$has_shipping ? 'pass' : 'warning',
			$has_shipping ? __( 'At least one shipping method exists.', 'topnotch-mall' ) : __( 'No shipping method found. Add rates so delivery cost is transparent.', 'topnotch-mall' ),
			__( 'WooCommerce > Settings > Shipping: add a zone (Kenya) with flat rate / free shipping.', 'topnotch-mall' ),
			$has_wc ? admin_url( 'admin.php?page=wc-settings&tab=shipping' ) : $admin,
			__( 'Open Shipping', 'topnotch-mall' )
		);

		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		$checks[] = $this->check(
			'Store function',
			__( 'Currency set', 'topnotch-mall' ),
			'' !== $currency ? 'pass' : 'warning',
			'' !== $currency ? sprintf( __( 'Store currency: %s', 'topnotch-mall' ), $currency ) : __( 'No currency configured.', 'topnotch-mall' ),
			__( 'WooCommerce > Settings > General: set the currency (KES) and ensure prices match your feed.', 'topnotch-mall' ),
			$has_wc ? admin_url( 'admin.php?page=wc-settings' ) : $admin,
			__( 'Open settings', 'topnotch-mall' )
		);

		/* ---- Product data quality ---- */
		$counts = function_exists( 'wp_count_posts' ) ? wp_count_posts( 'product' ) : null;
		$total  = $counts && isset( $counts->publish ) ? (int) $counts->publish : 0;
		$checks[] = $this->check(
			'Product data',
			__( 'Products published', 'topnotch-mall' ),
			$total > 0 ? 'pass' : 'critical',
			sprintf( __( '%d published product(s).', 'topnotch-mall' ), $total ),
			__( 'Publish real, in-stock products with accurate details before requesting review.', 'topnotch-mall' ),
			admin_url( 'edit.php?post_type=product' ),
			__( 'Open Products', 'topnotch-mall' )
		);

		if ( $total > 0 ) {
			$no_image = $this->count_products_meta( array( array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ) ) );
			$checks[] = $this->check(
				'Product data',
				__( 'Every product has a main image', 'topnotch-mall' ),
				$no_image > 0 ? 'critical' : 'pass',
				$no_image > 0 ? sprintf( __( '%d product(s) have no main image.', 'topnotch-mall' ), $no_image ) : __( 'All products have images.', 'topnotch-mall' ),
				__( 'Add a clear main image to every product. Missing/placeholder images are a common Misrepresentation flag.', 'topnotch-mall' ),
				admin_url( 'edit.php?post_type=product' ),
				__( 'Open Products', 'topnotch-mall' )
			);

			$no_price = $this->count_products_meta(
				array(
					'relation' => 'OR',
					array( 'key' => '_price', 'compare' => 'NOT EXISTS' ),
					array( 'key' => '_price', 'value' => '', 'compare' => '=' ),
				)
			);
			$checks[] = $this->check(
				'Product data',
				__( 'Every product has a price', 'topnotch-mall' ),
				$no_price > 0 ? 'critical' : 'pass',
				$no_price > 0 ? sprintf( __( '%d product(s) have no price.', 'topnotch-mall' ), $no_price ) : __( 'All products are priced.', 'topnotch-mall' ),
				__( 'Set a price on every product. The price on the page must match checkout and any Merchant feed.', 'topnotch-mall' ),
				admin_url( 'edit.php?post_type=product' ),
				__( 'Open Products', 'topnotch-mall' )
			);

			$no_sku = $this->count_products_meta(
				array(
					'relation' => 'OR',
					array( 'key' => '_sku', 'compare' => 'NOT EXISTS' ),
					array( 'key' => '_sku', 'value' => '', 'compare' => '=' ),
				)
			);
			$checks[] = $this->check(
				'Product data',
				__( 'Products have a SKU', 'topnotch-mall' ),
				$no_sku > 0 ? 'warning' : 'pass',
				$no_sku > 0 ? sprintf( __( '%d product(s) have no SKU.', 'topnotch-mall' ), $no_sku ) : __( 'All products have a SKU.', 'topnotch-mall' ),
				__( 'Add SKUs — they help Merchant feed matching and identity.', 'topnotch-mall' ),
				admin_url( 'edit.php?post_type=product' ),
				__( 'Open Products', 'topnotch-mall' )
			);

			$brand_tax = $this->brand_taxonomy();
			if ( '' !== $brand_tax ) {
				$no_brand = $this->count_products_meta( array() );
				$qb = new \WP_Query(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'no_found_rows'  => false,
						'tax_query'      => array( array( 'taxonomy' => $brand_tax, 'operator' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					)
				);
				$no_brand = (int) $qb->found_posts;
				$checks[] = $this->check(
					'Product data',
					__( 'Products assigned a brand', 'topnotch-mall' ),
					$no_brand > 0 ? 'warning' : 'pass',
					$no_brand > 0 ? sprintf( __( '%d product(s) have no brand.', 'topnotch-mall' ), $no_brand ) : __( 'All products have a brand.', 'topnotch-mall' ),
					__( 'Assign the correct brand to each product. Accurate brand data reduces misrepresentation risk.', 'topnotch-mall' ),
					admin_url( 'edit.php?post_type=product' ),
					__( 'Open Products', 'topnotch-mall' )
				);
			}
		}

		return $checks;
	}

	/**
	 * Render the admin report screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$checks = $this->run_checks();
		$crit   = 0;
		$warn   = 0;
		$pass   = 0;
		foreach ( $checks as $c ) {
			if ( 'critical' === $c['status'] ) {
				$crit++;
			} elseif ( 'warning' === $c['status'] ) {
				$warn++;
			} else {
				$pass++;
			}
		}
		$ready = 0 === $crit;
		?>
		<div class="wrap rk-mci">
			<h1><?php esc_html_e( 'Merchant Compliance Inspector', 'topnotch-mall' ); ?></h1>
			<p class="rk-mci__intro"><?php esc_html_e( 'Audits your store against Google Merchant Center policies, with emphasis on Misrepresentation. Fix the criticals first, then request a Merchant Center review. Refresh this page to re-scan.', 'topnotch-mall' ); ?></p>

			<div class="rk-mci__summary">
				<span class="rk-mci__pill rk-mci__pill--critical"><?php echo esc_html( sprintf( _n( '%d critical', '%d critical', $crit, 'topnotch-mall' ), $crit ) ); ?></span>
				<span class="rk-mci__pill rk-mci__pill--warning"><?php echo esc_html( sprintf( _n( '%d warning', '%d warnings', $warn, 'topnotch-mall' ), $warn ) ); ?></span>
				<span class="rk-mci__pill rk-mci__pill--pass"><?php echo esc_html( sprintf( _n( '%d passed', '%d passed', $pass, 'topnotch-mall' ), $pass ) ); ?></span>
			</div>

			<div class="rk-mci__review <?php echo $ready ? 'is-ready' : 'is-blocked'; ?>">
				<?php if ( $ready ) : ?>
					<p><strong><?php esc_html_e( 'No criticals remaining.', 'topnotch-mall' ); ?></strong> <?php esc_html_e( 'You can request a Merchant Center review: open your Merchant Center account, go to the Misrepresentation issue, confirm the fixes, and click Request review.', 'topnotch-mall' ); ?></p>
					<a class="button button-primary" href="https://merchants.google.com/" target="_blank" rel="noopener"><?php esc_html_e( 'Open Google Merchant Center', 'topnotch-mall' ); ?></a>
				<?php else : ?>
					<p><strong><?php esc_html_e( 'Resolve the critical items below before requesting a review.', 'topnotch-mall' ); ?></strong> <?php esc_html_e( 'Requesting a review with unresolved criticals usually results in another rejection.', 'topnotch-mall' ); ?></p>
				<?php endif; ?>
			</div>

			<?php
			$groups = array();
			foreach ( $checks as $c ) {
				$groups[ $c['group'] ][] = $c;
			}
			foreach ( $groups as $group => $rows ) :
				?>
				<h2 class="rk-mci__group"><?php echo esc_html( $group ); ?></h2>
				<table class="widefat rk-mci__table">
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr class="rk-mci__row rk-mci__row--<?php echo esc_attr( $row['status'] ); ?>">
							<td class="rk-mci__badge"><span class="rk-mci__dot rk-mci__dot--<?php echo esc_attr( $row['status'] ); ?>"></span></td>
							<td class="rk-mci__label"><strong><?php echo esc_html( $row['label'] ); ?></strong><br><span class="rk-mci__detail"><?php echo esc_html( $row['detail'] ); ?></span>
								<?php if ( 'pass' !== $row['status'] && '' !== $row['fix'] ) : ?>
									<br><span class="rk-mci__fix"><?php echo esc_html( $row['fix'] ); ?></span>
								<?php endif; ?>
							</td>
							<td class="rk-mci__action">
								<?php if ( '' !== $row['link'] ) : ?>
									<a class="button button-small" href="<?php echo esc_url( $row['link'] ); ?>"><?php echo esc_html( '' !== $row['link_label'] ? $row['link_label'] : __( 'Open', 'topnotch-mall' ) ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>

			<p class="rk-mci__foot"><?php esc_html_e( 'This inspector covers the store-side signals Merchant Center weighs most under Misrepresentation. It cannot see your Merchant Center feed diagnostics, so also resolve any product-level issues shown there.', 'topnotch-mall' ); ?></p>
		</div>
		<style>
			.rk-mci__intro{max-width:820px;color:#50575e}
			.rk-mci__summary{display:flex;gap:10px;margin:14px 0 8px}
			.rk-mci__pill{display:inline-block;padding:6px 14px;border-radius:20px;font-weight:700;color:#fff}
			.rk-mci__pill--critical{background:#b32d2e}
			.rk-mci__pill--warning{background:#bd8600}
			.rk-mci__pill--pass{background:#1a7f37}
			.rk-mci__review{margin:8px 0 18px;padding:14px 16px;border-radius:8px;max-width:820px}
			.rk-mci__review.is-ready{background:#edfaef;border:1px solid #1a7f37}
			.rk-mci__review.is-blocked{background:#fcf0f0;border:1px solid #b32d2e}
			.rk-mci__group{margin:22px 0 6px;font-size:15px}
			.rk-mci__table{max-width:980px}
			.rk-mci__badge{width:34px;text-align:center;vertical-align:top;padding-top:14px}
			.rk-mci__dot{display:inline-block;width:12px;height:12px;border-radius:50%}
			.rk-mci__dot--critical{background:#b32d2e}
			.rk-mci__dot--warning{background:#bd8600}
			.rk-mci__dot--pass{background:#1a7f37}
			.rk-mci__detail{color:#50575e}
			.rk-mci__fix{color:#1d2327;font-style:italic}
			.rk-mci__action{width:180px;text-align:right;vertical-align:middle}
			.rk-mci__foot{max-width:820px;color:#787c82;font-style:italic;margin-top:16px}
		</style>
		<?php
	}
}
