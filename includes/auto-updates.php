<?php
/**
 * The theme keeps itself up to date.
 *
 * The update checker bootstrapped in includes/bootstrap.php tells WordPress that a
 * newer version exists. This is what decides that WordPress should go ahead and
 * install it, rather than leaving the offer on the Themes screen for somebody to
 * press. Together they are the whole of "nobody uploads a zip".
 *
 * Forced rather than a setting, deliberately. There is one answer for every site
 * this theme is on, so there is nothing to keep in sync and nothing to forget on
 * a site nobody has logged into for a year. The cost is real and worth naming: a
 * bad release reaches every site within about twelve hours, with no human in the
 * loop. What stands in its way is the CI that guards every merge, and a release
 * that refuses to publish when the tag and the theme's own version disagree.
 * There is no downgrade — a bad version is answered by releasing a better one.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns automatic updates on for this theme, and this theme only.
 *
 * Every theme on the site passes through this filter, so the incoming decision
 * is returned untouched for all the others — including a deliberate "no" that
 * some other plugin or the site owner has set, which this has no business
 * overturning.
 *
 * Matched on the theme's folder, not on whichever theme is active: with a
 * child theme active, the update on offer is still the parent's.
 *
 * @param bool|null $update Whether to update, as decided so far. Null means
 *                          nobody has an opinion yet.
 * @param object    $item   The update offer. Themes carry a `theme` property
 *                          holding the folder name, e.g. `blueworx`.
 * @return bool|null True for this theme, otherwise the decision as it arrived.
 */
function blueworx_force_own_auto_update( $update, $item ) {
	if ( ! is_object( $item ) || ! isset( $item->theme ) ) {
		return $update;
	}

	if ( BLUEWORX_THEME_SLUG !== $item->theme ) {
		return $update;
	}

	return true;
}
add_filter( 'auto_update_theme', 'blueworx_force_own_auto_update', 10, 2 );
