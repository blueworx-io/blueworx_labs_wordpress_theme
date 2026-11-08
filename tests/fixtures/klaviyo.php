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
 *   refuse <n>            The next n requests to Klaviyo answer 401, as a bad key would.
 *   mail                  Prints the emails the site has tried to send.
 *   alerts <json>         Sets alert state: last_sent (timestamp).
 *   tick                  Runs the five-minute tick.
 *   profiles <json>       The profiles the double holds, keyed by email.
 *   identify <json>       Calls blueworx_klaviyo_identify( email, properties, args ).
 *   track <json>          Calls blueworx_klaviyo_track( event, email, properties, args ).
 *   subscribe <json>      Calls blueworx_klaviyo_subscribe( email, list, args ).
 *   drain                 Runs the queue and prints {calls, jobs, log, profiles}.
 *   due                   Makes every waiting job due now.
 *   state                 Prints {calls, jobs, log, profiles} without running.
 *   forms <json>          Saves the form connections and prints them back.
 *   elementor <json>      Fires Elementor's form hook: { name, fields }.
 *   sureforms <json>      Fires SureForms' hook: { form_id, data }.
 *   html <json>           Posts to the HTML form address: { form, page, fields }.
 *   records <json>        The SureCart records the double serves, by type and id.
 *   store <json>          Saves store settings: secret, list, box, test_mode.
 *   checkout <json>       A checkout SureCart's form has just saved; prints what happened to it.
 *   user <json>           Makes a user (wp_insert_user fields) and prints {id}.
 *   login <id>            Fires the sign-in hook for that user.
 *   role <json>           Sets a user's role: { id, role }.
 *   public <key>          Saves the public key; an empty key clears it.
 *   product               Creates a stand-in SureCart product and prints {id, url}.
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

// Deleting a user is an admin-screen function WordPress does not load on its own.
require_once ABSPATH . 'wp-admin/includes/user.php';

/**
 * Clears the queue, the log, the double's memory and the recorded calls.
 *
 * @return void
 */
function klaviyo_fixture_reset() {
	global $wpdb;

	foreach ( array( 'job_', 'double_call_', 'double_mail_', 'seen_', 'sub_' ) as $family ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'blueworx_klaviyo_' . $family ) . '%' ) );
	}

	delete_option( 'blueworx_klaviyo_log' );
	delete_option( 'blueworx_klaviyo_double_profiles' );
	delete_option( 'blueworx_klaviyo_double_fail' );
	delete_option( 'blueworx_klaviyo_double_refuse' );
	foreach ( array( 'alert_pending', 'alert_last_sent', 'alert_key_refused', 'last_sent_at' ) as $setting ) {
		delete_option( 'blueworx_klaviyo_' . $setting );
	}
	delete_option( 'blueworx_klaviyo_store_last_refused' );
	delete_transient( 'blueworx_klaviyo_pruned' );
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

	case 'refuse':
		update_option( 'blueworx_klaviyo_double_refuse', (int) $arg, 'no' );
		klaviyo_fixture_print( 'ok' );
		break;

	case 'mail':
		wp_cache_flush();
		$rows = $GLOBALS['wpdb']->get_col( $GLOBALS['wpdb']->prepare( "SELECT option_value FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE %s ORDER BY option_name ASC", $GLOBALS['wpdb']->esc_like( 'blueworx_klaviyo_double_mail_' ) . '%' ) );
		klaviyo_fixture_print(
			array_map(
				static function ( $row ) {
					return json_decode( maybe_unserialize( $row ), true );
				},
				$rows
			)
		);
		break;

	case 'alerts':
		if ( isset( $json['last_sent'] ) ) {
			update_option( 'blueworx_klaviyo_alert_last_sent', (int) $json['last_sent'], 'no' );
		}
		klaviyo_fixture_print( 'ok' );
		break;

	case 'tick':
		delete_transient( 'blueworx_klaviyo_lock' );
		do_action( 'blueworx_klaviyo_tick' );
		klaviyo_fixture_print( klaviyo_fixture_state() );
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

	case 'forms':
		update_option( 'blueworx_klaviyo_forms', $json, 'no' );
		klaviyo_fixture_print( blueworx_klaviyo_form_connections() );
		break;

	case 'elementor':
		// Takes the form's name and its fields keyed by id, each with title, value and type.
		$_POST['referrer'] = 'https://example.test/elementor-page/';
		do_action( 'elementor_pro/forms/new_record', new Klaviyo_Double_Record( $json['name'], $json['fields'] ), null ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Elementor's own hook name.
		klaviyo_fixture_print( 'fired' );
		break;

	case 'sureforms':
		// Takes the form's id and the posted data keyed by SureForms' field names.
		do_action( 'srfm_submission_accepted', 1, (int) $json['form_id'], $json['data'] );
		klaviyo_fixture_print( 'fired' );
		break;

	case 'html':
		// Takes the form's id, the page, and the fields with name, label, value and checked.
		$request = new WP_REST_Request( 'POST', '/blueworx/v1/klaviyo/form' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $json ) );
		$response = rest_do_request( $request );
		klaviyo_fixture_print(
			array(
				'status' => $response->get_status(),
				'data'   => $response->get_data(),
			)
		);
		break;

	case 'records':
		update_option( 'blueworx_klaviyo_double_records', $json, 'no' );
		klaviyo_fixture_print( 'ok' );
		break;

	case 'store':
		// Takes any of: secret, list, box, test_mode.
		foreach ( array(
			'secret'    => 'store_webhook_secret',
			'list'      => 'store_list',
			'box'       => 'store_checkout_box',
			'test_mode' => 'store_test_mode',
		) as $key => $setting ) {
			if ( array_key_exists( $key, $json ) ) {
				update_option( 'blueworx_klaviyo_' . $setting, (string) $json[ $key ], 'no' );
			}
		}
		klaviyo_fixture_print( 'ok' );
		break;

	case 'checkout':
		// Takes a checkout as SureCart's form saves it: id, status, email, line_items, total_amount.
		klaviyo_fixture_print( blueworx_klaviyo_checkout_noticed( $json ) );
		break;

	case 'user':
		// Takes wp_insert_user's fields. Logins beginning klaviyo- are removed by cleanup.
		$existing = get_user_by( 'login', $json['user_login'] );
		if ( $existing ) {
			wp_delete_user( $existing->ID );
		}
		if ( empty( $json['user_pass'] ) ) {
			$json['user_pass'] = 'wptest-klaviyo-pw';
		}
		$user_id = wp_insert_user( $json );
		klaviyo_fixture_print( array( 'id' => is_wp_error( $user_id ) ? 0 : $user_id ) );
		break;

	case 'login':
		$user = get_userdata( (int) $arg );
		delete_transient( 'blueworx_klaviyo_login_' . (int) $arg );
		do_action( 'wp_login', $user ? $user->user_login : '', $user );
		klaviyo_fixture_print( 'fired' );
		break;

	case 'role':
		$user = get_userdata( (int) $json['id'] );
		if ( $user ) {
			$user->set_role( $json['role'] );
		}
		klaviyo_fixture_print( 'ok' );
		break;

	case 'public':
		update_option( 'blueworx_klaviyo_public_key', (string) $arg, 'no' );
		klaviyo_fixture_print( 'ok' );
		break;

	case 'product':
		$existing = get_page_by_path( 'test-racket', OBJECT, 'sc_product' );
		$post_id  = $existing ? $existing->ID : wp_insert_post(
			array(
				'post_type'   => 'sc_product',
				'post_status' => 'publish',
				'post_name'   => 'test-racket',
				'post_title'  => 'Test racket',
			)
		);
		flush_rewrite_rules();
		klaviyo_fixture_print(
			array(
				'id'  => $post_id,
				'url' => get_permalink( $post_id ),
			)
		);
		break;

	case 'off':
		update_option( 'blueworx_feature_klaviyo', '0' );
		klaviyo_fixture_print( 'ok' );
		break;

	case 'cleanup':
		klaviyo_fixture_reset();
		delete_option( 'blueworx_feature_klaviyo' );
		delete_option( 'blueworx_klaviyo_forms' );
		delete_option( 'blueworx_klaviyo_double_records' );
		foreach ( array( 'store_webhook_secret', 'store_list', 'store_checkout_box', 'store_test_mode' ) as $setting ) {
			delete_option( 'blueworx_klaviyo_' . $setting );
		}
		delete_option( 'blueworx_klaviyo_private_key' );
		delete_option( 'blueworx_klaviyo_public_key' );
		delete_option( 'blueworx_klaviyo_users_sync' );
		delete_option( 'blueworx_klaviyo_alert_email' );
		foreach ( get_users(
			array(
				'search'         => 'klaviyo-*',
				'search_columns' => array( 'user_login' ),
			)
		) as $made ) {
			wp_delete_user( $made->ID );
		}
		wp_clear_scheduled_hook( 'blueworx_klaviyo_tick' );
		$product = get_page_by_path( 'test-racket', OBJECT, 'sc_product' );
		if ( $product ) {
			wp_delete_post( $product->ID, true );
		}
		if ( is_file( $double ) ) {
			unlink( $double );
		}
		klaviyo_fixture_print( 'ok' );
		break;

	default:
		fwrite( STDERR, "Unknown command: {$command}\n" );
		exit( 1 );
}
