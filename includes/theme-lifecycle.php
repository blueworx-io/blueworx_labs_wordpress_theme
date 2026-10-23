<?php
/**
 * Setting a site up, and cleaning it up, without plugin activation hooks.
 *
 * Themes have no activation, deactivation or uninstall hooks. The plugin's
 * activation work runs here on the first load of each site instead, which
 * also covers a new site on a network where the theme is already the default:
 * nobody switches the theme on there, it simply loads. Deactivation becomes the
 * switch_theme action.
 *
 * There is no uninstall step. A theme's code is not running when the theme is
 * deleted, so nothing could run it. Switching away already removes the
 * accounts that must not outlive the theme.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Puts back what switching away took away.
 *
 * Only the external role, and only when the function is already on — which
 * means the theme was switched away from and back. A fresh site has the
 * function off and registers the role when somebody switches it on.
 *
 * @return void
 */
function blueworx_labs_on_activate() {
	blueworx_external_on_activate();
}

/**
 * Removes the guest viewer and support accounts from the current site.
 *
 * Both read-only roles are near-administrator accounts whose safety comes from
 * the request-layer block in includes/readonly-access.php. With the theme
 * switched off that block does not run, so the accounts must not be left
 * standing.
 *
 * @return void
 */
function blueworx_labs_deactivate_site() {
	blueworx_support_on_deactivate();
	blueworx_external_on_deactivate();
}

/**
 * Runs this site's setup once per theme version.
 *
 * Tracked per site in blueworx_theme_setup_version, so every site on a
 * network is set up on its own first load, and again after an update.
 *
 * @return void
 */
function blueworx_theme_maybe_setup_site() {
	if ( BLUEWORX_LABS_VERSION === get_option( 'blueworx_theme_setup_version' ) ) {
		return;
	}

	blueworx_labs_on_activate();
	blueworx_theme_remove_switch_themes();

	update_option( 'blueworx_theme_setup_version', BLUEWORX_LABS_VERSION );
}
add_action( 'after_setup_theme', 'blueworx_theme_maybe_setup_site', 6 );

/**
 * Takes the ability to change theme away from every role but administrator.
 *
 * Switching away from this theme switches off the whole BlueWorx base, so
 * only administrators (and super admins, who hold every capability) may do it.
 * Roles a plugin adds later are covered by blueworx_theme_deny_switch_themes().
 *
 * @return void
 */
function blueworx_theme_remove_switch_themes() {
	foreach ( wp_roles()->role_objects as $name => $role ) {
		if ( 'administrator' !== $name && $role->has_cap( 'switch_themes' ) ) {
			$role->remove_cap( 'switch_themes' );
		}
	}
}

/**
 * Refuses switch_themes to anyone who is not an administrator.
 *
 * Covers roles registered after this site's setup ran, such as a client
 * plugin's own roles, and capabilities granted to a single user.
 *
 * @param array   $allcaps Capabilities the user has.
 * @param array   $caps    Capabilities being checked.
 * @param array   $args    The original check.
 * @param WP_User $user    The user being checked.
 * @return array
 */
function blueworx_theme_deny_switch_themes( $allcaps, $caps, $args, $user ) {
	if ( empty( $allcaps['switch_themes'] ) || ! in_array( 'switch_themes', (array) $caps, true ) ) {
		return $allcaps;
	}

	if ( in_array( 'administrator', (array) $user->roles, true ) || is_super_admin( $user->ID ) ) {
		return $allcaps;
	}

	$allcaps['switch_themes'] = false;

	return $allcaps;
}
add_filter( 'user_has_cap', 'blueworx_theme_deny_switch_themes', 10, 4 );

/**
 * Cleans up when this site switches to another theme.
 *
 * Does what plugin deactivation did, for this site only: a theme is switched
 * per site, never for a whole network at once. Also forgets that this site was
 * set up, so switching back runs the setup again and puts the role back.
 *
 * @param string   $new_name  The new theme's name.
 * @param WP_Theme $new_theme The new theme.
 * @param WP_Theme $old_theme The theme being switched away from.
 * @return void
 */
function blueworx_theme_on_switch_away( $new_name, $new_theme, $old_theme ) {
	if ( ! $old_theme instanceof WP_Theme || 'blueworx' !== $old_theme->get_template() ) {
		return;
	}

	// Switching from the parent to its own child theme keeps the theme running.
	if ( $new_theme instanceof WP_Theme && 'blueworx' === $new_theme->get_template() ) {
		return;
	}

	blueworx_labs_deactivate_site();
	delete_option( 'blueworx_theme_setup_version' );
}
add_action( 'switch_theme', 'blueworx_theme_on_switch_away', 10, 3 );
