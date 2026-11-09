<?php
/**
 * A page: its title, then its content, inside the frame.
 *
 * The front page keeps its title out of the way, because a home page is led
 * by its own hero. See blueworx_frame_shows_title().
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

while ( have_posts() ) {
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'blueworx-page' ); ?>>
		<?php if ( blueworx_frame_shows_title( is_front_page() ) ) : ?>
			<header class="blueworx-page__head">
				<h1 class="blueworx-page__title"><?php the_title(); ?></h1>
			</header>
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
	</article>
	<?php
}

get_footer();
