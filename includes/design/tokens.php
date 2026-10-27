<?php
/**
 * Site design system: the named styles.
 *
 * Turns the site's choices into the CSS custom properties every public page,
 * the block editor and every plugin read — --color-primary, --font-heading,
 * --space-4 and the rest. The names are a contract: plugins are written
 * against them, so the list only ever grows. docs/design-system.md lists them.
 *
 * The values match the approved design system mock-up for the default look,
 * and every shade is worked out from the chosen colours so text stays readable
 * whatever was chosen. Anything that cannot be made readable is reported by
 * blueworx_design_readability() rather than quietly changed.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Type sizes in px: desktop, then phone (below 640px).
 *
 * @return array Pairs keyed by named style.
 */
function blueworx_design_type_sizes() {
	return array(
		'--text-display' => array( 64, 40 ),
		'--text-h1'      => array( 48, 34 ),
		'--text-h2'      => array( 36, 28 ),
		'--text-h3'      => array( 28, 24 ),
		'--text-h4'      => array( 22, 20 ),
		'--text-h5'      => array( 18, 17 ),
		'--text-h6'      => array( 14, 14 ),
		'--text-lead'    => array( 19, 17 ),
		'--text-body'    => array( 16, 16 ),
		'--text-small'   => array( 14, 14 ),
		'--text-caption' => array( 12, 12 ),
	);
}

/**
 * Whether the type size choice scales a named style.
 *
 * Only display to h5: body text, captions and the capitals of h6 stay put, so
 * "large" means bigger headings rather than a page that no longer fits.
 *
 * @param string $name Named style.
 * @return bool
 */
function blueworx_design_type_scales( $name ) {
	return in_array( $name, array( '--text-display', '--text-h1', '--text-h2', '--text-h3', '--text-h4', '--text-h5' ), true );
}

/**
 * Cleans a font stack or family name down to what a font-family value needs.
 *
 * Fonts can come from plugins, and these values are printed inside a style
 * tag, so anything that could end the rule or the tag is dropped.
 *
 * @param string $value A font-family value.
 * @return string
 */
function blueworx_design_clean_font_value( $value ) {
	$value = preg_replace( '/[^A-Za-z0-9 ,"\'_.-]/', '', (string) $value );

	// A quote left open would run on through every style printed after it.
	foreach ( array( '"', "'" ) as $quote ) {
		if ( 0 !== substr_count( $value, $quote ) % 2 ) {
			$value = str_replace( $quote, '', $value );
		}
	}

	return trim( $value );
}

/**
 * Every named style for a set of choices.
 *
 * @param array|null $settings Choices; null reads the site's.
 * @return array Values keyed by named style, in the documented order.
 */
function blueworx_design_tokens( $settings = null ) {
	$s       = null === $settings ? blueworx_design_settings() : blueworx_design_sanitize( $settings );
	$choices = blueworx_design_choices();
	$fonts   = blueworx_design_fonts();

	$bg      = $s['background'];
	$ink     = $s['text'];
	$primary = $s['primary'];
	$pole    = blueworx_color_pole( $bg );
	$accent  = '' !== $s['accent'] ? $s['accent'] : blueworx_color_partner( $primary, $bg, $ink );

	// Hover and pressed are the button's fill, under the same text as the main
	// colour. They move away from that text — darker under light text, lighter
	// under dark — so the text only ever gets easier to read as they deepen.
	$on_primary = blueworx_color_text_on( $primary, $ink );
	$away       = blueworx_color_luminance( $on_primary ) > blueworx_color_luminance( $primary ) ? '#000000' : '#ffffff';

	$t = array(
		'--color-primary'         => $primary,
		'--color-primary-hover'   => blueworx_color_mix( $primary, $away, 0.82 ),
		'--color-primary-pressed' => blueworx_color_mix( $primary, $away, 0.66 ),
		'--color-primary-tint'    => blueworx_color_mix( $primary, $bg, 0.08 ),
		'--color-on-primary'      => $on_primary,
		// The main colour as text: links, outline buttons.
		'--color-link'            => blueworx_color_readable_on( blueworx_color_mix( $primary, $pole, 0.82 ), $bg ),
	);

	foreach ( blueworx_color_scale( $primary ) as $step => $shade ) {
		$t[ '--color-primary-' . $step ] = $shade;
	}

	$t['--color-accent']      = $accent;
	$t['--color-accent-text'] = blueworx_color_readable_on( $accent, $bg );
	$t['--color-accent-tint'] = blueworx_color_mix( $accent, $bg, 0.12 );
	$t['--color-on-accent']   = blueworx_color_text_on( $accent, $ink );

	$t['--color-bg']            = $bg;
	$t['--color-surface']       = blueworx_color_mix( $ink, $bg, 0.035 );
	$t['--color-border']        = blueworx_color_mix( $ink, $bg, 0.15 );
	$t['--color-border-strong'] = blueworx_color_mix( $ink, $bg, 0.42 );
	$t['--color-placeholder']   = blueworx_color_readable_on( blueworx_color_mix( $ink, $bg, 0.62 ), $bg );
	$t['--color-text-muted']    = blueworx_color_readable_on( blueworx_color_mix( $ink, $bg, 0.75 ), $bg );
	$t['--color-text']          = $ink;
	$t['--color-dark-raised']   = blueworx_color_mix( $ink, $bg, 0.92 );

	// Made readable on their own tint, which is the harder of the two places
	// they sit: on a light site the tint is darker than the page, on a dark one
	// lighter, so readable there means readable on the page too.
	foreach ( array(
		'success' => '#1d7a46',
		'warning' => '#9a5108',
		'error'   => '#c42b2b',
		'info'    => '#0b6bcb',
	) as $status => $base ) {
		$tint                             = blueworx_color_mix( $base, $bg, 0.10 );
		$t[ '--color-' . $status ]        = blueworx_color_readable_on( $base, $tint );
		$t[ '--color-' . $status . '-tint' ] = $tint;
	}

	$t['--font-heading'] = blueworx_design_clean_font_value( $fonts[ $s['heading_font'] ]['stack'] );
	$t['--font-body']    = blueworx_design_clean_font_value( $fonts[ $s['body_font'] ]['stack'] );

	$type = $choices['type_scale'][ $s['type_scale'] ]['value'];

	foreach ( blueworx_design_type_sizes() as $name => $sizes ) {
		$t[ $name ] = (int) round( $sizes[0] * ( blueworx_design_type_scales( $name ) ? $type : 1 ) ) . 'px';
	}

	$space = $choices['spacing'][ $s['spacing'] ]['value'];

	foreach ( array( 1, 2, 3, 4, 6, 8, 12, 16, 24 ) as $step ) {
		$t[ '--space-' . $step ] = (int) round( $step * 4 * $space ) . 'px';
	}

	list( $sm, $md, $lg, $xl ) = $choices['corners'][ $s['corners'] ]['value'];

	$buttons = array(
		'square'  => 0,
		'rounded' => $md,
		'pill'    => 9999,
	);

	$t['--radius-sm']     = $sm . 'px';
	$t['--radius-md']     = $md . 'px';
	$t['--radius-lg']     = $lg . 'px';
	$t['--radius-xl']     = $xl . 'px';
	$t['--radius-full']   = '9999px';
	$t['--radius-button'] = $buttons[ $choices['buttons'][ $s['buttons'] ]['value'] ] . 'px';

	list( $r, $g, $b ) = blueworx_color_rgb( $primary );

	$t['--shadow-sm']  = '0 1px 2px rgba(17, 24, 39, .06), 0 1px 3px rgba(17, 24, 39, .08)';
	$t['--shadow-md']  = '0 4px 16px rgba(17, 31, 85, .10)';
	$t['--shadow-lg']  = '0 16px 40px rgba(17, 31, 85, .16)';
	$t['--focus-ring'] = sprintf( '0 0 0 3px rgba(%d, %d, %d, .28)', $r, $g, $b );
	$t['--overlay']    = 'rgba(17, 24, 39, .55)';

	$t['--width-reading'] = '720px';
	$t['--width-page']    = '1200px';
	$t['--width-wide']    = '1440px';
	$t['--gutter']        = sprintf( 'clamp(%dpx, 3vw, %dpx)', round( 16 * $space ), round( 32 * $space ) );
	$t['--section-space'] = sprintf( 'clamp(%dpx, 8vw, %dpx)', round( 56 * $space ), round( 96 * $space ) );

	/**
	 * Filters the site's named styles.
	 *
	 * For a plugin that has to adjust a value the choices cannot express.
	 * Values are cleaned again before they reach a page.
	 *
	 * @param array $tokens   Values keyed by named style.
	 * @param array $settings The choices they came from.
	 */
	return (array) apply_filters( 'blueworx_design_tokens', $t, $s );
}

/**
 * The type sizes used below 640px.
 *
 * @param array|null $settings Choices; null reads the site's.
 * @return array Values keyed by named style.
 */
function blueworx_design_phone_tokens( $settings = null ) {
	$s     = null === $settings ? blueworx_design_settings() : blueworx_design_sanitize( $settings );
	$type  = blueworx_design_choices()['type_scale'][ $s['type_scale'] ]['value'];
	$phone = array();

	foreach ( blueworx_design_type_sizes() as $name => $sizes ) {
		if ( $sizes[0] !== $sizes[1] ) {
			$phone[ $name ] = (int) round( $sizes[1] * ( blueworx_design_type_scales( $name ) ? $type : 1 ) ) . 'px';
		}
	}

	return $phone;
}

/**
 * One of the site's named styles.
 *
 * @param string $name Named style, e.g. --color-primary.
 * @return string Its value, or an empty string for a name that does not exist.
 */
function blueworx_design_token( $name ) {
	$tokens = blueworx_design_tokens();

	return isset( $tokens[ $name ] ) ? (string) $tokens[ $name ] : '';
}

/**
 * Writes named styles as declarations.
 *
 * Names and values are cleaned here, the last step before a page, so neither a
 * plugin's filter nor a stored value can end the rule or the style tag.
 *
 * @param array $tokens Values keyed by named style.
 * @return string Declarations.
 */
function blueworx_design_declarations( $tokens ) {
	$css = '';

	foreach ( $tokens as $name => $value ) {
		if ( ! is_string( $name ) || ! preg_match( '/^--[a-z0-9-]+$/', $name ) ) {
			continue;
		}

		$css .= $name . ':' . trim( preg_replace( '/[;{}<>\\\\]/', '', (string) $value ) ) . ';';
	}

	return $css;
}

/**
 * The named styles as CSS.
 *
 * @param array|null $settings Choices; null reads the site's.
 * @return string A :root block, then the phone sizes.
 */
function blueworx_design_css( $settings = null ) {
	return ':root{' . blueworx_design_declarations( blueworx_design_tokens( $settings ) ) . '}'
		. '@media (max-width:639px){:root{' . blueworx_design_declarations( blueworx_design_phone_tokens( $settings ) ) . '}}';
}

/**
 * The @font-face rules for the two chosen fonts.
 *
 * Only the chosen fonts' files, so a site never downloads a typeface it does
 * not use, and none at all for a device font.
 *
 * @param array|null $settings Choices; null reads the site's.
 * @return string CSS.
 */
function blueworx_design_font_face_css( $settings = null ) {
	$s     = null === $settings ? blueworx_design_settings() : blueworx_design_sanitize( $settings );
	$fonts = blueworx_design_fonts();
	$css   = '';

	foreach ( array_unique( array( $s['heading_font'], $s['body_font'] ) ) as $id ) {
		$family = preg_replace( '/[^A-Za-z0-9 _-]/', '', (string) $fonts[ $id ]['family'] );
		$files  = isset( $fonts[ $id ]['files'] ) && is_array( $fonts[ $id ]['files'] ) ? $fonts[ $id ]['files'] : array();

		if ( '' === $family ) {
			continue;
		}

		foreach ( $files as $weight => $url ) {
			$url = preg_replace( '/["\'()<>;{}\s\\\\]/', '', esc_url_raw( (string) $url ) );

			if ( '' === $url ) {
				continue;
			}

			$css .= sprintf(
				'@font-face{font-family:"%1$s";font-style:normal;font-weight:%2$d;font-display:swap;src:url(%3$s) format("woff2");}',
				$family,
				absint( $weight ),
				$url
			);
		}
	}

	return $css;
}

/**
 * Checks every text pairing the site uses.
 *
 * @param array|null $tokens Named styles; null reads the site's.
 * @return array Rows: label, text, background, ratio (one decimal) and passes.
 */
function blueworx_design_readability( $tokens = null ) {
	$t = null === $tokens ? blueworx_design_tokens() : $tokens;

	$pairs = array(
		array( __( 'Body text on the background', 'blueworx-labs-wordpress' ), '--color-text', '--color-bg' ),
		array( __( 'Secondary text on the background', 'blueworx-labs-wordpress' ), '--color-text-muted', '--color-bg' ),
		array( __( 'Placeholder text on the background', 'blueworx-labs-wordpress' ), '--color-placeholder', '--color-bg' ),
		array( __( 'Links on the background', 'blueworx-labs-wordpress' ), '--color-link', '--color-bg' ),
		array( __( 'Button text on the main colour', 'blueworx-labs-wordpress' ), '--color-on-primary', '--color-primary' ),
		array( __( 'Button text on hover', 'blueworx-labs-wordpress' ), '--color-on-primary', '--color-primary-hover' ),
		array( __( 'Button text when pressed', 'blueworx-labs-wordpress' ), '--color-on-primary', '--color-primary-pressed' ),
		array( __( 'Text on the second colour', 'blueworx-labs-wordpress' ), '--color-on-accent', '--color-accent' ),
		array( __( 'Second colour as text on the background', 'blueworx-labs-wordpress' ), '--color-accent-text', '--color-bg' ),
		array( __( 'Body text on panels', 'blueworx-labs-wordpress' ), '--color-text', '--color-surface' ),
	);

	foreach ( array(
		'success' => __( 'Success messages', 'blueworx-labs-wordpress' ),
		'warning' => __( 'Warnings', 'blueworx-labs-wordpress' ),
		'error'   => __( 'Error messages', 'blueworx-labs-wordpress' ),
		'info'    => __( 'Information messages', 'blueworx-labs-wordpress' ),
	) as $status => $label ) {
		/* translators: %s: a kind of message, e.g. "Warnings". */
		$pairs[] = array( sprintf( __( '%s on the background', 'blueworx-labs-wordpress' ), $label ), '--color-' . $status, '--color-bg' );
		/* translators: %s: a kind of message, e.g. "Warnings". */
		$pairs[] = array( sprintf( __( '%s on their tint', 'blueworx-labs-wordpress' ), $label ), '--color-' . $status, '--color-' . $status . '-tint' );
	}

	$rows = array();

	foreach ( $pairs as $pair ) {
		$ratio = blueworx_color_contrast( $t[ $pair[1] ], $t[ $pair[2] ] );

		$rows[] = array(
			'label'      => $pair[0],
			'text'       => $pair[1],
			'background' => $pair[2],
			'ratio'      => round( $ratio, 1 ),
			'passes'     => $ratio >= 4.5,
		);
	}

	return $rows;
}
