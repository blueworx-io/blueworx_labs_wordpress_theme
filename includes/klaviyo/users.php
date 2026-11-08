<?php
/**
 * Klaviyo connection: the site's own users.
 *
 * Somebody who registers, signs in or has their role changed gets an
 * up-to-date profile in Klaviyo whether or not they ever buy anything: their
 * name, their role on the site, when they joined and when they were last
 * here. A new account is also an event, Created Account, so a welcome flow
 * can run for people who are not customers.
 *
 * Staff are left out: administrators and the BlueWorx support roles are the
 * people building the site, not an audience. Nobody is put on a list by
 * this; lists stay opt-in through forms and the checkout box.
 *
 * A site switching Klaviyo on with users already in place can send everyone
 * once from the Klaviyo screen. That runs through the queue in small batches,
 * so a site with thousands of users stays responsive.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many users one background job sends.
 */
const BLUEWORX_KLAVIYO_USERS_BATCH = 25;

/**
 * Roles whose people are never sent.
 *
 * @return string[]
 */
function blueworx_klaviyo_user_excluded_roles() {
	$roles = array( 'administrator' );

	if ( function_exists( 'blueworx_support_role_slug' ) ) {
		$roles[] = (string) blueworx_support_role_slug();
	}

	if ( function_exists( 'blueworx_external_role_slug' ) ) {
		$roles[] = (string) blueworx_external_role_slug();
	}

	/**
	 * Roles whose people are never sent to Klaviyo.
	 *
	 * Administrators and the BlueWorx support roles by default. Add a role to
	 * keep its people out, or remove one to send them.
	 *
	 * @param string[] $roles Role slugs.
	 */
	$roles = apply_filters( 'blueworx_klaviyo_user_excluded_roles', $roles );

	return array_values( array_unique( array_filter( array_map( 'strval', (array) $roles ) ) ) );
}

/**
 * The plan for one user, or nothing when they are staff or have no email.
 *
 * Pure apart from the roles filter.
 *
 * @param WP_User|object $user   The user: ID, user_email, first_name, last_name, roles, user_registered.
 * @param string         $reason register, login, profile, role or sync.
 * @param int|null       $now    The time, for tests.
 * @return array|null The plan.
 */
function blueworx_klaviyo_user_plan( $user, $reason, $now = null ) {
	if ( ! is_object( $user ) || empty( $user->user_email ) ) {
		return null;
	}

	$roles = isset( $user->roles ) ? array_values( array_map( 'strval', (array) $user->roles ) ) : array();

	if ( array_intersect( $roles, blueworx_klaviyo_user_excluded_roles() ) ) {
		return null;
	}

	$now        = null === $now ? time() : (int) $now;
	$registered = isset( $user->user_registered ) && $user->user_registered ? (int) strtotime( $user->user_registered . ' UTC' ) : 0;

	$plan = array(
		'label'   => __( 'Website account', 'blueworx-labs-wordpress' ),
		'email'   => (string) $user->user_email,
		'profile' => array(
			'first_name'      => isset( $user->first_name ) ? (string) $user->first_name : '',
			'last_name'       => isset( $user->last_name ) ? (string) $user->last_name : '',
			'external_id'     => isset( $user->ID ) ? (string) (int) $user->ID : '',
			'site_role'       => $roles ? $roles[0] : '',
			'account_created' => $registered > 0 ? wp_date( 'Y-m-d', $registered ) : '',
		),
	);

	if ( 'login' === $reason ) {
		$plan['profile']['last_login'] = wp_date( 'Y-m-d', $now );
	}

	if ( 'register' === $reason ) {
		$plan['label'] = 'Created Account';
		$plan['event'] = array(
			'name'       => 'Created Account',
			'unique_id'  => 'account-' . ( isset( $user->ID ) ? (int) $user->ID : 0 ),
			'time'       => $registered > 0 ? $registered : $now,
			'properties' => array(
				'site_role' => $roles ? $roles[0] : '',
			),
		);
	}

	return $plan;
}

/**
 * Sends one user's profile, after something about them changed.
 *
 * The job's key collapses a burst of changes within a minute onto one job.
 *
 * @param int    $user_id The user.
 * @param string $reason  register, login, profile or role.
 * @return bool Whether it was queued.
 */
function blueworx_klaviyo_user_changed( $user_id, $reason ) {
	if ( ! blueworx_klaviyo_enabled() ) {
		return false;
	}

	$user = get_userdata( (int) $user_id );

	if ( ! $user ) {
		return false;
	}

	// A new account's role is set as it is made, which would send it twice.
	// The register hook that follows a moment later covers it.
	if ( 'role' === $reason && ! empty( $user->user_registered ) && (int) strtotime( $user->user_registered . ' UTC' ) > time() - 10 ) {
		return false;
	}

	$plan = blueworx_klaviyo_user_plan( $user, $reason );

	if ( ! $plan ) {
		return false;
	}

	return blueworx_klaviyo_send( $plan, 'users', 'users:' . (int) $user_id . ':' . $reason . ':' . (int) floor( time() / MINUTE_IN_SECONDS ) );
}

/**
 * A new account.
 *
 * @param int $user_id The user.
 * @return void
 */
function blueworx_klaviyo_on_user_register( $user_id ) {
	blueworx_klaviyo_user_changed( $user_id, 'register' );
}
add_action( 'user_register', 'blueworx_klaviyo_on_user_register' );

/**
 * A sign-in, noted at most once every twelve hours per person.
 *
 * @param string  $user_login Login name.
 * @param WP_User $user       The user.
 * @return void
 */
function blueworx_klaviyo_on_user_login( $user_login, $user = null ) {
	if ( ! is_object( $user ) || empty( $user->ID ) || ! blueworx_klaviyo_enabled() ) {
		return;
	}

	if ( get_transient( 'blueworx_klaviyo_login_' . (int) $user->ID ) ) {
		return;
	}

	set_transient( 'blueworx_klaviyo_login_' . (int) $user->ID, 1, 12 * HOUR_IN_SECONDS );

	blueworx_klaviyo_user_changed( (int) $user->ID, 'login' );
}
add_action( 'wp_login', 'blueworx_klaviyo_on_user_login', 10, 2 );

/**
 * A changed profile.
 *
 * @param int $user_id The user.
 * @return void
 */
function blueworx_klaviyo_on_profile_update( $user_id ) {
	blueworx_klaviyo_user_changed( $user_id, 'profile' );
}
add_action( 'profile_update', 'blueworx_klaviyo_on_profile_update' );

/**
 * A changed role.
 *
 * @param int $user_id The user.
 * @return void
 */
function blueworx_klaviyo_on_role_change( $user_id ) {
	blueworx_klaviyo_user_changed( $user_id, 'role' );
}
add_action( 'set_user_role', 'blueworx_klaviyo_on_role_change' );
add_action( 'add_user_role', 'blueworx_klaviyo_on_role_change' );
add_action( 'remove_user_role', 'blueworx_klaviyo_on_role_change' );

// --- Sending everyone once ----------------------------------------------------

/**
 * Where the one-off send has got to.
 *
 * @return array started, total, done and finished, as timestamps and counts.
 */
function blueworx_klaviyo_users_sync_state() {
	$state = blueworx_klaviyo_setting( 'users_sync', array() );

	return is_array( $state ) ? $state : array();
}

/**
 * Starts sending every user, in batches through the queue.
 *
 * @return bool Whether it started.
 */
function blueworx_klaviyo_users_start_sync() {
	if ( ! blueworx_klaviyo_connected() ) {
		return false;
	}

	$counts = count_users();

	blueworx_klaviyo_update_setting(
		'users_sync',
		array(
			'started'  => time(),
			'total'    => isset( $counts['total_users'] ) ? (int) $counts['total_users'] : 0,
			'done'     => 0,
			'finished' => 0,
		)
	);

	return blueworx_klaviyo_users_queue_batch( 0 );
}

/**
 * Queues one batch of the one-off send.
 *
 * @param int $offset Where in the list of users to start.
 * @return bool
 */
function blueworx_klaviyo_users_queue_batch( $offset ) {
	return blueworx_klaviyo_queue_job(
		'users-batch:' . (int) $offset . ':' . (int) blueworx_klaviyo_users_sync_state()['started'],
		array(
			'label'    => __( 'Website users', 'blueworx-labs-wordpress' ),
			'source'   => 'users',
			'resolver' => 'users-batch',
			'data'     => array( 'offset' => (int) $offset ),
		)
	);
}

/**
 * Answers the queue's resolver for a batch: the plans for those users, and
 * the next batch queued if there is one.
 *
 * @param mixed  $plans    Nothing yet, or another resolver's answer.
 * @param string $resolver The job's resolver name.
 * @param array  $data     What the job was queued with.
 * @return mixed Plans, or whatever came in.
 */
function blueworx_klaviyo_users_resolve( $plans, $resolver, $data ) {
	if ( 'users-batch' !== $resolver ) {
		return $plans;
	}

	$offset = isset( $data['offset'] ) ? (int) $data['offset'] : 0;
	$users  = get_users(
		array(
			'number'  => BLUEWORX_KLAVIYO_USERS_BATCH,
			'offset'  => $offset,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'fields'  => 'all',
		)
	);

	$state         = blueworx_klaviyo_users_sync_state();
	$state['done'] = $offset + count( $users );

	if ( count( $users ) === BLUEWORX_KLAVIYO_USERS_BATCH ) {
		blueworx_klaviyo_users_queue_batch( $offset + BLUEWORX_KLAVIYO_USERS_BATCH );
	} else {
		$state['finished'] = time();
	}

	blueworx_klaviyo_update_setting( 'users_sync', $state );

	$plans = array();

	foreach ( $users as $user ) {
		$plan = blueworx_klaviyo_user_plan( $user, 'sync' );

		if ( $plan ) {
			$plans[] = $plan;
		}
	}

	return $plans;
}
add_filter( 'blueworx_klaviyo_resolve_job', 'blueworx_klaviyo_users_resolve', 10, 3 );

// --- The screen ---------------------------------------------------------------

/**
 * The users card on the Klaviyo screen.
 *
 * @return void
 */
function blueworx_klaviyo_users_card() {
	$state    = blueworx_klaviyo_users_sync_state();
	$excluded = blueworx_klaviyo_user_excluded_roles();
	$names    = array();

	foreach ( $excluded as $slug ) {
		$names[] = isset( wp_roles()->role_names[ $slug ] ) ? translate_user_role( wp_roles()->role_names[ $slug ] ) : $slug;
	}

	$body = blueworx_ds_field(
		array(
			'label' => __( 'Who is sent', 'blueworx-labs-wordpress' ),
			'help'  => sprintf(
				/* translators: %s: comma-separated role names. */
				__( 'Everyone with an account, as they register, sign in or change role: name, role, join date and last sign-in, plus a Created Account event for a new account. Left out: %s. Nobody is added to a list by this.', 'blueworx-labs-wordpress' ),
				implode( ', ', $names )
			),
		)
	);

	if ( ! empty( $state['started'] ) ) {
		if ( empty( $state['finished'] ) ) {
			$status = sprintf(
				/* translators: 1: number sent so far, 2: total, 3: date and time started. */
				__( 'Sending everyone: %1$s of %2$s so far, started %3$s.', 'blueworx-labs-wordpress' ),
				number_format_i18n( (int) $state['done'] ),
				number_format_i18n( (int) $state['total'] ),
				wp_date( 'j M H:i', (int) $state['started'] )
			);
		} else {
			$status = sprintf(
				/* translators: 1: number of users, 2: date and time finished. */
				__( 'Everyone was last sent on %2$s: %1$s accounts looked at, staff left out.', 'blueworx-labs-wordpress' ),
				number_format_i18n( (int) $state['done'] ),
				wp_date( 'j M Y H:i', (int) $state['finished'] )
			);
		}
	} else {
		$status = __( 'Never sent everyone. People are sent one at a time as things change; press the button to send every existing account once.', 'blueworx-labs-wordpress' );
	}

	$body .= blueworx_ds_field(
		array(
			'label'   => __( 'Send everyone once', 'blueworx-labs-wordpress' ),
			'help'    => $status,
			'control' => blueworx_ds_button(
				array(
					'label'    => __( 'Send every account to Klaviyo now', 'blueworx-labs-wordpress' ),
					'variant'  => 'secondary',
					'type'     => 'submit',
					'disabled' => ! blueworx_klaviyo_connected(),
					'attrs'    => array(
						'name'        => 'blueworx_klaviyo_sync_users',
						'value'       => '1',
						'data-testid' => 'blueworx-klaviyo-sync-users',
					),
				)
			),
		)
	);

	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helpers escape everything they emit.
		array(
			'title' => __( 'Website users', 'blueworx-labs-wordpress' ),
			'body'  => $body,
		)
	);
}
add_action( 'blueworx_klaviyo_screen_cards', 'blueworx_klaviyo_users_card', 8 );

/**
 * Starts the one-off send when its button saved the screen.
 *
 * @param string[] $refused Labels refused so far.
 * @return string[]
 */
function blueworx_klaviyo_users_save( $refused ) {
	// The caller has verified the nonce and the capability.
	if ( ! empty( $_POST['blueworx_klaviyo_sync_users'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		blueworx_klaviyo_users_start_sync();
	}

	return (array) $refused;
}
add_filter( 'blueworx_klaviyo_save_settings', 'blueworx_klaviyo_users_save' );

/**
 * Says what user sync sends, on the Klaviyo screen.
 *
 * @param array $sources Entries with 'label' and 'text'.
 * @return array
 */
function blueworx_klaviyo_users_source( $sources ) {
	$sources[] = array(
		'label' => __( 'Website users', 'blueworx-labs-wordpress' ),
		'text'  => __( 'Every account as it registers, signs in or changes role: name, site role, join date and last sign-in, and Created Account for a new one. Administrators and support roles are never sent, and nobody is added to a list.', 'blueworx-labs-wordpress' ),
	);

	return $sources;
}
add_filter( 'blueworx_klaviyo_sources', 'blueworx_klaviyo_users_source', 9 );
