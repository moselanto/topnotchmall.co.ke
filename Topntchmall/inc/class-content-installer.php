<?php
/**
 * Auto-creates the legal / informational pages (fully editable) and builds
 * navigation menus when the theme is activated. All content uses the real
 * Topnotch Mall business details and is written to satisfy Google Merchant
 * Center and standard e-commerce trust requirements.
 *
 * @package TopnotchMall
 */

declare( strict_types = 1 );

namespace TopnotchMall;

defined( 'ABSPATH' ) || exit;

/**
 * Content bootstrapper.
 */
final class Content_Installer {

	private const FLAG = 'topnotch_content_installed_v1';
	private const MENU_FLAG = 'topnotch_menus_v3';
	private const CAT_IMG_FLAG = 'topnotch_cat_images_v2';

	public function hooks(): void {
		add_action( 'admin_init', array( $this, 'install' ) );
		add_action( 'admin_init', array( $this, 'ensure_front_page' ) );
		add_action( 'admin_init', array( $this, 'sync_menus' ) );
		add_action( 'admin_init', array( $this, 'dedupe_menus' ), 11 );
		add_action( 'admin_init', array( $this, 'sync_category_images' ) );
		add_action( 'admin_init', array( $this, 'refresh_contact_details' ) );
		add_action( 'admin_init', array( $this, 'refresh_pages_content' ) );
		add_action( 'admin_init', array( $this, 'ensure_privacy_page' ), 12 );
		add_action( 'admin_init', array( $this, 'seed_contact_defaults' ) );
		add_action( 'admin_init', array( $this, 'refresh_brand_colors' ) );
		add_action( 'admin_init', array( $this, 'cleanup_competitor_brand' ) );
		add_action( 'admin_init', array( $this, 'reclassify_dewalt_welders' ) );
	}

	/**
	 * Create pages + menus once.
	 */
	public function install(): void {
		if ( get_option( self::FLAG ) ) {
			return;
		}
		// Only run for a user who could have just activated the theme.
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		try {
			$ids = array();
			foreach ( $this->pages() as $slug => $page ) {
				$ids[ $slug ] = $this->upsert_page( $slug, $page['title'], $page['content'] );
			}
			$this->build_menus( $ids );
			update_option( self::FLAG, time() );
		} catch ( \Throwable $e ) {
			error_log( 'Topnotch Mall content install failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Create the page if a page with that slug does not already exist.
	 */
	/**
	 * Make the storefront a static homepage instead of the blog index.
	 * Runs once (own flag) so it applies even if pages were already installed.
	 */
	public function ensure_front_page(): void {
		if ( get_option( 'topnotch_front_page_v1' ) ) {
			return;
		}
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		try {
			$home = get_page_by_path( 'home' );
			if ( $home instanceof \WP_Post ) {
				$home_id = (int) $home->ID;
			} else {
				$home_id = wp_insert_post(
					array(
						'post_title'   => 'Home',
						'post_name'    => 'home',
						'post_status'  => 'publish',
						'post_type'    => 'page',
						'post_content' => '',
					)
				);
			}
			if ( $home_id && ! is_wp_error( $home_id ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', (int) $home_id );
			}
			update_option( 'topnotch_front_page_v1', time() );
		} catch ( \Throwable $e ) {
			error_log( 'Topnotch Mall front page setup failed: ' . $e->getMessage() );
		}
	}

	private function upsert_page( string $slug, string $title, string $content ): int {
		$existing = get_page_by_path( $slug );
		if ( $existing instanceof \WP_Post ) {
			// WordPress ships its own "Privacy Policy" page as a draft, and that
			// draft squats the privacy-policy slug. get_page_by_path() returns it
			// whatever its status, so the page was found, left as a draft, and
			// linked from the footer - where it 404s for every visitor. A missing
			// or broken privacy policy is a hard Merchant Center failure, so any
			// page of ours that is not published gets published here.
			if ( 'publish' !== $existing->post_status ) {
				$update = array(
					'ID'          => (int) $existing->ID,
					'post_status' => 'publish',
				);
				// WordPress's draft carries its own suggested-text boilerplate.
				// Replace it with the real policy rather than shipping a template.
				$update['post_content'] = $content;
				$update['post_title']   = $title;
				wp_update_post( $update );
			}
			return (int) $existing->ID;
		}
		$id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * Guarantee a published, reachable Privacy Policy page.
	 *
	 * Measured on the live site: /privacy-policy/ returned 404 while the footer
	 * linked to ?page_id=3, the unpublished page WordPress creates on install.
	 * Google Merchant Center and Google Ads both require a working privacy
	 * policy, so this publishes the page, gives it the real policy text, fixes
	 * the slug, and points WordPress's privacy-page setting at it.
	 * Idempotent (own flag).
	 */
	public function ensure_privacy_page(): void {
		if ( get_option( 'topnotch_privacy_page_v1' ) ) {
			return;
		}
		if ( function_exists( 'current_user_can' ) === false || current_user_can( 'edit_theme_options' ) === false ) {
			return;
		}
		try {
			$pages = $this->pages();
			if ( empty( $pages['privacy-policy'] ) ) {
				return;
			}
			$title   = (string) $pages['privacy-policy']['title'];
			$content = (string) $pages['privacy-policy']['content'];

			// WordPress's own setting points at the draft it created on install.
			$id = (int) get_option( 'wp_page_for_privacy_policy' );
			if ( $id <= 0 ) {
				$found = get_page_by_path( 'privacy-policy' );
				$id    = $found instanceof \WP_Post ? (int) $found->ID : 0;
			}

			if ( $id > 0 && get_post_status( $id ) !== false ) {
				wp_update_post(
					array(
						'ID'           => $id,
						'post_title'   => $title,
						'post_name'    => 'privacy-policy',
						'post_content' => $content,
						'post_status'  => 'publish',
					)
				);
			} else {
				$id = $this->upsert_page( 'privacy-policy', $title, $content );
			}

			if ( $id > 0 ) {
				update_option( 'wp_page_for_privacy_policy', $id );
			}
			update_option( 'topnotch_privacy_page_v1', time() );
		} catch ( \Throwable $e ) {
			error_log( 'Topnotch Mall privacy page repair failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Re-sync theme navigation menus once per version so existing sites pick up
	 * menu changes idempotently, without duplicating items.
	 */
	public function sync_menus(): void {
		if ( get_option( self::MENU_FLAG ) ) {
			return;
		}
		if ( function_exists( 'current_user_can' ) === false || current_user_can( 'edit_theme_options' ) === false ) {
			return;
		}
		try {
			$slugs = array(
				'about-us', 'contact-us', 'payment-methods', 'return-refund-policy',
				'shipping-delivery-policy', 'track-order', 'faq', 'privacy-policy',
				'terms-conditions', 'warranty-policy', 'cookie-policy',
			);
			$ids = array();
			foreach ( $slugs as $slug ) {
				$page = get_page_by_path( $slug );
				if ( $page instanceof \WP_Post ) {
					$ids[ $slug ] = (int) $page->ID;
				}
			}
			$this->build_menus( $ids );
			update_option( self::MENU_FLAG, time() );
		} catch ( \Throwable $e ) {
			error_log( 'Topnotch Mall menu sync failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Build primary + footer menus and assign locations. Idempotent: the menu
	 * assigned to each location is cleared and rebuilt from the definitions,
	 * so the method can be re-run safely.
	 *
	 * @param array<string,int> $ids Slug => page ID.
	 */
	private function build_menus( array $ids ): void {
		// install() and sync_menus() can both fire on the same admin_init.
		// Building twice in one request duplicated items, because the second
		// pass read the menu from a stale object cache. Build once per request.
		static $already_built = false;
		if ( $already_built === true ) {
			return;
		}
		$already_built = true;

		$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );

		$defs = array(
			'primary'         => array(
				array( 'custom', 'Home', home_url( '/' ) ),
				array( 'page', 'about-us' ),
				array( 'custom', 'Shop', $shop_url ),
				array( 'page', 'return-refund-policy' ),
				array( 'page', 'shipping-delivery-policy' ),
				array( 'page', 'payment-methods' ),
				array( 'page', 'contact-us' ),
			),
			'footer_service'  => array(
				array( 'page', 'contact-us' ),
				array( 'page', 'track-order' ),
				array( 'page', 'faq' ),
				array( 'page', 'payment-methods' ),
			),
			'footer_policies' => array(
				array( 'page', 'privacy-policy' ),
				array( 'page', 'terms-conditions' ),
				array( 'page', 'return-refund-policy' ),
				array( 'page', 'shipping-delivery-policy' ),
				array( 'page', 'warranty-policy' ),
				array( 'page', 'cookie-policy' ),
			),
		);

		$assigned = function_exists( 'get_nav_menu_locations' ) ? get_nav_menu_locations() : array();

		foreach ( $defs as $location => $items ) {
			$menu_id = 0;
			if ( isset( $assigned[ $location ] ) && $assigned[ $location ] ) {
				$obj = wp_get_nav_menu_object( (int) $assigned[ $location ] );
				if ( $obj ) {
					$menu_id = (int) $obj->term_id;
				}
			}
			if ( $menu_id < 1 ) {
				$menu_name = 'Topnotch ' . $location;
				$menu      = wp_get_nav_menu_object( $menu_name );
				$created   = $menu ? (int) $menu->term_id : wp_create_nav_menu( $menu_name );
				if ( is_wp_error( $created ) ) {
					continue;
				}
				$menu_id = (int) $created;
			}
			if ( $menu_id < 1 ) {
				continue;
			}

			$this->clear_menu_items( $menu_id );

			$position = 0;
			foreach ( $items as $item ) {
				++$position;
				if ( 'custom' === $item[0] ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'    => $item[1],
							'menu-item-url'      => $item[2],
							'menu-item-type'     => 'custom',
							'menu-item-status'   => 'publish',
							'menu-item-position' => $position,
						)
					);
					continue;
				}
				$slug = $item[1];
				if ( empty( $ids[ $slug ] ) ) {
					--$position;
					continue;
				}
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-object'    => 'page',
						'menu-item-object-id' => $ids[ $slug ],
						'menu-item-type'      => 'post_type',
						'menu-item-status'    => 'publish',
						'menu-item-position'  => $position,
					)
				);
			}

			$locations              = get_theme_mod( 'nav_menu_locations', array() );
			$locations[ $location ] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}
	}

	/**
	 * The page definitions. Content is intentionally complete (not placeholder).
	 *
	 * @return array<string,array{title:string,content:string}>
	 */
	private function pages(): array {
        $name   = 'Topnotch Mall';
        $phone  = (string) get_theme_mod( 'topnotch_phone', '+254 708 777192' );
        $mail   = (string) get_theme_mod( 'topnotch_email', 'info@topnotchmall.co.ke' );
        $addr   = (string) get_theme_mod( 'topnotch_address', 'Magomano House, Tom Mboya Street, Nairobi, Kenya' );
        $wa     = function_exists( 'rk_whatsapp_number' ) ? rk_whatsapp_number() : '254708777192';
        $tel    = '+' . ( '' === $wa ? '254708777192' : $wa );
        $hours  = (string) get_theme_mod( 'topnotch_hours', 'Mon - Sat, 9AM - 5PM' );
        $cutoff = (string) get_theme_mod( 'topnotch_cutoff', '5:00pm' );

        return array(
            'about-us' => array(
                'title'   => 'About Us',
                'content' => "<p>{$name} is a Nairobi-based supplier of power tools, solar equipment, generators, water pumps, welding machines and general hardware. We sell to contractors, fundis, farmers, businesses and homeowners, and we deliver across Kenya.</p><h2>What we sell</h2><p>We stock well-known brands such as Total, Ingco, Makita, DeWalt, Bosch, Honda and Solarmax, alongside dependable value options. Everything we carry comes from authorised distributors, so the item you buy is genuine and covered by the manufacturer's warranty.</p><h2>How we work</h2><p>Prices are shown clearly in Kenya Shillings, with nothing hidden. If you are not sure which tool or machine suits the job, call or WhatsApp us and we will help you decide. Orders placed before our 5:00pm cut-off are usually sent out the same day.</p><h2>Come and see us</h2><p>Visit the shop at {$addr}, open Monday to Saturday, 9:00am to 5:00pm. You can also reach us on {$phone} or at {$mail}.</p>",
            ),
            'contact-us' => array(
                'title'   => 'Contact Us',
                'content' => "<p>You can reach {$name} by phone, WhatsApp, email or in person at our Nairobi shop. We answer most calls and messages the same day during opening hours.</p><h2>Phone and WhatsApp</h2><p>Call or message us on {$phone}. WhatsApp is usually the quickest way to send a photo of what you need or to place an order.</p><h2>Email</h2><p>Write to us at {$mail}. Please include your order number if your message is about an order you have already placed.</p><h2>Our shop</h2><p>{$addr}</p><p>Open Monday to Saturday, 9:00am to 5:00pm. Closed on Sundays and public holidays.</p><h2>Send us a message</h2><p>The quickest way to reach us is a call or WhatsApp on {$phone}. You can also email us and we will reply within one working day.</p><p class=\"rk-contact-actions\"><a class=\"rk-btn rk-btn--primary\" href=\"tel:{$tel}\">Call {$phone}</a> <a class=\"rk-btn rk-btn--primary\" href=\"https://wa.me/{$wa}\">WhatsApp us</a> <a class=\"rk-btn rk-btn--ghost\" href=\"mailto:{$mail}\">Email us</a></p>",
            ),
            'privacy-policy' => array(
                'title'   => 'Privacy Policy',
                'content' => "<p><em>Last updated: 1 September 2026</em></p><p>This policy explains how {$name} collects and uses your personal information when you shop with us or use this website. We handle personal data in line with the Data Protection Act, 2019 and are guided by the Office of the Data Protection Commissioner.</p><h2>1. Information we collect</h2><p>When you place an order or get in touch, we collect your name, phone number, email address and delivery address, together with the details of what you bought. As you use the website we also collect basic technical information such as your IP address, browser type and the pages you visit, mostly through cookies.</p><h2>2. How we use your information</h2><p>We use it to process and deliver your orders, answer your questions, keep you updated on an order, and meet our tax and record-keeping obligations. We only send offers or marketing messages if you have asked to receive them, and you can opt out at any time.</p><h2>3. Payments</h2><p>Payments go through M-PESA and licensed card processors. You enter your card or mobile-money details directly with those providers over secure connections. We do not see or store your full payment details.</p><h2>4. Who we share it with</h2><p>We share your details only with the partners who help us complete your order, such as delivery and courier companies and our payment processors, and with the authorities where the law requires it. We do not sell your personal information.</p><h2>5. How long we keep it</h2><p>We keep your information for as long as we need it to complete your order and satisfy legal and tax requirements, then we delete or anonymise it.</p><h2>6. Your rights</h2><p>You can ask to see the information we hold about you, correct it, or have it deleted, and you can object to us using it in certain ways. Email {$mail} and we will respond.</p><h2>7. Contact</h2><p>{$name}, {$addr}. {$phone} / {$mail}.</p>",
            ),
            'terms-conditions' => array(
                'title'   => 'Terms &amp; Conditions',
                'content' => "<p><em>Last updated: 1 September 2026</em></p><h2>1. About these terms</h2><p>These terms apply when you buy from {$name}, whether on this website, by phone or on WhatsApp. Placing an order means you accept them.</p><h2>2. Products, prices and stock</h2><p>Prices are in Kenya Shillings and include VAT where it applies. We work hard to keep prices, descriptions, photographs and stock levels accurate, but mistakes do happen. If we find an error in the price or description of something you have ordered, we will contact you and, if you prefer, cancel the order and refund you in full.</p><h2>3. Placing an order</h2><p>Your order is confirmed once we have received payment or, for cash on delivery, once we have confirmed it with you by phone. We may decline or cancel an order if we cannot verify it or if we suspect fraud.</p><h2>4. Payment</h2><p>We accept M-PESA, Visa, Mastercard and cash on delivery where available. There is more detail on our Payment Methods page.</p><h2>5. Delivery</h2><p>Delivery times and charges are set out in our Shipping &amp; Delivery Policy. Goods become your responsibility once they are handed to you or someone acting for you.</p><h2>6. Returns and warranty</h2><p>Our Return &amp; Refund Policy and Warranty Policy explain how to return an item or make a warranty claim, and they form part of these terms.</p><h2>7. Using the equipment safely</h2><p>Power tools and machinery must be used according to the manufacturer's instructions and with the right safety equipment. We are not responsible for injury or damage caused by incorrect or careless use.</p><h2>8. Our content</h2><p>The text, logos and images on this website belong to {$name} or our suppliers and may not be copied or reused without permission.</p><h2>9. Liability</h2><p>As far as the law allows, our responsibility for any claim is limited to the price you paid for the product concerned.</p><h2>10. Governing law</h2><p>These terms are governed by the laws of Kenya, and any dispute falls under the Kenyan courts.</p><p>{$name}, {$addr}. {$phone} / {$mail}.</p>",
            ),
            'shipping-delivery-policy' => array(
                'title'   => 'Shipping &amp; Delivery Policy',
                'content' => "<div class='rk-policy'><p class='rk-policy-updated'>Last updated: 17 September 2026</p><p class='rk-policy-lead'>We deliver countrywide from our shop in Nairobi. This page sets out exactly when your order is dispatched, how long it takes to reach you, and what it costs, so you know where you stand before you pay.</p><div class='rk-policy-summary'><h2>At a glance</h2><ul><li><strong>Order cut-off</strong><span>{$cutoff}, Mon to Sat</span></li><li><strong>Handling time</strong><span>0 to 1 working day</span></li><li><strong>Delivery time</strong><span>1 to 7 working days</span></li><li><strong>Delivery fee</strong><span>KSh 500 countrywide</span></li><li><strong>Shop collection</strong><span>Free</span></li><li><strong>We deliver to</strong><span>All 47 counties</span></li></ul></div><h2>Order cut-off and handling time</h2><p>Our order cut-off is <strong>{$cutoff}, Monday to Saturday</strong>. Orders confirmed before the cut-off on a working day are packed and handed to the carrier the same day. Orders confirmed after the cut-off, on a Sunday, or on a public holiday are dispatched the next working day.</p><p>Handling time is <strong>0 to 1 working day</strong> for items held in stock. Where an item has to be brought in from a supplier, we tell you the expected date before you pay.</p><h2>Delivery timelines</h2><p>The times below are counted in working days from dispatch, not from when the order is placed. Sundays and public holidays are not counted.</p><table class='rk-policy-table'><thead><tr><th>Destination</th><th>Delivery time after dispatch</th></tr></thead><tbody><tr><td>Nairobi CBD and surrounding estates</td><td>Same or next working day</td></tr><tr><td>Greater Nairobi: Kiambu, Thika, Ruiru, Juja, Ngong, Rongai, Athi River, Kitengela, Machakos</td><td>1 to 2 working days</td></tr><tr><td>Major towns: Mombasa, Kisumu, Nakuru, Eldoret, Nyeri, Meru, Embu, Naivasha, Kericho</td><td>2 to 3 working days</td></tr><tr><td>Regional towns: Kakamega, Kisii, Bungoma, Homa Bay, Migori, Kitale, Malindi, Voi, Nanyuki, Makueni</td><td>3 to 4 working days</td></tr><tr><td>Outlying areas: Garissa, Wajir, Mandera, Marsabit, Lodwar, Isiolo, Lamu, Moyale</td><td>4 to 7 working days</td></tr></tbody></table><p>Most deliveries arrive at the earlier end of these ranges. Where a road, a courier backlog or the weather is going to make your order late, we call you rather than leave you waiting.</p><h2>Delivery charges</h2><p>We charge a <strong>flat delivery fee of KSh 500</strong> to every destination in Kenya. The fee is the same whether you are in Nairobi or in Mandera, so there is nothing to work out by zone. It is calculated and shown at checkout before you pay.</p><table class='rk-policy-table'><thead><tr><th>Order type</th><th>Delivery fee</th></tr></thead><tbody><tr><td>Standard orders, anywhere in Kenya</td><td>KSh 500 flat</td></tr><tr><td>Bulk orders and heavy machinery</td><td>Agreed with you beforehand</td></tr><tr><td>Collection from our shop</td><td>Free</td></tr></tbody></table><p>Where a promotion includes free delivery, we say so at the time.</p><h2>Bulk orders and heavy machinery</h2><p>Large generators, compressors, welding plant, solar installations and bulk quantities need a pickup, a lorry or a freight service rather than a motorbike, so the flat rate does not apply to them.</p><p>For these orders we agree the arrangement and the cost with you directly by <strong>call or WhatsApp on {$phone}</strong> before anything is dispatched. Nothing is charged until you have agreed the figure, and delivery of this kind can add a day to the times above. If you are ordering in bulk, talk to us first and we will quote you properly.</p><h2>Collection from our shop</h2><p>You are welcome to collect your order in person from <strong>{$addr}</strong>, open {$hours}. Collection is free. Please wait for our call or message confirming the order is ready before you travel.</p><h2>Tracking your order</h2><p>We send you a confirmation when your order is dispatched, along with the courier details or the rider's number where one applies. You can check on an order at any time by calling or sending a WhatsApp message to {$phone} with your order number.</p><h2>If delivery fails</h2><p>Our rider or courier will attempt delivery and call you on the number you gave us. If nobody is reachable, we hold the order and try again the next working day. After two failed attempts we hold the item at our shop for collection and get in touch to agree what happens next. Repeated failed deliveries caused by a wrong address or an unreachable phone number may mean the delivery fee is charged again.</p><h2>Where we deliver</h2><p>We deliver to all 47 counties of Kenya. We do not currently ship outside Kenya.</p><div class='rk-policy-contact'><p><strong>Questions about a delivery?</strong> Call or WhatsApp {$phone}, or email {$mail} with your order number.</p><p>{$name}, {$addr}. Open {$hours}.</p></div></div>",
            ),
            'return-refund-policy' => array(
                'title'   => 'Return &amp; Refund Policy',
                'content' => "<div class='rk-policy'><p class='rk-policy-updated'>Last updated: 17 September 2026</p><p class='rk-policy-lead'>If something is not right with your order, we will put it right. This policy applies to every customer, needs no account or login to read, and sits alongside your rights under the Consumer Protection Act, 2012 and the Sale of Goods Act.</p><div class='rk-policy-summary'><h2>At a glance</h2><ul><li><strong>Return window</strong><span>7 days from delivery</span></li><li><strong>Change of mind</strong><span>Accepted</span></li><li><strong>Restocking fee</strong><span>None</span></li><li><strong>Refund issued in</strong><span>3 to 7 working days</span></li><li><strong>Faulty item returns</strong><span>We pay the cost</span></li><li><strong>Exchanges</strong><span>Available</span></li></ul></div><h2>Your return window</h2><p>You may return most items within <strong>7 days of delivery or collection</strong>. This covers both faulty goods and <strong>change of mind</strong>: you do not have to prove anything is wrong with an item to return it, provided it meets the condition below.</p><h2>Condition of returned items</h2><p>The item should be unused and in its original packaging, with all accessories, manuals, warranty cards and any free gifts included. Items returned incomplete or with missing accessories may be refused, or may have the value of the missing parts deducted from the refund.</p><h2>What we cannot accept</h2><p>We cannot take back items that have been used, fitted, installed or altered, unless they are faulty. Consumables such as blades, drill bits, grinding discs and lubricants cannot be returned once opened, for hygiene and safety reasons. Items clearly marked non-returnable at the time of sale, and goods damaged through misuse, accident, incorrect voltage or by ignoring the manufacturer's instructions, are also excluded.</p><h2>How to start a return</h2><p>Call or WhatsApp <strong>{$phone}</strong>, or email <strong>{$mail}</strong>, within the 7-day window. Give us your order number and, where the item is faulty, a photograph or short video of the problem. We will confirm whether the return qualifies and tell you which method applies before you send anything back.</p><h2>Return method and who pays</h2><p>You can return an item in one of three ways: bring it to our shop at {$addr}, send it back through a courier of your choice, or ask us to arrange collection.</p><table class='rk-policy-table'><thead><tr><th>Reason for return</th><th>Who pays the return cost</th></tr></thead><tbody><tr><td>Faulty, damaged in transit, not as described, or wrong item sent</td><td>We do</td></tr><tr><td>You changed your mind</td><td>You do</td></tr><tr><td>Restocking or handling fee</td><td>Never charged</td></tr></tbody></table><p>Where the item is faulty, damaged, not as described or simply the wrong product, we also arrange collection at our expense. On change-of-mind returns the original delivery fee is not refunded.</p><h2>Refunds and how long they take</h2><p>Once the item reaches us we inspect it within <strong>2 working days</strong>. When the return is approved we issue the refund to your original payment method within <strong>3 to 7 working days</strong>.</p><table class='rk-policy-table'><thead><tr><th>Refund method</th><th>Time to arrive</th></tr></thead><tbody><tr><td>M-PESA</td><td>1 to 3 working days</td></tr><tr><td>Card refund</td><td>5 to 7 working days</td></tr><tr><td>Bank transfer</td><td>3 to 7 working days</td></tr></tbody></table><p>We email or message you at each step, and we do not deduct any fee from an approved refund.</p><h2>Exchanges</h2><p>If you would rather exchange an item than take a refund, say so when you start the return. Exchanges follow the same 7-day window and the same condition requirements. Where the replacement costs more, you pay the difference; where it costs less, we refund the difference by your original payment method.</p><h2>Faulty goods and warranty</h2><p>A fault that appears after the 7-day return window may still be covered by the manufacturer's warranty, which on most power tools runs from six to twelve months and on many generators and solar products twelve months or more. See our Warranty Policy for how to make a claim. Your statutory rights in respect of faulty goods are not limited by this policy or by any warranty period.</p><h2>Orders cancelled before dispatch</h2><p>If you cancel before your order has been dispatched, we refund the full amount including any delivery fee, with no deduction.</p><h2>If you are not satisfied</h2><p>If you are unhappy with how a return has been handled, ask for the matter to be escalated to our shop manager on {$phone}. We aim to resolve every complaint within 5 working days.</p><div class='rk-policy-contact'><p><strong>Need to start a return?</strong> Call or WhatsApp {$phone}, or email {$mail} with your order number.</p><p>{$name}, {$addr}. Open {$hours}.</p></div></div>",
            ),
            'warranty-policy' => array(
                'title'   => 'Warranty Policy',
                'content' => "<p><em>Last updated: 1 September 2026</em></p><p>The tools and equipment we sell carry the manufacturer's warranty. This page explains what that covers and how to make a claim.</p><h2>Warranty period</h2><p>How long the cover lasts depends on the brand and the type of product. As a guide, most power tools carry six to twelve months, and many generators and solar products carry twelve months or more. The exact period is shown on the product page or in the papers that come with the item.</p><h2>What is covered</h2><p>The warranty covers faults in materials or workmanship under normal use. If a covered item fails, it will be repaired or replaced; where neither is possible, a refund is arranged in line with the manufacturer's terms.</p><h2>What is not covered</h2><p>Normal wear and tear and consumable parts are not covered, and neither is damage caused by misuse, overloading, dropping, the wrong power supply, water, unauthorised repairs or not following the manufacturer's instructions.</p><h2>How to make a claim</h2><p>Contact us on {$phone} or {$mail} with your order number, your receipt and a short description of the fault. Keep the original box and accessories where you can. We will guide you through the claim and, where needed, book the item in with the manufacturer's service centre.</p><h2>Proof of purchase</h2><p>You will need your receipt or order confirmation for any warranty claim, so please keep it safe.</p><p>{$name}, {$addr}. {$phone} / {$mail}.</p>",
            ),
            'payment-methods' => array(
                'title'   => 'Payment Methods',
                'content' => "<p>We keep paying simple and secure. Choose whichever option suits you when you check out or when you order by phone.</p><h2>M-PESA</h2><p>Pay by M-PESA using the till or paybill details shown at checkout, or send payment directly when you order by phone or WhatsApp. Keep the M-PESA confirmation message as your proof of payment.</p><h2>Visa and Mastercard</h2><p>We accept Visa and Mastercard debit and credit cards. Card payments are handled by our licensed processor over an encrypted connection, so we never see or store your full card number.</p><h2>Cash on delivery</h2><p>Cash on delivery is available for selected locations and order values. Our team will confirm whether it applies to your order before we dispatch it.</p><h2>Prices and currency</h2><p>All prices are in Kenya Shillings (KSh) and are the same on the product page, in your cart and at checkout. Delivery is a flat KSh 500 to any destination in Kenya and is shown at checkout before you pay, so there are no surprises at the end. Bulk orders and heavy machinery are quoted separately and agreed with you first.</p><p>If you have a question about payment, call or WhatsApp {$phone}.</p>",
            ),
            'cookie-policy' => array(
                'title'   => 'Cookie Policy',
                'content' => "<p><em>Last updated: 1 September 2026</em></p><p>This website uses cookies. Cookies are small files saved on your phone or computer that help the site work and remember what you do.</p><h2>Why we use them</h2><p>Some cookies are essential: they keep your shopping cart and checkout working as you move around the site. Others remember your preferences, such as items you have looked at, and some help us see how the site is used so we can improve it.</p><h2>Managing cookies</h2><p>You can delete or block cookies in your browser settings. Do note that if you turn off the essential ones, parts of the site such as the cart and checkout may stop working properly.</p><p>Questions about cookies: {$mail}.</p>",
            ),
            'faq' => array(
                'title'   => 'Frequently Asked Questions',
                'content' => "<h2>Ordering</h2><p><strong>How do I place an order?</strong><br>Add what you want to the cart and check out, or simply call or WhatsApp us on {$phone} and we will place it for you.</p><p><strong>Are your products genuine?</strong><br>Yes. We buy only from authorised distributors, and our products come with the manufacturer's warranty.</p><h2>Payment</h2><p><strong>How can I pay?</strong><br>By M-PESA, Visa or Mastercard, or cash on delivery where it is available. See the Payment Methods page for more.</p><h2>Delivery</h2><p><strong>Do you deliver countrywide?</strong><br>Yes, to all 47 counties. Nairobi orders often arrive the same or next working day, and upcountry orders take two to seven working days depending on your location. The full table is on the Shipping &amp; Delivery Policy page.</p><p><strong>How much is delivery?</strong><br>A flat <strong>KSh 500</strong> to any destination in Kenya, shown at checkout before you pay. Collection from our shop is free. Bulk orders and heavy machinery are arranged separately by call or WhatsApp on {$phone}, and we agree the cost with you before dispatch.</p><p><strong>Can I collect my order myself?</strong><br>Yes, from our shop at {$addr} once we confirm it is ready.</p><h2>Returns and warranty</h2><p><strong>What if my item is faulty or wrong?</strong><br>Get in touch within 7 days and we will arrange a return, or help you with a warranty claim. See the Return &amp; Refund Policy and Warranty Policy.</p><h2>Talk to us</h2><p><strong>How do I reach you?</strong><br>Call or WhatsApp {$phone}, email {$mail}, or visit the shop Monday to Saturday, 9:00am to 5:00pm.</p>",
            ),
            'track-order' => array(
                'title'   => 'Track Order',
                'content' => "<div class='rk-policy'><p class='rk-policy-lead'>We confirm every order by message when it is placed, and again the moment it is dispatched. You can also check on it yourself at any time using either method below.</p><h2>Track with your order number</h2><p>Enter your order number and the email address you used at checkout. Your order number is in the confirmation message we sent you.</p>[woocommerce_order_tracking]<h2>Or just ask us</h2><p>Call or WhatsApp <strong>{$phone}</strong>, or email <strong>{$mail}</strong>, with your order number. We will tell you exactly where your order is and when to expect it. We answer most calls and messages the same day during opening hours, {$hours}.</p><h2>What each update means</h2><table class='rk-policy-table'><thead><tr><th>Status</th><th>What is happening</th></tr></thead><tbody><tr><td>Processing</td><td>Payment received, order being picked and packed</td></tr><tr><td>Dispatched</td><td>Handed to our rider or courier, delivery time starts here</td></tr><tr><td>Out for delivery</td><td>With the rider today, expect a call</td></tr><tr><td>Delivered</td><td>Received and signed for</td></tr></tbody></table><p>Delivery times by destination are set out in full on our Shipping &amp; Delivery Policy page. Times are counted in working days from dispatch, not from when the order was placed.</p><div class='rk-policy-contact'><p><strong>Cannot find your order number?</strong> Call or WhatsApp {$phone} with the name and phone number used at checkout and we will look it up.</p><p>{$name}, {$addr}. Open {$hours}.</p></div></div>",
            ),
        );
    }

    /**
     * Refresh the auto-generated info / legal page content once per content
     * version so existing sites pick up rewritten copy without duplicating
     * pages or clobbering later manual edits. Idempotent (own flag).
     */
    public function refresh_pages_content(): void {
        if ( get_option( 'topnotch_pages_content_v7' ) ) {
            return;
        }
        if ( function_exists( 'current_user_can' ) === false || current_user_can( 'edit_theme_options' ) === false ) {
            return;
        }
        try {
            foreach ( $this->pages() as $slug => $page ) {
                $existing = get_page_by_path( $slug );
                if ( $existing instanceof \WP_Post === false ) {
                    continue;
                }
                wp_update_post(
                    array(
                        'ID'           => (int) $existing->ID,
                        'post_content' => $page['content'],
                    )
                );
            }
            update_option( 'topnotch_pages_content_v7', time() );
        } catch ( \Throwable $e ) {
            error_log( 'Topnotch Mall pages content refresh failed: ' . $e->getMessage() );
        }
    }

	/**
	 * Move the saved brand colours from the old blue palette to the green one.
	 * The Customizer writes these as theme mods, and a saved mod overrides the
	 * stylesheet, so without this an existing site would keep rendering blue.
	 * Only rewrites a value that still equals an old default, so a colour the
	 * owner picked themselves is left alone. Idempotent (own flag).
	 */
	public function refresh_brand_colors(): void {
		if ( get_option( 'topnotch_brand_colors_v2' ) ) {
			return;
		}
		if ( function_exists( 'current_user_can' ) === false || current_user_can( 'edit_theme_options' ) === false ) {
			return;
		}
		try {
			$map = array(
				'topnotch_primary' => array(
					'new' => '#0C7A3B',
					'old' => array( '#005EB8', '#005eb8', '#FDB913', '#fdb913', '#0F8A44', '#0f8a44' ),
				),
				'topnotch_accent'  => array(
					'new' => '#22C55E',
					'old' => array( '#E8A317', '#e8a317', '#10B5A8', '#10b5a8' ),
				),
				'topnotch_navy'    => array(
					'new' => '#0B2A1D',
					'old' => array( '#0B1E3F', '#0b1e3f' ),
				),
			);
			foreach ( $map as $key => $spec ) {
				$current = (string) get_theme_mod( $key, '' );
				if ( '' === $current || in_array( $current, $spec['old'], true ) ) {
					set_theme_mod( $key, $spec['new'] );
				}
			}
			update_option( 'topnotch_brand_colors_v2', time() );
		} catch ( \Throwable $e ) {
			error_log( 'Topnotch Mall brand colour migration failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Delete every item in a menu, reading the list straight from the term
	 * relationships instead of wp_get_nav_menu_items(), whose cached result
	 * can be stale within a request and leave duplicates behind on rebuild.
	 *
	 * @param int $menu_id Menu term ID.
	 */
	private function clear_menu_items( int $menu_id ): void {
		$item_ids = get_objects_in_term( $menu_id, 'nav_menu' );
		if ( is_wp_error( $item_ids ) || is_array( $item_ids ) === false ) {
			return;
		}
		foreach ( $item_ids as $item_id ) {
			$item = get_post( (int) $item_id );
			if ( $item instanceof \WP_Post && 'nav_menu_item' === $item->post_type ) {
				wp_delete_post( (int) $item_id, true );
			}
		}
		wp_cache_delete( $menu_id, 'nav_menu_items' );
	}

	/**
	 * One-time repair for menus that already contain duplicates (the repeated
	 * "Payment Methods / Contact Us" in the top menu and the repeated policy
	 * links in the footer). Keeps the first occurrence of each linked page or
	 * URL in every menu this theme manages and deletes the rest. Idempotent.
	 */
	public function dedupe_menus(): void {
		if ( get_option( 'topnotch_menu_dedupe_v1' ) ) {
			return;
		}
		if ( function_exists( 'current_user_can' ) === false || current_user_can( 'edit_theme_options' ) === false ) {
			return;
		}
		try {
			$locations = function_exists( 'get_nav_menu_locations' ) ? get_nav_menu_locations() : array();
			$menu_ids  = array();
			foreach ( array( 'primary', 'vertical_cats', 'footer_company', 'footer_service', 'footer_policies' ) as $location ) {
				if ( empty( $locations[ $location ] ) ) {
					continue;
				}
				$menu_ids[ (int) $locations[ $location ] ] = true;
			}
			foreach ( array_keys( $menu_ids ) as $menu_id ) {
				$items = wp_get_nav_menu_items( (int) $menu_id, array( 'post_status' => 'any' ) );
				if ( is_array( $items ) === false ) {
					continue;
				}
				$seen = array();
				foreach ( $items as $item ) {
					$key = ( 'post_type' === $item->type )
						? 'page:' . (int) $item->object_id
						: 'url:' . untrailingslashit( strtolower( (string) $item->url ) );
					if ( isset( $seen[ $key ] ) ) {
						wp_delete_post( (int) $item->ID, true );
						continue;
					}
					$seen[ $key ] = true;
				}
				wp_cache_delete( (int) $menu_id, 'nav_menu_items' );
			}
			update_option( 'topnotch_menu_dedupe_v1', time() );
		} catch ( \Throwable $e ) {
			error_log( 'Topnotch Mall menu dedupe failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Populate product category thumbnails from images bundled with the theme.
	 * Runs once (own flag). For each category with no saved thumbnail it looks
	 * for assets/img/categories/{slug}.jpg (matched on the term slug or the
	 * sanitized term name), sideloads it into the media library and stores the
	 * attachment id as that category thumbnail.
	 */
	public function sync_category_images(): void {
		if ( get_option( self::CAT_IMG_FLAG ) ) {
			return;
		}
		if ( function_exists( 'current_user_can' ) === false || current_user_can( 'edit_theme_options' ) === false ) {
			return;
		}
		if ( taxonomy_exists( 'product_cat' ) === false ) {
			return; // WooCommerce not ready yet; retry on a later admin load.
		}
		$dir = trailingslashit( get_template_directory() ) . 'assets/img/categories/';
		if ( is_dir( $dir ) === false ) {
			update_option( self::CAT_IMG_FLAG, time() );
			return;
		}
		try {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
				)
			);
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				return; // No terms yet; retry later without setting the flag.
			}
			// Categories whose bundled artwork was redrawn for the Aurora UI. For
			// these we replace the existing thumbnail; every other category keeps
			// the fill-only-if-empty behaviour so nothing chosen by hand is lost.
			$refreshed = array(
				'hardware-tools', 'water-pumps', 'drills', 'batteries', 'solar-panels',
				'welding-machines', 'generators', 'saws', 'grinders', 'solar-inverters',
			);

			foreach ( $terms as $term ) {
				$has_thumb = (int) get_term_meta( $term->term_id, 'thumbnail_id', true ) > 0;
				$slug_keys = array_unique( array( $term->slug, sanitize_title( $term->name ) ) );
				$replace   = count( array_intersect( $slug_keys, $refreshed ) ) > 0;
				if ( $has_thumb && $replace === false ) {
					continue;
				}
				$file = '';
				$keys = array_unique( array( $term->slug, sanitize_title( $term->name ) ) );
				foreach ( $keys as $key ) {
					$candidate = $dir . $key . '.jpg';
					if ( file_exists( $candidate ) ) {
						$file = $candidate;
						break;
					}
				}
				if ( '' === $file ) {
					continue;
				}
				$attach_id = $this->sideload_category_image( $file, $term->name );
				if ( $attach_id > 0 ) {
					update_term_meta( $term->term_id, 'thumbnail_id', $attach_id );
				}
			}
			update_option( self::CAT_IMG_FLAG, time() );
		} catch ( \Throwable $e ) {
			error_log( 'Topnotch Mall category image sync failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Copy a bundled image into the media library and return its attachment id.
	 */
	private function sideload_category_image( string $path, string $title ): int {
		$tmp = wp_tempnam( basename( $path ) );
		if ( empty( $tmp ) ) {
			return 0;
		}
		if ( @copy( $path, $tmp ) === false ) {
			@unlink( $tmp );
			return 0;
		}
		$file_array = array(
			'name'     => sanitize_file_name( basename( $path ) ),
			'tmp_name' => $tmp,
		);
		$id = media_handle_sideload( $file_array, 0, $title );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			return 0;
		}
		return (int) $id;
	}

	/**
	 * One-time migration: refresh contact details (phone, WhatsApp, address)
	 * on existing installs after a details change. Updates a saved Customizer
	 * value only when it still equals the previous default, and rewrites the
	 * matching strings inside the auto-generated info / legal pages. Idempotent.
	 */
	public function refresh_contact_details(): void {
		if ( get_option( 'topnotch_contact_refresh_v1' ) ) {
			return;
		}
		if ( function_exists( 'current_user_can' ) === false || current_user_can( 'edit_theme_options' ) === false ) {
			return;
		}
		try {
			$mods = array(
				'topnotch_phone'    => array( '0797 720290', '+254 708 777192' ),
				'topnotch_email'    => array( 'info@toptechmachinery.co.ke', 'info@topnotchmall.co.ke' ),
				'topnotch_hours'    => array( 'Mon-Sat 8:00am - 6:00pm', 'Mon - Sat, 9AM - 5PM' ),
				'topnotch_whatsapp' => array( '254797720290', '254708777192' ),
				'topnotch_address'  => array( 'This & That Exhibition, Opp. Ronald Ngala Post Office, Shop G15, Ronald Ngala Street, Nairobi, Kenya', 'Magomano House, Tom Mboya Street, Nairobi, Kenya' ),
			);
			foreach ( $mods as $key => $pair ) {
				if ( get_theme_mod( $key ) === $pair[0] ) {
					set_theme_mod( $key, $pair[1] );
				}
			}
			$repl = array(
				'This & That Exhibition, Opp. Ronald Ngala Post Office, Shop G15, Ronald Ngala Street, Nairobi, Kenya' => 'Magomano House, Tom Mboya Street, Nairobi, Kenya',
				'This & That Exhibition, Opp. Ronald Ngala Post Office, Shop G15, Ronald Ngala Street' => 'Magomano House, Tom Mboya Street',
				'Royal Palms Mall, Shop No. BG 55, Nairobi, Kenya'  => 'Magomano House, Tom Mboya Street, Nairobi, Kenya',
				'TopTech Machinery'                                => 'Topnotch Mall',
				'info@toptechmachinery.co.ke'                      => 'info@topnotchmall.co.ke',
				'0797 720290'                                      => '+254 708 777192',
				'0719 261277'                                      => '+254 708 777192',
				'254797720290'                                     => '254708777192',
				'254719261277'                                     => '254708777192',
				'8:00am to 6:00pm'                                 => '9:00am to 5:00pm',
				'8:00am - 6:00pm'                                  => '9:00am - 5:00pm',
			);
			$slugs = array(
				'about-us', 'contact-us', 'payment-methods', 'return-refund-policy',
				'shipping-delivery-policy', 'track-order', 'faq', 'privacy-policy',
				'terms-conditions', 'warranty-policy', 'cookie-policy', 'home',
			);
			foreach ( $slugs as $slug ) {
				$page = get_page_by_path( $slug );
				if ( $page instanceof \WP_Post === false ) {
					continue;
				}
				$content = (string) $page->post_content;
				$updated = strtr( $content, $repl );
				if ( $updated === $content ) {
					continue;
				}
				wp_update_post( array( 'ID' => (int) $page->ID, 'post_content' => $updated ) );
			}
			update_option( 'topnotch_contact_refresh_v1', time() );
		} catch ( \Throwable $e ) {
			error_log( 'Topnotch Mall contact refresh failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Seed the contact/support theme mods with the real business details on
	 * first run so the site (and the merchant inspector) always has a phone and
	 * email, even before anyone opens the Customizer. Only fills empty values.
	 */
	public function seed_contact_defaults(): void {
		if ( get_option( 'topnotch_contact_seed_v1' ) ) {
			return;
		}
		if ( function_exists( 'current_user_can' ) === false || current_user_can( 'edit_theme_options' ) === false ) {
			return;
		}
		try {
			$defaults = array(
				'topnotch_phone'    => '+254 708 777192',
				'topnotch_email'    => 'info@topnotchmall.co.ke',
				'topnotch_hours'    => 'Mon - Sat, 9AM - 5PM',
				'topnotch_address'  => 'Magomano House, Tom Mboya Street, Nairobi, Kenya',
				'topnotch_whatsapp' => '254708777192',
			);
			foreach ( $defaults as $key => $val ) {
				if ( trim( (string) get_theme_mod( $key, '' ) ) === '' ) {
					set_theme_mod( $key, $val );
				}
			}
			update_option( 'topnotch_contact_seed_v1', time() );
		} catch ( \Throwable $e ) {
			error_log( 'Topnotch Mall contact seed failed: ' . $e->getMessage() );
		}
	}

	/**
	 * One-time cleanup: strip leftover competitor brand references from product
	 * content. Earlier product copy contained "Topnotch Mall" (and a
	 * truncated "from Ricky.") which was bulk-replaced in the database; this
	 * migration self-heals any residual mentions on deploy so no stray copy
	 * survives in titles, descriptions, short descriptions or stored SEO meta.
	 * Targets only posts that still reference the old name, is case-insensitive
	 * for the full phrase, and runs once (own flag). Idempotent.
	 */
	public function cleanup_competitor_brand(): void {
		if ( get_option( 'topnotch_brand_cleanup_v1' ) ) {
			return;
		}
		if ( function_exists( 'current_user_can' ) === false || current_user_can( 'edit_theme_options' ) === false ) {
			return;
		}
		try {
			global $wpdb;
			if ( is_object( $wpdb ) === false ) {
				return;
			}
			$clean = static function ( string $value ): string {
				$value = str_ireplace( 'Topnotch Mall', 'Topnotch Mall', $value );
				$value = strtr( $value, array( 'from Ricky.' => 'from Topnotch Mall.' ) );
				return $value;
			};
			$like = '%' . $wpdb->esc_like( 'Ricky' ) . '%';
			$ids  = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_status <> 'trash' AND ( p.post_title LIKE %s OR p.post_content LIKE %s OR p.post_excerpt LIKE %s OR pm.meta_value LIKE %s )",
					$like,
					$like,
					$like,
					$like
				)
			);
			foreach ( (array) $ids as $id ) {
				$post = get_post( (int) $id );
				if ( $post instanceof \WP_Post === false ) {
					continue;
				}
				$update  = array();
				$title   = $clean( (string) $post->post_title );
				$content = $clean( (string) $post->post_content );
				$excerpt = $clean( (string) $post->post_excerpt );
				if ( $title !== $post->post_title ) {
					$update['post_title'] = $title;
				}
				if ( $content !== $post->post_content ) {
					$update['post_content'] = $content;
				}
				if ( $excerpt !== $post->post_excerpt ) {
					$update['post_excerpt'] = $excerpt;
				}
				if ( count( $update ) > 0 ) {
					$update['ID'] = (int) $id;
					wp_update_post( $update );
				}
				$metas = get_post_meta( (int) $id );
				if ( is_array( $metas ) ) {
					foreach ( $metas as $meta_key => $meta_values ) {
						foreach ( (array) $meta_values as $meta_value ) {
							if ( is_string( $meta_value ) === false || is_serialized( $meta_value ) || stripos( $meta_value, 'Ricky' ) === false ) {
								continue;
							}
							$new_value = $clean( $meta_value );
							if ( $new_value !== $meta_value ) {
								update_post_meta( (int) $id, $meta_key, $new_value, $meta_value );
							}
						}
					}
				}
			}
			update_option( 'topnotch_brand_cleanup_v1', time() );
		} catch ( \Throwable $e ) {
			error_log( 'Topnotch Mall brand cleanup failed: ' . $e->getMessage() );
		}
	}

	/**
	 * One-time cleanup: reclassify mislabelled "DeWalt" welders. DeWalt does not
	 * manufacture arc / MMA stick inverter welders, so these listings read as
	 * counterfeit / brand misrepresentation to Google Merchant Center. This
	 * migration retitles the affected products, strips the DeWalt name from their
	 * copy and non-serialized stored meta, and reassigns their product_brand term
	 * to "Generic" (created if absent). Targets a fixed set of known welder IDs,
	 * only acts on ones whose title still claims DeWalt, and runs once (own flag).
	 * Idempotent.
	 */
	public function reclassify_dewalt_welders(): void {
		if ( get_option( 'topnotch_brand_reclass_v1' ) ) {
			return;
		}
		if ( function_exists( 'current_user_can' ) === false || current_user_can( 'edit_theme_options' ) === false ) {
			return;
		}
		try {
			$targets = array( 30874, 13315, 12924 );
			$clean   = static function ( string $value ): string {
				return str_ireplace( array( 'DeWalt', 'De Walt', 'De-Walt' ), 'Generic', $value );
			};
			$brand_term_id = 0;
			if ( taxonomy_exists( 'product_brand' ) ) {
				$term = get_term_by( 'slug', 'generic', 'product_brand' );
				if ( $term instanceof \WP_Term ) {
					$brand_term_id = (int) $term->term_id;
				} else {
					$inserted = wp_insert_term( 'Generic', 'product_brand', array( 'slug' => 'generic' ) );
					if ( is_array( $inserted ) && isset( $inserted['term_id'] ) ) {
						$brand_term_id = (int) $inserted['term_id'];
					}
				}
			}
			foreach ( $targets as $target_id ) {
				$post = get_post( (int) $target_id );
				if ( $post instanceof \WP_Post === false ) {
					continue;
				}
				if ( stripos( (string) $post->post_title, 'Walt' ) === false ) {
					continue; // Already reclassified or not the expected product.
				}
				$update  = array();
				$title   = $clean( (string) $post->post_title );
				$content = $clean( (string) $post->post_content );
				$excerpt = $clean( (string) $post->post_excerpt );
				if ( $title !== $post->post_title ) {
					$update['post_title'] = $title;
				}
				if ( $content !== $post->post_content ) {
					$update['post_content'] = $content;
				}
				if ( $excerpt !== $post->post_excerpt ) {
					$update['post_excerpt'] = $excerpt;
				}
				if ( count( $update ) > 0 ) {
					$update['ID'] = (int) $target_id;
					wp_update_post( $update );
				}
				$metas = get_post_meta( (int) $target_id );
				if ( is_array( $metas ) ) {
					foreach ( $metas as $meta_key => $meta_values ) {
						foreach ( (array) $meta_values as $meta_value ) {
							if ( is_string( $meta_value ) === false || is_serialized( $meta_value ) || stripos( $meta_value, 'Walt' ) === false ) {
								continue;
							}
							$new_value = $clean( $meta_value );
							if ( $new_value !== $meta_value ) {
								update_post_meta( (int) $target_id, $meta_key, $new_value, $meta_value );
							}
						}
					}
				}
				if ( $brand_term_id > 0 ) {
					wp_set_object_terms( (int) $target_id, array( $brand_term_id ), 'product_brand', false );
				}
			}
			update_option( 'topnotch_brand_reclass_v1', time() );
		} catch ( \Throwable $e ) {
			error_log( 'Topnotch Mall brand reclassification failed: ' . $e->getMessage() );
		}
	}
}
