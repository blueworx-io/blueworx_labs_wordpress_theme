<?php
/**
 * The design system reference page's markup.
 *
 * Required by blueworx_design_reference_render() once it has checked the
 * visitor may see it. Every value comes from the site's settings; every
 * colour, font, size and corner is a named style, so the page is the site's
 * real look. Section order and wording follow the approved mock-up.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blueworx_settings = blueworx_design_settings();
$blueworx_tokens   = blueworx_design_tokens( $blueworx_settings );
$blueworx_fonts    = blueworx_design_fonts();
$blueworx_choices  = blueworx_design_choices();
$blueworx_name     = blueworx_design_reference_site_name();
$blueworx_tagline  = wp_specialchars_decode( (string) get_bloginfo( 'description' ), ENT_QUOTES );
$blueworx_heading  = $blueworx_fonts[ $blueworx_settings['heading_font'] ];
$blueworx_body     = $blueworx_fonts[ $blueworx_settings['body_font'] ];
$blueworx_icon_url = get_site_icon_url( 128 );
$blueworx_palette  = blueworx_design_palette();

$blueworx_sections = array(
	'overview'   => __( 'Overview', 'blueworx-labs-wordpress' ),
	'colours'    => __( 'Colours', 'blueworx-labs-wordpress' ),
	'typography' => __( 'Typography', 'blueworx-labs-wordpress' ),
	'spacing'    => __( 'Spacing & corners', 'blueworx-labs-wordpress' ),
	'layout'     => __( 'Layout', 'blueworx-labs-wordpress' ),
	'buttons'    => __( 'Buttons', 'blueworx-labs-wordpress' ),
	'inputs'     => __( 'Inputs', 'blueworx-labs-wordpress' ),
	'badges'     => __( 'Badges & states', 'blueworx-labs-wordpress' ),
	'alerts'     => __( 'Alerts', 'blueworx-labs-wordpress' ),
	'shadows'    => __( 'Shadows', 'blueworx-labs-wordpress' ),
	'cards'      => __( 'Cards', 'blueworx-labs-wordpress' ),
	'navigation' => __( 'Navigation', 'blueworx-labs-wordpress' ),
	'faqs'       => __( 'FAQs & tables', 'blueworx-labs-wordpress' ),
	'images'     => __( 'Images', 'blueworx-labs-wordpress' ),
	'dark'       => __( 'Dark sections', 'blueworx-labs-wordpress' ),
	'icons'      => __( 'Icons', 'blueworx-labs-wordpress' ),
	'logo'       => __( 'Logo', 'blueworx-labs-wordpress' ),
	'usage'      => __( 'Usage', 'blueworx-labs-wordpress' ),
	'tokens'     => __( 'Named styles', 'blueworx-labs-wordpress' ),
);

/**
 * Prints a section's opening: card, numbered head, body.
 *
 * @param string $id       Section id.
 * @param array  $sections All sections, for the number and title.
 * @return void
 */
$blueworx_open = static function ( $id, $sections ) {
	$number = array_search( $id, array_keys( $sections ), true ) + 1;

	printf(
		'<section class="bw-ref-sec" id="%1$s" aria-labelledby="%1$s-title"><div class="bw-ref-sec__head"><h2 id="%1$s-title"><b>%2$02d</b>// %3$s</h2></div>',
		esc_attr( $id ),
		(int) $number,
		esc_html( $sections[ $id ] )
	);
};

/**
 * Prints an example frame: a stage, then its caption.
 *
 * @param string $html    The example, already escaped.
 * @param string $caption Caption.
 * @param string $stage   Extra stage class: '', 'surface', 'dark', 'brand', 'col'.
 * @return void
 */
$blueworx_frame = static function ( $html, $caption, $stage = '' ) {
	$classes = 'bw-ref-stage';

	foreach ( array_filter( explode( ' ', $stage ) ) as $modifier ) {
		$classes .= ' bw-ref-stage--' . $modifier;
	}

	printf(
		'<div class="bw-ref-frame"><div class="%1$s">%2$s</div><div class="bw-ref-cap">%3$s</div></div>',
		esc_attr( $classes ),
		$html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts by the caller.
		esc_html( $caption )
	);
};

$blueworx_chevron = blueworx_design_reference_icon( 'expand', 18 );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<main class="bw-ref-main">

	<div class="bw-ref-bar">
		<span class="bw-ref-bar__title">
			<?php
			/* translators: %s: site name. */
			echo esc_html( sprintf( __( '%s · Design system', 'blueworx-labs-wordpress' ), $blueworx_name ) );
			?>
		</span>
		<span class="bw-ref-bar__meta"><?php echo esc_html( 'BlueWorx Enhance ' . BLUEWORX_LABS_VERSION . ' · ' . wp_date( get_option( 'date_format' ) ) ); ?></span>
		<a class="wp-element-button bw-ref-btn--small" href="<?php echo esc_url( blueworx_design_download_url() ); ?>"><?php esc_html_e( 'Download design system', 'blueworx-labs-wordpress' ); ?></a>
	</div>

	<nav class="bw-ref-jump" aria-label="<?php esc_attr_e( 'Sections', 'blueworx-labs-wordpress' ); ?>">
		<?php foreach ( $blueworx_sections as $blueworx_id => $blueworx_title ) : ?>
			<a href="#<?php echo esc_attr( $blueworx_id ); ?>"><?php echo esc_html( $blueworx_title ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php $blueworx_open( 'overview', $blueworx_sections ); ?>
		<div class="bw-ref-hero">
			<?php
			if ( (int) get_theme_mod( 'custom_logo', 0 ) || $blueworx_settings['logo_dark'] ) {
				echo wp_kses_post( blueworx_design_reference_logo( $blueworx_settings['logo_dark'] ? 'dark' : 'logo' ) );
			}
			?>
			<h1><?php echo esc_html( $blueworx_name ); ?></h1>
			<?php if ( '' !== $blueworx_tagline ) : ?>
				<p><?php echo esc_html( $blueworx_tagline ); ?></p>
			<?php endif; ?>
		</div>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede">
				<?php
				/* translators: %s: site name. */
				echo esc_html( sprintf( __( 'This is the design system for %s: its logo, colours, type, spacing and the building blocks every page uses. Each value has a plain name, and every page and plugin on the site uses those names, so the site stays consistent wherever something is added. It is drawn from the settings on BlueWorx › Design, so it always matches the live site.', 'blueworx-labs-wordpress' ), $blueworx_name ) );
				?>
			</p>
		</div>
	</section>

	<?php $blueworx_open( 'colours', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'The main colour leads: buttons, links and anything active. The second colour is for highlights, offers and badges, used sparingly. Neutrals carry text, lines and backgrounds. The badge on each swatch shows how readable text is on it.', 'blueworx-labs-wordpress' ); ?></p>
			<div class="bw-ref-group">
				<span class="bw-ref-label"><?php esc_html_e( 'Brand', 'blueworx-labs-wordpress' ); ?></span>
				<div class="bw-ref-swatches">
					<?php
					foreach ( $blueworx_palette['brand'] as $blueworx_swatch ) {
						echo blueworx_design_reference_swatch( $blueworx_tokens, $blueworx_swatch['token'], $blueworx_swatch['label'], $blueworx_swatch['use'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where it is built.
					}
					?>
				</div>
			</div>
			<div class="bw-ref-group">
				<span class="bw-ref-label"><?php esc_html_e( 'Main colour scale', 'blueworx-labs-wordpress' ); ?></span>
				<div class="bw-ref-scale">
					<?php foreach ( array( 50, 100, 200, 300, 400, 500, 600, 700, 800, 900 ) as $blueworx_step ) : ?>
						<div style="background:var(--color-primary-<?php echo (int) $blueworx_step; ?>);color:<?php echo esc_attr( blueworx_color_text_on( $blueworx_tokens[ '--color-primary-' . $blueworx_step ], $blueworx_tokens['--color-text'] ) ); ?>"><?php echo (int) $blueworx_step; ?></div>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="bw-ref-group">
				<span class="bw-ref-label"><?php esc_html_e( 'Neutrals', 'blueworx-labs-wordpress' ); ?></span>
				<div class="bw-ref-swatches">
					<?php
					foreach ( $blueworx_palette['neutrals'] as $blueworx_swatch ) {
						echo blueworx_design_reference_swatch( $blueworx_tokens, $blueworx_swatch['token'], $blueworx_swatch['label'], $blueworx_swatch['use'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where it is built.
					}
					?>
				</div>
			</div>
			<div class="bw-ref-group">
				<span class="bw-ref-label"><?php esc_html_e( 'Messages', 'blueworx-labs-wordpress' ); ?></span>
				<div class="bw-ref-swatches">
					<?php
					foreach ( $blueworx_palette['messages'] as $blueworx_swatch ) {
						echo blueworx_design_reference_swatch( $blueworx_tokens, $blueworx_swatch['token'], $blueworx_swatch['label'], $blueworx_swatch['use'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where it is built.
					}
					?>
				</div>
			</div>
			<div class="bw-ref-group">
				<span class="bw-ref-label"><?php esc_html_e( 'Readability of every text pairing', 'blueworx-labs-wordpress' ); ?></span>
				<div class="bw-ref-table">
					<table>
						<thead><tr><th><?php esc_html_e( 'Pairing', 'blueworx-labs-wordpress' ); ?></th><th><?php esc_html_e( 'Text', 'blueworx-labs-wordpress' ); ?></th><th><?php esc_html_e( 'On', 'blueworx-labs-wordpress' ); ?></th><th><?php esc_html_e( 'Contrast', 'blueworx-labs-wordpress' ); ?></th><th><?php esc_html_e( 'Result', 'blueworx-labs-wordpress' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( blueworx_design_readability( $blueworx_tokens ) as $blueworx_row ) : ?>
								<tr>
									<td><?php echo esc_html( $blueworx_row['label'] ); ?></td>
									<td class="bw-ref-m"><?php echo esc_html( $blueworx_row['text'] ); ?></td>
									<td class="bw-ref-m"><?php echo esc_html( $blueworx_row['background'] ); ?></td>
									<td class="bw-ref-m"><?php echo esc_html( number_format( $blueworx_row['ratio'], 1 ) . ':1' ); ?></td>
									<td class="<?php echo $blueworx_row['passes'] ? 'bw-ref-pass' : 'bw-ref-fail'; ?>"><?php echo $blueworx_row['passes'] ? esc_html__( 'Reads well', 'blueworx-labs-wordpress' ) : esc_html__( 'Hard to read', 'blueworx-labs-wordpress' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</section>

	<?php $blueworx_open( 'typography', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede">
				<?php
				/* translators: 1: heading font, 2: body font. */
				echo esc_html( sprintf( __( '%1$s sets headings. %2$s carries body text, buttons and forms. Fonts load from the site itself, never from Google. Headings have a desktop and a smaller phone size.', 'blueworx-labs-wordpress' ), $blueworx_heading['label'], $blueworx_body['label'] ) );
				?>
			</p>
			<div class="bw-ref-fonts">
				<?php
				foreach ( array(
					array( __( 'Headings', 'blueworx-labs-wordpress' ), $blueworx_heading, 'var(--font-heading)', 700 ),
					array( __( 'Body', 'blueworx-labs-wordpress' ), $blueworx_body, 'var(--font-body)', 400 ),
				) as $blueworx_font ) :
					$blueworx_weights = $blueworx_font[1]['files'] ? implode( ' · ', array_map( 'absint', array_keys( $blueworx_font[1]['files'] ) ) ) : __( 'Every weight the device has', 'blueworx-labs-wordpress' );
					?>
					<div class="bw-ref-font" style="font-family:<?php echo esc_attr( $blueworx_font[2] ); ?>">
						<span class="bw-ref-label"><?php echo esc_html( $blueworx_font[0] ); ?></span>
						<span class="bw-ref-font__aa" style="font-weight:<?php echo (int) $blueworx_font[3]; ?>">Aa</span>
						<span class="bw-ref-font__name"><?php echo esc_html( $blueworx_font[1]['label'] ); ?></span>
						<span class="bw-ref-font__chars"><?php echo esc_html( $blueworx_weights ); ?></span>
						<span class="bw-ref-font__chars">ABCDEFGHIJKLMNOPQRSTUVWXYZ abcdefghijklmnopqrstuvwxyz 0123456789 £€&amp;?!</span>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="bw-ref-type">
				<?php
				$blueworx_phone = blueworx_design_phone_tokens( $blueworx_settings );

				foreach ( array(
					array( 'Display', '--text-display', '<p style="margin:0;font-family:var(--font-heading);font-weight:700;font-size:var(--text-display);line-height:1.02">' . esc_html__( 'Big ideas, small words', 'blueworx-labs-wordpress' ) . '</p>' ),
					array( 'H1', '--text-h1', '<h1 role="presentation">' . esc_html__( 'Welcome to the site', 'blueworx-labs-wordpress' ) . '</h1>' ),
					array( 'H2', '--text-h2', '<h2 role="presentation">' . esc_html__( 'Our services', 'blueworx-labs-wordpress' ) . '</h2>' ),
					array( 'H3', '--text-h3', '<h3 role="presentation">' . esc_html__( 'How booking works', 'blueworx-labs-wordpress' ) . '</h3>' ),
					array( 'H4', '--text-h4', '<h4 role="presentation">' . esc_html__( 'Frequently asked questions', 'blueworx-labs-wordpress' ) . '</h4>' ),
					array( 'H5', '--text-h5', '<h5 role="presentation">' . esc_html__( 'Opening times', 'blueworx-labs-wordpress' ) . '</h5>' ),
					array( 'H6', '--text-h6', '<h6 role="presentation">' . esc_html__( 'Latest news', 'blueworx-labs-wordpress' ) . '</h6>' ),
					array( __( 'Eyebrow', 'blueworx-labs-wordpress' ), '', '<p class="bw-ref-eyebrow" style="margin:0">' . esc_html__( 'New this season', 'blueworx-labs-wordpress' ) . '</p>' ),
					array( __( 'Lead', 'blueworx-labs-wordpress' ), '--text-lead', '<p class="bw-ref-lead">' . esc_html__( 'An opening paragraph that sits under a page heading and sets the scene.', 'blueworx-labs-wordpress' ) . '</p>' ),
					array( __( 'Body', 'blueworx-labs-wordpress' ), '--text-body', '<p>' . esc_html__( 'The size for most reading on the site. Lines stay under about 70 characters so long paragraphs are comfortable to read.', 'blueworx-labs-wordpress' ) . '</p>' ),
					array( __( 'Link', 'blueworx-labs-wordpress' ), '--text-body', '<p><a href="#typography">' . esc_html__( 'View the full timetable', 'blueworx-labs-wordpress' ) . '</a></p>' ),
					array( __( 'Small', 'blueworx-labs-wordpress' ), '--text-small', '<p><small>' . esc_html__( 'Supporting details, form help and card descriptions.', 'blueworx-labs-wordpress' ) . '</small></p>' ),
					array( __( 'Caption', 'blueworx-labs-wordpress' ), '--text-caption', '<p style="font-size:var(--text-caption);color:var(--color-text-muted)">' . esc_html__( 'Image captions, dates and fine print.', 'blueworx-labs-wordpress' ) . '</p>' ),
				) as $blueworx_type ) :
					$blueworx_size = '' === $blueworx_type[1] ? '12px' : $blueworx_tokens[ $blueworx_type[1] ] . ( isset( $blueworx_phone[ $blueworx_type[1] ] ) ? ' / ' . $blueworx_phone[ $blueworx_type[1] ] : '' );
					?>
					<div class="bw-ref-type__row">
						<div><b><?php echo esc_html( $blueworx_type[0] ); ?></b><br><span class="bw-ref-hex"><?php echo esc_html( ( '' === $blueworx_type[1] ? '' : $blueworx_type[1] . ' · ' ) . $blueworx_size ); ?></span></div>
						<?php echo wp_kses_post( $blueworx_type[2] ); ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<?php $blueworx_open( 'spacing', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'Spacing steps up in a steady rhythm. Small steps space things inside a block; large steps space the sections of a page.', 'blueworx-labs-wordpress' ); ?></p>
			<div class="bw-ref-two">
				<div class="bw-ref-group">
					<span class="bw-ref-label"><?php echo esc_html( sprintf( /* translators: %s: spacing choice, e.g. Standard. */ __( 'Spacing · %s', 'blueworx-labs-wordpress' ), $blueworx_choices['spacing'][ $blueworx_settings['spacing'] ]['label'] ) ); ?></span>
					<?php foreach ( array( 1, 2, 3, 4, 6, 8, 12, 16, 24 ) as $blueworx_step ) : ?>
						<div class="bw-ref-space"><span><?php echo (int) $blueworx_step; ?></span><span><i style="width:var(--space-<?php echo (int) $blueworx_step; ?>)"></i><?php echo esc_html( $blueworx_tokens[ '--space-' . $blueworx_step ] ); ?></span></div>
					<?php endforeach; ?>
				</div>
				<div class="bw-ref-group">
					<span class="bw-ref-label"><?php echo esc_html( sprintf( /* translators: %s: corner choice, e.g. Rounded. */ __( 'Corners · %s', 'blueworx-labs-wordpress' ), $blueworx_choices['corners'][ $blueworx_settings['corners'] ]['label'] ) ); ?></span>
					<div class="bw-ref-radii">
						<?php
						foreach ( array(
							'sm'     => __( 'tags', 'blueworx-labs-wordpress' ),
							'md'     => __( 'inputs', 'blueworx-labs-wordpress' ),
							'lg'     => __( 'cards, images', 'blueworx-labs-wordpress' ),
							'xl'     => __( 'panels', 'blueworx-labs-wordpress' ),
							'button' => __( 'buttons', 'blueworx-labs-wordpress' ),
							'full'   => __( 'pills', 'blueworx-labs-wordpress' ),
						) as $blueworx_radius => $blueworx_use ) :
							?>
							<div><span style="border-radius:var(--radius-<?php echo esc_attr( $blueworx_radius ); ?>)"></span><b><?php echo esc_html( $blueworx_radius ); ?></b><span><?php echo esc_html( $blueworx_tokens[ '--radius-' . $blueworx_radius ] . ' · ' . $blueworx_use ); ?></span></div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>
	</section>

	<?php $blueworx_open( 'layout', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'Three widths cover every page. Reading text stays narrow so lines are comfortable; page content sits in the standard width; full-width images and bands can run wide. The layout changes at two screen sizes.', 'blueworx-labs-wordpress' ); ?></p>
			<div class="bw-ref-two">
				<div class="bw-ref-group">
					<span class="bw-ref-label"><?php esc_html_e( 'Widths', 'blueworx-labs-wordpress' ); ?></span>
					<?php
					foreach ( array(
						'reading' => array( __( 'Reading', 'blueworx-labs-wordpress' ), 50 ),
						'page'    => array( __( 'Page', 'blueworx-labs-wordpress' ), 83 ),
						'wide'    => array( __( 'Wide', 'blueworx-labs-wordpress' ), 100 ),
					) as $blueworx_width => $blueworx_meta ) :
						?>
						<div class="bw-ref-width"><?php echo esc_html( $blueworx_meta[0] ); ?><span style="width:<?php echo (int) $blueworx_meta[1]; ?>%"><?php echo esc_html( $blueworx_tokens[ '--width-' . $blueworx_width ] ); ?></span></div>
					<?php endforeach; ?>
				</div>
				<div class="bw-ref-group">
					<span class="bw-ref-label"><?php esc_html_e( 'Screen sizes', 'blueworx-labs-wordpress' ); ?></span>
					<?php
					foreach ( array(
						array( __( 'Phone, under 640px', 'blueworx-labs-wordpress' ), 4 ),
						array( __( 'Tablet, 640 to 1023px', 'blueworx-labs-wordpress' ), 8 ),
						array( __( 'Desktop, 1024px and up', 'blueworx-labs-wordpress' ), 12 ),
					) as $blueworx_bp ) :
						?>
						<div class="bw-ref-width"><?php echo esc_html( $blueworx_bp[0] ); ?><div class="bw-ref-cols" style="grid-template-columns:repeat(<?php echo (int) $blueworx_bp[1]; ?>,1fr)"><?php echo str_repeat( '<span></span>', (int) $blueworx_bp[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?></div></div>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="bw-ref-table">
				<table>
					<thead><tr><th><?php esc_html_e( 'Named style', 'blueworx-labs-wordpress' ); ?></th><th><?php esc_html_e( 'Value', 'blueworx-labs-wordpress' ); ?></th><th><?php esc_html_e( 'Use', 'blueworx-labs-wordpress' ); ?></th></tr></thead>
					<tbody>
						<tr><td class="bw-ref-m">--gutter</td><td class="bw-ref-m"><?php echo esc_html( $blueworx_tokens['--gutter'] ); ?></td><td><?php esc_html_e( 'The gap at the edges of the page', 'blueworx-labs-wordpress' ); ?></td></tr>
						<tr><td class="bw-ref-m">--section-space</td><td class="bw-ref-m"><?php echo esc_html( $blueworx_tokens['--section-space'] ); ?></td><td><?php esc_html_e( 'The gap between sections of a page', 'blueworx-labs-wordpress' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</section>

	<?php $blueworx_open( 'buttons', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'Use one main button per area, for the main action such as Book now. The outline button is for the other path, and the text button for low-priority links.', 'blueworx-labs-wordpress' ); ?></p>
			<div class="bw-ref-frames">
				<?php
				$blueworx_buttons = '<button type="button" class="bw-ref-btn--primary">' . esc_html__( 'Main', 'blueworx-labs-wordpress' ) . '</button>'
					. '<button type="button" class="bw-ref-btn--secondary">' . esc_html__( 'Outline', 'blueworx-labs-wordpress' ) . '</button>'
					. '<button type="button" class="bw-ref-btn--tertiary">' . esc_html__( 'Text', 'blueworx-labs-wordpress' ) . '</button>';

				$blueworx_frame( $blueworx_buttons, __( 'On the background', 'blueworx-labs-wordpress' ) );
				$blueworx_frame( $blueworx_buttons, __( 'On a dark band', 'blueworx-labs-wordpress' ), 'dark' );
				$blueworx_frame( $blueworx_buttons, __( 'On the main colour', 'blueworx-labs-wordpress' ), 'brand' );
				?>
			</div>
			<div class="bw-ref-frames">
				<?php
				$blueworx_frame(
					'<button type="button">' . esc_html__( 'Default', 'blueworx-labs-wordpress' ) . '</button>'
					. '<button type="button" class="bw-ref-btn--hover">' . esc_html__( 'Hover', 'blueworx-labs-wordpress' ) . '</button>'
					. '<button type="button" class="bw-ref-btn--pressed">' . esc_html__( 'Pressed', 'blueworx-labs-wordpress' ) . '</button>'
					. '<button type="button" disabled>' . esc_html__( 'Not available', 'blueworx-labs-wordpress' ) . '</button>',
					__( 'States', 'blueworx-labs-wordpress' )
				);
				$blueworx_frame(
					'<button type="button" class="bw-ref-btn--small">' . esc_html__( 'Small', 'blueworx-labs-wordpress' ) . '</button>'
					. '<button type="button">' . esc_html__( 'Medium', 'blueworx-labs-wordpress' ) . '</button>'
					. '<button type="button" class="bw-ref-btn--large">' . esc_html__( 'Large', 'blueworx-labs-wordpress' ) . '</button>'
					. '<button type="button">' . esc_html__( 'Continue', 'blueworx-labs-wordpress' ) . ' ' . blueworx_design_reference_icon( 'arrow', 18 ) . '</button>',
					__( 'Sizes, and with an icon', 'blueworx-labs-wordpress' )
				);
				?>
			</div>
		</div>
	</section>

	<?php $blueworx_open( 'inputs', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'Used by sign-in, checkout, contact forms and account pages. Every field has a visible label above it, never just placeholder text. Focus shows a ring so keyboard users always know where they are.', 'blueworx-labs-wordpress' ); ?></p>
			<?php
			$blueworx_frame(
				'<div class="bw-ref-fields">'
				. '<div class="bw-ref-field"><label for="bw-ref-in-1">' . esc_html__( 'Default', 'blueworx-labs-wordpress' ) . '</label><input id="bw-ref-in-1" type="text" placeholder="' . esc_attr__( 'Your full name', 'blueworx-labs-wordpress' ) . '"><span class="bw-ref-help">' . esc_html__( 'Help sits under the field.', 'blueworx-labs-wordpress' ) . '</span></div>'
				. '<div class="bw-ref-field"><label for="bw-ref-in-2">' . esc_html__( 'Focus', 'blueworx-labs-wordpress' ) . '</label><input id="bw-ref-in-2" class="bw-ref-input--focus" type="text" value="Alex Morgan"></div>'
				. '<div class="bw-ref-field"><label for="bw-ref-in-3">' . esc_html__( 'Error', 'blueworx-labs-wordpress' ) . '</label><input id="bw-ref-in-3" class="bw-ref-input--error" type="email" value="alex@" aria-invalid="true" aria-describedby="bw-ref-in-3-help"><span class="bw-ref-help bw-ref-help--error" id="bw-ref-in-3-help">' . esc_html__( 'Enter a full email address, like name@example.com.', 'blueworx-labs-wordpress' ) . '</span></div>'
				. '<div class="bw-ref-field"><label for="bw-ref-in-4">' . esc_html__( 'Not available', 'blueworx-labs-wordpress' ) . '</label><input id="bw-ref-in-4" type="text" value="' . esc_attr__( 'Locked', 'blueworx-labs-wordpress' ) . '" disabled></div>'
				. '<div class="bw-ref-field"><label for="bw-ref-in-5">' . esc_html__( 'Select', 'blueworx-labs-wordpress' ) . '</label><select id="bw-ref-in-5"><option>' . esc_html__( 'Booking enquiry', 'blueworx-labs-wordpress' ) . '</option></select></div>'
				. '<div class="bw-ref-field"><span class="bw-ref-label">' . esc_html__( 'Choices', 'blueworx-labs-wordpress' ) . '</span><div class="bw-ref-choices"><label><input type="checkbox" checked> ' . esc_html__( 'Tick box', 'blueworx-labs-wordpress' ) . '</label><label><input type="radio" name="bw-ref-radio" checked> ' . esc_html__( 'Option', 'blueworx-labs-wordpress' ) . '</label></div></div>'
				. '</div>',
				__( 'Fields', 'blueworx-labs-wordpress' ),
				'col'
			);
			?>
		</div>
	</section>

	<?php $blueworx_open( 'badges', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'Status badges show where something stands, such as an order or a booking. Shop badges flag offers. Empty states explain what will appear and how to start.', 'blueworx-labs-wordpress' ); ?></p>
			<div class="bw-ref-frames">
				<?php
				$blueworx_frame(
					'<span class="bw-ref-badge bw-ref-badge--dot bw-ref-badge--success">' . esc_html__( 'Paid', 'blueworx-labs-wordpress' ) . '</span>'
					. '<span class="bw-ref-badge bw-ref-badge--dot bw-ref-badge--warning">' . esc_html__( 'Pending', 'blueworx-labs-wordpress' ) . '</span>'
					. '<span class="bw-ref-badge bw-ref-badge--dot bw-ref-badge--error">' . esc_html__( 'Failed', 'blueworx-labs-wordpress' ) . '</span>'
					. '<span class="bw-ref-badge bw-ref-badge--dot bw-ref-badge--info">' . esc_html__( 'Processing', 'blueworx-labs-wordpress' ) . '</span>'
					. '<span class="bw-ref-badge bw-ref-badge--dot bw-ref-badge--neutral">' . esc_html__( 'Draft', 'blueworx-labs-wordpress' ) . '</span>',
					__( 'Status', 'blueworx-labs-wordpress' )
				);
				$blueworx_frame(
					'<span class="bw-ref-badge bw-ref-badge--sale">' . esc_html__( 'Sale', 'blueworx-labs-wordpress' ) . '</span>'
					. '<span class="bw-ref-badge bw-ref-badge--new">' . esc_html__( 'New', 'blueworx-labs-wordpress' ) . '</span>'
					. '<span class="bw-ref-badge bw-ref-badge--neutral">' . esc_html__( 'Sold out', 'blueworx-labs-wordpress' ) . '</span>',
					__( 'Shop', 'blueworx-labs-wordpress' )
				);
				?>
			</div>
			<?php
			$blueworx_frame(
				'<div class="bw-ref-empty">' . blueworx_design_reference_icon( 'basket', 28 ) . '<h4 role="presentation">' . esc_html__( 'Your basket is empty', 'blueworx-labs-wordpress' ) . '</h4><p>' . esc_html__( 'Products you add will appear here until you check out.', 'blueworx-labs-wordpress' ) . '</p><button type="button" class="bw-ref-btn--secondary bw-ref-btn--small">' . esc_html__( 'Browse the shop', 'blueworx-labs-wordpress' ) . '</button></div>',
				__( 'Empty state', 'blueworx-labs-wordpress' ),
				'surface'
			);
			?>
		</div>
	</section>

	<?php $blueworx_open( 'alerts', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'Messages after an action, and banners that need attention. Each pairs a colour with an icon and a bold first line, so the meaning never relies on colour alone.', 'blueworx-labs-wordpress' ); ?></p>
			<?php
			$blueworx_alerts = '';

			foreach ( array(
				'success' => array( 'tick', __( 'Booking confirmed', 'blueworx-labs-wordpress' ), __( 'We have emailed your receipt.', 'blueworx-labs-wordpress' ) ),
				'warning' => array( 'info', __( 'Card expires soon', 'blueworx-labs-wordpress' ), __( 'Update it before the end of the month.', 'blueworx-labs-wordpress' ) ),
				'error'   => array( 'close', __( 'Payment failed', 'blueworx-labs-wordpress' ), __( 'Try another card or contact your bank.', 'blueworx-labs-wordpress' ) ),
				'info'    => array( 'info', __( 'Good to know', 'blueworx-labs-wordpress' ), __( 'Orders placed after 2pm ship the next day.', 'blueworx-labs-wordpress' ) ),
			) as $blueworx_tone => $blueworx_alert ) {
				$blueworx_alerts .= '<div class="bw-ref-alert bw-ref-alert--' . esc_attr( $blueworx_tone ) . '" role="status">' . blueworx_design_reference_icon( $blueworx_alert[0] ) . '<p><b>' . esc_html( $blueworx_alert[1] ) . '</b>' . esc_html( $blueworx_alert[2] ) . '</p></div>';
			}

			$blueworx_frame( '<div class="bw-ref-alerts">' . $blueworx_alerts . '</div>', __( 'Messages', 'blueworx-labs-wordpress' ) );
			$blueworx_frame(
				'<div class="bw-ref-overlay"><div class="bw-ref-modal"><b>' . esc_html__( 'Remove this item?', 'blueworx-labs-wordpress' ) . '</b>' . esc_html__( 'It will be taken out of your basket.', 'blueworx-labs-wordpress' ) . '</div></div>',
				/* translators: %s: the overlay colour value. */
				sprintf( __( 'Overlay behind pop-ups and the phone menu · %s', 'blueworx-labs-wordpress' ), $blueworx_tokens['--overlay'] )
			);
			?>
		</div>
	</section>

	<?php $blueworx_open( 'shadows', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'Three levels, lightest to heaviest, plus the focus ring. Most things need none; use them to lift what floats above the page.', 'blueworx-labs-wordpress' ); ?></p>
			<?php
			$blueworx_shadows = '';

			foreach ( array(
				'--shadow-sm'  => array( __( 'Small', 'blueworx-labs-wordpress' ), __( 'Cards on hover, tabs', 'blueworx-labs-wordpress' ) ),
				'--shadow-md'  => array( __( 'Medium', 'blueworx-labs-wordpress' ), __( 'Dropdowns, sticky header', 'blueworx-labs-wordpress' ) ),
				'--shadow-lg'  => array( __( 'Large', 'blueworx-labs-wordpress' ), __( 'Pop-ups and drawers', 'blueworx-labs-wordpress' ) ),
				'--focus-ring' => array( __( 'Focus ring', 'blueworx-labs-wordpress' ), __( 'Any focused control', 'blueworx-labs-wordpress' ) ),
			) as $blueworx_shadow => $blueworx_meta ) {
				$blueworx_shadows .= '<div class="bw-ref-shadow" style="box-shadow:var(' . esc_attr( $blueworx_shadow ) . ')"><b>' . esc_html( $blueworx_meta[0] ) . '</b><span>' . esc_html( $blueworx_tokens[ $blueworx_shadow ] ) . '</span><span>' . esc_html( $blueworx_meta[1] ) . '</span></div>';
			}

			$blueworx_frame( '<div class="bw-ref-shadows">' . $blueworx_shadows . '</div>', __( 'Depth', 'blueworx-labs-wordpress' ), 'surface' );
			?>
		</div>
	</section>

	<?php $blueworx_open( 'cards', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'One card style: a thin line, the large corner and steady padding inside. Plugins use the same card for anything they add, such as a coach or a service.', 'blueworx-labs-wordpress' ); ?></p>
			<?php
			$blueworx_frame(
				'<div class="bw-ref-cards">'
				. '<div class="bw-ref-card"><div class="bw-ref-card__img" role="img" aria-label="' . esc_attr__( 'Product photo', 'blueworx-labs-wordpress' ) . '"><span class="bw-ref-badge bw-ref-badge--sale">' . esc_html__( 'Sale', 'blueworx-labs-wordpress' ) . '</span></div><div class="bw-ref-card__body"><h4>' . esc_html__( 'Waterproof jacket', 'blueworx-labs-wordpress' ) . '</h4><p>' . esc_html__( 'Navy · sizes XS to XXL', 'blueworx-labs-wordpress' ) . '</p><b>£64.00</b></div><div class="bw-ref-card__foot"><button type="button" class="bw-ref-btn--small">' . esc_html__( 'Add to basket', 'blueworx-labs-wordpress' ) . '</button></div></div>'
				. '<div class="bw-ref-card"><div class="bw-ref-card__img" role="img" aria-label="' . esc_attr__( 'Blog post image', 'blueworx-labs-wordpress' ) . '"></div><div class="bw-ref-card__body"><span class="bw-ref-badge bw-ref-badge--info" style="align-self:flex-start">' . esc_html__( 'News', 'blueworx-labs-wordpress' ) . '</span><h4>' . esc_html__( 'Five things to check before you book', 'blueworx-labs-wordpress' ) . '</h4><p>' . esc_html__( 'A short guide to choosing the right session.', 'blueworx-labs-wordpress' ) . '</p></div><div class="bw-ref-card__foot"><a href="#cards">' . esc_html__( 'Read more', 'blueworx-labs-wordpress' ) . '</a></div></div>'
				. '<div class="bw-ref-card"><div class="bw-ref-card__body"><span class="bw-ref-mark">' . blueworx_design_reference_icon( 'calendar', 22 ) . '</span><h4>' . esc_html__( 'General card', 'blueworx-labs-wordpress' ) . '</h4><p>' . esc_html__( 'For services, features or anything a plugin adds.', 'blueworx-labs-wordpress' ) . '</p><a href="#cards">' . esc_html__( 'Find out more', 'blueworx-labs-wordpress' ) . '</a></div></div>'
				. '</div>',
				__( 'Product · Post · General', 'blueworx-labs-wordpress' ),
				'surface'
			);
			?>
		</div>
	</section>

	<?php $blueworx_open( 'navigation', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'Which links appear is set on WordPress\'s Menus screen. How they look comes from here: the current page is underlined in the main colour and dropdowns use the medium shadow.', 'blueworx-labs-wordpress' ); ?></p>
			<?php
			$blueworx_frame(
				'<div class="bw-ref-sitebar">' . blueworx_design_reference_logo( 'logo' )
				. '<nav aria-label="' . esc_attr__( 'Example menu', 'blueworx-labs-wordpress' ) . '"><ul><li><a href="#navigation" aria-current="page">' . esc_html__( 'Home', 'blueworx-labs-wordpress' ) . '</a></li><li><a href="#navigation">' . esc_html__( 'Services', 'blueworx-labs-wordpress' ) . '</a></li><li><a href="#navigation">' . esc_html__( 'Shop', 'blueworx-labs-wordpress' ) . '</a></li><li><a href="#navigation">' . esc_html__( 'Contact', 'blueworx-labs-wordpress' ) . '</a></li></ul></nav>'
				. '<button type="button" class="bw-ref-btn--small" style="margin-left:auto">' . esc_html__( 'Book now', 'blueworx-labs-wordpress' ) . '</button></div>'
				. '<div class="bw-ref-two" style="width:100%"><div class="bw-ref-group"><span class="bw-ref-label">' . esc_html__( 'Dropdown', 'blueworx-labs-wordpress' ) . '</span><div class="bw-ref-dropdown"><a href="#navigation">' . esc_html__( 'Consultations', 'blueworx-labs-wordpress' ) . '<span>' . esc_html__( 'One-to-one, in person or online', 'blueworx-labs-wordpress' ) . '</span></a><a href="#navigation">' . esc_html__( 'Group sessions', 'blueworx-labs-wordpress' ) . '<span>' . esc_html__( 'Weekly classes for all levels', 'blueworx-labs-wordpress' ) . '</span></a></div></div>'
				. '<div class="bw-ref-group"><span class="bw-ref-label">' . esc_html__( 'Breadcrumbs', 'blueworx-labs-wordpress' ) . '</span><nav class="bw-ref-crumbs" aria-label="' . esc_attr__( 'Example breadcrumbs', 'blueworx-labs-wordpress' ) . '"><a href="#navigation">' . esc_html__( 'Home', 'blueworx-labs-wordpress' ) . '</a><span aria-hidden="true">/</span><a href="#navigation">' . esc_html__( 'Shop', 'blueworx-labs-wordpress' ) . '</a><span aria-hidden="true">/</span><b>' . esc_html__( 'Waterproof jacket', 'blueworx-labs-wordpress' ) . '</b></nav></div></div>',
				__( 'Header, dropdown, breadcrumbs', 'blueworx-labs-wordpress' ),
				'surface col'
			);
			?>
		</div>
	</section>

	<?php $blueworx_open( 'faqs', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'Questions and table contents live on each page. The theme sets how WordPress\'s own Details and Table blocks look, so every FAQ and table on the site matches.', 'blueworx-labs-wordpress' ); ?></p>
			<?php
			$blueworx_frame(
				'<div class="bw-ref-faq">'
				. '<details open><summary>' . esc_html__( 'Can I change my booking?', 'blueworx-labs-wordpress' ) . '</summary><p>' . esc_html__( 'Yes. You can move a booking up to 24 hours before it starts, from your account page.', 'blueworx-labs-wordpress' ) . '</p></details>'
				. '<details><summary>' . esc_html__( 'Do you offer refunds?', 'blueworx-labs-wordpress' ) . '</summary><p>' . esc_html__( 'Unused bookings are refunded in full up to 48 hours before the start time.', 'blueworx-labs-wordpress' ) . '</p></details>'
				. '</div>',
				__( 'FAQ · the Details block', 'blueworx-labs-wordpress' ),
				'col'
			);
			$blueworx_frame(
				'<figure class="wp-block-table" style="margin:0;overflow-x:auto"><table><thead><tr><th>' . esc_html__( 'Day', 'blueworx-labs-wordpress' ) . '</th><th>' . esc_html__( 'Opens', 'blueworx-labs-wordpress' ) . '</th><th>' . esc_html__( 'Closes', 'blueworx-labs-wordpress' ) . '</th></tr></thead><tbody>'
				. '<tr><td>' . esc_html__( 'Monday to Friday', 'blueworx-labs-wordpress' ) . '</td><td>06:00</td><td>22:00</td></tr>'
				. '<tr><td>' . esc_html__( 'Saturday', 'blueworx-labs-wordpress' ) . '</td><td>07:00</td><td>21:00</td></tr>'
				. '</tbody></table></figure>',
				__( 'Table · the Table block', 'blueworx-labs-wordpress' ),
				'col'
			);
			?>
		</div>
	</section>

	<?php $blueworx_open( 'images', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'Four shapes keep image grids tidy. Images take the large corner unless they run edge to edge. Text over a photo always sits on a dark fade so it stays readable.', 'blueworx-labs-wordpress' ); ?></p>
			<?php
			$blueworx_frame(
				'<div class="bw-ref-ratios">'
				. '<div class="bw-ref-ratio"><div style="aspect-ratio:16/9"></div>' . esc_html__( '16:9 · banners, video', 'blueworx-labs-wordpress' ) . '</div>'
				. '<div class="bw-ref-ratio"><div style="aspect-ratio:4/3"></div>' . esc_html__( '4:3 · cards, posts', 'blueworx-labs-wordpress' ) . '</div>'
				. '<div class="bw-ref-ratio"><div style="aspect-ratio:1/1"></div>' . esc_html__( '1:1 · products', 'blueworx-labs-wordpress' ) . '</div>'
				. '<div class="bw-ref-ratio"><div style="aspect-ratio:3/4"></div>' . esc_html__( '3:4 · people', 'blueworx-labs-wordpress' ) . '</div>'
				. '</div>',
				__( 'Shapes', 'blueworx-labs-wordpress' ),
				'col'
			);
			$blueworx_frame(
				'<div class="bw-ref-photo" role="img" aria-label="' . esc_attr__( 'Example photo with a heading over it', 'blueworx-labs-wordpress' ) . '"><div><b>' . esc_html__( 'Summer opening hours', 'blueworx-labs-wordpress' ) . '</b>' . esc_html__( 'Open until 10pm from 1 June', 'blueworx-labs-wordpress' ) . '</div></div>',
				__( 'Text over images', 'blueworx-labs-wordpress' ),
				'col'
			);
			?>
		</div>
	</section>

	<?php $blueworx_open( 'dark', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'Dark bands break up long pages and suit footers and calls to action. They use the text colour as the background, with the page colour for words.', 'blueworx-labs-wordpress' ); ?></p>
			<?php
			$blueworx_frame(
				'<div class="bw-ref-band"><div><p class="bw-ref-eyebrow" style="color:inherit;margin:0">' . esc_html__( 'Join today', 'blueworx-labs-wordpress' ) . '</p><h3>' . esc_html__( 'Your first session is on us', 'blueworx-labs-wordpress' ) . '</h3><p>' . esc_html__( 'Try any group session free, with no commitment.', 'blueworx-labs-wordpress' ) . ' <a href="#dark">' . esc_html__( 'See the timetable', 'blueworx-labs-wordpress' ) . '</a></p><button type="button">' . esc_html__( 'Book a free session', 'blueworx-labs-wordpress' ) . '</button> <button type="button" class="bw-ref-btn--secondary">' . esc_html__( 'Talk to us', 'blueworx-labs-wordpress' ) . '</button></div>'
				. '<div class="bw-ref-band__panel"><b>4.9 / 5</b><span>' . esc_html__( 'Average rating from 1,240 reviews', 'blueworx-labs-wordpress' ) . '</span></div></div>',
				__( 'Dark band, with a raised panel', 'blueworx-labs-wordpress' ),
				'col'
			);
			?>
		</div>
	</section>

	<?php $blueworx_open( 'icons', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'One icon set, Lucide, drawn with rounded lines. Icons take the colour of the text beside them. Pair an icon with a word wherever there is room.', 'blueworx-labs-wordpress' ); ?></p>
			<div class="bw-ref-icons">
				<?php foreach ( array_keys( blueworx_design_reference_icons() ) as $blueworx_icon ) : ?>
					<div class="bw-ref-icon"><?php echo blueworx_design_reference_icon( $blueworx_icon, 24 ) . esc_html( $blueworx_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG from the theme. ?></div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<?php $blueworx_open( 'logo', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'Set on BlueWorx › Design: the main logo, a version for dark backgrounds and a small mark. Until one is set, the site\'s first letter on the main colour stands in.', 'blueworx-labs-wordpress' ); ?></p>
			<div class="bw-ref-frames">
				<?php
				$blueworx_frame( blueworx_design_reference_logo( 'logo' ), __( 'Main logo, on the background', 'blueworx-labs-wordpress' ) );
				$blueworx_frame( blueworx_design_reference_logo( 'dark' ), __( 'For dark backgrounds', 'blueworx-labs-wordpress' ), 'dark' );
				$blueworx_frame( blueworx_design_reference_logo( 'mark' ), __( 'Small mark', 'blueworx-labs-wordpress' ) );
				$blueworx_frame(
					$blueworx_icon_url ? '<img src="' . esc_url( $blueworx_icon_url ) . '" alt="" width="64" height="64">' : '<span class="bw-ref-help">' . esc_html__( 'Not set yet. Set it under Settings, General.', 'blueworx-labs-wordpress' ) . '</span>',
					__( 'Browser tab icon', 'blueworx-labs-wordpress' )
				);
				?>
			</div>
			<ul class="bw-ref-help">
				<li><?php esc_html_e( 'Leave clear space around the logo at least the height of the mark.', 'blueworx-labs-wordpress' ); ?></li>
				<li><?php esc_html_e( 'Never stretch it, recolour it or put it on a busy photo.', 'blueworx-labs-wordpress' ); ?></li>
				<li><?php esc_html_e( 'Use the dark-background version on dark bands and the main colour.', 'blueworx-labs-wordpress' ); ?></li>
			</ul>
		</div>
	</section>

	<?php $blueworx_open( 'usage', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'The rules that keep pages consistent, for anyone building a page or a plugin on this site.', 'blueworx-labs-wordpress' ); ?></p>
			<div class="bw-ref-dos">
				<div class="bw-ref-do bw-ref-do--yes">
					<h4><?php esc_html_e( 'Do', 'blueworx-labs-wordpress' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'Use the named styles for every colour, font, size and gap.', 'blueworx-labs-wordpress' ); ?></li>
						<li><?php esc_html_e( 'Keep one main button per area.', 'blueworx-labs-wordpress' ); ?></li>
						<li><?php esc_html_e( 'Pair colour with an icon or words for every status.', 'blueworx-labs-wordpress' ); ?></li>
						<li><?php esc_html_e( 'Keep reading text within the reading width.', 'blueworx-labs-wordpress' ); ?></li>
						<li><?php esc_html_e( 'Give every form field a visible label.', 'blueworx-labs-wordpress' ); ?></li>
					</ul>
				</div>
				<div class="bw-ref-do bw-ref-do--no">
					<h4><?php esc_html_e( 'Don\'t', 'blueworx-labs-wordpress' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'Type colours, fonts or sizes into a page or plugin by hand.', 'blueworx-labs-wordpress' ); ?></li>
						<li><?php esc_html_e( 'Use the second colour for text or large areas.', 'blueworx-labs-wordpress' ); ?></li>
						<li><?php esc_html_e( 'Rely on placeholder text instead of a label.', 'blueworx-labs-wordpress' ); ?></li>
						<li><?php esc_html_e( 'Add a new shade without adding it to the design system first.', 'blueworx-labs-wordpress' ); ?></li>
					</ul>
				</div>
			</div>
		</div>
	</section>

	<?php $blueworx_open( 'tokens', $blueworx_sections ); ?>
		<div class="bw-ref-sec__body">
			<p class="bw-ref-lede"><?php esc_html_e( 'Every value on this page under the name a page or plugin uses. The download will carry this same file.', 'blueworx-labs-wordpress' ); ?></p>
			<?php $blueworx_file = blueworx_design_tokens_file( $blueworx_settings ); ?>
			<div class="bw-ref-code">
				<textarea id="bw-ref-tokens-copy" hidden readonly><?php echo esc_textarea( $blueworx_file ); ?></textarea>
				<button type="button" class="bw-ref-copy bw-ref-btn--secondary bw-ref-btn--small" data-blueworx-copy="bw-ref-tokens-copy" data-copied-label="<?php esc_attr_e( 'Copied', 'blueworx-labs-wordpress' ); ?>"><?php esc_html_e( 'Copy', 'blueworx-labs-wordpress' ); ?></button>
				<pre><?php echo esc_html( $blueworx_file ); ?></pre>
			</div>
		</div>
	</section>

</main>
<?php wp_footer(); ?>
</body>
</html>
