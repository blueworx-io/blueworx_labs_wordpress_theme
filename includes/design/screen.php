<?php
/**
 * Site design system: the BlueWorx > Design screen.
 *
 * Where the team sets the site's brand and look. The screen itself keeps the
 * BlueWorx admin design; only the site's public pages take the choices made
 * here.
 *
 * One form and one handler. Applying a preset is a second button on the same
 * form rather than a form of its own, because the page body already sits inside
 * the save form and forms cannot nest.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin allow-list, plus what this screen alone draws: logo previews, and
 * the attributes its script pairs controls by.
 *
 * @return array wp_kses allow-list.
 */
function blueworx_design_screen_allowed_html() {
	$allowed = blueworx_ds_allowed_html();
	$pairing = array(
		'data-blueworx-color-for' => true,
		'data-blueworx-media'     => true,
		'data-blueworx-clear'     => true,
	);

	foreach ( array( 'input', 'button', 'span' ) as $tag ) {
		$allowed[ $tag ] = array_merge( isset( $allowed[ $tag ] ) ? $allowed[ $tag ] : array(), $pairing );
	}

	$allowed['img'] = array(
		'src'   => true,
		'alt'   => true,
		'class' => true,
		'id'    => true,
	);

	return $allowed;
}

/**
 * Loads the screen's script, and WordPress's media picker for the logos.
 *
 * @return void
 */
function blueworx_design_screen_assets() {
	if ( ! isset( $_GET['page'] ) || 'blueworx-design' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks which screen's assets to load.
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'blueworx-design-screen',
		BLUEWORX_LABS_URL . 'assets/js/design-screen.js',
		array(),
		blueworx_get_admin_asset_version( 'assets/js/design-screen.js' ),
		true
	);
	wp_localize_script(
		'blueworx-design-screen',
		'blueworxDesignScreen',
		array(
			'choose' => __( 'Choose an image', 'blueworx-labs-wordpress' ),
			'use'    => __( 'Use this image', 'blueworx-labs-wordpress' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'blueworx_design_screen_assets' );

/**
 * Checks a request to change the design, then lets it through.
 *
 * @param string $action The nonce action.
 * @return void
 */
function blueworx_design_check_request( $action ) {
	blueworx_require_post_request();

	if ( ! current_user_can( blueworx_design_capability() ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( $action );
}

/**
 * Clears cached pages, leaves a notice, and goes back to the screen.
 *
 * @param string $notice What to say: 'saved', 'preset:<id>' or 'preset-missing'.
 * @return void
 */
function blueworx_design_finish_request( $notice ) {
	if ( function_exists( 'blueworx_purge_site_cache' ) ) {
		blueworx_purge_site_cache( 'design' );
	}

	set_transient( 'blueworx_design_notice_' . get_current_user_id(), $notice, 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-design' ) );
	exit;
}

/**
 * Saves the Design screen.
 *
 * @return void
 */
function blueworx_design_handle_save() {
	blueworx_design_check_request( 'blueworx_save_design' );

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in blueworx_design_check_request().
	$raw = isset( $_POST['blueworx_design'] ) && is_array( $_POST['blueworx_design'] ) ? wp_unslash( $_POST['blueworx_design'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every key is cleaned by blueworx_design_sanitize().

	blueworx_design_save( $raw );

	if ( isset( $_POST['blueworx_design_site_name'] ) ) {
		update_option( 'blogname', sanitize_text_field( wp_unslash( $_POST['blueworx_design_site_name'] ) ) );
	}

	if ( isset( $_POST['blueworx_design_logo'] ) ) {
		$logo = absint( $_POST['blueworx_design_logo'] );

		if ( $logo ) {
			set_theme_mod( 'custom_logo', $logo );
		} else {
			remove_theme_mod( 'custom_logo' );
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	blueworx_design_finish_request( 'saved' );
}
add_action( 'admin_post_blueworx_save_design', 'blueworx_design_handle_save' );

/**
 * Applies a preset from the Design screen.
 *
 * A form of its own, in the page header, so pressing Enter in the settings
 * form can only ever save it — never replace the look with a preset.
 *
 * @return void
 */
function blueworx_design_handle_preset() {
	blueworx_design_check_request( 'blueworx_apply_design_preset' );

	// Only ever looked up among the registered presets, so it is used as sent.
	$preset = isset( $_POST['blueworx_design_preset'] ) && is_string( $_POST['blueworx_design_preset'] ) ? wp_unslash( $_POST['blueworx_design_preset'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked above; matched against preset ids, never output or stored as sent.

	blueworx_design_finish_request( blueworx_design_apply_preset( $preset ) ? 'preset:' . $preset : 'preset-missing' );
}
add_action( 'admin_post_blueworx_apply_design_preset', 'blueworx_design_handle_preset' );

/**
 * A colour field: the browser's picker paired with a hex box.
 *
 * The hex box is the one that is sent, so the screen still works without its
 * script, and an empty box can mean "work it out".
 *
 * @param string $key      Setting name.
 * @param string $value    Current colour, or empty.
 * @param string $fallback What the picker shows while the box is empty.
 * @param string $empty    Placeholder for an empty box; empty when one is required.
 * @return string HTML.
 */
function blueworx_design_color_control( $key, $value, $fallback, $empty = '' ) {
	$id    = 'blueworx_design_' . $key;
	$attrs = array(
		'autocomplete'            => 'off',
		'data-blueworx-color-for' => $id,
		'aria-label'              => __( 'Hex code', 'blueworx-labs-wordpress' ),
	);

	if ( '' !== $empty ) {
		$attrs['placeholder'] = $empty;
	}

	// Written out rather than through blueworx_ds_input(): the swatch is the
	// colour field's own control, not a bw-input.
	return '<span class="bw-colorfield">'
		. sprintf(
			'<input type="color" class="bw-colorfield__swatch" id="%1$s" value="%2$s" data-blueworx-color-for="%1$s_hex" aria-label="%3$s" />',
			esc_attr( $id ),
			esc_attr( '' !== $value ? $value : $fallback ),
			esc_attr__( 'Pick a colour', 'blueworx-labs-wordpress' )
		)
		. '<span class="bw-colorfield__hex">'
		. blueworx_ds_input(
			array(
				'name'  => 'blueworx_design[' . $key . ']',
				'id'    => $id . '_hex',
				'value' => $value,
				'mono'  => true,
				'attrs' => $attrs,
			)
		)
		. '</span></span>';
}

/**
 * A logo picker, in the design system's media field.
 *
 * @param string $name  Field name.
 * @param string $id    Element id.
 * @param int    $value Attachment id, or 0.
 * @param string $hint  What the logo is for.
 * @return string HTML.
 */
function blueworx_design_media_control( $name, $id, $value, $hint ) {
	$src = $value ? (string) wp_get_attachment_image_url( $value, 'medium' ) : '';

	$preview = '' !== $src
		? sprintf( '<img class="bw-media__img" id="%1$s_preview" src="%2$s" alt="" />', esc_attr( $id ), esc_url( $src ) )
		: sprintf( '<span class="bw-media__empty" id="%1$s_preview">%2$s</span>', esc_attr( $id ), blueworx_ds_icon( 'image', 20 ) );

	return '<span class="bw-media">'
		. $preview
		. blueworx_ds_input(
			array(
				'type'  => 'hidden',
				'name'  => $name,
				'id'    => $id,
				'value' => $value ? (string) $value : '',
			)
		)
		. '<span class="bw-media__body"><span class="bw-media__hint">' . esc_html( $hint ) . '</span><span class="bw-media__actions">'
		. blueworx_ds_button(
			array(
				'label' => __( 'Choose image', 'blueworx-labs-wordpress' ),
				'size'  => 'sm',
				'icon'  => 'upload',
				'attrs' => array( 'data-blueworx-media' => $id ),
			)
		)
		. blueworx_ds_button(
			array(
				'label'   => __( 'Remove', 'blueworx-labs-wordpress' ),
				'size'    => 'sm',
				'variant' => 'link',
				'attrs'   => array( 'data-blueworx-clear' => $id ),
			)
		)
		. '</span></span></span>';
}

/**
 * A select of one setting's choices.
 *
 * @param string $key      Setting name.
 * @param array  $options  Value => label.
 * @param string $selected Current value.
 * @return string HTML.
 */
function blueworx_design_select_control( $key, $options, $selected ) {
	return blueworx_ds_select(
		array(
			'name'     => 'blueworx_design[' . $key . ']',
			'id'       => 'blueworx_design_' . $key,
			'options'  => $options,
			'selected' => $selected,
		)
	);
}

/**
 * The notice left by the last save, if any.
 *
 * @return string HTML.
 */
function blueworx_design_saved_notice() {
	$key    = 'blueworx_design_notice_' . get_current_user_id();
	$notice = get_transient( $key );

	if ( false === $notice ) {
		return '';
	}

	delete_transient( $key );

	if ( 'preset-missing' === $notice ) {
		return blueworx_ds_notice(
			array(
				'tone' => 'danger',
				'text' => __( 'That preset is no longer available, so nothing was changed.', 'blueworx-labs-wordpress' ),
			)
		);
	}

	if ( 0 === strpos( (string) $notice, 'preset:' ) ) {
		$presets = blueworx_design_presets();
		$id      = substr( $notice, 7 );
		$label   = isset( $presets[ $id ]['label'] ) ? $presets[ $id ]['label'] : $id;

		return blueworx_ds_notice(
			array(
				'tone' => 'success',
				/* translators: %s: preset name. */
				'text' => sprintf( __( 'Saved. Preset applied: %s.', 'blueworx-labs-wordpress' ), $label ),
			)
		);
	}

	return blueworx_ds_notice(
		array(
			'tone' => 'success',
			'text' => __( 'Saved. Every public page now uses these settings.', 'blueworx-labs-wordpress' ),
		)
	);
}

/**
 * Flags every text pairing that does not read, whenever there is one.
 *
 * Shown on every visit rather than only after a save, so a problem cannot be
 * missed by whoever opens the screen next.
 *
 * @param array $tokens Named styles.
 * @return string HTML.
 */
function blueworx_design_readability_notice( $tokens ) {
	$failing = array();

	foreach ( blueworx_design_readability( $tokens ) as $row ) {
		if ( ! $row['passes'] ) {
			/* translators: 1: a colour pairing, e.g. "Links on the background", 2: its contrast, e.g. 3.1. */
			$failing[] = sprintf( __( '%1$s (%2$s:1)', 'blueworx-labs-wordpress' ), $row['label'], $row['ratio'] );
		}
	}

	if ( ! $failing ) {
		return '';
	}

	return blueworx_ds_notice(
		array(
			'tone'  => 'warning',
			'title' => __( 'Hard to read', 'blueworx-labs-wordpress' ),
			'text'  => sprintf(
				/* translators: %s: the pairings that fail, e.g. "Body text on the background (1.5:1)". */
				__( 'These fall below the 4.5:1 contrast people need to read comfortably: %s. The settings are saved; pick a darker or lighter colour to fix them.', 'blueworx-labs-wordpress' ),
				implode( '; ', $failing )
			),
		)
	);
}

/**
 * Renders the Design screen.
 *
 * @return void
 */
function blueworx_render_design_page() {
	$s       = blueworx_design_settings();
	$choices = blueworx_design_choices();
	$presets = blueworx_design_presets();
	$allowed = blueworx_design_screen_allowed_html();
	$actions = blueworx_ds_button(
		array(
			'label' => __( 'View design system', 'blueworx-labs-wordpress' ),
			'icon'  => 'eye',
			'href'  => blueworx_design_reference_url(),
		)
	);

	// The preset picker is a form of its own in the header, outside the settings
	// form, so Enter pressed in a settings box can only ever save.
	if ( $presets ) {
		$options = array();

		foreach ( $presets as $id => $preset ) {
			$options[ $id ] = $preset['label'] ?? $id;
		}

		$actions .= sprintf(
			'<form method="post" action="%1$s"><input type="hidden" name="action" value="blueworx_apply_design_preset" />%2$s',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'blueworx_apply_design_preset', '_wpnonce', true, false )
		)
			. blueworx_ds_select(
				array(
					'name'     => 'blueworx_design_preset',
					'id'       => 'blueworx_design_preset',
					'options'  => $options,
					'selected' => $s['preset'],
					'attrs'    => array( 'aria-label' => __( 'Preset', 'blueworx-labs-wordpress' ) ),
				)
			)
			. ' '
			. blueworx_ds_button(
				array(
					'label' => __( 'Use this preset', 'blueworx-labs-wordpress' ),
					'type'  => 'submit',
					'attrs' => array( 'title' => __( 'Replaces the colours, fonts and shapes below. The logos and links stay.', 'blueworx-labs-wordpress' ) ),
				)
			)
			. '</form>';
	}

	blueworx_open_admin_page(
		array(
			'title'   => __( 'Design', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'The site\'s brand and look. Every public page, the editor and our plugins take their colours, fonts, sizes and corners from here.', 'blueworx-labs-wordpress' ),
			'actions' => $actions,
			// novalidate: the server cleans every value, and a browser refusing to
			// send the form would hide that from whoever pressed Save.
			'form'    => sprintf(
				'<form method="post" action="%1$s" novalidate><input type="hidden" name="action" value="blueworx_save_design" />%2$s',
				esc_url( admin_url( 'admin-post.php' ) ),
				wp_nonce_field( 'blueworx_save_design', '_wpnonce', true, false )
			),
		)
	);


	$notices = blueworx_design_saved_notice() . blueworx_design_readability_notice( blueworx_design_tokens( $s ) );

	if ( ! blueworx_feature_enabled( 'site_styles' ) ) {
		$notices .= blueworx_ds_notice(
			array(
				'tone' => 'info',
				'text' => __( 'Site styles are off, so public pages only get these settings where a plugin uses them. Switch on Site styles under Enhancements, Appearance, to style the whole site.', 'blueworx-labs-wordpress' ),
			)
		);
	}

	echo wp_kses( $notices, $allowed );

	$html = '';

	$brand = blueworx_ds_field(
		array(
			'label'   => __( 'Site name', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_design_site_name',
			'control' => blueworx_ds_input(
				array(
					'name'  => 'blueworx_design_site_name',
					'id'    => 'blueworx_design_site_name',
					'value' => get_option( 'blogname' ),
				)
			),
			'wide'    => true,
		)
	)
	. blueworx_ds_field(
		array(
			'label'   => __( 'Logo', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_design_logo',
			'control' => blueworx_design_media_control( 'blueworx_design_logo', 'blueworx_design_logo', (int) get_theme_mod( 'custom_logo', 0 ), __( 'The main logo, for light backgrounds. SVG or PNG.', 'blueworx-labs-wordpress' ) ),
			'wide'    => true,
		)
	)
	. blueworx_ds_field(
		array(
			'label'   => __( 'Logo for dark backgrounds', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_design_logo_dark',
			'control' => blueworx_design_media_control( 'blueworx_design[logo_dark]', 'blueworx_design_logo_dark', $s['logo_dark'], __( 'Usually a white version.', 'blueworx-labs-wordpress' ) ),
			'wide'    => true,
		)
	)
	. blueworx_ds_field(
		array(
			'label'   => __( 'Small mark', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_design_logo_mark',
			'control' => blueworx_design_media_control( 'blueworx_design[logo_mark]', 'blueworx_design_logo_mark', $s['logo_mark'], __( 'The symbol on its own, for tight spaces.', 'blueworx-labs-wordpress' ) ),
			'wide'    => true,
		)
	)
	. blueworx_ds_field(
		array(
			'label'   => __( 'Favicon', 'blueworx-labs-wordpress' ),
			'control' => blueworx_ds_button(
				array(
					'label'   => __( 'Set the site icon under Settings, General', 'blueworx-labs-wordpress' ),
					'variant' => 'link',
					'href'    => admin_url( 'options-general.php' ),
				)
			),
			'help'    => __( 'WordPress keeps the browser tab icon there.', 'blueworx-labs-wordpress' ),
			'wide'    => true,
		)
	);

	foreach ( blueworx_design_social_networks() as $network => $label ) {
		$brand .= blueworx_ds_field(
			array(
				'label'   => $label,
				'for'     => 'blueworx_design_social_' . $network,
				'control' => blueworx_ds_input(
					array(
						'type'  => 'url',
						'name'  => 'blueworx_design[social][' . $network . ']',
						'id'    => 'blueworx_design_social_' . $network,
						'value' => isset( $s['social'][ $network ] ) ? $s['social'][ $network ] : '',
						'attrs' => array( 'placeholder' => 'https://' ),
					)
				),
			)
		);
	}

	$html .= blueworx_ds_card(
		array(
			'title' => __( 'Brand', 'blueworx-labs-wordpress' ),
			'body'  => '<div class="bw-fields">' . $brand . '</div>',
		)
	);

	$html .= blueworx_ds_card(
		array(
			'title' => __( 'Colours', 'blueworx-labs-wordpress' ),
			'note'  => __( 'Every other shade is worked out from these four, so text stays readable.', 'blueworx-labs-wordpress' ),
			'body'  => '<div class="bw-fields">'
				. blueworx_ds_field(
					array(
						'label'   => __( 'Main colour', 'blueworx-labs-wordpress' ),
						'for'     => 'blueworx_design_primary_hex',
						'control' => blueworx_design_color_control( 'primary', $s['primary'], $s['primary'] ),
						'help'    => __( 'Buttons, links and highlights.', 'blueworx-labs-wordpress' ),
					)
				)
				. blueworx_ds_field(
					array(
						'label'   => __( 'Second colour', 'blueworx-labs-wordpress' ),
						'for'     => 'blueworx_design_accent_hex',
						'control' => blueworx_design_color_control( 'accent', $s['accent'], $s['primary'], __( 'Automatic', 'blueworx-labs-wordpress' ) ),
						'help'    => __( 'Badges and offers. Leave it empty and one is picked to go with the main colour.', 'blueworx-labs-wordpress' ),
					)
				)
				. blueworx_ds_field(
					array(
						'label'   => __( 'Background', 'blueworx-labs-wordpress' ),
						'for'     => 'blueworx_design_background_hex',
						'control' => blueworx_design_color_control( 'background', $s['background'], $s['background'] ),
					)
				)
				. blueworx_ds_field(
					array(
						'label'   => __( 'Text', 'blueworx-labs-wordpress' ),
						'for'     => 'blueworx_design_text_hex',
						'control' => blueworx_design_color_control( 'text', $s['text'], $s['text'] ),
					)
				)
				. '</div>',
		)
	);

	$fonts = array();

	foreach ( blueworx_design_fonts() as $id => $font ) {
		$fonts[ $id ] = isset( $font['label'] ) ? $font['label'] : $id;
	}

	$html .= blueworx_ds_card(
		array(
			'title' => __( 'Fonts and type', 'blueworx-labs-wordpress' ),
			'body'  => '<div class="bw-fields">'
				. blueworx_ds_field(
					array(
						'label'   => __( 'Heading font', 'blueworx-labs-wordpress' ),
						'for'     => 'blueworx_design_heading_font',
						'control' => blueworx_design_select_control( 'heading_font', $fonts, $s['heading_font'] ),
					)
				)
				. blueworx_ds_field(
					array(
						'label'   => __( 'Body font', 'blueworx-labs-wordpress' ),
						'for'     => 'blueworx_design_body_font',
						'control' => blueworx_design_select_control( 'body_font', $fonts, $s['body_font'] ),
					)
				)
				. blueworx_ds_field(
					array(
						'label'   => __( 'Heading sizes', 'blueworx-labs-wordpress' ),
						'for'     => 'blueworx_design_type_scale',
						'control' => blueworx_design_select_control( 'type_scale', wp_list_pluck( $choices['type_scale'], 'label' ), $s['type_scale'] ),
						'help'    => __( 'Body text stays the same size.', 'blueworx-labs-wordpress' ),
					)
				)
				. '</div>',
		)
	);

	$html .= blueworx_ds_card(
		array(
			'title' => __( 'Spacing, corners and buttons', 'blueworx-labs-wordpress' ),
			'body'  => '<div class="bw-fields">'
				. blueworx_ds_field(
					array(
						'label'   => __( 'Spacing', 'blueworx-labs-wordpress' ),
						'for'     => 'blueworx_design_spacing',
						'control' => blueworx_design_select_control( 'spacing', wp_list_pluck( $choices['spacing'], 'label' ), $s['spacing'] ),
					)
				)
				. blueworx_ds_field(
					array(
						'label'   => __( 'Corners', 'blueworx-labs-wordpress' ),
						'for'     => 'blueworx_design_corners',
						'control' => blueworx_design_select_control( 'corners', wp_list_pluck( $choices['corners'], 'label' ), $s['corners'] ),
					)
				)
				. blueworx_ds_field(
					array(
						'label'   => __( 'Buttons', 'blueworx-labs-wordpress' ),
						'for'     => 'blueworx_design_buttons',
						'control' => blueworx_design_select_control( 'buttons', wp_list_pluck( $choices['buttons'], 'label' ), $s['buttons'] ),
					)
				)
				. '</div>',
		)
	);

	echo wp_kses( $html, $allowed );

	$savebar = '<div class="bw-savebar"><span class="bw-savebar__hint">'
		. esc_html__( 'Nothing changes until you save.', 'blueworx-labs-wordpress' )
		. '</span>'
		. blueworx_ds_button(
			array(
				'label'   => __( 'Save changes', 'blueworx-labs-wordpress' ),
				'variant' => 'primary',
				'type'    => 'submit',
			)
		)
		. '</div></form>';

	blueworx_close_admin_page( $savebar );
}
