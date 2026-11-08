<?php
/**
 * Klaviyo connection: the switch and the keys.
 *
 * Two keys, and nothing else that is about any one site. The private key talks
 * to Klaviyo from the server and never reaches a page in full; the public key
 * is the site's ID for Klaviyo's own script and is fine to show. Either can be
 * set in wp-config.php, which then wins over the settings screen.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Start of every option the connection stores.
 */
const BLUEWORX_KLAVIYO_OPTION_PREFIX = 'blueworx_klaviyo_';

/**
 * Whether the connection is switched on under Enhancements.
 *
 * @return bool
 */
function blueworx_klaviyo_enabled() {
	return blueworx_feature_enabled( 'klaviyo' );
}

/**
 * Reads one of the connection's settings.
 *
 * @param string $name    Setting name, without the prefix.
 * @param mixed  $fallback What to return when it has never been saved.
 * @return mixed
 */
function blueworx_klaviyo_setting( $name, $fallback = '' ) {
	return get_option( BLUEWORX_KLAVIYO_OPTION_PREFIX . $name, $fallback );
}

/**
 * Saves one of the connection's settings. Never autoloaded: nothing here is
 * needed on an ordinary page view.
 *
 * @param string $name  Setting name, without the prefix.
 * @param mixed  $value Value.
 * @return void
 */
function blueworx_klaviyo_update_setting( $name, $value ) {
	update_option( BLUEWORX_KLAVIYO_OPTION_PREFIX . $name, $value, 'no' );
}

/**
 * The private API key: wp-config.php wins over the settings screen.
 *
 * @return string
 */
function blueworx_klaviyo_private_key() {
	if ( defined( 'BLUEWORX_KLAVIYO_PRIVATE_KEY' ) && BLUEWORX_KLAVIYO_PRIVATE_KEY ) {
		return (string) BLUEWORX_KLAVIYO_PRIVATE_KEY;
	}

	return (string) blueworx_klaviyo_setting( 'private_key', '' );
}

/**
 * The public API key, Klaviyo's six-character site ID. wp-config.php wins.
 *
 * @return string
 */
function blueworx_klaviyo_public_key() {
	if ( defined( 'BLUEWORX_KLAVIYO_PUBLIC_KEY' ) && BLUEWORX_KLAVIYO_PUBLIC_KEY ) {
		return (string) BLUEWORX_KLAVIYO_PUBLIC_KEY;
	}

	return (string) blueworx_klaviyo_setting( 'public_key', '' );
}

/**
 * Whether the connection can send: switched on, with a private key.
 *
 * @return bool
 */
function blueworx_klaviyo_connected() {
	return blueworx_klaviyo_enabled() && '' !== blueworx_klaviyo_private_key();
}

/**
 * Tidies a typed public key and checks it looks like one.
 *
 * Klaviyo's public keys are six letters and digits. Stray spaces are forgiven;
 * anything else is refused rather than corrected, so a pasted mistake never
 * becomes a site ID that silently tracks nothing.
 *
 * @param string $raw What was typed.
 * @return string|false The key, an empty string for an empty box, or false when refused.
 */
function blueworx_klaviyo_clean_public_key( $raw ) {
	$key = trim( (string) $raw );

	if ( '' === $key ) {
		return '';
	}

	return preg_match( '/^[A-Za-z0-9]{6}$/', $key ) ? $key : false;
}

/**
 * Tidies a typed private key and checks it looks like one.
 *
 * Klaviyo's private keys start with pk_. An empty box means "keep what is
 * saved", since the box never shows the saved key back.
 *
 * @param string $raw What was typed.
 * @return string|false The key, an empty string for an empty box, or false when refused.
 */
function blueworx_klaviyo_clean_private_key( $raw ) {
	$key = trim( (string) $raw );

	if ( '' === $key ) {
		return '';
	}

	return preg_match( '/^pk_[A-Za-z0-9]{20,}$/', $key ) ? $key : false;
}

/**
 * Shows the end of a secret, enough to tell which one is saved.
 *
 * @param string $secret The secret.
 * @return string
 */
function blueworx_klaviyo_mask( $secret ) {
	$secret = (string) $secret;

	return '' === $secret ? '' : str_repeat( '•', 8 ) . substr( $secret, -4 );
}

/**
 * Stops the background run when this site switches away from the theme.
 *
 * The settings stay, as every other function's do, so switching back picks
 * up where it left off.
 *
 * @return void
 */
function blueworx_klaviyo_on_deactivate() {
	wp_clear_scheduled_hook( 'blueworx_klaviyo_tick' );
}

/**
 * Forgets everything the connection stored on this site: keys, queue, log.
 *
 * @return void
 */
function blueworx_klaviyo_forget_all() {
	global $wpdb;

	wp_clear_scheduled_hook( 'blueworx_klaviyo_tick' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A family of rows no API can list, removed together.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( BLUEWORX_KLAVIYO_OPTION_PREFIX ) . '%' ) );

	delete_transient( 'blueworx_klaviyo_lock' );
	delete_transient( 'blueworx_klaviyo_lists' );
	wp_cache_flush();
}
