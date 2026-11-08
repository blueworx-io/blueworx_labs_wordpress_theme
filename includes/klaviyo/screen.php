<?php
/**
 * Klaviyo connection: the settings screen and the log screen.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saves the settings screen, and runs the connection test if that was asked for.
 *
 * @return void
 */
function blueworx_klaviyo_handle_save() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_save_klaviyo' );

	$refused = array();

	// Tidied by hand rather than with sanitize_text_field(): a key is checked
	// against a strict pattern, and anything that fails it is refused whole.
	$public = blueworx_klaviyo_clean_public_key( isset( $_POST['blueworx_klaviyo_public_key'] ) ? wp_unslash( $_POST['blueworx_klaviyo_public_key'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( false === $public ) {
		$refused[] = __( 'Public API key', 'blueworx-labs-wordpress' );
	} elseif ( ! defined( 'BLUEWORX_KLAVIYO_PUBLIC_KEY' ) ) {
		blueworx_klaviyo_update_setting( 'public_key', $public );
	}

	// An empty box means "keep what is saved": the box never shows the saved
	// key, so it is always empty when somebody saves for another reason.
	$private = blueworx_klaviyo_clean_private_key( isset( $_POST['blueworx_klaviyo_private_key'] ) ? wp_unslash( $_POST['blueworx_klaviyo_private_key'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( false === $private ) {
		$refused[] = __( 'Private API key', 'blueworx-labs-wordpress' );
	} elseif ( '' !== $private && ! defined( 'BLUEWORX_KLAVIYO_PRIVATE_KEY' ) ) {
		blueworx_klaviyo_update_setting( 'private_key', $private );
		delete_transient( 'blueworx_klaviyo_lists' );
	}

	if ( ! empty( $_POST['blueworx_klaviyo_forget_private_key'] ) ) {
		delete_option( BLUEWORX_KLAVIYO_OPTION_PREFIX . 'private_key' );
		delete_transient( 'blueworx_klaviyo_lists' );
	}

	/**
	 * Fires while the Klaviyo screen saves, after the keys.
	 *
	 * Other parts of the connection save their own boxes here. Return the
	 * labels of any boxes you refused by adding them to $refused.
	 *
	 * @param string[] $refused Labels of boxes refused so far.
	 */
	$refused = (array) apply_filters( 'blueworx_klaviyo_save_settings', $refused );

	$notice = array( 'refused' => $refused );

	if ( ! empty( $_POST['blueworx_klaviyo_test'] ) ) {
		$notice['test'] = blueworx_klaviyo_test_connection();
	}

	set_transient( 'blueworx_klaviyo_notice', $notice, 60 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-klaviyo' ) );
	exit;
}
add_action( 'admin_post_blueworx_save_klaviyo', 'blueworx_klaviyo_handle_save' );

/**
 * Empties the log.
 *
 * @return void
 */
function blueworx_klaviyo_handle_clear_log() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_clear_klaviyo_log' );

	delete_option( BLUEWORX_KLAVIYO_LOG_OPTION );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-klaviyo-log' ) );
	exit;
}
add_action( 'admin_post_blueworx_clear_klaviyo_log', 'blueworx_klaviyo_handle_clear_log' );

/**
 * The private key's box. The saved key is never put back in it.
 *
 * @return string HTML.
 */
function blueworx_klaviyo_private_key_field() {
	$value = blueworx_klaviyo_private_key();
	$label = __( 'Private API key', 'blueworx-labs-wordpress' );

	if ( defined( 'BLUEWORX_KLAVIYO_PRIVATE_KEY' ) && BLUEWORX_KLAVIYO_PRIVATE_KEY ) {
		return blueworx_ds_field(
			array(
				'label'   => $label,
				'help'    => __( 'Set in wp-config.php as BLUEWORX_KLAVIYO_PRIVATE_KEY, so it cannot be changed here.', 'blueworx-labs-wordpress' ),
				'control' => '<code>' . esc_html( blueworx_klaviyo_mask( $value ) ) . '</code>',
			)
		);
	}

	$html = blueworx_ds_field(
		array(
			'label'   => $label,
			'for'     => 'blueworx_klaviyo_private_key',
			'help'    => '' !== $value
				? sprintf(
					/* translators: %s: the last few characters of the saved key. */
					__( 'Saved: %s. Leave this empty to keep it.', 'blueworx-labs-wordpress' ),
					blueworx_klaviyo_mask( $value )
				)
				: __( 'In Klaviyo, under Settings, API keys, create a private key with full access to Profiles, Events, Lists and Subscriptions. It starts with pk_.', 'blueworx-labs-wordpress' ),
			'control' => blueworx_ds_input(
				array(
					'type'  => 'password',
					'name'  => 'blueworx_klaviyo_private_key',
					'id'    => 'blueworx_klaviyo_private_key',
					'value' => '',
					'attrs' => array( 'autocomplete' => 'new-password' ),
				)
			),
		)
	);

	if ( '' !== $value ) {
		$html .= blueworx_ds_checkbox(
			array(
				'name'  => 'blueworx_klaviyo_forget_private_key',
				'value' => '1',
				'label' => __( 'Remove the saved key', 'blueworx-labs-wordpress' ),
			)
		);
	}

	return $html;
}

/**
 * The public key's box.
 *
 * @return string HTML.
 */
function blueworx_klaviyo_public_key_field() {
	$value = blueworx_klaviyo_public_key();
	$label = __( 'Public API key', 'blueworx-labs-wordpress' );
	$help  = __( 'Also called the site ID: six letters and digits, shown beside the private keys in Klaviyo. Used for tracking visitors on the site.', 'blueworx-labs-wordpress' );

	if ( defined( 'BLUEWORX_KLAVIYO_PUBLIC_KEY' ) && BLUEWORX_KLAVIYO_PUBLIC_KEY ) {
		return blueworx_ds_field(
			array(
				'label'   => $label,
				'help'    => __( 'Set in wp-config.php as BLUEWORX_KLAVIYO_PUBLIC_KEY, so it cannot be changed here.', 'blueworx-labs-wordpress' ),
				'control' => '<code>' . esc_html( $value ) . '</code>',
			)
		);
	}

	return blueworx_ds_field(
		array(
			'label'   => $label,
			'for'     => 'blueworx_klaviyo_public_key',
			'help'    => $help,
			'control' => blueworx_ds_input(
				array(
					'name'  => 'blueworx_klaviyo_public_key',
					'id'    => 'blueworx_klaviyo_public_key',
					'value' => $value,
					'attrs' => array(
						'autocomplete' => 'off',
						'placeholder'  => 'AbC123',
					),
				)
			),
		)
	);
}

/**
 * Renders the settings screen.
 *
 * @return void
 */
function blueworx_render_klaviyo_page() {
	$enabled = blueworx_klaviyo_enabled();
	$ready   = blueworx_klaviyo_connected();

	$form = $enabled
		? sprintf(
			'<form method="post" action="%1$s"><input type="hidden" name="action" value="blueworx_save_klaviyo" />%2$s',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'blueworx_save_klaviyo', '_wpnonce', true, false )
		)
		: '';

	blueworx_open_admin_page(
		array(
			'title'   => __( 'Klaviyo', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'Connects this site to its Klaviyo account. Sign-ups, orders and visits are sent in the background: a form or a checkout never waits for Klaviyo, and if Klaviyo is down the work waits and tries again.', 'blueworx-labs-wordpress' ),
			'actions' => blueworx_ds_badge(
				$ready ? __( 'Connected', 'blueworx-labs-wordpress' ) : __( 'Not connected', 'blueworx-labs-wordpress' ),
				$ready ? 'success' : 'neutral',
				true
			)
				. blueworx_ds_button(
					array(
						'label'   => __( 'Open the log', 'blueworx-labs-wordpress' ),
						'href'    => admin_url( 'admin.php?page=blueworx-klaviyo-log' ),
						'variant' => 'secondary',
						'size'    => 'sm',
					)
				),
			'form'    => $form,
		)
	);

	if ( ! $enabled ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone'    => 'info',
					'title'   => __( 'The Klaviyo connection is switched off', 'blueworx-labs-wordpress' ),
					'text'    => __( 'Nothing is sent to Klaviyo while this is off, and this page is not listed in the menu. Switch it on under Enhancements to set it up.', 'blueworx-labs-wordpress' ),
					'actions' => blueworx_ds_button(
						array(
							'label' => __( 'Go to Enhancements', 'blueworx-labs-wordpress' ),
							'icon'  => 'arrow-right',
							'href'  => admin_url( 'admin.php?page=blueworx-labs-wordpress&section=tracking' ),
						)
					),
				)
			),
			blueworx_ds_allowed_html()
		);

		blueworx_close_admin_page();
		return;
	}

	blueworx_klaviyo_render_notices();

	// Not wp_kses(): the allow-list has no opinion on these form controls, and
	// the design system helpers escape everything they emit.
	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		array(
			'title' => __( 'Klaviyo account', 'blueworx-labs-wordpress' ),
			'body'  => blueworx_klaviyo_private_key_field() . blueworx_klaviyo_public_key_field(),
		)
	);

	/**
	 * Fires between the account card and the "what sends" card on the Klaviyo
	 * screen, so other parts of the connection can add their own cards.
	 */
	do_action( 'blueworx_klaviyo_screen_cards' );

	$sources = blueworx_klaviyo_sources();
	$body    = '';

	if ( $sources ) {
		foreach ( $sources as $source ) {
			$body .= blueworx_ds_field(
				array(
					'label' => (string) $source['label'],
					'help'  => ! empty( $source['text'] ) ? (string) $source['text'] : '',
				)
			);
		}
	} else {
		$body .= blueworx_ds_empty_state(
			array(
				'icon'  => 'mail',
				'title' => __( 'Nothing sends to Klaviyo yet', 'blueworx-labs-wordpress' ),
				'text'  => __( 'Visitor tracking, website forms and the store each connect in their own update. A plugin built for this site can send its own events through the connection as well.', 'blueworx-labs-wordpress' ),
			)
		);
	}

	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		array(
			'title' => __( 'What sends to Klaviyo', 'blueworx-labs-wordpress' ),
			'body'  => $body,
		)
	);

	$savebar = '<div class="bw-savebar"><span class="bw-savebar__hint">'
		. esc_html__( 'Nothing changes until you save.', 'blueworx-labs-wordpress' )
		. '</span>'
		. blueworx_ds_button(
			array(
				'label'   => __( 'Save and test connection', 'blueworx-labs-wordpress' ),
				'variant' => 'secondary',
				'type'    => 'submit',
				'attrs'   => array(
					'name'        => 'blueworx_klaviyo_test',
					'value'       => '1',
					'data-testid' => 'blueworx-klaviyo-test',
				),
			)
		)
		. blueworx_ds_button(
			array(
				'label'   => __( 'Save changes', 'blueworx-labs-wordpress' ),
				'variant' => 'primary',
				'type'    => 'submit',
				'attrs'   => array( 'data-testid' => 'blueworx-klaviyo-save' ),
			)
		)
		. '</div></form>';

	blueworx_close_admin_page( $savebar );
}

/**
 * Prints what the last save or test had to say.
 *
 * @return void
 */
function blueworx_klaviyo_render_notices() {
	$notice = get_transient( 'blueworx_klaviyo_notice' );

	if ( ! is_array( $notice ) ) {
		return;
	}

	delete_transient( 'blueworx_klaviyo_notice' );

	$messages = array();

	if ( ! empty( $notice['refused'] ) ) {
		$messages[] = array(
			'tone' => 'danger',
			'text' => __( 'Some boxes were not saved because they are not in the right format, so the previous values were kept: ', 'blueworx-labs-wordpress' ) . implode( ', ', array_map( 'strval', (array) $notice['refused'] ) ),
		);
	} else {
		$messages[] = array(
			'tone' => 'success',
			'text' => __( 'Settings saved.', 'blueworx-labs-wordpress' ),
		);
	}

	if ( isset( $notice['test'] ) ) {
		$messages[] = array(
			'tone'  => ! empty( $notice['test']['ok'] ) ? 'success' : 'danger',
			'title' => ! empty( $notice['test']['ok'] ) ? __( 'Connection works', 'blueworx-labs-wordpress' ) : __( 'Connection needs attention', 'blueworx-labs-wordpress' ),
			'text'  => implode( ' ', (array) $notice['test']['lines'] ),
		);
	}

	foreach ( $messages as $message ) {
		echo wp_kses( blueworx_ds_notice( $message ), blueworx_ds_allowed_html() );
	}
}

/**
 * Renders the log screen.
 *
 * @return void
 */
function blueworx_render_klaviyo_log_page() {
	$log = blueworx_klaviyo_log_lines();

	blueworx_open_admin_page(
		array(
			'title'   => __( 'Klaviyo log', 'blueworx-labs-wordpress' ),
			'lede'    => sprintf(
				/* translators: %s: number of sends kept. */
				__( 'The last %s sends to Klaviyo, newest first. Will try again needs nothing from you; Failed means it never reached Klaviyo, with the reason underneath.', 'blueworx-labs-wordpress' ),
				number_format_i18n( BLUEWORX_KLAVIYO_LOG_LIMIT )
			),
			'actions' => blueworx_ds_button(
				array(
					'label'   => __( 'Klaviyo settings', 'blueworx-labs-wordpress' ),
					'href'    => admin_url( 'admin.php?page=blueworx-klaviyo' ),
					'variant' => 'secondary',
					'size'    => 'sm',
				)
			),
		)
	);

	echo '<section class="bw-card"><div class="bw-card__head"><div class="bw-card__titles">';
	echo '<h2 class="bw-card__title">' . esc_html__( 'Recent sends', 'blueworx-labs-wordpress' ) . '</h2>';
	echo '</div><div class="bw-card__actions">';

	if ( $log ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'blueworx_clear_klaviyo_log' );
		echo '<input type="hidden" name="action" value="blueworx_clear_klaviyo_log" />';
		echo blueworx_ds_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helper escapes everything it emits.
			array(
				'label'   => __( 'Clear log', 'blueworx-labs-wordpress' ),
				'type'    => 'submit',
				'variant' => 'secondary',
				'size'    => 'sm',
			)
		);
		echo '</form>';
	}

	echo '</div></div>';

	if ( ! $log ) {
		echo '<div class="bw-card__body">';
		echo wp_kses(
			blueworx_ds_empty_state(
				array(
					'icon'  => 'mail',
					'title' => __( 'Nothing has been sent yet', 'blueworx-labs-wordpress' ),
					'text'  => __( 'Every send to Klaviyo writes a line here, whether it worked or not.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
		echo '</div></section>';

		blueworx_close_admin_page();
		return;
	}

	$user = blueworx_readonly_current_user();
	$hide = $user && ! blueworx_readonly_data_allowed( $user );

	$badges = array(
		'sent'     => array( __( 'Sent', 'blueworx-labs-wordpress' ), 'success' ),
		'retrying' => array( __( 'Will try again', 'blueworx-labs-wordpress' ), 'warning' ),
		'failed'   => array( __( 'Failed', 'blueworx-labs-wordpress' ), 'danger' ),
	);

	echo '<div style="overflow-x:auto"><table class="bw-table" data-testid="blueworx-klaviyo-log"><thead><tr>';
	echo '<th scope="col">' . esc_html__( 'When', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'What', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Who', 'blueworx-labs-wordpress' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Outcome', 'blueworx-labs-wordpress' ) . '</th>';
	echo '</tr></thead><tbody>';

	foreach ( $log as $line ) {
		$outcome = isset( $line['outcome'] ) ? (string) $line['outcome'] : '';
		$badge   = isset( $badges[ $outcome ] ) ? $badges[ $outcome ] : array( $outcome, 'neutral' );
		$email   = isset( $line['email'] ) ? (string) $line['email'] : '';

		echo '<tr data-outcome="' . esc_attr( $outcome ) . '">';
		echo '<td><span class="bw-table__primary">' . esc_html( wp_date( 'j M Y H:i', isset( $line['time'] ) ? (int) $line['time'] : 0 ) ) . '</span></td>';
		echo '<td><span class="bw-table__primary">' . esc_html( isset( $line['label'] ) ? (string) $line['label'] : '' ) . '</span></td>';
		echo '<td>' . esc_html( $hide && '' !== $email ? __( 'Hidden', 'blueworx-labs-wordpress' ) : ( '' !== $email ? $email : '—' ) ) . '</td>';
		echo '<td>' . wp_kses( blueworx_ds_badge( $badge[0], $badge[1] ), blueworx_ds_allowed_html() );

		if ( ! empty( $line['detail'] ) ) {
			echo '<span class="bw-table__sub">' . esc_html( (string) $line['detail'] ) . '</span>';
		}

		echo '</td></tr>';
	}

	echo '</tbody></table></div></section>';

	blueworx_close_admin_page();
}
