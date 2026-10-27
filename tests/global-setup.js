/**
 * Fails a local run early, and in English, when the harness is not running.
 *
 * The suite now defaults to the local harness, so the commonest way to get
 * nothing useful is to run it before starting one. Without this the first spec
 * dies on ECONNREFUSED partway through a navigation, which reads like a bug in
 * the test rather than a machine that has nothing listening on the port.
 *
 * Scoped to localhost on purpose. A staging or CI URL that does not answer is a
 * real failure and is left to report itself as one.
 *
 * Once the harness answers, the store-page fixtures are seeded (see below).
 */

import { spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { baseURL, isHarness } from './test-target.js';

const REPO = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const WP_ROOT = join(REPO, '.wp-test', 'wp');
const WP_LOAD = join(WP_ROOT, 'wp-load.php');
const MU_PLUGINS = join(WP_ROOT, 'wp-content', 'mu-plugins');
const TEST_HOOKS = join(MU_PLUGINS, 'blueworx-store-test-hooks.php');
const TEST_PLUGIN = join(WP_ROOT, 'wp-content', 'plugins', 'blueworx-test-plugin', 'blueworx-test-plugin.php');

/**
 * The mu-plugin the dashboard specs rely on: exercises the store pages' five
 * filters the way another plugin would.
 */
const TEST_HOOKS_PHP = `<?php
/**
 * Plugin Name: BlueWorx store pages test hooks
 * Description: Exercises the store pages and Source column filters the way another plugin would. Test fixture only.
 */

// Listens for the theme's ready signal the way a client plugin would, and
// prints what it heard, so tests/theme-skeleton.spec.js can read it off a page.
add_action( 'blueworx_theme_ready', static function ( $version ) {
	$GLOBALS['bw_test_ready'][] = array( $version, function_exists( 'blueworx_store_dashboard_screen' ) );
} );

add_action( 'wp_head', static function () {
	$heard = isset( $GLOBALS['bw_test_ready'] ) ? $GLOBALS['bw_test_ready'] : array();
	if ( ! $heard ) {
		echo '<!-- blueworx_theme_ready: never -->';
		return;
	}
	printf( '<!-- blueworx_theme_ready: %s, store loaded: %s, times: %d -->', esc_html( $heard[0][0] ), $heard[0][1] ? 'yes' : 'no', count( $heard ) );
} );

add_shortcode( 'bw_store_test_panel', static function () {
	return '<p id="test-panel">TEST PANEL</p>';
} );

add_filter( 'blueworx_store_views', static function ( $views ) {
	$views[] = array(
		'key'       => 'club',
		'label'     => 'Club',
		'title'     => 'Your club',
		'lede'      => 'A view another plugin added.',
		'icon'      => 'calendar',
		'where'     => 'both',
		'shortcode' => 'bw_store_test_panel',
	);
	return $views;
} );

add_filter( 'blueworx_store_panel', static function ( $html, $key ) {
	return 'dashboard' === $key ? '<p id="test-welcome">WELCOME</p>' . $html : $html;
}, 10, 2 );

add_filter( 'blueworx_store_context', static function ( $context ) {
	$context['site_name']  = 'Fixture Club';
	$context['home_label'] = 'Back to Fixture Club';
	return $context;
} );

add_filter( 'blueworx_store_checkout_links', static function ( $links ) {
	$links[] = array( 'label' => 'Fixture terms', 'href' => home_url( '/terms-fixture/' ) );
	return $links;
} );

// Names a page in the Pages list's Source column, the way a plugin that
// built one would.
add_filter( 'blueworx_page_source', static function ( $label, $post_id ) {
	$page = get_post( $post_id );
	return $page && 'claimed-fixture' === $page->post_name ? 'Fixture page' : $label;
}, 10, 2 );

// Claims the dashboard address only when the request asks it to, so the same
// site can prove both "dressed here" and "redirected there".
add_filter( 'blueworx_store_dashboard_url', static function ( $url ) {
	return isset( $_GET['bw_claim'] ) ? home_url( '/claimed-fixture/' ) : $url; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
} );

// Supplies a design preset and a font the way Clubhouse V2 supplies its skins,
// and reads the named styles back the way any plugin would, for
// tests/design-system.spec.js.
add_filter( 'blueworx_design_fonts', static function ( $fonts ) {
	$fonts['fixture-mono'] = array( 'label' => 'Fixture Mono', 'family' => 'Fixture Mono', 'stack' => '"Fixture Mono", monospace', 'files' => array() );
	return $fonts;
} );

add_filter( 'blueworx_design_presets', static function ( $presets ) {
	$presets['fixture'] = array(
		'label'       => 'Fixture preset',
		'description' => 'A preset another plugin added.',
		'settings'    => array( 'primary' => '#c6f24e', 'heading_font' => 'fixture-mono', 'corners' => 'square' ),
	);
	return $presets;
} );

add_action( 'wp_head', static function () {
	if ( function_exists( 'blueworx_design_tokens' ) ) {
		$tokens = blueworx_design_tokens();
		printf( '<!-- design tokens seen: %d, primary: %s -->', count( $tokens ), esc_html( $tokens['--color-primary'] ) );
	}
} );
`;

export default async function globalSetup() {
  if (!isHarness) {
    return;
  }

  try {
    // Any answer at all is enough — a redirect or even a 500 proves something is
    // listening. Only a connection failure means "no harness".
    await fetch(baseURL, { method: 'HEAD' });
  } catch {
    throw new Error(
      [
        `No WordPress is answering at ${baseURL}.`,
        '',
        'The suite defaults to the local harness, which serves this working tree.',
        'Start it with:',
        '',
        '  node ../bluegroup_core_foundation/scripts/wp-test-env.mjs up --theme . --theme-slug blueworx',
        '',
        'To test a deployed site instead, set PLAYWRIGHT_BASE_URL (plus WP_ADMIN_USER,',
        'WP_ADMIN_PASS and WP_LOGIN_PATH) in .env or on the command line.',
      ].join('\n')
    );
  }

  if (!existsSync(WP_LOAD)) {
    return;
  }

  seedStoreFixtures();
}

/**
 * Seeds the pages the store specs drive, by running PHP against the harness.
 *
 * Stand-ins for the pages SureCart seeds. CI has no SureCart; the stored id IS
 * the contract, so a page the option names is the honest fixture. Also a member
 * who is not an administrator, for the dashboard specs, and the test-hooks
 * mu-plugin those specs rely on.
 */
function seedStoreFixtures() {
  // A plugin that does nothing, switched on by the PHP below. The theme is no
  // longer a plugin, so without this the test site has none active, and the
  // specs that check what an account can do on the Plugins screen have nothing
  // to try it on.
  mkdirSync(dirname(TEST_PLUGIN), { recursive: true });
  writeFileSync(
    TEST_PLUGIN,
    [
      '<?php',
      '/**',
      ' * Plugin Name: BlueWorx test plugin',
      ' * Description: Does nothing. Gives the test site one active plugin. Test fixture only.',
      ' */',
      '',
    ].join('\n')
  );

  const php = join(tmpdir(), 'blueworx-store-global-setup.php');
  writeFileSync(
    php,
    `<?php
require_once ${JSON.stringify(WP_LOAD)};

function bw_fixture_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing instanceof WP_Post ) {
		wp_update_post( array( 'ID' => $existing->ID, 'post_status' => 'publish' ) );
		return $existing->ID;
	}
	return (int) wp_insert_post( array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $content,
	) );
}

// Stand-ins for the pages SureCart seeds. CI has no SureCart; the stored id IS
// the contract, so a page the option names is the honest fixture. The option
// names are SureCart's own (surecart_<page>_page_id, hyphen and all).
update_option( 'surecart_checkout_page_id', bw_fixture_page( 'checkout-fixture', 'Checkout fixture', '<p id="shop-content">SHOP CONTENT</p>' ) );
update_option( 'surecart_order-confirmation_page_id', bw_fixture_page( 'thanks-fixture', 'Thank you fixture', '<p id="shop-content">THANKS CONTENT</p>' ) );
update_option( 'surecart_dashboard_page_id', bw_fixture_page( 'dashboard-fixture', 'Dashboard fixture', '<p id="foreign-content">FOREIGN</p>' ) );
bw_fixture_page( 'claimed-fixture', 'Claimed dashboard fixture', '<p id="claimed-content">CLAIMED</p>' );

// A member who is not an administrator, for the dashboard specs.
if ( ! get_user_by( 'login', 'member' ) ) {
	wp_insert_user( array( 'user_login' => 'member', 'user_pass' => 'wptest-member-pw', 'user_email' => 'member@example.com', 'display_name' => 'Pat Member', 'role' => 'subscriber' ) );
}

// One active plugin for the Plugins screen specs, written just before this ran.
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( file_exists( WP_PLUGIN_DIR . '/blueworx-test-plugin/blueworx-test-plugin.php' ) ) {
	activate_plugin( 'blueworx-test-plugin/blueworx-test-plugin.php' );
}

echo (int) get_option( 'surecart_checkout_page_id' ) > 0 ? 'seeded' : 'failed';
`
  );
  const res = spawnSync('php', [php], { encoding: 'utf8' });
  rmSync(php, { force: true });

  if (res.status !== 0 || res.stdout.trim() !== 'seeded') {
    // Fail loudly. Continuing would produce a wall of store-spec failures whose
    // cause is this, not the plugin.
    throw new Error(
      `global-setup: could not seed the store fixture pages (exit ${res.status}). ` +
        `stdout=${res.stdout?.trim()} stderr=${res.stderr?.trim()}`
    );
  }

  // The hooks the dashboard specs register from inside WordPress. Written on
  // every run so a harness rebuilt from scratch still has it.
  mkdirSync(MU_PLUGINS, { recursive: true });
  writeFileSync(TEST_HOOKS, TEST_HOOKS_PHP);

  console.log('global-setup: store fixture pages seeded.');
}
