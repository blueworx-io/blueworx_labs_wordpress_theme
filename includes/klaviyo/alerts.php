<?php
/**
 * Klaviyo connection: telling somebody when sending goes wrong.
 *
 * The log says what happened, but nobody reads a log until they already
 * know something is wrong. So two things get an email: a send that has
 * failed for good, and a private key Klaviyo refuses. Failures are grouped,
 * at most one email an hour with everything that failed in it; the key email
 * goes once, and not again until the key has worked.
 *
 * Who gets it is the site's admin email unless the Klaviyo screen says
 * otherwise. The screen also shows when the last send went through, which is
 * the quickest way to see the connection is alive.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many failures one email lists at most.
 */
const BLUEWORX_KLAVIYO_ALERT_LIMIT = 50;

/**
 * Who the emails go to.
 *
 * @return string[] Email addresses.
 */
function blueworx_klaviyo_alert_recipients() {
	$saved = (string) blueworx_klaviyo_setting( 'alert_email', '' );
	$to    = '' !== $saved ? array( $saved ) : array( (string) get_option( 'admin_email' ) );

	/**
	 * Who is emailed when sending to Klaviyo fails.
	 *
	 * @param string[] $to Email addresses: the Klaviyo screen's, or the site's admin email.
	 */
	$to = apply_filters( 'blueworx_klaviyo_alert_recipients', $to );

	return array_values( array_unique( array_filter( array_map( 'strval', (array) $to ), 'is_email' ) ) );
}

/**
 * Whether the failures waiting are worth an email yet. Pure.
 *
 * @param array $pending   Failures waiting to be sent.
 * @param int   $last_sent When the last failures email went, or 0.
 * @param int   $now       The time.
 * @return bool
 */
function blueworx_klaviyo_alert_should_send( $pending, $last_sent, $now ) {
	return ! empty( $pending ) && ( (int) $now - (int) $last_sent ) >= HOUR_IN_SECONDS;
}

/**
 * The failures email. Pure.
 *
 * @param array  $pending   Failures: time, label, email, detail.
 * @param string $site_name The site's name.
 * @param string $log_url   The log screen.
 * @return array subject and body.
 */
function blueworx_klaviyo_alert_message( $pending, $site_name, $log_url ) {
	$count = count( $pending );
	$lines = array(
		sprintf(
			/* translators: 1: number of failed sends, 2: site name. */
			_n( '%1$s send to Klaviyo from %2$s has failed and will not be tried again.', '%1$s sends to Klaviyo from %2$s have failed and will not be tried again.', $count, 'blueworx-labs-wordpress' ),
			number_format_i18n( $count ),
			$site_name
		),
		'',
	);

	foreach ( array_slice( $pending, 0, BLUEWORX_KLAVIYO_ALERT_LIMIT ) as $failure ) {
		$lines[] = sprintf(
			'- %1$s: %2$s%3$s (%4$s)',
			isset( $failure['label'] ) ? (string) $failure['label'] : '',
			! empty( $failure['email'] ) ? (string) $failure['email'] . ' — ' : '',
			isset( $failure['detail'] ) ? (string) $failure['detail'] : '',
			wp_date( 'j M H:i', isset( $failure['time'] ) ? (int) $failure['time'] : 0 )
		);
	}

	if ( $count > BLUEWORX_KLAVIYO_ALERT_LIMIT ) {
		$lines[] = sprintf(
			/* translators: %s: number of further failures. */
			__( '…and %s more.', 'blueworx-labs-wordpress' ),
			number_format_i18n( $count - BLUEWORX_KLAVIYO_ALERT_LIMIT )
		);
	}

	$lines[] = '';
	$lines[] = __( 'Nothing is lost on the site, but these did not reach Klaviyo. The reason is beside each one. The log has the full picture:', 'blueworx-labs-wordpress' );
	$lines[] = $log_url;
	$lines[] = '';
	$lines[] = __( 'At most one of these emails is sent an hour, with everything that failed in it.', 'blueworx-labs-wordpress' );

	return array(
		'subject' => sprintf(
			/* translators: 1: site name, 2: number of failed sends. */
			_n( '[%1$s] Klaviyo: %2$s send failed', '[%1$s] Klaviyo: %2$s sends failed', $count, 'blueworx-labs-wordpress' ),
			$site_name,
			number_format_i18n( $count )
		),
		'body'    => implode( "\n", $lines ),
	);
}

/**
 * The refused-key email. Pure.
 *
 * @param string $detail    What Klaviyo said.
 * @param string $site_name The site's name.
 * @param string $settings_url The Klaviyo screen.
 * @return array subject and body.
 */
function blueworx_klaviyo_alert_key_message( $detail, $site_name, $settings_url ) {
	return array(
		'subject' => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Klaviyo is refusing the private API key', 'blueworx-labs-wordpress' ),
			$site_name
		),
		'body'    => implode(
			"\n",
			array(
				sprintf(
					/* translators: %s: site name. */
					__( 'Klaviyo is refusing the private API key %s is sending with, so nothing from the site is reaching Klaviyo. Sends are held and tried again, so nothing is lost yet; they fail for good after a day.', 'blueworx-labs-wordpress' ),
					$site_name
				),
				'',
				sprintf(
					/* translators: %s: what Klaviyo said. */
					__( 'Klaviyo said: %s', 'blueworx-labs-wordpress' ),
					$detail
				),
				'',
				__( 'The key may have been revoked in Klaviyo, or lost the access it needs (Profiles, Events, Lists and Subscriptions). Paste a working key here:', 'blueworx-labs-wordpress' ),
				$settings_url,
				'',
				__( 'This email is sent once. You will not hear again until the key has worked and then failed anew.', 'blueworx-labs-wordpress' ),
			)
		),
	);
}

/**
 * Sends one email to everyone who should get it.
 *
 * @param array $message subject and body.
 * @return bool Whether anything went out.
 */
function blueworx_klaviyo_alert_mail( $message ) {
	$sent = false;

	foreach ( blueworx_klaviyo_alert_recipients() as $to ) {
		$sent = wp_mail( $to, $message['subject'], $message['body'] ) || $sent;
	}

	return $sent;
}

/**
 * Notes a send that has failed for good, and emails if the hour is up.
 *
 * @param string $label  What it was.
 * @param string $email  Who it was about.
 * @param string $detail What went wrong.
 * @return void
 */
function blueworx_klaviyo_alert_note_failure( $label, $email, $detail ) {
	$pending   = blueworx_klaviyo_setting( 'alert_pending', array() );
	$pending   = is_array( $pending ) ? $pending : array();
	$pending[] = array(
		'time'   => time(),
		'label'  => (string) $label,
		'email'  => (string) $email,
		'detail' => (string) $detail,
	);

	blueworx_klaviyo_update_setting( 'alert_pending', array_slice( $pending, -BLUEWORX_KLAVIYO_ALERT_LIMIT * 2 ) );
	blueworx_klaviyo_alert_flush();
}
add_action( 'blueworx_klaviyo_gave_up', 'blueworx_klaviyo_alert_note_failure', 10, 3 );

/**
 * Emails the failures waiting, if there are any and the hour is up.
 *
 * Runs after each failure and on the regular tick, so a failure that arrived
 * inside the hour is still reported once the hour is up.
 *
 * @return bool Whether an email went.
 */
function blueworx_klaviyo_alert_flush() {
	$pending = blueworx_klaviyo_setting( 'alert_pending', array() );
	$pending = is_array( $pending ) ? $pending : array();

	if ( ! blueworx_klaviyo_alert_should_send( $pending, (int) blueworx_klaviyo_setting( 'alert_last_sent', 0 ), time() ) ) {
		return false;
	}

	blueworx_klaviyo_update_setting( 'alert_last_sent', time() );
	blueworx_klaviyo_update_setting( 'alert_pending', array() );

	return blueworx_klaviyo_alert_mail( blueworx_klaviyo_alert_message( $pending, wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ), admin_url( 'admin.php?page=blueworx-klaviyo-log' ) ) );
}
add_action( 'blueworx_klaviyo_tick', 'blueworx_klaviyo_alert_flush', 30 );

/**
 * Emails once that Klaviyo is refusing the key, and not again until it works.
 *
 * @param string $detail What Klaviyo said.
 * @return void
 */
function blueworx_klaviyo_alert_key_refused( $detail ) {
	if ( '1' === (string) blueworx_klaviyo_setting( 'alert_key_refused', '0' ) ) {
		return;
	}

	blueworx_klaviyo_update_setting( 'alert_key_refused', '1' );
	blueworx_klaviyo_alert_mail( blueworx_klaviyo_alert_key_message( (string) $detail, wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ), admin_url( 'admin.php?page=blueworx-klaviyo' ) ) );
}
add_action( 'blueworx_klaviyo_key_refused', 'blueworx_klaviyo_alert_key_refused' );

/**
 * Notes a send that went through: the key works, and the connection is alive.
 *
 * @return void
 */
function blueworx_klaviyo_alert_on_sent() {
	blueworx_klaviyo_update_setting( 'last_sent_at', time() );

	if ( '1' === (string) blueworx_klaviyo_setting( 'alert_key_refused', '0' ) ) {
		blueworx_klaviyo_update_setting( 'alert_key_refused', '0' );
	}
}
add_action( 'blueworx_klaviyo_sent', 'blueworx_klaviyo_alert_on_sent' );

/**
 * A new key gets a clean slate.
 *
 * @return void
 */
function blueworx_klaviyo_alert_on_new_key() {
	blueworx_klaviyo_update_setting( 'alert_key_refused', '0' );
}
add_action( 'update_option_blueworx_klaviyo_private_key', 'blueworx_klaviyo_alert_on_new_key' );
add_action( 'add_option_blueworx_klaviyo_private_key', 'blueworx_klaviyo_alert_on_new_key' );

// --- The screen ---------------------------------------------------------------

/**
 * The alerts card on the Klaviyo screen.
 *
 * @return void
 */
function blueworx_klaviyo_alerts_card() {
	$last_sent = (int) blueworx_klaviyo_setting( 'last_sent_at', 0 );
	$last_mail = (int) blueworx_klaviyo_setting( 'alert_last_sent', 0 );
	$refused   = '1' === (string) blueworx_klaviyo_setting( 'alert_key_refused', '0' );
	$body      = '';

	if ( $refused ) {
		$body .= wp_kses(
			blueworx_ds_notice(
				array(
					'tone' => 'danger',
					'text' => __( 'Klaviyo is refusing the private API key. Nothing is reaching Klaviyo until a working key is saved above.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	$body .= blueworx_ds_field(
		array(
			'label' => __( 'Last successful send', 'blueworx-labs-wordpress' ),
			'help'  => $last_sent > 0
				? sprintf(
					/* translators: %s: date and time. */
					__( '%s. If this is older than you expect, open the log.', 'blueworx-labs-wordpress' ),
					wp_date( 'j M Y H:i', $last_sent )
				)
				: __( 'Nothing has reached Klaviyo yet.', 'blueworx-labs-wordpress' ),
		)
	);

	$body .= blueworx_ds_field(
		array(
			'label'   => __( 'Email failures to', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_klaviyo_alert_email',
			'help'    => sprintf(
				/* translators: 1: the site's admin email, 2: when the last alert went or "never". */
				__( 'One email an hour at most, listing every send that failed for good, and one when Klaviyo refuses the key. Empty means the site\'s admin email, %1$s. Last alert: %2$s.', 'blueworx-labs-wordpress' ),
				(string) get_option( 'admin_email' ),
				$last_mail > 0 ? wp_date( 'j M Y H:i', $last_mail ) : __( 'never', 'blueworx-labs-wordpress' )
			),
			'control' => blueworx_ds_input(
				array(
					'type'  => 'email',
					'name'  => 'blueworx_klaviyo_alert_email',
					'id'    => 'blueworx_klaviyo_alert_email',
					'value' => (string) blueworx_klaviyo_setting( 'alert_email', '' ),
					'attrs' => array(
						'placeholder'  => (string) get_option( 'admin_email' ),
						'autocomplete' => 'off',
					),
				)
			),
		)
	);

	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helpers escape everything they emit.
		array(
			'title' => __( 'Health and alerts', 'blueworx-labs-wordpress' ),
			'body'  => $body,
		)
	);
}
add_action( 'blueworx_klaviyo_screen_cards', 'blueworx_klaviyo_alerts_card', 3 );

/**
 * Saves the alert email when the Klaviyo screen saves.
 *
 * @param string[] $refused Labels refused so far.
 * @return string[]
 */
function blueworx_klaviyo_alerts_save( $refused ) {
	// The caller has verified the nonce and the capability.
	// Checked whole rather than tidied: sanitize_email() would turn a bad
	// address into an empty one, which means 'use the admin email'.
	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$email = isset( $_POST['blueworx_klaviyo_alert_email'] ) ? trim( (string) wp_unslash( $_POST['blueworx_klaviyo_alert_email'] ) ) : '';
	// phpcs:enable

	if ( '' !== $email && ! is_email( $email ) ) {
		$refused[] = __( 'Email failures to', 'blueworx-labs-wordpress' );
	} else {
		blueworx_klaviyo_update_setting( 'alert_email', '' === $email ? '' : sanitize_email( $email ) );
	}

	return (array) $refused;
}
add_filter( 'blueworx_klaviyo_save_settings', 'blueworx_klaviyo_alerts_save' );
