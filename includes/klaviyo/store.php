<?php
/**
 * Klaviyo connection: the SureCart store.
 *
 * Every SureCart site sends its store activity to Klaviyo the same way. A
 * webhook of the theme's own, set up in the SureCart dashboard with its own
 * signing secret, tells the connection what happened; the connection checks
 * the signature, queues the event, and looks the records up in the background
 * where SureCart's ten-second answer window does not matter.
 *
 * Events: Placed Order (a one-off purchase), Started Subscription,
 * Subscription Renewed, Payment Failed, Subscription Cancelled, Plan Changed,
 * Refunded. Each carries the product, the price, the billing interval, the
 * dates and the IDs, in the store's own currency. Only money actually paid
 * counts as revenue. A checkout tick box, ticked, adds the customer to the
 * Members list; unticked never does.
 *
 * Nothing here names any one client's products. A site's plugin adds its own
 * rules through blueworx_klaviyo_plan with the source "store".
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Start of every "this SureCart event has been received" marker.
 */
const BLUEWORX_KLAVIYO_SEEN_PREFIX = 'blueworx_klaviyo_seen_';

/**
 * Start of every "what the connection remembers about a subscription" row.
 */
const BLUEWORX_KLAVIYO_SUB_PREFIX = 'blueworx_klaviyo_sub_';

/**
 * How long a received marker is kept. SureCart stops retrying well before this.
 */
const BLUEWORX_KLAVIYO_SEEN_DAYS = 30;

// --- Settings -----------------------------------------------------------------

/**
 * Whether SureCart is running on this site.
 *
 * @return bool
 */
function blueworx_klaviyo_surecart_active() {
	return class_exists( 'SureCart' ) || defined( 'SURECART_PLUGIN_FILE' );
}

/**
 * The store's settings, in the shape the plan builders take.
 *
 * @return array list, checkout_box and test_mode.
 */
function blueworx_klaviyo_store_settings() {
	return array(
		'list'         => (string) blueworx_klaviyo_setting( 'store_list', '' ),
		'checkout_box' => (string) blueworx_klaviyo_setting( 'store_checkout_box', 'marketing_opt_in' ),
		'test_mode'    => (string) blueworx_klaviyo_setting( 'store_test_mode', '0' ),
	);
}

/**
 * The SureCart webhook's signing secret: wp-config.php wins.
 *
 * @return string
 */
function blueworx_klaviyo_store_secret() {
	if ( defined( 'BLUEWORX_KLAVIYO_WEBHOOK_SECRET' ) && BLUEWORX_KLAVIYO_WEBHOOK_SECRET ) {
		return (string) BLUEWORX_KLAVIYO_WEBHOOK_SECRET;
	}

	return (string) blueworx_klaviyo_setting( 'store_webhook_secret', '' );
}

/**
 * Which SureCart events this listens for. The setup steps tick the same list.
 *
 * @return string[]
 */
function blueworx_klaviyo_store_events() {
	return array(
		'purchase.created',
		'subscription.renewed',
		'order.payment_failed',
		'subscription.set_to_cancel',
		'subscription.canceled',
		'subscription.updated',
		'refund.succeeded',
	);
}

/**
 * The address to give SureCart.
 *
 * @return string
 */
function blueworx_klaviyo_store_webhook_url() {
	return rest_url( 'blueworx/v1/klaviyo/surecart' );
}

// --- Small pure helpers -------------------------------------------------------

/**
 * Turns a SureCart date, which can be a timestamp or a string, into a timestamp.
 *
 * @param mixed $value Date.
 * @return int Timestamp, or 0 when there is none.
 */
function blueworx_klaviyo_store_timestamp( $value ) {
	if ( is_numeric( $value ) ) {
		return (int) $value;
	}

	return $value ? (int) strtotime( (string) $value ) : 0;
}

/**
 * Turns a SureCart date into an ISO date in the site's timezone.
 *
 * @param mixed $value Date.
 * @return string YYYY-MM-DD, or an empty string.
 */
function blueworx_klaviyo_store_date( $value ) {
	$time = blueworx_klaviyo_store_timestamp( $value );

	return $time > 0 ? wp_date( 'Y-m-d', $time ) : '';
}

/**
 * Converts SureCart's smallest-unit amount into whole currency.
 *
 * @param mixed $amount       Amount in the smallest unit.
 * @param bool  $zero_decimal Whether the currency has no minor unit.
 * @return float
 */
function blueworx_klaviyo_store_money( $amount, $zero_decimal = false ) {
	return $zero_decimal ? (float) $amount : round( (float) $amount / 100, 2 );
}

/**
 * A plain word for a billing interval.
 *
 * @param string $interval SureCart's recurring interval.
 * @return string annual, monthly, weekly, daily, or SureCart's own word.
 */
function blueworx_klaviyo_store_interval( $interval ) {
	$words = array(
		'year'  => 'annual',
		'month' => 'monthly',
		'week'  => 'weekly',
		'day'   => 'daily',
	);

	return isset( $words[ $interval ] ) ? $words[ $interval ] : (string) $interval;
}

/**
 * The currency a record is in, upper-cased.
 *
 * SureCart puts it on the price and on the checkout. When neither says, the
 * store's own currency is asked for through a filter, which defaults to the
 * SureCart account's when SureCart is there.
 *
 * @param array ...$records Records to look in, in order.
 * @return string ISO code, or an empty string when nothing knows.
 */
function blueworx_klaviyo_store_currency( ...$records ) {
	foreach ( $records as $record ) {
		if ( is_array( $record ) && ! empty( $record['currency'] ) && is_string( $record['currency'] ) ) {
			return strtoupper( $record['currency'] );
		}
	}

	$currency = '';

	if ( class_exists( 'SureCart' ) && method_exists( 'SureCart', 'account' ) ) {
		try {
			$account  = SureCart::account();
			$currency = isset( $account->currency ) ? (string) $account->currency : '';
		} catch ( Throwable $thrown ) {
			$currency = '';
		}
	}

	/**
	 * The store's currency, when a record does not say.
	 *
	 * @param string $currency ISO code, or empty.
	 */
	return strtoupper( (string) apply_filters( 'blueworx_klaviyo_store_currency', $currency ) );
}

/**
 * A plain word for a subscription's state.
 *
 * @param string $status SureCart's status.
 * @return string active, past_due or cancelled.
 */
function blueworx_klaviyo_store_status( $status ) {
	if ( in_array( $status, array( 'past_due', 'unpaid' ), true ) ) {
		return 'past_due';
	}

	return in_array( $status, array( 'canceled', 'completed' ), true ) ? 'cancelled' : 'active';
}

/**
 * Whether the checkout's marketing box was ticked.
 *
 * @param array  $checkout The checkout, with its metadata.
 * @param string $box      The box's name.
 * @return bool
 */
function blueworx_klaviyo_store_box_ticked( $checkout, $box ) {
	$metadata = isset( $checkout['metadata'] ) && is_array( $checkout['metadata'] ) ? $checkout['metadata'] : array();

	return '' !== (string) $box && isset( $metadata[ $box ] ) && blueworx_klaviyo_is_ticked( $metadata[ $box ] );
}

// --- Plans --------------------------------------------------------------------

/**
 * The details every subscription event and profile update shares.
 *
 * @param array $subscription SureCart subscription, with its price, product,
 *                            customer and current period expanded.
 * @return array|null The details, or null when it cannot be matched to a person.
 */
function blueworx_klaviyo_store_details( $subscription ) {
	$customer = isset( $subscription['customer'] ) && is_array( $subscription['customer'] ) ? $subscription['customer'] : array();
	$price    = isset( $subscription['price'] ) && is_array( $subscription['price'] ) ? $subscription['price'] : array();
	$product  = isset( $price['product'] ) && is_array( $price['product'] ) ? $price['product'] : array();

	if ( empty( $customer['email'] ) ) {
		return null;
	}

	$ending = ! empty( $subscription['cancel_at_period_end'] ) || ! empty( $subscription['ended_at'] );

	return array(
		'email'            => $customer['email'],
		'first_name'       => isset( $customer['first_name'] ) ? $customer['first_name'] : '',
		'last_name'        => isset( $customer['last_name'] ) ? $customer['last_name'] : '',
		'customer_id'      => isset( $customer['id'] ) ? $customer['id'] : '',
		'subscription_id'  => isset( $subscription['id'] ) ? $subscription['id'] : '',
		'price_id'         => isset( $price['id'] ) ? $price['id'] : '',
		'status'           => isset( $subscription['status'] ) ? $subscription['status'] : '',
		'product_name'     => isset( $product['name'] ) ? $product['name'] : '',
		'product_slug'     => isset( $product['slug'] ) ? $product['slug'] : '',
		'product_id'       => isset( $product['id'] ) ? $product['id'] : '',
		'billing_interval' => blueworx_klaviyo_store_interval( isset( $price['recurring_interval'] ) ? $price['recurring_interval'] : '' ),
		'amount'           => blueworx_klaviyo_store_money( isset( $price['amount'] ) ? $price['amount'] : 0, ! empty( $price['is_zero_decimal'] ) ),
		'currency'         => blueworx_klaviyo_store_currency( $price, $subscription ),
		'zero_decimal'     => ! empty( $price['is_zero_decimal'] ),
		'start_date'       => blueworx_klaviyo_store_date( isset( $subscription['created_at'] ) ? $subscription['created_at'] : 0 ),
		'next_renewal'     => $ending ? '' : blueworx_klaviyo_store_date( isset( $subscription['current_period_end_at'] ) ? $subscription['current_period_end_at'] : 0 ),
	);
}

/**
 * The properties every subscription event carries.
 *
 * @param array $details From blueworx_klaviyo_store_details().
 * @return array
 */
function blueworx_klaviyo_store_event_base( $details ) {
	return array(
		'product_name'          => $details['product_name'],
		'product_slug'          => $details['product_slug'],
		'product_id'            => $details['product_id'],
		'price_id'              => $details['price_id'],
		'billing_interval'      => $details['billing_interval'],
		'amount'                => $details['amount'],
		'currency'              => $details['currency'],
		'subscription_id'       => $details['subscription_id'],
		'membership_start_date' => $details['start_date'],
		'next_renewal_date'     => $details['next_renewal'],
	);
}

/**
 * The profile properties every subscription update sets.
 *
 * @param array $details From blueworx_klaviyo_store_details().
 * @return array
 */
function blueworx_klaviyo_store_profile_base( $details ) {
	return array(
		'surecart_customer_id' => $details['customer_id'],
		'membership_status'    => blueworx_klaviyo_store_status( $details['status'] ),
		'membership_plan'      => $details['product_name'],
		'billing_interval'     => $details['billing_interval'],
		'next_renewal_date'    => $details['next_renewal'],
	);
}

/**
 * Placed Order: a one-off purchase, with no subscription.
 *
 * @param array $purchase SureCart purchase, with its product and initial order expanded.
 * @param array $settings Store settings.
 * @return array|null The plan.
 */
function blueworx_klaviyo_store_plan_order( $purchase, $settings ) {
	$order    = isset( $purchase['initial_order'] ) && is_array( $purchase['initial_order'] ) ? $purchase['initial_order'] : array();
	$checkout = isset( $order['checkout'] ) && is_array( $order['checkout'] ) ? $order['checkout'] : array();
	$customer = isset( $checkout['customer'] ) && is_array( $checkout['customer'] ) ? $checkout['customer'] : ( isset( $purchase['customer'] ) && is_array( $purchase['customer'] ) ? $purchase['customer'] : array() );
	$product  = isset( $purchase['product'] ) && is_array( $purchase['product'] ) ? $purchase['product'] : array();

	if ( empty( $customer['email'] ) ) {
		return null;
	}

	$paid   = isset( $checkout['paid_amount'] ) ? $checkout['paid_amount'] : ( isset( $checkout['amount_due'] ) ? $checkout['amount_due'] : 0 );
	$value  = blueworx_klaviyo_store_money( $paid, ! empty( $checkout['is_zero_decimal'] ) );
	$ticked = blueworx_klaviyo_store_box_ticked( $checkout, $settings['checkout_box'] );
	$when   = isset( $checkout['paid_at'] ) && $checkout['paid_at'] ? $checkout['paid_at'] : time();
	$coupon = isset( $checkout['discount']['promotion']['code'] ) ? $checkout['discount']['promotion']['code'] : '';

	$set = array(
		'surecart_customer_id' => isset( $customer['id'] ) ? $customer['id'] : '',
		'last_order_date'      => blueworx_klaviyo_store_date( $when ),
	);

	if ( $ticked ) {
		$set['marketing_consent'] = true;
	}

	return array(
		'label'   => 'Placed Order',
		'email'   => $customer['email'],
		'fill'    => array(
			'first_name' => isset( $customer['first_name'] ) ? $customer['first_name'] : '',
			'last_name'  => isset( $customer['last_name'] ) ? $customer['last_name'] : '',
		),
		'profile' => $set,
		'event'   => array(
			'name'       => 'Placed Order',
			'unique_id'  => 'order-' . ( isset( $order['id'] ) ? $order['id'] : ( isset( $purchase['id'] ) ? $purchase['id'] : '' ) ),
			'value'      => $value,
			'currency'   => blueworx_klaviyo_store_currency( $checkout, $purchase ),
			'time'       => blueworx_klaviyo_store_timestamp( $when ),
			'properties' => array(
				'product_name' => isset( $product['name'] ) ? $product['name'] : '',
				'product_slug' => isset( $product['slug'] ) ? $product['slug'] : '',
				'product_id'   => isset( $product['id'] ) ? $product['id'] : ( isset( $purchase['product'] ) && is_string( $purchase['product'] ) ? $purchase['product'] : '' ),
				'quantity'     => isset( $purchase['quantity'] ) ? (int) $purchase['quantity'] : 1,
				'amount'       => $value,
				'currency'     => blueworx_klaviyo_store_currency( $checkout, $purchase ),
				'order_id'     => isset( $order['id'] ) ? $order['id'] : '',
				'coupon_code'  => $coupon,
			),
		),
		'list'    => $ticked && '' !== $settings['list'] ? $settings['list'] : '',
	);
}

/**
 * Started Subscription: the first successful payment on a new subscription.
 *
 * @param array $subscription SureCart subscription, expanded.
 * @param array $order        The first order, with its checkout expanded.
 * @param array $settings     Store settings.
 * @return array|null The plan.
 */
function blueworx_klaviyo_store_plan_started( $subscription, $order, $settings ) {
	$details = blueworx_klaviyo_store_details( $subscription );

	if ( ! $details ) {
		return null;
	}

	$checkout = isset( $order['checkout'] ) && is_array( $order['checkout'] ) ? $order['checkout'] : array();
	$coupon   = isset( $checkout['discount']['promotion']['code'] ) ? $checkout['discount']['promotion']['code'] : '';
	$paid     = isset( $checkout['paid_amount'] ) ? $checkout['paid_amount'] : ( isset( $checkout['amount_due'] ) ? $checkout['amount_due'] : 0 );
	$value    = blueworx_klaviyo_store_money( $paid, $details['zero_decimal'] );
	$ticked   = blueworx_klaviyo_store_box_ticked( $checkout, $settings['checkout_box'] );
	$when     = isset( $checkout['paid_at'] ) && $checkout['paid_at'] ? $checkout['paid_at'] : time();

	$set = blueworx_klaviyo_store_profile_base( $details );

	$set['membership_start_date'] = $details['start_date'];
	$set['last_order_date']       = blueworx_klaviyo_store_date( $when );

	if ( $ticked ) {
		$set['marketing_consent'] = true;
	}

	return array(
		'label'   => 'Started Subscription',
		'email'   => $details['email'],
		'fill'    => array(
			'first_name' => $details['first_name'],
			'last_name'  => $details['last_name'],
		),
		'profile' => $set,
		'event'   => array(
			'name'       => 'Started Subscription',
			'unique_id'  => 'started-' . $details['subscription_id'],
			'value'      => $value,
			'currency'   => $details['currency'],
			'time'       => blueworx_klaviyo_store_timestamp( $when ),
			'properties' => array_merge(
				blueworx_klaviyo_store_event_base( $details ),
				array(
					'order_id'    => isset( $order['id'] ) ? $order['id'] : '',
					'coupon_code' => $coupon,
				)
			),
		),
		'list'    => $ticked && '' !== $settings['list'] ? $settings['list'] : '',
		'after'   => array(
			'subscription' => $details['subscription_id'],
			'state'        => array(
				'price'  => $details['price_id'],
				'status' => $details['status'],
			),
		),
	);
}

/**
 * Subscription Renewed: each later payment, never the first.
 *
 * @param array    $subscription   SureCart subscription, expanded, with its current period's checkout.
 * @param int|null $payment_number Which payment this was, when it can be counted.
 * @return array|null The plan.
 */
function blueworx_klaviyo_store_plan_renewed( $subscription, $payment_number ) {
	$details = blueworx_klaviyo_store_details( $subscription );

	if ( ! $details ) {
		return null;
	}

	$period   = isset( $subscription['current_period'] ) && is_array( $subscription['current_period'] ) ? $subscription['current_period'] : array();
	$checkout = isset( $period['checkout'] ) && is_array( $period['checkout'] ) ? $period['checkout'] : array();
	$paid     = isset( $checkout['amount_due'] ) ? $checkout['amount_due'] : ( isset( $subscription['price']['amount'] ) ? $subscription['price']['amount'] : 0 );
	$value    = blueworx_klaviyo_store_money( $paid, $details['zero_decimal'] );

	$set                    = blueworx_klaviyo_store_profile_base( $details );
	$set['last_order_date'] = wp_date( 'Y-m-d' );

	return array(
		'label'   => 'Subscription Renewed',
		'email'   => $details['email'],
		'profile' => $set,
		'event'   => array(
			'name'       => 'Subscription Renewed',
			'unique_id'  => 'renewed-' . ( isset( $period['id'] ) ? $period['id'] : $details['subscription_id'] ),
			'value'      => $value,
			'currency'   => $details['currency'],
			'properties' => array_merge(
				blueworx_klaviyo_store_event_base( $details ),
				array( 'payment_number' => $payment_number )
			),
		),
	);
}

/**
 * Payment Failed.
 *
 * Reached from two SureCart events for one failure, so the attempt goes in the
 * unique ID and Klaviyo records it once however many of them arrive.
 *
 * @param array $subscription SureCart subscription, expanded.
 * @return array|null The plan.
 */
function blueworx_klaviyo_store_plan_failed( $subscription ) {
	$details = blueworx_klaviyo_store_details( $subscription );

	if ( ! $details ) {
		return null;
	}

	$period  = isset( $subscription['current_period'] ) && is_array( $subscription['current_period'] ) ? $subscription['current_period'] : array();
	$attempt = isset( $period['payment_retry_count'] ) ? (int) $period['payment_retry_count'] : 0;
	$set     = blueworx_klaviyo_store_profile_base( $details );

	$set['membership_status'] = 'past_due';

	return array(
		'label'   => 'Payment Failed',
		'email'   => $details['email'],
		'profile' => $set,
		'event'   => array(
			'name'       => 'Payment Failed',
			'unique_id'  => 'failed-' . ( isset( $period['id'] ) ? $period['id'] : $details['subscription_id'] ) . '-' . $attempt,
			'properties' => array_merge(
				blueworx_klaviyo_store_event_base( $details ),
				array(
					'attempt_count'   => $attempt,
					'next_retry_date' => blueworx_klaviyo_store_date( isset( $period['next_payment_retry_at'] ) ? $period['next_payment_retry_at'] : 0 ),
				)
			),
		),
	);
}

/**
 * Subscription Cancelled, when the member cancels or the store cancels for them.
 *
 * Cancelling normally leaves access running to the end of the paid period, so
 * they stay active and only membership_status changes when access ends.
 *
 * @param array $subscription SureCart subscription, expanded.
 * @return array|null The plan.
 */
function blueworx_klaviyo_store_plan_cancelled( $subscription ) {
	$details = blueworx_klaviyo_store_details( $subscription );

	if ( ! $details ) {
		return null;
	}

	$act     = isset( $subscription['current_cancellation_act'] ) && is_array( $subscription['current_cancellation_act'] ) ? $subscription['current_cancellation_act'] : array();
	$reason  = isset( $act['cancellation_reason']['label'] ) ? $act['cancellation_reason']['label'] : '';
	$comment = isset( $act['comment'] ) ? $act['comment'] : '';
	$ended   = isset( $subscription['ended_at'] ) ? $subscription['ended_at'] : '';
	$ends    = blueworx_klaviyo_store_date( $ended ? $ended : ( isset( $subscription['current_period_end_at'] ) ? $subscription['current_period_end_at'] : 0 ) );
	$when    = isset( $act['created_at'] ) ? $act['created_at'] : time();
	$set     = blueworx_klaviyo_store_profile_base( $details );

	$set['membership_end_date'] = $ends;

	return array(
		'label'   => 'Subscription Cancelled',
		'email'   => $details['email'],
		'profile' => $set,
		'event'   => array(
			'name'       => 'Subscription Cancelled',
			// Keyed on the cancellation itself, so someone who cancels, changes
			// their mind and cancels again still gets a second event.
			'unique_id'  => 'cancelled-' . $details['subscription_id'] . '-' . ( isset( $act['id'] ) ? $act['id'] : $ends ),
			'properties' => array_merge(
				blueworx_klaviyo_store_event_base( $details ),
				array(
					'cancelled_at'   => blueworx_klaviyo_store_date( $when ),
					'access_ends_at' => $ends,
					'cancel_reason'  => trim( $reason . ( '' !== $reason && '' !== $comment ? ': ' : '' ) . $comment ),
				)
			),
		),
		'after'   => array(
			'subscription' => $details['subscription_id'],
			'state'        => array( 'cancel_sent' => true ),
		),
	);
}

/**
 * Plan Changed: monthly to annual, or one product to another.
 *
 * Moving between monthly and annual keeps the same product, so the interval
 * has to be in the name or from_plan and to_plan would read the same.
 *
 * @param array $subscription SureCart subscription, expanded.
 * @param array $old_price    The price they moved from, with its product.
 * @return array|null The plan.
 */
function blueworx_klaviyo_store_plan_changed( $subscription, $old_price ) {
	$details = blueworx_klaviyo_store_details( $subscription );

	if ( ! $details ) {
		return null;
	}

	$old_name = isset( $old_price['product']['name'] ) ? $old_price['product']['name'] : $details['product_name'];
	$old_word = blueworx_klaviyo_store_interval( isset( $old_price['recurring_interval'] ) ? $old_price['recurring_interval'] : '' );
	$from     = trim( $old_name . ( '' !== $old_word ? ' (' . $old_word . ')' : '' ) );
	$to       = $details['product_name'] . ( '' !== $details['billing_interval'] ? ' (' . $details['billing_interval'] . ')' : '' );

	return array(
		'label'   => 'Plan Changed',
		'email'   => $details['email'],
		'profile' => blueworx_klaviyo_store_profile_base( $details ),
		'event'   => array(
			'name'       => 'Plan Changed',
			'unique_id'  => 'changed-' . $details['subscription_id'] . '-' . ( isset( $old_price['id'] ) ? $old_price['id'] : '' ) . '-' . $details['price_id'],
			'properties' => array_merge(
				blueworx_klaviyo_store_event_base( $details ),
				array(
					'from_plan' => $from,
					'to_plan'   => $to,
				)
			),
		),
		'after'   => array(
			'subscription' => $details['subscription_id'],
			'state'        => array( 'price' => $details['price_id'] ),
		),
	);
}

/**
 * Refunded. The amount is a plain property, never revenue.
 *
 * @param array $refund SureCart refund, with its customer and charge expanded.
 * @return array|null The plan.
 */
function blueworx_klaviyo_store_plan_refunded( $refund ) {
	$customer = isset( $refund['customer'] ) && is_array( $refund['customer'] ) ? $refund['customer'] : array();
	$checkout = isset( $refund['charge']['checkout'] ) && is_array( $refund['charge']['checkout'] ) ? $refund['charge']['checkout'] : array();
	$items    = isset( $checkout['line_items']['data'] ) && is_array( $checkout['line_items']['data'] ) ? $checkout['line_items']['data'] : array();
	$price    = isset( $items[0]['price'] ) && is_array( $items[0]['price'] ) ? $items[0]['price'] : array();

	if ( empty( $customer['email'] ) ) {
		return null;
	}

	$order = isset( $checkout['order'] ) ? $checkout['order'] : '';
	$name  = isset( $price['product']['name'] ) ? $price['product']['name'] : '';

	return array(
		'label'   => 'Refunded',
		'email'   => $customer['email'],
		'profile' => array(
			'surecart_customer_id' => isset( $customer['id'] ) ? $customer['id'] : '',
		),
		'event'   => array(
			'name'       => 'Refunded',
			'unique_id'  => 'refunded-' . ( isset( $refund['id'] ) ? $refund['id'] : '' ),
			'properties' => array(
				'amount'           => blueworx_klaviyo_store_money( isset( $refund['amount'] ) ? $refund['amount'] : 0, ! empty( $price['is_zero_decimal'] ) ),
				'currency'         => blueworx_klaviyo_store_currency( $refund, $checkout, $price ),
				'order_id'         => is_array( $order ) ? ( isset( $order['id'] ) ? $order['id'] : '' ) : $order,
				'reason'           => isset( $refund['reason'] ) ? $refund['reason'] : '',
				'product_name'     => $name,
				'product_slug'     => isset( $price['product']['slug'] ) ? $price['product']['slug'] : '',
				'billing_interval' => blueworx_klaviyo_store_interval( isset( $price['recurring_interval'] ) ? $price['recurring_interval'] : '' ),
			),
		),
	);
}

/**
 * A profile update with no event, for changes that have no event of their own:
 * access ending after a cancellation, payment recovering, a cancellation undone.
 *
 * @param array $subscription SureCart subscription, expanded.
 * @param array $extra        Extra properties to set.
 * @param array $remove       Properties to remove.
 * @return array|null The plan.
 */
function blueworx_klaviyo_store_plan_status( $subscription, $extra = array(), $remove = array() ) {
	$details = blueworx_klaviyo_store_details( $subscription );

	if ( ! $details ) {
		return null;
	}

	return array(
		'label'   => 'Subscription status update',
		'email'   => $details['email'],
		'profile' => array_merge( blueworx_klaviyo_store_profile_base( $details ), $extra ),
		'unset'   => $remove,
	);
}

// --- Memory -------------------------------------------------------------------

/**
 * What the connection remembers about one subscription, between events.
 *
 * @param string $subscription_id Subscription ID.
 * @return array price, status and cancel_sent, as last seen.
 */
function blueworx_klaviyo_store_recall( $subscription_id ) {
	$state = get_option( BLUEWORX_KLAVIYO_SUB_PREFIX . md5( (string) $subscription_id ), array() );

	return is_array( $state ) ? $state : array();
}

/**
 * Adds to what the connection remembers about one subscription.
 *
 * @param string $subscription_id Subscription ID.
 * @param array  $state           What to remember.
 * @return void
 */
function blueworx_klaviyo_store_remember( $subscription_id, $state ) {
	if ( '' === (string) $subscription_id ) {
		return;
	}

	update_option( BLUEWORX_KLAVIYO_SUB_PREFIX . md5( (string) $subscription_id ), array_merge( blueworx_klaviyo_store_recall( $subscription_id ), (array) $state ), 'no' );
}

/**
 * Remembers a subscription's state once its plan has gone through.
 *
 * @param array $plan The plan that went through.
 * @return void
 */
function blueworx_klaviyo_store_on_sent( $plan ) {
	if ( ! empty( $plan['after']['subscription'] ) && isset( $plan['after']['state'] ) ) {
		blueworx_klaviyo_store_remember( $plan['after']['subscription'], $plan['after']['state'] );
	}
}
add_action( 'blueworx_klaviyo_sent', 'blueworx_klaviyo_store_on_sent' );

/**
 * Whether a SureCart event has been received before.
 *
 * @param string $event_id SureCart's ID for the event.
 * @return bool
 */
function blueworx_klaviyo_store_seen( $event_id ) {
	return false !== get_option( BLUEWORX_KLAVIYO_SEEN_PREFIX . md5( (string) $event_id ), false );
}

/**
 * Records that a SureCart event has been received.
 *
 * @param string $event_id SureCart's ID for the event.
 * @return void
 */
function blueworx_klaviyo_store_mark_seen( $event_id ) {
	add_option( BLUEWORX_KLAVIYO_SEEN_PREFIX . md5( (string) $event_id ), time(), '', 'no' );
}

/**
 * Clears out old markers, at most once a day.
 *
 * @return void
 */
function blueworx_klaviyo_store_prune_seen() {
	global $wpdb;

	if ( get_transient( 'blueworx_klaviyo_pruned' ) ) {
		return;
	}

	set_transient( 'blueworx_klaviyo_pruned', 1, DAY_IN_SECONDS );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Housekeeping over a family of rows no API can list.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1000",
			$wpdb->esc_like( BLUEWORX_KLAVIYO_SEEN_PREFIX ) . '%'
		)
	);

	$cutoff = time() - BLUEWORX_KLAVIYO_SEEN_DAYS * DAY_IN_SECONDS;

	foreach ( (array) $rows as $row ) {
		if ( (int) $row->option_value < $cutoff ) {
			delete_option( $row->option_name );
		}
	}
}
add_action( 'blueworx_klaviyo_tick', 'blueworx_klaviyo_store_prune_seen', 20 );

// --- The webhook --------------------------------------------------------------

/**
 * Adds the address, only while the function is on.
 *
 * @return void
 */
function blueworx_klaviyo_store_register_route() {
	if ( ! blueworx_klaviyo_enabled() ) {
		return;
	}

	register_rest_route(
		'blueworx/v1',
		'/klaviyo/surecart',
		array(
			'methods'             => 'POST',
			'callback'            => 'blueworx_klaviyo_store_receive',
			// The signature checked in the callback is the permission: SureCart
			// has no WordPress account, and the check needs the raw body anyway.
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'blueworx_klaviyo_store_register_route' );

/**
 * Checks an event really came from SureCart.
 *
 * Refuses everything until a secret is set. An old timestamp is refused too,
 * so a captured request cannot be replayed; SureCart signs each retry afresh,
 * so a genuine retry is never caught by this.
 *
 * @param string   $body      Raw request body.
 * @param string   $signature The x-webhook-signature header.
 * @param string   $timestamp The x-webhook-timestamp header.
 * @param string   $secret    Signing secret.
 * @param int|null $now       Current time, for tests.
 * @return bool
 */
function blueworx_klaviyo_store_signature_valid( $body, $signature, $timestamp, $secret, $now = null ) {
	if ( '' === (string) $secret || '' === (string) $signature || ! is_numeric( $timestamp ) ) {
		return false;
	}

	$now = null === $now ? time() : $now;

	if ( abs( $now - (int) $timestamp ) > 5 * MINUTE_IN_SECONDS ) {
		return false;
	}

	return hash_equals( hash_hmac( 'sha256', $timestamp . '.' . $body, (string) $secret ), (string) $signature );
}

/**
 * Handles one delivery from SureCart.
 *
 * @param WP_REST_Request $request The delivery.
 * @return WP_REST_Response|WP_Error
 */
function blueworx_klaviyo_store_receive( $request ) {
	$body = (string) $request->get_body();

	if ( ! blueworx_klaviyo_store_signature_valid( $body, (string) $request->get_header( 'x-webhook-signature' ), (string) $request->get_header( 'x-webhook-timestamp' ), blueworx_klaviyo_store_secret() ) ) {
		// Noted for the settings screen, which is where a wrong secret gets
		// noticed. Not a log line: anyone can post here, and would push out the
		// real ones.
		if ( (int) blueworx_klaviyo_setting( 'store_last_refused', 0 ) < time() - MINUTE_IN_SECONDS ) {
			blueworx_klaviyo_update_setting( 'store_last_refused', time() );
		}

		return new WP_Error( 'blueworx_klaviyo_bad_signature', 'Invalid signature.', array( 'status' => 401 ) );
	}

	$event = json_decode( $body, true );

	if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['type'] ) ) {
		return new WP_Error( 'blueworx_klaviyo_bad_event', 'Not a SureCart event.', array( 'status' => 400 ) );
	}

	return new WP_REST_Response( array( 'status' => blueworx_klaviyo_store_accept_event( $event ) ), 200 );
}

/**
 * Decides what to do with a genuine event, and queues it if it matters.
 *
 * @param array $event The decoded event.
 * @return string What happened: queued, repeat, ignored or test_mode.
 */
function blueworx_klaviyo_store_accept_event( $event ) {
	if ( ! in_array( $event['type'], blueworx_klaviyo_store_events(), true ) ) {
		return 'ignored';
	}

	$object   = isset( $event['data']['object'] ) && is_array( $event['data']['object'] ) ? $event['data']['object'] : array();
	$settings = blueworx_klaviyo_store_settings();

	if ( isset( $object['live_mode'] ) && false === $object['live_mode'] && '1' !== $settings['test_mode'] ) {
		return 'test_mode';
	}

	if ( blueworx_klaviyo_store_seen( $event['id'] ) ) {
		return 'repeat';
	}

	blueworx_klaviyo_store_mark_seen( $event['id'] );

	blueworx_klaviyo_queue_job(
		'surecart:' . $event['id'],
		array(
			'label'    => $event['type'],
			'resolver' => 'surecart',
			'data'     => array(
				'type'     => $event['type'],
				'id'       => isset( $object['id'] ) ? $object['id'] : '',
				'previous' => isset( $event['data']['previous_attributes'] ) && is_array( $event['data']['previous_attributes'] ) ? $event['data']['previous_attributes'] : array(),
			),
		)
	);

	return 'queued';
}

// --- Looking records up -------------------------------------------------------

/**
 * Looks up one SureCart record, with what the plans need expanded.
 *
 * The filter is the seam the tests use, since there is no SureCart account to
 * talk to from a test site.
 *
 * @param string $type subscription, purchase, order, refund or price.
 * @param string $id   Record ID.
 * @return array|WP_Error The record as plain arrays.
 */
function blueworx_klaviyo_surecart_record( $type, $id ) {
	/**
	 * Stands in for a SureCart record. Return the record as plain arrays, a
	 * WP_Error, or null to let SureCart answer.
	 *
	 * @param null   $short Nothing yet.
	 * @param string $type  Record type.
	 * @param string $id    Record ID.
	 */
	$short = apply_filters( 'blueworx_klaviyo_surecart_record', null, $type, $id );

	if ( null !== $short ) {
		return $short;
	}

	$expand = array(
		'subscription' => array( 'customer', 'price', 'price.product', 'current_period', 'period.checkout', 'current_cancellation_act', 'cancellation_act.cancellation_reason' ),
		'purchase'     => array( 'product', 'customer', 'initial_order', 'order.checkout', 'checkout.customer', 'checkout.discount', 'discount.promotion' ),
		'order'        => array( 'checkout', 'checkout.customer' ),
		'refund'       => array( 'customer', 'charge', 'charge.checkout', 'checkout.line_items', 'line_item.price', 'price.product' ),
		'price'        => array( 'product' ),
	);

	$classes = array(
		'subscription' => '\SureCart\Models\Subscription',
		'purchase'     => '\SureCart\Models\Purchase',
		'order'        => '\SureCart\Models\Order',
		'refund'       => '\SureCart\Models\Refund',
		'price'        => '\SureCart\Models\Price',
	);

	if ( ! isset( $classes[ $type ] ) || ! class_exists( $classes[ $type ] ) ) {
		return new WP_Error( 'blueworx_klaviyo_no_surecart', 'SureCart is not available.' );
	}

	$record = $classes[ $type ]::with( $expand[ $type ] )->find( $id );

	// The reason for a cancellation is a nicety. If asking for it is what
	// failed, ask again without it rather than lose the event.
	if ( is_wp_error( $record ) && 'subscription' === $type ) {
		$record = $classes[ $type ]::with( array_slice( $expand[ $type ], 0, 5 ) )->find( $id );
	}

	if ( is_wp_error( $record ) ) {
		return $record;
	}

	return blueworx_klaviyo_surecart_plain( $record );
}

/**
 * Turns SureCart's models into plain arrays, however deep they go.
 *
 * @param mixed $record A model or array.
 * @return array
 */
function blueworx_klaviyo_surecart_plain( $record ) {
	$plain = json_decode( (string) wp_json_encode( is_object( $record ) && method_exists( $record, 'toArray' ) ? $record->toArray() : $record ), true );

	return is_array( $plain ) ? $plain : array();
}

/**
 * Counts how many payments a subscription has made, for payment_number.
 *
 * A figure that cannot be worked out is left off rather than guessed.
 *
 * @param string $subscription_id Subscription ID.
 * @return int|null
 */
function blueworx_klaviyo_store_payment_number( $subscription_id ) {
	/**
	 * Stands in for counting a subscription's paid periods.
	 *
	 * @param null   $short           Nothing yet.
	 * @param string $subscription_id Subscription ID.
	 */
	$short = apply_filters( 'blueworx_klaviyo_payment_number', null, $subscription_id );

	if ( null !== $short ) {
		return $short;
	}

	if ( ! class_exists( '\SureCart\Models\Period' ) ) {
		return null;
	}

	$periods = \SureCart\Models\Period::where( array( 'subscription_ids' => array( $subscription_id ) ) )->paginate( array( 'per_page' => 100 ) );

	if ( is_wp_error( $periods ) || empty( $periods->data ) ) {
		return null;
	}

	$paid = 0;

	foreach ( $periods->data as $period ) {
		if ( 'paid' === ( isset( $period->status ) ? $period->status : '' ) ) {
			++$paid;
		}
	}

	return $paid > 0 ? $paid : null;
}

/**
 * Finds the subscription a failed order was for.
 *
 * Orders do not name their subscription, so this looks through the customer's
 * subscriptions for the one whose current period is that checkout. A failed
 * first payment has no subscription yet, which is why this can come back empty.
 *
 * @param array $order The order, with its checkout expanded.
 * @return string Subscription ID, or an empty string.
 */
function blueworx_klaviyo_store_subscription_for_order( $order ) {
	/**
	 * Stands in for finding a failed order's subscription.
	 *
	 * @param null  $short Nothing yet.
	 * @param array $order The order.
	 */
	$short = apply_filters( 'blueworx_klaviyo_subscription_for_order', null, $order );

	if ( null !== $short ) {
		return (string) $short;
	}

	$checkout = isset( $order['checkout'] ) && is_array( $order['checkout'] ) ? $order['checkout'] : array();
	$customer = isset( $checkout['customer'] ) ? $checkout['customer'] : '';
	$customer = is_array( $customer ) ? ( isset( $customer['id'] ) ? $customer['id'] : '' ) : $customer;

	if ( '' === (string) $customer || empty( $checkout['id'] ) || ! class_exists( '\SureCart\Models\Subscription' ) ) {
		return '';
	}

	$subscriptions = \SureCart\Models\Subscription::where( array( 'customer_ids' => array( $customer ) ) )->with( array( 'current_period' ) )->get();

	if ( is_wp_error( $subscriptions ) ) {
		return '';
	}

	foreach ( (array) $subscriptions as $subscription ) {
		$plain = blueworx_klaviyo_surecart_plain( $subscription );

		if ( isset( $plain['current_period']['checkout'] ) && $plain['current_period']['checkout'] === $checkout['id'] ) {
			return isset( $plain['id'] ) ? (string) $plain['id'] : '';
		}
	}

	return '';
}

// --- Working out what an event means ------------------------------------------

/**
 * Answers the queue's resolver for SureCart jobs.
 *
 * @param mixed  $plans    Nothing yet, or another resolver's answer.
 * @param string $resolver The job's resolver name.
 * @param array  $data     What the job was queued with.
 * @return mixed Plans, a WP_Error, or whatever came in.
 */
function blueworx_klaviyo_store_resolve( $plans, $resolver, $data ) {
	if ( 'surecart' !== $resolver ) {
		return $plans;
	}

	return blueworx_klaviyo_store_plans( $data, blueworx_klaviyo_store_settings() );
}
add_filter( 'blueworx_klaviyo_resolve_job', 'blueworx_klaviyo_store_resolve', 10, 3 );

/**
 * Works out what a SureCart event means for Klaviyo.
 *
 * @param array $webhook  type, id of the record it is about, and previous (what changed, when SureCart says).
 * @param array $settings Store settings.
 * @return array|WP_Error Plans to send, possibly none, or an error worth a retry.
 */
function blueworx_klaviyo_store_plans( $webhook, $settings ) {
	$type = isset( $webhook['type'] ) ? $webhook['type'] : '';
	$id   = isset( $webhook['id'] ) ? (string) $webhook['id'] : '';

	switch ( $type ) {
		case 'purchase.created':
			return blueworx_klaviyo_store_plans_for_purchase( $id, $settings );

		case 'subscription.renewed':
			return blueworx_klaviyo_store_plans_for_renewal( $id );

		case 'order.payment_failed':
			return blueworx_klaviyo_store_plans_for_failed_order( $id );

		case 'subscription.set_to_cancel':
		case 'subscription.canceled':
			return blueworx_klaviyo_store_plans_for_cancel( $id );

		case 'subscription.updated':
			return blueworx_klaviyo_store_plans_for_update( $id, isset( $webhook['previous'] ) ? (array) $webhook['previous'] : array() );

		case 'refund.succeeded':
			$refund = blueworx_klaviyo_surecart_record( 'refund', $id );
			return is_wp_error( $refund ) ? $refund : array_values( array_filter( array( blueworx_klaviyo_store_plan_refunded( $refund ) ) ) );
	}

	return array();
}

/**
 * A new purchase: Started Subscription when it carries one, Placed Order otherwise.
 *
 * @param string $purchase_id Purchase ID.
 * @param array  $settings    Store settings.
 * @return array|WP_Error
 */
function blueworx_klaviyo_store_plans_for_purchase( $purchase_id, $settings ) {
	$purchase = blueworx_klaviyo_surecart_record( 'purchase', $purchase_id );

	if ( is_wp_error( $purchase ) ) {
		return $purchase;
	}

	$subscription_id = isset( $purchase['subscription'] ) ? $purchase['subscription'] : '';
	$subscription_id = is_array( $subscription_id ) ? ( isset( $subscription_id['id'] ) ? $subscription_id['id'] : '' ) : (string) $subscription_id;

	if ( '' === $subscription_id ) {
		return array_values( array_filter( array( blueworx_klaviyo_store_plan_order( $purchase, $settings ) ) ) );
	}

	$subscription = blueworx_klaviyo_surecart_record( 'subscription', $subscription_id );

	if ( is_wp_error( $subscription ) ) {
		return $subscription;
	}

	$order = isset( $purchase['initial_order'] ) && is_array( $purchase['initial_order'] ) ? $purchase['initial_order'] : array();

	return array_values( array_filter( array( blueworx_klaviyo_store_plan_started( $subscription, $order, $settings ) ) ) );
}

/**
 * Subscription Renewed, once the renewal has really been paid.
 *
 * If SureCart reports the renewal before the money has settled this asks to be
 * tried again, and a renewal that never gets paid ends up in the log as failed.
 *
 * @param string $subscription_id Subscription ID.
 * @return array|WP_Error
 */
function blueworx_klaviyo_store_plans_for_renewal( $subscription_id ) {
	$subscription = blueworx_klaviyo_surecart_record( 'subscription', $subscription_id );

	if ( is_wp_error( $subscription ) ) {
		return $subscription;
	}

	$status = isset( $subscription['current_period']['status'] ) ? $subscription['current_period']['status'] : '';

	if ( '' !== $status && 'paid' !== $status ) {
		return new WP_Error( 'blueworx_klaviyo_not_paid', 'SureCart has not confirmed this renewal as paid yet.' );
	}

	return array_values( array_filter( array( blueworx_klaviyo_store_plan_renewed( $subscription, blueworx_klaviyo_store_payment_number( $subscription_id ) ) ) ) );
}

/**
 * Payment Failed, from a failed order.
 *
 * @param string $order_id Order ID.
 * @return array|WP_Error
 */
function blueworx_klaviyo_store_plans_for_failed_order( $order_id ) {
	$order = blueworx_klaviyo_surecart_record( 'order', $order_id );

	if ( is_wp_error( $order ) ) {
		return $order;
	}

	$subscription_id = blueworx_klaviyo_store_subscription_for_order( $order );

	if ( '' === $subscription_id ) {
		return array();
	}

	$subscription = blueworx_klaviyo_surecart_record( 'subscription', $subscription_id );

	if ( is_wp_error( $subscription ) ) {
		return $subscription;
	}

	return array_values( array_filter( array( blueworx_klaviyo_store_plan_failed( $subscription ) ) ) );
}

/**
 * Subscription Cancelled, or just the status when that has already been sent.
 *
 * Setting a subscription to cancel sends the event. When access then ends,
 * only the status changes, so one cancellation is one event.
 *
 * @param string $subscription_id Subscription ID.
 * @return array|WP_Error
 */
function blueworx_klaviyo_store_plans_for_cancel( $subscription_id ) {
	$subscription = blueworx_klaviyo_surecart_record( 'subscription', $subscription_id );

	if ( is_wp_error( $subscription ) ) {
		return $subscription;
	}

	$state = blueworx_klaviyo_store_recall( $subscription_id );

	if ( ! empty( $state['cancel_sent'] ) ) {
		$ended = isset( $subscription['ended_at'] ) ? $subscription['ended_at'] : '';

		return array_values(
			array_filter(
				array(
					blueworx_klaviyo_store_plan_status(
						$subscription,
						$ended ? array( 'membership_end_date' => blueworx_klaviyo_store_date( $ended ) ) : array()
					),
				)
			)
		);
	}

	return array_values( array_filter( array( blueworx_klaviyo_store_plan_cancelled( $subscription ) ) ) );
}

/**
 * Whatever a general update means: a new plan, a failed payment, a cancellation
 * undone, payment recovering, or usually nothing at all.
 *
 * @param string $subscription_id Subscription ID.
 * @param array  $previous        What changed, when SureCart says.
 * @return array|WP_Error
 */
function blueworx_klaviyo_store_plans_for_update( $subscription_id, $previous ) {
	$subscription = blueworx_klaviyo_surecart_record( 'subscription', $subscription_id );

	if ( is_wp_error( $subscription ) ) {
		return $subscription;
	}

	$state     = blueworx_klaviyo_store_recall( $subscription_id );
	$price     = isset( $subscription['price'] ) && is_array( $subscription['price'] ) ? $subscription['price'] : array();
	$price_id  = isset( $price['id'] ) ? $price['id'] : '';
	$status    = isset( $subscription['status'] ) ? $subscription['status'] : '';
	$old_price = isset( $previous['price'] ) ? $previous['price'] : ( isset( $state['price'] ) ? $state['price'] : '' );
	$old_price = is_array( $old_price ) ? ( isset( $old_price['id'] ) ? $old_price['id'] : '' ) : (string) $old_price;
	$old_state = isset( $previous['status'] ) ? (string) $previous['status'] : ( isset( $state['status'] ) ? (string) $state['status'] : '' );
	$plans     = array();

	if ( '' !== $old_price && '' !== $price_id && $old_price !== $price_id ) {
		$old_record = blueworx_klaviyo_surecart_record( 'price', $old_price );

		if ( is_wp_error( $old_record ) ) {
			return $old_record;
		}

		$plans[] = blueworx_klaviyo_store_plan_changed( $subscription, $old_record );
	}

	if ( '' !== $old_state && $old_state !== $status ) {
		if ( 'past_due' === blueworx_klaviyo_store_status( $status ) && 'past_due' !== blueworx_klaviyo_store_status( $old_state ) ) {
			$plans[] = blueworx_klaviyo_store_plan_failed( $subscription );
		} elseif ( ! $plans ) {
			$plans[] = blueworx_klaviyo_store_plan_status( $subscription );
		}
	}

	$now = array(
		'price'  => $price_id,
		'status' => $status,
	);

	// A cancellation taken back before access ended: there is no end date any
	// more, and the next cancellation is a new one.
	if ( ! empty( $previous['cancel_at_period_end'] ) && empty( $subscription['cancel_at_period_end'] ) ) {
		$plans[]            = blueworx_klaviyo_store_plan_status( $subscription, array(), array( 'membership_end_date' ) );
		$now['cancel_sent'] = false;
	}

	$plans = array_values( array_filter( $plans ) );

	if ( ! $plans ) {
		blueworx_klaviyo_store_remember( $subscription_id, $now );
		return array();
	}

	// Remembered only once it has gone through, so a retry still sees the change.
	$last                    = count( $plans ) - 1;
	$plans[ $last ]['after'] = array(
		'subscription' => $subscription_id,
		'state'        => $now,
	);

	return $plans;
}

// --- The Klaviyo screen -------------------------------------------------------

/**
 * The SureCart card on the Klaviyo screen.
 *
 * @return void
 */
function blueworx_klaviyo_store_card() {
	$settings = blueworx_klaviyo_store_settings();
	$secret   = blueworx_klaviyo_store_secret();
	$lists    = blueworx_klaviyo_connected() ? blueworx_klaviyo_lists() : false;
	$lists    = is_wp_error( $lists ) ? false : $lists;
	$body     = '';

	if ( ! blueworx_klaviyo_surecart_active() ) {
		$body .= wp_kses(
			blueworx_ds_notice(
				array(
					'tone' => 'info',
					'text' => __( 'SureCart is not installed on this site, so nothing from the store is sent. The settings below are kept for when it is.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	$refused = (int) blueworx_klaviyo_setting( 'store_last_refused', 0 );

	if ( $refused > time() - DAY_IN_SECONDS ) {
		$body .= wp_kses(
			blueworx_ds_notice(
				array(
					'tone' => 'warning',
					'text' => sprintf(
						/* translators: %s: date and time. */
						__( 'A delivery to the webhook address was refused at %s because its signature did not match. If SureCart is not reaching this site, check the signing secret below.', 'blueworx-labs-wordpress' ),
						wp_date( 'j M H:i', $refused )
					),
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	$body .= blueworx_ds_field(
		array(
			'label'   => __( 'Webhook address', 'blueworx-labs-wordpress' ),
			'help'    => sprintf(
				/* translators: %s: comma-separated SureCart event names. */
				__( 'In the SureCart dashboard, under Settings, Webhooks, add this address listening for: %s.', 'blueworx-labs-wordpress' ),
				implode( ', ', blueworx_klaviyo_store_events() )
			),
			'control' => '<code data-testid="blueworx-klaviyo-webhook-url">' . esc_html( blueworx_klaviyo_store_webhook_url() ) . '</code>',
		)
	);

	if ( defined( 'BLUEWORX_KLAVIYO_WEBHOOK_SECRET' ) && BLUEWORX_KLAVIYO_WEBHOOK_SECRET ) {
		$body .= blueworx_ds_field(
			array(
				'label'   => __( 'Webhook signing secret', 'blueworx-labs-wordpress' ),
				'help'    => __( 'Set in wp-config.php as BLUEWORX_KLAVIYO_WEBHOOK_SECRET, so it cannot be changed here.', 'blueworx-labs-wordpress' ),
				'control' => '<code>' . esc_html( blueworx_klaviyo_mask( $secret ) ) . '</code>',
			)
		);
	} else {
		$body .= blueworx_ds_field(
			array(
				'label'   => __( 'Webhook signing secret', 'blueworx-labs-wordpress' ),
				'for'     => 'blueworx_klaviyo_store_webhook_secret',
				'help'    => '' !== $secret
					? sprintf(
						/* translators: %s: the last few characters of the saved secret. */
						__( 'Saved: %s. Leave this empty to keep it.', 'blueworx-labs-wordpress' ),
						blueworx_klaviyo_mask( $secret )
					)
					: __( 'Shown in the SureCart dashboard once the webhook is added. Until it is set here, every delivery is refused.', 'blueworx-labs-wordpress' ),
				'control' => blueworx_ds_input(
					array(
						'type'  => 'password',
						'name'  => 'blueworx_klaviyo_store_webhook_secret',
						'id'    => 'blueworx_klaviyo_store_webhook_secret',
						'value' => '',
						'attrs' => array( 'autocomplete' => 'new-password' ),
					)
				),
			)
		);

		if ( '' !== $secret ) {
			$body .= blueworx_ds_checkbox(
				array(
					'name'  => 'blueworx_klaviyo_store_forget_secret',
					'value' => '1',
					'label' => __( 'Remove the saved secret', 'blueworx-labs-wordpress' ),
				)
			);
		}
	}

	if ( is_array( $lists ) && $lists ) {
		$options = array( '' => __( 'No list', 'blueworx-labs-wordpress' ) ) + $lists;

		if ( '' !== $settings['list'] && ! isset( $lists[ $settings['list'] ] ) ) {
			$options[ $settings['list'] ] = $settings['list'];
		}

		$list = blueworx_ds_select(
			array(
				'name'     => 'blueworx_klaviyo_store_list',
				'id'       => 'blueworx_klaviyo_store_list',
				'options'  => $options,
				'selected' => $settings['list'],
			)
		);
	} else {
		$list = blueworx_ds_input(
			array(
				'name'  => 'blueworx_klaviyo_store_list',
				'id'    => 'blueworx_klaviyo_store_list',
				'value' => $settings['list'],
				'attrs' => array(
					'placeholder'  => __( 'List ID from Klaviyo', 'blueworx-labs-wordpress' ),
					'autocomplete' => 'off',
				),
			)
		);
	}

	$body .= blueworx_ds_field(
		array(
			'label'   => __( 'Members list', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_klaviyo_store_list',
			'help'    => __( 'Customers who tick the marketing box at checkout are subscribed here. Unticked never subscribes.', 'blueworx-labs-wordpress' ),
			'control' => $list,
		)
	);

	$body .= blueworx_ds_field(
		array(
			'label'   => __( 'Checkout marketing box name', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_klaviyo_store_checkout_box',
			'help'    => __( 'The Name given to the checkbox in the SureCart checkout form. Ticked records marketing consent and adds them to the Members list.', 'blueworx-labs-wordpress' ),
			'control' => blueworx_ds_input(
				array(
					'name'  => 'blueworx_klaviyo_store_checkout_box',
					'id'    => 'blueworx_klaviyo_store_checkout_box',
					'value' => $settings['checkout_box'],
					'attrs' => array( 'autocomplete' => 'off' ),
				)
			),
		)
	);

	$body .= blueworx_ds_checkbox(
		array(
			'name'    => 'blueworx_klaviyo_store_test_mode',
			'value'   => '1',
			'checked' => '1' === $settings['test_mode'],
			'label'   => __( 'Also send SureCart test orders', 'blueworx-labs-wordpress' ),
			'help'    => __( 'For setting up. Switch this off before real customers pay, or test orders go into Klaviyo with them.', 'blueworx-labs-wordpress' ),
		)
	);

	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helpers escape everything they emit.
		array(
			'title' => __( 'SureCart store', 'blueworx-labs-wordpress' ),
			'body'  => $body,
		)
	);
}
add_action( 'blueworx_klaviyo_screen_cards', 'blueworx_klaviyo_store_card', 5 );

/**
 * Saves the store's boxes when the Klaviyo screen saves.
 *
 * @param string[] $refused Labels refused so far.
 * @return string[]
 */
function blueworx_klaviyo_store_save( $refused ) {
	// The caller has verified the nonce and the capability; every field is checked below.
	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$list = isset( $_POST['blueworx_klaviyo_store_list'] ) ? trim( (string) wp_unslash( $_POST['blueworx_klaviyo_store_list'] ) ) : '';

	if ( '' !== $list && ! preg_match( '/^[A-Za-z0-9]{4,12}$/', $list ) ) {
		$refused[] = __( 'Members list', 'blueworx-labs-wordpress' );
	} else {
		blueworx_klaviyo_update_setting( 'store_list', $list );
	}

	$box = isset( $_POST['blueworx_klaviyo_store_checkout_box'] ) ? trim( (string) wp_unslash( $_POST['blueworx_klaviyo_store_checkout_box'] ) ) : '';

	if ( '' !== $box && ! preg_match( '/^[A-Za-z0-9_-]{1,60}$/', $box ) ) {
		$refused[] = __( 'Checkout marketing box name', 'blueworx-labs-wordpress' );
	} else {
		blueworx_klaviyo_update_setting( 'store_checkout_box', $box );
	}

	blueworx_klaviyo_update_setting( 'store_test_mode', empty( $_POST['blueworx_klaviyo_store_test_mode'] ) ? '0' : '1' );

	if ( ! defined( 'BLUEWORX_KLAVIYO_WEBHOOK_SECRET' ) ) {
		// Tidied by hand because sanitize_text_field() could alter a secret. An
		// empty box means "keep what is saved".
		$secret = isset( $_POST['blueworx_klaviyo_store_webhook_secret'] ) ? trim( (string) wp_unslash( $_POST['blueworx_klaviyo_store_webhook_secret'] ) ) : '';

		if ( '' !== $secret ) {
			blueworx_klaviyo_update_setting( 'store_webhook_secret', $secret );
			delete_option( BLUEWORX_KLAVIYO_OPTION_PREFIX . 'store_last_refused' );
		}

		if ( ! empty( $_POST['blueworx_klaviyo_store_forget_secret'] ) ) {
			delete_option( BLUEWORX_KLAVIYO_OPTION_PREFIX . 'store_webhook_secret' );
		}
	}
	// phpcs:enable

	return (array) $refused;
}
add_filter( 'blueworx_klaviyo_save_settings', 'blueworx_klaviyo_store_save' );

/**
 * Says what the store sends, on the Klaviyo screen.
 *
 * @param array $sources Entries with 'label' and 'text'.
 * @return array
 */
function blueworx_klaviyo_store_source( $sources ) {
	$ready = blueworx_klaviyo_surecart_active() && '' !== blueworx_klaviyo_store_secret();

	$sources[] = array(
		'label' => __( 'SureCart store', 'blueworx-labs-wordpress' ),
		'text'  => $ready
			? __( 'Placed Order, Started Subscription, Subscription Renewed, Payment Failed, Subscription Cancelled, Plan Changed and Refunded, each with the product, price, dates and IDs in the store\'s currency. Customers who tick the marketing box join the Members list.', 'blueworx-labs-wordpress' )
			: __( 'Needs SureCart installed and the webhook set up below. Until then nothing from the store is sent.', 'blueworx-labs-wordpress' ),
	);

	return $sources;
}
add_filter( 'blueworx_klaviyo_sources', 'blueworx_klaviyo_store_source', 7 );

/**
 * Adds the store's lines to the Test connection button.
 *
 * @param array $result ok and lines.
 * @param array $lists  The account's lists, names keyed by ID.
 * @return array
 */
function blueworx_klaviyo_store_test( $result, $lists ) {
	$settings = blueworx_klaviyo_store_settings();

	if ( '' === blueworx_klaviyo_store_secret() ) {
		$result['lines'][] = __( 'Store: no webhook signing secret yet, so SureCart deliveries are refused.', 'blueworx-labs-wordpress' );
	} else {
		$result['lines'][] = __( 'Store: the webhook signing secret is set.', 'blueworx-labs-wordpress' );
	}

	if ( '' !== $settings['list'] ) {
		if ( isset( $lists[ $settings['list'] ] ) ) {
			$result['lines'][] = sprintf(
				/* translators: %s: the list's name. */
				__( 'Store: the Members list is "%s".', 'blueworx-labs-wordpress' ),
				$lists[ $settings['list'] ]
			);
		} else {
			$result['ok']      = false;
			$result['lines'][] = sprintf(
				/* translators: %s: the list ID. */
				__( 'Store: Klaviyo cannot find the Members list %s.', 'blueworx-labs-wordpress' ),
				$settings['list']
			);
		}
	}

	return $result;
}
add_filter( 'blueworx_klaviyo_test_connection', 'blueworx_klaviyo_store_test', 7, 2 );
