<?php
/**
 * Klaviyo checkouts: what Started Checkout carries, and when a saved checkout
 * is worth sending.
 *
 * Run with: php tests/php/klaviyo-checkout-test.php
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

function wp_date( $format, $time = null ) {
	return gmdate( $format, null === $time ? 1762300800 : $time );
}

function wp_rand() {
	return 4;
}

function add_option( $key, $value, $deprecated = '', $autoload = 'yes' ) {
	if ( array_key_exists( $key, $GLOBALS['options'] ) ) {
		return false;
	}
	$GLOBALS['options'][ $key ] = $value;
	$GLOBALS['queued'][]        = $value;
	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['options'][ $key ] );
	return true;
}

function wp_schedule_single_event( $time, $hook, $args = array() ) {
	return true;
}

function add_query_arg( $key, $value, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . rawurlencode( $value );
}

function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/klaviyo/settings.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/client.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/queue.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/api.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/forms.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/store.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/checkout.php';

/**
 * A checkout the way SureCart's form saves it, with two items.
 *
 * @param array $overrides Fields to change.
 * @return array
 */
function checkout_fixture( $overrides = array() ) {
	return array_merge(
		array(
			'id'           => 'chk1',
			'object'       => 'checkout',
			'status'       => 'draft',
			'email'        => 'Sam@Example.com',
			'first_name'   => 'Sam',
			'live_mode'    => true,
			'total_amount' => 54800,
			'line_items'   => array(
				'data' => array(
					array(
						'quantity'     => 2,
						'total_amount' => 50800,
						'price'        => array(
							'id'       => 'price1',
							'amount'   => 25400,
							'currency' => 'aud',
							'product'  => array(
								'id'        => 'prod1',
								'slug'      => 'racket',
								'name'      => 'Racket',
								'permalink' => 'https://example.test/products/racket/',
								'image_url' => 'https://example.test/racket.jpg',
							),
						),
					),
					array(
						'quantity' => 1,
						'price'    => array(
							'id'      => 'price2',
							'amount'  => 4000,
							'product' => array(
								'id'   => 'prod2',
								'name' => 'Grip',
							),
						),
					),
				),
			),
		),
		$overrides
	);
}

$settings = array(
	'list'         => '',
	'checkout_box' => 'marketing_opt_in',
	'test_mode'    => '0',
);

// --- The plan -----------------------------------------------------------------

$plan = blueworx_klaviyo_checkout_plan( checkout_fixture(), $settings, 'https://example.test/checkout/' );

check( 'the event is Started Checkout', $plan['event']['name'], 'Started Checkout' );
check( 'addressed to the checkout email', $plan['email'], 'Sam@Example.com' );
check( 'the value is the checkout total in whole currency', $plan['event']['value'], 548.0 );
check( 'the currency comes from the price when the checkout has none', $plan['event']['currency'], 'AUD' );
check( 'every item is listed', count( $plan['event']['properties']['Items'] ), 2 );
check( 'with its name', $plan['event']['properties']['ItemNames'], array( 'Racket', 'Grip' ) );
check( 'its quantity', $plan['event']['properties']['Items'][0]['Quantity'], 2 );
check( 'its row total', $plan['event']['properties']['Items'][0]['RowTotal'], 508.0 );
check( 'a row total worked out when SureCart gives none', $plan['event']['properties']['Items'][1]['RowTotal'], 40.0 );
check( 'its link', $plan['event']['properties']['Items'][0]['ProductURL'], 'https://example.test/products/racket/' );
check( 'and the number of things in the cart', $plan['event']['properties']['item_count'], 3 );
check( 'the link back names the checkout', $plan['event']['properties']['CheckoutURL'], 'https://example.test/checkout/?checkout_id=chk1' );
check( 'the first name is filled, never overwritten', $plan['fill']['first_name'], 'Sam' );
check( 'the event id starts with the checkout', strpos( $plan['event']['unique_id'], 'checkout-chk1-' ), 0 );

$again = blueworx_klaviyo_checkout_plan( checkout_fixture(), $settings, 'https://example.test/checkout/' );
check( 'the same cart has the same event id', $again['event']['unique_id'], $plan['event']['unique_id'] );

$changed                                      = checkout_fixture();
$changed['line_items']['data'][1]['quantity'] = 3;
$changed['total_amount']                      = 62800;
$changed                                      = blueworx_klaviyo_checkout_plan( $changed, $settings, 'https://example.test/checkout/' );
check( 'a changed cart is a new event', $changed['event']['unique_id'] !== $plan['event']['unique_id'], true );

$normalized = blueworx_klaviyo_normalize_plan( $plan );
check( 'the plan passes the connection\'s checks', $normalized['email'], 'sam@example.com' );
check( 'and keeps the items', count( $normalized['event']['properties']['Items'] ), 2 );

// --- When there is nothing to send --------------------------------------------

check( 'no email, nothing to send', blueworx_klaviyo_checkout_plan( checkout_fixture( array( 'email' => '' ) ), $settings, '' ), null );
check(
	'a customer record\'s email will do',
	blueworx_klaviyo_checkout_plan(
		checkout_fixture(
			array(
				'email'    => null,
				'customer' => array( 'email' => 'c@example.com' ),
			)
		),
		$settings,
		''
	)['email'],
	'c@example.com'
);
check( 'a paid checkout is not a started one', blueworx_klaviyo_checkout_plan( checkout_fixture( array( 'status' => 'paid' ) ), $settings, '' ), null );
check( 'a finalized checkout is being paid for', blueworx_klaviyo_checkout_plan( checkout_fixture( array( 'status' => 'finalized' ) ), $settings, '' ), null );
check( 'an empty cart is not a started checkout', blueworx_klaviyo_checkout_plan( checkout_fixture( array( 'line_items' => array( 'data' => array() ) ) ), $settings, '' ), null );

// --- Noticing a saved checkout ------------------------------------------------

$GLOBALS['options']['blueworx_klaviyo_private_key'] = 'pk_fixture0123456789abcdefghij';

check( 'a draft with an email is queued', blueworx_klaviyo_checkout_noticed( checkout_fixture() ), 'queued' );
check( 'as one job', count( array_filter( $GLOBALS['queued'], 'is_array' ) ), 1 );
check( 'saved again unchanged, it is a repeat', blueworx_klaviyo_checkout_noticed( checkout_fixture() ), 'repeat' );
check( 'and queues nothing more', count( array_filter( $GLOBALS['queued'], 'is_array' ) ), 1 );

$changed                                      = checkout_fixture();
$changed['line_items']['data'][1]['quantity'] = 3;
check( 'a changed cart is queued again', blueworx_klaviyo_checkout_noticed( $changed ), 'queued' );
check( 'without an email it is skipped', blueworx_klaviyo_checkout_noticed( checkout_fixture( array( 'email' => '' ) ) ), 'skipped' );
check( 'a test checkout stays out', blueworx_klaviyo_checkout_noticed( checkout_fixture( array( 'live_mode' => false ) ) ), 'test_mode' );

$GLOBALS['options']['blueworx_klaviyo_store_test_mode'] = '1';
check(
	'until asked for',
	blueworx_klaviyo_checkout_noticed(
		checkout_fixture(
			array(
				'id'        => 'chk2',
				'live_mode' => false,
			)
		)
	),
	'queued'
);

$GLOBALS['enabled'] = false;
check( 'switched off, nothing is noticed', blueworx_klaviyo_checkout_noticed( checkout_fixture() ), 'off' );

finish();
