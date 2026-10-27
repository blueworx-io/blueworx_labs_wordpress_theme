<?php
/**
 * Site design system: the colour maths.
 *
 * Every shade the site uses is worked out from a handful of chosen colours, so
 * the guarantees that matter are that nothing but a real colour gets through,
 * and that any colour pushed to "readable" really reads at 4.5:1, on a light
 * background and a dark one alike.
 *
 * Run with: php tests/php/design-color-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its working variables are never
// seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';
require dirname( __DIR__, 2 ) . '/includes/design/color.php';

check( 'normalises short hex', blueworx_color_normalize( '#ABC' ), '#aabbcc' );
check( 'adds the hash', blueworx_color_normalize( '2F5BEA' ), '#2f5bea' );
check( 'refuses a word', blueworx_color_normalize( 'red' ), '' );
check( 'refuses a short code', blueworx_color_normalize( '#12' ), '' );
check( 'refuses script', blueworx_color_normalize( 'javascript:alert(1)' ), '' );

check( 'black on white is 21', round( blueworx_color_contrast( '#000000', '#ffffff' ), 2 ), 21.0 );
check( 'contrast reads either way round', blueworx_color_contrast( '#2f5bea', '#ffffff' ), blueworx_color_contrast( '#ffffff', '#2f5bea' ) );
check( 'half and half', blueworx_color_mix( '#000000', '#ffffff', 0.5 ), '#808080' );

check( 'pole on white is black', blueworx_color_pole( '#ffffff' ), '#000000' );
check( 'pole on dark is white', blueworx_color_pole( '#111111' ), '#ffffff' );

check( 'readable colour is left alone', blueworx_color_readable_on( '#16181d', '#ffffff' ), '#16181d' );

foreach ( array( '#f5d90a', '#808080', '#2f5bea', '#c6f24e', '#00ffff' ) as $color ) {
	check( "$color made readable on white", blueworx_color_contrast( blueworx_color_readable_on( $color, '#ffffff' ), '#ffffff' ) >= 4.5, true );
	check( "$color made readable on dark", blueworx_color_contrast( blueworx_color_readable_on( $color, '#111111' ), '#111111' ) >= 4.5, true );
}

check( 'white text on brand blue', blueworx_color_text_on( '#2f5bea', '#16181d' ), '#ffffff' );
check( 'ink text on yellow', blueworx_color_text_on( '#f5d90a', '#16181d' ), '#16181d' );

$scale = blueworx_color_scale( '#2f5bea' );
check( 'scale has ten steps', array_keys( $scale ), array( 50, 100, 200, 300, 400, 500, 600, 700, 800, 900 ) );
check( 'scale 500 is the colour', $scale[500], '#2f5bea' );
check( 'scale gets darker', blueworx_color_contrast( $scale[900], '#ffffff' ) > blueworx_color_contrast( $scale[50], '#ffffff' ), true );

$partner = blueworx_color_partner( '#2f5bea', '#ffffff', '#16181d' );
check( 'partner differs from primary', '#2f5bea' !== $partner, true );
check( 'partner is a colour', blueworx_color_normalize( $partner ), $partner );

finish();
