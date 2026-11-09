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

// With the 1.x plugin still active the theme's code has not loaded. See parts/plain.php.
if ( ! function_exists( 'blueworx_frame_enabled' ) ) {
	require get_template_directory() . '/parts/plain.php';
	return;
}

get_header();

echo blueworx_404_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts; the search form is WordPress's own.
	home_url( '/' ),
	array_slice( blueworx_frame_fallback_pages(), 0, 4 ),
	get_search_form( array( 'echo' => false ) )
);

get_footer();
