<?php
/**
 * View the admin as another role.
 *
 * Replaces the Admin and Site Enhancements `view_admin_as_role` module. The
 * other half of that ASE pair — assigning a user more than one role — is
 * already covered by includes/user-roles.php and is not duplicated here.
 *
 * The rule this file is built around: viewing as a role may only ever REMOVE
 * capabilities. It never grants one. The switch is applied by filtering
 * `user_has_cap` down to the target role's own capability map, so a
 * capability the real user does not hold cannot appear no matter what the
 * target role says. A network's super admin skips that filter altogether, so
 * for them the same narrowing is applied a step earlier, at `map_meta_cap`.
 *
 * The preview also changes the role NAME other code sees. Plenty of plugins
 * (SureCart, Clubhouse, membership plugins) decide what to show by asking
 * which role somebody holds rather than what they may do, and an administrator
 * previewing a customer still looked like an administrator to all of them. So
 * while a preview runs, every user object built for the person previewing
 * reports the previewed role as their only role. That happens at the one place
 * every user object reads its roles from — the capabilities user meta — and
 * only in memory: nothing about the account is ever written, so ending the
 * preview, or simply never coming back to end it, leaves the real roles exactly
 * where they were. A guard on writes to that same meta stops the swapped view
 * being saved back over the real one.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The user option holding the role currently being viewed as.
 *
 * Saved as a user option, so the key on disk carries the site's table prefix
 * and a preview started on one site of a network stays on that site. Versions
 * before that saved it under this bare name, on the account itself — see
 * blueworx_view_as_migrate_legacy().
 */
const BLUEWORX_VIEW_AS_META = '_blueworx_view_as_role';

/**
 * The user meta key this site's preview is saved under.
 *
 * The same key update_user_option() writes: the option name with the current
 * site's table prefix in front.
 *
 * @return string Meta key.
 */
function blueworx_view_as_site_key() {
	global $wpdb;

	return $wpdb->get_blog_prefix() . BLUEWORX_VIEW_AS_META;
}

/**
 * Starts or ends a preview on the current site.
 *
 * @param int    $user_id User ID.
 * @param string $role    Role slug to preview, or an empty string to end it.
 * @return void
 */
function blueworx_view_as_store_role( $user_id, $role ) {
	if ( '' === $role ) {
		delete_user_option( $user_id, BLUEWORX_VIEW_AS_META );
		return;
	}

	update_user_option( $user_id, BLUEWORX_VIEW_AS_META, $role );
}

/**
 * Picks up a preview saved by an older version, once.
 *
 * Older versions kept the preview on the account rather than the site, under
 * the bare option name. On a single site there is only one place it can have
 * been started, so it is moved onto that site's key and carries on. On a
 * network there is no telling which site it belonged to, so it is ended rather
 * than guessed at — a preview that turned up on the wrong site is the bug this
 * storage exists to fix. Either way the old key is removed, which also stops
 * get_user_option() falling back to it on every site.
 *
 * @param int $user_id User ID.
 * @return void
 */
function blueworx_view_as_migrate_legacy( $user_id ) {
	// Writing user meta fires hooks, and anything on them that asks a
	// permission question would land back here before the old key is gone.
	static $busy = false;

	if ( $busy ) {
		return;
	}

	$legacy = (string) get_user_meta( $user_id, BLUEWORX_VIEW_AS_META, true );

	if ( '' === $legacy ) {
		return;
	}

	$busy = true;

	delete_user_meta( $user_id, BLUEWORX_VIEW_AS_META );

	if ( ! is_multisite() && '' === (string) get_user_meta( $user_id, blueworx_view_as_site_key(), true ) ) {
		blueworx_view_as_store_role( $user_id, $legacy );
	}

	$busy = false;
}

/**
 * The user being shown as their previewed role in this request, if anyone.
 *
 * Set when WordPress settles who is signed in (blueworx_view_as_begin()), and
 * only ever the signed-in person. Kept here rather than worked out on the spot
 * because the place it is read — a user object being built — is the one moment
 * asking "who is signed in?" could start building that same object again.
 *
 * @param int|null $user_id New value, or null to only read it.
 * @return int User ID, or 0 for nobody.
 */
function blueworx_view_as_swapping( $user_id = null ) {
	static $swapping = 0;

	if ( null !== $user_id ) {
		$swapping = (int) $user_id;
	}

	return $swapping;
}

/**
 * Whether reads and writes of the roles meta are passing straight through.
 *
 * On while this file reads or writes the real, stored roles itself, so its own
 * filters on that meta stand aside for it.
 *
 * @param bool|null $raw New value, or null to only read it.
 * @return bool True while bypassing.
 */
function blueworx_view_as_raw( $raw = null ) {
	static $on = false;

	if ( null !== $raw ) {
		$on = (bool) $raw;
	}

	return $on;
}

/**
 * The user meta key this site keeps each person's roles under.
 *
 * The same key WP_User reads: the site's table prefix, then "capabilities".
 *
 * @return string Meta key.
 */
function blueworx_view_as_caps_key() {
	global $wpdb;

	return $wpdb->get_blog_prefix() . 'capabilities';
}

/**
 * The roles and capabilities stored on the account, untouched by any preview.
 *
 * @param int $user_id User ID.
 * @return array Stored capabilities meta: role slugs and single capabilities.
 */
function blueworx_view_as_stored_caps( $user_id ) {
	blueworx_view_as_raw( true );
	$stored = get_user_meta( $user_id, blueworx_view_as_caps_key(), true );
	blueworx_view_as_raw( false );

	return is_array( $stored ) ? $stored : array();
}

/**
 * Every role's capability map, keyed by role slug.
 *
 * @return array Capability maps.
 */
function blueworx_view_as_role_maps() {
	$maps = array();

	foreach ( wp_roles()->roles as $slug => $role ) {
		$maps[ $slug ] = (array) $role['capabilities'];
	}

	return $maps;
}

/**
 * Turns a capabilities meta value into roles and capabilities, as WP_User does.
 *
 * The roles are the keys that name a role; the capabilities are every one of
 * those roles' maps merged, with the meta laid over the top. Pure, so the
 * checks can see exactly what a user object built from a given value holds.
 *
 * @param array $stored    Capabilities meta.
 * @param array $role_maps Capability maps keyed by role slug.
 * @return array { roles: string[], allcaps: array }.
 */
function blueworx_view_as_build_caps( $stored, $role_maps ) {
	$roles   = array();
	$allcaps = array();

	foreach ( array_keys( (array) $stored ) as $key ) {
		if ( isset( $role_maps[ $key ] ) ) {
			$roles[] = $key;
			$allcaps = array_merge( $allcaps, (array) $role_maps[ $key ] );
		}
	}

	return array(
		'roles'   => $roles,
		'allcaps' => array_merge( $allcaps, (array) $stored ),
	);
}

/**
 * Who the real person is, before a preview narrows or renames anything.
 *
 * Read off the user object rather than through current_user_can(), which is
 * exactly what blueworx_view_as_filter_caps() is narrowing. Asking the filtered
 * question would make the control vanish the moment somebody previewed a role
 * that cannot do whatever the control is gated on — the trap that used to force
 * this feature to be administrators-only. While a preview is renaming the role
 * the object names the previewed role too, so the answer then comes from the
 * stored roles instead.
 *
 * A network's super admin can do everything on every site, whether or not they
 * hold a role there, so every role's capabilities count as theirs. Without that
 * the control would be missing on any site where they hold no role — and on a
 * site where they lost theirs mid-preview, so would the way out.
 *
 * @return array { roles: string[], allcaps: array }.
 */
function blueworx_view_as_real_state() {
	$user = wp_get_current_user();

	if ( ! $user || ! $user->exists() ) {
		return array(
			'roles'   => array(),
			'allcaps' => array(),
		);
	}

	if ( blueworx_view_as_swapping() === (int) $user->ID ) {
		$state = blueworx_view_as_build_caps( blueworx_view_as_stored_caps( $user->ID ), blueworx_view_as_role_maps() );
	} else {
		$state = array(
			'roles'   => (array) $user->roles,
			'allcaps' => (array) $user->allcaps,
		);
	}

	// is_multisite() first: on a single site is_super_admin() asks has_cap(),
	// which is the filter this file narrows. Asked of the signed-in user rather
	// than by ID, because by ID it builds a fresh user object, and this runs
	// while one is being built.
	if ( is_multisite() && is_super_admin() ) {
		foreach ( wp_roles()->roles as $role ) {
			$state['allcaps'] = array_merge( $state['allcaps'], array_filter( (array) $role['capabilities'] ) );
		}
	}

	return $state;
}

/**
 * What the real person can do, before a preview narrows anything.
 *
 * @return array Capabilities the real user holds, keyed by name.
 */
function blueworx_view_as_real_caps() {
	$state = blueworx_view_as_real_state();

	return $state['allcaps'];
}

/**
 * The roles meta to show in place of the stored one while previewing a role.
 *
 * Only the previewed role, so to any plugin that asks it is the one role held,
 * and the real roles, being absent, read as not held. Two rules on top:
 *
 * - The previewed role must sit at or below the real person: every capability
 *   it grants is one they already hold. Otherwise renaming them into it would
 *   hand over capabilities they lack, so the preview falls back to narrowing
 *   capabilities alone, as it always did.
 * - `read` is carried over from the real person when the role lacks it, for
 *   the reason blueworx_view_as_filter_caps() carries it: without it wp-admin
 *   will not open, and the way out of the preview lives there.
 *
 * Pure: the role, its capabilities and the real person's in; the meta out.
 *
 * @param string $role_slug    Role being previewed.
 * @param array  $role_caps    That role's capability map.
 * @param array  $real_allcaps What the real person can do.
 * @return array|null Meta to show, or null to leave the stored roles showing.
 */
function blueworx_view_as_swap_caps( $role_slug, $role_caps, $real_allcaps ) {
	if ( '' === (string) $role_slug ) {
		return null;
	}

	foreach ( (array) $role_caps as $cap => $granted ) {
		if ( $granted && empty( $real_allcaps[ $cap ] ) ) {
			return null;
		}
	}

	$shown = array( $role_slug => true );

	if ( empty( $role_caps['read'] ) && ! empty( $real_allcaps['read'] ) ) {
		$shown['read'] = true;
	}

	return $shown;
}

/**
 * What to do with a write to the roles meta of somebody mid-preview.
 *
 * The danger is a write built from the swapped view: the Profile screen, or a
 * plugin calling set_role(), remove_role() or wp_update_user() on the user
 * object it was handed, saving "subscriber" — or no role at all — over an
 * administrator. So the write is laid onto the STORED roles instead, keeping
 * only what it adds on top of what the preview was showing. The previewed role
 * is never stored, and nothing the preview was hiding is ever taken away. A
 * write that adds nothing new is refused outright; one that does — a shop
 * plugin granting its customer role at checkout, say — goes through, on top of
 * the real roles rather than in place of them.
 *
 * Pure: the stored meta, the meta being shown, the write and the role in; the
 * decision out.
 *
 * @param array  $stored    Roles meta as stored.
 * @param array  $shown     Roles meta the preview is showing.
 * @param mixed  $value     What the write would store.
 * @param string $role_slug Role being previewed.
 * @return array|false|null Meta to store instead, false to refuse, or null to let it through.
 */
function blueworx_view_as_caps_write( $stored, $shown, $value, $role_slug ) {
	// Not a roles map, so not something the swapped view produced.
	if ( ! is_array( $value ) ) {
		return null;
	}

	$merged = (array) $stored;

	foreach ( $value as $key => $granted ) {
		if ( $role_slug === $key || array_key_exists( $key, (array) $shown ) ) {
			continue;
		}

		$merged[ $key ] = $granted;
	}

	// Loose on purpose: the same roles in a different order are the same write.
	return $merged == $stored ? false : $merged; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
}

/**
 * Whether this user may use the switch at all.
 *
 * Anyone who edits the site, and never a BlueWorx support agent. A support
 * session is read-only by virtue of a capability set this plugin controls;
 * letting it swap that set for another role's would replace the guarantee with
 * a different one nobody has reviewed.
 *
 * @return bool True when the switch is available.
 */
function blueworx_view_as_available() {
	if ( ! blueworx_feature_enabled( 'view_as_role' ) ) {
		return false;
	}

	if ( function_exists( 'blueworx_support_is_support_user' ) && blueworx_support_is_support_user() ) {
		return false;
	}

	$caps = blueworx_view_as_real_caps();

	return ! empty( $caps['edit_posts'] );
}

/**
 * Gets the roles this user may view as, in alphabetical order.
 *
 * Downwards only. A role is offered when the real user already holds every
 * capability that role grants, so an editor is offered author, contributor and
 * subscriber and never the other way about. That is the same rule the
 * capability filter enforces — this just stops the menu offering something the
 * filter would refuse to honour.
 *
 * Administrator is excluded outright, and so is whatever the user already is:
 * their own level is not a preview, it is the "My own view" option.
 *
 * @return array Role labels keyed by slug, ordered by name.
 */
function blueworx_view_as_role_choices() {
	// The real roles, not the ones a preview is showing: the previewed role is
	// not "your own level", it is one of the places you can go.
	$real  = blueworx_view_as_real_state();
	$mine  = $real['allcaps'];
	$owned = $real['roles'];

	$choices = array();

	foreach ( wp_roles()->roles as $slug => $role ) {
		if ( 'administrator' === $slug || in_array( $slug, $owned, true ) ) {
			continue;
		}

		$caps = array_keys( array_filter( (array) $role['capabilities'] ) );

		foreach ( $caps as $cap ) {
			if ( empty( $mine[ $cap ] ) ) {
				continue 2;
			}
		}

		$choices[ $slug ] = translate_user_role( $role['name'] );
	}

	// Alphabetical, by the name on screen rather than the slug behind it. A list
	// you can run your eye down beats one ordered by how much each role can do:
	// that order is invisible to the person reading it, and leaves the menu
	// arranged differently on every site.
	uasort( $choices, 'strnatcasecmp' );

	return $choices;
}

/**
 * Gets the role the current user is viewing as.
 *
 * Answers from stored state alone, and asks no permission question of its own.
 * This is what the `user_has_cap` callback reads, so anything here that went
 * back through current_user_can() would fire that filter again and the two
 * would call each other until PHP ran out of stack. Nothing here needs the
 * check anyway: the capability filter can only ever narrow.
 *
 * @return string Role slug, or an empty string.
 */
function blueworx_view_as_current_role() {
	// No control, no preview. The control lives in the re-skinned sidebar, so
	// switching the admin theme off would otherwise leave somebody narrowed to a
	// role with nothing on screen to undo it and — now that a preview really does
	// take the settings screens away — no way back at all.
	if ( ! function_exists( 'blueworx_admin_theme_enabled' ) || ! blueworx_admin_theme_enabled() ) {
		return '';
	}

	// The network admin belongs to no one site, so no site's preview applies.
	if ( is_multisite() && is_network_admin() ) {
		return '';
	}

	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return '';
	}

	blueworx_view_as_migrate_legacy( $user_id );

	// Read straight from this site's key rather than through get_user_option().
	// That builds a fresh user object on every call, and this runs on every
	// permission check; it would also fall back to the old account-wide key.
	$role = (string) get_user_meta( $user_id, blueworx_view_as_site_key(), true );

	if ( '' === $role || 'administrator' === $role || ! get_role( $role ) ) {
		return '';
	}

	return $role;
}

/**
 * Narrows the current user's capabilities to those of the viewed role.
 *
 * Intersection, not replacement: a capability is granted only when BOTH the
 * viewed role and the real user hold it. That is what makes this incapable of
 * escalating — the result is always a subset of what the user could already do.
 *
 * One capability survives the narrowing: `read`, without which wp-admin will
 * not load at all and the preview would be of the login screen. `manage_options`
 * used to survive too, so that an administrator mid-preview could still reach
 * the settings screen the way out lived on. The way out is now the sidebar
 * control, which is present on every admin screen and gated on the real user's
 * capabilities, so the exception has been dropped: previewing a role now hides
 * the settings screens exactly as that role would find them hidden.
 *
 * `read` is only ever CARRIED OVER, never added: if the real user did not hold
 * it, it is not granted here. A leftover meta value on a non-administrator
 * therefore narrows their access and cannot widen it.
 *
 * Only the person previewing is narrowed. The filter also runs when a screen
 * checks somebody else — "can this author edit this post?" — and that answer
 * must stay the other person's own (#236).
 *
 * The previewed role's own name is carried over the same way `read` is: a
 * plugin asking current_user_can( 'customer' ) should hear yes while somebody
 * previews a customer. It is only there to carry when the user object is
 * already showing that role (blueworx_view_as_swap_caps() decided so), which is
 * what keeps it from granting anything.
 *
 * @param array        $allcaps Capabilities the user holds.
 * @param array        $caps    Primitive capabilities being checked.
 * @param array        $args    The original check's arguments.
 * @param WP_User|null $user    The user being checked.
 * @return array Filtered capabilities.
 */
function blueworx_view_as_filter_caps( $allcaps, $caps = array(), $args = array(), $user = null ) {
	if ( $user && get_current_user_id() !== (int) $user->ID ) {
		return $allcaps;
	}

	$role_slug = blueworx_view_as_current_role();

	if ( '' === $role_slug ) {
		return $allcaps;
	}

	$role_caps = blueworx_view_as_role_caps( $role_slug );

	if ( null === $role_caps ) {
		return $allcaps;
	}

	$filtered = array();

	foreach ( (array) $allcaps as $cap => $granted ) {
		$filtered[ $cap ] = $granted && ! empty( $role_caps[ $cap ] );
	}

	$filtered['read']       = ! empty( $allcaps['read'] );
	$filtered[ $role_slug ] = ! empty( $allcaps[ $role_slug ] );

	return $filtered;
}

/**
 * The capabilities a role holds, as its members actually get them.
 *
 * The support and guest viewer roles store sign-in rights only and get the
 * rest while the theme runs, so their stored map would preview as nearly
 * nothing.
 *
 * @param string $role_slug Role slug.
 * @return array|null Capability map, or null when there is no such role.
 */
function blueworx_view_as_role_caps( $role_slug ) {
	$role = get_role( $role_slug );

	if ( ! $role ) {
		return null;
	}

	$granted = function_exists( 'blueworx_readonly_role_caps' ) ? blueworx_readonly_role_caps( $role_slug ) : null;

	return null !== $granted ? $granted + (array) $role->capabilities : (array) $role->capabilities;
}

/**
 * Holds a previewing super admin to the previewed role.
 *
 * WP_User::has_cap() answers yes to a network's super admin before the
 * `user_has_cap` filter runs, so blueworx_view_as_filter_caps() never sees
 * them. The one thing that shortcut still honours is `do_not_allow` coming out
 * of map_meta_cap(), so that is where the narrowing happens for them: any check
 * needing a capability the previewed role does not hold is refused.
 *
 * `read` and `exist` always pass, for the same reason `read` survives the other
 * filter: without them wp-admin will not open, and the sidebar control that ends
 * the preview lives there. Ending it is gated on the real user's capabilities,
 * not on this, so it stays reachable whatever is being previewed.
 *
 * The previewed role's own name passes too, but only while the user object is
 * showing that role, so current_user_can( 'subscriber' ) answers as it would
 * for a subscriber. Every other role name — administrator included — is
 * refused like any capability the role lacks.
 *
 * @param array  $caps    Primitive capabilities the check needs.
 * @param string $cap     Capability being checked.
 * @param int    $user_id User being checked.
 * @return array The same list, or `do_not_allow`.
 */
function blueworx_view_as_super_admin_caps( $caps, $cap, $user_id ) {
	if ( ! is_multisite() || get_current_user_id() !== (int) $user_id ) {
		return $caps;
	}

	$role_slug = blueworx_view_as_current_role();

	if ( '' === $role_slug || ! is_super_admin( $user_id ) ) {
		return $caps;
	}

	$role_caps = blueworx_view_as_role_caps( $role_slug );

	if ( null === $role_caps ) {
		return $caps;
	}

	$shows_role = in_array( $role_slug, (array) wp_get_current_user()->roles, true );

	foreach ( (array) $caps as $needed ) {
		if ( 'read' === $needed || 'exist' === $needed || ( $shows_role && $role_slug === $needed ) ) {
			continue;
		}

		if ( empty( $role_caps[ $needed ] ) ) {
			return array( 'do_not_allow' );
		}
	}

	return $caps;
}

/**
 * The roles meta a preview is showing for this user, right now.
 *
 * Asked fresh each time rather than remembered, so a preview started or ended
 * part-way through a request takes effect for every user object built after it.
 *
 * Our own read-only roles (support, guest viewer) are previewed by capability
 * alone, as before. Holding one of them for real switches on write blocking and
 * activity logging keyed on the role name, and that would also block the post
 * that ends the preview.
 *
 * @param int $user_id User ID.
 * @return array|null Meta being shown, or null when the stored roles show.
 */
function blueworx_view_as_shown_caps( $user_id ) {
	// Working the answer out reads roles of its own; anything in there that
	// built a user object would arrive straight back here.
	static $busy = false;

	if ( $busy || ! $user_id || (int) $user_id !== blueworx_view_as_swapping() ) {
		return null;
	}

	$role_slug = blueworx_view_as_current_role();

	if ( '' === $role_slug || ( function_exists( 'blueworx_readonly_role_caps' ) && null !== blueworx_readonly_role_caps( $role_slug ) ) ) {
		return null;
	}

	$busy = true;

	$role_caps = blueworx_view_as_role_caps( $role_slug );
	$shown     = null === $role_caps ? null : blueworx_view_as_swap_caps( $role_slug, $role_caps, blueworx_view_as_real_caps() );

	$busy = false;

	return $shown;
}

/**
 * Whether this user is being shown as a previewed role right now.
 *
 * For code that copies a user's role somewhere else — a marketing list, say —
 * and should not copy the pretend one.
 *
 * @param int $user_id User ID.
 * @return bool True mid-preview.
 */
function blueworx_view_as_is_previewing( $user_id ) {
	return null !== blueworx_view_as_shown_caps( (int) $user_id );
}

/**
 * Starts showing the previewed role once WordPress knows who is signed in.
 *
 * WordPress builds the signed-in user's object before it says who that is, so
 * the object it already handed over still holds the stored roles. Rebuilding it
 * here, now that the meta filter knows whose roles to swap, means the object
 * every later caller gets — wp_get_current_user() and all — names the previewed
 * role. Runs again whenever the signed-in user changes, and stands down for
 * anyone without a preview.
 *
 * @return void
 */
function blueworx_view_as_begin() {
	blueworx_view_as_swapping( 0 );

	$user = isset( $GLOBALS['current_user'] ) ? $GLOBALS['current_user'] : null;

	if ( ! $user instanceof WP_User || ! $user->exists() || '' === blueworx_view_as_current_role() ) {
		return;
	}

	blueworx_view_as_swapping( $user->ID );
	$user->for_site( $user->get_site_id() );
}

/**
 * Hands out the previewed role wherever the previewer's roles are read.
 *
 * Every user object — the signed-in one, get_userdata(), a fresh WP_User —
 * reads its roles from this one meta value, so answering here is what makes
 * the preview the same to every plugin however it asks. Only for the person
 * previewing, only for this site's key, and only in memory.
 *
 * @param mixed  $value   Null, or a value another filter already supplied.
 * @param int    $user_id User whose meta is being read.
 * @param string $key     Meta key.
 * @return mixed The swapped value wrapped as get_metadata() expects, or $value.
 */
function blueworx_view_as_filter_caps_meta( $value, $user_id, $key ) {
	if ( null !== $value || blueworx_view_as_raw() || blueworx_view_as_caps_key() !== $key ) {
		return $value;
	}

	$shown = blueworx_view_as_shown_caps( $user_id );

	return null === $shown ? $value : array( $shown );
}

/**
 * Stops the previewed role being saved over the real one.
 *
 * Covers the roles meta and the legacy user level WordPress recomputes beside
 * it. A roles write is rebased onto the stored roles by
 * blueworx_view_as_caps_write(); a user level write is refused, since the
 * level worked out mid-preview is the previewed role's.
 *
 * @param mixed  $check   Null, or another filter's short-circuit.
 * @param int    $user_id User whose meta is being written.
 * @param string $key     Meta key.
 * @param mixed  $value   Value being written.
 * @return mixed Null to let the write happen, or the result to report instead.
 */
function blueworx_view_as_guard_caps_write( $check, $user_id, $key, $value ) {
	global $wpdb;

	if ( null !== $check || blueworx_view_as_raw() ) {
		return $check;
	}

	$caps_key  = blueworx_view_as_caps_key();
	$level_key = $wpdb->get_blog_prefix() . 'user_level';

	if ( $caps_key !== $key && $level_key !== $key ) {
		return $check;
	}

	$shown = blueworx_view_as_shown_caps( $user_id );

	if ( null === $shown ) {
		return $check;
	}

	if ( $level_key === $key ) {
		return false;
	}

	$decision = blueworx_view_as_caps_write( blueworx_view_as_stored_caps( $user_id ), $shown, $value, blueworx_view_as_current_role() );

	if ( null === $decision || false === $decision ) {
		return null === $decision ? $check : false;
	}

	blueworx_view_as_raw( true );
	$done = update_metadata( 'user', $user_id, $caps_key, $decision );
	blueworx_view_as_raw( false );

	return $done;
}

/**
 * Keeps wp-admin open to a previewer whose real role may enter it.
 *
 * Site Protection can limit wp-admin by role. Previewing a role it keeps out
 * would lock the previewer out of the one place the preview can be ended, so
 * for the back end the real roles are asked as well. The front end is left to
 * the preview: being turned away there is exactly what that member would see,
 * and the way out is still in wp-admin.
 *
 * @param bool   $has_role Whether the roles shown pass.
 * @param string $area     'frontend' or 'backend'.
 * @return bool Whether to let them in.
 */
function blueworx_view_as_keep_backend_open( $has_role, $area ) {
	if ( $has_role || 'backend' !== $area || ! function_exists( 'blueworx_get_site_protection_allowed_roles' ) ) {
		return $has_role;
	}

	if ( ! blueworx_view_as_is_previewing( get_current_user_id() ) ) {
		return $has_role;
	}

	$real = blueworx_view_as_real_state();

	return (bool) array_intersect( blueworx_get_site_protection_allowed_roles( 'backend' ), $real['roles'] );
}

/**
 * Applies or clears the switch from a toolbar link.
 *
 * @return void
 */
function blueworx_view_as_handle() {
	blueworx_require_post_request();

	if ( ! blueworx_view_as_available() ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_view_as' );

	$role = isset( $_POST['blueworx_view_as_role'] ) ? sanitize_key( wp_unslash( $_POST['blueworx_view_as_role'] ) ) : '';

	if ( '' === $role || ! isset( blueworx_view_as_role_choices()[ $role ] ) ) {
		blueworx_view_as_store_role( get_current_user_id(), '' );

		// Leaving a preview gives every capability back, so the screen they were
		// on is theirs to return to.
		$referer = wp_get_referer();

		wp_safe_redirect( $referer ? $referer : admin_url() );
		exit;
	}

	blueworx_view_as_store_role( get_current_user_id(), $role );

	// Entering one goes to the dashboard rather than back where they were. The
	// screen they started from is very often one the previewed role cannot open,
	// and WordPress answers that with a bare permissions notice — no sidebar, and
	// so no way back out of the preview.
	wp_safe_redirect( admin_url() );
	exit;
}

/**
 * The role switcher for the sidebar.
 *
 * A single control that both says where you are and takes you somewhere else:
 * a button reading "My own view" or "Viewing as <Role>", opening a list of the
 * roles you may preview. It replaces two separate things — a pill in the top
 * bar that only appeared once you were already inside a role, and a bar pinned
 * across the bottom of the screen that was the only way in. One of them told
 * you nothing until it was too late to matter, and the other covered the save
 * bar of whatever screen it landed on.
 *
 * Every option is a submit button carrying its own role, so choosing one posts
 * without any script involved. The script only opens and closes the panel.
 *
 * @return string HTML, or an empty string when this user has no switch.
 */
function blueworx_view_as_sidebar_control() {
	if ( ! blueworx_view_as_available() ) {
		return '';
	}

	$choices = blueworx_view_as_role_choices();

	if ( empty( $choices ) ) {
		return '';
	}

	$current = blueworx_view_as_current_role();
	$active  = isset( $choices[ $current ] );

	$label = $active
		? sprintf(
			/* translators: %s: role name. */
			__( 'Viewing as %s', 'blueworx-labs-wordpress' ),
			$choices[ $current ]
		)
		: __( 'My own view', 'blueworx-labs-wordpress' );

	// "My own view" first, then the roles this user may drop down to. Leaving a
	// preview is choosing your own view again — there is no separate way out to
	// go looking for.
	$options = array( '' => __( 'My own view', 'blueworx-labs-wordpress' ) ) + $choices;
	$items   = '';

	foreach ( $options as $slug => $name ) {
		$selected = ( '' === $slug ) ? ! $active : ( $active && $slug === $current );

		$items .= sprintf(
			'<button type="submit" class="bw-viewas__option%1$s" name="blueworx_view_as_role" value="%2$s" role="menuitemradio" aria-checked="%3$s">%4$s</button>',
			$selected ? ' is-selected' : '',
			esc_attr( $slug ),
			$selected ? 'true' : 'false',
			esc_html( $name )
		);
	}

	// The panel sits after the button in the markup and above it on screen, so
	// tabbing out of the button lands in the list rather than past it.
	return sprintf(
		'<form class="bw-viewas" method="post" action="%1$s">%2$s'
			. '<input type="hidden" name="action" value="blueworx_view_as" />'
			. '<div class="bw-viewas__wrap">'
			. '<button type="button" class="bw-viewas__trigger%3$s" aria-haspopup="menu" aria-expanded="false" aria-controls="bw-viewas-menu" data-blueworx-viewas-trigger>'
			. '%4$s<span class="bw-viewas__label">%5$s</span>%6$s'
			. '</button>'
			. '<div class="bw-viewas__menu" id="bw-viewas-menu" role="menu" aria-label="%7$s" hidden>%8$s</div>'
			. '</div></form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		wp_nonce_field( 'blueworx_view_as', '_wpnonce', true, false ),
		$active ? ' is-active' : '',
		blueworx_ds_icon( 'eye', 18 ),
		esc_html( $label ),
		blueworx_ds_icon( 'chevron-down', 14, 'bw-viewas__chev' ),
		esc_attr__( 'View the admin as another role', 'blueworx-labs-wordpress' ),
		$items
	);
}

/**
 * Puts the switcher in the sidebar, above the Log Out row.
 *
 * Injected the same way that row is, and for the same reason: WordPress builds
 * the menu itself and offers nowhere to append to it. Printed only with the
 * admin theme on, because the block it belongs to — the dark column, the rule
 * above Log Out — is the theme's.
 *
 * @return void
 */
function blueworx_view_as_print_sidebar_control() {
	$markup = blueworx_view_as_sidebar_control();

	if ( '' === $markup ) {
		return;
	}
	?>
	<script>
		( function () {
			var menu = document.getElementById( 'adminmenu' );

			if ( ! menu || menu.querySelector( '.bw-viewas' ) ) {
				return;
			}

			var item = document.createElement( 'li' );
			item.className = 'bw-viewasrow';
			item.innerHTML = <?php echo wp_json_encode( $markup ); ?>;

			var logout = menu.querySelector( '.bw-logout' );

			if ( logout ) {
				menu.insertBefore( item, logout );
			} else {
				menu.appendChild( item );
			}
		}() );
	</script>
	<?php
}

/**
 * Catches up with a signed-in user settled before the theme loaded.
 *
 * A plugin that asks who is signed in early has WordPress settle it before
 * this file's hook on set_current_user exists, so that first object would keep
 * the stored roles all request.
 *
 * @return void
 */
function blueworx_view_as_catch_up() {
	if ( did_action( 'set_current_user' ) ) {
		blueworx_view_as_begin();
	}
}

if ( blueworx_feature_enabled( 'view_as_role' ) ) {
	add_filter( 'user_has_cap', 'blueworx_view_as_filter_caps', 10, 4 );
	add_filter( 'map_meta_cap', 'blueworx_view_as_super_admin_caps', 10, 3 );
	add_action( 'admin_post_blueworx_view_as', 'blueworx_view_as_handle' );

	// The role swap. First on set_current_user, so anything else listening
	// there already sees the previewed role.
	add_action( 'set_current_user', 'blueworx_view_as_begin', 0 );
	add_action( 'after_setup_theme', 'blueworx_view_as_catch_up', 0 );
	add_filter( 'get_user_metadata', 'blueworx_view_as_filter_caps_meta', 10, 3 );
	add_filter( 'update_user_metadata', 'blueworx_view_as_guard_caps_write', 10, 4 );
	add_filter( 'add_user_metadata', 'blueworx_view_as_guard_caps_write', 10, 4 );
	add_filter( 'blueworx_site_protection_role_check', 'blueworx_view_as_keep_backend_open', 20, 2 );

	if ( function_exists( 'blueworx_admin_theme_enabled' ) && blueworx_admin_theme_enabled() ) {
		// After the Log Out row, which is appended at the default priority: this
		// script looks that row up so it can insert itself above it.
		add_action( 'admin_footer', 'blueworx_view_as_print_sidebar_control', 11 );
	}
}
