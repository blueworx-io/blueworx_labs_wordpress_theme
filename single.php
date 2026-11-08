<?php
/**
 * One post, inside the frame. See parts/post-single.php.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
get_template_part( 'parts/post-single' );
get_footer();
