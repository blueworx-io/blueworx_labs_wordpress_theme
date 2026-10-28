<?php
/**
 * Site design system: what goes in the download.
 *
 * The zip has to hold exactly what the design system page shows, never fetch
 * anything from another site, and still make sense on a site with no logo and
 * no font files of its own.
 *
 * Run with: php tests/php/design-download-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';

define( 'BLUEWORX_LABS_PATH', dirname( __DIR__, 2 ) . '/' );
define( 'BLUEWORX_LABS_URL', 'https://site.test/wp-content/themes/blueworx-labs-wordpress-theme/' );
define( 'BLUEWORX_LABS_VERSION', '2.3.0' );
define( 'WP_CONTENT_DIR', dirname( __DIR__, 2 ) . '/.wp-test-none' );

$GLOBALS['theme_mods'] = array();
$GLOBALS['attached']   = array();
$GLOBALS['site_name']  = 'Fixture Site';

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function add_action( $hook, $callback, $priority = 10 ) {
	return true;
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- The stub stands in for it.
}

function esc_url_raw( $url ) {
	return filter_var( $url, FILTER_SANITIZE_URL );
}

function sanitize_title( $title ) {
	$title = strtr(
		(string) $title,
		array(
			'é' => 'e',
			'è' => 'e',
			'à' => 'a',
			'ç' => 'c',
		)
	);
	return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $title ) ), '-' );
}

function get_bloginfo( $what ) {
	return 'name' === $what ? $GLOBALS['site_name'] : '';
}

function wp_specialchars_decode( $text, $quotes = 0 ) {
	return htmlspecialchars_decode( (string) $text, ENT_QUOTES );
}

function get_theme_mod( $name, $default = false ) {
	return isset( $GLOBALS['theme_mods'][ $name ] ) ? $GLOBALS['theme_mods'][ $name ] : $default;
}

function get_attached_file( $id ) {
	return isset( $GLOBALS['attached'][ $id ] ) ? $GLOBALS['attached'][ $id ] : false;
}

function trailingslashit( $value ) {
	return rtrim( $value, '/\\' ) . '/';
}

function content_url( $path = '' ) {
	return 'https://site.test/wp-content/' . ltrim( $path, '/' );
}

function wp_date( $format ) {
	return '28 October 2026';
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/design/color.php';
require dirname( __DIR__, 2 ) . '/includes/design/settings.php';
require dirname( __DIR__, 2 ) . '/includes/design/tokens.php';
require dirname( __DIR__, 2 ) . '/includes/design/download.php';

check( 'folder from the site name', blueworx_design_download_folder( 'Café & Co!' ), 'cafe-co-design-system' );
check( 'folder when the name has no letters', blueworx_design_download_folder( '!!!' ), 'site-design-system' );

$m = blueworx_design_download_manifest( blueworx_design_defaults() );

check( 'tokens.css is the same file the page shows', $m['tokens/tokens.css']['content'], blueworx_design_tokens_file( blueworx_design_defaults() ) );

$json = json_decode( $m['tokens/tokens.json']['content'], true );
check( 'tokens.json holds every named style', count( $json['root'] ), count( blueworx_design_tokens( blueworx_design_defaults() ) ) );
check( 'tokens.json holds the phone sizes', $json['phone']['--text-h1'], '34px' );

$colours = json_decode( $m['tokens/colours.json']['content'], true );
check( 'colours.json has the main colour first', $colours['brand'][0]['hex'], '#2f5bea' );
check( 'colours.json says what it is for', '' !== $colours['brand'][0]['use'], true );

check( 'the heading font file is the theme\'s own', $m['fonts/sora-700.woff2']['file'], BLUEWORX_LABS_PATH . 'assets/fonts/sora-700.woff2' );
check( 'the font file exists', is_readable( $m['fonts/sora-700.woff2']['file'] ), true );
check( 'fonts.css loads the bundled file', false !== strpos( $m['fonts/fonts.css']['content'], 'url("sora-700.woff2")' ), true );
check( 'a README', isset( $m['README.md'] ), true );
check( 'logo guidance', isset( $m['logos/LOGO-USAGE.md'] ), true );
check( 'no logo file when none is set', array_values( preg_grep( '#^logos/logo\.#', array_keys( $m ) ) ), array() );
check( 'the README says no logo is set', false !== stripos( $m['README.md']['content'], 'no logo' ), true );

$device = blueworx_design_download_manifest(
	array_merge(
		blueworx_design_defaults(),
		array(
			'heading_font' => 'system',
			'body_font'    => 'serif',
		)
	)
);
check( 'device fonts put no font files in', array_values( preg_grep( '#^fonts/#', array_keys( $device ) ) ), array() );

$GLOBALS['theme_mods']['custom_logo'] = 7;
$GLOBALS['attached'][7]               = __FILE__;
$with_logo                            = blueworx_design_download_manifest( blueworx_design_defaults() );
check( 'an uploaded logo goes in under its own extension', $with_logo['logos/logo.php']['file'], __FILE__ );

$GLOBALS['filters']['blueworx_design_fonts'] = function ( $fonts ) {
	$fonts['remote'] = array(
		'label'  => 'Remote Sans',
		'family' => 'Remote Sans',
		'stack'  => '"Remote Sans", sans-serif',
		'files'  => array( 400 => 'https://fonts.example.com/remote.woff2' ),
	);
	return $fonts;
};
$remote                                      = blueworx_design_download_manifest( array_merge( blueworx_design_defaults(), array( 'heading_font' => 'remote' ) ) );
check( 'a font from another site is not fetched', isset( $remote['fonts/remote.woff2'] ), false );
check( 'the README names a font it left out', false !== strpos( $remote['README.md']['content'], 'Remote Sans' ), true );

check( 'a theme URL maps to its file', blueworx_design_local_path( BLUEWORX_LABS_URL . 'assets/fonts/inter-400.woff2' ), BLUEWORX_LABS_PATH . 'assets/fonts/inter-400.woff2' );
check( 'a URL elsewhere maps to nothing', blueworx_design_local_path( 'https://evil.test/x.woff2' ), '' );
check( 'a path that climbs out maps to nothing', blueworx_design_local_path( BLUEWORX_LABS_URL . '../../../wp-config.php' ), '' );

$palette = blueworx_design_palette();
check( 'the palette has its three groups', array_keys( $palette ), array( 'brand', 'neutrals', 'messages' ) );

finish();
