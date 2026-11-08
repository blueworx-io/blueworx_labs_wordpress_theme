<?php
/**
 * Blog and post pages: the list of posts, one post, and the archives.
 *
 * The templates (home.php, archive.php, search.php, single.php and the parts
 * under parts/) stay thin and call in here, so every piece of markup is built
 * by a pure function that tests/php/blog-test.php can check without a site.
 * Styled by the "Posts" section of assets/css/frame.css, from the named
 * styles alone.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The heading of a list page.
 *
 * The posts page's own title when the site has one; the archive's name on a
 * category, tag, date or author page, without WordPress's "Category:"
 * prefix; what was searched for on a search page; and "Blog" when the front
 * page is the list of posts and nothing names it.
 *
 * @param array<string,mixed> $context is_404, is_home, is_search, is_archive, posts_page_title, archive_title, search_query.
 * @return string Plain text.
 */
function blueworx_blog_list_title( $context ) {
	if ( ! empty( $context['is_404'] ) ) {
		return __( 'Page not found', 'blueworx-labs-wordpress' );
	}

	if ( ! empty( $context['is_search'] ) ) {
		return sprintf(
			/* translators: %s: what was searched for. */
			__( 'Search results for “%s”', 'blueworx-labs-wordpress' ),
			trim( (string) ( $context['search_query'] ?? '' ) )
		);
	}

	if ( ! empty( $context['is_archive'] ) ) {
		$title = trim( (string) ( $context['archive_title'] ?? '' ) );

		if ( '' !== $title ) {
			return $title;
		}
	}

	$posts_page = trim( (string) ( $context['posts_page_title'] ?? '' ) );

	return '' !== $posts_page ? $posts_page : __( 'Blog', 'blueworx-labs-wordpress' );
}

/**
 * Takes the "Category: " and "Tag: " prefixes off archive titles.
 *
 * The page's heading already sits on a page that looks like a category, and
 * the prefix reads as a label left in by mistake.
 *
 * @return string
 */
function blueworx_blog_archive_title_prefix() {
	return '';
}
add_filter( 'get_the_archive_title_prefix', 'blueworx_blog_archive_title_prefix' );

/**
 * A post's by-line: when, by whom, and in which categories.
 *
 * Pure. Every part is drawn only when it has a value.
 *
 * @param array<string,mixed> $meta date_iso, date_text, author, author_url, categories (each a label and an href).
 * @return string
 */
function blueworx_blog_meta_html( $meta ) {
	$date_iso   = trim( (string) ( $meta['date_iso'] ?? '' ) );
	$date_text  = trim( (string) ( $meta['date_text'] ?? '' ) );
	$author     = trim( (string) ( $meta['author'] ?? '' ) );
	$author_url = trim( (string) ( $meta['author_url'] ?? '' ) );
	$categories = is_array( $meta['categories'] ?? null ) ? $meta['categories'] : array();

	$parts = array();

	if ( '' !== $date_text ) {
		$parts[] = '<time class="blueworx-post__date" datetime="' . blueworx_frame_e( $date_iso ) . '">' . blueworx_frame_e( $date_text ) . '</time>';
	}

	if ( '' !== $author ) {
		$name    = '' !== $author_url
			? '<a href="' . blueworx_frame_e( $author_url ) . '">' . blueworx_frame_e( $author ) . '</a>'
			: blueworx_frame_e( $author );
		$parts[] = '<span class="blueworx-post__author">' . $name . '</span>';
	}

	$cats = '';

	foreach ( $categories as $category ) {
		$label = trim( (string) ( $category['label'] ?? '' ) );
		$href  = trim( (string) ( $category['href'] ?? '' ) );

		if ( '' === $label || '' === $href ) {
			continue;
		}

		$cats .= '<a class="blueworx-post__category" href="' . blueworx_frame_e( $href ) . '">' . blueworx_frame_e( $label ) . '</a>';
	}

	if ( '' !== $cats ) {
		$parts[] = '<span class="blueworx-post__categories">' . $cats . '</span>';
	}

	if ( array() === $parts ) {
		return '';
	}

	return '<p class="blueworx-post__meta">' . implode( '', $parts ) . '</p>';
}

/**
 * One post in a list. Pure.
 *
 * The image, when there is one, links to the post like the title does, so a
 * reader can tap either. Its alt text is empty on purpose: the title beside
 * it says what the post is, and a reader would otherwise hear it twice.
 *
 * @param array<string,mixed> $card title, href, excerpt, image (HTML, or ''), meta (HTML from blueworx_blog_meta_html()).
 * @return string
 */
function blueworx_blog_card_html( $card ) {
	$title   = trim( (string) ( $card['title'] ?? '' ) );
	$href    = trim( (string) ( $card['href'] ?? '' ) );
	$excerpt = trim( (string) ( $card['excerpt'] ?? '' ) );
	$image   = trim( (string) ( $card['image'] ?? '' ) );
	$meta    = (string) ( $card['meta'] ?? '' );

	if ( '' === $title ) {
		$title = __( '(Untitled)', 'blueworx-labs-wordpress' );
	}

	$out = '<article class="blueworx-card">';

	if ( '' !== $image ) {
		$out .= '<a class="blueworx-card__image" href="' . blueworx_frame_e( $href ) . '" tabindex="-1" aria-hidden="true">' . $image . '</a>';
	}

	$out .= '<div class="blueworx-card__body">' . $meta
		. '<h2 class="blueworx-card__title"><a href="' . blueworx_frame_e( $href ) . '">' . blueworx_frame_e( $title ) . '</a></h2>';

	if ( '' !== $excerpt ) {
		$out .= '<p class="blueworx-card__excerpt">' . blueworx_frame_e( $excerpt ) . '</p>';
	}

	return $out . '</div></article>';
}

/**
 * The head of a list page: its title and, when there is one, a description.
 *
 * @param string $title       Plain text title.
 * @param string $description Description, already safe HTML (term descriptions are).
 * @return string
 */
function blueworx_blog_list_head_html( $title, $description = '' ) {
	$out = '<header class="blueworx-page__head blueworx-list__head"><h1 class="blueworx-page__title">' . blueworx_frame_e( $title ) . '</h1>';

	if ( '' !== trim( (string) $description ) ) {
		$out .= '<div class="blueworx-list__description">' . $description . '</div>';
	}

	return $out . '</header>';
}

/**
 * What a list page says when it has nothing to list.
 *
 * @param bool $is_search Whether this was a search.
 * @param bool $is_404    Whether nothing lives at this address at all.
 * @return string
 */
function blueworx_blog_empty_html( $is_search, $is_404 = false ) {
	if ( $is_404 ) {
		$text = __( 'That page has moved or never existed.', 'blueworx-labs-wordpress' );
	} else {
		$text = $is_search
			? __( 'Nothing matched that search. Try a different word.', 'blueworx-labs-wordpress' )
			: __( 'No posts yet.', 'blueworx-labs-wordpress' );
	}

	return '<p class="blueworx-list__empty">' . blueworx_frame_e( $text ) . '</p>';
}

/**
 * The current post's by-line, built from WordPress.
 *
 * @return string
 */
function blueworx_blog_current_meta() {
	$categories = array();

	foreach ( (array) get_the_category() as $category ) {
		$categories[] = array(
			'label' => (string) $category->name,
			'href'  => (string) get_category_link( $category ),
		);
	}

	return blueworx_blog_meta_html(
		array(
			'date_iso'   => (string) get_the_date( DATE_W3C ),
			'date_text'  => (string) get_the_date(),
			'author'     => (string) get_the_author(),
			'author_url' => (string) get_author_posts_url( (int) get_the_author_meta( 'ID' ) ),
			'categories' => $categories,
		)
	);
}

/**
 * The current post as a card, built from WordPress.
 *
 * @return string
 */
function blueworx_blog_current_card() {
	return blueworx_blog_card_html(
		array(
			'title'   => get_the_title(),
			'href'    => get_permalink(),
			'excerpt' => has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( strip_shortcodes( get_the_content() ) ), 32 ),
			'image'   => has_post_thumbnail() ? get_the_post_thumbnail( null, 'large', array( 'alt' => '' ) ) : '',
			'meta'    => blueworx_blog_meta_html(
				array(
					'date_iso'  => (string) get_the_date( DATE_W3C ),
					'date_text' => (string) get_the_date(),
				)
			),
		)
	);
}

/**
 * The heading of the list page being viewed, built from WordPress.
 *
 * @return string
 */
function blueworx_blog_current_list_title() {
	$posts_page = (int) get_option( 'page_for_posts', 0 );

	return blueworx_blog_list_title(
		array(
			'is_404'           => is_404(),
			'is_home'          => is_home(),
			'is_search'        => is_search(),
			'is_archive'       => is_archive(),
			'posts_page_title' => is_home() && $posts_page > 0 ? get_the_title( $posts_page ) : '',
			'archive_title'    => is_archive() ? wp_strip_all_tags( get_the_archive_title() ) : '',
			'search_query'     => get_search_query(),
		)
	);
}

/**
 * Prints the pagination under a list, in the theme's classes.
 *
 * @return void
 */
function blueworx_blog_pagination() {
	the_posts_pagination(
		array(
			'mid_size'           => 1,
			'prev_text'          => __( 'Newer', 'blueworx-labs-wordpress' ),
			'next_text'          => __( 'Older', 'blueworx-labs-wordpress' ),
			'class'              => 'blueworx-pagination',
			'screen_reader_text' => __( 'Posts', 'blueworx-labs-wordpress' ),
		)
	);
}
