<?php
/**
 * Klaviyo alerts: when a failure is worth an email, what it says, who gets
 * it, and that the refused-key email goes once.
 *
 * Run with: php tests/php/klaviyo-alerts-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';

$GLOBALS['enabled'] = true;
$GLOBALS['mail']    = array();

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	return true;
}

function blueworx_feature_enabled( $key ) {
	return 'klaviyo' === $key && $GLOBALS['enabled'];
}

function _n( $single, $plural, $number, $domain = '' ) {
	return 1 === (int) $number ? $single : $plural;
}

function number_format_i18n( $number ) {
	return (string) $number;
}

function wp_date( $format, $time = null ) {
	return gmdate( $format, null === $time ? 1762300800 : $time );
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path;
}

function wp_specialchars_decode( $text, $quotes = 0 ) {
	return $text;
}

function wp_mail( $to, $subject, $message ) {
	$GLOBALS['mail'][] = array(
		'to'      => $to,
		'subject' => $subject,
		'message' => $message,
	);
	return true;
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/klaviyo/settings.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/alerts.php';

$GLOBALS['options']['admin_email'] = 'owner@example.com';
$GLOBALS['options']['blogname']    = 'Test Site';

// --- When a failure is worth an email ------------------------------------------

$failure = array(
	'time'   => 1762300800,
	'label'  => 'Submitted Form',
	'email'  => 'sam@example.com',
	'detail' => 'Klaviyo said 400: bad email',
);

check( 'nothing waiting, nothing to send', blueworx_klaviyo_alert_should_send( array(), 0, 1762300800 ), false );
check( 'the first failure is sent at once', blueworx_klaviyo_alert_should_send( array( $failure ), 0, 1762300800 ), true );
check( 'another within the hour waits', blueworx_klaviyo_alert_should_send( array( $failure ), 1762300800, 1762300800 + 1800 ), false );
check( 'and goes once the hour is up', blueworx_klaviyo_alert_should_send( array( $failure ), 1762300800, 1762300800 + 3600 ), true );

// --- What the email says ----------------------------------------------------------

$message = blueworx_klaviyo_alert_message( array( $failure ), 'Test Site', 'https://example.test/log' );
check( 'the subject names the site and the count', $message['subject'], '[Test Site] Klaviyo: 1 send failed' );
check( 'the body names what failed and why', false !== strpos( $message['body'], 'Submitted Form: sam@example.com — Klaviyo said 400: bad email' ), true );
check( 'and links to the log', false !== strpos( $message['body'], 'https://example.test/log' ), true );

$message = blueworx_klaviyo_alert_message( array_fill( 0, 60, $failure ), 'Test Site', '' );
check( 'many failures are counted', $message['subject'], '[Test Site] Klaviyo: 60 sends failed' );
check( 'and the list stops at fifty', false !== strpos( $message['body'], '10 more' ), true );

$key = blueworx_klaviyo_alert_key_message( 'Klaviyo said 401', 'Test Site', 'https://example.test/settings' );
check( 'the key email says what Klaviyo said', false !== strpos( $key['body'], 'Klaviyo said 401' ), true );
check( 'and where to fix it', false !== strpos( $key['body'], 'https://example.test/settings' ), true );

// --- Who gets it -------------------------------------------------------------------

check( 'the admin email by default', blueworx_klaviyo_alert_recipients(), array( 'owner@example.com' ) );
$GLOBALS['options']['blueworx_klaviyo_alert_email'] = 'marketing@example.com';
check( 'the screen\'s address when set', blueworx_klaviyo_alert_recipients(), array( 'marketing@example.com' ) );
$GLOBALS['filters']['blueworx_klaviyo_alert_recipients'] = static function ( $to ) {
	$to[] = 'dev@example.com';
	$to[] = 'not an email';
	return $to;
};
check( 'a filter can add people, and a bad address is dropped', blueworx_klaviyo_alert_recipients(), array( 'marketing@example.com', 'dev@example.com' ) );
unset( $GLOBALS['filters']['blueworx_klaviyo_alert_recipients'] );

// --- Failures, grouped by the hour ------------------------------------------------------

$GLOBALS['mail'] = array();
blueworx_klaviyo_alert_note_failure( 'Submitted Form', 'sam@example.com', 'Klaviyo said 400' );
check( 'the first failure is emailed at once', count( $GLOBALS['mail'] ), 1 );
check( 'to the address on the screen', $GLOBALS['mail'][0]['to'], 'marketing@example.com' );
check( 'and nothing is left waiting', $GLOBALS['options']['blueworx_klaviyo_alert_pending'], array() );

for ( $i = 0; $i < 10; $i++ ) {
	blueworx_klaviyo_alert_note_failure( 'Placed Order', 'c' . $i . '@example.com', 'Klaviyo said 503' );
}
check( 'ten more inside the hour send no more email', count( $GLOBALS['mail'] ), 1 );
check( 'but wait their turn', count( $GLOBALS['options']['blueworx_klaviyo_alert_pending'] ), 10 );

$GLOBALS['options']['blueworx_klaviyo_alert_last_sent'] = time() - 3601;
check( 'once the hour is up, the tick sends them', blueworx_klaviyo_alert_flush(), true );
check( 'as one email', count( $GLOBALS['mail'] ), 2 );
check( 'listing all ten', $GLOBALS['mail'][1]['subject'], '[Test Site] Klaviyo: 10 sends failed' );
check( 'with nothing to send after', blueworx_klaviyo_alert_flush(), false );

// --- The refused key, once ---------------------------------------------------------------

$GLOBALS['mail'] = array();
blueworx_klaviyo_alert_key_refused( 'Klaviyo said 401: Missing or invalid private key.' );
blueworx_klaviyo_alert_key_refused( 'Klaviyo said 401: Missing or invalid private key.' );
blueworx_klaviyo_alert_key_refused( 'Klaviyo said 401: Missing or invalid private key.' );
check( 'a refused key is emailed once however many jobs hit it', count( $GLOBALS['mail'] ), 1 );
check( 'with the subject that says so', $GLOBALS['mail'][0]['subject'], '[Test Site] Klaviyo is refusing the private API key' );

blueworx_klaviyo_alert_on_sent();
check( 'a send going through notes the time', $GLOBALS['options']['blueworx_klaviyo_last_sent_at'] > 0, true );
check( 'and clears the refusal', $GLOBALS['options']['blueworx_klaviyo_alert_key_refused'], '0' );

blueworx_klaviyo_alert_key_refused( 'Klaviyo said 401' );
check( 'so a fresh refusal is emailed again', count( $GLOBALS['mail'] ), 2 );

blueworx_klaviyo_alert_on_new_key();
check( 'a new key clears it too', $GLOBALS['options']['blueworx_klaviyo_alert_key_refused'], '0' );

finish();
