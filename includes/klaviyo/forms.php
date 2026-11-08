<?php
/**
 * Klaviyo connection: website forms.
 *
 * Any form on the site can send its sign-ups to a Klaviyo list. An admin adds
 * a "form connection" on the Klaviyo screen: which form (an Elementor form's
 * name, a SureForms form's ID, or the id on a plain HTML form's tag), which
 * list, the consent wording shown beside the button, and a short tag that
 * says where the sign-up came from.
 *
 * Fields are found by name or label, so a form can be edited without breaking
 * its connection. A form with a marketing tick box respects it: unticked means
 * the person is identified but never subscribed. A form without one treats the
 * submission as the consent, and the wording is sent as the record of it.
 *
 * Every submission only queues work. Nothing here waits on Klaviyo, and
 * nothing that goes wrong here may reach the visitor.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The option the form connections live in.
 */
const BLUEWORX_KLAVIYO_FORMS_OPTION = 'blueworx_klaviyo_forms';

/**
 * The kinds of form a connection can name.
 *
 * @return array Labels keyed by kind.
 */
function blueworx_klaviyo_form_kinds() {
	return array(
		'elementor' => __( 'Elementor form, by its Form Name', 'blueworx-labs-wordpress' ),
		'sureforms' => __( 'SureForms form, by its ID', 'blueworx-labs-wordpress' ),
		'html'      => __( 'Any HTML form, by the id on its tag', 'blueworx-labs-wordpress' ),
	);
}

/**
 * The saved form connections.
 *
 * @return array Each has kind, match, list, consent and tag.
 */
function blueworx_klaviyo_form_connections() {
	$saved = get_option( BLUEWORX_KLAVIYO_FORMS_OPTION, array() );

	return is_array( $saved ) ? array_values( array_filter( $saved, 'is_array' ) ) : array();
}

/**
 * Tidies and checks the connections posted from the Klaviyo screen.
 *
 * Pure, so the rules can be tested. A row with no form named is dropped
 * quietly: it is an empty row somebody added and left. A row whose list does
 * not look like a list ID is refused and the whole set is kept as it was, so
 * one bad row cannot break the others.
 *
 * @param mixed $posted The posted rows.
 * @return array {
 *     @type array    $values  The connections to save.
 *     @type string[] $refused Labels of what was refused.
 * }
 */
function blueworx_klaviyo_clean_connections( $posted ) {
	$kinds   = array_keys( blueworx_klaviyo_form_kinds() );
	$values  = array();
	$refused = array();

	foreach ( is_array( $posted ) ? $posted : array() as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$match = isset( $row['match'] ) ? sanitize_text_field( (string) $row['match'] ) : '';
		$kind  = isset( $row['kind'] ) && in_array( $row['kind'], $kinds, true ) ? $row['kind'] : 'elementor';
		$list  = isset( $row['list'] ) ? trim( (string) $row['list'] ) : '';

		if ( '' === $match ) {
			continue;
		}

		if ( 'sureforms' === $kind && ! ctype_digit( $match ) ) {
			$refused[] = sprintf(
				/* translators: %s: what was typed. */
				__( 'SureForms form ID "%s" should be a number', 'blueworx-labs-wordpress' ),
				$match
			);
			continue;
		}

		if ( 'html' === $kind && ! preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $match ) ) {
			$refused[] = sprintf(
				/* translators: %s: what was typed. */
				__( 'HTML form id "%s" should be letters, digits, dashes and underscores', 'blueworx-labs-wordpress' ),
				$match
			);
			continue;
		}

		if ( '' !== $list && ! preg_match( '/^[A-Za-z0-9]{4,12}$/', $list ) ) {
			$refused[] = sprintf(
				/* translators: %s: the form the list was for. */
				__( 'Klaviyo list for "%s"', 'blueworx-labs-wordpress' ),
				$match
			);
			continue;
		}

		$tag = isset( $row['tag'] ) ? sanitize_title( (string) $row['tag'] ) : '';

		$values[] = array(
			'kind'    => $kind,
			'match'   => $match,
			'list'    => $list,
			'consent' => isset( $row['consent'] ) ? sanitize_textarea_field( (string) $row['consent'] ) : '',
			'tag'     => '' !== $tag ? $tag : sanitize_title( $match ),
		);
	}

	return array(
		'values'  => $values,
		'refused' => $refused,
	);
}

/**
 * Finds the connection for a form, if there is one.
 *
 * @param string $kind  elementor, sureforms or html.
 * @param string $form_match The form's name or id as the submission reports it.
 * @return array|null
 */
function blueworx_klaviyo_form_connection( $kind, $form_match ) {
	foreach ( blueworx_klaviyo_form_connections() as $connection ) {
		if ( $connection['kind'] === $kind && 0 === strcasecmp( (string) $connection['match'], (string) $form_match ) ) {
			return $connection;
		}
	}

	return null;
}

/**
 * Whether a tick box's answer means ticked.
 *
 * @param mixed $value Answer.
 * @return bool
 */
function blueworx_klaviyo_is_ticked( $value ) {
	if ( is_bool( $value ) ) {
		return $value;
	}

	if ( is_array( $value ) ) {
		return (bool) array_filter( $value );
	}

	$value = strtolower( trim( (string) $value ) );

	return '' !== $value && ! in_array( $value, array( '0', 'false', 'no', 'off' ), true );
}

/**
 * Picks a sign-up's answers out of a form's fields, by name or label.
 *
 * Pure. Each field is an array with name, label and value, and optionally
 * checked (true or false for a tick box, absent otherwise).
 *
 * @param array $fields The submitted fields.
 * @return array {
 *     @type string    $email      The first email address found.
 *     @type string    $first_name First name, or the first word of a single name box.
 *     @type string    $last_name  Last name, or the rest of a single name box.
 *     @type bool|null $marketing  The marketing box's answer, or null when the form has none.
 * }
 */
function blueworx_klaviyo_form_values( $fields ) {
	$values = array(
		'email'      => '',
		'first_name' => '',
		'last_name'  => '',
		'marketing'  => null,
	);

	$full_name = '';

	foreach ( (array) $fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$name  = isset( $field['name'] ) ? (string) $field['name'] : '';
		$label = isset( $field['label'] ) ? (string) $field['label'] : '';
		$value = isset( $field['value'] ) ? $field['value'] : '';
		$text  = is_scalar( $value ) ? trim( (string) $value ) : '';
		$key   = strtolower( $name . ' ' . $label );
		$box   = array_key_exists( 'checked', $field ) && null !== $field['checked'];

		if ( $box || preg_match( '/marketing|newsletter|\bnews\b|updates|consent|subscribe|opt.?in|keep me/i', $key ) ) {
			if ( null === $values['marketing'] && ( $box || '' !== $text ) ) {
				$values['marketing'] = $box ? blueworx_klaviyo_is_ticked( $field['checked'] ) : blueworx_klaviyo_is_ticked( $text );
			}

			if ( $box ) {
				continue;
			}
		}

		if ( '' === $text ) {
			continue;
		}

		if ( '' === $values['email'] && ( preg_match( '/e-?mail/i', $key ) || is_email( $text ) ) && is_email( $text ) ) {
			$values['email'] = $text;
			continue;
		}

		if ( '' === $values['first_name'] && preg_match( '/first.?name|\bfname\b|given|forename/i', $key ) ) {
			$values['first_name'] = $text;
		} elseif ( '' === $values['last_name'] && preg_match( '/last.?name|\blname\b|surname|family/i', $key ) ) {
			$values['last_name'] = $text;
		} elseif ( '' === $full_name && preg_match( '/^(your.?)?(full.?)?name$|\bname\b/i', trim( $label ) ) ) {
			$full_name = $text;
		} elseif ( '' === $full_name && preg_match( '/^(your_|full_)?name$/i', trim( $name ) ) ) {
			$full_name = $text;
		}
	}

	if ( '' === $values['first_name'] && '' !== $full_name ) {
		$parts                = preg_split( '/\s+/', $full_name, 2 );
		$values['first_name'] = $parts[0];

		if ( '' === $values['last_name'] && isset( $parts[1] ) ) {
			$values['last_name'] = $parts[1];
		}
	}

	return $values;
}

/**
 * What one sign-up sends to Klaviyo.
 *
 * Pure. Names fill only when the profile has none: a form is anonymous, so it
 * must not be able to rename somebody. Unticked marketing means identified
 * but never subscribed.
 *
 * @param array  $connection The form connection.
 * @param array  $values     From blueworx_klaviyo_form_values().
 * @param string $page       The page the form was on.
 * @return array|null The plan, or null when there is no usable email.
 */
function blueworx_klaviyo_form_plan( $connection, $values, $page ) {
	$email = isset( $values['email'] ) ? sanitize_email( $values['email'] ) : '';

	if ( ! is_email( $email ) ) {
		return null;
	}

	$tag       = ! empty( $connection['tag'] ) ? (string) $connection['tag'] : sanitize_title( (string) $connection['match'] );
	$marketing = ! array_key_exists( 'marketing', $values ) || null === $values['marketing'] ? true : (bool) $values['marketing'];

	return array(
		'label'   => sprintf(
			/* translators: %s: the form's tag. */
			__( 'Form: %s', 'blueworx-labs-wordpress' ),
			$tag
		),
		'email'   => $email,
		'fill'    => array(
			'first_name' => isset( $values['first_name'] ) ? sanitize_text_field( (string) $values['first_name'] ) : '',
			'last_name'  => isset( $values['last_name'] ) ? sanitize_text_field( (string) $values['last_name'] ) : '',
		),
		'profile' => array(
			'signup_source'          => $tag,
			'signup_page'            => (string) $page,
			// Klaviyo quietly drops a property called consent_text, so this name is deliberate.
			'signup_consent_wording' => isset( $connection['consent'] ) ? (string) $connection['consent'] : '',
			'marketing_consent'      => $marketing,
		),
		'event'   => array(
			'name'       => 'Submitted Form',
			'properties' => array(
				'form' => $tag,
				'page' => (string) $page,
			),
		),
		'list'    => $marketing && ! empty( $connection['list'] ) ? (string) $connection['list'] : '',
	);
}

/**
 * Handles one submission from any kind of form: finds its connection, works
 * out the plan and queues it.
 *
 * @param string $kind   elementor, sureforms or html.
 * @param string $form_match  The form's name or id.
 * @param array  $fields The fields, each with name, label, value and maybe checked.
 * @param string $page   The page the form was on.
 * @return bool Whether anything was queued.
 */
function blueworx_klaviyo_form_submitted( $kind, $form_match, $fields, $page ) {
	if ( ! blueworx_klaviyo_enabled() ) {
		return false;
	}

	$connection = blueworx_klaviyo_form_connection( $kind, $form_match );

	if ( ! $connection ) {
		return false;
	}

	$plan = blueworx_klaviyo_form_plan( $connection, blueworx_klaviyo_form_values( $fields ), esc_url_raw( (string) $page ) );

	if ( ! $plan ) {
		return false;
	}

	return blueworx_klaviyo_send( $plan, 'forms' );
}

// --- Elementor ---------------------------------------------------------------

/**
 * Handles an Elementor form submission.
 *
 * @param object $record Elementor's form record.
 * @return void
 */
function blueworx_klaviyo_on_elementor_form( $record ) {
	try {
		if ( ! is_object( $record ) || ! method_exists( $record, 'get_form_settings' ) || ! method_exists( $record, 'get' ) ) {
			return;
		}

		$fields = array();

		foreach ( (array) $record->get( 'fields' ) as $id => $field ) {
			$type     = isset( $field['type'] ) ? (string) $field['type'] : '';
			$fields[] = array(
				'name'    => (string) $id,
				'label'   => isset( $field['title'] ) ? (string) $field['title'] : '',
				'value'   => isset( $field['value'] ) ? $field['value'] : '',
				'checked' => in_array( $type, array( 'acceptance', 'checkbox' ), true ) ? blueworx_klaviyo_is_ticked( isset( $field['value'] ) ? $field['value'] : '' ) : null,
			);
		}

		// Elementor has already checked its own nonce before this hook runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$page = isset( $_POST['referrer'] ) ? esc_url_raw( wp_unslash( $_POST['referrer'] ) ) : (string) wp_get_referer();

		blueworx_klaviyo_form_submitted( 'elementor', (string) $record->get_form_settings( 'form_name' ), $fields, $page );
	} catch ( Throwable $thrown ) {
		blueworx_klaviyo_log( __( 'Elementor form', 'blueworx-labs-wordpress' ), '', 'failed', $thrown->getMessage() );
	}
}
add_action( 'elementor_pro/forms/new_record', 'blueworx_klaviyo_on_elementor_form' );

// --- SureForms ---------------------------------------------------------------

/**
 * Turns SureForms' posted field names into fields with labels.
 *
 * A name looks like srfm-input-3b3cf3ac-lbl-Rmlyc3QgTmFtZQ-first-name: the
 * type at the front, the label in base64 after "lbl-", and a slug at the end.
 *
 * @param array $data The posted form data.
 * @return array Fields with name, label, value and checked.
 */
function blueworx_klaviyo_sureforms_fields( $data ) {
	$fields = array();

	foreach ( (array) $data as $name => $value ) {
		$name = (string) $name;

		if ( 0 !== strpos( $name, 'srfm-' ) ) {
			continue;
		}

		$type  = preg_match( '/^srfm-([a-z]+)-/', $name, $m ) ? $m[1] : '';
		$label = '';

		if ( preg_match( '/-lbl-([A-Za-z0-9+\/=_-]+?)(?:-[a-z0-9-]*)?$/', $name, $m ) ) {
			$decoded = base64_decode( strtr( $m[1], '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- SureForms encodes field labels this way.
			$label   = false !== $decoded ? $decoded : '';
		}

		$fields[] = array(
			'name'    => $name,
			'label'   => $label,
			'value'   => is_scalar( $value ) || is_array( $value ) ? $value : '',
			'checked' => in_array( $type, array( 'checkbox', 'gdpr' ), true ) ? blueworx_klaviyo_is_ticked( $value ) : null,
		);
	}

	return $fields;
}

/**
 * Handles a SureForms submission.
 *
 * On the accepted hook rather than the later submit one, so a slow mail server
 * cannot hold this up, and it sees the raw field names.
 *
 * @param int   $entry_id Entry ID.
 * @param int   $form_id  Form ID.
 * @param array $data     The posted form data.
 * @return void
 */
function blueworx_klaviyo_on_sureforms_submission( $entry_id, $form_id, $data ) {
	try {
		blueworx_klaviyo_form_submitted( 'sureforms', (string) (int) $form_id, blueworx_klaviyo_sureforms_fields( $data ), (string) wp_get_referer() );
	} catch ( Throwable $thrown ) {
		blueworx_klaviyo_log( __( 'SureForms form', 'blueworx-labs-wordpress' ), '', 'failed', $thrown->getMessage() );
	}
}
add_action( 'srfm_submission_accepted', 'blueworx_klaviyo_on_sureforms_submission', 10, 3 );

// --- Plain HTML forms --------------------------------------------------------

/**
 * The HTML forms connected, for the page's script.
 *
 * @return string[] Form ids.
 */
function blueworx_klaviyo_html_form_ids() {
	$ids = array();

	foreach ( blueworx_klaviyo_form_connections() as $connection ) {
		if ( 'html' === $connection['kind'] ) {
			$ids[] = (string) $connection['match'];
		}
	}

	return $ids;
}

/**
 * Puts the catcher on public pages while an HTML form is connected.
 *
 * The catcher watches the named forms and posts a copy of what was typed to
 * the address below as the form submits, so the form's own handler is left
 * alone.
 *
 * @return void
 */
function blueworx_klaviyo_forms_enqueue() {
	if ( ! blueworx_klaviyo_connected() || is_admin() ) {
		return;
	}

	$ids = blueworx_klaviyo_html_form_ids();

	if ( ! $ids ) {
		return;
	}

	wp_enqueue_script(
		'blueworx-klaviyo-forms',
		BLUEWORX_LABS_URL . 'assets/js/klaviyo-forms.js',
		array(),
		blueworx_get_admin_asset_version( 'assets/js/klaviyo-forms.js' ),
		array( 'in_footer' => true )
	);

	wp_localize_script(
		'blueworx-klaviyo-forms',
		'blueworxKlaviyoForms',
		array(
			'endpoint' => esc_url_raw( rest_url( 'blueworx/v1/klaviyo/form' ) ),
			'forms'    => $ids,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'blueworx_klaviyo_forms_enqueue' );

/**
 * Adds the address the catcher posts to, only while an HTML form is connected.
 *
 * @return void
 */
function blueworx_klaviyo_forms_register_route() {
	if ( ! blueworx_klaviyo_enabled() || ! blueworx_klaviyo_html_form_ids() ) {
		return;
	}

	register_rest_route(
		'blueworx/v1',
		'/klaviyo/form',
		array(
			'methods'             => 'POST',
			'callback'            => 'blueworx_klaviyo_receive_html_form',
			// Anyone who can submit the form can reach this. What keeps it honest
			// is that it only accepts a form id an admin connected, only queues
			// work (never answers with anything), and is rate limited.
			'permission_callback' => '__return_true',
			'args'                => array(
				'form'   => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'page'   => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'esc_url_raw',
				),
				'fields' => array(
					'type'    => 'array',
					'default' => array(),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'blueworx_klaviyo_forms_register_route' );

/**
 * Handles a copy of an HTML form submission from the catcher.
 *
 * @param WP_REST_Request $request The request.
 * @return WP_REST_Response|WP_Error
 */
function blueworx_klaviyo_receive_html_form( $request ) {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'blueworx_klaviyo_form_rate_' . md5( $ip );
	$hit = (int) get_transient( $key );

	if ( $hit >= 20 ) {
		return new WP_Error( 'blueworx_klaviyo_too_many', 'Too many submissions. Try again in a minute.', array( 'status' => 429 ) );
	}

	set_transient( $key, $hit + 1, MINUTE_IN_SECONDS );

	$form = (string) $request->get_param( 'form' );

	if ( ! blueworx_klaviyo_form_connection( 'html', $form ) ) {
		return new WP_Error( 'blueworx_klaviyo_unknown_form', 'That form is not connected.', array( 'status' => 404 ) );
	}

	$fields = array();

	foreach ( array_slice( (array) $request->get_param( 'fields' ), 0, 40 ) as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$fields[] = array(
			'name'    => isset( $field['name'] ) ? sanitize_text_field( substr( (string) $field['name'], 0, 100 ) ) : '',
			'label'   => isset( $field['label'] ) ? sanitize_text_field( substr( (string) $field['label'], 0, 200 ) ) : '',
			'value'   => isset( $field['value'] ) && is_scalar( $field['value'] ) ? sanitize_text_field( substr( (string) $field['value'], 0, 500 ) ) : '',
			'checked' => array_key_exists( 'checked', $field ) && null !== $field['checked'] ? blueworx_klaviyo_is_ticked( $field['checked'] ) : null,
		);
	}

	$queued = blueworx_klaviyo_form_submitted( 'html', $form, $fields, (string) $request->get_param( 'page' ) );

	return new WP_REST_Response( array( 'queued' => (bool) $queued ), 202 );
}

// --- The Klaviyo screen -------------------------------------------------------

/**
 * One row of the form connections editor.
 *
 * @param array|null  $connection The connection, or null for the blank template row.
 * @param string      $index      The row's index in the posted array.
 * @param array|false $lists      The account's lists, names keyed by ID, or false when unknown.
 * @return string HTML.
 */
function blueworx_klaviyo_form_row( $connection, $index, $lists ) {
	$connection = wp_parse_args(
		is_array( $connection ) ? $connection : array(),
		array(
			'kind'    => 'elementor',
			'match'   => '',
			'list'    => '',
			'consent' => '',
			'tag'     => '',
		)
	);

	$name = 'blueworx_klaviyo_forms[' . $index . ']';

	$fields = blueworx_ds_field(
		array(
			'label'   => __( 'Which form', 'blueworx-labs-wordpress' ),
			'control' => blueworx_ds_select(
				array(
					'name'     => $name . '[kind]',
					'options'  => blueworx_klaviyo_form_kinds(),
					'selected' => $connection['kind'],
				)
			) . blueworx_ds_input(
				array(
					'name'  => $name . '[match]',
					'value' => $connection['match'],
					'attrs' => array(
						'placeholder'  => __( 'The form\'s name or id', 'blueworx-labs-wordpress' ),
						'autocomplete' => 'off',
						'data-match'   => '1',
					),
				)
			),
			'help'    => __( 'Elementor: the Form Name set on the form. SureForms: the number in the form\'s address when you edit it. HTML: the id attribute on the <form> tag.', 'blueworx-labs-wordpress' ),
		)
	);

	if ( is_array( $lists ) && $lists ) {
		$options = array( '' => __( 'Identify only, no list', 'blueworx-labs-wordpress' ) ) + $lists;

		if ( '' !== $connection['list'] && ! isset( $lists[ $connection['list'] ] ) ) {
			$options[ $connection['list'] ] = $connection['list'];
		}

		$list = blueworx_ds_select(
			array(
				'name'     => $name . '[list]',
				'options'  => $options,
				'selected' => $connection['list'],
			)
		);
	} else {
		$list = blueworx_ds_input(
			array(
				'name'  => $name . '[list]',
				'value' => $connection['list'],
				'attrs' => array(
					'placeholder'  => __( 'List ID from Klaviyo, or empty to identify only', 'blueworx-labs-wordpress' ),
					'autocomplete' => 'off',
				),
			)
		);
	}

	$fields .= blueworx_ds_field(
		array(
			'label'   => __( 'Klaviyo list', 'blueworx-labs-wordpress' ),
			'control' => $list,
			'help'    => __( 'Where a sign-up is subscribed. Somebody who leaves a marketing tick box unticked is never subscribed.', 'blueworx-labs-wordpress' ),
		)
	);

	$fields .= blueworx_ds_field(
		array(
			'label'   => __( 'Consent wording', 'blueworx-labs-wordpress' ),
			'control' => blueworx_ds_textarea(
				array(
					'name'  => $name . '[consent]',
					'value' => $connection['consent'],
					'rows'  => 2,
				)
			),
			'help'    => __( 'The exact words shown beside the button. Sent with each sign-up as the record of what they agreed to.', 'blueworx-labs-wordpress' ),
		)
	);

	$fields .= blueworx_ds_field(
		array(
			'label'   => __( 'Source tag', 'blueworx-labs-wordpress' ),
			'control' => blueworx_ds_input(
				array(
					'name'  => $name . '[tag]',
					'value' => $connection['tag'],
					'attrs' => array(
						'placeholder'  => 'newsletter',
						'autocomplete' => 'off',
					),
				)
			),
			'help'    => __( 'A short word Klaviyo records as signup_source, so campaigns can tell this form\'s sign-ups apart.', 'blueworx-labs-wordpress' ),
		)
	);

	$bar = '<div class="bw-repeater__bar">' . blueworx_ds_icon_button(
		array(
			'icon'  => 'trash-2',
			'label' => __( 'Remove this form', 'blueworx-labs-wordpress' ),
			'attrs' => array( 'data-remove-form' => '1' ),
		)
	) . '</div>';

	return '<div class="bw-repeater__row" data-form-row="1">' . $bar . '<div class="bw-repeater__fields">' . $fields . '</div></div>';
}

/**
 * The Website forms card on the Klaviyo screen.
 *
 * @return void
 */
function blueworx_klaviyo_forms_card() {
	$connections = blueworx_klaviyo_form_connections();
	$lists       = blueworx_klaviyo_connected() ? blueworx_klaviyo_lists() : false;
	$lists       = is_wp_error( $lists ) ? false : $lists;
	$rows        = '';

	foreach ( $connections as $index => $connection ) {
		$rows .= blueworx_klaviyo_form_row( $connection, (string) $index, $lists );
	}

	$empty = sprintf(
		'<p class="bw-repeater__empty" data-forms-empty%1$s>%2$s</p>',
		$connections ? ' hidden' : '',
		esc_html__( 'No forms are connected yet. Add one to send its sign-ups to Klaviyo.', 'blueworx-labs-wordpress' )
	);

	$template = '<template data-form-template>' . blueworx_klaviyo_form_row( null, '__index__', $lists ) . '</template>';

	$body = '<p>' . esc_html__( 'Each form here sends its sign-ups to Klaviyo. Fields are found by their label (email, first name, last name, a marketing tick box), so the form can change without breaking this.', 'blueworx-labs-wordpress' ) . '</p>'
		. '<div class="bw-repeater" data-forms-list>' . $rows . $empty . '</div>' . $template
		. '<p>' . blueworx_ds_button(
			array(
				'label'   => __( 'Add a form', 'blueworx-labs-wordpress' ),
				'icon'    => 'plus',
				'variant' => 'secondary',
				'type'    => 'button',
				'attrs'   => array( 'data-add-form' => '1' ),
			)
		) . '</p>';

	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helpers escape everything they emit.
		array(
			'title' => __( 'Website forms', 'blueworx-labs-wordpress' ),
			'body'  => $body,
		)
	);
}
add_action( 'blueworx_klaviyo_screen_cards', 'blueworx_klaviyo_forms_card' );

/**
 * Saves the form connections when the Klaviyo screen saves.
 *
 * @param string[] $refused Labels refused so far.
 * @return string[]
 */
function blueworx_klaviyo_forms_save( $refused ) {
	// The caller has verified the nonce and the capability; every row is cleaned below.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$posted = isset( $_POST['blueworx_klaviyo_forms'] ) ? wp_unslash( $_POST['blueworx_klaviyo_forms'] ) : array();
	$clean  = blueworx_klaviyo_clean_connections( $posted );

	if ( $clean['refused'] ) {
		return array_merge( (array) $refused, $clean['refused'] );
	}

	update_option( BLUEWORX_KLAVIYO_FORMS_OPTION, $clean['values'], 'no' );

	if ( function_exists( 'blueworx_purge_site_cache' ) ) {
		blueworx_purge_site_cache( 'Klaviyo forms' );
	}

	return (array) $refused;
}
add_filter( 'blueworx_klaviyo_save_settings', 'blueworx_klaviyo_forms_save' );

/**
 * Loads the editor's script on the Klaviyo screen.
 *
 * @param string $hook_suffix The screen.
 * @return void
 */
function blueworx_klaviyo_forms_admin_assets( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'blueworx_page_blueworx-klaviyo', 'admin_page_blueworx-klaviyo' ), true ) ) {
		return;
	}

	wp_enqueue_script(
		'blueworx-klaviyo-forms-admin',
		BLUEWORX_LABS_URL . 'assets/js/klaviyo-forms-admin.js',
		array(),
		blueworx_get_admin_asset_version( 'assets/js/klaviyo-forms-admin.js' ),
		true
	);
}
add_action( 'admin_enqueue_scripts', 'blueworx_klaviyo_forms_admin_assets' );

/**
 * Says what the forms send, on the Klaviyo screen.
 *
 * @param array $sources Entries with 'label' and 'text'.
 * @return array
 */
function blueworx_klaviyo_forms_source( $sources ) {
	$connections = blueworx_klaviyo_form_connections();
	$tags        = array_map(
		static function ( $connection ) {
			return $connection['tag'];
		},
		$connections
	);

	$sources[] = array(
		'label' => __( 'Website forms', 'blueworx-labs-wordpress' ),
		'text'  => $connections
			? sprintf(
				/* translators: 1: number of forms, 2: their tags. */
				_n( '%1$s form connected: %2$s. Each sign-up is identified, recorded as Submitted Form, and subscribed to its list when they agreed.', '%1$s forms connected: %2$s. Each sign-up is identified, recorded as Submitted Form, and subscribed to its list when they agreed.', count( $connections ), 'blueworx-labs-wordpress' ),
				number_format_i18n( count( $connections ) ),
				implode( ', ', $tags )
			)
			: __( 'No forms connected yet. Add one in the Website forms card.', 'blueworx-labs-wordpress' ),
	);

	return $sources;
}
add_filter( 'blueworx_klaviyo_sources', 'blueworx_klaviyo_forms_source', 6 );

/**
 * Checks every connected form's list exists, for the Test connection button.
 *
 * @param array $result ok and lines.
 * @param array $lists  The account's lists, names keyed by ID.
 * @return array
 */
function blueworx_klaviyo_forms_test( $result, $lists ) {
	foreach ( blueworx_klaviyo_form_connections() as $connection ) {
		if ( '' === $connection['list'] ) {
			continue;
		}

		if ( isset( $lists[ $connection['list'] ] ) ) {
			$result['lines'][] = sprintf(
				/* translators: 1: the form's tag, 2: the list's name. */
				__( 'Form %1$s subscribes to "%2$s".', 'blueworx-labs-wordpress' ),
				$connection['tag'],
				$lists[ $connection['list'] ]
			);
		} else {
			$result['ok']      = false;
			$result['lines'][] = sprintf(
				/* translators: 1: the form's tag, 2: the list ID. */
				__( 'Form %1$s: Klaviyo cannot find list %2$s.', 'blueworx-labs-wordpress' ),
				$connection['tag'],
				$connection['list']
			);
		}
	}

	return $result;
}
add_filter( 'blueworx_klaviyo_test_connection', 'blueworx_klaviyo_forms_test', 6, 2 );
