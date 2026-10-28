<?php
/**
 * Site styles: a site that already ran the theme keeps its look on update.
 *
 * 2.1.0 adds Site styles default-on, which restyles every public page. Every
 * live site updates itself, so the one thing that must hold is that a site
 * with a version row gets the switch written off, a fresh site does not, and a
 * choice somebody already made is never overwritten.
 *
 * Run with: php tests/php/site-styles-migration-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function add_action( $hook, $callback, $priority = 10 ) {
	return true;
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/upgrade.php';

check( 'the migration is counted', blueworx_get_labs_db_version() >= 15, true );

$GLOBALS['options'] = array( 'blueworx_labs_db_version' => 14 );
blueworx_migrate_site_styles_default();
check( 'a site that already ran the theme gets site styles off', get_option( 'blueworx_feature_site_styles', null ), '0' );

$GLOBALS['options'] = array();
blueworx_migrate_site_styles_default();
check( 'a fresh site is left to the default, which is on', get_option( 'blueworx_feature_site_styles', null ), null );

$GLOBALS['options'] = array(
	'blueworx_labs_db_version'     => 14,
	'blueworx_feature_site_styles' => '1',
);
blueworx_migrate_site_styles_default();
check( 'a choice already made is kept', get_option( 'blueworx_feature_site_styles', null ), '1' );

finish();
