<?php
/**
 * The top of every public page: the document's head, then the site header.
 *
 * The header itself is drawn by includes/frame.php, and only with Site styles
 * on; otherwise this is the bare document the theme always drew, so a site
 * that has not chosen the theme's look sees no change.
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
<?php if ( blueworx_frame_enabled() ) : ?>
<a class="blueworx-skip" href="#content"><?php esc_html_e( 'Skip to content', 'blueworx-labs-wordpress' ); ?></a>
<?php endif; ?>
<?php blueworx_frame_header(); ?>
<main id="content" class="blueworx-main">
