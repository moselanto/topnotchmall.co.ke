<?php
/**
 * Theme Customizer: brand colours + contact/support details.
 *
 * @package TopnotchMall
 */

declare( strict_types = 1 );

namespace TopnotchMall;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Customizer settings and outputs brand CSS variables.
 */
final class Customizer {

	public function hooks(): void {
		add_action( 'customize_register', array( $this, 'register' ) );
		add_action( 'wp_head', array( $this, 'output_css_vars' ), 20 );
	}

	/**
	 * @param \WP_Customize_Manager $wp_customize Customizer manager.
	 */
	public function register( $wp_customize ): void {
		$wp_customize->add_panel( 'topnotch_panel', array( 'title' => __( 'Topnotch Mall', 'topnotch-mall' ), 'priority' => 20 ) );

		// Colours.
		$wp_customize->add_section( 'topnotch_colors', array( 'title' => __( 'Brand Colours', 'topnotch-mall' ), 'panel' => 'topnotch_panel' ) );
		$this->color( $wp_customize, 'topnotch_primary', '#0F8A44', __( 'Primary (Green)', 'topnotch-mall' ) );
		$this->color( $wp_customize, 'topnotch_accent', '#22C55E', __( 'Accent (Luminous Green)', 'topnotch-mall' ) );
		$this->color( $wp_customize, 'topnotch_navy', '#0B2A1D', __( 'Secondary (Dark Green)', 'topnotch-mall' ) );

		// Contact + support.
		$wp_customize->add_section( 'topnotch_contact', array( 'title' => __( 'Contact & Support', 'topnotch-mall' ), 'panel' => 'topnotch_panel' ) );
		$this->text( $wp_customize, 'topnotch_phone', '+254 708 777192', __( 'Phone / WhatsApp', 'topnotch-mall' ) );
		$this->text( $wp_customize, 'topnotch_email', 'info@topnotchmall.co.ke', __( 'Email', 'topnotch-mall' ) );
		$this->text( $wp_customize, 'topnotch_hours', 'Mon - Sat, 9AM - 5PM', __( 'Support Hours', 'topnotch-mall' ) );
		$this->text( $wp_customize, 'topnotch_address', 'Magomano House, Tom Mboya Street, Nairobi, Kenya', __( 'Business Address', 'topnotch-mall' ) );
		$this->text( $wp_customize, 'topnotch_whatsapp', '254708777192', __( 'WhatsApp number (intl, no +)', 'topnotch-mall' ) );
		$this->text( $wp_customize, 'topnotch_cutoff', '5:00pm', __( 'Same-day order cut-off time', 'topnotch-mall' ) );
	}

	private function color( $wp, string $id, string $default, string $label ): void {
		$wp->add_setting( $id, array( 'default' => $default, 'sanitize_callback' => 'sanitize_hex_color', 'transport' => 'postMessage' ) );
		$wp->add_control( new \WP_Customize_Color_Control( $wp, $id, array( 'label' => $label, 'section' => 'topnotch_colors' ) ) );
	}

	private function text( $wp, string $id, string $default, string $label ): void {
		$wp->add_setting( $id, array( 'default' => $default, 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp->add_control( $id, array( 'label' => $label, 'section' => 'topnotch_contact', 'type' => 'text' ) );
	}

	/**
	 * Print brand colours as CSS custom properties.
	 */
	public function output_css_vars(): void {
		$green  = sanitize_hex_color( (string) get_theme_mod( 'topnotch_primary', '#0C7A3B' ) );
		$accent = sanitize_hex_color( (string) get_theme_mod( 'topnotch_accent', '#22C55E' ) );
		$dark   = sanitize_hex_color( (string) get_theme_mod( 'topnotch_navy', '#0B2A1D' ) );
		$green  = $green ? $green : '#0C7A3B';
		$accent = $accent ? $accent : '#22C55E';
		$dark   = $dark ? $dark : '#0B2A1D';
		printf(
			'<style id="topnotch-brand">:root{--rk-primary:%1$s;--rk-primary-600:%2$s;--rk-navy:%3$s;--rk-accent:%4$s;--rk-accent-600:%5$s;--rk-accent-700:%6$s;--rk-accent-tint:%7$s}</style>' . "\n",
			esc_html( $green ),
			esc_html( $this->shade( $green, 0.80 ) ),
			esc_html( $dark ),
			esc_html( $accent ),
			esc_html( $this->shade( $accent, 0.86 ) ),
			esc_html( $this->shade( $accent, 0.56 ) ),
			esc_html( $this->tint( $accent, 0.14 ) )
		);
	}

	/**
	 * Darken (factor below 1) or lighten (above 1) a hex colour.
	 *
	 * @param string $hex    Source colour.
	 * @param float  $factor Multiplier applied to each channel.
	 */
	private function shade( string $hex, float $factor ): string {
		$rgb = $this->to_rgb( $hex );
		foreach ( $rgb as $i => $channel ) {
			$rgb[ $i ] = max( 0, min( 255, (int) round( $channel * $factor ) ) );
		}
		return sprintf( '#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2] );
	}

	/**
	 * Return a colour as a translucent rgba() string.
	 *
	 * @param string $hex   Source colour.
	 * @param float  $alpha Opacity between 0 and 1.
	 */
	private function tint( string $hex, float $alpha ): string {
		$rgb = $this->to_rgb( $hex );
		return sprintf( 'rgba(%d,%d,%d,%s)', $rgb[0], $rgb[1], $rgb[2], rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' ) );
	}

	/**
	 * Hex to array( r, g, b ). Falls back to black for an unusable value.
	 *
	 * @param string $hex Source colour.
	 * @return array<int,int>
	 */
	private function to_rgb( string $hex ): array {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 > strlen( $hex ) || 6 < strlen( $hex ) ) {
			return array( 0, 0, 0 );
		}
		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}
}
