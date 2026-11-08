<?php
/**
 * Blog and post pages: every piece of markup, built pure.
 *
 * Run with: php tests/php/blog-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

require __DIR__ . '/stubs.php';

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

require __DIR__ . '/../../includes/frame.php';
require __DIR__ . '/../../includes/blog.php';

/**
 * A yes-or-no check.
 *
 * @param string $label What is being checked.
 * @param bool   $cond  Whether it held.
 * @return void
 */
function ok( $label, $cond ) {
	check( $label, (bool) $cond, true );
}

// The heading of a list page.
check(
	'the posts page is named after its page',
	'News',
	blueworx_blog_list_title(
		array(
			'is_home'          => true,
			'posts_page_title' => 'News',
		)
	)
);
check( 'a front page of posts is called Blog', 'Blog', blueworx_blog_list_title( array( 'is_home' => true ) ) );
check(
	'an archive is named after itself',
	'Fixtures',
	blueworx_blog_list_title(
		array(
			'is_archive'    => true,
			'archive_title' => 'Fixtures',
		)
	)
);
check(
	'an archive with no name falls back to Blog',
	'Blog',
	blueworx_blog_list_title(
		array(
			'is_archive'    => true,
			'archive_title' => '',
		)
	)
);
check(
	'a search says what was searched for',
	'Search results for “rackets”',
	blueworx_blog_list_title(
		array(
			'is_search'    => true,
			'search_query' => ' rackets ',
		)
	)
);
check( 'the archive prefix is dropped', '', blueworx_blog_archive_title_prefix() );

// The by-line.
$meta = blueworx_blog_meta_html(
	array(
		'date_iso'   => '2026-11-08T10:00:00+00:00',
		'date_text'  => '8 November 2026',
		'author'     => 'Pat & Co',
		'author_url' => 'http://x/author/pat/',
		'categories' => array(
			array(
				'label' => 'News',
				'href'  => 'http://x/category/news/',
			),
			array(
				'label' => '',
				'href'  => 'http://x/category/empty/',
			),
		),
	)
);
ok( 'the date is a time element', false !== strpos( $meta, '<time class="blueworx-post__date" datetime="2026-11-08T10:00:00+00:00">8 November 2026</time>' ) );
ok( 'the author links to their posts, escaped', false !== strpos( $meta, '<span class="blueworx-post__author"><a href="http://x/author/pat/">Pat &amp; Co</a></span>' ) );
ok( 'categories are links', false !== strpos( $meta, '<span class="blueworx-post__categories"><a class="blueworx-post__category" href="http://x/category/news/">News</a></span>' ) );
ok( 'a category with no label is skipped', false === strpos( $meta, 'empty' ) );
ok( 'an author with no address is plain text', false !== strpos( blueworx_blog_meta_html( array( 'author' => 'Pat' ) ), '<span class="blueworx-post__author">Pat</span>' ) );
check( 'nothing to say means no by-line', '', blueworx_blog_meta_html( array() ) );

// A card.
$card = blueworx_blog_card_html(
	array(
		'title'   => 'Strings & grips',
		'href'    => 'http://x/strings/',
		'excerpt' => 'All about <them>',
		'image'   => '<img src="http://x/a.jpg">',
		'meta'    => '<p class="blueworx-post__meta">META</p>',
	)
);
ok( 'the image links to the post and is skipped by readers', false !== strpos( $card, '<a class="blueworx-card__image" href="http://x/strings/" tabindex="-1" aria-hidden="true"><img src="http://x/a.jpg"></a>' ) );
ok( 'the by-line comes before the title', false !== strpos( $card, '<div class="blueworx-card__body"><p class="blueworx-post__meta">META</p><h2 class="blueworx-card__title"><a href="http://x/strings/">Strings &amp; grips</a></h2>' ) );
ok( 'the excerpt is escaped', false !== strpos( $card, '<p class="blueworx-card__excerpt">All about &lt;them&gt;</p>' ) );
$bare = blueworx_blog_card_html( array( 'href' => 'http://x/' ) );
ok( 'no image means no image link', false === strpos( $bare, 'blueworx-card__image' ) );
ok( 'no excerpt means no excerpt', false === strpos( $bare, 'blueworx-card__excerpt' ) );
ok( 'an untitled post is still a link', false !== strpos( $bare, '>(Untitled)</a>' ) );

// The list head and the empty state.
check( 'a list head with no description', '<header class="blueworx-page__head blueworx-list__head"><h1 class="blueworx-page__title">Blog</h1></header>', blueworx_blog_list_head_html( 'Blog' ) );
ok( 'a description is passed through as HTML', false !== strpos( blueworx_blog_list_head_html( 'News', '<p>All the news.</p>' ), '<div class="blueworx-list__description"><p>All the news.</p></div>' ) );
ok( 'an empty search says so', false !== strpos( blueworx_blog_empty_html( true ), 'Nothing matched that search' ) );
ok( 'an empty list says so', false !== strpos( blueworx_blog_empty_html( false ), 'No posts yet.' ) );

finish();
