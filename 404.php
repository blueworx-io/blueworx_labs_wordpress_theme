<?php
/**
 * Page not found: says so, offers a search and a few places to go.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

echo blueworx_404_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts; the search form is WordPress's own.
	home_url( '/' ),
	array_slice( blueworx_frame_fallback_pages(), 0, 4 ),
	get_search_form( array( 'echo' => false ) )
);

get_footer();
