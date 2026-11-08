<?php
/**
 * Klaviyo connection: checkouts that were started but not paid.
 *
 * Klaviyo's abandoned-cart emails rest on one event: Started Checkout, sent
 * the moment a customer at the checkout has given an email. SureCart's
 * checkout form saves the checkout to SureCart through this site's own REST
 * API as the customer types, so the connection watches those saves and sends
 * the event from the one that first carries an email, and again whenever the
 * cart's contents change. A later Placed Order on the same person is what
 * lets Klaviyo's flow stand down.
 *
 * A repeat save with the same contents is noticed and dropped before it
 * reaches the queue. No SureCart account is needed: everything comes from the
 * checkout the form has just saved.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The REST routes SureCart's checkout form saves through.
 */
const BLUEWORX_KLAVIYO_CHECKOUT_ROUTE = '#^/surecart/v1/checkouts(?:/|$)#';

/**
 * The page the customer is sent back to, with the checkout to pick up.
 *
 * SureCart's checkout page re-opens a checkout named in its address. The
 * page is asked of SureCart when it is there, and /checkout/ is assumed
 * otherwise; a site can say otherwise through the filter.
 *
 * @return string
 */
function blueworx_klaviyo_checkout_page_url() {
	$url = '';

	if ( class_exists( 'SureCart' ) && method_exists( 'SureCart', 'pages' ) ) {
		try {
			$pages = SureCart::pages();

			if ( is_object( $pages ) && method_exists( $pages, 'url' ) ) {
				$url = (string) $pages->url( 'checkout' );
			}
		} catch ( Throwable $thrown ) {
			$url = '';
		}
	}

	if ( '' === $url ) {
		$url = home_url( '/checkout/' );
	}

	/**
	 * The checkout page a customer is sent back to from an abandoned-cart email.
	 *
	 * @param string $url The page, without the checkout's ID.
	 */
	return (string) apply_filters( 'blueworx_klaviyo_checkout_page_url', $url );
}

/**
 * The line items of a checkout, as a plain list.
 *
 * @param array $checkout The checkout.
 * @return array
 */
function blueworx_klaviyo_checkout_items( $checkout ) {
	$items = isset( $checkout['line_items'] ) ? $checkout['line_items'] : array();

	if ( is_array( $items ) && isset( $items['data'] ) && is_array( $items['data'] ) ) {
		$items = $items['data'];
	}

	return is_array( $items ) ? array_values( array_filter( $items, 'is_array' ) ) : array();
}

/**
 * Started Checkout: the plan for a checkout with an email and a cart.
 *
 * Pure. The event's ID changes with the cart's contents, so a changed cart is
 * a fresh event and an unchanged one is recorded once however often it is
 * saved. Klaviyo's own names for the cart (Items, ItemNames, CheckoutURL) are
 * used, so its abandoned-cart templates work without changes.
 *
 * @param array  $checkout The checkout, as SureCart's form saved it.
 * @param array  $settings Store settings.
 * @param string $page_url The checkout page, without the checkout's ID.
 * @return array|null The plan, or null when there is nothing to send yet.
 */
function blueworx_klaviyo_checkout_plan( $checkout, $settings, $page_url ) {
	$customer = isset( $checkout['customer'] ) && is_array( $checkout['customer'] ) ? $checkout['customer'] : array();
	$email    = isset( $checkout['email'] ) && is_string( $checkout['email'] ) ? $checkout['email'] : ( isset( $customer['email'] ) ? (string) $customer['email'] : '' );
	$status   = isset( $checkout['status'] ) ? (string) $checkout['status'] : '';
	$items    = blueworx_klaviyo_checkout_items( $checkout );

	if ( '' === $email || ! in_array( $status, array( '', 'draft' ), true ) || ! $items ) {
		return null;
	}

	$zero     = ! empty( $checkout['is_zero_decimal'] );
	$id       = isset( $checkout['id'] ) ? (string) $checkout['id'] : '';
	$currency = blueworx_klaviyo_store_currency( $checkout );
	$rows     = array();
	$names    = array();
	$shape    = array();
	$sum      = 0.0;

	foreach ( $items as $item ) {
		$price    = isset( $item['price'] ) && is_array( $item['price'] ) ? $item['price'] : array();
		$product  = isset( $price['product'] ) && is_array( $price['product'] ) ? $price['product'] : array();
		$quantity = isset( $item['quantity'] ) ? max( 1, (int) $item['quantity'] ) : 1;
		$each     = blueworx_klaviyo_store_money( isset( $price['amount'] ) ? $price['amount'] : 0, $zero );
		$row      = isset( $item['total_amount'] ) ? blueworx_klaviyo_store_money( $item['total_amount'], $zero ) : ( isset( $item['subtotal_amount'] ) ? blueworx_klaviyo_store_money( $item['subtotal_amount'], $zero ) : round( $each * $quantity, 2 ) );
		$name     = isset( $product['name'] ) ? (string) $product['name'] : ( isset( $price['name'] ) ? (string) $price['name'] : '' );
		$prod_id  = isset( $product['id'] ) ? (string) $product['id'] : ( isset( $price['product'] ) && is_string( $price['product'] ) ? $price['product'] : '' );

		if ( '' === $currency ) {
			$currency = blueworx_klaviyo_store_currency( $price );
		}

		$rows[]  = blueworx_klaviyo_clean(
			array(
				'ProductID'   => $prod_id,
				'ProductName' => $name,
				'ProductSlug' => isset( $product['slug'] ) ? (string) $product['slug'] : '',
				'PriceID'     => isset( $price['id'] ) ? (string) $price['id'] : '',
				'Quantity'    => $quantity,
				'ItemPrice'   => $each,
				'RowTotal'    => $row,
				'ProductURL'  => isset( $product['permalink'] ) ? (string) $product['permalink'] : '',
				'ImageURL'    => isset( $product['image_url'] ) ? (string) $product['image_url'] : '',
			)
		);
		$names[] = $name;
		$shape[] = array( isset( $price['id'] ) ? (string) $price['id'] : $prod_id, $quantity, $row );
		$sum    += $row;
	}

	$total = isset( $checkout['total_amount'] ) ? blueworx_klaviyo_store_money( $checkout['total_amount'], $zero ) : ( isset( $checkout['amount_due'] ) ? blueworx_klaviyo_store_money( $checkout['amount_due'], $zero ) : round( $sum, 2 ) );
	$url   = '' !== $id ? add_query_arg( 'checkout_id', $id, $page_url ) : $page_url;

	/**
	 * The address an abandoned-cart email sends the customer back to.
	 *
	 * @param string $url      The checkout page with the checkout's ID.
	 * @param array  $checkout The checkout.
	 */
	$url = (string) apply_filters( 'blueworx_klaviyo_checkout_url', $url, $checkout );

	return array(
		'label' => 'Started Checkout',
		'email' => $email,
		'fill'  => array(
			'first_name' => isset( $checkout['first_name'] ) ? (string) $checkout['first_name'] : ( isset( $customer['first_name'] ) ? (string) $customer['first_name'] : '' ),
			'last_name'  => isset( $checkout['last_name'] ) ? (string) $checkout['last_name'] : ( isset( $customer['last_name'] ) ? (string) $customer['last_name'] : '' ),
		),
		'event' => array(
			'name'       => 'Started Checkout',
			'unique_id'  => 'checkout-' . $id . '-' . substr( md5( wp_json_encode( array( $shape, $total ) ) ), 0, 12 ),
			'value'      => $total,
			'currency'   => $currency,
			'time'       => time(),
			'properties' => array(
				'checkout_id' => $id,
				'item_count'  => array_sum( array_column( $rows, 'Quantity' ) ),
				'ItemNames'   => array_values( array_filter( $names ) ),
				'Items'       => $rows,
				'CheckoutURL' => $url,
				'coupon_code' => isset( $checkout['discount']['promotion']['code'] ) ? (string) $checkout['discount']['promotion']['code'] : '',
				'currency'    => $currency,
			),
		),
	);
}

/**
 * Takes in a checkout SureCart's form has just saved, and queues Started
 * Checkout if this is the first time this cart has been seen with an email.
 *
 * @param array|object $checkout The checkout.
 * @return string queued, repeat, skipped, test_mode or off.
 */
function blueworx_klaviyo_checkout_noticed( $checkout ) {
	if ( ! blueworx_klaviyo_connected() ) {
		return 'off';
	}

	$checkout = is_array( $checkout ) ? $checkout : blueworx_klaviyo_surecart_plain( $checkout );
	$settings = blueworx_klaviyo_store_settings();

	if ( isset( $checkout['live_mode'] ) && false === $checkout['live_mode'] && '1' !== $settings['test_mode'] ) {
		return 'test_mode';
	}

	$plan = blueworx_klaviyo_checkout_plan( $checkout, $settings, blueworx_klaviyo_checkout_page_url() );

	if ( ! $plan ) {
		return 'skipped';
	}

	$key = 'checkout:' . $plan['event']['unique_id'];

	if ( blueworx_klaviyo_store_seen( $key ) ) {
		return 'repeat';
	}

	blueworx_klaviyo_store_mark_seen( $key );

	return blueworx_klaviyo_send( $plan, 'store', $key ) ? 'queued' : 'skipped';
}

/**
 * Watches the saves SureCart's checkout form makes through this site.
 *
 * Only the answer is read, after SureCart has done its work, so nothing here
 * can slow or break the checkout.
 *
 * @param WP_REST_Response|mixed $response The answer about to go back.
 * @param WP_REST_Server         $server   The server.
 * @param WP_REST_Request        $request  The request.
 * @return WP_REST_Response|mixed The answer, untouched.
 */
function blueworx_klaviyo_checkout_watch( $response, $server, $request ) {
	if ( ! ( $response instanceof WP_REST_Response ) || ! ( $request instanceof WP_REST_Request ) ) {
		return $response;
	}

	if ( 'GET' === $request->get_method() || $response->get_status() >= 300 || ! preg_match( BLUEWORX_KLAVIYO_CHECKOUT_ROUTE, (string) $request->get_route() ) ) {
		return $response;
	}

	if ( ! blueworx_klaviyo_connected() ) {
		return $response;
	}

	$checkout = blueworx_klaviyo_surecart_plain( $response->get_data() );

	if ( isset( $checkout['object'] ) && 'checkout' === $checkout['object'] ) {
		try {
			blueworx_klaviyo_checkout_noticed( $checkout );
		} catch ( Throwable $thrown ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- The checkout must never fail because of this.
			// Nothing: the checkout comes first.
		}
	}

	return $response;
}
add_filter( 'rest_post_dispatch', 'blueworx_klaviyo_checkout_watch', 10, 3 );

/**
 * Says what abandoned checkouts send, on the Klaviyo screen.
 *
 * @param array $sources Entries with 'label' and 'text'.
 * @return array
 */
function blueworx_klaviyo_checkout_source( $sources ) {
	$sources[] = array(
		'label' => __( 'Abandoned checkouts', 'blueworx-labs-wordpress' ),
		'text'  => blueworx_klaviyo_surecart_active()
			? __( 'Started Checkout the moment a SureCart checkout has an email, with the items, the total and a link back to the checkout, so Klaviyo can send abandoned-cart emails. A changed cart sends it again with the new contents; a Placed Order afterwards lets Klaviyo stand down.', 'blueworx-labs-wordpress' )
			: __( 'Needs SureCart installed. Then Started Checkout is sent the moment a checkout has an email, so Klaviyo can send abandoned-cart emails.', 'blueworx-labs-wordpress' ),
	);

	return $sources;
}
add_filter( 'blueworx_klaviyo_sources', 'blueworx_klaviyo_checkout_source', 8 );
