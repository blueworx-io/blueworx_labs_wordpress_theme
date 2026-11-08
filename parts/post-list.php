<?php
/**
 * A list of posts: the blog, a category, a tag, a date, an author, a search.
 *
 * Used by home.php, archive.php, search.php and index.php. The heading and
 * every card are built in includes/blog.php.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo blueworx_blog_list_head_html( blueworx_blog_current_list_title(), is_archive() ? get_the_archive_description() : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts; the description is WordPress's own filtered HTML.

if ( have_posts() ) {
	echo '<div class="blueworx-posts">';

	while ( have_posts() ) {
		the_post();
		echo blueworx_blog_current_card(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
	}

	echo '</div>';

	blueworx_blog_pagination();
} else {
	echo blueworx_blog_empty_html( is_search() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where built.
}
