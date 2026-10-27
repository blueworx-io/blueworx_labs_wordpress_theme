<?php
/**
 * Site design system: the named styles plugins and pages read.
 *
 * The names are a contract — Clubhouse and every client plugin are written
 * against them — so every one must always be there. The values must follow
 * the choices, and every text pairing must read, on any background, or be
 * flagged when it cannot.
 *
 * Run with: php tests/php/design-tokens-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';

define( 'BLUEWORX_LABS_URL', 'https://site.test/wp-content/themes/blueworx-labs-wordpress-theme/' );

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function absint( $value ) {
	return abs( (int) $value );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- The stub stands in for it.
}

function esc_url_raw( $url ) {
	return filter_var( $url, FILTER_SANITIZE_URL );
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/design/color.php';
require dirname( __DIR__, 2 ) . '/includes/design/settings.php';
require dirname( __DIR__, 2 ) . '/includes/design/tokens.php';

/**
 * The defaults with some choices changed.
 *
 * @param array $over Choices to change.
 * @return array Settings.
 */
function with( $over ) {
	return array_merge( blueworx_design_defaults(), $over );
}

$named = array(
	'--color-primary',
	'--color-primary-hover',
	'--color-primary-pressed',
	'--color-primary-tint',
	'--color-on-primary',
	'--color-link',
	'--color-primary-50',
	'--color-primary-100',
	'--color-primary-200',
	'--color-primary-300',
	'--color-primary-400',
	'--color-primary-500',
	'--color-primary-600',
	'--color-primary-700',
	'--color-primary-800',
	'--color-primary-900',
	'--color-accent',
	'--color-accent-text',
	'--color-accent-tint',
	'--color-on-accent',
	'--color-bg',
	'--color-surface',
	'--color-border',
	'--color-border-strong',
	'--color-placeholder',
	'--color-text-muted',
	'--color-text',
	'--color-dark-raised',
	'--color-success',
	'--color-success-tint',
	'--color-warning',
	'--color-warning-tint',
	'--color-error',
	'--color-error-tint',
	'--color-info',
	'--color-info-tint',
	'--font-heading',
	'--font-body',
	'--text-display',
	'--text-h1',
	'--text-h2',
	'--text-h3',
	'--text-h4',
	'--text-h5',
	'--text-h6',
	'--text-lead',
	'--text-body',
	'--text-small',
	'--text-caption',
	'--space-1',
	'--space-2',
	'--space-3',
	'--space-4',
	'--space-6',
	'--space-8',
	'--space-12',
	'--space-16',
	'--space-24',
	'--radius-sm',
	'--radius-md',
	'--radius-lg',
	'--radius-xl',
	'--radius-full',
	'--radius-button',
	'--shadow-sm',
	'--shadow-md',
	'--shadow-lg',
	'--focus-ring',
	'--overlay',
	'--width-reading',
	'--width-page',
	'--width-wide',
	'--gutter',
	'--section-space',
);

$t = blueworx_design_tokens( blueworx_design_defaults() );

check( 'every named style and nothing else', array_keys( $t ), $named );
check( 'primary passes through', $t['--color-primary'], '#2f5bea' );
check( 'scale 500 is the primary', $t['--color-primary-500'], '#2f5bea' );
check( 'white on default blue', $t['--color-on-primary'], '#ffffff' );
check( 'accent passes through', $t['--color-accent'], '#f08a2c' );
check( 'h1 is 48px', $t['--text-h1'], '48px' );
check( 'body is 16px', $t['--text-body'], '16px' );
check( 'space-24 is 96px', $t['--space-24'], '96px' );
check( 'rounded md is 8px', $t['--radius-md'], '8px' );
check( 'buttons match the corners', $t['--radius-button'], '8px' );
check( 'heading font is Sora', 0 === strpos( $t['--font-heading'], '"Sora"' ), true );
check( 'focus ring uses the primary', $t['--focus-ring'], '0 0 0 3px rgba(47, 91, 234, .28)' );
check( 'page width', $t['--width-page'], '1200px' );
check( 'gutter', $t['--gutter'], 'clamp(16px, 3vw, 32px)' );
check( 'one value by name', blueworx_design_token( '--text-h2' ), '36px' );
check( 'an unknown name is empty', blueworx_design_token( '--nope' ), '' );

$phone = blueworx_design_phone_tokens( blueworx_design_defaults() );
check( 'phone h1 is 34px', $phone['--text-h1'], '34px' );
check( 'phone display is 40px', $phone['--text-display'], '40px' );

check( 'large type grows h1', blueworx_design_tokens( with( array( 'type_scale' => 'large' ) ) )['--text-h1'], '54px' );
check( 'large type leaves body alone', blueworx_design_tokens( with( array( 'type_scale' => 'large' ) ) )['--text-body'], '16px' );
check( 'compact spacing', blueworx_design_tokens( with( array( 'spacing' => 'compact' ) ) )['--space-4'], '12px' );
check( 'compact gutter', blueworx_design_tokens( with( array( 'spacing' => 'compact' ) ) )['--gutter'], 'clamp(12px, 3vw, 24px)' );
check( 'square corners', blueworx_design_tokens( with( array( 'corners' => 'square' ) ) )['--radius-lg'], '0px' );
check( 'pill buttons', blueworx_design_tokens( with( array( 'buttons' => 'pill' ) ) )['--radius-button'], '9999px' );
check( 'square buttons', blueworx_design_tokens( with( array( 'buttons' => 'square' ) ) )['--radius-button'], '0px' );

$auto = blueworx_design_tokens( with( array( 'accent' => '' ) ) )['--color-accent'];
check( 'empty accent is worked out', blueworx_color_normalize( $auto ), $auto );
check( 'worked-out accent differs from primary', '#2f5bea' !== $auto, true );

$looks = array(
	'defaults' => array(),
	'yellow'   => array( 'primary' => '#f5d90a' ),
	'lime'     => array( 'primary' => '#c6f24e' ),
	'grey'     => array( 'primary' => '#808080' ),
	'cyan'     => array( 'primary' => '#00ffff' ),
	'orange'   => array( 'primary' => '#f08a2c' ),
	'dark'     => array(
		'background' => '#111111',
		'text'       => '#f2f2f2',
	),
	'dark-red' => array(
		'background' => '#14110b',
		'text'       => '#f5efe3',
		'primary'    => '#d1202f',
	),
);

foreach ( $looks as $label => $over ) {
	foreach ( blueworx_design_readability( blueworx_design_tokens( with( $over ) ) ) as $row ) {
		check( "$label: {$row['label']} reads ({$row['ratio']})", $row['passes'], true );
	}
}

$pale = blueworx_design_readability( blueworx_design_tokens( with( array( 'text' => '#d0d0d0' ) ) ) );
check( 'pale body text is flagged', array_column( $pale, 'passes', 'label' )['Body text on the background'], false );
check( 'a mid-grey brand colour still gets readable button text', array_column( blueworx_design_readability( blueworx_design_tokens( with( array( 'primary' => '#8a8a8a' ) ) ) ), 'passes', 'label' )['Button text on the main colour'], true );

$dark = blueworx_design_tokens( with( $looks['dark'] ) );
$report = array_column( blueworx_design_readability( $dark ), 'passes', 'label' );
foreach ( array( 'Button text on the main colour', 'Button text on hover', 'Button text when pressed', 'Links on the background' ) as $pair ) {
	check( "the report checks: $pair", isset( $report[ $pair ] ), true );
}
check( 'links have their own colour', isset( $dark['--color-link'] ), true );

$css = blueworx_design_css( blueworx_design_defaults() );
check( 'css is a root block and a phone block', (bool) preg_match( '/^:root\{[^}]+\}@media \(max-width:639px\)\{:root\{[^}]+\}\}$/', $css ), true );
check( 'css carries the primary', false !== strpos( $css, '--color-primary:#2f5bea;' ), true );

check( 'only the chosen fonts load', substr_count( blueworx_design_font_face_css( blueworx_design_defaults() ), '@font-face' ), 6 );
check( 'one font for both loads once', substr_count( blueworx_design_font_face_css( with( array( 'body_font' => 'sora' ) ) ), '@font-face' ), 3 );
check(
	'device fonts load nothing',
	blueworx_design_font_face_css(
		with(
			array(
				'heading_font' => 'system',
				'body_font'    => 'serif',
			)
		)
	),
	''
);

$GLOBALS['filters']['blueworx_design_fonts'] = function ( $fonts ) {
	$fonts['evil'] = array(
		'label'  => 'Evil',
		'family' => 'Evil"}</style><script>x()</script>',
		'stack'  => 'x;}</style><script>x()</script>',
		'files'  => array( 400 => 'https://club.test/a.woff2") ;}</style><script>' ),
	);
	return $fonts;
};
$evil = with( array( 'heading_font' => 'evil' ) );
check( 'a plugin stack cannot break out', preg_match( '/[;{}<>]/', blueworx_design_tokens( $evil )['--font-heading'] ), 0 );
check( 'a plugin font face cannot break out', preg_match( '/<|\}\s*<|;\s*\}\s*</', blueworx_design_font_face_css( $evil ) ), 0 );
check( 'the css cannot break out', false === strpos( blueworx_design_css( $evil ), '</style' ), true );

$GLOBALS['filters']['blueworx_design_tokens'] = function ( $tokens ) {
	$tokens['--color-primary'] = 'red;}</style>';
	return $tokens;
};
check( 'a filtered value cannot break out', false === strpos( blueworx_design_css( blueworx_design_defaults() ), '</style' ), true );

finish();
