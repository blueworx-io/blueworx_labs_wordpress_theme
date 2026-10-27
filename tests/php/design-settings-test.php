<?php
/**
 * Site design system: what is stored, and what a plugin can add.
 *
 * Whatever reaches the option — from the Design screen, a preset or straight
 * into the database — has to come back as a complete, clean set of choices, so
 * nothing downstream ever meets a missing key or a value that is not one of the
 * choices on offer.
 *
 * Run with: php tests/php/design-settings-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';

define( 'BLUEWORX_LABS_URL', 'https://site.test/wp-content/themes/blueworx-labs-wordpress-theme/' );

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function absint( $value ) {
	return abs( (int) $value );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- The stub stands in for it.
}

function esc_url_raw( $url ) {
	return filter_var( $url, FILTER_SANITIZE_URL );
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/design/color.php';
require dirname( __DIR__, 2 ) . '/includes/design/settings.php';

check( 'fresh site gets defaults', blueworx_design_settings(), blueworx_design_defaults() );
check( 'default primary', blueworx_design_defaults()['primary'], '#2f5bea' );

$clean = blueworx_design_sanitize(
	array(
		'primary'      => 'red',
		'heading_font' => 'comic',
		'corners'      => 'wobbly',
		'logo_dark'    => '-7',
		'social'       => array(
			'facebook' => 'javascript:alert(1)',
			'myspace'  => 'https://x.test',
		),
	)
);
check( 'bad colour falls back', $clean['primary'], '#2f5bea' );
check( 'unknown font falls back', $clean['heading_font'], 'sora' );
check( 'unknown choice falls back', $clean['corners'], 'rounded' );
check( 'logo id is a whole number', $clean['logo_dark'], 7 );
check( 'bad and unknown social links dropped', $clean['social'], array() );
check( 'every key is present', array_keys( $clean ), array_keys( blueworx_design_defaults() ) );

check( 'empty accent is kept as automatic', blueworx_design_sanitize( array( 'accent' => '' ) )['accent'], '' );
check( 'a good link is kept', blueworx_design_sanitize( array( 'social' => array( 'instagram' => 'https://instagram.com/site' ) ) )['social'], array( 'instagram' => 'https://instagram.com/site' ) );
check( 'a non-array stored value is survived', blueworx_design_sanitize( array( 'social' => 'oops' ) )['social'], array() );

$GLOBALS['options']['blueworx_design'] = array( 'primary' => 'nonsense' );
check( 'a bad stored value is cleaned on the way out', blueworx_design_settings()['primary'], '#2f5bea' );

$saved = blueworx_design_save( array( 'primary' => '#0A7D5C' ) );
check( 'save returns what it stored', $saved['primary'], '#0a7d5c' );
check( 'save stores a clean copy', $GLOBALS['options']['blueworx_design']['primary'], '#0a7d5c' );

$fonts = blueworx_design_fonts();
check( 'Sora ships with the theme', $fonts['sora']['files'][700], BLUEWORX_LABS_URL . 'assets/fonts/sora-700.woff2' );
check( 'the system font has no files', $fonts['system']['files'], array() );

$GLOBALS['filters']['blueworx_design_fonts'] = function ( $fonts ) {
	$fonts['syne'] = array(
		'label'  => 'Syne',
		'family' => 'Syne',
		'stack'  => '"Syne", sans-serif',
		'files'  => array( 700 => 'https://club.test/syne.woff2' ),
	);
	return $fonts;
};
check( 'a plugin font can be chosen', blueworx_design_sanitize( array( 'heading_font' => 'syne' ) )['heading_font'], 'syne' );

$GLOBALS['filters']['blueworx_design_fonts'] = function ( $fonts ) {
	$fonts['broken']  = 'not a font';
	$fonts['nostack'] = array( 'label' => 'No stack' );
	return $fonts;
};
check( 'a malformed plugin font is left out', isset( blueworx_design_fonts()['broken'] ), false );
check( 'a plugin font with no stack is left out', isset( blueworx_design_fonts()['nostack'] ), false );
check( 'a malformed plugin font cannot be chosen', blueworx_design_sanitize( array( 'heading_font' => 'broken' ) )['heading_font'], 'sora' );
check( 'the built-in fonts survive a bad plugin', isset( blueworx_design_fonts()['inter'] ), true );
unset( $GLOBALS['filters']['blueworx_design_fonts'] );

check( 'no presets by default', blueworx_design_presets(), array() );

$GLOBALS['filters']['blueworx_design_presets'] = function () {
	return array(
		'court' => array(
			'label'       => 'Court',
			'description' => '',
			'settings'    => array(
				'primary' => '#c6f24e',
				'corners' => 'nonsense',
			),
		),
	);
};
$GLOBALS['options']['blueworx_design'] = array( 'logo_dark' => 12, 'social' => array( 'x' => 'https://x.com/site' ) );
check( 'preset applies', blueworx_design_apply_preset( 'court' ), true );
check( 'preset colour saved', blueworx_design_settings()['primary'], '#c6f24e' );
check( 'preset bad value cleaned', blueworx_design_settings()['corners'], 'rounded' );
check( 'preset untouched keys are defaults', blueworx_design_settings()['body_font'], 'inter' );
check( 'preset recorded', blueworx_design_settings()['preset'], 'court' );
check( 'preset keeps the logos', blueworx_design_settings()['logo_dark'], 12 );
check( 'preset keeps the social links', blueworx_design_settings()['social'], array( 'x' => 'https://x.com/site' ) );
check( 'unknown preset refused', blueworx_design_apply_preset( 'nope' ), false );
check( 'refused preset changes nothing', blueworx_design_settings()['primary'], '#c6f24e' );

check( 'admins set the design', blueworx_design_capability(), 'manage_options' );

finish();
