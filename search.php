<?php
/**
 * Search results, inside the frame. See parts/post-list.php.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
get_template_part( 'parts/post-list' );
get_footer();
