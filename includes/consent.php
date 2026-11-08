<?php
/**
 * Cookie consent, through SureCookie.
 *
 * The theme does not write consent code of its own. Switching the function on
 * installs the free SureCookie plugin from wordpress.org, switches it on, and
 * sets it up the house way: ask first, block tracking until the visitor
 * agrees, no scanner sending page addresses to a third party, and the banner
 * pointing at the site's own cookies and privacy pages. The handful of
 * settings a site owner should change live on the theme's own Cookie consent
 * screen, in plain words; SureCookie's own screens are taken out of the menu.
 *
 * The theme's tracking codes wait for consent: with this on, each one is
 * written to the page the way SureCookie blocks a script, as text the browser
 * does not run, and SureCookie turns it into a live script the moment the
 * visitor agrees to its category, without a reload. Klaviyo's script does the
 * same (includes/klaviyo/tracking.php).
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** SureCookie's plugin file, relative to the plugins folder. */
const BLUEWORX_CONSENT_PLUGIN = 'surecookie/surecookie.php';

/** SureCookie's slug on wordpress.org. */
const BLUEWORX_CONSENT_SLUG = 'surecookie';

/** The option recording how the last install attempt went. */
const BLUEWORX_CONSENT_STATE = 'blueworx_consent_install';

/** The option recording which version of the house defaults a site has had. */
const BLUEWORX_CONSENT_DEFAULTS_DONE = 'blueworx_consent_defaults_version';

/** Bump when the house defaults change, so existing sites get the new ones once. */
const BLUEWORX_CONSENT_DEFAULTS_VERSION = 1;

/**
 * Whether SureCookie's files are on the site.
 *
 * @return bool
 */
function blueworx_consent_plugin_present() {
	return file_exists( WP_PLUGIN_DIR . '/' . BLUEWORX_CONSENT_PLUGIN );
}

/**
 * Whether SureCookie is switched on.
 *
 * @return bool
 */
function blueworx_consent_plugin_active() {
	if ( defined( 'SURECOOKIE_VERSION' ) ) {
		return true;
	}

	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	return is_plugin_active( BLUEWORX_CONSENT_PLUGIN );
}

/**
 * Whether consent is in force: the function is on and SureCookie is running.
 *
 * @return bool
 */
function blueworx_consent_active() {
	return function_exists( 'blueworx_feature_enabled' ) && blueworx_feature_enabled( 'cookie_consent' ) && blueworx_consent_plugin_active();
}

/**
 * What switching the function on has to do. Pure.
 *
 * @param bool $present     SureCookie's files are on the site.
 * @param bool $active      SureCookie is switched on.
 * @param bool $can_install This person may install plugins here.
 * @return string 'nothing', 'activate', 'install' or 'no-permission'.
 */
function blueworx_consent_plan( $present, $active, $can_install ) {
	if ( $active ) {
		return 'nothing';
	}

	if ( $present ) {
		return 'activate';
	}

	return $can_install ? 'install' : 'no-permission';
}

/**
 * The house defaults SureCookie gets on a BlueWorx site. Pure.
 *
 * Ask first, and block tracking until the visitor agrees: the safe side of
 * every cookie law. The scanner stays off, because it sends the site's page
 * addresses to SureCookie's service once a month, and that is a decision for
 * the site, not a default. SureCookie's own menu is left registered, because
 * the theme removes it itself; the banner links to the theme's cookies and
 * privacy pages.
 *
 * @param int $cookies_page_id The site's cookies page, or 0.
 * @param int $privacy_page_id The site's privacy policy page, or 0.
 * @return array<string,mixed> SureCookie settings keyed by SureCookie's own names.
 */
function blueworx_consent_house_defaults( $cookies_page_id, $privacy_page_id ) {
	return array(
		'banner_enabled'          => true,
		'consent_model'           => 'opt-in',
		'blocking_enabled'        => true,
		'consent_logging_enabled' => true,
		'auto_scan_enabled'       => false,
		'top_level_menu_enabled'  => true,
		'cookie_policy_page_id'   => (int) $cookies_page_id,
		'privacy_policy_page_id'  => (int) $privacy_page_id,
	);
}

/**
 * Writes settings into SureCookie.
 *
 * Through SureCookie's own writer when it is loaded, so every value is checked
 * the way its screens check it; straight into its option otherwise, which is
 * the case right after an install, before the plugin has loaded once.
 *
 * @param array<string,mixed> $settings SureCookie settings keyed by its names.
 * @return void
 */
function blueworx_consent_write_settings( $settings ) {
	if ( class_exists( '\SureCookie\Inc\Functions\Settings' ) && is_callable( array( '\SureCookie\Inc\Functions\Settings', 'update' ) ) ) {
		foreach ( $settings as $key => $value ) {
			\SureCookie\Inc\Functions\Settings::update( $key, $value );
		}
		return;
	}

	$option = get_option( 'surecookie_settings', array() );
	$option = is_array( $option ) ? $option : array();

	update_option( 'surecookie_settings', array_merge( $option, $settings ) );
}

/**
 * Reads one SureCookie setting, with SureCookie's default when it is unset.
 *
 * @param string $key      Setting name.
 * @param mixed  $fallback What to answer when SureCookie is not loaded and the option is unset.
 * @return mixed
 */
function blueworx_consent_read_setting( $key, $fallback = '' ) {
	if ( class_exists( '\SureCookie\Inc\Functions\Settings' ) && is_callable( array( '\SureCookie\Inc\Functions\Settings', 'get' ) ) ) {
		return \SureCookie\Inc\Functions\Settings::get( $key );
	}

	$option = get_option( 'surecookie_settings', array() );

	return is_array( $option ) && array_key_exists( $key, $option ) ? $option[ $key ] : $fallback;
}

/**
 * Applies the house defaults, once per version of them.
 *
 * Also stops SureCookie bouncing the next admin page load into its own
 * onboarding, which asks the questions these defaults have answered.
 *
 * @param bool $force Apply again even if this version has been applied.
 * @return void
 */
function blueworx_consent_apply_defaults( $force = false ) {
	if ( ! $force && (int) get_option( BLUEWORX_CONSENT_DEFAULTS_DONE, 0 ) >= BLUEWORX_CONSENT_DEFAULTS_VERSION ) {
		return;
	}

	$cookies = function_exists( 'blueworx_legal_page_id' ) ? blueworx_legal_page_id( 'cookies' ) : 0;
	$privacy = (int) get_option( 'wp_page_for_privacy_policy', 0 );

	blueworx_consent_write_settings( blueworx_consent_house_defaults( $cookies, $privacy ) );

	delete_option( 'surecookie_do_activation_redirect' );
	update_option( 'surecookie_onboarding_completed', true );
	update_option( BLUEWORX_CONSENT_DEFAULTS_DONE, BLUEWORX_CONSENT_DEFAULTS_VERSION );
}

/**
 * Installs SureCookie from wordpress.org and switches it on.
 *
 * Returns what happened in plain words, and records it for the screen. A
 * failure is never silent: the screen says what went wrong and offers the
 * manual route.
 *
 * @return array{status:string,message:string} status is 'ok' or 'failed'.
 */
function blueworx_consent_install() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plan   = blueworx_consent_plan( blueworx_consent_plugin_present(), blueworx_consent_plugin_active(), current_user_can( 'install_plugins' ) );
	$result = array(
		'status'  => 'ok',
		'message' => '',
	);

	switch ( $plan ) {
		case 'nothing':
			break;

		case 'no-permission':
			$result = array(
				'status'  => 'failed',
				'message' => is_multisite()
					? __( 'Plugins on this network are installed by a network administrator. Ask them to install SureCookie from the plugin directory, then switch this on again.', 'blueworx-labs-wordpress' )
					: __( 'This account may not install plugins. Ask an administrator to install SureCookie from the plugin directory, then switch this on again.', 'blueworx-labs-wordpress' ),
			);
			break;

		case 'install':
			$error = blueworx_consent_download_and_install();

			if ( '' !== $error ) {
				$result = array(
					'status'  => 'failed',
					'message' => $error,
				);
				break;
			}
			// Fall through to switch it on.

		case 'activate':
			$activated = activate_plugin( BLUEWORX_CONSENT_PLUGIN, '', is_multisite() && is_network_admin() );

			if ( is_wp_error( $activated ) ) {
				$result = array(
					'status'  => 'failed',
					/* translators: %s: the error WordPress gave. */
					'message' => sprintf( __( 'SureCookie is installed but could not be switched on: %s', 'blueworx-labs-wordpress' ), $activated->get_error_message() ),
				);
			}
			break;
	}

	if ( 'ok' === $result['status'] ) {
		blueworx_consent_apply_defaults();

		// Every activation sets this, and the next admin page load would bounce
		// into SureCookie's onboarding, which the defaults have already answered.
		delete_option( 'surecookie_do_activation_redirect' );
		update_option( 'surecookie_onboarding_completed', true );
	}

	update_option(
		BLUEWORX_CONSENT_STATE,
		array(
			'status'  => $result['status'],
			'message' => $result['message'],
			'time'    => time(),
		),
		false
	);

	return $result;
}

/**
 * Fetches SureCookie from wordpress.org and unpacks it into the plugins folder.
 *
 * @return string '' on success, otherwise what went wrong, in plain words.
 */
function blueworx_consent_download_and_install() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$info = plugins_api(
		'plugin_information',
		array(
			'slug'   => BLUEWORX_CONSENT_SLUG,
			'fields' => array(
				'sections' => false,
				'tags'     => false,
				'banners'  => false,
				'icons'    => false,
			),
		)
	);

	if ( is_wp_error( $info ) || empty( $info->download_link ) ) {
		return __( 'The site could not reach wordpress.org to fetch SureCookie. Check the host allows outgoing connections, or install it by hand.', 'blueworx-labs-wordpress' );
	}

	// The quiet skin: it answers every question itself and prints nothing,
	// which is what a request that is really a settings save needs.
	$upgrader  = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
	$installed = $upgrader->install( $info->download_link );

	if ( is_wp_error( $installed ) ) {
		/* translators: %s: the error WordPress gave. */
		return sprintf( __( 'SureCookie could not be installed: %s', 'blueworx-labs-wordpress' ), $installed->get_error_message() );
	}

	if ( true !== $installed || ! blueworx_consent_plugin_present() ) {
		$errors = $upgrader->skin->get_upgrade_messages();
		$detail = is_array( $errors ) && $errors ? (string) end( $errors ) : '';

		return '' !== $detail
			/* translators: %s: the last message WordPress gave. */
			? sprintf( __( 'SureCookie could not be installed. WordPress said: %s', 'blueworx-labs-wordpress' ), wp_strip_all_tags( $detail ) )
			: __( 'SureCookie could not be installed. The plugins folder may not be writable by the site.', 'blueworx-labs-wordpress' );
	}

	return '';
}

/**
 * The last install attempt, or null when there has not been one.
 *
 * @return array{status:string,message:string,time:int}|null
 */
function blueworx_consent_state() {
	$state = get_option( BLUEWORX_CONSENT_STATE, null );

	if ( ! is_array( $state ) || empty( $state['status'] ) ) {
		return null;
	}

	return array(
		'status'  => (string) $state['status'],
		'message' => (string) ( $state['message'] ?? '' ),
		'time'    => (int) ( $state['time'] ?? 0 ),
	);
}

/**
 * Runs when the function is switched on: installs and sets up SureCookie.
 *
 * @return void
 */
function blueworx_consent_on_switched_on() {
	blueworx_consent_install();
	blueworx_consent_refresh_cache();
}

/**
 * Runs when the function is switched off: SureCookie goes quiet.
 *
 * Deactivated rather than deleted, so switching back on is instant and the
 * consent log is kept; its settings stay in the database for the same
 * reason, and go when the plugin is deleted. The theme's own record of the
 * install is dropped, so a later switch-on starts clean.
 *
 * @return void
 */
function blueworx_consent_on_switched_off() {
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( blueworx_consent_plugin_active() ) {
		deactivate_plugins( BLUEWORX_CONSENT_PLUGIN );
	}

	delete_option( BLUEWORX_CONSENT_STATE );
	blueworx_consent_refresh_cache();
}

/**
 * Clears cached pages, so the banner appears or goes for visitors at once.
 *
 * @return void
 */
function blueworx_consent_refresh_cache() {
	if ( function_exists( 'blueworx_purge_site_cache' ) ) {
		blueworx_purge_site_cache( 'cookie consent' );
	}
}

/**
 * Rewrites a script's opening tag the way SureCookie blocks a script. Pure.
 *
 * The browser does not run text/plain; SureCookie's script turns it back
 * into a live script when the visitor agrees to its category, with the
 * address moved back from data-surecookie-src. The shape is SureCookie's own
 * (its Blocker::modify_script_tag), so its restore code needs nothing from
 * us. A tag already carrying a category is left alone.
 *
 * @param string $tag      The opening tag, e.g. '<script>' or '<script async src="...">'.
 * @param string $category 'analytics' or 'marketing'.
 * @param string $name     The service, for SureCookie's own listing.
 * @return string
 */
function blueworx_consent_gate_script_tag( $tag, $category, $name ) {
	$tag = (string) $tag;

	if ( false !== stripos( $tag, 'data-surecookie-category' ) || ! preg_match( '/^<script(\s[^>]*)?>$/is', $tag, $m ) ) {
		return $tag;
	}

	$attributes = isset( $m[1] ) ? ' ' . trim( $m[1] ) : '';
	$src        = '';

	if ( preg_match( '/\ssrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attributes, $s ) ) {
		$src        = '' !== (string) $s[1] ? $s[1] : ( isset( $s[2] ) && '' !== (string) $s[2] ? $s[2] : ( isset( $s[3] ) ? $s[3] : '' ) );
		$attributes = (string) preg_replace( '/\ssrc\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attributes );
	}

	$attributes = (string) preg_replace( '/\stype\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attributes );
	$attributes = trim( $attributes );

	$out = '<script type="text/plain"'
		. ' data-surecookie-category="' . esc_attr( 'marketing' === $category ? 'marketing' : 'analytics' ) . '"'
		. ' data-surecookie-name="' . esc_attr( $name ) . '"'
		. ' data-surecookie-original-type="none"';

	if ( '' !== $src ) {
		$out .= ' data-surecookie-src="' . esc_url( $src ) . '"';
	}

	if ( '' !== $attributes ) {
		$out .= ' ' . $attributes;
	}

	return $out . '>';
}

/**
 * Gates the theme's tracking codes while consent is in force.
 *
 * @param string $tag      The opening script tag.
 * @param string $category 'analytics' or 'marketing'.
 * @param string $name     The service.
 * @return string
 */
function blueworx_consent_filter_tracking_tag( $tag, $category, $name ) {
	return blueworx_consent_active() ? blueworx_consent_gate_script_tag( $tag, $category, $name ) : $tag;
}
add_filter( 'blueworx_tracking_script_tag', 'blueworx_consent_filter_tracking_tag', 10, 3 );

/**
 * Drops the tracking codes' no-script fallbacks while consent is in force.
 *
 * A pixel or frame fires with no script to stop it, so there is no consent
 * it could wait for. Without scripts there is no banner either, so a visitor
 * with scripts off is simply not tracked.
 *
 * @param bool $allowed Whether the fallbacks are printed.
 * @return bool
 */
function blueworx_consent_filter_tracking_noscript( $allowed ) {
	return blueworx_consent_active() ? false : $allowed;
}
add_filter( 'blueworx_tracking_noscript', 'blueworx_consent_filter_tracking_noscript' );

/**
 * Lists SureCookie's own cookie on the cookies page.
 *
 * @param array $cookies Declarations so far.
 * @return array
 */
function blueworx_consent_cookie_declarations( $cookies ) {
	if ( ! blueworx_consent_active() ) {
		return $cookies;
	}

	$cookies[] = array(
		'name'     => 'surecookie_user_consent',
		'provider' => __( 'This site', 'blueworx-labs-wordpress' ),
		'purpose'  => __( 'Remembers the cookie choices you made in the banner.', 'blueworx-labs-wordpress' ),
		'duration' => __( 'One year', 'blueworx-labs-wordpress' ),
		'category' => __( 'Essential', 'blueworx-labs-wordpress' ),
	);

	return $cookies;
}
add_filter( 'blueworx_cookie_declarations', 'blueworx_consent_cookie_declarations' );

/**
 * Whether this person may reach SureCookie's own screens.
 *
 * On a network that is a super admin; on a single site anyone who manages
 * options, which is the same rule the Design screen uses. A site that wants
 * it tighter says so through the filter.
 *
 * @return bool
 */
function blueworx_consent_full_access() {
	$allowed = is_multisite() ? is_super_admin() : current_user_can( 'manage_options' );

	/**
	 * Filters who may open SureCookie's own screens.
	 *
	 * @param bool $allowed True to allow.
	 */
	return (bool) apply_filters( 'blueworx_consent_full_access', $allowed );
}

/**
 * Takes SureCookie's own menu out of the admin.
 *
 * Its screens stay reachable by address, for the people
 * blueworx_consent_full_access() allows, from a link on the theme's screen.
 *
 * @return void
 */
function blueworx_consent_hide_menu() {
	if ( ! blueworx_consent_plugin_active() ) {
		return;
	}

	remove_menu_page( 'surecookie' );
	remove_submenu_page( 'options-general.php', 'surecookie' );
}
// After SureCookie has registered it: its menu callback runs at the default priority.
add_action( 'admin_menu', 'blueworx_consent_hide_menu', 999 );

/**
 * Sends anyone who should not be on SureCookie's screens to the theme's own.
 *
 * @return void
 */
function blueworx_consent_guard_screens() {
	if ( ! blueworx_consent_plugin_active() || blueworx_consent_full_access() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads which screen this is.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( '' === $page || 0 !== strpos( $page, 'surecookie' ) ) {
		return;
	}

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-consent' ) );
	exit;
}
add_action( 'admin_init', 'blueworx_consent_guard_screens' );

/**
 * Keeps SureCookie's asks for a review off the site's admin screens.
 *
 * @return bool
 */
function blueworx_consent_no_rating_notice() {
	return false;
}
add_filter( 'surecookie_show_rating_notice', 'blueworx_consent_no_rating_notice' );

/**
 * The settings the theme's screen offers, with SureCookie's names behind them.
 *
 * @return array<string,array{label:string,help:string,type:string,options?:array<string,string>}>
 */
function blueworx_consent_fields() {
	return array(
		'message_heading'         => array(
			'label' => __( 'Banner heading', 'blueworx-labs-wordpress' ),
			'help'  => __( 'A short line at the top of the banner. Leave it empty for none.', 'blueworx-labs-wordpress' ),
			'type'  => 'text',
		),
		'message_description'     => array(
			'label' => __( 'Banner message', 'blueworx-labs-wordpress' ),
			'help'  => __( 'What the banner says. Keep it to a sentence or two.', 'blueworx-labs-wordpress' ),
			'type'  => 'textarea',
		),
		'banner_position'         => array(
			'label'   => __( 'Where the banner sits', 'blueworx-labs-wordpress' ),
			'help'    => '',
			'type'    => 'select',
			'options' => array(
				'bottom' => __( 'Bottom of the screen', 'blueworx-labs-wordpress' ),
				'top'    => __( 'Top of the screen', 'blueworx-labs-wordpress' ),
			),
		),
		'floating_widget_enabled' => array(
			'label' => __( 'Show a small button for changing cookie choices later', 'blueworx-labs-wordpress' ),
			'help'  => __( 'A visitor who has answered the banner can change their mind from it.', 'blueworx-labs-wordpress' ),
			'type'  => 'checkbox',
		),
	);
}

/**
 * Cleans the posted screen into SureCookie settings. Pure.
 *
 * @param array<string,mixed> $posted Raw post data, already unslashed.
 * @return array<string,mixed> Settings keyed by SureCookie's names.
 */
function blueworx_consent_clean_settings( $posted ) {
	$clean = array();

	foreach ( blueworx_consent_fields() as $key => $field ) {
		$field_name = 'blueworx_consent_' . $key;

		switch ( $field['type'] ) {
			case 'checkbox':
				$clean[ $key ] = ! empty( $posted[ $field_name ] );
				break;

			case 'select':
				$value         = isset( $posted[ $field_name ] ) ? (string) $posted[ $field_name ] : '';
				$clean[ $key ] = isset( $field['options'][ $value ] ) ? $value : array_key_first( $field['options'] );
				break;

			case 'textarea':
				$clean[ $key ] = isset( $posted[ $field_name ] ) ? trim( sanitize_textarea_field( (string) $posted[ $field_name ] ) ) : '';
				break;

			default:
				$clean[ $key ] = isset( $posted[ $field_name ] ) ? trim( sanitize_text_field( (string) $posted[ $field_name ] ) ) : '';
		}
	}

	return $clean;
}

/**
 * Saves the Cookie consent screen.
 *
 * @return void
 */
function blueworx_consent_handle_save() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_save_consent' );

	blueworx_consent_write_settings( blueworx_consent_clean_settings( wp_unslash( $_POST ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned field by field in blueworx_consent_clean_settings().
	blueworx_consent_refresh_cache();

	set_transient( 'blueworx_consent_notice', 'saved', 30 );
	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-consent' ) );
	exit;
}
add_action( 'admin_post_blueworx_save_consent', 'blueworx_consent_handle_save' );

/**
 * Tries the install again from the screen's button.
 *
 * @return void
 */
function blueworx_consent_handle_retry() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_consent_retry' );

	$result = blueworx_consent_install();
	blueworx_consent_refresh_cache();

	set_transient( 'blueworx_consent_notice', 'ok' === $result['status'] ? 'installed' : 'failed', 30 );
	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-consent' ) );
	exit;
}
add_action( 'admin_post_blueworx_consent_retry', 'blueworx_consent_handle_retry' );

/**
 * The status card's words. Pure.
 *
 * @param bool       $enabled The function is on.
 * @param bool       $active  SureCookie is running.
 * @param bool       $present SureCookie's files are there.
 * @param array|null $state   The last install attempt.
 * @return array{tone:string,title:string,text:string}
 */
function blueworx_consent_status( $enabled, $active, $present, $state ) {
	if ( ! $enabled ) {
		return array(
			'tone'  => 'info',
			'title' => __( 'Cookie consent is switched off', 'blueworx-labs-wordpress' ),
			'text'  => __( 'No banner is shown and tracking codes load for everyone. Switch it on under Enhancements and the theme installs and sets up SureCookie for you.', 'blueworx-labs-wordpress' ),
		);
	}

	if ( $active ) {
		return array(
			'tone'  => 'success',
			'title' => __( 'SureCookie is installed and running', 'blueworx-labs-wordpress' ),
			'text'  => __( 'Visitors are asked before any tracking code runs, and the banner links to the cookies and privacy pages.', 'blueworx-labs-wordpress' ),
		);
	}

	if ( is_array( $state ) && 'failed' === $state['status'] && '' !== $state['message'] ) {
		return array(
			'tone'  => 'danger',
			'title' => __( 'SureCookie is not running', 'blueworx-labs-wordpress' ),
			'text'  => $state['message'],
		);
	}

	return array(
		'tone'  => 'warning',
		'title' => $present ? __( 'SureCookie is installed but switched off', 'blueworx-labs-wordpress' ) : __( 'SureCookie is not installed yet', 'blueworx-labs-wordpress' ),
		'text'  => __( 'Press Try again to install and switch it on, or install SureCookie by hand from Plugins, Add New.', 'blueworx-labs-wordpress' ),
	);
}

/**
 * Renders the Cookie consent screen.
 *
 * @return void
 */
function blueworx_render_consent_page() {
	$enabled = blueworx_feature_enabled( 'cookie_consent' );
	$active  = blueworx_consent_plugin_active();
	$present = blueworx_consent_plugin_present();
	$status  = blueworx_consent_status( $enabled, $active, $present, blueworx_consent_state() );

	$form = $enabled && $active
		? sprintf(
			'<form method="post" action="%1$s"><input type="hidden" name="action" value="blueworx_save_consent" />%2$s',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'blueworx_save_consent', '_wpnonce', true, false )
		)
		: '';

	blueworx_open_admin_page(
		array(
			'title'   => __( 'Cookie consent', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'The cookie banner, through SureCookie. The theme installs it, sets it up the right way and keeps the tracking codes waiting until a visitor agrees.', 'blueworx-labs-wordpress' ),
			'actions' => blueworx_ds_badge(
				$enabled && $active ? __( 'Live', 'blueworx-labs-wordpress' ) : __( 'Not live', 'blueworx-labs-wordpress' ),
				$enabled && $active ? 'success' : 'neutral',
				true
			),
			'form'    => $form,
		)
	);

	$notice = get_transient( 'blueworx_consent_notice' );

	if ( false !== $notice ) {
		delete_transient( 'blueworx_consent_notice' );
		$texts = array(
			'saved'     => array( 'success', __( 'Settings saved.', 'blueworx-labs-wordpress' ) ),
			'installed' => array( 'success', __( 'SureCookie is installed and running.', 'blueworx-labs-wordpress' ) ),
			'failed'    => array( 'danger', __( 'That did not work. The details are below.', 'blueworx-labs-wordpress' ) ),
		);

		if ( isset( $texts[ $notice ] ) ) {
			echo wp_kses(
				blueworx_ds_notice(
					array(
						'tone' => $texts[ $notice ][0],
						'text' => $texts[ $notice ][1],
					)
				),
				blueworx_ds_allowed_html()
			);
		}
	}

	$actions = '';

	if ( ! $enabled ) {
		$actions = blueworx_ds_button(
			array(
				'label' => __( 'Go to Enhancements', 'blueworx-labs-wordpress' ),
				'icon'  => 'arrow-right',
				'href'  => admin_url( 'admin.php?page=blueworx-labs-wordpress&section=tracking' ),
			)
		);
	} elseif ( ! $active ) {
		$actions = sprintf(
			'<form method="post" action="%1$s"><input type="hidden" name="action" value="blueworx_consent_retry" />%2$s%3$s</form>%4$s',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'blueworx_consent_retry', '_wpnonce', true, false ),
			blueworx_ds_button(
				array(
					'label'   => __( 'Try again', 'blueworx-labs-wordpress' ),
					'variant' => 'primary',
					'type'    => 'submit',
					'attrs'   => array( 'data-testid' => 'blueworx-consent-retry' ),
				)
			),
			blueworx_ds_button(
				array(
					'label' => __( 'Install by hand', 'blueworx-labs-wordpress' ),
					'icon'  => 'download',
					'href'  => admin_url( 'plugin-install.php?s=surecookie&tab=search&type=term' ),
				)
			)
		);
	}

	echo wp_kses(
		blueworx_ds_notice(
			array(
				'tone'    => $status['tone'],
				'title'   => $status['title'],
				'text'    => $status['text'],
				'actions' => $actions,
			)
		),
		blueworx_ds_allowed_html()
	);

	if ( ! $enabled || ! $active ) {
		blueworx_close_admin_page();
		return;
	}

	$fields = '';

	foreach ( blueworx_consent_fields() as $key => $field ) {
		$id      = 'blueworx_consent_' . $key;
		$current = blueworx_consent_read_setting( $key );

		switch ( $field['type'] ) {
			case 'checkbox':
				$fields .= blueworx_ds_form_row(
					blueworx_ds_checkbox(
						array(
							'name'    => $id,
							'label'   => $field['label'],
							'value'   => '1',
							'checked' => (bool) $current,
							'help'    => $field['help'],
						)
					)
				);
				break;

			case 'select':
				$fields .= blueworx_ds_field(
					array(
						'label'   => $field['label'],
						'for'     => $id,
						'help'    => $field['help'],
						'control' => blueworx_ds_select(
							array(
								'name'     => $id,
								'id'       => $id,
								'options'  => $field['options'],
								'selected' => (string) $current,
							)
						),
					)
				);
				break;

			case 'textarea':
				$fields .= blueworx_ds_field(
					array(
						'label'   => $field['label'],
						'for'     => $id,
						'help'    => $field['help'],
						'control' => blueworx_ds_textarea(
							array(
								'name'  => $id,
								'id'    => $id,
								'value' => (string) $current,
								'rows'  => 3,
							)
						),
					)
				);
				break;

			default:
				$fields .= blueworx_ds_field(
					array(
						'label'   => $field['label'],
						'for'     => $id,
						'help'    => $field['help'],
						'control' => blueworx_ds_input(
							array(
								'name'  => $id,
								'id'    => $id,
								'value' => (string) $current,
							)
						),
					)
				);
		}
	}

	$cookies_id = function_exists( 'blueworx_legal_page_id' ) ? blueworx_legal_page_id( 'cookies' ) : 0;
	$links      = '';

	if ( $cookies_id > 0 ) {
		$links .= blueworx_ds_button(
			array(
				'label' => __( 'Edit the cookies page', 'blueworx-labs-wordpress' ),
				'icon'  => 'file-text',
				'href'  => get_edit_post_link( $cookies_id, 'raw' ),
			)
		);
	}

	if ( blueworx_consent_full_access() ) {
		$links .= blueworx_ds_button(
			array(
				'label' => __( "Open SureCookie's own screens", 'blueworx-labs-wordpress' ),
				'icon'  => 'settings',
				'href'  => admin_url( 'admin.php?page=surecookie' ),
			)
		);
	}

	echo wp_kses(
		blueworx_ds_card(
			array(
				'title' => __( 'The banner', 'blueworx-labs-wordpress' ),
				'body'  => $fields,
			)
		)
		. blueworx_ds_card(
			array(
				'title'  => __( 'How it is set up', 'blueworx-labs-wordpress' ),
				'body'   => '<p>' . esc_html__( 'Visitors are asked first, and nothing that tracks them runs until they agree: Google Tag Manager and Analytics wait for analytics consent, the Meta pixel and Klaviyo for marketing consent, and each starts the moment the visitor agrees, with no reload. Their choice is kept for a year. SureCookie\'s cookie scanner is off, because it sends the site\'s page addresses to SureCookie\'s own service.', 'blueworx-labs-wordpress' ) . '</p>',
				'footer' => $links,
			)
		),
		blueworx_ds_allowed_html()
	);

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
