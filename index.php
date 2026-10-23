<?php
/**
 * The page frame every public page falls back to.
 *
 * Deliberately bare for now. A client plugin such as Clubhouse draws its own
 * pages, and the theme's own header, footer and page templates move in from
 * Clubhouse in a later release. Until then this keeps any other page working:
 * WordPress's head and footer still run, so plugins can add what they need.
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
}
?>
</main>
<?php wp_footer(); ?>
</body>
</html>
