<?php
/**
 * Klaviyo users: what a user's profile carries, who is left out, and how the
 * one-off send works through the queue.
 *
 * Run with: php tests/php/klaviyo-users-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';

$GLOBALS['enabled'] = true;
$GLOBALS['queued']  = array();
$GLOBALS['users']   = array();

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	return true;
}

function blueworx_feature_enabled( $key ) {
	return 'klaviyo' === $key && $GLOBALS['enabled'];
}

function sanitize_email( $email ) {
	return filter_var( (string) $email, FILTER_SANITIZE_EMAIL );
}

function wp_date( $format, $time = null ) {
	return gmdate( $format, null === $time ? 1762300800 : $time );
}

function wp_rand() {
	return 4;
}

function add_option( $key, $value, $deprecated = '', $autoload = 'yes' ) {
	if ( array_key_exists( $key, $GLOBALS['options'] ) ) {
		return false;
	}
	$GLOBALS['options'][ $key ] = $value;
	$GLOBALS['queued'][]        = $value;
	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['options'][ $key ] );
	return true;
}

function wp_schedule_single_event( $time, $hook, $args = array() ) {
	return true;
}

function get_userdata( $id ) {
	return isset( $GLOBALS['users'][ $id ] ) ? $GLOBALS['users'][ $id ] : false;
}

function count_users() {
	return array( 'total_users' => count( $GLOBALS['users'] ) );
}

function get_users( $args ) {
	return array_slice( array_values( $GLOBALS['users'] ), (int) $args['offset'], (int) $args['number'] );
}

function blueworx_support_role_slug() {
	return 'blueworx_support';
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/klaviyo/settings.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/client.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/queue.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/api.php';
require dirname( __DIR__, 2 ) . '/includes/klaviyo/users.php';

/**
 * A user the way WordPress hands one over.
 *
 * @param array $overrides Fields to change.
 * @return object
 */
function user_fixture( $overrides = array() ) {
	return (object) array_merge(
		array(
			'ID'              => 7,
			'user_email'      => 'Pat@Example.com',
			'first_name'      => 'Pat',
			'last_name'       => 'Member',
			'roles'           => array( 'subscriber' ),
			'user_registered' => '2026-11-01 09:30:00',
		),
		$overrides
	);
}

// --- Who is sent ----------------------------------------------------------------

check( 'administrators are left out', blueworx_klaviyo_user_excluded_roles(), array( 'administrator', 'blueworx_support' ) );

$GLOBALS['filters']['blueworx_klaviyo_user_excluded_roles'] = static function ( $roles ) {
	$roles[] = 'shop_manager';
	return $roles;
};
check( 'a site can leave another role out', in_array( 'shop_manager', blueworx_klaviyo_user_excluded_roles(), true ), true );
check( 'and that role sends nothing', blueworx_klaviyo_user_plan( user_fixture( array( 'roles' => array( 'shop_manager' ) ) ), 'profile' ), null );
unset( $GLOBALS['filters']['blueworx_klaviyo_user_excluded_roles'] );

check( 'an administrator sends nothing', blueworx_klaviyo_user_plan( user_fixture( array( 'roles' => array( 'administrator' ) ) ), 'profile' ), null );
check( 'even with another role too', blueworx_klaviyo_user_plan( user_fixture( array( 'roles' => array( 'subscriber', 'administrator' ) ) ), 'profile' ), null );
check( 'a support agent sends nothing', blueworx_klaviyo_user_plan( user_fixture( array( 'roles' => array( 'blueworx_support' ) ) ), 'profile' ), null );
check( 'no email, nothing to send', blueworx_klaviyo_user_plan( user_fixture( array( 'user_email' => '' ) ), 'profile' ), null );

// --- What the profile carries -----------------------------------------------------

$plan = blueworx_klaviyo_user_plan( user_fixture(), 'profile', 1762300800 );

check( 'the name', $plan['profile']['first_name'], 'Pat' );
check( 'the user ID as the external ID', $plan['profile']['external_id'], '7' );
check( 'the role on the site', $plan['profile']['site_role'], 'subscriber' );
check( 'the join date', $plan['profile']['account_created'], '2026-11-01' );
check( 'no last sign-in unless they signed in', isset( $plan['profile']['last_login'] ), false );
check( 'no event for a change', isset( $plan['event'] ), false );
check( 'never a list', isset( $plan['list'] ), false );

$plan = blueworx_klaviyo_user_plan( user_fixture(), 'login', 1762300800 );
check( 'a sign-in sets the last sign-in date', $plan['profile']['last_login'], '2025-11-05' );

$plan = blueworx_klaviyo_user_plan( user_fixture(), 'register', 1762300800 );
check( 'a new account is an event', $plan['event']['name'], 'Created Account' );
check( 'recorded once per account', $plan['event']['unique_id'], 'account-7' );
check( 'at the time the account was made', $plan['event']['time'], strtotime( '2026-11-01 09:30:00 UTC' ) );

$normalized = blueworx_klaviyo_normalize_plan( $plan );
check( 'the plan passes the connection\'s checks', $normalized['email'], 'pat@example.com' );
check( 'with no list on it', $normalized['list'], '' );

// --- A change on the site -----------------------------------------------------------

$GLOBALS['options']['blueworx_klaviyo_private_key'] = 'pk_fixture0123456789abcdefghij';
$GLOBALS['users'][7]                                = user_fixture();
$GLOBALS['users'][8]                                = user_fixture(
	array(
		'ID'         => 8,
		'user_email' => 'admin@example.com',
		'roles'      => array( 'administrator' ),
	)
);

check( 'a changed profile is queued', blueworx_klaviyo_user_changed( 7, 'profile' ), true );
check( 'with the users source', end( $GLOBALS['queued'] )['source'], 'users' );
check( 'an administrator\'s change is not', blueworx_klaviyo_user_changed( 8, 'profile' ), false );
check( 'an unknown user is not', blueworx_klaviyo_user_changed( 99, 'profile' ), false );

$GLOBALS['users'][9] = user_fixture(
	array(
		'ID'              => 9,
		'user_email'      => 'new@example.com',
		'user_registered' => gmdate( 'Y-m-d H:i:s' ),
	)
);
check( 'a role set as an account is made waits for the register hook', blueworx_klaviyo_user_changed( 9, 'role' ), false );
check( 'which sends it', blueworx_klaviyo_user_changed( 9, 'register' ), true );

// --- Sending everyone once -----------------------------------------------------------

$GLOBALS['queued'] = array();
check( 'the one-off send starts with a batch job', blueworx_klaviyo_users_start_sync(), true );
check( 'that the resolver answers', end( $GLOBALS['queued'] )['resolver'], 'users-batch' );
check( 'knowing how many there are', blueworx_klaviyo_users_sync_state()['total'], 3 );

$plans = blueworx_klaviyo_users_resolve( null, 'users-batch', array( 'offset' => 0 ) );
check( 'the batch sends everyone but staff', count( $plans ), 2 );
check( 'as plain profile updates', isset( $plans[0]['event'] ), false );
check( 'and knows it has finished', blueworx_klaviyo_users_sync_state()['finished'] > 0, true );
check( 'having looked at everyone', blueworx_klaviyo_users_sync_state()['done'], 3 );
check( 'other resolvers are left alone', blueworx_klaviyo_users_resolve( null, 'other', array() ), null );

for ( $i = 10; $i < 10 + BLUEWORX_KLAVIYO_USERS_BATCH; $i++ ) {
	$GLOBALS['users'][ $i ] = user_fixture(
		array(
			'ID'         => $i,
			'user_email' => 'u' . $i . '@example.com',
		)
	);
}

$GLOBALS['queued'] = array();
blueworx_klaviyo_users_start_sync();
$plans = blueworx_klaviyo_users_resolve( null, 'users-batch', array( 'offset' => 0 ) );
check( 'a full batch is one batch of people', count( $plans ) <= BLUEWORX_KLAVIYO_USERS_BATCH, true );
check( 'and queues the next batch', end( $GLOBALS['queued'] )['data']['offset'], BLUEWORX_KLAVIYO_USERS_BATCH );
check( 'without having finished', blueworx_klaviyo_users_sync_state()['finished'], 0 );

finish();
