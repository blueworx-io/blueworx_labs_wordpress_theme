<?php
/**
 * Klaviyo connection: the functions everything else calls.
 *
 * Three plain functions cover what a site ever needs to tell Klaviyo: who
 * somebody is, what they did, and which list they agreed to join. The rest of
 * the theme uses these, and so does a site's own plugin. Each one queues the
 * work and returns straight away; nothing here waits on Klaviyo.
 *
 * Every send passes through one filter first, so a site plugin can add to it,
 * change it or stop it. See docs/klaviyo-api.md.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells Klaviyo who somebody is: properties on their profile.
 *
 * @param string $email      Their email address.
 * @param array  $properties Profile properties to set. Klaviyo's own fields
 *                           (first_name, last_name, phone_number, external_id,
 *                           organization, title) go where Klaviyo keeps them;
 *                           anything else becomes a custom property.
 * @param array  $args {
 *     Optional.
 *
 *     @type array  $fill   Properties written only when the profile has none yet.
 *     @type array  $unset  Property names to remove.
 *     @type string $label  What the log calls it. Defaults to "Profile update".
 *     @type string $source Where this came from, for the filter. Defaults to "plugin".
 *     @type string $key    Identifies the job, so a repeat lands on the same row.
 * }
 * @return bool Whether it was queued.
 */
function blueworx_klaviyo_identify( $email, $properties = array(), $args = array() ) {
	$args = is_array( $args ) ? $args : array();

	return blueworx_klaviyo_send(
		array(
			'label'   => isset( $args['label'] ) ? $args['label'] : __( 'Profile update', 'blueworx-labs-wordpress' ),
			'email'   => $email,
			'profile' => (array) $properties,
			'fill'    => isset( $args['fill'] ) ? (array) $args['fill'] : array(),
			'unset'   => isset( $args['unset'] ) ? (array) $args['unset'] : array(),
		),
		isset( $args['source'] ) ? (string) $args['source'] : 'plugin',
		isset( $args['key'] ) ? (string) $args['key'] : ''
	);
}

/**
 * Tells Klaviyo somebody did something: an event on their profile.
 *
 * @param string $event      The event's name, as campaigns will see it.
 * @param string $email      Their email address.
 * @param array  $properties Details of the event.
 * @param array  $args {
 *     Optional.
 *
 *     @type string $unique_id Identifies the event, so a repeat is recorded once.
 *                             Defaults to one made from the name, email and time.
 *     @type float  $value     Money Klaviyo should count as revenue. Leave it out
 *                             for anything that is not a sale.
 *     @type string $currency  The value's ISO currency code.
 *     @type int    $time      When it happened, as a timestamp. Defaults to now.
 *     @type array  $profile   Profile properties to set at the same time.
 *     @type array  $fill      Properties written only when the profile has none yet.
 *     @type string $list      A list ID to subscribe them to at the same time.
 *     @type string $label     What the log calls it. Defaults to the event's name.
 *     @type string $source    Where this came from, for the filter. Defaults to "plugin".
 *     @type string $key       Identifies the job, so a repeat lands on the same row.
 * }
 * @return bool Whether it was queued.
 */
function blueworx_klaviyo_track( $event, $email, $properties = array(), $args = array() ) {
	$args  = is_array( $args ) ? $args : array();
	$event = (string) $event;

	if ( '' === $event ) {
		return false;
	}

	$unique = isset( $args['unique_id'] ) && '' !== (string) $args['unique_id']
		? (string) $args['unique_id']
		: md5( $event . '|' . strtolower( trim( (string) $email ) ) . '|' . microtime( true ) );

	return blueworx_klaviyo_send(
		array(
			'label'   => isset( $args['label'] ) ? $args['label'] : $event,
			'email'   => $email,
			'profile' => isset( $args['profile'] ) ? (array) $args['profile'] : array(),
			'fill'    => isset( $args['fill'] ) ? (array) $args['fill'] : array(),
			'event'   => array(
				'name'       => $event,
				'unique_id'  => $unique,
				'properties' => (array) $properties,
				'value'      => isset( $args['value'] ) ? $args['value'] : null,
				'currency'   => isset( $args['currency'] ) ? (string) $args['currency'] : '',
				'time'       => isset( $args['time'] ) ? (int) $args['time'] : 0,
			),
			'list'    => isset( $args['list'] ) ? (string) $args['list'] : '',
		),
		isset( $args['source'] ) ? (string) $args['source'] : 'plugin',
		isset( $args['key'] ) ? (string) $args['key'] : ''
	);
}

/**
 * Subscribes somebody to a list. Only call it for somebody who agreed.
 *
 * @param string $email   Their email address.
 * @param string $list_id The list's ID in Klaviyo.
 * @param array  $args {
 *     Optional.
 *
 *     @type array  $profile Profile properties to set at the same time.
 *     @type array  $fill    Properties written only when the profile has none yet.
 *     @type string $label   What the log calls it. Defaults to "List subscription".
 *     @type string $source  Where this came from, for the filter. Defaults to "plugin".
 *     @type string $key     Identifies the job, so a repeat lands on the same row.
 * }
 * @return bool Whether it was queued.
 */
function blueworx_klaviyo_subscribe( $email, $list_id, $args = array() ) {
	$args    = is_array( $args ) ? $args : array();
	$list_id = trim( (string) $list_id );

	if ( '' === $list_id ) {
		return false;
	}

	return blueworx_klaviyo_send(
		array(
			'label'   => isset( $args['label'] ) ? $args['label'] : __( 'List subscription', 'blueworx-labs-wordpress' ),
			'email'   => $email,
			'profile' => isset( $args['profile'] ) ? (array) $args['profile'] : array(),
			'fill'    => isset( $args['fill'] ) ? (array) $args['fill'] : array(),
			'list'    => $list_id,
		),
		isset( $args['source'] ) ? (string) $args['source'] : 'plugin',
		isset( $args['key'] ) ? (string) $args['key'] : ''
	);
}

/**
 * Queues one plan, after the filter has had its say.
 *
 * The three functions above all come here. A plan the filter returns as
 * empty, false or null is dropped quietly.
 *
 * @param array  $plan   The plan. See includes/klaviyo/client.php for its shape.
 * @param string $source Where it came from: tracking, forms, store, plugin, or a site plugin's own word.
 * @param string $key    Identifies the job, so a repeat lands on the same row. Empty makes a fresh one.
 * @return bool Whether it was queued.
 */
function blueworx_klaviyo_send( $plan, $source = 'plugin', $key = '' ) {
	if ( ! blueworx_klaviyo_enabled() ) {
		return false;
	}

	$plan = blueworx_klaviyo_normalize_plan( $plan );

	if ( ! $plan ) {
		return false;
	}

	/**
	 * Every send to Klaviyo, before it is queued.
	 *
	 * Add a property, change the list, rename the event, or return null to
	 * send nothing. The plan's shape is documented in docs/klaviyo-api.md.
	 *
	 * @param array  $plan   The plan.
	 * @param string $source Where it came from: tracking, forms, store, plugin, or a site plugin's own word.
	 */
	$plan = apply_filters( 'blueworx_klaviyo_plan', $plan, (string) $source );
	$plan = blueworx_klaviyo_normalize_plan( $plan );

	if ( ! $plan ) {
		return false;
	}

	if ( '' === (string) $key ) {
		$key = $source . ':' . $plan['email'] . ':' . $plan['label'] . ':' . microtime( true ) . ':' . wp_rand();
	}

	return blueworx_klaviyo_queue_job(
		(string) $key,
		array(
			'label'  => $plan['label'],
			'email'  => $plan['email'],
			'source' => (string) $source,
			'plans'  => array( $plan ),
		)
	);
}

/**
 * Property names the theme must never overwrite once they have a value.
 *
 * Marketing teams manage some properties by hand in Klaviyo. Anything named
 * here is only ever written when the profile has no value for it yet.
 *
 * @return string[]
 */
function blueworx_klaviyo_protected_properties() {
	/**
	 * Property names that are only ever written when the profile has none.
	 *
	 * @param string[] $names Property names.
	 */
	$names = apply_filters( 'blueworx_klaviyo_protected_properties', array() );

	return array_values( array_unique( array_filter( array_map( 'strval', (array) $names ) ) ) );
}

/**
 * Puts a plan into the one shape the rest of the connection expects.
 *
 * Pure apart from the protected-properties filter. Checks the email, fills in
 * every key, drops empty values, and moves protected properties from "write
 * every time" to "write only when empty".
 *
 * @param mixed $plan What a caller or the filter handed over.
 * @return array|null The plan, or null when there is nothing sendable in it.
 */
function blueworx_klaviyo_normalize_plan( $plan ) {
	if ( ! is_array( $plan ) ) {
		return null;
	}

	$email = isset( $plan['email'] ) ? sanitize_email( strtolower( trim( (string) $plan['email'] ) ) ) : '';

	if ( ! is_email( $email ) ) {
		return null;
	}

	$clean = array(
		'label'   => isset( $plan['label'] ) ? sanitize_text_field( (string) $plan['label'] ) : '',
		'email'   => $email,
		'profile' => blueworx_klaviyo_clean( isset( $plan['profile'] ) ? (array) $plan['profile'] : array() ),
		'fill'    => blueworx_klaviyo_clean( isset( $plan['fill'] ) ? (array) $plan['fill'] : array() ),
		'guard'   => blueworx_klaviyo_clean( isset( $plan['guard'] ) ? (array) $plan['guard'] : array() ),
		'unset'   => array_values( array_filter( array_map( 'strval', isset( $plan['unset'] ) ? (array) $plan['unset'] : array() ) ) ),
		'event'   => array(),
		'list'    => isset( $plan['list'] ) ? trim( (string) $plan['list'] ) : '',
	);

	foreach ( blueworx_klaviyo_protected_properties() as $name ) {
		if ( array_key_exists( $name, $clean['profile'] ) ) {
			$clean['guard'][ $name ] = $clean['profile'][ $name ];
			unset( $clean['profile'][ $name ] );
		}
	}

	if ( ! empty( $plan['event'] ) && is_array( $plan['event'] ) && ! empty( $plan['event']['name'] ) ) {
		$event          = $plan['event'];
		$clean['event'] = array(
			'name'       => sanitize_text_field( (string) $event['name'] ),
			'unique_id'  => isset( $event['unique_id'] ) && '' !== (string) $event['unique_id'] ? (string) $event['unique_id'] : md5( (string) $event['name'] . '|' . $email . '|' . microtime( true ) ),
			'properties' => blueworx_klaviyo_clean( isset( $event['properties'] ) ? (array) $event['properties'] : array() ),
			'value'      => isset( $event['value'] ) && '' !== $event['value'] && null !== $event['value'] ? (float) $event['value'] : null,
			'currency'   => isset( $event['currency'] ) ? strtoupper( trim( (string) $event['currency'] ) ) : '',
			'time'       => isset( $event['time'] ) ? (int) $event['time'] : 0,
		);
	}

	if ( '' === $clean['label'] ) {
		$clean['label'] = $clean['event'] ? $clean['event']['name'] : __( 'Profile update', 'blueworx-labs-wordpress' );
	}

	if ( ! $clean['profile'] && ! $clean['fill'] && ! $clean['guard'] && ! $clean['unset'] && ! $clean['event'] && '' === $clean['list'] ) {
		return null;
	}

	return $clean;
}

/**
 * What sends to Klaviyo on this site, for the settings screen.
 *
 * @return array Each entry has a 'label' and a one-line 'text'.
 */
function blueworx_klaviyo_sources() {
	/**
	 * The parts of the site that send to Klaviyo, listed on the Klaviyo screen.
	 *
	 * Add an entry with a 'label' and a 'text' so the person setting the site
	 * up can see what will reach Klaviyo and from where.
	 *
	 * @param array $sources Entries with 'label' and 'text'.
	 */
	$sources = apply_filters( 'blueworx_klaviyo_sources', array() );

	return array_values(
		array_filter(
			(array) $sources,
			static function ( $source ) {
				return is_array( $source ) && ! empty( $source['label'] );
			}
		)
	);
}
