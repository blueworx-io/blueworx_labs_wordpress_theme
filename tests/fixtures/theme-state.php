<?php
/**
 * Test fixture — NOT part of the theme.
 *
 * Puts the test site into the theme states the theme specs need, which the
 * admin has no reliable way to reach: a child theme, a switch to another theme
 * and back, a site that has never been set up, and the 1.x plugin running
 * alongside.
 *
 * Usage: php theme-state.php /absolute/path/to/wp-load.php <command>
 *
 * Commands:
 *   child-on       writes a blueworx-child theme and switches to it
 *   child-off      switches back to blueworx and deletes the child theme
 *   switch-away    switches to twentytwentyfive, the way the Themes screen does
 *   switch-back    switches back to blueworx
 *   forget-setup   deletes blueworx_theme_setup_version, as on a fresh site
 *   setup-version  prints blueworx_theme_setup_version as it was before this
 *                  script's own load ran the setup
 *   support-state  prints "<support user exists>|<key stored>", e.g. "0|0"
 *   support-caps   prints "<caps the role stores>|<can open settings with the
 *                  theme running>|<and without it>", e.g. "read|1|0"
 *   support-off    removes the support account and key
 *   plugin-on      writes an mu-plugin that looks like the 1.x plugin loaded
 *   plugin-off     removes it
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$command = isset( $argv[2] ) ? $argv[2] : '';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// The plugin stand-in is a file, not a database row, and must be removable
// even when it stops the theme loading, so it is handled before WordPress.
$mu_dir  = dirname( $wp_load ) . '/wp-content/mu-plugins';
$mu_file = $mu_dir . '/blueworx-fake-labs-plugin.php';

if ( 'plugin-on' === $command ) {
	if ( ! is_dir( $mu_dir ) ) {
		mkdir( $mu_dir, 0777, true );
	}
	file_put_contents(
		$mu_file,
		"<?php\n/**\n * Plugin Name: BlueWorx fake 1.x plugin\n * Description: Test fixture. Defines the 1.x plugin's version constant, as the plugin does when it loads.\n */\ndefine( 'BLUEWORX_LABS_VERSION', '1.96.3' );\n"
	);
	echo "ok\n";
	exit( 0 );
}

if ( 'plugin-off' === $command ) {
	if ( is_file( $mu_file ) ) {
		unlink( $mu_file );
	}
	echo "ok\n";
	exit( 0 );
}

// See tests/fixtures/clear-view-as.php: without this, Site Protection treats
// this CLI process as an anonymous visitor and stops it once WordPress loads.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );

// Loading WordPress runs the theme's own setup, which would write the very
// option this is asked to read. So it is read on setup_theme, just before the
// theme loads. WordPress picks up hooks set in $wp_filter before it starts.
$GLOBALS['setup_version_before_load'] = '';
if ( 'setup-version' === $command ) {
	$GLOBALS['wp_filter'] = array(
		'setup_theme' => array(
			10 => array(
				array(
					'function'      => static function () {
						$GLOBALS['setup_version_before_load'] = (string) get_option( 'blueworx_theme_setup_version', '' );
					},
					'accepted_args' => 0,
				),
			),
		),
	);
}

require $wp_load;

$child_dir = get_theme_root() . '/blueworx-child';

switch ( $command ) {
	case 'child-on':
		if ( ! is_dir( $child_dir ) ) {
			mkdir( $child_dir, 0777, true );
		}
		file_put_contents( $child_dir . '/style.css', "/*\nTheme Name: BlueWorx child (test)\nTemplate: blueworx-labs-wordpress-theme\n*/\n" );
		search_theme_directories( true );
		switch_theme( 'blueworx-child' );
		echo get_option( 'stylesheet' ) . "\n";
		break;

	case 'child-off':
		switch_theme( 'blueworx-labs-wordpress-theme' );
		if ( is_file( $child_dir . '/style.css' ) ) {
			unlink( $child_dir . '/style.css' );
		}
		if ( is_dir( $child_dir ) ) {
			rmdir( $child_dir );
		}
		echo get_option( 'stylesheet' ) . "\n";
		break;

	case 'switch-away':
		switch_theme( 'twentytwentyfive' );
		echo get_option( 'stylesheet' ) . "\n";
		break;

	case 'switch-back':
		switch_theme( 'blueworx-labs-wordpress-theme' );
		echo get_option( 'stylesheet' ) . "\n";
		break;

	case 'forget-setup':
		delete_option( 'blueworx_theme_setup_version' );
		echo "ok\n";
		break;

	case 'setup-version':
		echo $GLOBALS['setup_version_before_load'] . "\n";
		break;

	case 'support-state':
		$user = get_user_by( 'login', 'blueworx_support' );
		$key  = get_option( 'blueworx_support_key_hash', '' );
		echo ( $user ? '1' : '0' ) . '|' . ( $key ? '1' : '0' ) . "\n";
		break;

	case 'support-caps':
		$user   = get_user_by( 'login', 'blueworx_support' );
		$role   = get_role( 'blueworx_support' );
		$stored = $role ? implode( ',', array_keys( array_filter( $role->capabilities ) ) ) : '';
		$with   = $user && user_can( $user, 'manage_options' ) ? '1' : '0';

		// What a request sees where the theme is not loaded, such as another
		// theme being previewed in the Customizer.
		remove_filter( 'user_has_cap', 'blueworx_readonly_grant_caps', 1 );
		$user    = $user ? new WP_User( $user->ID ) : null;
		$without = $user && user_can( $user, 'manage_options' ) ? '1' : '0';

		echo $stored . '|' . $with . '|' . $without . "\n";
		break;

	case 'support-off':
		blueworx_support_on_deactivate();
		echo "ok\n";
		break;

	default:
		fwrite( STDERR, "Unknown command: $command\n" );
		exit( 1 );
}
