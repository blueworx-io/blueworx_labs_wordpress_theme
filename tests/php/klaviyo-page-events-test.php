<?php
/**
 * Klaviyo page events: what a marked page sends, and that an unmarked one
 * sends nothing.
 *
 * Run with: php tests/php/klaviyo-page-events-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';

$GLOBALS['enabled']  = true;
$GLOBALS['admin']    = false;
$GLOBALS['can']      = false;
$GLOBALS['singular'] = 0;
$GLOBALS['types']    = array();
$GLOBALS['meta']     = array();

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['filters'][ $hook ] = $callback;
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
	return isset( $GLOBALS['types'][ $post_id ] ) ? $GLOBALS['types'][ $post_id ] : 'page';
}

function get_the_title( $post_id ) {
	return 'Page ' . $post_id;
}

function get_permalink( $post_id ) {
	return 'https://example.test/?p=' . $post_id;
}

function get_the_post_thumbnail_url( $post_id, $size = 'post-thumbnail' ) {
	return false;
}

function get_post_meta( $post_id, $key, $single = false ) {
	return isset( $GLOBALS['meta'][ $post_id ] ) ? $GLOBALS['meta'][ $post_id ] : '';
}

function get_post_types( $args = array() ) {
	return array(
		'post'       => 'post',
		'page'       => 'page',
		'attachment' => 'attachment',
		'sc_product' => 'sc_product',
	);
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/klaviyo/settings.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/client.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/tracking.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/page-events.php';

// --- The event itself ---------------------------------------------------------

$event = blueworx_klaviyo_page_event_for( '  Booked a Demo ', 'Book a demo', 'https://example.test/demo/', 'page' );
check( 'the name is tidied', $event['name'], 'Booked a Demo' );
check( 'the page title goes with it', $event['properties']['PageTitle'], 'Book a demo' );
check( 'and its address', $event['properties']['URL'], 'https://example.test/demo/' );
check( 'and its kind', $event['properties']['PostType'], 'page' );
check( 'no name means no event', blueworx_klaviyo_page_event_for( '', 'Title', 'https://example.test/', 'page' ), null );
check( 'nor does a name of spaces', blueworx_klaviyo_page_event_for( '   ', 'Title', 'https://example.test/', 'page' ), null );
check( 'tags are stripped from a name', blueworx_klaviyo_page_event_for( '<script>x</script>', 'Title', '', 'page' )['name'], 'x' );

// --- Which types can have one -----------------------------------------------------

check( 'every public type but attachments', blueworx_klaviyo_page_event_post_types(), array( 'post', 'page', 'sc_product' ) );

// --- On the page --------------------------------------------------------------------

$GLOBALS['singular'] = 12;
$GLOBALS['meta'][12] = 'Viewed Pricing';

$events = blueworx_klaviyo_page_events();
check( 'a marked page sends its event', count( $events ), 1 );
check( 'by name', $events[0]['name'], 'Viewed Pricing' );
check( 'with the page title', $events[0]['properties']['PageTitle'], 'Page 12' );

$GLOBALS['singular'] = 13;
check( 'an unmarked page sends nothing', blueworx_klaviyo_page_events(), array() );

$GLOBALS['singular'] = 7;
$GLOBALS['types'][7] = 'sc_product';
$GLOBALS['meta'][7]  = 'Viewed Hero Product';
$events              = blueworx_klaviyo_page_events();
check( 'a product page keeps Viewed Product', $events[0]['name'], 'Viewed Product' );
check( 'and adds its own', $events[1]['name'], 'Viewed Hero Product' );

$GLOBALS['singular'] = 0;
check( 'nothing on a page that is not one post', blueworx_klaviyo_page_events(), array() );

finish();
