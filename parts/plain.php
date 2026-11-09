<?php
/**
 * The bare page, for when the theme's own code has not loaded.
 *
 * With the 1.x plugin still active the theme stays dormant (see
 * functions.php), so none of its template functions exist. Every template
 * checks for them and falls back to this: WordPress's head and footer still
 * run, and the page's content is drawn plainly, so a site mid-migration keeps
 * working.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<main id="content">
<?php
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
?>
</main>
<?php wp_footer(); ?>
</body>
</html>
