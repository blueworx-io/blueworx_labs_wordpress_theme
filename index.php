<?php
/**
 * The page every public request falls back to when no closer template fits.
 *
 * Pages have page.php, posts single.php, and lists home.php, archive.php and
 * search.php. Whatever is left lands here and is drawn the same way: one
 * thing as a post, several as a list.
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
get_template_part( is_singular() ? 'parts/post-single' : 'parts/post-list' );
get_footer();
