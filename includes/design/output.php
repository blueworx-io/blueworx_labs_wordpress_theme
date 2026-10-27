<?php
/**
 * Site design system: putting it on the page.
 *
 * Two layers, deliberately separate:
 *
 * - The named styles and the chosen fonts, on every public page and in the
 *   block editor. They style nothing on their own, so they are always there
 *   for plugins to read. A plugin declares the blueworx-design style handle as
 *   a dependency to be sure they come first.
 * - The base site styles (assets/css/site.css), which style plain WordPress
 *   content from those names. Behind the site_styles switch, because turning
 *   them on restyles every page — which a site that already had the theme must
 *   choose, not get from an update.
 *
 * Never on admin screens: those keep the BlueWorx admin design system.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The CSS every page and the editor get: font faces, then the named styles.
 *
 * @return string CSS.
 */
function blueworx_design_page_css() {
	return blueworx_design_font_face_css() . blueworx_design_css();
}

/**
 * Adds the design system to public pages.
 *
 * @return void
 */
function blueworx_design_enqueue() {
	// No file: the handle exists to carry the inline block and to be depended on.
	wp_register_style( 'blueworx-design', false, array(), BLUEWORX_LABS_VERSION );
	wp_add_inline_style( 'blueworx-design', blueworx_design_page_css() );
	wp_enqueue_style( 'blueworx-design' );

	if ( blueworx_feature_enabled( 'site_styles' ) ) {
		wp_enqueue_style( 'blueworx-site', BLUEWORX_LABS_URL . 'assets/css/site.css', array( 'blueworx-design' ), BLUEWORX_LABS_VERSION );
	}
}
// Early, so a plugin enqueuing at the default priority finds the handle ready.
add_action( 'wp_enqueue_scripts', 'blueworx_design_enqueue', 5 );

/**
 * Gives the block editor the same named styles, fonts and base styles.
 *
 * Passed as editor settings rather than enqueued, so WordPress scopes them to
 * the content area — and into the editor's frame when it has one — instead of
 * restyling the editor's own controls.
 *
 * @param array $settings Block editor settings.
 * @return array
 */
function blueworx_design_editor_settings( $settings ) {
	$css = blueworx_design_page_css();

	if ( blueworx_feature_enabled( 'site_styles' ) ) {
		$css .= (string) file_get_contents( BLUEWORX_LABS_PATH . 'assets/css/site.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A file in the theme, not a remote URL.
	}

	$settings['styles']   = isset( $settings['styles'] ) && is_array( $settings['styles'] ) ? $settings['styles'] : array();
	$settings['styles'][] = array( 'css' => $css );

	return $settings;
}
add_filter( 'block_editor_settings_all', 'blueworx_design_editor_settings' );

/**
 * Offers the site's colours and type sizes in the block editor.
 *
 * So a colour or size picked for a block is one of the design system's, and
 * follows it when the design changes.
 *
 * @return void
 */
function blueworx_design_editor_supports() {
	$t = blueworx_design_tokens();

	$colors = array(
		'primary' => array( __( 'Main colour', 'blueworx-labs-wordpress' ), '--color-primary' ),
		'accent'  => array( __( 'Second colour', 'blueworx-labs-wordpress' ), '--color-accent' ),
		'text'    => array( __( 'Text', 'blueworx-labs-wordpress' ), '--color-text' ),
		'muted'   => array( __( 'Secondary text', 'blueworx-labs-wordpress' ), '--color-text-muted' ),
		'surface' => array( __( 'Panel', 'blueworx-labs-wordpress' ), '--color-surface' ),
		'bg'      => array( __( 'Background', 'blueworx-labs-wordpress' ), '--color-bg' ),
	);

	$palette = array();

	foreach ( $colors as $slug => $color ) {
		$palette[] = array(
			'name'  => $color[0],
			'slug'  => $slug,
			'color' => $t[ $color[1] ],
		);
	}

	$sizes = array(
		'small' => array( __( 'Small', 'blueworx-labs-wordpress' ), '--text-small' ),
		'body'  => array( __( 'Body', 'blueworx-labs-wordpress' ), '--text-body' ),
		'lead'  => array( __( 'Lead', 'blueworx-labs-wordpress' ), '--text-lead' ),
		'h3'    => array( __( 'Heading 3', 'blueworx-labs-wordpress' ), '--text-h3' ),
		'h2'    => array( __( 'Heading 2', 'blueworx-labs-wordpress' ), '--text-h2' ),
		'h1'    => array( __( 'Heading 1', 'blueworx-labs-wordpress' ), '--text-h1' ),
	);

	$font_sizes = array();

	foreach ( $sizes as $slug => $size ) {
		$font_sizes[] = array(
			'name' => $size[0],
			'slug' => $slug,
			'size' => (int) $t[ $size[1] ],
		);
	}

	add_theme_support( 'editor-color-palette', $palette );
	add_theme_support( 'editor-font-sizes', $font_sizes );
}
add_action( 'after_setup_theme', 'blueworx_design_editor_supports' );
