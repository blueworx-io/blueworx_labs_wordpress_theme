<?php
/**
 * Site design system: colour maths.
 *
 * Turns the few colours the team picks into every shade the site needs, and
 * keeps text readable whatever was picked. No WordPress and no storage here,
 * only arithmetic, so tests/php/design-color-test.php can prove the promises.
 *
 * Ported from Clubhouse's colour engine, which has carried the same guarantees
 * across every club site. Readable means WCAG AA: 4.5:1.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tidies a colour into lowercase #rrggbb.
 *
 * @param string $hex A colour as #rgb, rgb, #rrggbb or rrggbb.
 * @return string The colour, or an empty string for anything that is not one.
 */
function blueworx_color_normalize( $hex ) {
	$hex = strtolower( ltrim( trim( (string) $hex ), '#' ) );

	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	return preg_match( '/^[0-9a-f]{6}$/', $hex ) ? '#' . $hex : '';
}

/**
 * Splits a colour into red, green and blue.
 *
 * @param string $hex A colour.
 * @return int[] Three numbers from 0 to 255. Black for anything that is not a colour.
 */
function blueworx_color_rgb( $hex ) {
	$hex = ltrim( blueworx_color_normalize( $hex ), '#' );

	if ( '' === $hex ) {
		$hex = '000000';
	}

	return array(
		(int) hexdec( substr( $hex, 0, 2 ) ),
		(int) hexdec( substr( $hex, 2, 2 ) ),
		(int) hexdec( substr( $hex, 4, 2 ) ),
	);
}

/**
 * Joins red, green and blue into a colour.
 *
 * @param int $r Red, 0 to 255.
 * @param int $g Green, 0 to 255.
 * @param int $b Blue, 0 to 255.
 * @return string #rrggbb.
 */
function blueworx_color_hex( $r, $g, $b ) {
	return sprintf(
		'#%02x%02x%02x',
		max( 0, min( 255, (int) $r ) ),
		max( 0, min( 255, (int) $g ) ),
		max( 0, min( 255, (int) $b ) )
	);
}

/**
 * How bright a colour looks, as WCAG measures it.
 *
 * @param string $hex A colour.
 * @return float 0 for black to 1 for white.
 */
function blueworx_color_luminance( $hex ) {
	$channel = static function ( $c ) {
		$s = $c / 255;
		return $s <= 0.03928 ? $s / 12.92 : ( ( $s + 0.055 ) / 1.055 ) ** 2.4;
	};

	list( $r, $g, $b ) = blueworx_color_rgb( $hex );

	return 0.2126 * $channel( $r ) + 0.7152 * $channel( $g ) + 0.0722 * $channel( $b );
}

/**
 * The WCAG contrast ratio between two colours.
 *
 * @param string $a A colour.
 * @param string $b Another colour.
 * @return float From 1 (no contrast) to 21 (black on white).
 */
function blueworx_color_contrast( $a, $b ) {
	$la = blueworx_color_luminance( $a );
	$lb = blueworx_color_luminance( $b );

	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

/**
 * Blends two colours.
 *
 * @param string $a        A colour.
 * @param string $b        Another colour.
 * @param float  $weight_a How much of $a, from 0 to 1.
 * @return string The blend.
 */
function blueworx_color_mix( $a, $b, $weight_a ) {
	$weight_a = max( 0.0, min( 1.0, (float) $weight_a ) );

	list( $ar, $ag, $ab ) = blueworx_color_rgb( $a );
	list( $br, $bg, $bb ) = blueworx_color_rgb( $b );

	return blueworx_color_hex(
		(int) round( $ar * $weight_a + $br * ( 1 - $weight_a ) ),
		(int) round( $ag * $weight_a + $bg * ( 1 - $weight_a ) ),
		(int) round( $ab * $weight_a + $bb * ( 1 - $weight_a ) )
	);
}

/**
 * Black or white, whichever stands out more against a background.
 *
 * Blending toward it is what "darker" means on a light site and "lighter" on a
 * dark one, so one rule works for both.
 *
 * @param string $bg The background.
 * @return string #000000 or #ffffff.
 */
function blueworx_color_pole( $bg ) {
	return blueworx_color_contrast( '#000000', $bg ) >= blueworx_color_contrast( '#ffffff', $bg ) ? '#000000' : '#ffffff';
}

/**
 * The colour nearest the one given that reads on a background.
 *
 * Steps toward black or white in twentieths and stops at the first step that
 * reads, so the brand colour is kept as far as readability allows. The last
 * step is pure black or white, one of which always reads, so this never fails.
 *
 * @param string $color The colour wanted.
 * @param string $bg    The background it sits on.
 * @param float  $min   The contrast it has to reach.
 * @return string A colour that reaches it.
 */
function blueworx_color_readable_on( $color, $bg, $min = 4.5 ) {
	$pole = blueworx_color_pole( $bg );

	for ( $i = 20; $i >= 0; $i-- ) {
		$candidate = blueworx_color_mix( $color, $pole, $i / 20 );

		if ( blueworx_color_contrast( $candidate, $bg ) >= $min ) {
			return $candidate;
		}
	}

	return $pole;
}

/**
 * The text colour for something filled with a colour.
 *
 * The site's own text colour when it reads, so text keeps the site's ink.
 * Otherwise white or black, whichever reads better — and one of the two always
 * reaches 4.5:1 on any colour, so this never returns unreadable text.
 *
 * @param string $fill The fill.
 * @param string $ink  The site's text colour.
 * @return string The ink, white or black.
 */
function blueworx_color_text_on( $fill, $ink ) {
	$ink = blueworx_color_normalize( $ink );

	if ( '' !== $ink && blueworx_color_contrast( $ink, $fill ) >= 4.5 ) {
		return $ink;
	}

	return blueworx_color_contrast( '#ffffff', $fill ) >= blueworx_color_contrast( '#000000', $fill ) ? '#ffffff' : '#000000';
}

/**
 * Ten shades of a colour, from palest to darkest, with the colour itself at 500.
 *
 * @param string $hex A colour.
 * @return string[] Keyed 50, 100, 200 … 900.
 */
function blueworx_color_scale( $hex ) {
	$hex   = blueworx_color_normalize( $hex );
	$scale = array();

	foreach ( array(
		50  => 0.08,
		100 => 0.17,
		200 => 0.34,
		300 => 0.55,
		400 => 0.78,
	) as $step => $weight ) {
		$scale[ $step ] = blueworx_color_mix( $hex, '#ffffff', $weight );
	}

	$scale[500] = $hex;

	foreach ( array(
		600 => 0.82,
		700 => 0.66,
		800 => 0.52,
		900 => 0.36,
	) as $step => $weight ) {
		$scale[ $step ] = blueworx_color_mix( $hex, '#000000', $weight );
	}

	return $scale;
}

/**
 * Splits a colour into hue, saturation and lightness.
 *
 * @param string $hex A colour.
 * @return float[] Hue 0–360, saturation and lightness 0–1.
 */
function blueworx_color_to_hsl( $hex ) {
	list( $r, $g, $b ) = array_map(
		static function ( $c ) {
			return $c / 255;
		},
		blueworx_color_rgb( $hex )
	);

	$max = max( $r, $g, $b );
	$min = min( $r, $g, $b );
	$l   = ( $max + $min ) / 2;
	$d   = $max - $min;

	// A grey has no hue; 0 is as good as any.
	if ( 0.0 === (float) $d ) {
		return array( 0.0, 0.0, $l );
	}

	$s = $d / ( 1 - abs( 2 * $l - 1 ) );

	if ( $max === $r ) {
		$h = fmod( ( $g - $b ) / $d, 6.0 );
	} elseif ( $max === $g ) {
		$h = ( $b - $r ) / $d + 2;
	} else {
		$h = ( $r - $g ) / $d + 4;
	}

	return array( fmod( $h * 60 + 360, 360 ), $s, $l );
}

/**
 * Joins hue, saturation and lightness into a colour.
 *
 * @param float $h Hue in degrees.
 * @param float $s Saturation, 0 to 1.
 * @param float $l Lightness, 0 to 1.
 * @return string #rrggbb.
 */
function blueworx_color_from_hsl( $h, $s, $l ) {
	$h = fmod( fmod( $h, 360 ) + 360, 360 );
	$s = max( 0.0, min( 1.0, $s ) );
	$l = max( 0.0, min( 1.0, $l ) );

	$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
	$x = $c * ( 1 - abs( fmod( $h / 60, 2.0 ) - 1 ) );
	$m = $l - $c / 2;

	if ( $h < 60 ) {
		list( $r, $g, $b ) = array( $c, $x, 0.0 );
	} elseif ( $h < 120 ) {
		list( $r, $g, $b ) = array( $x, $c, 0.0 );
	} elseif ( $h < 180 ) {
		list( $r, $g, $b ) = array( 0.0, $c, $x );
	} elseif ( $h < 240 ) {
		list( $r, $g, $b ) = array( 0.0, $x, $c );
	} elseif ( $h < 300 ) {
		list( $r, $g, $b ) = array( $x, 0.0, $c );
	} else {
		list( $r, $g, $b ) = array( $c, 0.0, $x );
	}

	return blueworx_color_hex(
		(int) round( ( $r + $m ) * 255 ),
		(int) round( ( $g + $m ) * 255 ),
		(int) round( ( $b + $m ) * 255 )
	);
}

/**
 * Whether a colour can carry text and be text.
 *
 * True when the better of ink or white reads on it as a fill, and the colour
 * itself, made readable, reads on the background.
 *
 * @param string $color A colour.
 * @param string $bg    The site's background.
 * @param string $ink   The site's text colour.
 * @return bool
 */
function blueworx_color_is_legible( $color, $bg, $ink ) {
	return blueworx_color_contrast( blueworx_color_text_on( $color, $ink ), $color ) >= 4.5
		&& blueworx_color_contrast( blueworx_color_readable_on( $color, $bg ), $bg ) >= 4.5;
}

/**
 * The second colour a site gets when it has not chosen one.
 *
 * The main colour turned 150° round the colour wheel at the same saturation:
 * clearly different, still in the same family, and never the 180° opposite,
 * which reliably clashes. Lightness is kept where text still reads on it, and
 * otherwise nudged the shortest way to where it does.
 *
 * @param string $primary The main colour.
 * @param string $bg      The site's background.
 * @param string $ink     The site's text colour.
 * @return string A colour.
 */
function blueworx_color_partner( $primary, $bg, $ink ) {
	list( $h, $s, $l ) = blueworx_color_to_hsl( $primary );
	$h                += 150;

	$candidate = blueworx_color_from_hsl( $h, $s, $l );

	if ( blueworx_color_is_legible( $candidate, $bg, $ink ) ) {
		return $candidate;
	}

	for ( $step = 0.02; $step <= 1.0; $step += 0.02 ) {
		foreach ( array( $l - $step, $l + $step ) as $try ) {
			if ( $try < 0.0 || $try > 1.0 ) {
				continue;
			}

			$candidate = blueworx_color_from_hsl( $h, $s, $try );

			if ( blueworx_color_is_legible( $candidate, $bg, $ink ) ) {
				return $candidate;
			}
		}
	}

	return blueworx_color_from_hsl( $h, $s, $l );
}
