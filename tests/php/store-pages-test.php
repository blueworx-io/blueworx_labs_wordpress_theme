<?php
/**
 * The store page rules: what state a page is in and what the repair may fix.
 *
 * Run with: php tests/php/store-pages-test.php
 *
 * @package BlueWorxLabs
 */

// The shared WordPress stand-ins.
require __DIR__ . '/stubs.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

function blueworx_feature_enabled( $key ) {
	return true;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

require __DIR__ . '/../../includes/store/pages.php';

echo "A page's state\n";
check( 'no shop means no-shop whatever else is true', blueworx_store_decide( false, 5, 'publish' ), 'no-shop' );
check( 'a published page with an id is ok', blueworx_store_decide( true, 5, 'publish' ), 'ok' );
check( 'a trashed page is unpublished', blueworx_store_decide( true, 5, 'trash' ), 'unpublished' );
check( 'a draft is unpublished', blueworx_store_decide( true, 5, 'draft' ), 'unpublished' );
check( 'an id pointing at nothing is missing', blueworx_store_decide( true, 5, '' ), 'missing' );
check( 'no id is missing', blueworx_store_decide( true, 0, '' ), 'missing' );

echo "\nWhich problems the button can fix\n";
$pages    = blueworx_store_pages();
$problems = blueworx_store_problems(
	array(
		'checkout'           => 'ok',
		'shop'               => 'missing',
		'dashboard'          => 'unpublished',
		'order-confirmation' => 'no-shop',
	)
);
check( 'only missing and unpublished are problems', array_keys( $problems ), array( 'shop', 'dashboard' ) );
check( 'every known page is repairable', array_keys( blueworx_store_repairable( $problems, $pages ) ), array( 'shop', 'dashboard' ) );
check( 'an unknown missing page is not', blueworx_store_repairable( array( 'mystery' => 'missing' ), $pages ), array() );
check( 'but an unknown unpublished one can be republished', array_keys( blueworx_store_repairable( array( 'mystery' => 'unpublished' ), $pages ) ), array( 'mystery' ) );

echo "\nThe option name is SureCart's own\n";
check( 'checkout', blueworx_store_option_name( 'checkout' ), 'surecart_checkout_page_id' );
$GLOBALS['options']['surecart_checkout_page_id'] = '12';
check( 'a stored id is read as an int', blueworx_store_page_id( 'checkout' ), 12 );
$GLOBALS['options']['surecart_checkout_page_id'] = 'junk';
check( 'junk reads as 0', blueworx_store_page_id( 'checkout' ), 0 );

echo "\nThe thank-you page is made only when nothing ever made one\n";
check( 'never created: yes', blueworx_store_should_create_confirmation( true, 0, '' ), true );
check( 'deleted (id on record, no post): no', blueworx_store_should_create_confirmation( true, 9, '' ), false );
check( 'present: no', blueworx_store_should_create_confirmation( true, 9, 'publish' ), false );
check( 'no shop: no', blueworx_store_should_create_confirmation( false, 0, '' ), false );
$page = blueworx_store_confirmation_page();
check( 'it is SureCart\'s own slug', $page['slug'], 'order-confirmation' );
check( 'its block is filled, not empty', false !== strpos( $page['content'], '<!-- wp:surecart/order-confirmation-line-items -->' ), true );
check( 'and points the buyer at the dashboard', false !== strpos( $page['content'], 'wp:surecart/customer-dashboard-button' ), true );
check( 'inside SureCart\'s own wrapper, so the editor reads it as valid', false !== strpos( $page['content'], '<sc-order-confirmation><!-- wp:' ), true );

echo "\nAn empty confirmation block is filled as it renders\n";
$filled = array(
	'blockName'   => 'surecart/order-confirmation',
	'innerBlocks' => array( array( 'blockName' => 'surecart/order-confirmation-line-items' ) ),
	'innerHTML'   => '<sc-order-confirmation></sc-order-confirmation>',
);
$empty  = array(
	'blockName'   => 'surecart/order-confirmation',
	'innerBlocks' => array(),
	'innerHTML'   => ' ',
);
check( 'the empty one SureCart writes is filled', blueworx_store_fill_confirmation( $empty, $filled ), $filled );
$own = array(
	'blockName'   => 'surecart/order-confirmation',
	'innerBlocks' => array( array( 'blockName' => 'core/paragraph' ) ),
	'innerHTML'   => '<sc-order-confirmation></sc-order-confirmation>',
);
check( 'one the site has filled itself is left alone', blueworx_store_fill_confirmation( $own, $filled ), $own );
$para = array(
	'blockName'   => 'core/paragraph',
	'innerBlocks' => array(),
	'innerHTML'   => '',
);
check( 'any other block is left alone', blueworx_store_fill_confirmation( $para, $filled ), $para );

echo "\nThe four pages\n";
check( 'are checkout, order-confirmation, dashboard, shop', array_keys( $pages ), array( 'checkout', 'order-confirmation', 'dashboard', 'shop' ) );

echo "\nA page's status and statuses, by name\n";
$GLOBALS['options']['surecart_checkout_page_id'] = 0;
check( 'status with no shop', blueworx_store_page_status( 'checkout' ), 'no-shop' );
check( 'statuses cover the four pages', array_keys( blueworx_store_page_statuses() ), array( 'checkout', 'order-confirmation', 'dashboard', 'shop' ) );

finish();
