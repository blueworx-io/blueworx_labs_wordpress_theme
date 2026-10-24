<?php
/**
 * Setting a site up and cleaning it up, now that there are no plugin hooks.
 *
 * A theme gets no activation or deactivation hook, so setup runs on each
 * site's first load and again after an update, and the clean-up runs when the
 * site switches to another theme. Both are text in, text out against a few
 * stand-ins, so they run here. tests/theme-lifecycle.spec.js covers the same on
 * a running site.
 *
 * Run with: php tests/php/theme-lifecycle-test.php
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
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed

define( 'BLUEWORX_LABS_VERSION', '2.0.0' );

$GLOBALS['hooks']        = array();
$GLOBALS['super_admins'] = array();

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ] = array(
		'callback' => $callback,
		'priority' => $priority,
	);
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	add_action( $hook, $callback, $priority, $args );
}

function delete_option( $key ) {
	unset( $GLOBALS['options'][ $key ] );
	return true;
}

function is_super_admin( $user_id = 0 ) {
	return in_array( $user_id, $GLOBALS['super_admins'], true );
}

/**
 * A role, holding only what the code under test reads and writes.
 */
class WP_Role {
	/**
	 * Capabilities.
	 *
	 * @var array
	 */
	public $capabilities;

	/**
	 * Constructor.
	 *
	 * @param array $capabilities Capability => granted.
	 */
	public function __construct( $capabilities ) {
		$this->capabilities = $capabilities;
	}

	public function has_cap( $cap ) {
		return ! empty( $this->capabilities[ $cap ] );
	}

	public function remove_cap( $cap ) {
		unset( $this->capabilities[ $cap ] );
	}
}

/**
 * A theme, by its folder and its parent's.
 */
class WP_Theme {
	/**
	 * Parent theme folder, or this theme's own when it has no parent.
	 *
	 * @var string
	 */
	private $template;

	/**
	 * Constructor.
	 *
	 * @param string $template Parent theme folder.
	 */
	public function __construct( $template ) {
		$this->template = $template;
	}

	public function get_template() {
		return $this->template;
	}
}

/**
 * A user, by ID and roles.
 */
class WP_User {
	/**
	 * User ID.
	 *
	 * @var int
	 */
	public $ID;

	/**
	 * Roles.
	 *
	 * @var array
	 */
	public $roles;

	/**
	 * Constructor.
	 *
	 * @param int   $id    User ID.
	 * @param array $roles Roles.
	 */
	public function __construct( $id, $roles ) {
		$this->ID    = $id;
		$this->roles = $roles;
	}
}

function wp_roles() {
	return (object) array( 'role_objects' => $GLOBALS['roles'] );
}

/**
 * What setup and clean-up call, counted rather than run: their own files and
 * tests cover what they do.
 */
function blueworx_external_on_activate() {
	++$GLOBALS['calls']['activate'];
}

function blueworx_support_on_deactivate() {
	++$GLOBALS['calls']['support_off'];
}

function blueworx_external_on_deactivate() {
	++$GLOBALS['calls']['external_off'];
}

/**
 * Puts the counters and roles back to a fresh site.
 *
 * @return void
 */
function fresh_site() {
	$GLOBALS['options'] = array();
	$GLOBALS['calls']   = array(
		'activate'     => 0,
		'support_off'  => 0,
		'external_off' => 0,
	);
	$GLOBALS['roles']   = array(
		'administrator' => new WP_Role(
			array(
				'switch_themes'  => true,
				'manage_options' => true,
			)
		),
		'editor'        => new WP_Role( array( 'edit_posts' => true ) ),
		'owner'         => new WP_Role(
			array(
				'switch_themes'      => true,
				'edit_theme_options' => true,
			)
		),
	);
}

require __DIR__ . '/../../includes/theme-lifecycle.php';

fresh_site();

echo "Setup runs once per site, per version\n";

check(
	'it is hooked early in after_setup_theme',
	$GLOBALS['hooks']['after_setup_theme'],
	array(
		'callback' => 'blueworx_theme_maybe_setup_site',
		'priority' => 6,
	)
);

blueworx_theme_maybe_setup_site();
check( 'a fresh site is set up on its first load', $GLOBALS['calls']['activate'], 1 );
check( 'and remembers the version it was set up for', get_option( 'blueworx_theme_setup_version' ), '2.0.0' );

blueworx_theme_maybe_setup_site();
check( 'the next load does not set it up again', $GLOBALS['calls']['activate'], 1 );

update_option( 'blueworx_theme_setup_version', '1.99.0' );
blueworx_theme_maybe_setup_site();
check( 'an update sets it up again', $GLOBALS['calls']['activate'], 2 );
check( 'at the new version', get_option( 'blueworx_theme_setup_version' ), '2.0.0' );

echo "\nOnly administrators can change theme\n";

fresh_site();
blueworx_theme_maybe_setup_site();

$holders = array();
foreach ( $GLOBALS['roles'] as $name => $role ) {
	if ( $role->has_cap( 'switch_themes' ) ) {
		$holders[] = $name;
	}
}

check( 'after setup only the administrator role holds switch_themes', $holders, array( 'administrator' ) );
check( 'and the owner keeps the rest of its capabilities', $GLOBALS['roles']['owner']->has_cap( 'edit_theme_options' ), true );

$asked     = array( 'switch_themes' );
$granted   = array( 'switch_themes' => true );
$owner     = new WP_User( 7, array( 'owner' ) );
$admin     = new WP_User( 1, array( 'administrator' ) );
$super     = new WP_User( 9, array() );
$other_cap = array( 'edit_posts' => true );

$GLOBALS['super_admins'] = array( 9 );

$owner_caps = blueworx_theme_deny_switch_themes( $granted, $asked, array(), $owner );
check( 'a role added after setup is still refused', $owner_caps['switch_themes'], false );
check( 'an administrator is not', blueworx_theme_deny_switch_themes( $granted, $asked, array(), $admin ), $granted );
check( 'nor a super admin with no role on the site', blueworx_theme_deny_switch_themes( $granted, $asked, array(), $super ), $granted );
check( 'other capability checks are left alone', blueworx_theme_deny_switch_themes( $other_cap, array( 'edit_posts' ), array(), $owner ), $other_cap );

echo "\nSwitching away cleans up, once\n";

fresh_site();
blueworx_theme_maybe_setup_site();

blueworx_theme_on_switch_away( 'Twenty Twenty-Five', new WP_Theme( 'twentytwentyfive' ), new WP_Theme( 'blueworx' ) );
check( 'switching away from this theme removes the support account', $GLOBALS['calls']['support_off'], 1 );
check( 'and the guest viewers', $GLOBALS['calls']['external_off'], 1 );
check( 'and forgets the setup, so switching back runs it again', get_option( 'blueworx_theme_setup_version' ), false );

blueworx_theme_on_switch_away( 'Twenty Twenty-Four', new WP_Theme( 'twentytwentyfour' ), new WP_Theme( 'twentytwentyfive' ) );
check( 'switching between two other themes does nothing', $GLOBALS['calls']['support_off'], 1 );

blueworx_theme_on_switch_away( 'BlueWorx child', new WP_Theme( 'blueworx' ), new WP_Theme( 'blueworx' ) );
check( 'nor does moving to a child theme of this one', $GLOBALS['calls']['support_off'], 1 );

blueworx_theme_on_switch_away( 'Twenty Twenty-Five', new WP_Theme( 'twentytwentyfive' ), null );
check( 'nor a switch with no old theme to go on', $GLOBALS['calls']['support_off'], 1 );

finish();
