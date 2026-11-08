<?php
/**
 * Test fixture — NOT part of the theme.
 *
 * Prints what is really stored on an account, for tests/view-as-role.spec.js:
 * the roles and the user level, straight from the database. A preview only
 * changes what the signed-in request sees, so this is the place to prove the
 * account itself was never touched.
 *
 * Usage: php view-as-role.php /absolute/path/to/wp-load.php stored <login>
 *
 * @package BlueWorxLabsTests
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : null;
$command = isset( $argv[2] ) ? $argv[2] : '';
$login   = isset( $argv[3] ) ? $argv[3] : '';

if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, 'wp-load.php not found: ' . var_export( $wp_load, true ) . "\n" );
	exit( 1 );
}

// See tests/fixtures/clear-view-as.php: Site Protection would otherwise stop
// this CLI process as an anonymous visitor.
define( 'WP_CLI', true );
define( 'WP_USE_THEMES', false );
require $wp_load;

if ( 'stored' !== $command ) {
	fwrite( STDERR, "Unknown command.\n" );
	exit( 1 );
}

$user = get_user_by( 'login', $login );

if ( ! $user ) {
	fwrite( STDERR, "No such user.\n" );
	exit( 1 );
}

// Read past every cache and filter: the row is the truth.
$read = static function ( $key ) use ( $wpdb, $user ) {
	return $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s", $user->ID, $key ) );
};

echo wp_json_encode(
	array(
		'caps'  => maybe_unserialize( $read( $wpdb->get_blog_prefix() . 'capabilities' ) ),
		'level' => (int) $read( $wpdb->get_blog_prefix() . 'user_level' ),
	)
);
