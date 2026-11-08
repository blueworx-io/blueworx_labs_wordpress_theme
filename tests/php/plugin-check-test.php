<?php
/**
 * Plugin check: which installed plugins are flagged, and how the list is kept.
 *
 * Run with: php tests/php/plugin-check-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs carry core's own
// unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

require __DIR__ . '/stubs.php';

function add_action( $hook, $callback ) {
	return true;
}

function _n( $single, $plural, $number ) {
	return 1 === $number ? $single : $plural;
}

function blueworx_ds_notice( $args ) {
	return '[' . $args['title'] . '|' . $args['actions'] . ']';
}

function blueworx_ds_button( $args ) {
	return '<a href="' . $args['href'] . '">' . $args['label'] . '</a>';
}

require dirname( __DIR__, 2 ) . '/includes/plugin-check.php';

$url = blueworx_plugin_check_url();
check( 'the list is read from the foundation v1 tag', $url, 'https://raw.githubusercontent.com/blueworx-io/bluegroup_core_foundation/v1/approved-plugins.json' );

// Slugs.
check( 'slug is the folder', blueworx_plugin_check_slug( 'surecart/surecart.php' ), 'surecart' );
check( 'slug of a single-file plugin is the file name', blueworx_plugin_check_slug( 'hello.php' ), 'hello' );

// Parsing.
check( 'garbage is not a list', blueworx_plugin_check_parse( 'nope' ), null );
check( 'missing plugins key is not a list', blueworx_plugin_check_parse( '{"updated":"x"}' ), null );
$parsed = blueworx_plugin_check_parse( '{"updated":"x","plugins":{"surecart":{"name":"SureCart"}}}' );
check( 'a list parses to slug => entry', array_keys( $parsed ), array( 'surecart' ) );

// Flagging.
$installed = array(
	'surecart/surecart.php'                               => array( 'Name' => 'SureCart' ),
	'blueworx-labs-clubhouse/blueworx-labs-clubhouse.php' => array( 'Name' => 'Clubhouse V1' ),
	'hello.php'                                           => array( 'Name' => 'Hello' ),
);
$approved  = array(
	'surecart'              => array(),
	'blueworx-clubhouse-v2' => array(),
);
check(
	'unlisted plugins are flagged, listed ones are not',
	array_keys( blueworx_plugin_check_flagged( $installed, $approved ) ),
	array( 'blueworx-labs-clubhouse/blueworx-labs-clubhouse.php', 'hello.php' )
);
check( 'an empty list flags everything', count( blueworx_plugin_check_flagged( $installed, array() ) ), 3 );
check( 'nothing installed, nothing flagged', blueworx_plugin_check_flagged( array(), $approved ), array() );

// Fetching and keeping the list.
$GLOBALS['http'][ $url ] = array(
	'updated' => 'now',
	'plugins' => array( 'surecart' => array( 'name' => 'SureCart' ) ),
);
$list                    = blueworx_plugin_check_list();
check( 'a fetch gives the list', array_keys( $list['plugins'] ), array( 'surecart' ) );
check( 'a fetch is stamped', $list['fetched'] > 0, true );

$last = get_option( 'blueworx_approved_plugins_last_good' );
check( 'the last good list is kept', isset( $last['plugins']['surecart'] ), true );

$calls = $GLOBALS['calls'];
blueworx_plugin_check_list();
check( 'a second look in the same day does not fetch again', $GLOBALS['calls'], $calls );

// A failed fetch keeps the last good list and its date.
$stamp = $list['fetched'];
unset( $GLOBALS['http'][ $url ] );
$kept = blueworx_plugin_check_list( true );
check( 'a failed fetch keeps the last good list', array_keys( $kept['plugins'] ), array( 'surecart' ) );
check( 'a failed fetch keeps the old date', $kept['fetched'], $stamp );

// No list ever: nothing to compare against.
$GLOBALS['options']    = array();
$GLOBALS['transients'] = array();
$none                  = blueworx_plugin_check_list( true );
check( 'no list known gives null, not an empty list', $none['plugins'], null );

// The filter supplies a list.
$GLOBALS['filters']['blueworx_approved_plugins'] = static function () {
	return array( 'hello' => array() );
};
$supplied = blueworx_plugin_check_list();
check( 'the filter can supply the list', array_keys( $supplied['plugins'] ), array( 'hello' ) );

// The notice.
check( 'no notice when nothing is flagged', blueworx_plugin_check_notice_html( 0, 'u' ), '' );
check( 'one plugin reads singular', false !== strpos( blueworx_plugin_check_notice_html( 1, 'u' ), '1 plugin not on' ), true );
check( 'several read plural', false !== strpos( blueworx_plugin_check_notice_html( 3, 'u' ), '3 plugins not on' ), true );

finish();
