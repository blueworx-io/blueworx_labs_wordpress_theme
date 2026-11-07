<?php
/**
 * Locked pages.
 *
 * An administrator locks a page so a client can change its words and images
 * but cannot move, add or remove blocks. The lock is one post meta flag that
 * only administrators may write. While somebody else edits a locked page the
 * block editor is told to lock the template, and the server checks every save
 * regardless of where it came from: the shape of the block tree (which blocks,
 * in which order, nested how) has to come back exactly as it went out. Text
 * and attributes may change; shape may not. A REST save that changes the shape
 * is refused with a message; any other write keeps the content it had.
 *
 * The editor-side lock is a courtesy so the client is not offered controls
 * that will fail. The server-side check is the guarantee, and it is what makes
 * the lock hold for a connected tool as well as for a person.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BLUEWORX_PAGE_LOCK_META = '_blueworx_page_lock';

/**
 * The post types a lock can be set on. Pages, unless a plugin adds its own.
 *
 * @return string[]
 */
function blueworx_page_lock_post_types() {
	return (array) apply_filters( 'blueworx_page_lock_post_types', array( 'page' ) );
}

/**
 * Whether the current user may lock and unlock pages: administrators.
 *
 * @return bool
 */
function blueworx_page_lock_can_manage() {
	return current_user_can( apply_filters( 'blueworx_page_lock_capability', 'manage_options' ) );
}

/**
 * Whether a post is locked.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function blueworx_page_is_locked( $post_id ) {
	return (bool) get_post_meta( (int) $post_id, BLUEWORX_PAGE_LOCK_META, true );
}

/**
 * The shape of a block tree: which blocks, in which order, nested how.
 *
 * Reads the block comments WordPress writes into post content and returns a
 * nested list of [ block name, children ]. Everything else — text, markup,
 * attributes — is ignored, because a locked page is still a page somebody is
 * allowed to write on. Pure PHP, no WordPress functions, so it is testable on
 * its own and never disagrees with itself between the editor and the API.
 *
 * @param string $content Post content.
 * @return array<int, array{0: string, 1: array}>
 */
function blueworx_page_lock_shape( $content ) {
	$root  = array();
	$stack = array( &$root );

	// <!-- wp:name {"json":1} --> … <!-- /wp:name -->, or <!-- wp:name /-->.
	// Attributes are skipped by matching the JSON object non-greedily; a block
	// whose JSON happens to contain "-->" is not something WordPress writes.
	if ( ! preg_match_all( '#<!--\s+(/)?wp:([a-z][a-z0-9_-]*(?:/[a-z][a-z0-9_-]*)?)(?:\s+\{.*?\})?\s*(/)?-->#s', (string) $content, $matches, PREG_SET_ORDER ) ) {
		return $root;
	}

	foreach ( $matches as $m ) {
		$closing = '' !== $m[1];
		$name    = false === strpos( $m[2], '/' ) ? 'core/' . $m[2] : $m[2];
		$void    = isset( $m[3] ) && '' !== $m[3];
		$depth   = count( $stack ) - 1;

		if ( $closing ) {
			if ( $depth > 0 ) {
				array_pop( $stack );
			}
			continue;
		}

		$node                 = array( $name, array() );
		$stack[ $depth ][]    = $node;
		$index                = count( $stack[ $depth ] ) - 1;

		if ( ! $void ) {
			$stack[] = &$stack[ $depth ][ $index ][1];
		}
	}

	return $root;
}

/**
 * Whether two pieces of content differ in shape.
 *
 * @param string $old Content as it was saved.
 * @param string $new Content being saved.
 * @return bool
 */
function blueworx_page_lock_structure_changed( $old, $new ) {
	return blueworx_page_lock_shape( $old ) !== blueworx_page_lock_shape( $new );
}

/**
 * The message a client sees when a save would change a locked page's shape.
 *
 * @return string
 */
function blueworx_page_lock_message() {
	return __( 'This page is locked. You can change its words and images, but not add, move or remove blocks. Ask an administrator to unlock it.', 'blueworx-labs-wordpress' );
}

/**
 * Whether a save by the current user must keep this post's shape.
 *
 * @param int $post_id Post being saved.
 * @return bool
 */
function blueworx_page_lock_applies( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return false;
	}
	if ( ! in_array( (string) get_post_type( $post_id ), blueworx_page_lock_post_types(), true ) ) {
		return false;
	}
	return blueworx_page_is_locked( $post_id ) && ! blueworx_page_lock_can_manage();
}

/**
 * Registers the lock as post meta that only administrators can write.
 *
 * @return void
 */
function blueworx_page_lock_register_meta() {
	foreach ( blueworx_page_lock_post_types() as $type ) {
		register_post_meta(
			$type,
			BLUEWORX_PAGE_LOCK_META,
			array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => true,
				'auth_callback' => 'blueworx_page_lock_can_manage',
			)
		);
	}
}

/**
 * Stops anyone but an administrator writing the lock, whatever the route.
 *
 * The REST auth callback above covers the editor and the API. This covers
 * direct meta writes from plugin code or imports as well, so there is no
 * second door.
 *
 * @param null|bool $check    Whether to short-circuit.
 * @param int       $post_id  Post ID.
 * @param string    $meta_key Meta key.
 * @return null|bool
 */
function blueworx_page_lock_guard_meta( $check, $post_id, $meta_key ) {
	if ( BLUEWORX_PAGE_LOCK_META !== $meta_key || blueworx_page_lock_can_manage() ) {
		return $check;
	}
	return false;
}

/**
 * Refuses a REST save that would change a locked page's shape.
 *
 * @param stdClass|WP_Error $prepared The post as prepared for the database.
 * @param WP_REST_Request   $request  The request.
 * @return stdClass|WP_Error
 */
function blueworx_page_lock_rest_guard( $prepared, $request ) {
	if ( is_wp_error( $prepared ) || empty( $prepared->ID ) || ! isset( $prepared->post_content ) ) {
		return $prepared;
	}
	if ( ! blueworx_page_lock_applies( $prepared->ID ) ) {
		return $prepared;
	}

	$existing = get_post( $prepared->ID );
	if ( $existing && blueworx_page_lock_structure_changed( $existing->post_content, $prepared->post_content ) ) {
		return new WP_Error( 'blueworx_page_locked', blueworx_page_lock_message(), array( 'status' => 403 ) );
	}

	return $prepared;
}

/**
 * Keeps a locked page's content on any save that is not REST.
 *
 * There is no way to refuse from here with a message, so the content stays
 * as it was and the rest of the save (title, excerpt) goes through.
 *
 * @param array $data    Slashed post data about to be written.
 * @param array $postarr The full post array, including ID.
 * @return array
 */
function blueworx_page_lock_insert_guard( $data, $postarr ) {
	$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
	if ( ! $post_id || ! isset( $data['post_content'] ) || ! blueworx_page_lock_applies( $post_id ) ) {
		return $data;
	}

	$existing = get_post( $post_id );
	if ( $existing && blueworx_page_lock_structure_changed( $existing->post_content, wp_unslash( $data['post_content'] ) ) ) {
		$data['post_content'] = wp_slash( $existing->post_content );
	}

	return $data;
}

/**
 * Locks the template in the block editor for anyone who may not change it.
 *
 * @param array                   $settings Editor settings.
 * @param WP_Block_Editor_Context $context  Editor context.
 * @return array
 */
function blueworx_page_lock_editor_settings( $settings, $context ) {
	if ( empty( $context->post ) || ! blueworx_page_lock_applies( $context->post->ID ) ) {
		return $settings;
	}
	$settings['templateLock']  = 'all';
	$settings['canLockBlocks'] = false;
	return $settings;
}

/**
 * Marks locked pages in the Pages list.
 *
 * @param string[] $states Post states.
 * @param WP_Post  $post   The post.
 * @return string[]
 */
function blueworx_page_lock_post_state( $states, $post ) {
	if ( $post && in_array( $post->post_type, blueworx_page_lock_post_types(), true ) && blueworx_page_is_locked( $post->ID ) ) {
		$states['blueworx_page_lock'] = __( 'Locked', 'blueworx-labs-wordpress' );
	}
	return $states;
}

/**
 * Adds the Page lock panel to the block editor's sidebar.
 *
 * @return void
 */
function blueworx_page_lock_editor_assets() {
	$post = get_post();
	if ( ! $post || ! in_array( $post->post_type, blueworx_page_lock_post_types(), true ) ) {
		return;
	}

	wp_enqueue_script(
		'blueworx-page-lock',
		BLUEWORX_LABS_URL . 'assets/js/page-lock.js',
		array( 'wp-plugins', 'wp-editor', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n' ),
		BLUEWORX_LABS_VERSION,
		true
	);
	wp_localize_script(
		'blueworx-page-lock',
		'blueworxPageLock',
		array(
			'metaKey'   => BLUEWORX_PAGE_LOCK_META,
			'canManage' => blueworx_page_lock_can_manage(),
			'copy'      => array(
				'title'    => __( 'Page lock', 'blueworx-labs-wordpress' ),
				'label'    => __( 'Locked', 'blueworx-labs-wordpress' ),
				'help'     => __( 'Everyone else can change the words and images on this page, but not add, move or remove blocks.', 'blueworx-labs-wordpress' ),
				'unlocked' => __( 'Anyone who can edit this page can change its layout.', 'blueworx-labs-wordpress' ),
				'locked'   => __( 'This page is locked by an administrator. You can change its words and images, but not add, move or remove blocks.', 'blueworx-labs-wordpress' ),
			),
		)
	);
}

if ( blueworx_feature_enabled( 'page_lock' ) ) {
	add_action( 'init', 'blueworx_page_lock_register_meta' );
	add_filter( 'add_post_metadata', 'blueworx_page_lock_guard_meta', 10, 3 );
	add_filter( 'update_post_metadata', 'blueworx_page_lock_guard_meta', 10, 3 );
	add_filter( 'delete_post_metadata', 'blueworx_page_lock_guard_meta', 10, 3 );
	add_filter( 'wp_insert_post_data', 'blueworx_page_lock_insert_guard', 10, 2 );
	add_filter( 'block_editor_settings_all', 'blueworx_page_lock_editor_settings', 10, 2 );
	add_filter( 'display_post_states', 'blueworx_page_lock_post_state', 10, 2 );
	add_action( 'enqueue_block_editor_assets', 'blueworx_page_lock_editor_assets' );
	add_action(
		'init',
		function () {
			foreach ( blueworx_page_lock_post_types() as $type ) {
				add_filter( 'rest_pre_insert_' . $type, 'blueworx_page_lock_rest_guard', 10, 2 );
			}
		}
	);
}
