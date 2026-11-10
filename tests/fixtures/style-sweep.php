<?php
/**
 * Test fixture — NOT part of the theme.
 *
 * Installs and removes a must-use plugin that drops every stylesheet from the
 * front end late in wp_enqueue_scripts, the way a client plugin that owns the
 * site's look does (Brodfest keeps only its own handles). The design system
 * reference page must still arrive with its styles.
 *
 * Usage: php style-sweep.php /absolute/path/to/wp-load.php <install|remove>
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$command = isset( $argv[2] ) ? $argv[2] : '';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// Without this, Site Protection wp_die()s the CLI process as an anonymous visitor.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;

/**
 * Path of the must-use plugin this fixture writes.
 *
 * @return string Absolute path.
 */
function blueworx_style_sweep_fixture_file() {
	return WPMU_PLUGIN_DIR . '/blueworx-test-style-sweep.php';
}

switch ( $command ) {
	case 'install':
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			wp_mkdir_p( WPMU_PLUGIN_DIR );
		}

		file_put_contents(
			blueworx_style_sweep_fixture_file(),
			"<?php\n"
			. "// Test fixture, installed and removed by tests/fixtures/style-sweep.php.\n"
			. "add_action( 'wp_enqueue_scripts', function () {\n"
			. "\tforeach ( wp_styles()->queue as \$handle ) {\n"
			. "\t\tif ( 'admin-bar' !== \$handle ) {\n"
			. "\t\t\twp_dequeue_style( \$handle );\n"
			. "\t\t}\n"
			. "\t}\n"
			. "}, 100 );\n"
		);
		echo 'installed';
		break;

	case 'remove':
		if ( is_file( blueworx_style_sweep_fixture_file() ) ) {
			unlink( blueworx_style_sweep_fixture_file() );
		}
		echo 'removed';
		break;

	default:
		fwrite( STDERR, "Usage: php style-sweep.php /path/to/wp-load.php <install|remove>\n" );
		exit( 1 );
}
