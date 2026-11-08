<?php
/**
 * The page every public request falls back to when no closer template fits.
 *
 * Pages have page.php. Everything else lands here: it draws whatever the loop
 * holds, one article after another, inside the frame.
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

if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<h1><?php the_title(); ?></h1>
			<?php the_content(); ?>
		</article>
		<?php
	}
} else {
	?>
	<h1><?php echo is_404() ? esc_html__( 'Page not found', 'blueworx-labs-wordpress' ) : esc_html__( 'Nothing here yet', 'blueworx-labs-wordpress' ); ?></h1>
	<?php
}

get_footer();
