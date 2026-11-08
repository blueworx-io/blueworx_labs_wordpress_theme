<?php
/**
 * Template Name: Legal page
 *
 * A privacy policy, cookies page or terms: a reading-width column with the
 * date it last changed at the top, so a reader knows how current it is.
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
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'blueworx-page blueworx-legal' ); ?>>
		<header class="blueworx-page__head">
			<h1 class="blueworx-page__title"><?php the_title(); ?></h1>
			<?php echo blueworx_legal_updated_html( get_the_modified_date() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where built. ?>
		</header>
		<div class="entry-content">
			<?php the_content(); ?>
		</div>
	</article>
	<?php
}

get_footer();
