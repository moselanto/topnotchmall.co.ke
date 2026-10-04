<?php
/**
 * Analytics: adds the GA4 Google tag to the gtag.js that Google for WooCommerce
 * already loads (no second gtag.js when one is present), and records clicks on
 * WhatsApp links as a GA4 event and, when a send_to label is set, a Google Ads
 * conversion ("Order on WhatsApp").
 *
 * Settings: Customize > Topnotch Mall > Contact & Support.
 *
 * @package TopnotchMall
 */

declare( strict_types = 1 );

namespace TopnotchMall;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end measurement glue.
 */
final class Analytics {

	public function hooks(): void {
		add_action( 'wp_footer', array( $this, 'output' ), 5 );
	}

	/**
	 * Print the GA4 config + WhatsApp click tracking.
	 */
	public function output(): void {
		if ( is_admin() ) {
			return;
		}
		$ga4 = strtoupper( trim( (string) get_theme_mod( 'topnotch_ga4_id', 'G-WD7ZVQZ0SD' ) ) );
		$wa  = trim( (string) get_theme_mod( 'topnotch_wa_conversion', '' ) );
		if ( '' !== $ga4 && ! preg_match( '/^G-[A-Z0-9]{4,16}$/', $ga4 ) ) {
			$ga4 = '';
		}
		if ( '' !== $wa && ! preg_match( '#^AW-[0-9]{6,14}/[A-Za-z0-9_-]{4,64}$#', $wa ) ) {
			$wa = '';
		}
		if ( '' === $ga4 && '' === $wa ) {
			return;
		}
		$cfg = wp_json_encode(
			array(
				'ga4' => $ga4,
				'wa'  => $wa,
			)
		);
		?>
<script id="topnotch-analytics">
(function(c){
	window.dataLayer = window.dataLayer || [];
	if (typeof window.gtag !== 'function') { window.gtag = function(){ window.dataLayer.push(arguments); }; }
	var hasLib = !!document.querySelector('script[src*="googletagmanager.com/gtag/js"]');
	if (c.ga4) {
		if (!hasLib) {
			var s = document.createElement('script');
			s.async = true;
			s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(c.ga4);
			document.head.appendChild(s);
			window.gtag('js', new Date());
		}
		window.gtag('config', c.ga4);
	}
	document.addEventListener('click', function(e){
		var a = e.target && e.target.closest ? e.target.closest('a[href*="wa.me"],a[href*="api.whatsapp.com"],a[href*="whatsapp://"]') : null;
		if (!a) { return; }
		if (c.ga4) {
			window.gtag('event', 'whatsapp_click', { send_to: c.ga4, link_url: a.href, transport_type: 'beacon' });
		}
		if (c.wa) {
			window.gtag('event', 'conversion', { send_to: c.wa, transport_type: 'beacon' });
		}
	}, true);
})(<?php echo $cfg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated IDs, JSON-encoded. ?>);
</script>
		<?php
	}
}
