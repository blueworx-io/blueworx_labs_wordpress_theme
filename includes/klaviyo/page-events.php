<?php
/**
 * Klaviyo connection: an event when a chosen page is viewed.
 *
 * A thank-you page, a landing page, a pricing page: a site owner marks it in
 * the editor's sidebar, names the event, and every view by a known visitor
 * reaches Klaviyo as that event. The event rides on Klaviyo's own site
 * script, so it follows the same rules as the rest of tracking: never for
 * administrators, never on admin screens, and behind cookie consent once that
 * arrives.
 *
 * The name is post meta, registered on every public post type, that anyone
 * who can edit the page can set. Off is simply no name.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The meta key the event's name is kept in. Empty means off.
 */
const BLUEWORX_KLAVIYO_PAGE_EVENT_META = '_blueworx_klaviyo_event';

/**
 * The post types a page event can be set on: every public one.
 *
 * @return string[]
 */
function blueworx_klaviyo_page_event_post_types() {
	$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );

	/**
	 * The post types that can send a Klaviyo event when viewed.
	 *
	 * @param string[] $types Post type names. Every public type but attachments by default.
	 */
	return array_values( array_unique( array_filter( array_map( 'strval', (array) apply_filters( 'blueworx_klaviyo_page_event_post_types', $types ) ) ) ) );
}

/**
 * Tidies a typed event name.
 *
 * @param mixed $name What was typed.
 * @return string
 */
function blueworx_klaviyo_clean_page_event_name( $name ) {
	return trim( sanitize_text_field( (string) $name ) );
}

/**
 * The event a page sends, or nothing. Pure.
 *
 * @param string $name  The event's name, as set on the page.
 * @param string $title The page's title.
 * @param string $url   The page's address.
 * @param string $type  The post type.
 * @return array|null An entry for blueworx_klaviyo_page_events, or null when off.
 */
function blueworx_klaviyo_page_event_for( $name, $title, $url, $type ) {
	$name = blueworx_klaviyo_clean_page_event_name( $name );

	if ( '' === $name ) {
		return null;
	}

	return array(
		'name'       => $name,
		'properties' => blueworx_klaviyo_clean(
			array(
				'PageTitle' => (string) $title,
				'URL'       => (string) $url,
				'PostType'  => (string) $type,
			)
		),
	);
}

/**
 * Lets the editor read and write the name, for anyone who can edit the page.
 *
 * @return void
 */
function blueworx_klaviyo_page_event_register_meta() {
	if ( ! blueworx_klaviyo_enabled() ) {
		return;
	}

	foreach ( blueworx_klaviyo_page_event_post_types() as $type ) {
		register_post_meta(
			$type,
			BLUEWORX_KLAVIYO_PAGE_EVENT_META,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'blueworx_klaviyo_clean_page_event_name',
				'auth_callback'     => 'blueworx_klaviyo_page_event_can_edit',
			)
		);
	}
}
add_action( 'init', 'blueworx_klaviyo_page_event_register_meta', 20 );

/**
 * Whether the current user may set the event on this page: whoever can edit it.
 *
 * @param bool   $allowed  Whether the user can add the meta.
 * @param string $meta_key The meta key.
 * @param int    $post_id  The post.
 * @return bool
 */
function blueworx_klaviyo_page_event_can_edit( $allowed, $meta_key, $post_id ) {
	return current_user_can( 'edit_post', (int) $post_id );
}

/**
 * Adds the page's own event to what the script sends as the page loads.
 *
 * @param array $events  Each entry has a 'name' and 'properties'.
 * @param int   $post_id The post being viewed, or 0.
 * @return array
 */
function blueworx_klaviyo_page_event_add( $events, $post_id ) {
	$post_id = (int) $post_id;

	if ( $post_id <= 0 ) {
		return $events;
	}

	$event = blueworx_klaviyo_page_event_for(
		get_post_meta( $post_id, BLUEWORX_KLAVIYO_PAGE_EVENT_META, true ),
		get_the_title( $post_id ),
		get_permalink( $post_id ),
		get_post_type( $post_id )
	);

	if ( $event ) {
		$events[] = $event;
	}

	return $events;
}
add_filter( 'blueworx_klaviyo_page_events', 'blueworx_klaviyo_page_event_add', 5, 2 );

/**
 * Adds the Klaviyo event panel to the block editor's sidebar.
 *
 * @return void
 */
function blueworx_klaviyo_page_event_editor_assets() {
	$post = get_post();

	if ( ! blueworx_klaviyo_enabled() || ! $post || ! in_array( $post->post_type, blueworx_klaviyo_page_event_post_types(), true ) ) {
		return;
	}

	wp_enqueue_script(
		'blueworx-klaviyo-page-event',
		BLUEWORX_LABS_URL . 'assets/js/klaviyo-page-event.js',
		array( 'wp-plugins', 'wp-editor', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n' ),
		BLUEWORX_LABS_VERSION,
		true
	);
	wp_localize_script(
		'blueworx-klaviyo-page-event',
		'blueworxKlaviyoPageEvent',
		array(
			'metaKey' => BLUEWORX_KLAVIYO_PAGE_EVENT_META,
			'copy'    => array(
				'title'       => __( 'Klaviyo', 'blueworx-labs-wordpress' ),
				'label'       => __( 'Send an event when this page is viewed', 'blueworx-labs-wordpress' ),
				'help'        => __( 'Every view by a visitor Klaviyo knows reaches their profile as this event. Administrators are never counted.', 'blueworx-labs-wordpress' ),
				'off'         => __( 'Viewing this page sends nothing to Klaviyo beyond the ordinary page view.', 'blueworx-labs-wordpress' ),
				'name'        => __( 'Event name', 'blueworx-labs-wordpress' ),
				'nameHelp'    => __( 'As campaigns and flows will see it, for example Booked a Demo. Saved with the page.', 'blueworx-labs-wordpress' ),
				'placeholder' => __( 'Viewed Pricing', 'blueworx-labs-wordpress' ),
			),
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'blueworx_klaviyo_page_event_editor_assets' );

/**
 * Says what page events send, on the Klaviyo screen.
 *
 * @param array $sources Entries with 'label' and 'text'.
 * @return array
 */
function blueworx_klaviyo_page_event_source( $sources ) {
	$sources[] = array(
		'label' => __( 'Page events', 'blueworx-labs-wordpress' ),
		'text'  => __( 'Any page or post can send an event of its own when viewed: tick the box in the editor\'s Klaviyo panel and name the event. Sent by the site script, so the same rules as visitor tracking apply.', 'blueworx-labs-wordpress' ),
	);

	return $sources;
}
add_filter( 'blueworx_klaviyo_sources', 'blueworx_klaviyo_page_event_source', 5 );
