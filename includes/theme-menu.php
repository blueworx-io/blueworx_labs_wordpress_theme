<?php
/**
 * The Theme menu: the screens that shape the site itself.
 *
 * Sits directly below BlueWorx. The switches that turn these screens on stay on
 * BlueWorx > Enhancements; the screens themselves live here.
 *
 * The page slugs did not change when the screens moved, so every existing link
 * and bookmark (admin.php?page=blueworx-design, ...) still resolves. What did
 * change is each screen's hook suffix, which WordPress derives from the parent:
 * blueworx_page_* became theme_page_*. includes/admin-assets.php names them.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BLUEWORX_THEME_MENU = 'blueworx-theme';

/**
 * The screens under Theme, in menu order.
 *
 * SEO is listed while its switch is on and reachable but unlisted while it is
 * off, the same as Tracking codes, so the screen can explain itself.
 *
 * @return array[] Each with slug, title, capability, callback and listed.
 */
function blueworx_theme_menu_items() {
	$items = array(
		array(
			'slug'       => 'blueworx-seo',
			'title'      => __( 'SEO', 'blueworx-labs-wordpress' ),
			'capability' => 'manage_options',
			'callback'   => 'blueworx_render_seo_page',
			'listed'     => blueworx_feature_enabled( 'seo' ),
		),
		array(
			'slug'       => 'blueworx-design',
			'title'      => __( 'Design System', 'blueworx-labs-wordpress' ),
			'capability' => blueworx_design_capability(),
			'callback'   => 'blueworx_render_design_page',
			'listed'     => true,
		),
	);

	$items[] = array(
		'slug'       => 'blueworx-email-log',
		'title'      => __( 'Email logs', 'blueworx-labs-wordpress' ),
		'capability' => 'manage_options',
		'callback'   => 'blueworx_render_email_log_page',
		'listed'     => blueworx_feature_enabled( 'email_log' ),
	);

	if ( blueworx_feature_enabled( 'cache_manual' ) ) {
		$items[] = array(
			'slug'       => 'blueworx-cache',
			'title'      => __( 'Cache', 'blueworx-labs-wordpress' ),
			'capability' => 'manage_options',
			'callback'   => 'blueworx_render_cache_page',
			'listed'     => true,
		);
	}

	return $items;
}

/**
 * The first listed Theme screen the current user may open.
 *
 * @return array|null The item, or null when none is open to them.
 */
function blueworx_theme_menu_first_item() {
	foreach ( blueworx_theme_menu_items() as $item ) {
		if ( $item['listed'] && current_user_can( $item['capability'] ) ) {
			return $item;
		}
	}

	return null;
}

/**
 * Registers the Theme menu and its screens.
 *
 * The row is shown to anyone who can open at least one screen under it, so it
 * takes the capability of the first screen they can reach.
 *
 * The first submenu row repeats the parent's own slug. WordPress renames a
 * top-level row after its first submenu when the two differ, and the sidebar's
 * ordering, grouping and icon are all keyed on this slug; a row that changed
 * name with whichever screen happened to be first would lose all three. That
 * row is taken back out just before the menu is drawn, see
 * blueworx_theme_menu_drop_placeholder().
 *
 * Position 58.05 puts it between BlueWorx (58) and Guides (58.1) on a site
 * where the menu ordering filter is not running.
 *
 * @return void
 */
function blueworx_register_theme_menu() {
	$first = blueworx_theme_menu_first_item();

	if ( null === $first ) {
		return;
	}

	add_menu_page(
		esc_html__( 'Theme', 'blueworx-labs-wordpress' ),
		esc_html__( 'Theme', 'blueworx-labs-wordpress' ),
		$first['capability'],
		BLUEWORX_THEME_MENU,
		'blueworx_render_theme_menu_page',
		'dashicons-admin-customizer',
		58.05
	);

	add_submenu_page(
		BLUEWORX_THEME_MENU,
		esc_html__( 'Theme', 'blueworx-labs-wordpress' ),
		esc_html__( 'Theme', 'blueworx-labs-wordpress' ),
		$first['capability'],
		BLUEWORX_THEME_MENU,
		'blueworx_render_theme_menu_page'
	);

	foreach ( blueworx_theme_menu_items() as $item ) {
		add_submenu_page(
			$item['listed'] ? BLUEWORX_THEME_MENU : null,
			esc_html( $item['title'] ),
			esc_html( $item['title'] ),
			$item['capability'],
			$item['slug'],
			$item['callback']
		);
	}
}
add_action( 'admin_menu', 'blueworx_register_theme_menu' );

/**
 * Sends the bare Theme address on to the first screen the user can open.
 *
 * The sidebar never links here, but the address exists, so it should land
 * somewhere useful rather than on an empty page.
 *
 * @return void
 */
function blueworx_theme_menu_redirect() {
	$first = blueworx_theme_menu_first_item();

	if ( null === $first ) {
		return;
	}

	wp_safe_redirect( admin_url( 'admin.php?page=' . $first['slug'] ) );
	exit;
}
add_action( 'load-toplevel_page_' . BLUEWORX_THEME_MENU, 'blueworx_theme_menu_redirect' );

/**
 * Fallback body for the bare Theme address.
 *
 * Only reached if the redirect above could not run.
 *
 * @return void
 */
function blueworx_render_theme_menu_page() {
	echo '<div class="wrap"><h1>' . esc_html__( 'Theme', 'blueworx-labs-wordpress' ) . '</h1></div>';
}

/**
 * Takes the placeholder row out of the Theme submenu before it is drawn.
 *
 * Runs on the submenu_file filter, which WordPress applies after it has
 * finished working out parents and access, and immediately before it prints
 * the sidebar. With the placeholder gone the Theme row links straight to the
 * first real screen.
 *
 * @param string|null $submenu_file The current submenu file, passed through untouched.
 * @return string|null
 */
function blueworx_theme_menu_drop_placeholder( $submenu_file ) {
	global $submenu;

	if ( ! empty( $submenu[ BLUEWORX_THEME_MENU ] ) ) {
		foreach ( $submenu[ BLUEWORX_THEME_MENU ] as $index => $row ) {
			if ( isset( $row[2] ) && BLUEWORX_THEME_MENU === $row[2] ) {
				unset( $submenu[ BLUEWORX_THEME_MENU ][ $index ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Removing this file's own placeholder row.
			}
		}
	}

	return $submenu_file;
}
add_filter( 'submenu_file', 'blueworx_theme_menu_drop_placeholder' );
