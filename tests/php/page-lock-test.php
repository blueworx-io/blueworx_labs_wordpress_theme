<?php
/**
 * Locked pages: the shape of a block tree, and what counts as changing it.
 *
 * A locked page may still be written on. The one thing a client must not be
 * able to do is change which blocks are there, in what order, nested how, and
 * that has to be decided the same way for the editor and for the REST API.
 * These checks pin the parser that decision rests on.
 *
 * Run with: php tests/php/page-lock-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function blueworx_feature_enabled( $key ) {
	return false;
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/page-lock.php';

$two = '<!-- wp:paragraph --><p>One</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->';

check(
	'two paragraphs read as two core blocks',
	blueworx_page_lock_shape( $two ),
	array( array( 'core/paragraph', array() ), array( 'core/paragraph', array() ) )
);

$nested = '<!-- wp:group {"align":"full","backgroundColor":"surface"} --><div class="wp-block-group"><!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3>Hi</h3><!-- /wp:heading --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:spacer {"height":"40px"} /--></div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->';

check(
	'nesting, attributes and a self-closing block all read correctly',
	blueworx_page_lock_shape( $nested ),
	array(
		array(
			'core/group',
			array(
				array(
					'core/columns',
					array(
						array( 'core/column', array( array( 'core/heading', array() ) ) ),
						array( 'core/column', array( array( 'core/spacer', array() ) ) ),
					),
				),
			),
		),
	)
);

check(
	'a namespaced block keeps its namespace',
	blueworx_page_lock_shape( '<!-- wp:brodfest/hero {"heading":"Hi"} /-->' ),
	array( array( 'brodfest/hero', array() ) )
);

check( 'plain text has no shape', blueworx_page_lock_shape( '<p>Classic content</p>' ), array() );

$text_changed = '<!-- wp:paragraph --><p>One, reworded</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->';
check( 'changing the words is not a change of shape', blueworx_page_lock_structure_changed( $two, $text_changed ), false );

$attr_changed = '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">One</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->';
check( 'changing an attribute is not a change of shape', blueworx_page_lock_structure_changed( $two, $attr_changed ), false );

$added = $two . '<!-- wp:paragraph --><p>Three</p><!-- /wp:paragraph -->';
check( 'adding a block is a change of shape', blueworx_page_lock_structure_changed( $two, $added ), true );

$removed = '<!-- wp:paragraph --><p>One</p><!-- /wp:paragraph -->';
check( 'removing a block is a change of shape', blueworx_page_lock_structure_changed( $two, $removed ), true );

$swapped = '<!-- wp:heading --><h2>One</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->';
check( 'swapping one block for another is a change of shape', blueworx_page_lock_structure_changed( $two, $swapped ), true );

$nested_image = str_replace( '<!-- wp:spacer {"height":"40px"} /-->', '<!-- wp:spacer {"height":"40px"} /--><!-- wp:image /-->', $nested );
check( 'adding a block deep inside a column is a change of shape', blueworx_page_lock_structure_changed( $nested, $nested_image ), true );

$nested_text = str_replace( '<h3>Hi</h3>', '<h3>Hello there</h3>', $nested );
check( 'rewording a nested heading is not a change of shape', blueworx_page_lock_structure_changed( $nested, $nested_text ), false );

$reordered = '<!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>One</p><!-- /wp:paragraph -->';
check( 'two paragraphs swapped read the same, because shape is names not words', blueworx_page_lock_structure_changed( $two, $reordered ), false );

finish();
