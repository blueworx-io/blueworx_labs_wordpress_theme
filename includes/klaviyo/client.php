<?php
/**
 * Klaviyo connection: talking to Klaviyo.
 *
 * Nothing in this file runs while a visitor is waiting. Only the queue calls
 * it, in the background, and the settings screen's Test connection button.
 *
 * A "plan" is one person's worth of work, and is what everything else in the
 * connection produces and this file carries out:
 *
 *     label    What the log calls it.
 *     email    Who it is about. Klaviyo matches profiles on this.
 *     profile  Profile properties written every time.
 *     fill     Profile fields and properties written only when the profile has
 *              none yet, such as a name a form should not be able to change.
 *     guard    Properties written only when the profile has no value for them.
 *     unset    Properties to remove.
 *     event    The event, if there is one: name, unique_id, properties, and
 *              optionally value, currency and time.
 *     list     A list ID to subscribe them to, if any.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where Klaviyo's API lives.
 */
const BLUEWORX_KLAVIYO_API = 'https://a.klaviyo.com/api/';

/**
 * The version of Klaviyo's API this was written and checked against.
 *
 * A fixed date rather than "latest": Klaviyo keeps each dated version working
 * for two years, so a site keeps working without anyone changing anything.
 */
const BLUEWORX_KLAVIYO_REVISION = '2026-10-15';

/**
 * The profile fields Klaviyo holds outside "properties".
 *
 * @return string[]
 */
function blueworx_klaviyo_profile_fields() {
	return array( 'first_name', 'last_name', 'phone_number', 'external_id', 'organization', 'title', 'image', 'locale' );
}

/**
 * Makes one request to Klaviyo.
 *
 * @param string     $method GET or POST.
 * @param string     $path   Path after /api/, with any query string.
 * @param array|null $body   JSON body.
 * @return array {
 *     @type int    $status HTTP status, or 0 when it never got there.
 *     @type array  $body   Decoded response.
 *     @type string $error  Short description when it failed.
 * }
 */
function blueworx_klaviyo_request( $method, $path, $body = null ) {
	$args = array(
		'method'  => $method,
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Klaviyo-API-Key ' . blueworx_klaviyo_private_key(),
			'revision'      => BLUEWORX_KLAVIYO_REVISION,
			'Accept'        => 'application/vnd.api+json',
		),
	);

	if ( null !== $body ) {
		$args['headers']['Content-Type'] = 'application/vnd.api+json';
		$args['body']                    = wp_json_encode( $body );
	}

	$response = wp_remote_request( BLUEWORX_KLAVIYO_API . $path, $args );

	if ( is_wp_error( $response ) ) {
		return array(
			'status' => 0,
			'body'   => array(),
			'error'  => $response->get_error_message(),
		);
	}

	$status  = (int) wp_remote_retrieve_response_code( $response );
	$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	$decoded = is_array( $decoded ) ? $decoded : array();
	$error   = '';

	if ( $status < 200 || $status >= 300 ) {
		$detail = isset( $decoded['errors'][0]['detail'] ) ? (string) $decoded['errors'][0]['detail'] : '';
		$error  = 'Klaviyo said ' . $status . ( '' !== $detail ? ': ' . $detail : '' );
	}

	return array(
		'status' => $status,
		'body'   => $decoded,
		'error'  => $error,
	);
}

/**
 * Whether a failed request is worth trying again.
 *
 * Klaviyo being down or busy clears up on its own, and so does a key somebody
 * fixes. A request Klaviyo refuses as malformed fails the same way every time.
 *
 * @param int $status HTTP status, or 0 when it never got there.
 * @return bool
 */
function blueworx_klaviyo_is_retryable( $status ) {
	return 0 === $status || in_array( $status, array( 401, 403, 408, 409, 429 ), true ) || $status >= 500;
}

/**
 * Finds a profile by email.
 *
 * @param string $email Email address.
 * @return array The request result, with 'profile' added: its attributes, or empty.
 */
function blueworx_klaviyo_find_profile( $email ) {
	$filter = 'equals(email,"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $email ) . '")';
	$result = blueworx_klaviyo_request( 'GET', 'profiles?filter=' . rawurlencode( $filter ) . '&fields[profile]=email,first_name,last_name,phone_number,external_id,organization,title,properties' );

	$result['profile'] = isset( $result['body']['data'][0]['attributes'] ) ? (array) $result['body']['data'][0]['attributes'] : array();

	return $result;
}

/**
 * Removes properties with nothing in them, so an empty box never wipes a value.
 *
 * Only null and empty strings go. False and zero are real answers.
 *
 * @param array $properties Properties.
 * @return array
 */
function blueworx_klaviyo_clean( $properties ) {
	return array_filter(
		(array) $properties,
		static function ( $value ) {
			return null !== $value && '' !== $value;
		}
	);
}

/**
 * Works out what to write to a profile, given what is already there.
 *
 * Pure, so the rules a site's marketing team depends on can be tested without
 * a site: a fill never overwrites, a guard never overwrites a held value, and
 * an empty value never wipes anything.
 *
 * @param array $plan     The plan.
 * @param array $existing The profile's attributes as Klaviyo holds them, or empty.
 * @return array Attributes for the profile import.
 */
function blueworx_klaviyo_profile_attributes( $plan, $existing = array() ) {
	$fields     = blueworx_klaviyo_profile_fields();
	$held       = isset( $existing['properties'] ) && is_array( $existing['properties'] ) ? $existing['properties'] : array();
	$attributes = array( 'email' => $plan['email'] );
	$properties = array();

	foreach ( blueworx_klaviyo_clean( isset( $plan['profile'] ) ? $plan['profile'] : array() ) as $name => $value ) {
		if ( in_array( $name, $fields, true ) ) {
			$attributes[ $name ] = $value;
		} else {
			$properties[ $name ] = $value;
		}
	}

	foreach ( blueworx_klaviyo_clean( isset( $plan['fill'] ) ? $plan['fill'] : array() ) as $name => $value ) {
		if ( in_array( $name, $fields, true ) ) {
			if ( empty( $existing[ $name ] ) ) {
				$attributes[ $name ] = $value;
			}
		} elseif ( ! isset( $held[ $name ] ) || '' === $held[ $name ] ) {
			$properties[ $name ] = $value;
		}
	}

	foreach ( blueworx_klaviyo_clean( isset( $plan['guard'] ) ? $plan['guard'] : array() ) as $name => $value ) {
		if ( ! isset( $held[ $name ] ) || '' === $held[ $name ] ) {
			$properties[ $name ] = $value;
		}
	}

	if ( $properties ) {
		$attributes['properties'] = $properties;
	}

	return $attributes;
}

/**
 * Whether carrying out a plan needs to know what the profile already holds.
 *
 * @param array $plan The plan.
 * @return bool
 */
function blueworx_klaviyo_plan_needs_profile( $plan ) {
	return ! empty( $plan['fill'] ) || ! empty( $plan['guard'] );
}

/**
 * The request body for an event.
 *
 * Only the event's own 'value' becomes Klaviyo's revenue figure. Every other
 * amount travels as an ordinary property.
 *
 * @param array $plan The plan.
 * @return array
 */
function blueworx_klaviyo_event_body( $plan ) {
	$event = $plan['event'];

	$attributes = array(
		'properties' => (object) blueworx_klaviyo_clean( isset( $event['properties'] ) ? $event['properties'] : array() ),
		'unique_id'  => (string) $event['unique_id'],
		'metric'     => array(
			'data' => array(
				'type'       => 'metric',
				'attributes' => array( 'name' => (string) $event['name'] ),
			),
		),
		'profile'    => array(
			'data' => array(
				'type'       => 'profile',
				'attributes' => array( 'email' => $plan['email'] ),
			),
		),
	);

	if ( isset( $event['value'] ) && '' !== $event['value'] ) {
		$attributes['value'] = (float) $event['value'];

		if ( ! empty( $event['currency'] ) ) {
			$attributes['value_currency'] = strtoupper( (string) $event['currency'] );
		}
	}

	if ( ! empty( $event['time'] ) ) {
		$attributes['time'] = gmdate( 'c', (int) $event['time'] );
	}

	return array(
		'data' => array(
			'type'       => 'event',
			'attributes' => $attributes,
		),
	);
}

/**
 * The request body for subscribing one email to one list.
 *
 * @param string $email   Email address.
 * @param string $list_id List ID.
 * @return array
 */
function blueworx_klaviyo_subscribe_body( $email, $list_id ) {
	return array(
		'data' => array(
			'type'          => 'profile-subscription-bulk-create-job',
			'attributes'    => array(
				'profiles'          => array(
					'data' => array(
						array(
							'type'       => 'profile',
							'attributes' => array(
								'email'         => $email,
								'subscriptions' => array(
									'email' => array(
										'marketing' => array( 'consent' => 'SUBSCRIBED' ),
									),
								),
							),
						),
					),
				),
				'historical_import' => false,
			),
			'relationships' => array(
				'list' => array(
					'data' => array(
						'type' => 'list',
						'id'   => (string) $list_id,
					),
				),
			),
		),
	);
}

/**
 * Carries out one plan: profile first, then the event, then the list.
 *
 * Safe to repeat from the top. The profile import is an update by email, an
 * event with a unique ID is recorded once, and subscribing someone who is
 * already subscribed changes nothing. That is what makes retrying simple.
 *
 * @param array $plan The plan.
 * @return array {
 *     @type bool   $ok    Whether it all went through.
 *     @type bool   $retry Whether trying again might help.
 *     @type string $error  What went wrong.
 *     @type int    $status The HTTP status that decided it.
 * }
 */
function blueworx_klaviyo_send_plan( $plan ) {
	$failed = static function ( $result ) {
		return array(
			'ok'     => false,
			'retry'  => blueworx_klaviyo_is_retryable( $result['status'] ),
			'error'  => $result['error'],
			'status' => (int) $result['status'],
		);
	};

	$existing = array();

	// Only asked when the answer changes what gets written.
	if ( blueworx_klaviyo_plan_needs_profile( $plan ) ) {
		$found = blueworx_klaviyo_find_profile( $plan['email'] );

		if ( '' !== $found['error'] ) {
			return $failed( $found );
		}

		$existing = $found['profile'];
	}

	$attributes = blueworx_klaviyo_profile_attributes( $plan, $existing );
	$has_unset  = ! empty( $plan['unset'] );

	// A plan that only records an event still names the profile, so Klaviyo
	// attaches the event to the right person. One with nothing to write and no
	// event or list is already done.
	if ( count( $attributes ) > 1 || $has_unset || ! empty( $plan['event'] ) || ! empty( $plan['list'] ) ) {
		$import = array(
			'data' => array(
				'type'       => 'profile',
				'attributes' => $attributes,
			),
		);

		if ( $has_unset ) {
			$import['data']['meta'] = array( 'patch_properties' => array( 'unset' => array_values( (array) $plan['unset'] ) ) );
		}

		$result = blueworx_klaviyo_request( 'POST', 'profile-import', $import );

		if ( '' !== $result['error'] ) {
			return $failed( $result );
		}
	}

	if ( ! empty( $plan['event'] ) ) {
		$result = blueworx_klaviyo_request( 'POST', 'events', blueworx_klaviyo_event_body( $plan ) );

		if ( '' !== $result['error'] ) {
			return $failed( $result );
		}
	}

	if ( ! empty( $plan['list'] ) ) {
		$result = blueworx_klaviyo_request( 'POST', 'profile-subscription-bulk-create-jobs', blueworx_klaviyo_subscribe_body( $plan['email'], $plan['list'] ) );

		if ( '' !== $result['error'] ) {
			return $failed( $result );
		}
	}

	return array(
		'ok'     => true,
		'retry'  => false,
		'error'  => '',
		'status' => 200,
	);
}

/**
 * The account's lists, by ID, remembered for ten minutes.
 *
 * For screens that let somebody pick a list. Asked of Klaviyo at most every
 * ten minutes, and never while a visitor is waiting.
 *
 * @param bool $fresh Whether to ask Klaviyo again rather than use what is remembered.
 * @return array|WP_Error List names keyed by ID.
 */
function blueworx_klaviyo_lists( $fresh = false ) {
	if ( ! $fresh ) {
		$cached = get_transient( 'blueworx_klaviyo_lists' );

		if ( is_array( $cached ) ) {
			return $cached;
		}
	}

	$result = blueworx_klaviyo_request( 'GET', 'lists?fields[list]=name' );

	if ( '' !== $result['error'] ) {
		return new WP_Error( 'blueworx_klaviyo_lists', $result['error'] );
	}

	$lists = array();

	foreach ( isset( $result['body']['data'] ) ? (array) $result['body']['data'] : array() as $list ) {
		if ( isset( $list['id'] ) ) {
			$lists[ (string) $list['id'] ] = isset( $list['attributes']['name'] ) ? (string) $list['attributes']['name'] : (string) $list['id'];
		}
	}

	set_transient( 'blueworx_klaviyo_lists', $lists, 10 * MINUTE_IN_SECONDS );

	return $lists;
}

/**
 * Checks the key works, for the Test connection button.
 *
 * Other parts of the connection add their own checks through the filter, so
 * one button tells the whole story: the key, then every list something here
 * sends to.
 *
 * @return array {
 *     @type bool     $ok    Whether everything answered.
 *     @type string[] $lines What was found, one sentence each.
 * }
 */
function blueworx_klaviyo_test_connection() {
	if ( '' === blueworx_klaviyo_private_key() ) {
		return array(
			'ok'    => false,
			'lines' => array( __( 'There is no private API key yet.', 'blueworx-labs-wordpress' ) ),
		);
	}

	$lists = blueworx_klaviyo_lists( true );

	if ( is_wp_error( $lists ) ) {
		return array(
			'ok'    => false,
			'lines' => array( $lists->get_error_message() ),
		);
	}

	$lines = array(
		$lists
			? sprintf(
				/* translators: 1: number of lists, 2: the lists' names. */
				_n( 'The key works. It can see %1$s list: %2$s.', 'The key works. It can see %1$s lists: %2$s.', count( $lists ), 'blueworx-labs-wordpress' ),
				number_format_i18n( count( $lists ) ),
				implode( ', ', $lists )
			)
			: __( 'The key works. The account has no lists yet.', 'blueworx-labs-wordpress' ),
	);

	$result = array(
		'ok'    => true,
		'lines' => $lines,
	);

	/**
	 * Lets the parts that send to Klaviyo add their own checks to the Test
	 * connection button: a list that must exist, a webhook that must be set.
	 *
	 * @param array $result ok and lines, as above.
	 * @param array $lists  The account's lists, names keyed by ID.
	 */
	$result = apply_filters( 'blueworx_klaviyo_test_connection', $result, $lists );

	return array(
		'ok'    => ! empty( $result['ok'] ),
		'lines' => isset( $result['lines'] ) ? (array) $result['lines'] : $lines,
	);
}
