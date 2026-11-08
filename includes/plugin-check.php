<?php
/**
 * Plugin check: flags installed plugins that are not on the BlueWorx approved list.
 *
 * The list lives in the foundation repo (approved-plugins.json) so it is edited
 * in one place, and each site fetches it once a day. This only ever reports:
 * nothing is deactivated, removed or blocked, because an unexpected plugin is
 * something for a person to look at, not something to break a site over.
 *
 * Clubhouse V1 is deliberately absent from the list, so a V1 install on a site
 * running this theme is flagged and never ends up there by mistake.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where the approved list is fetched from.
 *
 * The v1 tag rather than main, so the list a site reads only changes when the
 * foundation moves the tag. A test can point it elsewhere with the filter.
 *
 * @return string
 */
function blueworx_plugin_check_url() {
	return (string) apply_filters( 'blueworx_approved_plugins_url', 'https://raw.githubusercontent.com/blueworx-io/bluegroup_core_foundation/v1/approved-plugins.json' );
}

/**
 * The slug of an installed plugin: its folder, or for a single-file plugin its
 * file name without .php.
 *
 * @param string $file Key from get_plugins(), e.g. "surecart/surecart.php".
 * @return string
 */
function blueworx_plugin_check_slug( $file ) {
	$file = (string) $file;

	if ( false !== strpos( $file, '/' ) ) {
		return substr( $file, 0, strpos( $file, '/' ) );
	}

	return preg_replace( '/\.php$/', '', $file );
}

/**
 * Reads the list file's text into slug => entry, or null when it is not a list.
 *
 * @param string $body Response body.
 * @return array|null
 */
function blueworx_plugin_check_parse( $body ) {
	$data = json_decode( (string) $body, true );

	if ( ! is_array( $data ) || ! isset( $data['plugins'] ) || ! is_array( $data['plugins'] ) ) {
		return null;
	}

	$plugins = array();

	foreach ( $data['plugins'] as $slug => $entry ) {
		$plugins[ (string) $slug ] = is_array( $entry ) ? $entry : array();
	}

	return $plugins;
}

/**
 * The installed plugins that are not on the approved list, active or not.
 *
 * @param array $installed As get_plugins() returns it, keyed by "folder/file.php".
 * @param array $approved  Approved list keyed by slug.
 * @return array The flagged entries of $installed, same keys.
 */
function blueworx_plugin_check_flagged( $installed, $approved ) {
	$flagged = array();

	foreach ( (array) $installed as $file => $data ) {
		if ( ! isset( $approved[ blueworx_plugin_check_slug( $file ) ] ) ) {
			$flagged[ $file ] = $data;
		}
	}

	return $flagged;
}

/**
 * Fetches the list from GitHub.
 *
 * @return array|null Slug => entry, or null when it could not be fetched.
 */
function blueworx_plugin_check_fetch() {
	$url = blueworx_plugin_check_url();

	if ( '' === $url ) {
		return null;
	}

	$response = wp_remote_get( $url, array( 'timeout' => 5 ) );

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	return blueworx_plugin_check_parse( wp_remote_retrieve_body( $response ) );
}

/**
 * The approved list, fetched at most once a day.
 *
 * A failed fetch keeps the last good list (stored in an option) and is not
 * retried for an hour, so a GitHub outage does not slow every admin page.
 *
 * @param bool $force Fetch now, ignoring the cache.
 * @return array {
 *     @type array|null $plugins Slug => entry, or null when no list is known.
 *     @type int        $fetched When the list was last fetched, 0 if never.
 * }
 */
function blueworx_plugin_check_list( $force = false ) {
	$cached = $force ? false : get_transient( 'blueworx_approved_plugins_cache' );

	if ( ! is_array( $cached ) ) {
		$fresh = blueworx_plugin_check_fetch();

		if ( null !== $fresh ) {
			$cached = array(
				'plugins' => $fresh,
				'fetched' => time(),
			);
			update_option( 'blueworx_approved_plugins_last_good', $cached, false );
			set_transient( 'blueworx_approved_plugins_cache', $cached, DAY_IN_SECONDS );
		} else {
			$last   = get_option( 'blueworx_approved_plugins_last_good', array() );
			$cached = array(
				'plugins' => isset( $last['plugins'] ) && is_array( $last['plugins'] ) ? $last['plugins'] : null,
				'fetched' => isset( $last['fetched'] ) ? (int) $last['fetched'] : 0,
			);
			set_transient( 'blueworx_approved_plugins_cache', $cached, HOUR_IN_SECONDS );
		}
	}

	// Lets a test, or a site with a reason, supply its own list. Null in means
	// no list is known; an array out means there is one.
	$cached['plugins'] = apply_filters( 'blueworx_approved_plugins', $cached['plugins'] );

	return $cached;
}

/**
 * Every installed plugin, with whether it is flagged. Null when no list is known,
 * so a site that cannot reach GitHub is not told every plugin is unapproved.
 *
 * @param bool $force Fetch the list now.
 * @return array|null {
 *     @type array $installed get_plugins().
 *     @type array $flagged   Subset of installed.
 *     @type int   $fetched   When the list was last fetched.
 * }
 */
function blueworx_plugin_check_result( $force = false ) {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$list      = blueworx_plugin_check_list( $force );
	$installed = get_plugins();

	if ( ! is_array( $list['plugins'] ) ) {
		return array(
			'installed' => $installed,
			'flagged'   => null,
			'fetched'   => $list['fetched'],
		);
	}

	return array(
		'installed' => $installed,
		'flagged'   => blueworx_plugin_check_flagged( $installed, $list['plugins'] ),
		'fetched'   => $list['fetched'],
	);
}

/**
 * The notice markup. Pure, so it can be checked without WordPress.
 *
 * @param int    $count Number of flagged plugins.
 * @param string $url   The Plugin check screen.
 * @return string
 */
function blueworx_plugin_check_notice_html( $count, $url ) {
	if ( $count < 1 ) {
		return '';
	}

	$title = sprintf(
		/* translators: %d: number of plugins. */
		_n( '%d plugin not on the BlueWorx approved list', '%d plugins not on the BlueWorx approved list', $count, 'blueworx-labs-wordpress' ),
		$count
	);

	// .notice keeps core's placement; .bw-admin inside it is what the design
	// system styles, the same pairing the store notice uses.
	return sprintf(
		'<div class="notice blueworx-plugin-check-notice"><div class="bw-admin">%s</div></div>',
		blueworx_ds_notice(
			array(
				'tone'    => 'warning',
				'title'   => $title,
				'actions' => blueworx_ds_button(
					array(
						'label'   => __( 'Open Plugin check', 'blueworx-labs-wordpress' ),
						'variant' => 'primary',
						'href'    => $url,
					)
				),
			)
		)
	);
}

/**
 * Prints the notice on every admin screen except the check itself.
 *
 * @return void
 */
function blueworx_plugin_check_render_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads which screen this is.
	if ( isset( $_GET['page'] ) && 'blueworx-plugin-check' === $_GET['page'] ) {
		return;
	}

	$result = blueworx_plugin_check_result();

	if ( empty( $result['flagged'] ) ) {
		return;
	}

	echo wp_kses(
		blueworx_plugin_check_notice_html( count( $result['flagged'] ), admin_url( 'admin.php?page=blueworx-plugin-check' ) ),
		blueworx_ds_allowed_html()
	);
}
add_action( 'admin_notices', 'blueworx_plugin_check_render_notice' );

/**
 * Fetches the list again when "Check again" is pressed.
 *
 * @return void
 */
function blueworx_plugin_check_handle_refresh() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_plugin_check_refresh' );

	blueworx_plugin_check_list( true );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-plugin-check&checked=1' ) );
	exit;
}
add_action( 'admin_post_blueworx_plugin_check_refresh', 'blueworx_plugin_check_handle_refresh' );

/**
 * Renders the Plugin check screen.
 *
 * @return void
 */
function blueworx_render_plugin_check_page() {
	$result  = blueworx_plugin_check_result();
	$flagged = $result['flagged'];
	$active  = (array) get_option( 'active_plugins', array() );

	if ( is_multisite() ) {
		$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}

	$form = sprintf(
		'<form method="post" action="%1$s"><input type="hidden" name="action" value="blueworx_plugin_check_refresh" />%2$s',
		esc_url( admin_url( 'admin-post.php' ) ),
		wp_nonce_field( 'blueworx_plugin_check_refresh', '_wpnonce', true, false )
	);

	if ( null === $flagged ) {
		$badge = blueworx_ds_badge( __( 'No list', 'blueworx-labs-wordpress' ), 'neutral', true );
	} elseif ( $flagged ) {
		$badge = blueworx_ds_badge( __( 'Needs a look', 'blueworx-labs-wordpress' ), 'warning', true );
	} else {
		$badge = blueworx_ds_badge( __( 'All clear', 'blueworx-labs-wordpress' ), 'success', true );
	}

	blueworx_open_admin_page(
		array(
			'title'   => __( 'Plugin check', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'Compares the plugins installed on this site with the BlueWorx approved list. Nothing is ever deactivated or removed from here.', 'blueworx-labs-wordpress' ),
			'actions' => $badge,
			'form'    => $form,
		)
	);

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides whether to say the list was just refreshed.
	if ( isset( $_GET['checked'] ) ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone' => 'success',
					'text' => __( 'Checked again.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	if ( null === $flagged ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone'  => 'info',
					'title' => __( 'The approved list could not be fetched', 'blueworx-labs-wordpress' ),
					'text'  => __( 'No plugins are flagged until a list has been fetched. Try again in a moment.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	} elseif ( ! $flagged ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone' => 'success',
					'text' => __( 'All clear. Every installed plugin is on the approved list.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	} else {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone'  => 'warning',
					'title' => sprintf(
						/* translators: %d: number of plugins. */
						_n( '%d plugin is not on the approved list', '%d plugins are not on the approved list', count( $flagged ), 'blueworx-labs-wordpress' ),
						count( $flagged )
					),
					'text'  => __( 'Nothing has been changed. Check whether each one is needed, and ask for it to be added to the list if so.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	$rows = '';

	foreach ( $result['installed'] as $file => $data ) {
		$is_active  = in_array( $file, $active, true );
		$is_flagged = is_array( $flagged ) && isset( $flagged[ $file ] );

		if ( null === $flagged ) {
			$status = blueworx_ds_badge( __( 'Unknown', 'blueworx-labs-wordpress' ), 'neutral' );
		} elseif ( $is_flagged ) {
			$status = blueworx_ds_badge( __( 'Not approved', 'blueworx-labs-wordpress' ), 'warning' );
		} else {
			$status = blueworx_ds_badge( __( 'Approved', 'blueworx-labs-wordpress' ), 'success' );
		}

		$rows .= sprintf(
			'<tr data-testid="plugin-row" data-slug="%1$s"><td class="bw-table__primary">%2$s<span class="bw-table__sub">%1$s</span></td><td>%3$s</td><td>%4$s</td><td>%5$s</td></tr>',
			esc_attr( blueworx_plugin_check_slug( $file ) ),
			esc_html( $data['Name'] ),
			esc_html( $data['Version'] ),
			esc_html( $is_active ? __( 'Active', 'blueworx-labs-wordpress' ) : __( 'Inactive', 'blueworx-labs-wordpress' ) ),
			$status
		);
	}

	if ( '' === $rows ) {
		$rows = '<tr><td colspan="4">' . esc_html__( 'No plugins are installed.', 'blueworx-labs-wordpress' ) . '</td></tr>';
	}

	$table = sprintf(
		'<div style="overflow-x:auto"><table class="bw-table"><thead><tr><th scope="col">%1$s</th><th scope="col">%2$s</th><th scope="col">%3$s</th><th scope="col">%4$s</th></tr></thead><tbody>%5$s</tbody></table></div>',
		esc_html__( 'Plugin', 'blueworx-labs-wordpress' ),
		esc_html__( 'Version', 'blueworx-labs-wordpress' ),
		esc_html__( 'Status', 'blueworx-labs-wordpress' ),
		esc_html__( 'Approved list', 'blueworx-labs-wordpress' ),
		$rows
	);

	echo wp_kses(
		blueworx_ds_card(
			array(
				'title' => __( 'Installed plugins', 'blueworx-labs-wordpress' ),
				'body'  => $table,
			)
		),
		blueworx_ds_allowed_html()
	);

	if ( $result['fetched'] ) {
		$fetched = sprintf(
			/* translators: %s: date and time. */
			__( 'List last fetched %s.', 'blueworx-labs-wordpress' ),
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $result['fetched'] )
		);
	} else {
		$fetched = __( 'The list has not been fetched yet.', 'blueworx-labs-wordpress' );
	}

	$bar = '<div class="bw-savebar"><span class="bw-savebar__hint" data-testid="plugin-check-fetched">'
		. esc_html( $fetched )
		. '</span>'
		. blueworx_ds_button(
			array(
				'label'   => __( 'Check again', 'blueworx-labs-wordpress' ),
				'variant' => 'primary',
				'icon'    => 'refresh-cw',
				'type'    => 'submit',
			)
		)
		. '</div></form>';

	blueworx_close_admin_page( $bar );
}
