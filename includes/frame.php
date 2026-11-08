<?php
/**
 * The page frame: the header and footer around every public page.
 *
 * One header, one footer, on every page the theme draws, so a site looks like
 * one site whether the page is a plain WordPress page, a blog post or a page a
 * plugin fills. The header carries the brand, the primary menu and up to a
 * couple of actions; the footer the brand again, a second menu, the social
 * links from the Design screen, the legal pages and the copyright line.
 *
 * Drawn only with the Site styles switch on, for the same reason the base
 * styles are: a site that had the theme before this existed must not grow a
 * header the day it updates. New sites start with it on. A plugin that draws
 * its own chrome, the way Clubhouse does, says so through the
 * blueworx_site_frame filter and the frame steps aside.
 *
 * Every piece of markup is built by a pure function that takes what it draws
 * as arguments, so tests/php/site-frame-test.php can check it without a site.
 * Nothing here styles anything: assets/css/frame.css does, from the named
 * styles alone, so changing the design on BlueWorx > Design restyles the frame
 * with everything else.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The width below which the menu folds into the drawer. Mirrored in frame.css. */
define( 'BLUEWORX_FRAME_PHONE_WIDTH', 900 );

/**
 * Escapes text for HTML, decoding first so escaping twice changes nothing.
 *
 * The same rule the store shell follows: some of what arrives here is built by
 * WordPress's own helpers with ampersands already written as entities.
 *
 * @param string $value Value to escape.
 * @return string
 */
function blueworx_frame_e( $value ) {
	return htmlspecialchars( html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), ENT_QUOTES, 'UTF-8' );
}

/**
 * Whether the frame belongs on this request.
 *
 * With Site styles on, unless a plugin that draws its own header and footer
 * has said so. Clubhouse's chrome is recognised directly, because that plugin
 * predates the filter.
 *
 * @return bool
 */
function blueworx_frame_enabled() {
	if ( ! function_exists( 'blueworx_feature_enabled' ) || ! blueworx_feature_enabled( 'site_styles' ) ) {
		return false;
	}

	$enabled = true;

	if ( class_exists( 'Blueworx_Clubhouse_External_Chrome' ) && is_callable( array( 'Blueworx_Clubhouse_External_Chrome', 'applies' ) ) && Blueworx_Clubhouse_External_Chrome::applies() ) {
		$enabled = false;
	}

	/**
	 * Whether the theme draws its header and footer on this request.
	 *
	 * A plugin that renders a complete page of its own returns false.
	 *
	 * @param bool $enabled True to draw the frame.
	 */
	return (bool) apply_filters( 'blueworx_site_frame', $enabled );
}

/**
 * Registers the two menu locations.
 *
 * @return void
 */
function blueworx_frame_register_menus() {
	register_nav_menus(
		array(
			'primary' => __( 'Header menu', 'blueworx-labs-wordpress' ),
			'footer'  => __( 'Footer menu', 'blueworx-labs-wordpress' ),
		)
	);
}
add_action( 'after_setup_theme', 'blueworx_frame_register_menus' );

/**
 * Loads the frame's stylesheet and the small script behind the phone menu.
 *
 * After the design system's own enqueue at 5, which the stylesheet depends on.
 *
 * @return void
 */
function blueworx_frame_enqueue() {
	if ( ! blueworx_frame_enabled() ) {
		return;
	}

	wp_enqueue_style( 'blueworx-frame', BLUEWORX_LABS_URL . 'assets/css/frame.css', array( 'blueworx-design' ), blueworx_get_admin_asset_version( 'assets/css/frame.css' ) );
	wp_enqueue_script( 'blueworx-frame', BLUEWORX_LABS_URL . 'assets/js/frame.js', array(), blueworx_get_admin_asset_version( 'assets/js/frame.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'blueworx_frame_enqueue', 6 );

/**
 * Marks the body while the frame is drawn, so a plugin's styles can tell.
 *
 * @param array $classes Body classes.
 * @return array
 */
function blueworx_frame_body_class( $classes ) {
	if ( blueworx_frame_enabled() ) {
		$classes[] = 'blueworx-frame';
	}

	return $classes;
}
add_filter( 'body_class', 'blueworx_frame_body_class' );

/**
 * Drops WordPress's per-item ids from the theme's menus.
 *
 * The header menu is drawn twice, once in the bar and once in the phone
 * drawer, and an id may only appear once in a document.
 *
 * @param string   $id   The id WordPress would print.
 * @param WP_Post  $item The menu item.
 * @param stdClass $args The menu's arguments.
 * @return string
 */
function blueworx_frame_menu_item_id( $id, $item, $args ) {
	return isset( $args->theme_location ) && in_array( $args->theme_location, array( 'primary', 'footer' ), true ) ? '' : $id;
}
add_filter( 'nav_menu_item_id', 'blueworx_frame_menu_item_id', 10, 3 );

/**
 * The brand: the site's logo when it has one, its name when it has not.
 *
 * Always a link home. The logo image carries the site's name as its text, so
 * a screen reader hears the same thing either way.
 *
 * @param string $name     Site name.
 * @param string $logo_url Logo image address, or '' for none.
 * @param string $home_url Address to link to.
 * @return string
 */
function blueworx_frame_brand_html( $name, $logo_url, $home_url ) {
	$name     = trim( (string) $name );
	$logo_url = trim( (string) $logo_url );

	$body = '' !== $logo_url
		? '<img class="blueworx-brand__logo" src="' . blueworx_frame_e( $logo_url ) . '" alt="' . blueworx_frame_e( $name ) . '">'
		: '<span class="blueworx-brand__name">' . blueworx_frame_e( $name ) . '</span>';

	return '<a class="blueworx-brand" href="' . blueworx_frame_e( $home_url ) . '" rel="home">' . $body . '</a>';
}

/**
 * The site's logo for the header, or '' when none is set.
 *
 * The custom logo WordPress keeps, which the Design screen also shows as the
 * site's main logo. The site icon is not used here: it is a square mark made
 * for a browser tab, and the header has room for the full logo.
 *
 * @return string
 */
function blueworx_frame_logo_url() {
	$logo_id = (int) get_theme_mod( 'custom_logo', 0 );

	if ( $logo_id <= 0 ) {
		return '';
	}

	$url = wp_get_attachment_image_url( $logo_id, 'medium' );

	return is_string( $url ) ? trim( $url ) : '';
}

/**
 * A menu, as a list of links.
 *
 * Nested lists for sub-menus, with the classes WordPress gives them, so the
 * same markup serves the bar, the drawer and the footer.
 *
 * @param string $location   Menu location.
 * @param string $list_class      Class for the list.
 * @param string $aria_label What the nav is for.
 * @return string
 */
function blueworx_frame_menu_html( $location, $list_class, $aria_label ) {
	$menu = wp_nav_menu(
		array(
			'theme_location' => $location,
			'container'      => false,
			'menu_class'     => $list_class,
			'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
			'fallback_cb'    => false,
			'echo'           => false,
			'depth'          => 'footer' === $location ? 1 : 2,
		)
	);

	if ( ! is_string( $menu ) || '' === trim( $menu ) ) {
		$menu = blueworx_frame_pages_html( blueworx_frame_fallback_pages(), $list_class );
	}

	if ( '' === $menu ) {
		return '';
	}

	return '<nav class="blueworx-nav" aria-label="' . blueworx_frame_e( $aria_label ) . '">' . $menu . '</nav>';
}

/**
 * The pages a site with no menu yet shows: its top-level published pages.
 *
 * Never the store's own pages: checkout, the thank-you page and the dashboard
 * are places a visitor is sent, not places to browse to.
 *
 * @return array<int,array{label:string,href:string,current:bool}>
 */
function blueworx_frame_fallback_pages() {
	$pages = get_pages(
		array(
			'parent'      => 0,
			'sort_column' => 'menu_order,post_title',
			'number'      => 8,
			'post_status' => 'publish',
		)
	);

	if ( ! is_array( $pages ) ) {
		return array();
	}

	$front = (int) get_option( 'page_on_front', 0 );
	$store = array();

	if ( function_exists( 'blueworx_store_pages' ) && function_exists( 'blueworx_store_page_id' ) ) {
		foreach ( array_keys( blueworx_store_pages() ) as $key ) {
			$store[] = (int) blueworx_store_page_id( $key );
		}
	}

	$current = (int) get_queried_object_id();
	$out     = array();

	foreach ( $pages as $page ) {
		$id = (int) $page->ID;

		if ( $id === $front || in_array( $id, $store, true ) ) {
			continue;
		}

		$out[] = array(
			'label'   => (string) get_the_title( $page ),
			'href'    => (string) get_permalink( $page ),
			'current' => $id === $current,
		);
	}

	return $out;
}

/**
 * A list of pages as menu markup, with the classes a real menu gets.
 *
 * @param array<int,array{label:string,href:string,current?:bool}> $pages Pages.
 * @param string                                                   $list_class Class for the list.
 * @return string '' when there are no pages.
 */
function blueworx_frame_pages_html( $pages, $list_class ) {
	$items = '';

	foreach ( (array) $pages as $page ) {
		$label = trim( (string) ( $page['label'] ?? '' ) );
		$href  = trim( (string) ( $page['href'] ?? '' ) );

		if ( '' === $label || '' === $href ) {
			continue;
		}

		$current = ! empty( $page['current'] );
		$items  .= '<li class="menu-item' . ( $current ? ' current-menu-item' : '' ) . '">'
			. '<a href="' . blueworx_frame_e( $href ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>'
			. blueworx_frame_e( $label ) . '</a></li>';
	}

	return '' === $items ? '' : '<ul class="' . blueworx_frame_e( $list_class ) . '">' . $items . '</ul>';
}

/**
 * The header's actions: a button or two at the right of the bar.
 *
 * The theme offers one of its own, a link to the member area on a site with
 * a shop; a plugin adds more through the filter, or takes that one away.
 *
 * @return array<int,array{label:string,href:string,variant:string}>
 */
function blueworx_frame_actions() {
	$actions = array();

	if ( function_exists( 'blueworx_feature_enabled' ) && blueworx_feature_enabled( 'store_pages' )
		&& function_exists( 'blueworx_store_surecart_active' ) && blueworx_store_surecart_active()
		&& function_exists( 'blueworx_store_page_url' ) ) {
		$dashboard = blueworx_store_page_url( 'dashboard' );

		if ( '' !== $dashboard ) {
			$actions[] = array(
				'label'   => is_user_logged_in() ? __( 'My account', 'blueworx-labs-wordpress' ) : __( 'Sign in', 'blueworx-labs-wordpress' ),
				'href'    => is_user_logged_in() ? $dashboard : wp_login_url( $dashboard ),
				'variant' => 'secondary',
			);
		}
	}

	/**
	 * Filters the header's actions.
	 *
	 * @param array $actions Each a label, an href and a variant: 'primary' or 'secondary'.
	 */
	return (array) apply_filters( 'blueworx_header_actions', $actions );
}

/**
 * The actions as buttons.
 *
 * @param array<int,array{label:string,href:string,variant?:string}> $actions Actions.
 * @param string                                                     $extra   Extra class on each button, e.g. for the drawer.
 * @return string
 */
function blueworx_frame_actions_html( $actions, $extra = '' ) {
	$out = '';

	foreach ( (array) $actions as $action ) {
		$label = trim( (string) ( $action['label'] ?? '' ) );
		$href  = trim( (string) ( $action['href'] ?? '' ) );

		if ( '' === $label || '' === $href ) {
			continue;
		}

		$variant    = 'primary' === ( $action['variant'] ?? '' ) ? 'primary' : 'secondary';
		$list_class = 'blueworx-btn blueworx-btn--' . $variant . ( '' !== $extra ? ' ' . $extra : '' );
		$out       .= '<a class="' . blueworx_frame_e( $list_class ) . '" href="' . blueworx_frame_e( $href ) . '">' . blueworx_frame_e( $label ) . '</a>';
	}

	return $out;
}

/**
 * The header, as markup.
 *
 * Pure: the brand, the menu and the actions arrive built.
 *
 * On a phone the menu and the actions move into a drawer under a menu button.
 * The drawer is a details element, so it opens with no script at all; the
 * script only closes it when the visitor taps elsewhere or presses Escape.
 *
 * @param string $brand   From blueworx_frame_brand_html().
 * @param string $menu    From blueworx_frame_menu_html(), or ''.
 * @param string $actions From blueworx_frame_actions_html(), for the bar.
 * @param string $drawer  The same actions for the drawer, or ''.
 * @return string
 */
function blueworx_frame_header_html( $brand, $menu, $actions, $drawer = '' ) {
	$drawer_body = $drawer . $menu;

	$out = '<header class="blueworx-header"><div class="blueworx-header__inner">'
		. $brand
		. '<div class="blueworx-header__nav">' . $menu . '</div>'
		. '<div class="blueworx-header__actions">' . $actions;

	if ( '' !== trim( $drawer_body ) ) {
		$out .= '<details class="blueworx-menu">'
			. '<summary class="blueworx-menu__button" aria-label="' . blueworx_frame_e( __( 'Menu', 'blueworx-labs-wordpress' ) ) . '">'
			. '<span class="blueworx-menu__bars" aria-hidden="true"></span></summary>'
			. '<div class="blueworx-menu__drawer">' . $drawer_body . '</div>'
			. '</details>';
	}

	return $out . '</div></div></header>';
}

/**
 * The social links the Design screen holds, as label and address pairs.
 *
 * Text, not icons: the names are short, every reader knows them, and a row
 * of words needs no icon file to ship or to describe.
 *
 * @param array<string,string> $social Addresses keyed by network.
 * @param array<string,string> $labels Labels keyed by network.
 * @return array<int,array{label:string,href:string}>
 */
function blueworx_frame_social_links( $social, $labels ) {
	$out = array();

	foreach ( (array) $labels as $network => $label ) {
		$href = isset( $social[ $network ] ) ? trim( (string) $social[ $network ] ) : '';

		if ( '' === $href ) {
			continue;
		}

		$out[] = array(
			'label' => (string) $label,
			'href'  => $href,
		);
	}

	return $out;
}

/**
 * The legal pages linked at the foot of every page.
 *
 * WordPress's own privacy policy page when it is published. The cookies page
 * and the terms join it through the filter, from the module that owns them.
 *
 * @return array<int,array{label:string,href:string}>
 */
function blueworx_frame_legal_links() {
	$links   = array();
	$privacy = (int) get_option( 'wp_page_for_privacy_policy', 0 );

	if ( $privacy > 0 && 'publish' === get_post_status( $privacy ) ) {
		$links[] = array(
			'label' => (string) get_the_title( $privacy ),
			'href'  => (string) get_permalink( $privacy ),
		);
	}

	/**
	 * Filters the legal links in the footer.
	 *
	 * @param array $links Each a label and an href.
	 */
	return (array) apply_filters( 'blueworx_footer_legal_links', $links );
}

/**
 * The copyright line.
 *
 * @param int    $year Current year.
 * @param string $name Site name.
 * @return string
 */
function blueworx_frame_copyright( $year, $name ) {
	$name = trim( (string) $name );

	return '' === $name ? sprintf( '© %d', (int) $year ) : sprintf( '© %d %s', (int) $year, $name );
}

/**
 * A row of plain links, or '' when there are none.
 *
 * @param array<int,array{label:string,href:string}> $links Links.
 * @param string                                     $list_class Class on each link.
 * @return string
 */
function blueworx_frame_links_html( $links, $list_class ) {
	$out = '';

	foreach ( (array) $links as $link ) {
		$label = trim( (string) ( $link['label'] ?? '' ) );
		$href  = trim( (string) ( $link['href'] ?? '' ) );

		if ( '' === $label || '' === $href ) {
			continue;
		}

		$out .= '<a class="' . blueworx_frame_e( $list_class ) . '" href="' . blueworx_frame_e( $href ) . '">' . blueworx_frame_e( $label ) . '</a>';
	}

	return $out;
}

/**
 * The footer, as markup. Pure.
 *
 * Every part is drawn only when there is something to draw: an empty nav
 * announces a landmark holding nothing, which is worse than no nav.
 *
 * @param array<string,mixed> $args brand, tagline, menu, social (links), legal (links), copyright.
 * @return string
 */
function blueworx_frame_footer_html( $args ) {
	$brand     = (string) ( $args['brand'] ?? '' );
	$tagline   = trim( (string) ( $args['tagline'] ?? '' ) );
	$menu      = (string) ( $args['menu'] ?? '' );
	$social    = blueworx_frame_links_html( $args['social'] ?? array(), 'blueworx-footer__social-link' );
	$legal     = blueworx_frame_links_html( $args['legal'] ?? array(), 'blueworx-footer__legal-link' );
	$copyright = trim( (string) ( $args['copyright'] ?? '' ) );

	$out = '<footer class="blueworx-footer"><div class="blueworx-footer__inner">'
		. '<div class="blueworx-footer__brand">' . $brand;

	if ( '' !== $tagline ) {
		$out .= '<p class="blueworx-footer__tagline">' . blueworx_frame_e( $tagline ) . '</p>';
	}

	if ( '' !== $social ) {
		$out .= '<nav class="blueworx-footer__social" aria-label="' . blueworx_frame_e( __( 'Social links', 'blueworx-labs-wordpress' ) ) . '">' . $social . '</nav>';
	}

	$out .= '</div>';

	if ( '' !== trim( $menu ) ) {
		$out .= '<div class="blueworx-footer__menu">' . $menu . '</div>';
	}

	$out .= '</div>';

	if ( '' !== $copyright || '' !== $legal ) {
		$out .= '<div class="blueworx-footer__bottom">';

		if ( '' !== $copyright ) {
			$out .= '<span class="blueworx-footer__copyright">' . blueworx_frame_e( $copyright ) . '</span>';
		}

		if ( '' !== $legal ) {
			$out .= '<nav class="blueworx-footer__legal" aria-label="' . blueworx_frame_e( __( 'Legal', 'blueworx-labs-wordpress' ) ) . '">' . $legal . '</nav>';
		}

		$out .= '</div>';
	}

	return $out . '</footer>';
}

/**
 * Prints the header. Nothing at all when the frame is off.
 *
 * @return void
 */
function blueworx_frame_header() {
	if ( ! blueworx_frame_enabled() ) {
		return;
	}

	/**
	 * Fires before the theme's header. For a banner above it.
	 */
	do_action( 'blueworx_header_before' );

	$brand   = blueworx_frame_brand_html( get_bloginfo( 'name' ), blueworx_frame_logo_url(), home_url( '/' ) );
	$menu    = blueworx_frame_menu_html( 'primary', 'blueworx-nav__list', __( 'Primary', 'blueworx-labs-wordpress' ) );
	$actions = blueworx_frame_actions();

	echo blueworx_frame_header_html( $brand, $menu, blueworx_frame_actions_html( $actions ), blueworx_frame_actions_html( $actions, 'blueworx-menu__action' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.

	/**
	 * Fires after the theme's header, before the page's content.
	 */
	do_action( 'blueworx_header_after' );
}

/**
 * Prints the footer. Nothing at all when the frame is off.
 *
 * @return void
 */
function blueworx_frame_footer() {
	if ( ! blueworx_frame_enabled() ) {
		return;
	}

	/**
	 * Fires before the theme's footer, after the page's content.
	 */
	do_action( 'blueworx_footer_before' );

	$settings = function_exists( 'blueworx_design_settings' ) ? blueworx_design_settings() : array();
	$labels   = function_exists( 'blueworx_design_social_networks' ) ? blueworx_design_social_networks() : array();

	echo blueworx_frame_footer_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		array(
			'brand'     => blueworx_frame_brand_html( get_bloginfo( 'name' ), '', home_url( '/' ) ),
			'tagline'   => get_bloginfo( 'description' ),
			'menu'      => blueworx_frame_menu_html( 'footer', 'blueworx-footer__list', __( 'Footer', 'blueworx-labs-wordpress' ) ),
			'social'    => blueworx_frame_social_links( isset( $settings['social'] ) ? (array) $settings['social'] : array(), $labels ),
			'legal'     => blueworx_frame_legal_links(),
			'copyright' => blueworx_frame_copyright( (int) current_time( 'Y' ), get_bloginfo( 'name' ) ),
		)
	);

	/**
	 * Fires after the theme's footer.
	 */
	do_action( 'blueworx_footer_after' );
}

/**
 * Whether a page shows its title above its content.
 *
 * Every page but the front page: a home page is almost always led by a hero
 * block that carries its own heading, and a second one above it reads as a
 * mistake.
 *
 * @param bool $is_front Whether this is the site's front page.
 * @return bool
 */
function blueworx_frame_shows_title( $is_front ) {
	/**
	 * Filters whether the page title is drawn above the content.
	 *
	 * @param bool $shown True to draw it.
	 */
	return (bool) apply_filters( 'blueworx_page_title_shown', ! $is_front );
}
