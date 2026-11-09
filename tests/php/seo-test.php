<?php
/**
 * Basic SEO: what lands on the page, and when the theme stays out of it.
 *
 * Two things must be right. A page gets one full set of tags built from its
 * own details first and the site defaults second, with nothing written empty.
 * And the moment an SEO plugin is running, the theme writes nothing at all.
 *
 * Run with: php tests/php/seo-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';

$GLOBALS['hooks']   = array();
$GLOBALS['removed'] = array();
$GLOBALS['enabled'] = true;
$GLOBALS['admin']   = false;
$GLOBALS['front']   = true;
$GLOBALS['post']    = null;
$GLOBALS['images']  = array();
$GLOBALS['thumbs']  = array();

// phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.VariableComment.Missing, Generic.Files.OneObjectStructurePerFile.MultipleFound -- Test stubs mirror core signatures.

class WP_Post {
	public $ID           = 0;
	public $post_type    = 'page';
	public $post_title   = '';
	public $post_excerpt = '';
}

function add_action( $hook, $callback, $priority = 10 ) {
	$GLOBALS['hooks'][ $hook ] = array(
		'callback' => $callback,
		'priority' => $priority,
	);
	return true;
}

function remove_action( $hook, $callback ) {
	$GLOBALS['removed'][] = $hook . ':' . $callback;
	return true;
}

function blueworx_feature_enabled( $key ) {
	return 'seo' === $key && $GLOBALS['enabled'];
}

function is_admin() {
	return $GLOBALS['admin'];
}

function is_front_page() {
	return $GLOBALS['front'];
}

function is_home() {
	return false;
}

function is_category() {
	return false;
}

function is_tag() {
	return false;
}

function is_tax() {
	return false;
}

function is_post_type_archive() {
	return false;
}

function is_author() {
	return false;
}

function is_singular( $type = '' ) {
	if ( ! $GLOBALS['post'] ) {
		return false;
	}

	return '' === $type || $GLOBALS['post']->post_type === $type;
}

function get_queried_object() {
	return $GLOBALS['post'];
}

function get_the_title( $post ) {
	return $post->post_title;
}

function post_password_required( $post ) {
	return false;
}

function has_post_thumbnail( $post ) {
	return isset( $GLOBALS['thumbs'][ $post->ID ] );
}

function get_post_thumbnail_id( $post ) {
	return $GLOBALS['thumbs'][ $post->ID ];
}

function wp_get_attachment_image_src( $id, $size = 'thumbnail' ) {
	return isset( $GLOBALS['images'][ $id ] ) ? $GLOBALS['images'][ $id ] : false;
}

function wp_attachment_is_image( $id ) {
	return isset( $GLOBALS['images'][ $id ] );
}

function get_post_meta( $id, $key, $single = false ) {
	return 'Alt for ' . $id;
}

function wp_get_canonical_url( $post ) {
	return 'https://example.test/page-' . $post->ID . '/';
}

function wp_get_document_title() {
	return 'Example Site – Just another site';
}

function get_bloginfo( $show ) {
	return 'name' === $show ? 'Example Site' : 'Just another site';
}

function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

function absint( $value ) {
	return abs( (int) $value );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES );
}

function wp_unslash( $value ) {
	return $value;
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.VariableComment.Missing, Generic.Files.OneObjectStructurePerFile.MultipleFound

require dirname( __DIR__, 2 ) . '/includes/seo.php';

/**
 * Captures what the print function echoes.
 *
 * @return string Output.
 */
function blueworx_test_seo_head() {
	ob_start();
	blueworx_seo_print_head();
	return (string) ob_get_clean();
}

echo "The home page, with nothing set\n";

$head = blueworx_test_seo_head();

check( 'the description falls back to the tagline', false !== strpos( $head, '<meta name="description" content="Just another site" />' ), true );
check( 'the canonical link is the home address', false !== strpos( $head, '<link rel="canonical" href="https://example.test/" />' ), true );
check( 'the share address matches it', false !== strpos( $head, '<meta property="og:url" content="https://example.test/" />' ), true );
check( 'the type is website', false !== strpos( $head, '<meta property="og:type" content="website" />' ), true );
check( 'the site name is the site title', false !== strpos( $head, '<meta property="og:site_name" content="Example Site" />' ), true );
check( 'the title is the browser tab title', false !== strpos( $head, '<meta property="og:title" content="Example Site – Just another site" />' ), true );
check( 'no image tag is written without an image', false === strpos( $head, 'og:image' ), true );
check( 'nothing is written empty', false === strpos( $head, 'content=""' ), true );
check( 'the X card is the small one without an image', false !== strpos( $head, '<meta name="twitter:card" content="summary" />' ), true );
check( 'core\'s own canonical link is taken out', in_array( 'wp_head:rel_canonical', $GLOBALS['removed'], true ), true );

echo "\nThe home page, with the site defaults saved\n";

$GLOBALS['images'][7] = array( 'https://example.test/share.png', 1200, 630 );

blueworx_seo_save(
	array(
		'blueworx_seo_site_name'   => ' Example <b>Co</b> ',
		'blueworx_seo_description' => "Hand-made things,\n  sent anywhere.",
		'blueworx_seo_image'       => '7',
	)
);

$head = blueworx_test_seo_head();

check( 'the site name is stored as plain text', get_option( 'blueworx_seo_site_name' ), 'Example Co' );
check( 'the description is flattened to one line', get_option( 'blueworx_seo_description' ), 'Hand-made things, sent anywhere.' );
check( 'the default description is used', false !== strpos( $head, '<meta property="og:description" content="Hand-made things, sent anywhere." />' ), true );
check( 'the site name is used', false !== strpos( $head, '<meta property="og:site_name" content="Example Co" />' ), true );
check( 'the default image is used', false !== strpos( $head, '<meta property="og:image" content="https://example.test/share.png" />' ), true );
check( 'with its width', false !== strpos( $head, '<meta property="og:image:width" content="1200" />' ), true );
check( 'and its height', false !== strpos( $head, '<meta property="og:image:height" content="630" />' ), true );
check( 'the X card is the large one', false !== strpos( $head, '<meta name="twitter:card" content="summary_large_image" />' ), true );
check( 'the X image is set', false !== strpos( $head, '<meta name="twitter:image" content="https://example.test/share.png" />' ), true );

blueworx_seo_save( array( 'blueworx_seo_image' => '99' ) );
check( 'an image that is not in the library is not kept', get_option( 'blueworx_seo_image' ), 0 );

blueworx_seo_save(
	array(
		'blueworx_seo_description' => 'Hand-made things, sent anywhere.',
		'blueworx_seo_image'       => '7',
	)
);

echo "\nA post with its own excerpt and featured image\n";

$GLOBALS['front']              = false;
$GLOBALS['post']               = new WP_Post();
$GLOBALS['post']->ID           = 12;
$GLOBALS['post']->post_type    = 'post';
$GLOBALS['post']->post_title   = 'Opening "night"';
$GLOBALS['post']->post_excerpt = 'Doors at seven.';
$GLOBALS['images'][8]          = array( 'https://example.test/night.jpg', 800, 600 );
$GLOBALS['thumbs'][12]         = 8;

$head = blueworx_test_seo_head();

check( 'the title is the post\'s own, safely quoted', false !== strpos( $head, '<meta property="og:title" content="Opening &quot;night&quot;" />' ), true );
check( 'the description is the excerpt', false !== strpos( $head, '<meta name="description" content="Doors at seven." />' ), true );
check( 'the image is the featured image', false !== strpos( $head, '<meta property="og:image" content="https://example.test/night.jpg" />' ), true );
check( 'the type is article', false !== strpos( $head, '<meta property="og:type" content="article" />' ), true );
check( 'the canonical link is the post\'s address', false !== strpos( $head, '<link rel="canonical" href="https://example.test/page-12/" />' ), true );

echo "\nA page with neither falls back to the defaults\n";

$GLOBALS['post']->post_type    = 'page';
$GLOBALS['post']->post_excerpt = '';
unset( $GLOBALS['thumbs'][12] );

$head = blueworx_test_seo_head();

check( 'the default description', false !== strpos( $head, '<meta name="description" content="Hand-made things, sent anywhere." />' ), true );
check( 'the default image', false !== strpos( $head, '<meta property="og:image" content="https://example.test/share.png" />' ), true );
check( 'the type is website', false !== strpos( $head, '<meta property="og:type" content="website" />' ), true );

echo "\nNothing is written when it should not be\n";

$GLOBALS['enabled'] = false;
check( 'switched off', blueworx_test_seo_head(), '' );

$GLOBALS['enabled'] = true;
$GLOBALS['admin']   = true;
check( 'on an admin screen', blueworx_test_seo_head(), '' );

$GLOBALS['admin']                          = false;
$GLOBALS['filters']['blueworx_seo_plugin'] = function () {
	return 'Another plugin';
};
check( 'when a filter names another SEO plugin', blueworx_test_seo_head(), '' );
unset( $GLOBALS['filters']['blueworx_seo_plugin'] );

check( 'no SEO plugin is seen before one is running', blueworx_seo_plugin(), '' );
check( 'so the theme is writing the tags', '' !== blueworx_test_seo_head(), true );

define( 'SURERANK_VERSION', '1.0.0' );

check( 'SureRank is recognised', blueworx_seo_plugin(), 'SureRank' );
check( 'and the theme writes nothing', blueworx_test_seo_head(), '' );

echo "\nThe hook is in place\n";

check( 'the tags are hooked into the head', isset( $GLOBALS['hooks']['wp_head']['callback'] ) ? $GLOBALS['hooks']['wp_head']['callback'] : null, 'blueworx_seo_print_head' );
check( 'ahead of core\'s canonical link', $GLOBALS['hooks']['wp_head']['priority'] < 10, true );

finish();
