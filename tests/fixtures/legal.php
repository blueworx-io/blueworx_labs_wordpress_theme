<?php
/**
 * Drives the legal pages from the command line, for tests/legal-pages.spec.js.
 *
 * Usage: php tests/fixtures/legal.php <path to wp-load.php> <command>
 *
 *   seed     Creates whichever legal pages are missing, published, and prints their ids.
 *   reset    Deletes the pages the theme created and forgets them, so the next
 *            seed starts clean. WordPress's own privacy page is put back as it was.
 *
 * @package BlueWorxLabs
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.

define( 'WP_USE_THEMES', false );

$wp_load = isset( $argv[1] ) ? (string) $argv[1] : '';
$command = isset( $argv[2] ) ? (string) $argv[2] : '';

if ( '' === $wp_load || ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "Usage: php tests/fixtures/legal.php <wp-load.php> <seed|reset>\n" );
	exit( 1 );
}

require_once $wp_load;

switch ( $command ) {
	case 'seed':
		// Remember WordPress's own privacy page, so reset can put it back.
		if ( false === get_option( 'blueworx_test_privacy_before', false ) ) {
			update_option( 'blueworx_test_privacy_before', (int) get_option( 'wp_page_for_privacy_policy', 0 ), false );
		}
		$ids = blueworx_legal_ensure_pages( true );
		foreach ( $ids as $kind => $id ) {
			// Published for the test even if it was WordPress's own draft.
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			);
		}
		echo wp_json_encode( $ids ), "\n";
		break;

	case 'reset':
		$before = get_option( 'blueworx_test_privacy_before', false );
		foreach ( (array) get_option( 'blueworx_legal_pages', array() ) as $kind => $id ) {
			if ( 'privacy' === $kind && false !== $before && (int) $before === (int) $id ) {
				wp_update_post(
					array(
						'ID'          => (int) $id,
						'post_status' => 'draft',
					)
				);
				continue;
			}
			wp_delete_post( (int) $id, true );
		}
		delete_option( 'blueworx_legal_pages' );
		if ( false !== $before ) {
			update_option( 'wp_page_for_privacy_policy', (int) $before );
			delete_option( 'blueworx_test_privacy_before' );
		}
		echo "reset\n";
		break;

	default:
		fwrite( STDERR, "Unknown command: {$command}\n" );
		exit( 1 );
}
