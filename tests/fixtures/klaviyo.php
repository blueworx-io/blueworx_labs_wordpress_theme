<?php
/**
 * Test fixture — NOT part of the theme.
 *
 * Sets a test site up for the Klaviyo spec, calls the connection the way a
 * site's plugin would, runs the queue and reports what was sent. Installs
 * tests/fixtures/klaviyo-double.php first, so nothing can reach the real
 * Klaviyo.
 *
 * Usage: php klaviyo.php /absolute/path/to/wp-load.php <command> [args]
 *
 *   setup                 Installs the test double, switches the function on
 *                         and saves a key.
 *   double                Installs the test double only, for the screen test.
 *   reset                 Clears the queue, log, double's memory and calls.
 *   fail <n>              The next n requests to Klaviyo answer 503.
 *   profiles <json>       The profiles the double holds, keyed by email.
 *   identify <json>       Calls blueworx_klaviyo_identify( email, properties, args ).
 *   track <json>          Calls blueworx_klaviyo_track( event, email, properties, args ).
 *   subscribe <json>      Calls blueworx_klaviyo_subscribe( email, list, args ).
 *   drain                 Runs the queue and prints {calls, jobs, log, profiles}.
 *   due                   Makes every waiting job due now.
 *   state                 Prints {calls, jobs, log, profiles} without running.
 *   off                   Switches the function off.
 *   cleanup               Removes everything, the test double included.
 *
 * @package BlueWorxLabsTests
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- A fixture: it reads the tables directly on purpose.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.WP.AlternativeFunctions -- A CLI fixture writing its own files and stderr.
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Reports a bad path on the command line.

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$command = isset( $argv[2] ) ? $argv[2] : '';
$arg     = isset( $argv[3] ) ? $argv[3] : '';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// The test double has to be in place before WordPress loads, so a queue run
// started by this very process cannot reach anything real.
$mu_plugins = dirname( $wp_load ) . '/wp-content/mu-plugins';
$double     = $mu_plugins . '/blueworx-klaviyo-double.php';

if ( 'setup' === $command || 'double' === $command ) {
	if ( ! is_dir( $mu_plugins ) ) {
		mkdir( $mu_plugins, 0777, true );
	}

	copy( __DIR__ . '/klaviyo-double.php', $double );
}

// See tests/fixtures/impostor-support-user.php for why this comes before wp-load.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;

/**
 * Clears the queue, the log, the double's memory and the recorded calls.
 *
 * @return void
 */
function klaviyo_fixture_reset() {
	global $wpdb;

	foreach ( array( 'job_', 'double_call_' ) as $family ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'blueworx_klaviyo_' . $family ) . '%' ) );
	}

	delete_option( 'blueworx_klaviyo_log' );
	delete_option( 'blueworx_klaviyo_double_profiles' );
	delete_option( 'blueworx_klaviyo_double_fail' );
	delete_transient( 'blueworx_klaviyo_lock' );
	delete_transient( 'blueworx_klaviyo_lists' );
	delete_transient( 'blueworx_klaviyo_notice' );
	wp_cache_flush();
}

/**
 * What the double has been asked, oldest first.
 *
 * @return array
 */
function klaviyo_fixture_calls() {
	global $wpdb;

	$rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name ASC", $wpdb->esc_like( 'blueworx_klaviyo_double_call_' ) . '%' ) );

	return array_map(
		static function ( $row ) {
			return json_decode( maybe_unserialize( $row ), true );
		},
		$rows
	);
}

/**
 * Everything the spec reads: what was asked of Klaviyo, what is still waiting,
 * what the log says and what the double now holds.
 *
 * @return array
 */
function klaviyo_fixture_state() {
	wp_cache_flush();

	return array(
		'calls'    => klaviyo_fixture_calls(),
		'jobs'     => array_values( blueworx_klaviyo_waiting_jobs() ),
		'log'      => blueworx_klaviyo_log_lines(),
		'profiles' => get_option( 'blueworx_klaviyo_double_profiles', array() ),
	);
}

/**
 * Prints one answer as JSON on the last line.
 *
 * @param mixed $answer The answer.
 * @return void
 */
function klaviyo_fixture_print( $answer ) {
	echo wp_json_encode( $answer ) . "\n";
}

$json = '' !== $arg ? json_decode( $arg, true ) : array();
$json = is_array( $json ) ? $json : array();

switch ( $command ) {
	case 'setup':
		klaviyo_fixture_reset();
		update_option( 'blueworx_feature_klaviyo', '1' );
		update_option( 'blueworx_klaviyo_private_key', 'pk_fixture0123456789abcdefghij', 'no' );
		update_option( 'blueworx_klaviyo_public_key', 'AbC123', 'no' );
		klaviyo_fixture_print( 'ok' );
		break;

	case 'double':
	case 'reset':
		klaviyo_fixture_reset();
		klaviyo_fixture_print( 'ok' );
		break;

	case 'fail':
		update_option( 'blueworx_klaviyo_double_fail', (int) $arg, 'no' );
		klaviyo_fixture_print( 'ok' );
		break;

	case 'profiles':
		update_option( 'blueworx_klaviyo_double_profiles', $json, 'no' );
		klaviyo_fixture_print( 'ok' );
		break;

	case 'identify':
		klaviyo_fixture_print( blueworx_klaviyo_identify( $json['email'], isset( $json['properties'] ) ? $json['properties'] : array(), isset( $json['args'] ) ? $json['args'] : array() ) );
		break;

	case 'track':
		klaviyo_fixture_print( blueworx_klaviyo_track( $json['event'], $json['email'], isset( $json['properties'] ) ? $json['properties'] : array(), isset( $json['args'] ) ? $json['args'] : array() ) );
		break;

	case 'subscribe':
		klaviyo_fixture_print( blueworx_klaviyo_subscribe( $json['email'], $json['list'], isset( $json['args'] ) ? $json['args'] : array() ) );
		break;

	case 'due':
		foreach ( blueworx_klaviyo_waiting_jobs() as $name => $job ) {
			$job['next_at'] = 0;
			update_option( $name, $job, 'no' );
		}
		klaviyo_fixture_print( 'ok' );
		break;

	case 'drain':
		delete_transient( 'blueworx_klaviyo_lock' );
		blueworx_klaviyo_run_queue();
		klaviyo_fixture_print( klaviyo_fixture_state() );
		break;

	case 'state':
		klaviyo_fixture_print( klaviyo_fixture_state() );
		break;

	case 'off':
		update_option( 'blueworx_feature_klaviyo', '0' );
		klaviyo_fixture_print( 'ok' );
		break;

	case 'cleanup':
		klaviyo_fixture_reset();
		delete_option( 'blueworx_feature_klaviyo' );
		delete_option( 'blueworx_klaviyo_private_key' );
		delete_option( 'blueworx_klaviyo_public_key' );
		wp_clear_scheduled_hook( 'blueworx_klaviyo_tick' );
		if ( is_file( $double ) ) {
			unlink( $double );
		}
		klaviyo_fixture_print( 'ok' );
		break;

	default:
		fwrite( STDERR, "Unknown command: {$command}\n" );
		exit( 1 );
}
