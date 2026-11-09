<?php
/**
 * Legal pages and the not-found page.
 *
 * Every site gets a privacy policy, a cookies page and terms and conditions,
 * each an ordinary page a site owner can edit, on the Legal page template,
 * with starter text that names the site. A fresh site gets them published, so
 * its footer is complete from the first day; a site that already ran the
 * theme gets them as drafts, for its owner to read and publish. The cookies
 * page lists the cookies the site actually sets, through a shortcode, so it
 * stays right as tracking codes come and go.
 *
 * The privacy policy is the page WordPress itself knows about (Settings,
 * Privacy), so core's own tools keep working on it.
 *
 * The not-found page lives here too: 404.php calls blueworx_404_html().
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The option holding the legal pages' ids, keyed by kind. */
const BLUEWORX_LEGAL_OPTION = 'blueworx_legal_pages';

/** The post meta that marks a page as one of ours, holding its kind. */
const BLUEWORX_LEGAL_META = '_blueworx_legal_kind';

/** The page template the legal pages use. */
const BLUEWORX_LEGAL_TEMPLATE = 'template-legal.php';

/**
 * The three legal pages.
 *
 * @return array<string,array{title:string,slug:string}>
 */
function blueworx_legal_kinds() {
	return array(
		'privacy' => array(
			'title' => __( 'Privacy policy', 'blueworx-labs-wordpress' ),
			'slug'  => 'privacy-policy',
		),
		'cookies' => array(
			'title' => __( 'Cookies', 'blueworx-labs-wordpress' ),
			'slug'  => 'cookies',
		),
		'terms'   => array(
			'title' => __( 'Terms and conditions', 'blueworx-labs-wordpress' ),
			'slug'  => 'terms-and-conditions',
		),
	);
}

/**
 * The starter text of one legal page, as block markup. Pure.
 *
 * Plain, generic and complete enough to stand on a site until its owner has
 * something of their own to say. The cookies page carries the shortcode that
 * lists the site's cookies, so that part is never typed by hand.
 *
 * @param string $kind      privacy, cookies or terms.
 * @param string $site_name The site's name.
 * @param string $email     The address to write to about the site, or ''.
 * @return string Block markup, or '' for an unknown kind.
 */
function blueworx_legal_content( $kind, $site_name, $email ) {
	$name    = trim( (string) $site_name );
	$email   = trim( (string) $email );
	$contact = '' !== $email
		/* translators: %s: email address. */
		? sprintf( __( 'Questions about this page can be sent to %s.', 'blueworx-labs-wordpress' ), $email )
		: __( 'Questions about this page can be sent through the site\'s contact details.', 'blueworx-labs-wordpress' );

	$p = static function ( $text ) {
		return '<!-- wp:paragraph --><p>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->';
	};
	$h = static function ( $text ) {
		return '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $text ) . '</h2><!-- /wp:heading -->';
	};

	switch ( $kind ) {
		case 'privacy':
			return implode(
				"\n\n",
				array(
					/* translators: %s: site name. */
					$p( sprintf( __( '%s respects your privacy. This page explains what personal information the site collects, why, and what you can ask us to do with it.', 'blueworx-labs-wordpress' ), $name ) ),
					$h( __( 'What we collect', 'blueworx-labs-wordpress' ) ),
					$p( __( 'When you fill in a form, create an account or buy something, we keep the details you give us: your name, email address and anything else the form asks for. The site also records the usual technical details of a visit, such as your browser and the pages you open, to keep it working and secure.', 'blueworx-labs-wordpress' ) ),
					$h( __( 'How we use it', 'blueworx-labs-wordpress' ) ),
					$p( __( 'To provide what you asked for, to answer your messages, to run your account or order, and, where you have agreed, to send you news and offers. We do not sell your information.', 'blueworx-labs-wordpress' ) ),
					$h( __( 'Who else sees it', 'blueworx-labs-wordpress' ) ),
					$p( __( 'The services that run this site on our behalf, such as hosting, payments and email, see only what they need to do their job. Our cookies page lists the services that set cookies here.', 'blueworx-labs-wordpress' ) ),
					$h( __( 'Your choices', 'blueworx-labs-wordpress' ) ),
					$p( __( 'You can ask to see the information we hold about you, have it corrected or deleted, or stop hearing from us at any time.', 'blueworx-labs-wordpress' ) ),
					$p( $contact ),
				)
			);

		case 'cookies':
			return implode(
				"\n\n",
				array(
					$p( __( 'Cookies are small files a website stores in your browser. This site uses them to work properly, to remember your choices and, where you agree, to understand how the site is used.', 'blueworx-labs-wordpress' ) ),
					$h( __( 'The cookies this site sets', 'blueworx-labs-wordpress' ) ),
					'<!-- wp:shortcode -->[blueworx_cookie_list]<!-- /wp:shortcode -->',
					$h( __( 'Your choices', 'blueworx-labs-wordpress' ) ),
					$p( __( 'You can clear or block cookies in your browser\'s settings. Blocking the essential ones may stop parts of the site working, such as signing in or paying.', 'blueworx-labs-wordpress' ) ),
					$p( $contact ),
				)
			);

		case 'terms':
			return implode(
				"\n\n",
				array(
					/* translators: %s: site name. */
					$p( sprintf( __( 'These are the terms on which %s is offered to you. By using the site you agree to them.', 'blueworx-labs-wordpress' ), $name ) ),
					$h( __( 'Using the site', 'blueworx-labs-wordpress' ) ),
					$p( __( 'You may use the site for its intended purpose. You may not misuse it, try to gain access you have not been given, or copy its content for commercial use without permission.', 'blueworx-labs-wordpress' ) ),
					$h( __( 'Accounts and purchases', 'blueworx-labs-wordpress' ) ),
					$p( __( 'You are responsible for keeping your account details safe. Prices, availability and the details of anything you buy are as shown at the time of purchase, and your statutory rights are not affected.', 'blueworx-labs-wordpress' ) ),
					$h( __( 'Changes', 'blueworx-labs-wordpress' ) ),
					$p( __( 'We may update these terms. The date at the top of this page shows when they last changed.', 'blueworx-labs-wordpress' ) ),
					$p( $contact ),
				)
			);
	}

	return '';
}

/**
 * The ids of the legal pages, keyed by kind.
 *
 * The privacy policy is whatever WordPress has on Settings, Privacy, so a
 * page chosen there is the one the footer links to.
 *
 * @return array<string,int> Only kinds with a page that still exists.
 */
function blueworx_legal_page_ids() {
	$stored = get_option( BLUEWORX_LEGAL_OPTION, array() );
	$ids    = array();

	foreach ( array_keys( blueworx_legal_kinds() ) as $kind ) {
		$id = 'privacy' === $kind
			? (int) get_option( 'wp_page_for_privacy_policy', 0 )
			: (int) ( is_array( $stored ) && isset( $stored[ $kind ] ) ? $stored[ $kind ] : 0 );

		if ( $id > 0 && get_post( $id ) && 'trash' !== get_post_status( $id ) ) {
			$ids[ $kind ] = $id;
		}
	}

	return $ids;
}

/**
 * One legal page's id, or 0.
 *
 * @param string $kind privacy, cookies or terms.
 * @return int
 */
function blueworx_legal_page_id( $kind ) {
	$ids = blueworx_legal_page_ids();

	return isset( $ids[ $kind ] ) ? $ids[ $kind ] : 0;
}

/**
 * Makes sure every legal page exists, creating the missing ones.
 *
 * Safe to run again: a page that exists is left exactly as it is, published
 * or not, edited or not. Only a page that is missing is created.
 *
 * @param bool $publish Whether a created page is published or left a draft.
 * @return array<string,int> The pages' ids, keyed by kind.
 */
function blueworx_legal_ensure_pages( $publish ) {
	$ids    = blueworx_legal_page_ids();
	$stored = get_option( BLUEWORX_LEGAL_OPTION, array() );
	$stored = is_array( $stored ) ? $stored : array();

	foreach ( blueworx_legal_kinds() as $kind => $def ) {
		if ( isset( $ids[ $kind ] ) ) {
			$stored[ $kind ] = $ids[ $kind ];
			continue;
		}

		// A page with the slug already there, perhaps made by hand, is adopted
		// rather than doubled.
		$existing = get_page_by_path( $def['slug'], OBJECT, 'page' );

		if ( $existing instanceof WP_Post && 'trash' !== $existing->post_status ) {
			$id = (int) $existing->ID;
		} else {
			$id = (int) wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => $publish ? 'publish' : 'draft',
					'post_name'    => $def['slug'],
					'post_title'   => $def['title'],
					'post_content' => blueworx_legal_content( $kind, get_bloginfo( 'name' ), (string) get_option( 'admin_email', '' ) ),
					'meta_input'   => array(
						BLUEWORX_LEGAL_META => $kind,
						'_wp_page_template' => BLUEWORX_LEGAL_TEMPLATE,
					),
				)
			);
		}

		if ( $id <= 0 ) {
			continue;
		}

		update_post_meta( $id, BLUEWORX_LEGAL_META, $kind );
		$stored[ $kind ] = $id;
		$ids[ $kind ]    = $id;

		if ( 'privacy' === $kind ) {
			update_option( 'wp_page_for_privacy_policy', $id );
		}
	}

	update_option( BLUEWORX_LEGAL_OPTION, $stored );

	return $ids;
}

/**
 * The footer's legal links: whichever of the three pages are published.
 *
 * Pure: takes the pages as facts and decides which to link.
 *
 * @param array<int,array{label:string,href:string}>                 $links Links already there (the frame adds the privacy policy).
 * @param array<string,array{status:string,title:string,url:string}> $pages The legal pages by kind.
 * @return array<int,array{label:string,href:string}>
 */
function blueworx_legal_merge_links( $links, $pages ) {
	$links = is_array( $links ) ? array_values( $links ) : array();
	$seen  = array_map(
		static function ( $link ) {
			return isset( $link['href'] ) ? (string) $link['href'] : '';
		},
		$links
	);

	foreach ( array( 'privacy', 'cookies', 'terms' ) as $kind ) {
		if ( ! isset( $pages[ $kind ] ) || 'publish' !== ( $pages[ $kind ]['status'] ?? '' ) ) {
			continue;
		}

		$url = (string) ( $pages[ $kind ]['url'] ?? '' );

		if ( '' === $url || in_array( $url, $seen, true ) ) {
			continue;
		}

		$links[] = array(
			'label' => (string) ( $pages[ $kind ]['title'] ?? '' ),
			'href'  => $url,
		);
		$seen[]  = $url;
	}

	return $links;
}

/**
 * Adds the cookies page and the terms to the footer.
 *
 * @param array $links Links from the frame.
 * @return array
 */
function blueworx_legal_footer_links( $links ) {
	$pages = array();

	foreach ( blueworx_legal_page_ids() as $kind => $id ) {
		$pages[ $kind ] = array(
			'status' => (string) get_post_status( $id ),
			'title'  => (string) get_the_title( $id ),
			'url'    => (string) get_permalink( $id ),
		);
	}

	return blueworx_legal_merge_links( $links, $pages );
}
add_filter( 'blueworx_footer_legal_links', 'blueworx_legal_footer_links' );

/**
 * Names the legal pages in the Pages list's Source column.
 *
 * @param string $label   The label so far.
 * @param int    $post_id The page.
 * @return string
 */
function blueworx_legal_page_source( $label, $post_id ) {
	return '' !== (string) get_post_meta( (int) $post_id, BLUEWORX_LEGAL_META, true ) ? __( 'Legal page', 'blueworx-labs-wordpress' ) : $label;
}
add_filter( 'blueworx_page_source', 'blueworx_legal_page_source', 10, 2 );

/**
 * The cookies the site sets, for the cookies page.
 *
 * WordPress's own, then the tracking codes that are switched on, then
 * Klaviyo's when its site script runs. Plugins add theirs through the filter.
 *
 * @return array<int,array{name:string,provider:string,purpose:string,duration:string,category:string}>
 */
function blueworx_cookie_declarations() {
	$cookies = array(
		array(
			'name'     => 'wordpress_test_cookie',
			'provider' => __( 'This site', 'blueworx-labs-wordpress' ),
			'purpose'  => __( 'Checks that your browser accepts cookies, so signing in can work.', 'blueworx-labs-wordpress' ),
			'duration' => __( 'Until you close the browser', 'blueworx-labs-wordpress' ),
			'category' => __( 'Essential', 'blueworx-labs-wordpress' ),
		),
		array(
			'name'     => 'wordpress_logged_in_*, wordpress_sec_*',
			'provider' => __( 'This site', 'blueworx-labs-wordpress' ),
			'purpose'  => __( 'Keeps you signed in.', 'blueworx-labs-wordpress' ),
			'duration' => __( 'Until you sign out, or up to two weeks', 'blueworx-labs-wordpress' ),
			'category' => __( 'Essential', 'blueworx-labs-wordpress' ),
		),
		array(
			'name'     => 'wp-settings-*',
			'provider' => __( 'This site', 'blueworx-labs-wordpress' ),
			'purpose'  => __( 'Remembers how you have set up the admin screens.', 'blueworx-labs-wordpress' ),
			'duration' => __( 'One year', 'blueworx-labs-wordpress' ),
			'category' => __( 'Essential', 'blueworx-labs-wordpress' ),
		),
	);

	$ids = array();

	if ( function_exists( 'blueworx_tracking_active' ) && function_exists( 'blueworx_tracking_id' ) && blueworx_tracking_active() ) {
		$ids = array_filter(
			array(
				'gtm'  => blueworx_tracking_id( 'gtm' ),
				'ga'   => blueworx_tracking_id( 'ga' ),
				'meta' => blueworx_tracking_id( 'meta' ),
			)
		);
	}

	if ( isset( $ids['gtm'] ) || isset( $ids['ga'] ) ) {
		$cookies[] = array(
			'name'     => '_ga, _ga_*, _gid',
			'provider' => 'Google',
			'purpose'  => __( 'Tells apart visitors, so Google Analytics can count visits and pages.', 'blueworx-labs-wordpress' ),
			'duration' => __( 'Up to two years', 'blueworx-labs-wordpress' ),
			'category' => __( 'Analytics', 'blueworx-labs-wordpress' ),
		);
	}

	if ( isset( $ids['meta'] ) ) {
		$cookies[] = array(
			'name'     => '_fbp',
			'provider' => 'Meta',
			'purpose'  => __( 'Lets Meta measure adverts and show relevant ones on Facebook and Instagram.', 'blueworx-labs-wordpress' ),
			'duration' => __( 'Three months', 'blueworx-labs-wordpress' ),
			'category' => __( 'Marketing', 'blueworx-labs-wordpress' ),
		);
	}

	if ( function_exists( 'blueworx_klaviyo_tracking_active' ) && blueworx_klaviyo_tracking_active() ) {
		$cookies[] = array(
			'name'     => '__kla_id',
			'provider' => 'Klaviyo',
			'purpose'  => __( 'Recognises you across visits so the site\'s emails can reflect what you looked at.', 'blueworx-labs-wordpress' ),
			'duration' => __( 'Two years', 'blueworx-labs-wordpress' ),
			'category' => __( 'Marketing', 'blueworx-labs-wordpress' ),
		);
	}

	/**
	 * Filters the cookies listed on the cookies page.
	 *
	 * @param array $cookies Each a name, provider, purpose, duration and category.
	 */
	return (array) apply_filters( 'blueworx_cookie_declarations', $cookies );
}

/**
 * The cookie table. Pure.
 *
 * @param array<int,array<string,string>> $cookies From blueworx_cookie_declarations().
 * @return string
 */
function blueworx_cookie_list_html( $cookies ) {
	$rows = '';

	foreach ( (array) $cookies as $cookie ) {
		$name = trim( (string) ( $cookie['name'] ?? '' ) );

		if ( '' === $name ) {
			continue;
		}

		$rows .= '<tr>'
			. '<td class="blueworx-cookies__name"><code>' . blueworx_frame_e( $name ) . '</code></td>'
			. '<td>' . blueworx_frame_e( (string) ( $cookie['provider'] ?? '' ) ) . '</td>'
			. '<td>' . blueworx_frame_e( (string) ( $cookie['purpose'] ?? '' ) ) . '</td>'
			. '<td>' . blueworx_frame_e( (string) ( $cookie['duration'] ?? '' ) ) . '</td>'
			. '<td>' . blueworx_frame_e( (string) ( $cookie['category'] ?? '' ) ) . '</td>'
			. '</tr>';
	}

	if ( '' === $rows ) {
		return '<p class="blueworx-cookies__none">' . blueworx_frame_e( __( 'This site sets no cookies of its own.', 'blueworx-labs-wordpress' ) ) . '</p>';
	}

	return '<figure class="blueworx-cookies"><table>'
		. '<thead><tr>'
		. '<th scope="col">' . blueworx_frame_e( __( 'Cookie', 'blueworx-labs-wordpress' ) ) . '</th>'
		. '<th scope="col">' . blueworx_frame_e( __( 'Set by', 'blueworx-labs-wordpress' ) ) . '</th>'
		. '<th scope="col">' . blueworx_frame_e( __( 'What it does', 'blueworx-labs-wordpress' ) ) . '</th>'
		. '<th scope="col">' . blueworx_frame_e( __( 'Lasts', 'blueworx-labs-wordpress' ) ) . '</th>'
		. '<th scope="col">' . blueworx_frame_e( __( 'Type', 'blueworx-labs-wordpress' ) ) . '</th>'
		. '</tr></thead><tbody>' . $rows . '</tbody></table></figure>';
}

/**
 * The [blueworx_cookie_list] shortcode.
 *
 * @return string
 */
function blueworx_cookie_list_shortcode() {
	return blueworx_cookie_list_html( blueworx_cookie_declarations() );
}
add_shortcode( 'blueworx_cookie_list', 'blueworx_cookie_list_shortcode' );

/**
 * The "last updated" line on a legal page.
 *
 * @param string $date The date, already formatted.
 * @return string
 */
function blueworx_legal_updated_html( $date ) {
	$date = trim( (string) $date );

	if ( '' === $date ) {
		return '';
	}

	return '<p class="blueworx-legal__updated">' . blueworx_frame_e(
		/* translators: %s: date. */
		sprintf( __( 'Last updated %s', 'blueworx-labs-wordpress' ), $date )
	) . '</p>';
}

/**
 * The not-found page's body. Pure.
 *
 * Says what happened, offers a search, and lists a few places to go.
 *
 * @param string                                     $home_url    Home.
 * @param array<int,array{label:string,href:string}> $pages       Pages to offer.
 * @param string                                     $search_form The search form's markup, or ''.
 * @return string
 */
function blueworx_404_html( $home_url, $pages, $search_form ) {
	$out = '<section class="blueworx-404">'
		. '<header class="blueworx-page__head"><p class="blueworx-404__code" aria-hidden="true">404</p>'
		. '<h1 class="blueworx-page__title">' . blueworx_frame_e( __( 'Page not found', 'blueworx-labs-wordpress' ) ) . '</h1>'
		. '<p class="blueworx-404__text">' . blueworx_frame_e( __( 'That page has moved or never existed. Try a search, or pick up from one of these.', 'blueworx-labs-wordpress' ) ) . '</p>'
		. '</header>';

	if ( '' !== trim( (string) $search_form ) ) {
		$out .= '<div class="blueworx-404__search">' . $search_form . '</div>';
	}

	$links = '<a class="blueworx-btn blueworx-btn--primary" href="' . blueworx_frame_e( $home_url ) . '">' . blueworx_frame_e( __( 'Back to the home page', 'blueworx-labs-wordpress' ) ) . '</a>';

	foreach ( (array) $pages as $page ) {
		$label = trim( (string) ( $page['label'] ?? '' ) );
		$href  = trim( (string) ( $page['href'] ?? '' ) );

		if ( '' === $label || '' === $href ) {
			continue;
		}

		$links .= '<a class="blueworx-btn blueworx-btn--secondary" href="' . blueworx_frame_e( $href ) . '">' . blueworx_frame_e( $label ) . '</a>';
	}

	return $out . '<nav class="blueworx-404__links" aria-label="' . blueworx_frame_e( __( 'Suggested pages', 'blueworx-labs-wordpress' ) ) . '">' . $links . '</nav></section>';
}

/**
 * Tells search engines a not-found page is one.
 *
 * WordPress sends the 404 status itself; this only makes sure a page cache
 * does not keep a copy of a page that does not exist.
 *
 * @return void
 */
function blueworx_404_headers() {
	if ( is_404() && ! headers_sent() ) {
		nocache_headers();
	}
}
add_action( 'template_redirect', 'blueworx_404_headers' );
