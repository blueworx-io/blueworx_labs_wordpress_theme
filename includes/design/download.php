<?php
/**
 * Site design system: the download.
 *
 * One zip of the site's design system, built from its current settings, so a
 * designer or another tool gets exactly what the site uses: the named styles
 * (the same file the design system page shows), the palette, the font files,
 * the logos and a short read-me.
 *
 * Built with WordPress's own PclZip rather than ZipArchive, which some hosts
 * leave out; PclZip needs nothing but zlib. Nothing is ever fetched from
 * another site: a font hosted elsewhere is named in the read-me instead.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The zip's folder name.
 *
 * @param string $site_name The site's name.
 * @return string e.g. "cafe-co-design-system".
 */
function blueworx_design_download_folder( $site_name ) {
	$slug = sanitize_title( $site_name );

	return ( '' !== $slug ? $slug : 'site' ) . '-design-system';
}

/**
 * The file on this site behind a URL in the theme or wp-content.
 *
 * Only ever a file inside those two folders, so a crafted URL cannot reach
 * anything else on the server.
 *
 * @param string $url A URL.
 * @return string The absolute path, or an empty string.
 */
function blueworx_design_local_path( $url ) {
	$roots = array(
		BLUEWORX_LABS_URL                 => BLUEWORX_LABS_PATH,
		trailingslashit( content_url() ) => trailingslashit( WP_CONTENT_DIR ),
	);

	foreach ( $roots as $base_url => $base_path ) {
		if ( 0 !== strpos( (string) $url, $base_url ) ) {
			continue;
		}

		$relative = substr( (string) $url, strlen( $base_url ) );

		if ( false !== strpos( $relative, '..' ) || false !== strpos( $relative, '?' ) ) {
			return '';
		}

		$path = $base_path . $relative;

		return is_readable( $path ) && is_file( $path ) ? $path : '';
	}

	return '';
}

/**
 * Everything that goes in the zip.
 *
 * @param array|null $settings Choices; null reads the site's.
 * @return array Paths inside the folder => array( 'content' => text ) or
 *               array( 'file' => absolute path ).
 */
function blueworx_design_download_manifest( $settings = null ) {
	$s       = null === $settings ? blueworx_design_settings() : blueworx_design_sanitize( $settings );
	$tokens  = blueworx_design_tokens( $s );
	$fonts   = blueworx_design_fonts();
	$name    = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	$files   = array();
	$missing = array();

	$files['tokens/tokens.css']  = array( 'content' => blueworx_design_tokens_file( $s ) );
	$files['tokens/tokens.json'] = array(
		'content' => wp_json_encode(
			array(
				'root'  => $tokens,
				'phone' => blueworx_design_phone_tokens( $s ),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		) . "\n",
	);

	$colours = array();

	foreach ( blueworx_design_palette() as $group => $rows ) {
		foreach ( $rows as $row ) {
			$colours[ $group ][] = array(
				'name'  => $row['label'],
				'token' => $row['token'],
				'hex'   => $tokens[ $row['token'] ],
				'use'   => $row['use'],
			);
		}
	}

	$files['tokens/colours.json'] = array( 'content' => wp_json_encode( $colours, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );

	// Fonts: the chosen two, from their files on this site.
	$faces = '';

	foreach ( array_unique( array( $s['heading_font'], $s['body_font'] ) ) as $id ) {
		$font = $fonts[ $id ];

		foreach ( $font['files'] as $weight => $url ) {
			$path = blueworx_design_local_path( $url );

			if ( '' === $path ) {
				$missing[] = $font['label'];
				continue;
			}

			$file                       = basename( $path );
			$files[ 'fonts/' . $file ] = array( 'file' => $path );

			$faces .= sprintf(
				"@font-face {\n  font-family: \"%1\$s\";\n  font-weight: %2\$d;\n  font-style: normal;\n  font-display: swap;\n  src: url(\"%3\$s\") format(\"woff2\");\n}\n\n",
				preg_replace( '/[^A-Za-z0-9 _-]/', '', $font['family'] ),
				absint( $weight ),
				$file
			);
		}
	}

	if ( '' !== $faces ) {
		$files['fonts/fonts.css'] = array( 'content' => rtrim( $faces ) . "\n" );
	}

	// Logos: whichever are set, as uploaded.
	$logos = array(
		'logo'      => (int) get_theme_mod( 'custom_logo', 0 ),
		'logo-dark' => $s['logo_dark'],
		'logo-mark' => $s['logo_mark'],
		'favicon'   => (int) get_option( 'site_icon', 0 ),
	);
	$found = array();

	foreach ( $logos as $label => $id ) {
		$path = $id ? get_attached_file( $id ) : false;

		if ( $path && is_readable( $path ) ) {
			$files[ 'logos/' . $label . '.' . strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ] = array( 'file' => $path );
			$found[] = $label;
		}
	}

	$files['logos/LOGO-USAGE.md'] = array( 'content' => blueworx_design_download_logo_usage( $name ) );
	$files['README.md']           = array( 'content' => blueworx_design_download_readme( $name, $s, $found, array_unique( $missing ) ) );

	return $files;
}

/**
 * The read-me at the top of the zip.
 *
 * @param string $name    Site name.
 * @param array  $s       Clean settings.
 * @param array  $logos   Which logos went in.
 * @param array  $missing Fonts whose files are not on this site.
 * @return string Markdown.
 */
function blueworx_design_download_readme( $name, $s, $logos, $missing ) {
	$fonts = blueworx_design_fonts();
	$lines = array(
		'# ' . $name . ' design system',
		'',
		'Built from the site\'s settings on ' . wp_date( 'j F Y' ) . ' by BlueWorx Enhance ' . BLUEWORX_LABS_VERSION . '. It matches the site exactly as it was then.',
		'',
		'## What is here',
		'',
		'- `tokens/tokens.css`: every named style, ready to paste into a stylesheet. Use the names (`var(--color-primary)`), never the values.',
		'- `tokens/tokens.json`: the same values for design tools. `root` is every style; `phone` is the type sizes below 640px.',
		'- `tokens/colours.json`: the palette, with what each colour is for.',
		'- `fonts/`: the font files and `fonts.css`, which loads them.',
		'- `logos/`: the logos, and `LOGO-USAGE.md` on how to use them.',
		'',
		'## Fonts',
		'',
		'- Headings: ' . $fonts[ $s['heading_font'] ]['label'],
		'- Body: ' . $fonts[ $s['body_font'] ]['label'],
	);

	if ( $missing ) {
		$lines[] = '';
		$lines[] = 'Not included, because their files are not on this site: ' . implode( ', ', $missing ) . '. Get them from wherever the site loads them.';
	}

	$lines[] = '';
	$lines[] = '## Logos';
	$lines[] = '';
	$lines[] = $logos
		? 'Included: ' . implode( ', ', $logos ) . '.'
		: 'No logo is set on the site yet. Add one on BlueWorx › Design and download again.';
	$lines[] = '';

	return implode( "\n", $lines );
}

/**
 * The logo guidance that sits beside the logos.
 *
 * @param string $name Site name.
 * @return string Markdown.
 */
function blueworx_design_download_logo_usage( $name ) {
	return implode(
		"\n",
		array(
			'# Using the ' . $name . ' logo',
			'',
			'- **Clear space:** leave space around the logo at least the height of the mark, on every side.',
			'- **Smallest size:** 24px tall for the mark, 120px wide for the full logo.',
			'- **On dark backgrounds** and on the main colour, use `logo-dark`.',
			'- **Where space is tight,** such as an app icon or avatar, use `logo-mark`.',
			'- **Never** stretch, recolour, rotate or add effects to it, or put it on a busy photo.',
			'',
		)
	);
}
