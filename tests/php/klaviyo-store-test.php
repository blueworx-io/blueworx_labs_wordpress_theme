<?php
/**
 * Klaviyo store: what each SureCart event sends, in the store's own currency,
 * and how a delivery is checked.
 *
 * Run with: php tests/php/klaviyo-store-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';

$GLOBALS['enabled'] = true;
$GLOBALS['queued']  = array();
$GLOBALS['records'] = array();

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

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/klaviyo/settings.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/client.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/queue.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/api.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/forms.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/store.php';

$GLOBALS['filters']['blueworx_klaviyo_surecart_record'] = static function ( $short, $type, $id ) {
	return isset( $GLOBALS['records'][ $type ][ $id ] ) ? $GLOBALS['records'][ $type ][ $id ] : new WP_Error( 'not_found', 'No such record.' );
};

/**
 * A subscription the way SureCart hands it over, with its price, product,
 * customer and current period expanded.
 *
 * @param array $overrides Fields to change.
 * @return array
 */
function subscription_fixture( $overrides = array() ) {
	return array_merge(
		array(
			'id'                    => 'sub1',
			'status'                => 'active',
			'created_at'            => '2026-11-04T19:00:42+00:00',
			'current_period_end_at' => '2027-11-04T19:00:42+00:00',
			'cancel_at_period_end'  => false,
			'ended_at'              => null,
			'customer'              => array(
				'id'         => 'cus1',
				'email'      => 'jane@example.com',
				'first_name' => 'Jane',
				'last_name'  => 'Smith',
			),
			'price'                 => array(
				'id'                 => 'price1',
				'amount'             => 251900,
				'currency'           => 'aud',
				'recurring_interval' => 'year',
				'product'            => array(
					'id'   => 'prod1',
					'slug' => 'gold-membership',
					'name' => 'Gold Membership',
				),
			),
			'current_period'        => array(
				'id'                    => 'per1',
				'status'                => 'paid',
				'payment_retry_count'   => 2,
				'next_payment_retry_at' => '2026-12-10T00:00:00+00:00',
				'checkout'              => array( 'amount_due' => 22900 ),
			),
		),
		$overrides
	);
}

$settings = array(
	'list'         => 'XRYaZh',
	'checkout_box' => 'marketing_opt_in',
	'test_mode'    => '0',
);

$order = array(
	'id'       => 'ord1',
	'checkout' => array(
		'paid_amount' => 251900,
		'paid_at'     => '2026-11-04T19:00:42+00:00',
		'metadata'    => array( 'marketing_opt_in' => 'on' ),
		'discount'    => array( 'promotion' => array( 'code' => 'WELCOME' ) ),
	),
);

// --- Small helpers ----------------------------------------------------------

check( 'cents become whole currency', blueworx_klaviyo_store_money( 251900 ), 2519.0 );
check( 'a zero-decimal currency is left alone', blueworx_klaviyo_store_money( 2519, true ), 2519.0 );
check( 'year reads as annual', blueworx_klaviyo_store_interval( 'year' ), 'annual' );
check( 'month reads as monthly', blueworx_klaviyo_store_interval( 'month' ), 'monthly' );
check( 'an unknown interval is SureCart\'s own word', blueworx_klaviyo_store_interval( 'fortnight' ), 'fortnight' );
check( 'past_due and unpaid are past due', blueworx_klaviyo_store_status( 'unpaid' ), 'past_due' );
check( 'canceled and completed are cancelled', blueworx_klaviyo_store_status( 'completed' ), 'cancelled' );
check( 'anything else is active', blueworx_klaviyo_store_status( 'trialing' ), 'active' );
check( 'the currency comes from the first record that has one', blueworx_klaviyo_store_currency( array(), array( 'currency' => 'nzd' ) ), 'NZD' );
check( 'no record and no SureCart means no currency', blueworx_klaviyo_store_currency( array() ), '' );

$GLOBALS['filters']['blueworx_klaviyo_store_currency'] = static function () {
	return 'gbp';
};
check( 'a site can say its currency when records do not', blueworx_klaviyo_store_currency( array() ), 'GBP' );
unset( $GLOBALS['filters']['blueworx_klaviyo_store_currency'] );

// --- Started Subscription ---------------------------------------------------

$plan = blueworx_klaviyo_store_plan_started( subscription_fixture(), $order, $settings );

check( 'event name', $plan['event']['name'], 'Started Subscription' );
check( 'revenue is what was paid, in whole currency', $plan['event']['value'], 2519.0 );
check( 'in the price\'s currency', $plan['event']['currency'], 'AUD' );
check( 'one event per subscription', $plan['event']['unique_id'], 'started-sub1' );
check( 'the product is named', $plan['event']['properties']['product_name'], 'Gold Membership' );
check( 'and its slug, for a site plugin to match on', $plan['event']['properties']['product_slug'], 'gold-membership' );
check( 'annual or monthly is its own field', $plan['event']['properties']['billing_interval'], 'annual' );
check( 'order and coupon travel along', $plan['event']['properties']['order_id'] . '/' . $plan['event']['properties']['coupon_code'], 'ord1/WELCOME' );
check( 'the start date is the day the subscription began', $plan['event']['properties']['membership_start_date'], '2026-11-04' );
check( 'the next renewal', $plan['event']['properties']['next_renewal_date'], '2027-11-04' );
check( 'membership_status on the profile', $plan['profile']['membership_status'], 'active' );
check( 'membership_plan on the profile', $plan['profile']['membership_plan'], 'Gold Membership' );
check( 'surecart_customer_id on the profile', $plan['profile']['surecart_customer_id'], 'cus1' );
check( 'the ticked box records consent', $plan['profile']['marketing_consent'], true );
check( 'and goes on the Members list', $plan['list'], 'XRYaZh' );
check( 'names fill, never overwrite', $plan['fill']['first_name'], 'Jane' );
check( 'the subscription\'s state is remembered once sent', $plan['after']['state']['price'], 'price1' );
check( 'nothing in the plan names a client', strpos( wp_json_encode( $plan ), 'founding' ), false );

$unticked = $order;
unset( $unticked['checkout']['metadata'] );
$plan = blueworx_klaviyo_store_plan_started( subscription_fixture(), $unticked, $settings );

check( 'no tick means no consent written', isset( $plan['profile']['marketing_consent'] ), false );
check( 'and no list', $plan['list'], '' );
check( 'the event still goes', $plan['event']['name'], 'Started Subscription' );

// --- Placed Order -----------------------------------------------------------

$purchase = array(
	'id'            => 'pur2',
	'subscription'  => null,
	'quantity'      => 2,
	'product'       => array(
		'id'   => 'prod9',
		'slug' => 'day-pass',
		'name' => 'Day pass',
	),
	'initial_order' => array(
		'id'       => 'ord2',
		'checkout' => array(
			'paid_amount' => 4000,
			'currency'    => 'aud',
			'paid_at'     => '2026-11-05T10:00:00+00:00',
			'customer'    => array(
				'id'         => 'cus2',
				'email'      => 'sam@example.com',
				'first_name' => 'Sam',
			),
			'metadata'    => array( 'marketing_opt_in' => 'on' ),
		),
	),
);

$plan = blueworx_klaviyo_store_plan_order( $purchase, $settings );

check( 'a one-off purchase is Placed Order', $plan['event']['name'], 'Placed Order' );
check( 'with what was paid as revenue', $plan['event']['value'], 40.0 );
check( 'in the checkout\'s currency', $plan['event']['currency'], 'AUD' );
check( 'one event per order', $plan['event']['unique_id'], 'order-ord2' );
check( 'the product and quantity', $plan['event']['properties']['product_name'] . ' x' . $plan['event']['properties']['quantity'], 'Day pass x2' );
check( 'the customer is the checkout\'s', $plan['email'], 'sam@example.com' );
check( 'the last order date is set', $plan['profile']['last_order_date'], '2026-11-05' );
check( 'the ticked box subscribes them', $plan['list'], 'XRYaZh' );

// --- The other events -------------------------------------------------------

$plan = blueworx_klaviyo_store_plan_renewed( subscription_fixture(), 3 );
check( 'renewal event', $plan['event']['name'], 'Subscription Renewed' );
check( 'revenue from what the period charged', $plan['event']['value'], 229.0 );
check( 'payment_number', $plan['event']['properties']['payment_number'], 3 );
check( 'one event per period', $plan['event']['unique_id'], 'renewed-per1' );

$plan = blueworx_klaviyo_store_plan_failed( subscription_fixture() );
check( 'failed event', $plan['event']['name'], 'Payment Failed' );
check( 'no revenue on a failure', isset( $plan['event']['value'] ), false );
check( 'attempt_count', $plan['event']['properties']['attempt_count'], 2 );
check( 'next_retry_date', $plan['event']['properties']['next_retry_date'], '2026-12-10' );
check( 'the profile goes past due', $plan['profile']['membership_status'], 'past_due' );
check( 'one event per attempt', $plan['event']['unique_id'], 'failed-per1-2' );

$plan = blueworx_klaviyo_store_plan_cancelled(
	subscription_fixture(
		array(
			'cancel_at_period_end'     => true,
			'current_cancellation_act' => array(
				'id'                  => 'act1',
				'created_at'          => '2026-12-01T00:00:00+00:00',
				'comment'             => 'Moving away',
				'cancellation_reason' => array( 'label' => 'Relocating' ),
			),
		)
	)
);
check( 'cancelled event', $plan['event']['name'], 'Subscription Cancelled' );
check( 'access runs to the end of the period', $plan['event']['properties']['access_ends_at'], '2027-11-04' );
check( 'the reason and comment are one line', $plan['event']['properties']['cancel_reason'], 'Relocating: Moving away' );
check( 'still active until then', $plan['profile']['membership_status'], 'active' );
check( 'no next renewal once cancelling', $plan['profile']['next_renewal_date'], '' );
check( 'the end date is on the profile', $plan['profile']['membership_end_date'], '2027-11-04' );
check( 'remembered as sent', $plan['after']['state']['cancel_sent'], true );

$plan = blueworx_klaviyo_store_plan_changed(
	subscription_fixture(),
	array(
		'id'                 => 'price0',
		'recurring_interval' => 'month',
		'product'            => array( 'name' => 'Gold Membership' ),
	)
);
check( 'plan changed event', $plan['event']['name'], 'Plan Changed' );
check( 'from monthly', $plan['event']['properties']['from_plan'], 'Gold Membership (monthly)' );
check( 'to annual', $plan['event']['properties']['to_plan'], 'Gold Membership (annual)' );

$plan = blueworx_klaviyo_store_plan_refunded(
	array(
		'id'       => 'ref1',
		'amount'   => 5000,
		'reason'   => 'requested_by_customer',
		'customer' => array(
			'id'    => 'cus1',
			'email' => 'jane@example.com',
		),
		'charge'   => array(
			'checkout' => array(
				'order'      => 'ord1',
				'currency'   => 'aud',
				'line_items' => array(
					'data' => array(
						array(
							'price' => array(
								'recurring_interval' => 'year',
								'product'            => array(
									'name' => 'Gold Membership',
									'slug' => 'gold-membership',
								),
							),
						),
					),
				),
			),
		),
	)
);
check( 'refund event', $plan['event']['name'], 'Refunded' );
check( 'a refund is never revenue', isset( $plan['event']['value'] ), false );
check( 'the amount is a plain property', $plan['event']['properties']['amount'], 50.0 );
check( 'in the store\'s currency', $plan['event']['properties']['currency'], 'AUD' );
check( 'the order it was for', $plan['event']['properties']['order_id'], 'ord1' );

check( 'a record with no email is nothing', blueworx_klaviyo_store_plan_started( subscription_fixture( array( 'customer' => array() ) ), $order, $settings ), null );

// --- Working out an event ---------------------------------------------------

$GLOBALS['records'] = array(
	'purchase'     => array(
		'pur1' => array(
			'id'            => 'pur1',
			'subscription'  => 'sub1',
			'initial_order' => $order,
		),
		'pur2' => $purchase,
	),
	'subscription' => array( 'sub1' => subscription_fixture() ),
);

$plans = blueworx_klaviyo_store_plans(
	array(
		'type' => 'purchase.created',
		'id'   => 'pur1',
	),
	$settings
);
check( 'a purchase with a subscription starts one', $plans[0]['event']['name'], 'Started Subscription' );

$plans = blueworx_klaviyo_store_plans(
	array(
		'type' => 'purchase.created',
		'id'   => 'pur2',
	),
	$settings
);
check( 'a purchase without one is an order', $plans[0]['event']['name'], 'Placed Order' );

$plans = blueworx_klaviyo_store_plans(
	array(
		'type' => 'purchase.created',
		'id'   => 'missing',
	),
	$settings
);
check( 'a record that cannot be found is worth a retry', is_wp_error( $plans ), true );

$GLOBALS['records']['subscription']['sub1']['current_period']['status'] = 'pending';
$plans = blueworx_klaviyo_store_plans(
	array(
		'type' => 'subscription.renewed',
		'id'   => 'sub1',
	),
	$settings
);
check( 'a renewal not yet paid waits', is_wp_error( $plans ) && 'blueworx_klaviyo_not_paid' === $plans->get_error_code(), true );
$GLOBALS['records']['subscription']['sub1']['current_period']['status'] = 'paid';

$plans = blueworx_klaviyo_store_plans(
	array(
		'type'     => 'subscription.updated',
		'id'       => 'sub1',
		'previous' => array( 'status' => 'past_due' ),
	),
	$settings
);
check( 'payment recovering is a quiet status update', $plans[0]['label'], 'Subscription status update' );
check( 'back to active', $plans[0]['profile']['membership_status'], 'active' );

$plans = blueworx_klaviyo_store_plans(
	array(
		'type'     => 'subscription.updated',
		'id'       => 'sub1',
		'previous' => array(),
	),
	$settings
);
check( 'an update that changes nothing sends nothing', $plans, array() );

$plans = blueworx_klaviyo_store_plans(
	array(
		'type' => 'product.updated',
		'id'   => 'x',
	),
	$settings
);
check( 'an event this does not listen for sends nothing', $plans, array() );

// --- Checking a delivery ----------------------------------------------------

$body      = '{"id":"evt1","type":"purchase.created"}';
$timestamp = '1762300800';
$signature = hash_hmac( 'sha256', $timestamp . '.' . $body, 'secret' );

check( 'a delivery signed with the secret is genuine', blueworx_klaviyo_store_signature_valid( $body, $signature, $timestamp, 'secret', 1762300800 ), true );
check( 'a wrong secret is refused', blueworx_klaviyo_store_signature_valid( $body, $signature, $timestamp, 'other', 1762300800 ), false );
check( 'an old delivery is refused', blueworx_klaviyo_store_signature_valid( $body, $signature, $timestamp, 'secret', 1762300800 + 3600 ), false );
check( 'no secret refuses everything', blueworx_klaviyo_store_signature_valid( $body, $signature, $timestamp, '', 1762300800 ), false );
check( 'a changed body is refused', blueworx_klaviyo_store_signature_valid( $body . ' ', $signature, $timestamp, 'secret', 1762300800 ), false );

// --- Accepting an event -----------------------------------------------------

$GLOBALS['options']['blueworx_klaviyo_private_key'] = 'pk_0123456789abcdefghijklmnop';
$GLOBALS['queued']                                  = array();

$event = array(
	'id'   => 'evt1',
	'type' => 'purchase.created',
	'data' => array( 'object' => array( 'id' => 'pur1' ) ),
);

check( 'a genuine event is queued', blueworx_klaviyo_store_accept_event( $event ), 'queued' );
check( 'as a job for the SureCart resolver', $GLOBALS['queued'][1]['resolver'], 'surecart' );
check( 'the same event again is a repeat', blueworx_klaviyo_store_accept_event( $event ), 'repeat' );
check( 'and queues nothing more', count( $GLOBALS['queued'] ), 2 );

$event['id']   = 'evt2';
$event['type'] = 'product.updated';
check( 'an event not listened for is ignored', blueworx_klaviyo_store_accept_event( $event ), 'ignored' );

$event['type']                        = 'purchase.created';
$event['data']['object']['live_mode'] = false;
check( 'a test order is left out', blueworx_klaviyo_store_accept_event( $event ), 'test_mode' );

$GLOBALS['options']['blueworx_klaviyo_store_test_mode'] = '1';
check( 'until asked for', blueworx_klaviyo_store_accept_event( $event ), 'queued' );

// --- The resolver -----------------------------------------------------------

check(
	'the resolver answers for surecart jobs',
	blueworx_klaviyo_store_resolve(
		null,
		'surecart',
		array(
			'type' => 'purchase.created',
			'id'   => 'pur2',
		)
	)[0]['event']['name'],
	'Placed Order'
);
check( 'and leaves other jobs alone', blueworx_klaviyo_store_resolve( null, 'other', array() ), null );

finish();
