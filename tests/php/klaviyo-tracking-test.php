<?php
/**
 * Klaviyo tracking: who is identified, what a product page sends, and what the
 * page's script is told.
 *
 * Run with: php tests/php/klaviyo-tracking-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.WP.EnqueuedResources -- Tag strings under test, never printed.

require __DIR__ . '/stubs.php';

$GLOBALS['enabled']  = true;
$GLOBALS['admin']    = false;
$GLOBALS['can']      = false;
$GLOBALS['singular'] = 0;
$GLOBALS['types']    = array();
$GLOBALS['product']  = null;

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

function is_admin() {
	return $GLOBALS['admin'];
}

function current_user_can( $cap ) {
	return $GLOBALS['can'];
}

function is_singular() {
	return $GLOBALS['singular'] > 0;
}

function get_queried_object_id() {
	return $GLOBALS['singular'];
}

function get_post_type( $post_id ) {
	return isset( $GLOBALS['types'][ $post_id ] ) ? $GLOBALS['types'][ $post_id ] : 'post';
}

function get_the_title( $post_id ) {
	return 'Post ' . $post_id;
}

function get_permalink( $post_id ) {
	return 'https://example.test/?p=' . $post_id;
}

function get_the_post_thumbnail_url( $post_id, $size = 'post-thumbnail' ) {
	return 7 === $post_id ? 'https://example.test/racket.jpg' : false;
}

function sc_get_product( $post_id ) {
	return $GLOBALS['product'];
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/klaviyo/settings.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/client.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/tracking.php';

// --- When the script loads --------------------------------------------------

$GLOBALS['options']['blueworx_klaviyo_public_key'] = 'AbC123';

check( 'a visitor with a public key saved is tracked', blueworx_klaviyo_tracking_active(), true );

$GLOBALS['can'] = true;
check( 'an administrator is not', blueworx_klaviyo_tracking_active(), false );
$GLOBALS['can'] = false;

$GLOBALS['admin'] = true;
check( 'nothing on an admin screen', blueworx_klaviyo_tracking_active(), false );
$GLOBALS['admin'] = false;

$GLOBALS['options']['blueworx_klaviyo_public_key'] = '';
check( 'nothing without a public key', blueworx_klaviyo_tracking_active(), false );
$GLOBALS['options']['blueworx_klaviyo_public_key'] = 'AbC123';

$GLOBALS['enabled'] = false;
check( 'nothing while the function is off', blueworx_klaviyo_tracking_active(), false );
$GLOBALS['enabled'] = true;

$GLOBALS['filters']['blueworx_klaviyo_tracking_allowed'] = static function ( $allowed ) {
	return false;
};
check( 'consent can switch it off through the filter', blueworx_klaviyo_tracking_active(), false );
unset( $GLOBALS['filters']['blueworx_klaviyo_tracking_allowed'] );

// --- Who is identified ------------------------------------------------------

$user             = new stdClass();
$user->ID         = 12;
$user->user_email = 'Pat@Example.com';
$user->first_name = 'Pat';
$user->last_name  = '';

check(
	'a signed-in person is identified by email, with what is known of their name',
	blueworx_klaviyo_tracking_identity( $user ),
	array(
		'email'       => 'pat@example.com',
		'first_name'  => 'Pat',
		'external_id' => '12',
	)
);
check( 'nobody signed in means nobody identified', blueworx_klaviyo_tracking_identity( null ), array() );

$user->user_email = 'not-an-email';
check( 'a bad email identifies nobody', blueworx_klaviyo_tracking_identity( $user ), array() );

// --- What a product page sends ----------------------------------------------

$GLOBALS['types'][7] = 'sc_product';

$details = blueworx_klaviyo_viewed_product_details( 7 );

check( 'without SureCart\'s product, the post gives the name', $details['ProductName'], 'Post 7' );
check( 'the post gives the address', $details['URL'], 'https://example.test/?p=7' );
check( 'the featured image gives the picture', $details['ImageURL'], 'https://example.test/racket.jpg' );
check( 'no price is sent when none is known', isset( $details['Price'] ), false );

$product                            = new stdClass();
$product->id                        = 'prod_abc';
$product->name                      = 'Carbon racket';
$product->image_url                 = 'https://cdn.example.test/racket.png';
$product->metrics                   = new stdClass();
$product->metrics->min_price_amount = 12950;
$product->metrics->currency         = 'aud';
$GLOBALS['product']                 = $product;

$details = blueworx_klaviyo_viewed_product_details( 7 );

check( 'SureCart\'s product gives the name', $details['ProductName'], 'Carbon racket' );
check( 'and its own ID', $details['ProductID'], 'prod_abc' );
check( 'the featured image still wins over SureCart\'s picture', $details['ImageURL'], 'https://example.test/racket.jpg' );
check( 'the lowest price, in whole currency', $details['Price'], 129.5 );
check( 'the currency, upper-cased', $details['Currency'], 'AUD' );

$GLOBALS['types'][8] = 'sc_product';
$details             = blueworx_klaviyo_viewed_product_details( 8 );
check( 'SureCart\'s picture fills in when the post has none', $details['ImageURL'], 'https://cdn.example.test/racket.png' );

$GLOBALS['filters']['blueworx_klaviyo_viewed_product'] = static function ( $details, $post_id ) {
	$details['Brand'] = 'BlueWorx';
	return $details;
};
check( 'a site plugin can add to the details', blueworx_klaviyo_viewed_product_details( 7 )['Brand'], 'BlueWorx' );
unset( $GLOBALS['filters']['blueworx_klaviyo_viewed_product'] );

// --- What the page sends ----------------------------------------------------

$GLOBALS['singular'] = 7;
$events              = blueworx_klaviyo_page_events();

check( 'a product page sends Viewed Product', $events[0]['name'], 'Viewed Product' );
check( 'with the product\'s details', $events[0]['properties']['ProductName'], 'Carbon racket' );

$GLOBALS['singular'] = 3;
check( 'an ordinary page sends nothing', blueworx_klaviyo_page_events(), array() );

$GLOBALS['filters']['blueworx_klaviyo_page_events'] = static function ( $events, $post_id ) {
	$events[] = array(
		'name'       => 'Viewed Guide',
		'properties' => array( 'post' => $post_id ),
	);
	$events[] = 'not an event';
	return $events;
};
$events = blueworx_klaviyo_page_events();
check( 'a site plugin can add a page event', $events[0]['name'], 'Viewed Guide' );
check( 'and gets the post it is on', $events[0]['properties']['post'], 3 );
check( 'anything that is not an event is dropped', count( $events ), 1 );
unset( $GLOBALS['filters']['blueworx_klaviyo_page_events'] );

// --- What the script is told ------------------------------------------------

$GLOBALS['singular'] = 7;
$config              = blueworx_klaviyo_tracking_config( blueworx_klaviyo_tracking_identity( null ), blueworx_klaviyo_page_events() );

check( 'nobody signed in is an empty identify', (array) $config['identify'], array() );
check( 'the events travel as they are', $config['events'][0]['name'], 'Viewed Product' );
check( 'a product page also fills Klaviyo\'s recently-viewed shape', $config['viewedItem']->Title, 'Carbon racket' );
check( 'with the price inside Metadata', $config['viewedItem']->Metadata['Price'], 129.5 );

$GLOBALS['singular'] = 3;
$config              = blueworx_klaviyo_tracking_config( array( 'email' => 'pat@example.com' ), blueworx_klaviyo_page_events() );

check( 'an ordinary page has no recently-viewed item', $config['viewedItem'], null );
check( 'a signed-in person is in the config', $config['identify']->email, 'pat@example.com' );

// --- The screen's extra lines -----------------------------------------------

check(
	'the test says visitors are tracked',
	blueworx_klaviyo_tracking_test_line(
		array(
			'ok'    => true,
			'lines' => array(),
		)
	)['lines'][0],
	'Visitors are tracked with the public key.'
);

$GLOBALS['options']['blueworx_klaviyo_public_key'] = '';
$line = blueworx_klaviyo_tracking_test_line(
	array(
		'ok'    => true,
		'lines' => array(),
	)
);
check( 'and says so, without failing, when there is no public key', $line['lines'][0], 'No public API key yet, so visitors are not tracked.' );
check( 'the test still passes', $line['ok'], true );

// Holding the script back for consent: only the opening tag changes, through the gate.
$gate   = static function ( $opening ) {
	return '<script type="text/plain" data-surecookie-category="marketing">';
};
$script = '<script src="https://static.klaviyo.com/onsite/js/AbC123/klaviyo.js" id="klaviyo-onsite-js" async></script>';
check( 'the opening tag is replaced and the rest kept', '<script type="text/plain" data-surecookie-category="marketing"></script>', blueworx_klaviyo_gate_script_html( $script, $gate ) );
check( 'an inline script keeps its body', '<script type="text/plain" data-surecookie-category="marketing">var a = 1;</script>', blueworx_klaviyo_gate_script_html( '<script id="x">var a = 1;</script>', $gate ) );
check( 'no script tag means no change', '<style>a{}</style>', blueworx_klaviyo_gate_script_html( '<style>a{}</style>', $gate ) );
check( 'no gate means no change', $script, blueworx_klaviyo_gate_script_html( $script, null ) );
check( 'other handles are left alone', '<script>x</script>', blueworx_klaviyo_gate_script_tag( '<script>x</script>', 'jquery' ) );
check( 'with consent off, Klaviyo is left alone too', $script, blueworx_klaviyo_gate_script_tag( $script, 'klaviyo-onsite' ) );

finish();
