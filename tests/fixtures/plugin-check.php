<?php
/**
 * Test fixture — NOT part of the theme.
 *
 * Sets which list the Plugin check sees, through the mu-plugin that
 * tests/global-setup.js writes. The harness has no real plugins, so this also
 * drops one inactive stand-in plugin to have something to list.
 *
 * Usage: php plugin-check.php /absolute/path/to/wp-load.php <mode>
 *
 *   none   Nothing is approved, so every installed plugin is flagged.
 *   all    Every installed plugin is approved.
 *   off    Removes the stand-in plugin and the mode, back to the real list.
 *
 * @package BlueWorxLabsTests
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.WP.AlternativeFunctions -- A CLI fixture writing its own files and stderr.
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Reports a bad path on the command line.

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$mode    = isset( $argv[2] ) ? $argv[2] : '';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

if ( ! in_array( $mode, array( 'none', 'all', 'off' ), true ) ) {
	fwrite( STDERR, "Mode must be none, all or off.\n" );
	exit( 1 );
}

$plugin_dir  = dirname( $wp_load ) . '/wp-content/plugins/bw-test-unapproved';
$plugin_file = $plugin_dir . '/bw-test-unapproved.php';

if ( 'off' === $mode ) {
	if ( is_file( $plugin_file ) ) {
		unlink( $plugin_file );
		rmdir( $plugin_dir );
	}
} elseif ( ! is_file( $plugin_file ) ) {
	if ( ! is_dir( $plugin_dir ) ) {
		mkdir( $plugin_dir, 0777, true );
	}
	file_put_contents( $plugin_file, "<?php\n/**\n * Plugin Name: BW Test Unapproved\n * Version: 1.2.3\n * Description: Test fixture only.\n */\n" );
}

require $wp_load;

if ( 'off' === $mode ) {
	delete_option( 'bw_test_approved_mode' );
} else {
	update_option( 'bw_test_approved_mode', $mode );
}

echo wp_json_encode( array( 'mode' => $mode ) ) . "\n";
