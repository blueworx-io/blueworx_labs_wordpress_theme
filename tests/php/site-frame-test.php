<?php
/**
 * The page frame: every piece of header and footer markup, built pure.
 *
 * Run with: php tests/php/site-frame-test.php
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

require __DIR__ . '/stubs.php';

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

require __DIR__ . '/../../includes/frame.php';

/**
 * A yes-or-no check: shorter than spelling out "expected true" every time.
 *
 * @param string $label What is being checked.
 * @param bool   $cond  Whether it held.
 * @return void
 */
function ok( $label, $cond ) {
	check( $label, (bool) $cond, true );
}

// The brand: a logo when there is one, the name when there is not, always a link home.
$brand = blueworx_frame_brand_html( 'Fixture & Co', '', 'http://x/' );
ok( 'brand without a logo shows the name', false !== strpos( $brand, '<span class="blueworx-brand__name">Fixture &amp; Co</span>' ) );
ok( 'brand links home', false !== strpos( $brand, 'href="http://x/" rel="home"' ) );
$brand = blueworx_frame_brand_html( 'Fixture & Co', 'http://x/logo.png', 'http://x/' );
ok( 'brand with a logo shows the image', false !== strpos( $brand, '<img class="blueworx-brand__logo" src="http://x/logo.png" alt="Fixture &amp; Co">' ) );
ok( 'brand with a logo does not repeat the name as text', false === strpos( $brand, 'blueworx-brand__name' ) );

// Pages as a menu.
$pages = blueworx_frame_pages_html(
	array(
		array(
			'label'   => 'About',
			'href'    => 'http://x/about/',
			'current' => true,
		),
		array(
			'label' => 'Contact',
			'href'  => 'http://x/contact/',
		),
		array(
			'label' => '',
			'href'  => 'http://x/empty/',
		),
	),
	'blueworx-nav__list'
);
ok( 'pages list carries the class', false !== strpos( $pages, '<ul class="blueworx-nav__list">' ) );
ok( 'current page is marked', false !== strpos( $pages, '<li class="menu-item current-menu-item"><a href="http://x/about/" aria-current="page">About</a></li>' ) );
ok( 'other pages are plain items', false !== strpos( $pages, '<li class="menu-item"><a href="http://x/contact/">Contact</a></li>' ) );
ok( 'a page with no label is skipped', false === strpos( $pages, 'empty' ) );
ok( 'no pages means no list', '' === blueworx_frame_pages_html( array(), 'x' ) );

// Actions as buttons.
$actions = blueworx_frame_actions_html(
	array(
		array(
			'label'   => 'Join',
			'href'    => 'http://x/join/',
			'variant' => 'primary',
		),
		array(
			'label' => 'Sign in',
			'href'  => 'http://x/in/?a=1&b=2',
		),
		array(
			'label' => 'Nowhere',
			'href'  => '',
		),
	)
);
ok( 'a primary action', false !== strpos( $actions, '<a class="blueworx-btn blueworx-btn--primary" href="http://x/join/">Join</a>' ) );
ok( 'an action with no variant is secondary, and its address is escaped once', false !== strpos( $actions, '<a class="blueworx-btn blueworx-btn--secondary" href="http://x/in/?a=1&amp;b=2">Sign in</a>' ) );
ok( 'an action with no address is skipped', false === strpos( $actions, 'Nowhere' ) );
ok(
	'the drawer gets its extra class',
	false !== strpos(
		blueworx_frame_actions_html(
			array(
				array(
					'label' => 'A',
					'href'  => 'http://x/',
				),
			),
			'blueworx-menu__action'
		),
		'class="blueworx-btn blueworx-btn--secondary blueworx-menu__action"'
	)
);

// The header.
$header = blueworx_frame_header_html( '<a class="blueworx-brand">B</a>', '<nav>MENU</nav>', '<a class="blueworx-btn">ACT</a>', '<a class="blueworx-btn blueworx-menu__action">ACT</a>' );
ok( 'header holds the brand, the menu and the actions', false !== strpos( $header, '<a class="blueworx-brand">B</a><div class="blueworx-header__nav"><nav>MENU</nav></div><div class="blueworx-header__actions"><a class="blueworx-btn">ACT</a>' ) );
ok( 'header has a phone drawer', false !== strpos( $header, '<details class="blueworx-menu"><summary class="blueworx-menu__button" aria-label="Menu">' ) );
ok( 'drawer repeats the actions then the menu', false !== strpos( $header, '<div class="blueworx-menu__drawer"><a class="blueworx-btn blueworx-menu__action">ACT</a><nav>MENU</nav></div>' ) );
ok( 'no menu and no actions means no drawer', false === strpos( blueworx_frame_header_html( 'B', '', '', '' ), 'blueworx-menu' ) );

// Social links.
$social = blueworx_frame_social_links(
	array(
		'instagram' => ' https://instagram.com/x ',
		'x'         => '',
		'facebook'  => 'https://facebook.com/x',
	),
	array(
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'x'         => 'X',
	)
);
ok( 'social links follow the networks order and skip empty ones', array( 'Facebook', 'Instagram' ) === array_column( $social, 'label' ) );
ok( 'social addresses are trimmed', 'https://instagram.com/x' === $social[1]['href'] );

// Copyright.
ok( 'copyright with a name', '© 2026 Fixture' === blueworx_frame_copyright( 2026, 'Fixture' ) );
ok( 'copyright without a name', '© 2026' === blueworx_frame_copyright( 2026, '  ' ) );

// The footer.
$footer = blueworx_frame_footer_html(
	array(
		'brand'     => '<a class="blueworx-brand">B</a>',
		'tagline'   => 'One club & every sport',
		'menu'      => '<nav>MENU</nav>',
		'social'    => array(
			array(
				'label' => 'Instagram',
				'href'  => 'https://instagram.com/x',
			),
		),
		'legal'     => array(
			array(
				'label' => 'Privacy',
				'href'  => 'http://x/privacy/',
			),
		),
		'copyright' => '© 2026 Fixture',
	)
);
ok( 'footer brand and tagline', false !== strpos( $footer, '<div class="blueworx-footer__brand"><a class="blueworx-brand">B</a><p class="blueworx-footer__tagline">One club &amp; every sport</p>' ) );
ok( 'footer social nav', false !== strpos( $footer, '<nav class="blueworx-footer__social" aria-label="Social links"><a class="blueworx-footer__social-link" href="https://instagram.com/x">Instagram</a></nav>' ) );
ok( 'footer menu', false !== strpos( $footer, '<div class="blueworx-footer__menu"><nav>MENU</nav></div>' ) );
ok( 'footer bottom bar', false !== strpos( $footer, '<div class="blueworx-footer__bottom"><span class="blueworx-footer__copyright">© 2026 Fixture</span><nav class="blueworx-footer__legal" aria-label="Legal"><a class="blueworx-footer__legal-link" href="http://x/privacy/">Privacy</a></nav></div>' ) );

$bare = blueworx_frame_footer_html( array( 'brand' => 'B' ) );
ok( 'an empty footer draws no tagline, social, menu or bottom bar', false === strpos( $bare, 'tagline' ) && false === strpos( $bare, '<nav' ) && false === strpos( $bare, 'blueworx-footer__menu' ) && false === strpos( $bare, 'blueworx-footer__bottom' ) );
ok( 'a copyright alone still gets the bottom bar', false !== strpos( blueworx_frame_footer_html( array( 'copyright' => '© 2026' ) ), '<div class="blueworx-footer__bottom"><span class="blueworx-footer__copyright">© 2026</span></div>' ) );

// The page title.
ok( 'pages show their title', true === blueworx_frame_shows_title( false ) );
ok( 'the front page does not', false === blueworx_frame_shows_title( true ), true );

finish();
