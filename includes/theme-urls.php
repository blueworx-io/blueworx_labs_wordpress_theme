<?php
/**
 * Gives files inside the theme a working address when code asks for it the
 * plugin way.
 *
 * Shared code copied into every BlueWorx plugin works out its own address with
 * plugin_dir_url( __FILE__ ). The admin design system's registrar does, and so
 * does the page editor's loader. From inside a theme that returns a broken
 * address under /wp-content/plugins/, and the admin loses its styles and icons.
 *
 * Fixing it here rather than in each copy matters: plugins load first, so on a
 * site with Clubhouse it is Clubhouse's copy of the registrar that declares the
 * functions, even when the theme's copy is the one that wins. Its code would
 * still call plugin_dir_url() on the theme's file. This filter catches that
 * too.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrites a plugins_url() result for a file that lives in the theme.
 *
 * @param string $url    The address WordPress worked out.
 * @param string $path   The path asked for, relative to the file's folder.
 * @param string $plugin The file the address is relative to.
 * @return string
 */
function blueworx_theme_plugins_url( $url, $path, $plugin ) {
	if ( ! is_string( $plugin ) || '' === $plugin ) {
		return $url;
	}

	$relative = blueworx_theme_relative_path( $plugin );
	if ( null === $relative ) {
		return $url;
	}

	$folder = dirname( $relative );
	$theme  = untrailingslashit( get_template_directory_uri() );
	$base   = ( '.' === $folder || '' === $folder ) ? $theme : $theme . '/' . $folder;

	if ( is_string( $path ) && '' !== $path ) {
		$base .= '/' . ltrim( $path, '/' );
	}

	return $base;
}
add_filter( 'plugins_url', 'blueworx_theme_plugins_url', 10, 3 );

/**
 * A file's path relative to the theme's folder, or null when it is elsewhere.
 *
 * Checks the real path as well as the one WordPress reports: PHP resolves
 * links in __FILE__, so a theme folder linked in from somewhere else (as on
 * the test site) reports its files under the link's target.
 *
 * @param string $file Absolute path to a file.
 * @return string|null Forward-slashed, no leading slash.
 */
function blueworx_theme_relative_path( $file ) {
	$file  = wp_normalize_path( $file );
	$roots = array( get_template_directory() );

	$real = realpath( get_template_directory() );
	if ( false !== $real ) {
		$roots[] = $real;
	}

	foreach ( $roots as $root ) {
		$root = trailingslashit( wp_normalize_path( $root ) );
		if ( 0 === stripos( $file, $root ) ) {
			return substr( $file, strlen( $root ) );
		}
	}

	return null;
}
