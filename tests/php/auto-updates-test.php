<?php
/**
 * The rule that makes this theme update itself.
 *
 * The filter has to answer for exactly one theme and leave every other theme's
 * answer alone — including a deliberate "no" set elsewhere, which this must
 * never overturn. That is text in, text out, so it runs here rather than in a
 * browser. What only a running site can show is that WordPress reads the answer
 * as "forced on", and tests/admin-theme.spec.js covers that.
 *
 * Run with: php tests/php/auto-updates-test.php
 *
 * @package BlueWorxLabs
 */

// The shared WordPress stand-ins. Kept apart from the docblock above, which
// phpcs otherwise reads as this statement's rather than the file's.
require __DIR__ . '/stubs.php';

// This script stands in for WordPress rather than being loaded into it, so its
// stubs have to carry core's names.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Same: the hook arguments are part of the signature.

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

define( 'BLUEWORX_THEME_SLUG', 'blueworx-labs-wordpress-theme' );

require __DIR__ . '/../../includes/auto-updates.php';

$ours  = (object) array( 'theme' => 'blueworx-labs-wordpress-theme' );
$other = (object) array( 'theme' => 'twentytwentyfive' );
$child = (object) array( 'theme' => 'blueworx-child' );

echo "This theme answers only for itself\n";

check( 'it updates itself', blueworx_force_own_auto_update( null, $ours ), true );
check( 'and says so even where the site has said no', blueworx_force_own_auto_update( false, $ours ), true );

echo "\nAnd never speaks for anyone else\n";

check( 'another theme keeps its own answer', blueworx_force_own_auto_update( null, $other ), null );
check( 'including a deliberate no', blueworx_force_own_auto_update( false, $other ), false );
check( 'and a yes', blueworx_force_own_auto_update( true, $other ), true );
check( 'a child theme of this one is left alone', blueworx_force_own_auto_update( null, $child ), null );

echo "\nAnd it survives being handed something odd\n";

// WordPress hands plugin and translation items through other filters, but a
// stray one here carries no `theme` property at all.
check( 'a plugin item', blueworx_force_own_auto_update( null, (object) array( 'plugin' => 'blueworx-labs-wordpress/blueworx-labs-wordpress.php' ) ), null );
check( 'an item with no theme name', blueworx_force_own_auto_update( null, (object) array() ), null );
check( 'and no item at all', blueworx_force_own_auto_update( null, null ), null );

finish();
