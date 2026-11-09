<?php
/**
 * Export users: what goes in the spreadsheet, and what never does.
 *
 * The file has to open in Excel, hold whatever people typed into their
 * profiles without letting any of it run as a formula, and leave out anything
 * that signs someone in.
 *
 * Run with: php tests/php/user-export-test.php
 *
 * @package BlueWorxLabs
 */

// A command-line script WordPress never loads: its stubs have to carry core's
// own unprefixed names, and its working variables are never seen by a site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/stubs.php';

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Test stubs mirror core signatures.

function add_action( $hook, $callback, $priority = 10 ) {
	return true;
}

function maybe_unserialize( $data ) {
	if ( is_string( $data ) && preg_match( '/^(a|O|s|i|b|d):/', $data ) ) {
		$value = @unserialize( $data, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- The stub stands in for it.
		return false === $value && 'b:0;' !== $data ? $data : $value;
	}

	return $data;
}

function wp_check_invalid_utf8( $text, $strip = false ) {
	return mb_convert_encoding( (string) $text, 'UTF-8', 'UTF-8' );
}

function sanitize_title( $title ) {
	return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $title ) ), '-' );
}

// phpcs:enable Squiz.Commenting.FunctionComment.Missing

require dirname( __DIR__, 2 ) . '/includes/user-export.php';

// What is left out.
foreach ( array( 'session_tokens', '_application_passwords', 'wp_capabilities', 'wp_user_level', 'wp_2_capabilities', 'wp_user-settings', 'wp_persisted_preferences', 'wp__blueworx_view_as_role', 'closedpostboxes_page', 'metaboxhidden_dashboard', 'meta-box-order_post', 'manageuserscolumnshidden', 'edit_post_per_page', 'admin_color', 'my_plugin_api_key', 'two_factor_secret', 'backup_codes', 'reset_password_hash', 'first_name', '' ) as $skipped_key ) {
	check( "left out: '$skipped_key'", blueworx_user_export_skip_meta( $skipped_key, 'wp_' ), true );
}

// What goes in.
foreach ( array( 'nickname', 'description', 'billing_phone', 'sc_customer_ids', 'company', 'wp_custom_field' ) as $kept_key ) {
	check( "kept: '$kept_key'", blueworx_user_export_skip_meta( $kept_key, 'wp_' ), false );
}

check( 'another prefix: its own permissions are left out', blueworx_user_export_skip_meta( 'site_capabilities', 'site_' ), true );

// Values.
check( 'plain text stays as it is', blueworx_user_export_meta_text( array( 'Hello' ) ), 'Hello' );
check( 'a list comes out as JSON', blueworx_user_export_meta_text( array( serialize( array( 'live' => 'cus_1' ) ) ) ), '{"live":"cus_1"}' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- As the database holds it.
check( 'an empty list is an empty cell', blueworx_user_export_meta_text( array( serialize( array() ) ) ), '' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- As above.
check( 'several values share the cell', blueworx_user_export_meta_text( array( 'one', '', 'two' ) ), "one\ntwo" );
check( 'nothing stored is an empty cell', blueworx_user_export_meta_text( array() ), '' );

// Columns.
check( 'column 1', blueworx_user_export_column_name( 0 ), 'A' );
check( 'column 26', blueworx_user_export_column_name( 25 ), 'Z' );
check( 'column 27', blueworx_user_export_column_name( 26 ), 'AA' );
check( 'column 702', blueworx_user_export_column_name( 701 ), 'ZZ' );
check( 'the last column Excel has', blueworx_user_export_column_name( 16383 ), 'XFD' );

// Text.
check( 'markup is escaped', blueworx_user_export_escape( '<b>Tom & "Jo"</b>' ), '&lt;b&gt;Tom &amp; &quot;Jo&quot;&lt;/b&gt;' );
check( 'control characters are dropped', blueworx_user_export_escape( "a\x00\x08b\r\nc" ), "ab\nc" );
check( 'accents and emoji survive', blueworx_user_export_escape( 'Zoë 🎾' ), 'Zoë 🎾' );
check( 'a huge value is cut to what a cell holds', mb_strlen( blueworx_user_export_escape( str_repeat( 'é', 40000 ) ) ), 32767 );

// Rows.
$row = blueworx_user_export_row_xml( 2, array( 7, 'jo', '', '=HYPERLINK("https://evil.test")' ) );
check( 'the ID is a number', false !== strpos( $row, '<c r="A2"><v>7</v></c>' ), true );
check( 'text is stored as text', false !== strpos( $row, '<c r="B2" t="inlineStr"><is><t xml:space="preserve">jo</t></is></c>' ), true );
check( 'an empty cell is skipped', false === strpos( $row, 'r="C2"' ), true );
check( 'a formula is stored as text, never as a formula', false !== strpos( $row, '<c r="D2" t="inlineStr">' ) && false === strpos( $row, '<f>' ), true );
check( 'the heading row is bold', false !== strpos( blueworx_user_export_row_xml( 1, array( 'ID' ), true ), '<c r="A1" s="1" t="inlineStr">' ), true );

// The workbook.
$files = blueworx_user_export_workbook_files( blueworx_user_export_sheet_xml( blueworx_user_export_row_xml( 1, array( 'ID', 'Tom & Jo' ), true ) . $row ) );

check(
	'every part Excel needs is there',
	array_keys( $files ),
	array( '[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml' )
);

foreach ( $files as $part_name => $part ) {
	check( "$part_name is well-formed", false !== @simplexml_load_string( $part ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A failure is the result being checked.
}

$sheet = simplexml_load_string( $files['xl/worksheets/sheet1.xml'] );
$cells = $sheet->xpath( '//*[local-name()="row"][1]/*[local-name()="c"]' );
check( 'the sheet has both rows', count( $sheet->xpath( '//*[local-name()="row"]' ) ), 2 );
check( 'a heading reads back as typed', (string) $cells[1]->is->t, 'Tom & Jo' );
check( 'the heading row stays in view', (string) $sheet->xpath( '//*[local-name()="pane"]' )[0]['state'], 'frozen' );

// The file name.
check( 'named after the site and the day', blueworx_user_export_filename( 'Café & Co', '2026-11-09' ), 'caf-co-users-2026-11-09.xlsx' );
check( 'a site with no letters in its name', blueworx_user_export_filename( '!!!', '2026-11-09' ), 'site-users-2026-11-09.xlsx' );

finish();
