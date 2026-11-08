<?php
/**
 * Klaviyo connection: tracking visitors on the site.
 *
 * Klaviyo's own script goes on every public page, using the public API key.
 * That gives Klaviyo "Active on Site" and lets its sign-up forms and pop-ups
 * work. Somebody signed in is identified by email, so their browsing joins
 * their profile; a SureCart product page sends "Viewed Product".
 *
 * Nothing loads without a public key, on an admin screen, or for an
 * administrator: their own browsing of the site would only muddy the numbers.
 *
 * Nothing here waits for cookie consent yet. That arrives with the consent
 * work in issue #4, the same way as the tracking codes: the
 * blueworx_klaviyo_tracking_allowed filter is where it will plug in.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where Klaviyo serves its site script from. The public key goes in the path.
 */
const BLUEWORX_KLAVIYO_SCRIPT = 'https://static.klaviyo.com/onsite/js/%s/klaviyo.js';

/**
 * Whether Klaviyo's script belongs on this request.
 *
 * @return bool
 */
function blueworx_klaviyo_tracking_active() {
	if ( ! blueworx_klaviyo_enabled() || is_admin() || '' === blueworx_klaviyo_public_key() ) {
		return false;
	}

	// Administrators are the people building the site, not visitors to it.
	$allowed = ! current_user_can( 'manage_options' );

	/**
	 * Whether this visitor is tracked by Klaviyo's script.
	 *
	 * Cookie consent plugs in here: return false until the visitor has agreed.
	 *
	 * @param bool $allowed True for everyone but administrators.
	 */
	return (bool) apply_filters( 'blueworx_klaviyo_tracking_allowed', $allowed );
}

/**
 * Who a signed-in person is, for Klaviyo's identify call.
 *
 * @param WP_User|object|null $user The signed-in user, or nothing.
 * @return array Empty for a visitor who is not signed in.
 */
function blueworx_klaviyo_tracking_identity( $user ) {
	if ( ! $user || empty( $user->user_email ) || ! is_email( $user->user_email ) ) {
		return array();
	}

	return blueworx_klaviyo_clean(
		array(
			'email'       => strtolower( (string) $user->user_email ),
			'first_name'  => isset( $user->first_name ) ? (string) $user->first_name : '',
			'last_name'   => isset( $user->last_name ) ? (string) $user->last_name : '',
			'external_id' => isset( $user->ID ) && (int) $user->ID > 0 ? (string) $user->ID : '',
		)
	);
}

/**
 * The details of a SureCart product, in the names Klaviyo's catalogue uses.
 *
 * Read from SureCart's product when SureCart is there, and from the post
 * otherwise. Everything is optional but the name: a product without a price
 * still counts as viewed.
 *
 * @param int $post_id The product's post ID.
 * @return array ProductName, ProductID, URL, and when known ImageURL, Price, Currency.
 */
function blueworx_klaviyo_viewed_product_details( $post_id ) {
	$post_id = (int) $post_id;
	$details = array(
		'ProductName' => (string) get_the_title( $post_id ),
		'ProductID'   => (string) $post_id,
		'URL'         => (string) get_permalink( $post_id ),
		'ImageURL'    => (string) get_the_post_thumbnail_url( $post_id, 'large' ),
		'Price'       => '',
		'Currency'    => '',
	);

	if ( function_exists( 'sc_get_product' ) ) {
		$product = sc_get_product( $post_id );

		if ( is_object( $product ) ) {
			if ( ! empty( $product->id ) ) {
				$details['ProductID'] = (string) $product->id;
			}

			if ( ! empty( $product->name ) ) {
				$details['ProductName'] = (string) $product->name;
			}

			if ( '' === $details['ImageURL'] && ! empty( $product->image_url ) ) {
				$details['ImageURL'] = (string) $product->image_url;
			}

			if ( isset( $product->metrics ) && is_object( $product->metrics ) ) {
				if ( isset( $product->metrics->min_price_amount ) && is_numeric( $product->metrics->min_price_amount ) ) {
					$details['Price'] = round( (float) $product->metrics->min_price_amount / 100, 2 );
				}

				if ( ! empty( $product->metrics->currency ) ) {
					$details['Currency'] = strtoupper( (string) $product->metrics->currency );
				}
			}
		}
	}

	/**
	 * The details sent with Viewed Product for one SureCart product.
	 *
	 * @param array $details ProductName, ProductID, URL, ImageURL, Price, Currency.
	 * @param int   $post_id The product's post ID.
	 */
	return blueworx_klaviyo_clean( apply_filters( 'blueworx_klaviyo_viewed_product', $details, $post_id ) );
}

/**
 * The events this page sends as it loads.
 *
 * @return array Each entry has a 'name' and 'properties'.
 */
function blueworx_klaviyo_page_events() {
	$events  = array();
	$post_id = is_singular() ? (int) get_queried_object_id() : 0;

	if ( $post_id > 0 && 'sc_product' === get_post_type( $post_id ) ) {
		$details = blueworx_klaviyo_viewed_product_details( $post_id );

		if ( ! empty( $details['ProductName'] ) ) {
			$events[] = array(
				'name'       => 'Viewed Product',
				'properties' => $details,
			);
		}
	}

	/**
	 * The events Klaviyo's script sends as this page loads.
	 *
	 * Add an entry with a 'name' and 'properties' for a page your plugin
	 * knows something about.
	 *
	 * @param array $events  Each entry has a 'name' and 'properties'.
	 * @param int   $post_id The post being viewed, or 0.
	 */
	$events = apply_filters( 'blueworx_klaviyo_page_events', $events, $post_id );

	return array_values(
		array_filter(
			(array) $events,
			static function ( $event ) {
				return is_array( $event ) && ! empty( $event['name'] );
			}
		)
	);
}

/**
 * What the page's script is told. Pure, so the shape can be pinned.
 *
 * @param array $identity From blueworx_klaviyo_tracking_identity().
 * @param array $events   From blueworx_klaviyo_page_events().
 * @return array identify, events and viewedItem (Klaviyo's recently-viewed shape, for a product page).
 */
function blueworx_klaviyo_tracking_config( $identity, $events ) {
	$viewed = null;

	foreach ( $events as $event ) {
		if ( 'Viewed Product' === $event['name'] ) {
			$p      = isset( $event['properties'] ) ? (array) $event['properties'] : array();
			$viewed = blueworx_klaviyo_clean(
				array(
					'Title'    => isset( $p['ProductName'] ) ? $p['ProductName'] : '',
					'ItemId'   => isset( $p['ProductID'] ) ? $p['ProductID'] : '',
					'Url'      => isset( $p['URL'] ) ? $p['URL'] : '',
					'ImageUrl' => isset( $p['ImageURL'] ) ? $p['ImageURL'] : '',
					'Metadata' => blueworx_klaviyo_clean( array( 'Price' => isset( $p['Price'] ) ? $p['Price'] : '' ) ),
				)
			);
			break;
		}
	}

	return array(
		'identify'   => (object) $identity,
		'events'     => array_values( $events ),
		'viewedItem' => $viewed ? (object) $viewed : null,
	);
}

/**
 * Puts Klaviyo's script, and the page's own calls to it, on a public page.
 *
 * @return void
 */
function blueworx_klaviyo_tracking_enqueue() {
	if ( ! blueworx_klaviyo_tracking_active() ) {
		return;
	}

	// Klaviyo's script, loaded async as Klaviyo asks. No version: the address
	// is Klaviyo's and a query string on it would only break their caching.
	wp_enqueue_script(
		'klaviyo-onsite',
		sprintf( BLUEWORX_KLAVIYO_SCRIPT, rawurlencode( blueworx_klaviyo_public_key() ) ),
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- See above.
		array( 'strategy' => 'async' )
	);

	wp_enqueue_script(
		'blueworx-klaviyo-tracking',
		BLUEWORX_LABS_URL . 'assets/js/klaviyo-tracking.js',
		array(),
		blueworx_get_admin_asset_version( 'assets/js/klaviyo-tracking.js' ),
		array( 'in_footer' => true )
	);

	wp_localize_script(
		'blueworx-klaviyo-tracking',
		'blueworxKlaviyoTracking',
		blueworx_klaviyo_tracking_config( blueworx_klaviyo_tracking_identity( wp_get_current_user() ), blueworx_klaviyo_page_events() )
	);
}
add_action( 'wp_enqueue_scripts', 'blueworx_klaviyo_tracking_enqueue' );

/**
 * Clears cached pages when the public key or the switch changes, so the
 * script appears or goes for visitors straight away.
 *
 * @return void
 */
function blueworx_klaviyo_tracking_refresh_cache() {
	if ( function_exists( 'blueworx_purge_site_cache' ) ) {
		blueworx_purge_site_cache( 'Klaviyo tracking' );
	}
}
add_action( 'update_option_blueworx_klaviyo_public_key', 'blueworx_klaviyo_tracking_refresh_cache' );
add_action( 'add_option_blueworx_klaviyo_public_key', 'blueworx_klaviyo_tracking_refresh_cache' );
add_action( 'update_option_blueworx_feature_klaviyo', 'blueworx_klaviyo_tracking_refresh_cache' );

/**
 * Says what tracking sends, on the Klaviyo screen.
 *
 * @param array $sources Entries with 'label' and 'text'.
 * @return array
 */
function blueworx_klaviyo_tracking_source( $sources ) {
	$sources[] = array(
		'label' => __( 'Visitor tracking', 'blueworx-labs-wordpress' ),
		'text'  => '' !== blueworx_klaviyo_public_key()
			? __( 'Klaviyo\'s script is on every public page, so Klaviyo sees who is active on the site and its own sign-up forms work. Signed-in people are identified by email, and a product page sends Viewed Product. Administrators are not tracked.', 'blueworx-labs-wordpress' )
			: __( 'Needs the public API key above. Until it is saved, visitors are not tracked and Klaviyo\'s own forms cannot show.', 'blueworx-labs-wordpress' ),
	);

	return $sources;
}
add_filter( 'blueworx_klaviyo_sources', 'blueworx_klaviyo_tracking_source', 5 );

/**
 * Adds tracking's line to the Test connection button.
 *
 * A missing public key is worth saying, not failing over: sends still work
 * without it.
 *
 * @param array $result ok and lines.
 * @return array
 */
function blueworx_klaviyo_tracking_test_line( $result ) {
	$result['lines'][] = '' !== blueworx_klaviyo_public_key()
		? __( 'Visitors are tracked with the public key.', 'blueworx-labs-wordpress' )
		: __( 'No public API key yet, so visitors are not tracked.', 'blueworx-labs-wordpress' );

	return $result;
}
add_filter( 'blueworx_klaviyo_test_connection', 'blueworx_klaviyo_tracking_test_line', 5 );
