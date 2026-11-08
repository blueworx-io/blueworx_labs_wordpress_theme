<?php
/**
 * Klaviyo forms: which connections are accepted, how a form's answers are
 * found, and what one sign-up sends.
 *
 * Run with: php tests/php/klaviyo-forms-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';

$GLOBALS['enabled'] = true;
$GLOBALS['queued']  = array();

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

function sanitize_email( $email ) {
	return filter_var( (string) $email, FILTER_SANITIZE_EMAIL );
}

function sanitize_textarea_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_title( $value ) {
	return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' );
}

function esc_url_raw( $url ) {
	return (string) $url;
}

function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, (array) $args );
}

function wp_rand() {
	return 4;
}

function add_option( $key, $value, $deprecated = '', $autoload = 'yes' ) {
	$GLOBALS['options'][ $key ] = $value;
	$GLOBALS['queued'][]        = $value;
	return true;
}

function wp_schedule_single_event( $time, $hook, $args = array() ) {
	return true;
}

function _n( $single, $plural, $number, $domain = '' ) {
	return 1 === (int) $number ? $single : $plural;
}

function number_format_i18n( $number ) {
	return (string) $number;
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/klaviyo/settings.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/client.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/queue.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/api.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/forms.php';

// --- Saving connections -----------------------------------------------------

$clean = blueworx_klaviyo_clean_connections(
	array(
		array(
			'kind'    => 'elementor',
			'match'   => ' Stay up to date ',
			'list'    => 'Y5fFQA',
			'consent' => 'Keep me posted. <b>x</b>',
			'tag'     => 'Stay Up To Date!',
		),
		array(
			'kind'  => 'html',
			'match' => 'footer-signup',
			'list'  => '',
		),
		array(
			'kind'  => 'sureforms',
			'match' => '4773',
			'list'  => 'XRYaZh',
		),
		array(
			'kind'  => 'elementor',
			'match' => '',
			'list'  => 'ignored',
		),
		'not a row',
	)
);

check( 'an empty row is dropped quietly', count( $clean['values'] ), 3 );
check( 'nothing refused', $clean['refused'], array() );
check( 'the form name is trimmed', $clean['values'][0]['match'], 'Stay up to date' );
check( 'the tag becomes a slug', $clean['values'][0]['tag'], 'stay-up-to-date' );
check( 'the consent wording loses its markup', $clean['values'][0]['consent'], 'Keep me posted. x' );
check( 'a tag is made from the form when none is given', $clean['values'][1]['tag'], 'footer-signup' );
check( 'an empty list means identify only', $clean['values'][1]['list'], '' );

$bad = blueworx_klaviyo_clean_connections(
	array(
		array(
			'kind'  => 'sureforms',
			'match' => 'abc',
		),
		array(
			'kind'  => 'html',
			'match' => 'has spaces',
		),
		array(
			'kind'  => 'elementor',
			'match' => 'Form',
			'list'  => 'not a list!',
		),
		array(
			'kind'  => 'nonsense',
			'match' => 'Form 2',
		),
	)
);

check( 'a SureForms ID must be a number', $bad['refused'][0], 'SureForms form ID "abc" should be a number' );
check( 'an HTML id must be an id', $bad['refused'][1], 'HTML form id "has spaces" should be letters, digits, dashes and underscores' );
check( 'a list must look like a list ID', $bad['refused'][2], 'Klaviyo list for "Form"' );
check( 'an unknown kind falls back to Elementor', $bad['values'][0]['kind'], 'elementor' );

// --- Finding the answers ----------------------------------------------------

$values = blueworx_klaviyo_form_values(
	array(
		array(
			'name'  => 'field_1',
			'label' => 'Your name',
			'value' => 'Sam Jones',
		),
		array(
			'name'  => 'field_2',
			'label' => 'Email address',
			'value' => 'Sam@Example.com',
		),
		array(
			'name'    => 'field_3',
			'label'   => 'Send me news',
			'value'   => 'on',
			'checked' => true,
		),
	)
);

check( 'the email is found by its label', $values['email'], 'Sam@Example.com' );
check( 'a single name box gives the first name', $values['first_name'], 'Sam' );
check( 'and the last name', $values['last_name'], 'Jones' );
check( 'a ticked marketing box is yes', $values['marketing'], true );

$values = blueworx_klaviyo_form_values(
	array(
		array(
			'name'  => 'first_name',
			'label' => '',
			'value' => 'Sam',
		),
		array(
			'name'  => 'surname',
			'label' => '',
			'value' => 'Jones',
		),
		array(
			'name'  => 'contact',
			'label' => 'How can we reach you?',
			'value' => 'sam@example.com',
		),
		array(
			'name'    => 'marketing_opt_in',
			'label'   => 'Marketing',
			'value'   => '',
			'checked' => false,
		),
	)
);

check( 'names are found by field name too', $values['first_name'] . ' ' . $values['last_name'], 'Sam Jones' );
check( 'an email is found by its value when no label says email', $values['email'], 'sam@example.com' );
check( 'an unticked marketing box is no', $values['marketing'], false );

$values = blueworx_klaviyo_form_values(
	array(
		array(
			'name'  => 'email',
			'label' => 'Email',
			'value' => 'sam@example.com',
		),
	)
);

check( 'a form with no marketing box leaves the question open', $values['marketing'], null );

$values = blueworx_klaviyo_form_values(
	array(
		array(
			'name'  => 'email',
			'label' => 'Email',
			'value' => 'not an email',
		),
	)
);

check( 'something that is not an email is not one', $values['email'], '' );

// --- What a sign-up sends ---------------------------------------------------

$connection = array(
	'kind'    => 'elementor',
	'match'   => 'Stay up to date',
	'list'    => 'Y5fFQA',
	'consent' => 'Keep me posted.',
	'tag'     => 'newsletter',
);

$plan = blueworx_klaviyo_form_plan(
	$connection,
	array(
		'email'      => 'Sam@Example.com',
		'first_name' => 'Sam',
		'last_name'  => 'Jones',
		'marketing'  => null,
	),
	'https://example.test/about/'
);

check( 'the log calls it by the tag', $plan['label'], 'Form: newsletter' );
check( 'names are filled, never overwritten', $plan['fill']['first_name'], 'Sam' );
check( 'the tag is the sign-up source', $plan['profile']['signup_source'], 'newsletter' );
check( 'the page is recorded', $plan['profile']['signup_page'], 'https://example.test/about/' );
check( 'the consent wording travels under a name Klaviyo keeps', $plan['profile']['signup_consent_wording'], 'Keep me posted.' );
check( 'no marketing box means the submission is the consent', $plan['profile']['marketing_consent'], true );
check( 'and they go on the list', $plan['list'], 'Y5fFQA' );
check( 'every sign-up is an event', $plan['event']['name'], 'Submitted Form' );
check( 'that names the form', $plan['event']['properties']['form'], 'newsletter' );

$plan = blueworx_klaviyo_form_plan(
	$connection,
	array(
		'email'     => 'sam@example.com',
		'marketing' => false,
	),
	''
);

check( 'unticked means identified but not consented', $plan['profile']['marketing_consent'], false );
check( 'and never subscribed', $plan['list'], '' );

check( 'no email means nothing to send', blueworx_klaviyo_form_plan( $connection, array( 'email' => '' ), '' ), null );

// --- A submission end to end ------------------------------------------------

$GLOBALS['options']['blueworx_klaviyo_private_key']  = 'pk_0123456789abcdefghijklmnop';
$GLOBALS['options'][ BLUEWORX_KLAVIYO_FORMS_OPTION ] = array( $connection );

$fields = array(
	array(
		'name'  => 'email',
		'label' => 'Email',
		'value' => 'sam@example.com',
	),
);

check( 'a connected form queues a send', blueworx_klaviyo_form_submitted( 'elementor', 'stay up to date', $fields, 'https://example.test/' ), true );
check( 'through the forms source', $GLOBALS['queued'][0]['source'], 'forms' );
check( 'the match ignores case', $GLOBALS['queued'][0]['plans'][0]['list'], 'Y5fFQA' );
check( 'a form that is not connected sends nothing', blueworx_klaviyo_form_submitted( 'elementor', 'Other form', $fields, '' ), false );
check( 'the wrong kind of form sends nothing', blueworx_klaviyo_form_submitted( 'html', 'Stay up to date', $fields, '' ), false );

$GLOBALS['enabled'] = false;
check( 'nothing while the function is off', blueworx_klaviyo_form_submitted( 'elementor', 'Stay up to date', $fields, '' ), false );
$GLOBALS['enabled'] = true;

// --- SureForms' field names -------------------------------------------------

$fields = blueworx_klaviyo_sureforms_fields(
	array(
		'srfm-email-3b3cf3ac-lbl-RW1haWw-email'  => 'sam@example.com',
		'srfm-input-3b3cf3ac-lbl-Rmlyc3QgTmFtZQ-first-name' => 'Sam',
		'srfm-checkbox-3b3cf3ac-lbl-TmV3cw-news' => 'on',
		'_wpnonce'                               => 'ignored',
	)
);

check( 'only SureForms fields are read', count( $fields ), 3 );
check( 'the label is decoded', $fields[1]['label'], 'First Name' );
check( 'a checkbox is a tick box', $fields[2]['checked'], true );
check( 'a text field is not', $fields[0]['checked'], null );

$values = blueworx_klaviyo_form_values( $fields );
check( 'and the answers are found', $values['email'] . '|' . $values['first_name'] . '|' . var_export( $values['marketing'], true ), 'sam@example.com|Sam|true' );

finish();
