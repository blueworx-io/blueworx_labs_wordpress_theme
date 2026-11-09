<?php
/**
 * Previewing a role changes the role name other plugins see, and never the
 * account.
 *
 * The decisions are pure — stored roles, the previewed role and a write in;
 * what to show and what to store out — so they are checked here. That a real
 * page hands the previewed role to plugins, and that saving your profile mid-
 * preview leaves you an administrator, is tests/view-as-role.spec.js.
 *
 * Run with: php tests/php/view-as-role-swap-test.php
 *
 * @package BlueWorxLabs
 */

// The shared WordPress stand-ins. Kept apart from the docblock above, which
// phpcs otherwise reads as this statement's rather than the file's.
require __DIR__ . '/stubs.php';

// This script stands in for WordPress rather than being loaded into it, so its
// stubs have to carry core's names and its state has to be global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Same: the text domain is part of the signature.

/** Every role on this pretend site, in the shape wp_roles() hands them over. */
$GLOBALS['roles'] = array(
	'administrator' => array(
		'name'         => 'Administrator',
		'capabilities' => array(
			'read'           => true,
			'edit_posts'     => true,
			'manage_options' => true,
			'list_users'     => true,
		),
	),
	'editor'        => array(
		'name'         => 'Editor',
		'capabilities' => array(
			'read'       => true,
			'edit_posts' => true,
		),
	),
	'customer'      => array(
		'name'         => 'Customer',
		'capabilities' => array( 'read' => true ),
	),
	'subscriber'    => array(
		'name'         => 'Subscriber',
		'capabilities' => array( 'read' => true ),
	),
	'kiosk'         => array(
		'name'         => 'Kiosk',
		'capabilities' => array(),
	),
	'shop_manager'  => array(
		'name'         => 'Shop manager',
		'capabilities' => array(
			'read'         => true,
			'manage_store' => true,
		),
	),
);

/** Role maps, as blueworx_view_as_build_caps() takes them. */
$GLOBALS['maps'] = array_map(
	static function ( $role ) {
		return $role['capabilities'];
	},
	$GLOBALS['roles']
);

// User meta, per user and per key, the way the usermeta table holds it.
$GLOBALS['usermeta'] = array(
	1 => array( 'wp_capabilities' => array( 'administrator' => true ) ),
	2 => array( 'wp_capabilities' => array( 'editor' => true ) ),
);

function get_current_user_id() {
	return isset( $GLOBALS['user_id'] ) ? $GLOBALS['user_id'] : 1;
}

function wp_get_current_user() {
	// Built from the stored meta through the same filter WordPress runs, so it
	// shows whatever the preview is showing — the object every plugin gets.
	$id     = get_current_user_id();
	$stored = blueworx_view_as_filter_caps_meta( null, $id, 'wp_capabilities' );
	$stored = null === $stored ? get_user_meta( $id, 'wp_capabilities', true ) : $stored[0];
	$built  = blueworx_view_as_build_caps( (array) $stored, $GLOBALS['maps'] );

	return new class( $id, $built ) {
		/**
		 * User ID.
		 *
		 * @var int
		 */
		public $ID;

		/**
		 * Roles held.
		 *
		 * @var array
		 */
		public $roles;

		/**
		 * Capabilities held.
		 *
		 * @var array
		 */
		public $allcaps;

		public function __construct( $id, $built ) {
			$this->ID      = $id;
			$this->roles   = $built['roles'];
			$this->allcaps = $built['allcaps'];
		}

		public function exists() {
			return true;
		}
	};
}

function wp_roles() {
	return new class( $GLOBALS['roles'] ) {
		/**
		 * Every role registered on the site.
		 *
		 * @var array
		 */
		public $roles;

		public function __construct( $roles ) {
			$this->roles = $roles;
		}
	};
}

function get_role( $slug ) {
	return isset( $GLOBALS['roles'][ $slug ] ) ? (object) $GLOBALS['roles'][ $slug ] : null;
}

function translate_user_role( $name ) {
	return $name;
}

function blueworx_feature_enabled( $key ) {
	return true;
}

function blueworx_admin_theme_enabled() {
	return true;
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function esc_html__( $text, $domain = '' ) {
	return $text;
}

function esc_attr__( $text, $domain = '' ) {
	return $text;
}

function get_user_meta( $user_id, $key, $single = false ) {
	return isset( $GLOBALS['usermeta'][ $user_id ][ $key ] ) ? $GLOBALS['usermeta'][ $user_id ][ $key ] : '';
}

function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['usermeta'][ $user_id ][ $key ] = $value;

	return true;
}

function update_metadata( $type, $user_id, $key, $value ) {
	return update_user_meta( $user_id, $key, $value );
}

function delete_user_meta( $user_id, $key ) {
	unset( $GLOBALS['usermeta'][ $user_id ][ $key ] );

	return true;
}

function update_user_option( $user_id, $option, $value ) {
	return update_user_meta( $user_id, 'wp_' . $option, $value );
}

function delete_user_option( $user_id, $option ) {
	return delete_user_meta( $user_id, 'wp_' . $option );
}

$GLOBALS['wpdb'] = new class() { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Stands in for core's handle; WordPress is never loaded here.
	public function get_blog_prefix() {
		return 'wp_';
	}
};

function is_multisite() {
	return false;
}

function is_super_admin( $user_id = false ) {
	return false;
}

function is_network_admin() {
	return false;
}

function blueworx_get_site_protection_allowed_roles( $area ) {
	return array( 'administrator', 'editor' );
}

// The theme's read-only roles (support, guest viewer). None until a check says so.
$GLOBALS['readonly_roles'] = array();

function blueworx_readonly_role_caps( $role ) {
	return in_array( $role, $GLOBALS['readonly_roles'], true ) ? array( 'read' => true ) : null;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = $callback;
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = $callback;
}

// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require __DIR__ . '/../../includes/view-as-role.php';

$admin = $GLOBALS['roles']['administrator']['capabilities'] + array( 'administrator' => true );

echo "Previewing a role shows that role, and only that role\n";

$shown = blueworx_view_as_swap_caps( 'subscriber', $GLOBALS['roles']['subscriber']['capabilities'], $admin );
$built = blueworx_view_as_build_caps( $shown, $GLOBALS['maps'] );

check( 'the one role held is the previewed one', $built['roles'], array( 'subscriber' ) );
check( 'its name reads as held, as current_user_can( \'subscriber\' ) asks', ! empty( $built['allcaps']['subscriber'] ), true );
check( 'the real role\'s name does not', empty( $built['allcaps']['administrator'] ), true );
check( 'and neither do the real role\'s capabilities', empty( $built['allcaps']['manage_options'] ), true );
check( 'while the previewed role\'s own still do', ! empty( $built['allcaps']['read'] ), true );

echo "\nA preview can only ever show less\n";

check(
	'an editor is not renamed into a role holding something they lack',
	blueworx_view_as_swap_caps( 'shop_manager', $GLOBALS['roles']['shop_manager']['capabilities'], $GLOBALS['roles']['editor']['capabilities'] ),
	null
);
check(
	'but an administrator holding all of it is',
	blueworx_view_as_swap_caps( 'customer', $GLOBALS['roles']['customer']['capabilities'], $admin ),
	array( 'customer' => true )
);
check( 'and no role at all swaps nothing', blueworx_view_as_swap_caps( '', array(), $admin ), null );

// A role without `read` would leave wp-admin closed, and the way out of the
// preview lives there. It is carried over from the real person, never added.
check(
	'a role without read keeps the real person\'s read',
	blueworx_view_as_swap_caps( 'kiosk', array(), $admin ),
	array(
		'kiosk' => true,
		'read'  => true,
	)
);
check( 'and is not handed read by somebody who never had it', blueworx_view_as_swap_caps( 'kiosk', array(), array( 'edit_posts' => true ) ), array( 'kiosk' => true ) );

echo "\nA write built from the preview never lands on the account\n";

$stored = array( 'administrator' => true );
$view   = array( 'subscriber' => true );

check( 'saving the role the preview shows is refused', blueworx_view_as_caps_write( $stored, $view, array( 'subscriber' => true ), 'subscriber' ), false );
check( 'and so is removing it, which would leave no role at all', blueworx_view_as_caps_write( $stored, $view, array(), 'subscriber' ), false );
check(
	'and so is replacing it with read alone',
	blueworx_view_as_caps_write(
		$stored,
		array(
			'kiosk' => true,
			'read'  => true,
		),
		array( 'read' => true ),
		'kiosk'
	),
	false
);

check(
	'a role added on top goes onto the real roles, not in place of them',
	blueworx_view_as_caps_write( $stored, $view, array( 'subscriber' => true, 'customer' => true ), 'subscriber' ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	array(
		'administrator' => true,
		'customer'      => true,
	)
);
check(
	'and set_role() to another role keeps the real one too',
	blueworx_view_as_caps_write( $stored, $view, array( 'editor' => true ), 'subscriber' ),
	array(
		'administrator' => true,
		'editor'        => true,
	)
);
check( 'something that is not a roles map is not this guard\'s business', blueworx_view_as_caps_write( $stored, $view, '', 'subscriber' ), null );

echo "\nOn a running site\n";

// The hooks that put all of the above in front of WordPress.
foreach ( array( 'set_current_user', 'get_user_metadata', 'update_user_metadata', 'add_user_metadata' ) as $hook ) {
	check( "it listens on {$hook}", ! empty( $GLOBALS['hooks'][ $hook ] ), true );
}

// Nobody is previewing yet, so the stored role is what everyone sees.
check( 'with no preview, the real role shows', wp_get_current_user()->roles, array( 'administrator' ) );

blueworx_view_as_store_role( 1, 'subscriber' );
blueworx_view_as_swapping( 1 );

check( 'previewing a subscriber, the signed-in user is a subscriber', wp_get_current_user()->roles, array( 'subscriber' ) );
check( 'while the account still says administrator', get_user_meta( 1, 'wp_capabilities', true ), array( 'administrator' => true ) );
check( 'and the control still knows who they really are', blueworx_view_as_available(), true );
check( 'offering the way back up', isset( blueworx_view_as_role_choices()['editor'] ), true );
check( 'and treating the previewed role as somewhere to go, not their own level', isset( blueworx_view_as_role_choices()['subscriber'] ), true );

$caps = blueworx_view_as_filter_caps( wp_get_current_user()->allcaps, array(), array(), wp_get_current_user() );

check( 'the capability filter keeps the previewed role\'s name', ! empty( $caps['subscriber'] ), true );
check( 'and nothing the subscriber lacks', empty( $caps['manage_options'] ), true );

// Somebody else's roles are their own, preview or not.
check( 'another user\'s roles are left alone', blueworx_view_as_filter_caps_meta( null, 2, 'wp_capabilities' ), null );
check( 'and so is any other meta key of the previewer\'s', blueworx_view_as_filter_caps_meta( null, 1, 'wp_user_level' ), null );

check( 'saving the swapped roles back is refused', blueworx_view_as_guard_caps_write( null, 1, 'wp_capabilities', array( 'subscriber' => true ) ), false );
check( 'so the account is still an administrator', get_user_meta( 1, 'wp_capabilities', true ), array( 'administrator' => true ) );
check( 'and the user level worked out from the preview is not saved', blueworx_view_as_guard_caps_write( null, 1, 'wp_user_level', 0 ), false );
check( 'another user\'s roles can still be written', blueworx_view_as_guard_caps_write( null, 2, 'wp_capabilities', array( 'subscriber' => true ) ), null );
check( 'and so can the previewer\'s other meta', blueworx_view_as_guard_caps_write( null, 1, 'nickname', 'Sam' ), null );

blueworx_view_as_guard_caps_write( null, 1, 'wp_capabilities', array( 'subscriber' => true, 'customer' => true ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

check(
	'a role a plugin adds mid-preview lands on top of the real one',
	get_user_meta( 1, 'wp_capabilities', true ),
	array(
		'administrator' => true,
		'customer'      => true,
	)
);

$GLOBALS['usermeta'][1]['wp_capabilities'] = array( 'administrator' => true );

echo "\nThe way out is never locked\n";

// Site Protection may keep subscribers out of wp-admin. The previewer is let in
// on their real role, because the control that ends the preview is in there.
check( 'wp-admin stays open to the real role', blueworx_view_as_keep_backend_open( false, 'backend' ), true );
check( 'while the front end is left to the preview', blueworx_view_as_keep_backend_open( false, 'frontend' ), false );

echo "\nAnd ending the preview is all it takes to be yourself again\n";

blueworx_view_as_store_role( 1, '' );

check( 'the real role shows straight away', wp_get_current_user()->roles, array( 'administrator' ) );
check( 'nothing is being swapped', blueworx_view_as_is_previewing( 1 ), false );
check( 'and writes go through untouched', blueworx_view_as_guard_caps_write( null, 1, 'wp_capabilities', array( 'administrator' => true ) ), null );

// A closed tab or a crashed request never reaches "end the preview" at all.
// Nothing was written while it ran, so the account was never anything but an
// administrator: there is nothing to restore.
check( 'and the account was never changed', get_user_meta( 1, 'wp_capabilities', true ), array( 'administrator' => true ) );

echo "\nThe theme's own read-only roles are previewed by capability alone\n";

// Holding one for real switches on write blocking keyed on the role name, which
// would also block the post that ends the preview.
$GLOBALS['readonly_roles'] = array( 'subscriber' );

blueworx_view_as_store_role( 1, 'subscriber' );

check( 'so the stored role keeps showing', wp_get_current_user()->roles, array( 'administrator' ) );

blueworx_view_as_store_role( 1, '' );

finish();
