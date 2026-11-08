<?php
/**
 * Cookie consent: the pure parts.
 *
 * What switching on has to do, the house defaults, how a tracking script is
 * held back for consent, how the screen's fields are cleaned, and what the
 * status card says.
 *
 * Run with: php tests/php/consent-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable WordPress.WP.EnqueuedResources -- Tag strings under test, never printed.

require __DIR__ . '/stubs.php';

define( 'WP_PLUGIN_DIR', __DIR__ );

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $url ) {
	return str_replace( '&', '&#038;', (string) $url );
}
function sanitize_textarea_field( $text ) {
	return trim( strip_tags( (string) $text ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
}
function is_multisite() {
	return false;
}

require __DIR__ . '/../../includes/consent.php';

/**
 * A yes-or-no check.
 *
 * @param string $label What is being checked.
 * @param bool   $cond  Whether it held.
 * @return void
 */
function ok( $label, $cond ) {
	check( $label, (bool) $cond, true );
}

// What switching on has to do.
check( 'running already means nothing to do', 'nothing', blueworx_consent_plan( true, true, true ) );
check( 'installed but off means switch it on', 'activate', blueworx_consent_plan( true, false, false ) );
check( 'missing and allowed means install', 'install', blueworx_consent_plan( false, false, true ) );
check( 'missing and not allowed is said plainly', 'no-permission', blueworx_consent_plan( false, false, false ) );

// The house defaults.
$defaults = blueworx_consent_house_defaults( 12, 7 );
check( 'ask first', 'opt-in', $defaults['consent_model'] );
ok( 'block until agreed', true === $defaults['blocking_enabled'] && true === $defaults['banner_enabled'] );
ok( 'the scanner stays off', false === $defaults['auto_scan_enabled'] );
check( 'the banner links to the cookies page', 12, $defaults['cookie_policy_page_id'] );
check( 'and the privacy page', 7, $defaults['privacy_policy_page_id'] );

// Holding a script back, the way SureCookie does it.
check(
	'an inline script',
	'<script type="text/plain" data-surecookie-category="analytics" data-surecookie-name="Google Tag Manager" data-surecookie-original-type="none">',
	blueworx_consent_gate_script_tag( '<script>', 'analytics', 'Google Tag Manager' )
);
check(
	'a script with an address keeps its other attributes and moves the address',
	'<script type="text/plain" data-surecookie-category="analytics" data-surecookie-name="Google Analytics" data-surecookie-original-type="none" data-surecookie-src="https://www.googletagmanager.com/gtag/js?id=G-1" async>',
	blueworx_consent_gate_script_tag( '<script async src="https://www.googletagmanager.com/gtag/js?id=G-1">', 'analytics', 'Google Analytics' )
);
check(
	'marketing is marketing, anything else is analytics',
	'marketing',
	preg_match( '/data-surecookie-category="([a-z]+)"/', blueworx_consent_gate_script_tag( '<script>', 'marketing', 'Meta Pixel' ), $m ) ? $m[1] : ''
);
check( 'an unknown category is analytics', 'analytics', preg_match( '/data-surecookie-category="([a-z]+)"/', blueworx_consent_gate_script_tag( '<script>', 'other', 'X' ), $m ) ? $m[1] : '' );
check( 'a tag already held back is left alone', '<script type="text/plain" data-surecookie-category="marketing">', blueworx_consent_gate_script_tag( '<script type="text/plain" data-surecookie-category="marketing">', 'analytics', 'X' ) );
check( 'something that is not an opening script tag is left alone', '<noscript>', blueworx_consent_gate_script_tag( '<noscript>', 'analytics', 'X' ) );
ok( 'a type attribute is replaced, not doubled', 1 === substr_count( blueworx_consent_gate_script_tag( '<script type="text/javascript">', 'analytics', 'X' ), ' type=' ) );

// Cleaning the screen.
$clean = blueworx_consent_clean_settings(
	array(
		'blueworx_consent_message_heading'     => '  Cookies <b>here</b> ',
		'blueworx_consent_message_description' => "We use them.\nSecond line.",
		'blueworx_consent_banner_position'     => 'sideways',
	)
);
check( 'the heading is plain text', 'Cookies here', $clean['message_heading'] );
check( 'the message keeps its lines', "We use them.\nSecond line.", $clean['message_description'] );
check( 'an unknown position falls back to the first', 'bottom', $clean['banner_position'] );
check( 'an unticked box is false', false, $clean['floating_widget_enabled'] );
check( 'a ticked box is true', true, blueworx_consent_clean_settings( array( 'blueworx_consent_floating_widget_enabled' => '1' ) )['floating_widget_enabled'] );
check( 'top is allowed', 'top', blueworx_consent_clean_settings( array( 'blueworx_consent_banner_position' => 'top' ) )['banner_position'] );

// The status card.
check( 'off', 'info', blueworx_consent_status( false, false, false, null )['tone'] );
check( 'running', 'success', blueworx_consent_status( true, true, true, null )['tone'] );
$failed = blueworx_consent_status(
	true,
	false,
	false,
	array(
		'status'  => 'failed',
		'message' => 'No connection.',
		'time'    => 1,
	)
);
check( 'a failed install is a danger', 'danger', $failed['tone'] );
check( 'and says what went wrong', 'No connection.', $failed['text'] );
check( 'installed but off is a warning', 'warning', blueworx_consent_status( true, false, true, null )['tone'] );
ok( 'and says so', false !== strpos( blueworx_consent_status( true, false, true, null )['title'], 'switched off' ) );
ok( 'not installed says so', false !== strpos( blueworx_consent_status( true, false, false, null )['title'], 'not installed' ) );

finish();
