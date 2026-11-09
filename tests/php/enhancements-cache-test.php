<?php
/**
 * Saving the Enhancements screen (switches and translation settings) clears the page cache.
 *
 * Run with: php tests/php/enhancements-cache-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

namespace {
	require __DIR__ . '/stubs.php';

	$GLOBALS['purges'] = array();
	function blueworx_purge_site_cache( $reason ) {
		$GLOBALS['purges'][] = $reason;
	}
	function blueworx_feature_enabled( $key ) {
		return false;
	}
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

	require dirname( __DIR__, 2 ) . '/includes/admin-settings.php';

	echo "Saving Enhancements clears the cache\n";

	blueworx_feature_settings_refresh_cache();
	check( 'one purge', count( $GLOBALS['purges'] ), 1 );
	check( 'named Enhancements', $GLOBALS['purges'][0], 'Enhancements' );

	// The save handler must call it after the translation settings are written.
	$handler = new ReflectionFunction( 'blueworx_save_feature_settings' );
	$lines   = array_slice( file( $handler->getFileName() ), $handler->getStartLine() - 1, $handler->getEndLine() - $handler->getStartLine() + 1 );
	$source  = implode( '', $lines );

	$translate = strpos( $source, 'blueworx_translate_save_settings(' );
	$refresh   = strpos( $source, 'blueworx_feature_settings_refresh_cache()' );
	check( 'the save handler clears the cache', false !== $refresh, true );
	check( 'after translation settings are saved', false !== $translate && $refresh > $translate, true );
	check( 'before it redirects', $refresh < strpos( $source, 'wp_safe_redirect(' ), true );

	finish();
}
