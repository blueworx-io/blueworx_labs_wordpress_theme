<?php
/**
 * Klaviyo connection: the rules a marketing team depends on.
 *
 * What gets written to a profile, what counts as an event, which keys are
 * accepted and when a failed send is worth another go are all decided in pure
 * functions, so they can be pinned here without a site or a Klaviyo account.
 *
 * Run with: php tests/php/klaviyo-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';

$GLOBALS['enabled']  = true;
$GLOBALS['queued']   = array();
$GLOBALS['requests'] = array();

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

function _n( $single, $plural, $number, $domain = '' ) {
	return 1 === (int) $number ? $single : $plural;
}

function number_format_i18n( $number ) {
	return (string) $number;
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

function wp_remote_request( $url, $args = array() ) {
	$GLOBALS['requests'][] = array(
		'url'  => $url,
		'args' => $args,
	);

	$answer = array_shift( $GLOBALS['answers'] );

	if ( is_wp_error( $answer ) ) {
		return $answer;
	}

	return array(
		'code' => isset( $answer['code'] ) ? $answer['code'] : 200,
		'body' => wp_json_encode( isset( $answer['body'] ) ? $answer['body'] : array() ),
	);
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/klaviyo/settings.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/client.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/queue.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/api.php';

// --- Keys -------------------------------------------------------------------

check( 'a public key is six letters and digits', blueworx_klaviyo_clean_public_key( ' AbC123 ' ), 'AbC123' );
check( 'an empty public key box is an empty key', blueworx_klaviyo_clean_public_key( '' ), '' );
check( 'a public key of the wrong length is refused', blueworx_klaviyo_clean_public_key( 'AbC12' ), false );
check( 'a script is never a public key', blueworx_klaviyo_clean_public_key( '<script>' ), false );
check( 'a private key starts with pk_', blueworx_klaviyo_clean_private_key( 'pk_0123456789abcdefghijklmnop' ), 'pk_0123456789abcdefghijklmnop' );
check( 'an empty private key box means keep the saved one', blueworx_klaviyo_clean_private_key( '' ), '' );
check( 'a private key without the prefix is refused', blueworx_klaviyo_clean_private_key( '0123456789abcdefghijklmnop' ), false );
check( 'a short private key is refused', blueworx_klaviyo_clean_private_key( 'pk_short' ), false );
check( 'a masked key shows only its end', blueworx_klaviyo_mask( 'pk_0123456789abcdefghijklmnop' ), '••••••••mnop' );
check( 'an empty key masks to nothing', blueworx_klaviyo_mask( '' ), '' );

// --- What gets written to a profile -----------------------------------------

$plan = blueworx_klaviyo_normalize_plan(
	array(
		'label'   => 'Test',
		'email'   => ' Someone@Example.COM ',
		'profile' => array(
			'first_name' => 'Sam',
			'plan'       => 'Gold',
			'empty'      => '',
			'zero'       => 0,
			'no'         => false,
		),
		'fill'    => array(
			'last_name' => 'Jones',
			'source'    => 'form',
		),
		'guard'   => array( 'status' => 'lead' ),
	)
);

check( 'the email is lower-cased and trimmed', $plan['email'], 'someone@example.com' );
check( 'an empty value never reaches the profile', isset( $plan['profile']['empty'] ), false );
check( 'zero is a real answer', $plan['profile']['zero'], 0 );
check( 'false is a real answer', $plan['profile']['no'], false );

$attributes = blueworx_klaviyo_profile_attributes( $plan, array() );

check( 'a Klaviyo field goes where Klaviyo keeps it', $attributes['first_name'], 'Sam' );
check( 'anything else becomes a custom property', $attributes['properties']['plan'], 'Gold' );
check( 'a fill is written when the profile has none', $attributes['last_name'], 'Jones' );
check( 'a fill property is written when the profile has none', $attributes['properties']['source'], 'form' );
check( 'a guard is written when the profile has none', $attributes['properties']['status'], 'lead' );

$attributes = blueworx_klaviyo_profile_attributes(
	$plan,
	array(
		'last_name'  => 'Smith',
		'properties' => array(
			'source' => 'shop',
			'status' => 'customer',
		),
	)
);

check( 'a fill never overwrites a name', isset( $attributes['last_name'] ), false );
check( 'a fill never overwrites a property', isset( $attributes['properties']['source'] ), false );
check( 'a guard never overwrites a held value', isset( $attributes['properties']['status'] ), false );
check( 'a plain property is written every time', $attributes['properties']['plan'], 'Gold' );
check( 'the profile is only looked up when a fill or guard needs it', blueworx_klaviyo_plan_needs_profile( $plan ), true );
check(
	'a plan of plain properties needs no look-up',
	blueworx_klaviyo_plan_needs_profile(
		blueworx_klaviyo_normalize_plan(
			array(
				'email'   => 'a@b.co',
				'profile' => array( 'x' => 1 ),
			)
		)
	),
	false
);

// --- Protected properties ---------------------------------------------------

$GLOBALS['filters']['blueworx_klaviyo_protected_properties'] = static function ( $names ) {
	$names[] = 'member_number';
	return $names;
};

$plan = blueworx_klaviyo_normalize_plan(
	array(
		'email'   => 'a@b.co',
		'profile' => array(
			'member_number' => '17',
			'plan'          => 'Gold',
		),
	)
);

check( 'a protected property moves out of the every-time set', isset( $plan['profile']['member_number'] ), false );
check( 'and into the write-once set', $plan['guard']['member_number'], '17' );
check( 'written when the profile has none', blueworx_klaviyo_profile_attributes( $plan, array() )['properties']['member_number'], '17' );
check( 'left alone when it has one', isset( blueworx_klaviyo_profile_attributes( $plan, array( 'properties' => array( 'member_number' => '3' ) ) )['properties']['member_number'] ), false );

unset( $GLOBALS['filters']['blueworx_klaviyo_protected_properties'] );

// --- Normalising a plan -----------------------------------------------------

check(
	'a plan without a real email is nothing',
	blueworx_klaviyo_normalize_plan(
		array(
			'email'   => 'nope',
			'profile' => array( 'a' => 1 ),
		)
	),
	null
);
check(
	'a plan with nothing to send is nothing',
	blueworx_klaviyo_normalize_plan(
		array(
			'email'   => 'a@b.co',
			'profile' => array( 'a' => '' ),
		)
	),
	null
);
check( 'something that is not an array is nothing', blueworx_klaviyo_normalize_plan( 'x' ), null );

$plan = blueworx_klaviyo_normalize_plan(
	array(
		'email' => 'a@b.co',
		'event' => array(
			'name'       => 'Viewed Product',
			'unique_id'  => 'view-1',
			'properties' => array(
				'name'  => 'Hat',
				'price' => 12.5,
				'blank' => '',
			),
			'value'      => '12.5',
			'currency'   => 'aud',
			'time'       => 1700000000,
		),
	)
);

check( 'an event names the log line when nothing else does', $plan['label'], 'Viewed Product' );
check( 'an event value is a number', $plan['event']['value'], 12.5 );
check( 'a currency is upper-cased', $plan['event']['currency'], 'AUD' );
check( 'an empty event property is dropped', isset( $plan['event']['properties']['blank'] ), false );

$body = blueworx_klaviyo_event_body( $plan );

check( 'the event body names the metric', $body['data']['attributes']['metric']['data']['attributes']['name'], 'Viewed Product' );
check( 'the event body carries the unique id', $body['data']['attributes']['unique_id'], 'view-1' );
check( 'the event body names the profile by email', $body['data']['attributes']['profile']['data']['attributes']['email'], 'a@b.co' );
check( 'only value counts as revenue', $body['data']['attributes']['value'], 12.5 );
check( 'the currency travels with the value', $body['data']['attributes']['value_currency'], 'AUD' );
check( 'the time is sent as ISO 8601', $body['data']['attributes']['time'], gmdate( 'c', 1700000000 ) );

$plain = blueworx_klaviyo_event_body(
	blueworx_klaviyo_normalize_plan(
		array(
			'email' => 'a@b.co',
			'event' => array( 'name' => 'Signed Up' ),
		)
	)
);

check( 'an event with no value sends no revenue', isset( $plain['data']['attributes']['value'] ), false );
check( 'an event with no time lets Klaviyo stamp it', isset( $plain['data']['attributes']['time'] ), false );
check( 'an event without a unique id gets one', '' !== $plain['data']['attributes']['unique_id'], true );

$subscribe = blueworx_klaviyo_subscribe_body( 'a@b.co', 'Y5fFQA' );

check( 'a subscription names the list', $subscribe['data']['relationships']['list']['data']['id'], 'Y5fFQA' );
check( 'a subscription is to email marketing', $subscribe['data']['attributes']['profiles']['data'][0]['attributes']['subscriptions']['email']['marketing']['consent'], 'SUBSCRIBED' );
check( 'a subscription is never a historical import', $subscribe['data']['attributes']['historical_import'], false );

// --- Retrying ---------------------------------------------------------------

check( 'never reaching Klaviyo is worth a retry', blueworx_klaviyo_is_retryable( 0 ), true );
check( 'Klaviyo being down is worth a retry', blueworx_klaviyo_is_retryable( 503 ), true );
check( 'being rate limited is worth a retry', blueworx_klaviyo_is_retryable( 429 ), true );
check( 'a bad key is worth a retry, since somebody may fix it', blueworx_klaviyo_is_retryable( 401 ), true );
check( 'a malformed request fails the same way every time', blueworx_klaviyo_is_retryable( 400 ), false );
check( 'a missing list fails the same way every time', blueworx_klaviyo_is_retryable( 404 ), false );
check( 'five retries, spread over six hours', blueworx_klaviyo_retry_delays(), array( 60, 300, 900, 3600, 21600 ) );

// --- The three functions ----------------------------------------------------

$GLOBALS['options']['blueworx_klaviyo_private_key'] = 'pk_0123456789abcdefghijklmnop';
$GLOBALS['queued']                                  = array();

check( 'identify queues a job', blueworx_klaviyo_identify( 'a@b.co', array( 'plan' => 'Gold' ), array( 'source' => 'test' ) ), true );
check( 'the job carries the plan', $GLOBALS['queued'][0]['plans'][0]['profile']['plan'], 'Gold' );
check( 'the job remembers where it came from', $GLOBALS['queued'][0]['source'], 'test' );
check( 'the job starts with no attempts', $GLOBALS['queued'][0]['attempts'], 0 );

check(
	'track queues a job',
	blueworx_klaviyo_track(
		'Placed Order',
		'a@b.co',
		array( 'total' => 20 ),
		array(
			'value'    => 20,
			'currency' => 'nzd',
			'list'     => 'L1',
		)
	),
	true
);
check( 'track names the event', $GLOBALS['queued'][1]['plans'][0]['event']['name'], 'Placed Order' );
check( 'track carries the list along', $GLOBALS['queued'][1]['plans'][0]['list'], 'L1' );
check( 'track without a name sends nothing', blueworx_klaviyo_track( '', 'a@b.co' ), false );

check( 'subscribe queues a job', blueworx_klaviyo_subscribe( 'a@b.co', 'L1' ), true );
check( 'subscribe names the list', $GLOBALS['queued'][2]['plans'][0]['list'], 'L1' );
check( 'subscribe without a list sends nothing', blueworx_klaviyo_subscribe( 'a@b.co', '' ), false );
check( 'nothing is queued for a bad email', blueworx_klaviyo_identify( 'nope', array( 'a' => 1 ) ), false );

$GLOBALS['filters']['blueworx_klaviyo_plan'] = static function ( $plan, $source ) {
	if ( 'test' === $source ) {
		$plan['profile']['seen_by_filter'] = true;
	}
	if ( 'blocked' === $source ) {
		return null;
	}
	return $plan;
};

blueworx_klaviyo_identify( 'a@b.co', array( 'plan' => 'Gold' ), array( 'source' => 'test' ) );
check( 'the filter can add to a plan before it is queued', end( $GLOBALS['queued'] )['plans'][0]['profile']['seen_by_filter'], true );
check( 'the filter can stop a plan', blueworx_klaviyo_identify( 'a@b.co', array( 'plan' => 'Gold' ), array( 'source' => 'blocked' ) ), false );

unset( $GLOBALS['filters']['blueworx_klaviyo_plan'] );

$GLOBALS['enabled'] = false;
check( 'nothing is queued while the function is off', blueworx_klaviyo_identify( 'a@b.co', array( 'plan' => 'Gold' ) ), false );
$GLOBALS['enabled'] = true;

$GLOBALS['options']['blueworx_klaviyo_private_key'] = '';
$GLOBALS['options']['blueworx_klaviyo_log']         = array();
check( 'nothing is queued without a key', blueworx_klaviyo_identify( 'a@b.co', array( 'plan' => 'Gold' ) ), false );
check( 'and the log says why', $GLOBALS['options']['blueworx_klaviyo_log'][0]['outcome'], 'failed' );
$GLOBALS['options']['blueworx_klaviyo_private_key'] = 'pk_0123456789abcdefghijklmnop';

// --- Sending a plan ---------------------------------------------------------

$plan = blueworx_klaviyo_normalize_plan(
	array(
		'email' => 'a@b.co',
		'fill'  => array( 'first_name' => 'Sam' ),
		'event' => array(
			'name'      => 'Signed Up',
			'unique_id' => 'signup-1',
		),
		'list'  => 'L1',
	)
);

$GLOBALS['answers']  = array(
	array( 'body' => array( 'data' => array( array( 'attributes' => array( 'first_name' => 'Samuel' ) ) ) ) ),
	array( 'code' => 202 ),
	array( 'code' => 202 ),
	array( 'code' => 202 ),
);
$GLOBALS['requests'] = array();

$outcome = blueworx_klaviyo_send_plan( $plan );

check( 'a full plan goes through', $outcome['ok'], true );
check( 'profile is looked up, imported, event recorded, list subscribed', count( $GLOBALS['requests'] ), 4 );
check( 'the look-up comes first', strpos( $GLOBALS['requests'][0]['url'], 'profiles?filter=' ) !== false, true );
check( 'the key goes in the Authorization header', $GLOBALS['requests'][0]['args']['headers']['Authorization'], 'Klaviyo-API-Key pk_0123456789abcdefghijklmnop' );
check( 'the pinned API revision is sent', $GLOBALS['requests'][0]['args']['headers']['revision'], BLUEWORX_KLAVIYO_REVISION );

$import = json_decode( $GLOBALS['requests'][1]['args']['body'], true );
check( 'the held name is not overwritten', isset( $import['data']['attributes']['first_name'] ), false );

$GLOBALS['answers']  = array(
	array(
		'code' => 503,
		'body' => array( 'errors' => array( array( 'detail' => 'Down for a moment' ) ) ),
	),
);
$GLOBALS['requests'] = array();
$outcome             = blueworx_klaviyo_send_plan(
	blueworx_klaviyo_normalize_plan(
		array(
			'email'   => 'a@b.co',
			'profile' => array( 'a' => 1 ),
		)
	)
);

check( 'a 503 fails the plan', $outcome['ok'], false );
check( 'and asks for a retry', $outcome['retry'], true );
check( 'with Klaviyo\'s own words', $outcome['error'], 'Klaviyo said 503: Down for a moment' );

$GLOBALS['answers'] = array( array( 'code' => 400 ) );
$outcome            = blueworx_klaviyo_send_plan(
	blueworx_klaviyo_normalize_plan(
		array(
			'email'   => 'a@b.co',
			'profile' => array( 'a' => 1 ),
		)
	)
);

check( 'a 400 fails for good', $outcome['retry'], false );

$GLOBALS['answers'] = array( new WP_Error( 'http_request_failed', 'cURL error 28' ) );
$outcome            = blueworx_klaviyo_send_plan(
	blueworx_klaviyo_normalize_plan(
		array(
			'email'   => 'a@b.co',
			'profile' => array( 'a' => 1 ),
		)
	)
);

check( 'never getting there is worth a retry', $outcome['retry'], true );
check( 'with the transport\'s words', $outcome['error'], 'cURL error 28' );

// --- Test connection --------------------------------------------------------

$GLOBALS['answers'] = array(
	array(
		'body' => array(
			'data' => array(
				array(
					'id'         => 'L1',
					'attributes' => array( 'name' => 'Newsletter' ),
				),
				array(
					'id'         => 'L2',
					'attributes' => array( 'name' => 'Members' ),
				),
			),
		),
	),
);

$test = blueworx_klaviyo_test_connection();

check( 'a working key passes the test', $test['ok'], true );
check( 'and names the lists it can see', $test['lines'][0], 'The key works. It can see 2 lists: Newsletter, Members.' );
check(
	'the lists are remembered',
	blueworx_klaviyo_lists(),
	array(
		'L1' => 'Newsletter',
		'L2' => 'Members',
	)
);

$GLOBALS['options']['blueworx_klaviyo_private_key'] = '';
check( 'no key fails the test before asking Klaviyo', blueworx_klaviyo_test_connection()['ok'], false );

finish();
