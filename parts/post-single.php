<?php
/**
 * One post: its by-line, title, picture and content, then its tags and the
 * way to the posts either side of it.
 *
 * Used by single.php, and by index.php for any single post type without a
 * template of its own.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

while ( have_posts() ) {
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'blueworx-post' ); ?>>
		<header class="blueworx-page__head blueworx-post__head">
			<?php echo blueworx_blog_current_meta(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts. ?>
			<h1 class="blueworx-page__title"><?php the_title(); ?></h1>
		</header>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="blueworx-post__image"><?php the_post_thumbnail( 'large' ); ?></figure>
		<?php endif; ?>
		<div class="entry-content">
			<?php
			the_content();
			wp_link_pages(
				array(
					'before' => '<nav class="blueworx-page__pages" aria-label="' . esc_attr__( 'Pages', 'blueworx-labs-wordpress' ) . '">',
					'after'  => '</nav>',
				)
			);
			?>
		</div>
		<?php
		the_tags( '<p class="blueworx-post__tags">', '', '</p>' );

		the_post_navigation(
			array(
				'prev_text' => '<span class="blueworx-post__nav-label">' . esc_html__( 'Previous', 'blueworx-labs-wordpress' ) . '</span><span class="blueworx-post__nav-title">%title</span>',
				'next_text' => '<span class="blueworx-post__nav-label">' . esc_html__( 'Next', 'blueworx-labs-wordpress' ) . '</span><span class="blueworx-post__nav-title">%title</span>',
				'class'     => 'blueworx-post__nav',
			)
		);
		?>
	</article>
	<?php
}
