<?php
/**
 * Site design system: the reference page.
 *
 * One page showing the site's whole design system — colours, type, spacing,
 * buttons, blocks, logo and the named styles — laid out like the approved
 * mock-up. It is a public-side page rather than an admin screen on purpose:
 * it is drawn with the site's own styles, so every example on it is the site's
 * real look, not a picture of it.
 *
 * Only for people who can change the design. Anyone else asking for it gets
 * the site's normal page, and nothing on it says the reference exists.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The reference page's address.
 *
 * @return string URL.
 */
function blueworx_design_reference_url() {
	return home_url( '/?blueworx-design-system=1' );
}

/**
 * Whether this request asked for the reference page.
 *
 * @return bool
 */
function blueworx_design_reference_requested() {
	return isset( $_GET['blueworx-design-system'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks which page to draw; nothing changes.
}

/**
 * Whether the current visitor may see the reference page.
 *
 * @return bool
 */
function blueworx_design_reference_allowed() {
	return is_user_logged_in() && current_user_can( blueworx_design_capability() );
}

/**
 * The site's name as typed.
 *
 * WordPress keeps it HTML-escaped; decoded here so escaping it again on the
 * way out shows "&" rather than "&amp;".
 *
 * @return string
 */
function blueworx_design_reference_site_name() {
	return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
}

/**
 * How readable text is at a contrast ratio, in words.
 *
 * @param float $ratio WCAG contrast ratio.
 * @return string
 */
function blueworx_design_grade( $ratio ) {
	if ( $ratio >= 7 ) {
		return 'AAA';
	}

	if ( $ratio >= 4.5 ) {
		return 'AA';
	}

	return $ratio >= 3 ? __( 'Large text', 'blueworx-labs-wordpress' ) : __( 'Decoration', 'blueworx-labs-wordpress' );
}

/**
 * One colour swatch: the colour, its readability grade, name, value and use.
 *
 * @param array  $tokens  Named styles.
 * @param string $name    Named style.
 * @param string $label   What the team calls it.
 * @param string $purpose What it is for.
 * @return string HTML.
 */
function blueworx_design_reference_swatch( $tokens, $name, $label, $purpose ) {
	$hex   = $tokens[ $name ];
	$text  = blueworx_color_text_on( $hex, $tokens['--color-text'] );
	$ratio = blueworx_color_contrast( $text, $hex );

	return sprintf(
		'<div class="bw-ref-swatch" data-token="%1$s"><div class="bw-ref-chip" style="background:var(%1$s)"><span style="background:%2$s;color:%3$s" title="%4$s">%5$s</span></div><b>%6$s</b><span class="bw-ref-token">%1$s</span><span class="bw-ref-hex">%3$s</span><span class="bw-ref-use">%7$s</span></div>',
		esc_attr( $name ),
		esc_attr( $text ),
		esc_html( $hex ),
		/* translators: %s: contrast ratio, e.g. 4.8. */
		esc_attr( sprintf( __( 'Text contrast %s:1', 'blueworx-labs-wordpress' ), number_format( $ratio, 1 ) ) ),
		esc_html( blueworx_design_grade( $ratio ) ),
		esc_html( $label ),
		esc_html( $purpose )
	);
}

/**
 * The icons shown in the Icons section: Lucide's, as the mock-up lists them.
 *
 * @return array SVG inner markup keyed by name.
 */
function blueworx_design_reference_icons() {
	return array(
		'menu'     => '<path d="M4 12h16"/><path d="M4 6h16"/><path d="M4 18h16"/>',
		'search'   => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		'basket'   => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
		'account'  => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		'calendar' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
		'email'    => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
		'location' => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
		'arrow'    => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
		'expand'   => '<path d="m6 9 6 6 6-6"/>',
		'tick'     => '<path d="M20 6 9 17l-5-5"/>',
		'close'    => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
		'info'     => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
	);
}

/**
 * One icon as inline SVG.
 *
 * @param string $name Icon name from blueworx_design_reference_icons().
 * @param int    $size Pixel size.
 * @return string HTML.
 */
function blueworx_design_reference_icon( $name, $size = 20 ) {
	$icons = blueworx_design_reference_icons();

	return sprintf(
		'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%2$s</svg>',
		(int) $size,
		isset( $icons[ $name ] ) ? $icons[ $name ] : ''
	);
}

/**
 * The site's logo in one of its versions, or its first letter when none is set.
 *
 * @param string $which 'logo', 'dark' or 'mark'.
 * @return string HTML.
 */
function blueworx_design_reference_logo( $which ) {
	$settings = blueworx_design_settings();
	$ids      = array(
		'logo' => (int) get_theme_mod( 'custom_logo', 0 ),
		'dark' => $settings['logo_dark'],
		'mark' => $settings['logo_mark'],
	);
	$name     = blueworx_design_reference_site_name();
	$id       = isset( $ids[ $which ] ) ? $ids[ $which ] : 0;

	if ( $id && wp_get_attachment_image_url( $id, 'medium' ) ) {
		return wp_get_attachment_image( $id, 'medium', false, array( 'class' => 'bw-ref-logo-img' ) );
	}

	$letter = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : strtoupper( substr( $name, 0, 1 ) );
	$mark   = '<i class="bw-ref-mark">' . esc_html( $letter ) . '</i>';

	return 'mark' === $which ? $mark : '<span class="bw-ref-lockup">' . $mark . esc_html( $name ) . '</span>';
}

/**
 * Draws the reference page when it is asked for and allowed.
 *
 * @return void
 */
function blueworx_design_reference_render() {
	if ( ! blueworx_design_reference_requested() || ! blueworx_design_reference_allowed() ) {
		return;
	}

	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );

	add_filter(
		'body_class',
		static function ( $classes ) {
			$classes[] = 'bw-ref';
			return $classes;
		}
	);

	add_filter(
		'pre_get_document_title',
		static function () {
			/* translators: %s: site name. */
			return sprintf( __( '%s design system', 'blueworx-labs-wordpress' ), blueworx_design_reference_site_name() );
		}
	);

	// The site styles always, whatever the switch: this page shows the site
	// with its design system applied.
	add_action(
		'wp_enqueue_scripts',
		static function () {
			wp_enqueue_style( 'blueworx-site', BLUEWORX_LABS_URL . 'assets/css/site.css', array( 'blueworx-design' ), BLUEWORX_LABS_VERSION );
			wp_enqueue_style( 'blueworx-design-reference', BLUEWORX_LABS_URL . 'assets/css/design-reference.css', array( 'blueworx-site' ), BLUEWORX_LABS_VERSION );
			// The admin screens' copy button script: it only wires buttons to fields.
			wp_enqueue_script( 'blueworx-labs-wordpress-copy-field', BLUEWORX_LABS_URL . 'assets/js/copy-field.js', array(), BLUEWORX_LABS_VERSION, true );
		},
		20
	);

	require BLUEWORX_LABS_PATH . 'includes/design/reference-page.php';
	exit;
}
add_action( 'template_redirect', 'blueworx_design_reference_render' );
