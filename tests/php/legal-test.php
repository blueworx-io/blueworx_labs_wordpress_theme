<?php
/**
 * Legal pages and the not-found page: the pure parts.
 *
 * Run with: php tests/php/legal-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

require __DIR__ . '/stubs.php';

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_shortcode( $tag, $callback ) {}

require __DIR__ . '/../../includes/frame.php';
require __DIR__ . '/../../includes/legal.php';

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

// Starter text.
$privacy = blueworx_legal_content( 'privacy', 'Fixture & Co', 'hello@example.com' );
ok( 'the privacy policy names the site, escaped', false !== strpos( $privacy, 'Fixture &amp; Co respects your privacy' ) );
ok( 'the privacy policy gives the contact address', false !== strpos( $privacy, 'hello@example.com' ) );
ok( 'the privacy policy is block markup', false !== strpos( $privacy, '<!-- wp:heading -->' ) );
ok( 'the cookies page carries the cookie list shortcode', false !== strpos( blueworx_legal_content( 'cookies', 'Fixture', '' ), '<!-- wp:shortcode -->[blueworx_cookie_list]<!-- /wp:shortcode -->' ) );
ok( 'no address means a generic line', false !== strpos( blueworx_legal_content( 'terms', 'Fixture', '' ), "through the site's contact details" ) );
check( 'an unknown kind has no text', '', blueworx_legal_content( 'nope', 'Fixture', '' ) );
check( 'three kinds', array( 'privacy', 'cookies', 'terms' ), array_keys( blueworx_legal_kinds() ) );

// Footer links: only published pages, in order, never twice.
$pages = array(
	'privacy' => array(
		'status' => 'publish',
		'title'  => 'Privacy policy',
		'url'    => 'http://x/privacy-policy/',
	),
	'cookies' => array(
		'status' => 'publish',
		'title'  => 'Cookies',
		'url'    => 'http://x/cookies/',
	),
	'terms'   => array(
		'status' => 'draft',
		'title'  => 'Terms',
		'url'    => 'http://x/terms/',
	),
);
$links = blueworx_legal_merge_links(
	array(
		array(
			'label' => 'Privacy policy',
			'href'  => 'http://x/privacy-policy/',
		),
	),
	$pages
);
check( 'the privacy policy the frame added is not repeated, the draft is left out', array( 'Privacy policy', 'Cookies' ), array_column( $links, 'label' ) );
check( 'with nothing from the frame, every published page is linked in order', array( 'http://x/privacy-policy/', 'http://x/cookies/' ), array_column( blueworx_legal_merge_links( array(), $pages ), 'href' ) );
check( 'no pages means the links are untouched', array(), blueworx_legal_merge_links( array(), array() ) );

// The cookie table.
$table = blueworx_cookie_list_html(
	array(
		array(
			'name'     => '_ga',
			'provider' => 'Google',
			'purpose'  => 'Counts <visits>',
			'duration' => 'Two years',
			'category' => 'Analytics',
		),
		array( 'name' => '' ),
	)
);
ok( 'a cookie is a row with its name in code', false !== strpos( $table, '<td class="blueworx-cookies__name"><code>_ga</code></td><td>Google</td><td>Counts &lt;visits&gt;</td><td>Two years</td><td>Analytics</td>' ) );
ok( 'the table has its headings', false !== strpos( $table, '<th scope="col">Cookie</th><th scope="col">Set by</th>' ) );
ok( 'a cookie with no name is skipped', 1 === substr_count( $table, '<tr>' ) - 1 );
ok( 'no cookies says so', false !== strpos( blueworx_cookie_list_html( array() ), 'sets no cookies of its own' ) );

// The default declarations, off WordPress: just core's own.
check( 'with no tracking, only core cookies are listed', array( 'wordpress_test_cookie', 'wordpress_logged_in_*, wordpress_sec_*', 'wp-settings-*' ), array_column( blueworx_cookie_declarations(), 'name' ) );

// Last updated.
check( 'the updated line', '<p class="blueworx-legal__updated">Last updated 8 November 2026</p>', blueworx_legal_updated_html( '8 November 2026' ) );
check( 'no date means no line', '', blueworx_legal_updated_html( '' ) );

// Not found.
$nf = blueworx_404_html(
	'http://x/',
	array(
		array(
			'label' => 'About & more',
			'href'  => 'http://x/about/',
		),
		array(
			'label' => '',
			'href'  => 'http://x/none/',
		),
	),
	'<form class="search-form">S</form>'
);
ok( 'the not-found page says so', false !== strpos( $nf, '<h1 class="blueworx-page__title">Page not found</h1>' ) );
ok( 'the search form is in it', false !== strpos( $nf, '<div class="blueworx-404__search"><form class="search-form">S</form></div>' ) );
ok( 'home first, then the pages, escaped', false !== strpos( $nf, '<a class="blueworx-btn blueworx-btn--primary" href="http://x/">Back to the home page</a><a class="blueworx-btn blueworx-btn--secondary" href="http://x/about/">About &amp; more</a></nav>' ) );
ok( 'a page with no label is skipped', false === strpos( $nf, 'none' ) );
ok( 'no search form means no search box', false === strpos( blueworx_404_html( 'http://x/', array(), '' ), 'blueworx-404__search' ) );

finish();
