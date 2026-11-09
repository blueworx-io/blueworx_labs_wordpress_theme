<?php
/**
 * Export users: every account on the site as an Excel spreadsheet.
 *
 * An Export link sits last in the Users menu. Clicking it downloads one .xlsx
 * file with a row per user: the account's own details, then a column for every
 * profile field a plugin has added.
 *
 * Passwords, sign-ins, permissions and screen preferences never go in: the
 * file is for reading about people, not for getting into their accounts.
 *
 * The workbook is written by hand and packed with WordPress's own PclZip, so
 * the theme needs no spreadsheet library. Every cell is stored as plain text
 * (or a plain number for the ID), so nothing a user typed into their profile
 * can run as a formula when the file is opened.
 *
 * Only administrators get any of it, and never a read-only account: the link
 * and the download each check on their own.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The most text Excel will hold in one cell.
 */
const BLUEWORX_USER_EXPORT_CELL_LIMIT = 32767;

/**
 * The most columns Excel will hold in one sheet.
 */
const BLUEWORX_USER_EXPORT_COLUMN_LIMIT = 16384;

/**
 * Whether the current person may export users.
 *
 * @return bool True when the function is on and they are a full administrator.
 */
function blueworx_user_export_allowed() {
	return blueworx_feature_enabled( 'user_export' )
		&& current_user_can( 'list_users' )
		&& current_user_can( 'promote_users' )
		&& null === blueworx_readonly_current_user();
}

/**
 * The download's address, carrying a nonce.
 *
 * @return string URL, already escaped for an href.
 */
function blueworx_user_export_url() {
	return wp_nonce_url( admin_url( 'admin-post.php?action=blueworx_export_users' ), 'blueworx_export_users' );
}

/**
 * Adds Export as the last row of the Users menu.
 *
 * The row is a plain link to the download rather than a screen of its own, so
 * one click is all it takes. Runs late so it lands below anything a plugin
 * adds to the same menu.
 *
 * @return void
 */
function blueworx_user_export_menu() {
	global $submenu;

	if ( empty( $submenu['users.php'] ) || ! blueworx_user_export_allowed() ) {
		return;
	}

	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- A menu row that is a link, which add_submenu_page() cannot make.
	$submenu['users.php'][] = array(
		esc_html__( 'Export', 'blueworx-labs-wordpress' ),
		'list_users',
		blueworx_user_export_url(),
	);
}
add_action( 'admin_menu', 'blueworx_user_export_menu', 9999 );

/**
 * Whether a user meta key is left out of the spreadsheet.
 *
 * Left out: anything that signs someone in or says what they may do, anything
 * WordPress or a plugin marks as internal with a leading underscore, how each
 * person has arranged their admin screens, and anything named like a secret.
 *
 * @param string $key    Meta key.
 * @param string $prefix The site's database table prefix.
 * @return bool True to leave it out.
 */
function blueworx_user_export_skip_meta( $key, $prefix ) {
	$key = (string) $key;

	if ( '' === $key || '_' === $key[0] ) {
		return true;
	}

	$exact = array(
		// These have columns of their own.
		'first_name',
		'last_name',
		'session_tokens',
		'default_password_nag',
		'community-events-location',
		'rich_editing',
		'syntax_highlighting',
		'comment_shortcuts',
		'admin_color',
		'use_ssl',
		'show_admin_bar_front',
		'locale',
		'dismissed_wp_pointers',
		'show_welcome_panel',
		'nav_menu_recently_edited',
		'enable_custom_fields',
	);

	if ( in_array( $key, $exact, true ) ) {
		return true;
	}

	// Stored once per site under its table prefix ("wp_", or "wp_2_" on a network).
	$per_site = 'capabilities|user_level|user-settings|user-settings-time|persisted_preferences|dashboard_quick_press_last_post_id|media_library_mode|_blueworx_view_as_role';

	if ( '' !== $prefix && preg_match( '/^' . preg_quote( $prefix, '/' ) . '(\d+_)?(' . $per_site . ')$/', $key ) ) {
		return true;
	}

	// How someone has laid out their own admin screens.
	if ( preg_match( '/^(closedpostboxes_|metaboxhidden_|meta-box-order_|screen_layout_)|^manage.*columnshidden$|_per_page$/', $key ) ) {
		return true;
	}

	// Anything named like a way in.
	return 1 === preg_match( '/pass(word|wd)|secret|token|api_?key|otp|2fa|two_factor|backup_codes|session/i', $key );
}

/**
 * Turns whatever is stored for one profile field into the text for its cell.
 *
 * @param array $values Every value stored under the key, as the database holds them.
 * @return string Cell text. Lists and settings come out as JSON; several values share the cell, one per line.
 */
function blueworx_user_export_meta_text( $values ) {
	$parts = array();

	foreach ( (array) $values as $value ) {
		$value = maybe_unserialize( $value );

		if ( is_bool( $value ) ) {
			$value = $value ? '1' : '0';
		} elseif ( is_array( $value ) || is_object( $value ) ) {
			$value = array() === $value ? '' : (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		$value = (string) $value;

		if ( '' !== $value ) {
			$parts[] = $value;
		}
	}

	return implode( "\n", $parts );
}

/**
 * A column's letter name.
 *
 * @param int $index Column number, counting from 0.
 * @return string "A" to "Z", then "AA" and on.
 */
function blueworx_user_export_column_name( $index ) {
	$name = '';
	++$index;

	while ( $index > 0 ) {
		$remainder = ( $index - 1 ) % 26;
		$name      = chr( 65 + $remainder ) . $name;
		$index     = intdiv( $index - $remainder - 1, 26 );
	}

	return $name;
}

/**
 * Makes text safe to sit inside the workbook.
 *
 * @param string $text Any text.
 * @return string The same text without anything a spreadsheet cannot hold, cut to what one cell takes, and escaped.
 */
function blueworx_user_export_escape( $text ) {
	$text  = wp_check_invalid_utf8( (string) $text, true );
	$clean = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text );
	$text  = null === $clean ? '' : str_replace( "\r", '', $clean );

	if ( mb_strlen( $text ) > BLUEWORX_USER_EXPORT_CELL_LIMIT ) {
		$text = mb_substr( $text, 0, BLUEWORX_USER_EXPORT_CELL_LIMIT );
	}

	return htmlspecialchars( $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
}

/**
 * One row of the sheet.
 *
 * @param int   $number Row number, counting from 1.
 * @param array $cells  The row's values, left to right. Integers are stored as numbers, everything else as text.
 * @param bool  $header Whether this is the heading row.
 * @return string XML.
 */
function blueworx_user_export_row_xml( $number, $cells, $header = false ) {
	$xml   = '<row r="' . (int) $number . '">';
	$style = $header ? ' s="1"' : '';

	foreach ( array_values( $cells ) as $index => $value ) {
		if ( '' === $value || null === $value ) {
			continue;
		}

		$ref = blueworx_user_export_column_name( $index ) . (int) $number;

		if ( is_int( $value ) ) {
			$xml .= '<c r="' . $ref . '"' . $style . '><v>' . $value . '</v></c>';
			continue;
		}

		$xml .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">' . blueworx_user_export_escape( $value ) . '</t></is></c>';
	}

	return $xml . '</row>';
}

/**
 * Wraps the rows in a complete sheet, with the heading row kept in view.
 *
 * @param string $rows_xml Every row, from blueworx_user_export_row_xml().
 * @return string XML.
 */
function blueworx_user_export_sheet_xml( $rows_xml ) {
	return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
		. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
		. '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
		. '<sheetFormatPr defaultRowHeight="15"/>'
		. '<cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="' . BLUEWORX_USER_EXPORT_COLUMN_LIMIT . '" width="24" customWidth="1"/></cols>'
		. '<sheetData>' . $rows_xml . '</sheetData>'
		. '</worksheet>';
}

/**
 * Every file that makes up the workbook.
 *
 * @param string $sheet_xml The one sheet, from blueworx_user_export_sheet_xml().
 * @return array Path inside the .xlsx => contents.
 */
function blueworx_user_export_workbook_files( $sheet_xml ) {
	$head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
	$main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$rels = 'http://schemas.openxmlformats.org/package/2006/relationships';
	$doc  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$type = 'application/vnd.openxmlformats-officedocument.spreadsheetml';

	return array(
		'[Content_Types].xml'        => $head
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="' . $type . '.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="' . $type . '.worksheet+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="' . $type . '.styles+xml"/>'
			. '</Types>',
		'_rels/.rels'                => $head
			. '<Relationships xmlns="' . $rels . '">'
			. '<Relationship Id="rId1" Type="' . $doc . '/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>',
		'xl/workbook.xml'            => $head
			. '<workbook xmlns="' . $main . '" xmlns:r="' . $doc . '">'
			. '<sheets><sheet name="Users" sheetId="1" r:id="rId1"/></sheets>'
			. '</workbook>',
		'xl/_rels/workbook.xml.rels' => $head
			. '<Relationships xmlns="' . $rels . '">'
			. '<Relationship Id="rId1" Type="' . $doc . '/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="' . $doc . '/styles" Target="styles.xml"/>'
			. '</Relationships>',
		// Two looks: plain, and bold for the heading row.
		'xl/styles.xml'              => $head
			. '<styleSheet xmlns="' . $main . '">'
			. '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
			. '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
			. '</styleSheet>',
		'xl/worksheets/sheet1.xml'   => $sheet_xml,
	);
}

/**
 * The downloaded file's name.
 *
 * @param string $site_name The site's name.
 * @param string $date      Today, as YYYY-MM-DD.
 * @return string e.g. "cafe-co-users-2026-11-09.xlsx".
 */
function blueworx_user_export_filename( $site_name, $date ) {
	$slug = sanitize_title( $site_name );

	return ( '' !== $slug ? $slug : 'site' ) . '-users-' . $date . '.xlsx';
}

/**
 * The profile fields that get a column each, in A to Z order.
 *
 * @return array Meta keys.
 */
function blueworx_user_export_meta_keys() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A one-off read for a download; WordPress has no function that lists every user meta key.
	$keys = (array) $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE meta_key IS NOT NULL" );
	$keep = array();

	foreach ( $keys as $key ) {
		if ( ! blueworx_user_export_skip_meta( $key, $wpdb->base_prefix ) ) {
			$keep[] = (string) $key;
		}
	}

	sort( $keep, SORT_NATURAL | SORT_FLAG_CASE );

	/**
	 * Filters which profile fields are exported, one column each.
	 *
	 * @param array $keep Meta keys, in column order.
	 */
	$keep = array_values( array_unique( array_map( 'strval', (array) apply_filters( 'blueworx_user_export_meta_keys', $keep ) ) ) );

	// The account's own details take the first nine columns.
	return array_slice( $keep, 0, BLUEWORX_USER_EXPORT_COLUMN_LIMIT - 9 );
}

/**
 * One user's row.
 *
 * @param WP_User $user      The account.
 * @param array   $meta_keys From blueworx_user_export_meta_keys().
 * @param array   $roles     Role slug => name.
 * @return array Cell values, left to right.
 */
function blueworx_user_export_user_cells( $user, $meta_keys, $roles ) {
	$meta  = (array) get_user_meta( $user->ID );
	$names = array();

	foreach ( (array) $user->roles as $role ) {
		$names[] = isset( $roles[ $role ] ) ? $roles[ $role ] : $role;
	}

	$cells = array(
		(int) $user->ID,
		(string) $user->user_login,
		(string) $user->user_email,
		blueworx_user_export_meta_text( isset( $meta['first_name'] ) ? $meta['first_name'] : array() ),
		blueworx_user_export_meta_text( isset( $meta['last_name'] ) ? $meta['last_name'] : array() ),
		(string) $user->display_name,
		implode( ', ', $names ),
		(string) $user->user_registered,
		(string) $user->user_url,
	);

	foreach ( $meta_keys as $key ) {
		$cells[] = isset( $meta[ $key ] ) ? blueworx_user_export_meta_text( $meta[ $key ] ) : '';
	}

	return $cells;
}

/**
 * Builds the spreadsheet in a temporary file.
 *
 * @return string|WP_Error The file's path, or why it could not be made.
 */
function blueworx_user_export_build() {
	if ( ! class_exists( 'PclZip' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
	}

	$meta_keys = blueworx_user_export_meta_keys();
	$roles     = array();

	foreach ( wp_roles()->role_names as $slug => $name ) {
		$roles[ $slug ] = translate_user_role( $name );
	}

	$headings = array_merge(
		array(
			__( 'ID', 'blueworx-labs-wordpress' ),
			__( 'Username', 'blueworx-labs-wordpress' ),
			__( 'Email', 'blueworx-labs-wordpress' ),
			__( 'First name', 'blueworx-labs-wordpress' ),
			__( 'Last name', 'blueworx-labs-wordpress' ),
			__( 'Display name', 'blueworx-labs-wordpress' ),
			__( 'Role', 'blueworx-labs-wordpress' ),
			__( 'Registered', 'blueworx-labs-wordpress' ),
			__( 'Website', 'blueworx-labs-wordpress' ),
		),
		$meta_keys
	);

	$rows = blueworx_user_export_row_xml( 1, $headings, true );
	$row  = 1;
	$page = 1;

	// A few hundred at a time, so a site with many thousands of users never
	// has them all in memory at once.
	do {
		$users = get_users(
			array(
				'number'      => 200,
				'paged'       => $page,
				'orderby'     => 'ID',
				'order'       => 'ASC',
				'fields'      => 'all_with_meta',
				'count_total' => false,
			)
		);

		foreach ( $users as $user ) {
			++$row;
			$rows .= blueworx_user_export_row_xml( $row, blueworx_user_export_user_cells( $user, $meta_keys, $roles ) );
			clean_user_cache( $user );
		}

		++$page;
		$found = count( $users );
	} while ( 200 === $found );

	$list = array();

	foreach ( blueworx_user_export_workbook_files( blueworx_user_export_sheet_xml( $rows ) ) as $name => $content ) {
		$list[] = array(
			PCLZIP_ATT_FILE_NAME    => $name,
			PCLZIP_ATT_FILE_CONTENT => $content,
		);
	}

	unset( $rows );

	$path = wp_tempnam( 'users.xlsx' );
	$zip  = new PclZip( $path );

	if ( 0 === $zip->create( $list ) ) {
		wp_delete_file( $path );
		return new WP_Error( 'blueworx_user_export_zip', $zip->errorInfo( true ) );
	}

	return $path;
}

/**
 * Sends the spreadsheet.
 *
 * A read, so a nonce in the link is enough; nothing on the site changes.
 *
 * @return void
 */
function blueworx_user_export_handle_download() {
	if ( ! blueworx_user_export_allowed() ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ), 403 );
	}

	check_admin_referer( 'blueworx_export_users' );

	wp_raise_memory_limit( 'admin' );

	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- A site with many thousands of users needs longer than a page load.
	}

	$file = blueworx_user_export_build();

	if ( is_wp_error( $file ) ) {
		wp_die( esc_html__( 'The spreadsheet could not be made just now. Try again, and if it keeps happening, tell BlueWorx.', 'blueworx-labs-wordpress' ), 500 );
	}

	// Anything another plugin or a displayed notice has already buffered would
	// otherwise land inside the file and spoil it, so it is thrown away first.
	// Output that has already gone out cannot be taken back: say so instead of
	// sending a broken file.
	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}

	if ( headers_sent() ) {
		wp_delete_file( $file );
		wp_die( esc_html__( 'The spreadsheet could not be sent because something else on the site has already written to the page. Try again, and if it keeps happening, tell BlueWorx.', 'blueworx-labs-wordpress' ), 500 );
	}

	$name = blueworx_user_export_filename( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ), wp_date( 'Y-m-d' ) );

	nocache_headers();
	header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	header( 'Content-Length: ' . filesize( $file ) );

	readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming the file just built.
	wp_delete_file( $file );
	exit;
}
add_action( 'admin_post_blueworx_export_users', 'blueworx_user_export_handle_download' );
