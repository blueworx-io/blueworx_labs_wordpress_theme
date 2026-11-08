<?php
/**
 * Opens or closes registration, for tests/login-screens.spec.js.
 *
 * Usage: php tests/fixtures/registration.php <path to wp-load.php> <on|off|state>
 *
 * WordPress's settings endpoint does not carry "anyone can register", so the
 * test flips the option here and prints the previous value to put back.
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.

define( 'WP_USE_THEMES', false );

$wp_load = isset( $argv[1] ) ? (string) $argv[1] : '';
$command = isset( $argv[2] ) ? (string) $argv[2] : 'state';

if ( '' === $wp_load || ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "Usage: php tests/fixtures/registration.php <wp-load.php> <on|off|state>\n" );
	exit( 1 );
}

require_once $wp_load;

$before = (int) get_option( 'users_can_register', 0 );

if ( 'on' === $command ) {
	update_option( 'users_can_register', 1 );
} elseif ( 'off' === $command ) {
	update_option( 'users_can_register', 0 );
}

echo $before, "\n";
