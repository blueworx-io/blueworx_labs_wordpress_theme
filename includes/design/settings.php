<?php
/**
 * Site design system: the choices the team makes, and where they are kept.
 *
 * One option, blueworx_design, holds every choice. It is cleaned on the way in
 * and again on the way out, so whatever reads it always gets a complete set of
 * values that are among the choices on offer — never a missing key, and never
 * something typed straight into the database.
 *
 * Plugins add to two lists here and nothing else: fonts (a plugin that needs a
 * typeface ships its own files) and presets (ready-made sets of choices, which
 * is how Clubhouse V2 supplies its skins). The theme itself ships no presets.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a fresh site gets: the plain default look.
 *
 * An empty accent means "work one out from the main colour".
 *
 * @return array Settings keyed by name.
 */
function blueworx_design_defaults() {
	return array(
		'primary'      => '#2f5bea',
		'accent'       => '#f08a2c',
		'background'   => '#ffffff',
		'text'         => '#16181d',
		'heading_font' => 'sora',
		'body_font'    => 'inter',
		'type_scale'   => 'standard',
		'spacing'      => 'standard',
		'corners'      => 'rounded',
		'buttons'      => 'rounded',
		'logo_dark'    => 0,
		'logo_mark'    => 0,
		'social'       => array(),
		'preset'       => '',
	);
}

/**
 * The options offered for each setting that is a choice rather than a value.
 *
 * Type size and spacing carry the number the named styles are multiplied by;
 * corners and buttons are read by name.
 *
 * @return array Choices keyed by setting, then by choice.
 */
function blueworx_design_choices() {
	return array(
		'type_scale' => array(
			'compact'  => array(
				'label' => __( 'Compact', 'blueworx-labs-wordpress' ),
				'value' => 0.875,
			),
			'standard' => array(
				'label' => __( 'Standard', 'blueworx-labs-wordpress' ),
				'value' => 1,
			),
			'large'    => array(
				'label' => __( 'Large', 'blueworx-labs-wordpress' ),
				'value' => 1.125,
			),
		),
		'spacing'    => array(
			'compact'  => array(
				'label' => __( 'Compact', 'blueworx-labs-wordpress' ),
				'value' => 0.75,
			),
			'standard' => array(
				'label' => __( 'Standard', 'blueworx-labs-wordpress' ),
				'value' => 1,
			),
			'roomy'    => array(
				'label' => __( 'Roomy', 'blueworx-labs-wordpress' ),
				'value' => 1.25,
			),
		),
		'corners'    => array(
			'square'  => array(
				'label' => __( 'Square', 'blueworx-labs-wordpress' ),
				'value' => array( 0, 0, 0, 0 ),
			),
			'subtle'  => array(
				'label' => __( 'Slightly rounded', 'blueworx-labs-wordpress' ),
				'value' => array( 2, 4, 6, 10 ),
			),
			'rounded' => array(
				'label' => __( 'Rounded', 'blueworx-labs-wordpress' ),
				'value' => array( 4, 8, 12, 20 ),
			),
			'round'   => array(
				'label' => __( 'Very rounded', 'blueworx-labs-wordpress' ),
				'value' => array( 8, 12, 20, 32 ),
			),
		),
		'buttons'    => array(
			'square'  => array(
				'label' => __( 'Square', 'blueworx-labs-wordpress' ),
				'value' => 'square',
			),
			'rounded' => array(
				'label' => __( 'Match the corners', 'blueworx-labs-wordpress' ),
				'value' => 'rounded',
			),
			'pill'    => array(
				'label' => __( 'Pill', 'blueworx-labs-wordpress' ),
				'value' => 'pill',
			),
		),
	);
}

/**
 * The social networks a site can link to.
 *
 * @return array Labels keyed by network.
 */
function blueworx_design_social_networks() {
	return array(
		'facebook'  => __( 'Facebook', 'blueworx-labs-wordpress' ),
		'instagram' => __( 'Instagram', 'blueworx-labs-wordpress' ),
		'x'         => __( 'X', 'blueworx-labs-wordpress' ),
		'linkedin'  => __( 'LinkedIn', 'blueworx-labs-wordpress' ),
		'youtube'   => __( 'YouTube', 'blueworx-labs-wordpress' ),
		'tiktok'    => __( 'TikTok', 'blueworx-labs-wordpress' ),
	);
}

/**
 * The fonts a site can choose from.
 *
 * Sora and Inter ship with the theme; the other two are fonts every device
 * already has, so they load nothing. Nothing is ever loaded from Google.
 *
 * @return array Fonts keyed by id: label, family, stack (the CSS font-family
 *               value) and files (weight => woff2 URL).
 */
function blueworx_design_fonts() {
	$dir = BLUEWORX_LABS_URL . 'assets/fonts/';

	$fonts = array(
		'sora'   => array(
			'label'  => 'Sora',
			'family' => 'Sora',
			'stack'  => '"Sora", system-ui, sans-serif',
			'files'  => array(
				400 => $dir . 'sora-400.woff2',
				600 => $dir . 'sora-600.woff2',
				700 => $dir . 'sora-700.woff2',
			),
		),
		'inter'  => array(
			'label'  => 'Inter',
			'family' => 'Inter',
			'stack'  => '"Inter", system-ui, sans-serif',
			'files'  => array(
				400 => $dir . 'inter-400.woff2',
				500 => $dir . 'inter-500.woff2',
				600 => $dir . 'inter-600.woff2',
			),
		),
		'system' => array(
			'label'  => __( 'Device font (no download)', 'blueworx-labs-wordpress' ),
			'family' => '',
			'stack'  => 'system-ui, -apple-system, "Segoe UI", sans-serif',
			'files'  => array(),
		),
		'serif'  => array(
			'label'  => __( 'Classic serif (no download)', 'blueworx-labs-wordpress' ),
			'family' => '',
			'stack'  => 'Georgia, "Times New Roman", serif',
			'files'  => array(),
		),
	);

	/**
	 * Filters the fonts a site can choose from.
	 *
	 * A plugin that needs a typeface adds it here with its own files, so the
	 * theme never has to carry fonts only one product uses.
	 *
	 * @param array $fonts Fonts keyed by id: label, family, stack, files.
	 */
	$filtered = (array) apply_filters( 'blueworx_design_fonts', $fonts );
	$valid    = array();

	// A font is used on every request, so one a plugin got wrong is left out
	// rather than allowed to break every page, admin included.
	foreach ( $filtered as $id => $font ) {
		if ( ! is_string( $id ) || ! is_array( $font ) || empty( $font['stack'] ) || ! is_string( $font['stack'] ) ) {
			continue;
		}

		$valid[ $id ] = array(
			'label'  => isset( $font['label'] ) && is_string( $font['label'] ) ? $font['label'] : $id,
			'family' => isset( $font['family'] ) && is_string( $font['family'] ) ? $font['family'] : '',
			'stack'  => $font['stack'],
			'files'  => isset( $font['files'] ) && is_array( $font['files'] ) ? $font['files'] : array(),
		);
	}

	return $valid;
}

/**
 * Ready-made sets of choices that plugins supply.
 *
 * @return array Presets keyed by id: label, description and settings (any of
 *               the keys in blueworx_design_defaults()).
 */
function blueworx_design_presets() {
	/**
	 * Filters the presets offered on the Design screen.
	 *
	 * The theme ships none. Clubhouse V2 adds its skins here.
	 *
	 * @param array $presets Presets keyed by id: label, description, settings.
	 */
	return (array) apply_filters( 'blueworx_design_presets', array() );
}

/**
 * Cleans a set of choices.
 *
 * Every key comes back. Anything missing, or not one of the choices on offer,
 * takes the default rather than being guessed at.
 *
 * @param array $raw Choices from a form, a preset or the database.
 * @return array A complete, clean set.
 */
function blueworx_design_sanitize( $raw ) {
	$raw      = is_array( $raw ) ? $raw : array();
	$defaults = blueworx_design_defaults();
	$choices  = blueworx_design_choices();
	$fonts    = blueworx_design_fonts();
	$clean    = $defaults;

	foreach ( array( 'primary', 'accent', 'background', 'text' ) as $key ) {
		if ( ! isset( $raw[ $key ] ) ) {
			continue;
		}

		$color = blueworx_color_normalize( $raw[ $key ] );

		// Only the second colour may be left empty: it is then worked out.
		if ( '' !== $color || ( 'accent' === $key && '' === trim( (string) $raw[ $key ] ) ) ) {
			$clean[ $key ] = $color;
		}
	}

	foreach ( array( 'heading_font', 'body_font' ) as $key ) {
		if ( isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) && isset( $fonts[ $raw[ $key ] ] ) ) {
			$clean[ $key ] = $raw[ $key ];
		}
	}

	foreach ( $choices as $key => $options ) {
		if ( isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) && isset( $options[ $raw[ $key ] ] ) ) {
			$clean[ $key ] = $raw[ $key ];
		}
	}

	foreach ( array( 'logo_dark', 'logo_mark' ) as $key ) {
		if ( isset( $raw[ $key ] ) ) {
			$clean[ $key ] = absint( $raw[ $key ] );
		}
	}

	if ( isset( $raw['social'] ) && is_array( $raw['social'] ) ) {
		foreach ( array_keys( blueworx_design_social_networks() ) as $network ) {
			if ( empty( $raw['social'][ $network ] ) || ! is_string( $raw['social'][ $network ] ) ) {
				continue;
			}

			$url    = esc_url_raw( trim( $raw['social'][ $network ] ) );
			$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );

			if ( in_array( $scheme, array( 'http', 'https' ), true ) ) {
				$clean['social'][ $network ] = $url;
			}
		}
	}

	if ( isset( $raw['preset'] ) && is_string( $raw['preset'] ) ) {
		$clean['preset'] = preg_replace( '/[^a-z0-9_-]/', '', strtolower( $raw['preset'] ) );
	}

	return $clean;
}

/**
 * The site's current choices.
 *
 * @return array A complete, clean set.
 */
function blueworx_design_settings() {
	return blueworx_design_sanitize( get_option( 'blueworx_design', array() ) );
}

/**
 * Cleans and stores a set of choices.
 *
 * @param array $raw Choices.
 * @return array What was stored.
 */
function blueworx_design_save( $raw ) {
	$clean = blueworx_design_sanitize( $raw );

	update_option( 'blueworx_design', $clean );

	return $clean;
}

/**
 * Replaces the site's choices with a preset's.
 *
 * Anything the preset leaves out takes the default, not the site's previous
 * value, so the same preset always gives the same look. The site's own brand
 * (its extra logos and social links) is not part of a look and is kept.
 *
 * @param string $id Preset id.
 * @return bool False when no such preset exists.
 */
function blueworx_design_apply_preset( $id ) {
	$presets = blueworx_design_presets();

	if ( ! is_string( $id ) || ! isset( $presets[ $id ] ) ) {
		return false;
	}

	$settings = isset( $presets[ $id ]['settings'] ) && is_array( $presets[ $id ]['settings'] ) ? $presets[ $id ]['settings'] : array();
	$brand    = array_intersect_key( blueworx_design_settings(), array_flip( array( 'logo_dark', 'logo_mark', 'social' ) ) );

	blueworx_design_save( array_merge( $settings, $brand, array( 'preset' => $id ) ) );

	return true;
}

/**
 * Who may change the design.
 *
 * Administrators, for now.
 *
 * @return string A capability.
 */
function blueworx_design_capability() {
	/**
	 * Filters the capability needed to change the site's design.
	 *
	 * @param string $capability Default manage_options.
	 */
	return (string) apply_filters( 'blueworx_design_capability', 'manage_options' );
}
