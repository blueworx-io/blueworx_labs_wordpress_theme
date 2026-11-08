<?php
/**
 * Klaviyo feed: the shape of an item, and which keys open the feed.
 *
 * Run with: php tests/php/klaviyo-feed-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';

$GLOBALS['enabled'] = true;

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

function post_type_exists( $type ) {
	return false;
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/klaviyo/settings.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/client.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/feed.php';

// --- An item ------------------------------------------------------------------

$item = blueworx_klaviyo_feed_item(
	array(
		'ProductName' => 'Test racket',
		'ProductID'   => '42',
		'URL'         => 'https://example.test/products/test-racket/',
		'ImageURL'    => 'https://example.test/racket.jpg',
		'Price'       => 129.5,
		'Currency'    => 'AUD',
	),
	array(
		'description' => 'A fine racket.',
		'in_stock'    => true,
		'categories'  => array( 'Rackets', '' ),
	)
);

check( 'the id is Klaviyo\'s', $item['$id'], '42' );
check( 'so is the title', $item['$title'], 'Test racket' );
check( 'the link', $item['$link'], 'https://example.test/products/test-racket/' );
check( 'the image', $item['$image_link'], 'https://example.test/racket.jpg' );
check( 'the description', $item['$description'], 'A fine racket.' );
check( 'the price as a number', $item['$price'], 129.5 );
check( 'the currency rides along', $item['currency'], 'AUD' );
check( 'in stock is a quantity Klaviyo understands', $item['$inventory_quantity'], 1 );
check( 'with the policy that makes it count', $item['$inventory_policy'], 1 );
check( 'categories, with the empty one dropped', $item['$categories'], array( 'Rackets' ) );

$bare = blueworx_klaviyo_feed_item(
	array(
		'ProductName' => 'Grip',
		'ProductID'   => '43',
		'URL'         => 'https://example.test/products/grip/',
		'ImageURL'    => '',
		'Price'       => '',
		'Currency'    => '',
	)
);
check( 'a product without a price has none', isset( $bare['$price'] ), false );
check( 'nor an image', isset( $bare['$image_link'] ), false );
check( 'and no stock means no stock fields', isset( $bare['$inventory_quantity'] ), false );
check( 'a product without a name is not an item', blueworx_klaviyo_feed_item( array( 'ProductID' => '1' ) ), null );

$out = blueworx_klaviyo_feed_item(
	array(
		'ProductName' => 'Sold out',
		'ProductID'   => '44',
	),
	array( 'in_stock' => false )
);
check( 'out of stock is a quantity of none', $out['$inventory_quantity'], 0 );

// --- The key ------------------------------------------------------------------

check( 'the right key opens the feed', blueworx_klaviyo_feed_key_valid( 'abc123', 'abc123' ), true );
check( 'a wrong one does not', blueworx_klaviyo_feed_key_valid( 'abc124', 'abc123' ), false );
check( 'nor an empty one', blueworx_klaviyo_feed_key_valid( '', 'abc123' ), false );
check( 'nor anything when there is no secret yet', blueworx_klaviyo_feed_key_valid( '', '' ), false );

$first = blueworx_klaviyo_feed_secret();
check( 'a secret is made when there is none', strlen( $first ), 32 );
check( 'and kept', blueworx_klaviyo_feed_secret(), $first );
// The stub makes the same password every time, so only the length and the saving can be pinned here.
$fresh = blueworx_klaviyo_feed_new_secret();
check( 'a new one is made to the same length', strlen( $fresh ), 32 );
check( 'and saved in place of the old one', $GLOBALS['options']['blueworx_klaviyo_feed_secret'], $fresh );

// --- The filter -----------------------------------------------------------------

$GLOBALS['filters']['blueworx_klaviyo_feed_items'] = static function ( $items ) {
	$items[] = array(
		'$id'    => 'gift',
		'$title' => 'Gift card',
		'$price' => 50,
	);
	$items[] = array( '$title' => 'No id, dropped' );
	return $items;
};
$items = blueworx_klaviyo_feed_items();
check( 'a plugin can add an item', $items[0]['$title'], 'Gift card' );
check( ' and one without an id is dropped', count( $items ), 1 );

finish();
