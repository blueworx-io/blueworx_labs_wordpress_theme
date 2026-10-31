<?php
/**
 * Loads the theme: updates, constants, every feature, and the signal client
 * plugins wait for.
 *
 * Required from functions.php once it has checked the 1.x plugin is not also
 * running. Theme code loads after plugins_loaded, so anything here that the
 * plugin used to hook there now runs on after_setup_theme instead.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Updates. The theme watches this repo's GitHub releases and installs new
 * versions itself, so nobody uploads a zip. The house standard this follows is
 * documented in the foundation's docs/wordpress-auto-updates.md; the decision
 * to install rather than merely offer lives in includes/auto-updates.php.
 *
 * At file scope, and not inside a function, conditional or hook: the library's
 * "use" import below cannot be wrapped without a parse error.
 *
 * The checker is pointed at functions.php in the theme's own folder: the
 * library tells a theme from a plugin by finding style.css beside the file it
 * is given, and only looks one folder deep.
 */
// The theme's folder name on this site. Normally "blueworx-labs-wordpress-theme", the folder inside
// the release zip, but read rather than assumed: everything that recognises
// this theme by name compares against this.
if ( ! defined( 'BLUEWORX_THEME_SLUG' ) ) {
	define( 'BLUEWORX_THEME_SLUG', basename( get_template_directory() ) );
}

require_once get_template_directory() . '/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$blueworx_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/blueworx-io/blueworx_labs_wordpress_theme/',
	get_template_directory() . '/functions.php',
	// The folder the theme is installed in. The library renames the release
	// zip's own folder to this when it installs an update, so an update lands
	// on top of the original rather than beside it as a second theme.
	BLUEWORX_THEME_SLUG
);

/*
 * This repo is public, so releases are readable without credentials and no site
 * needs a token. The guard is kept so that making the repo private later is a
 * line in each site's wp-config.php and no change here:
 *
 *     define( 'BLUEWORX_PLUGIN_UPDATE_TOKEN', 'github_pat_...' );
 */
if ( defined( 'BLUEWORX_PLUGIN_UPDATE_TOKEN' ) && BLUEWORX_PLUGIN_UPDATE_TOKEN ) {
	$blueworx_update_checker->setAuthentication( BLUEWORX_PLUGIN_UPDATE_TOKEN );
}

/*
 * Install the zip attached to the release, not GitHub's own source tarball.
 * That tarball unpacks to a folder named <repo>-<version>, which WordPress
 * would treat as a different theme — and it carries every development file in
 * the repo besides.
 */
$blueworx_update_checker->getVcsApi()->enableReleaseAssets();

// Kept under the plugin's name: client plugins such as Clubhouse check it to
// know the BlueWorx base is present and new enough.
if ( ! defined( 'BLUEWORX_LABS_VERSION' ) ) {
	define( 'BLUEWORX_LABS_VERSION', '2.3.1' );
}

// The template directory, never the stylesheet one, so a child theme still
// finds the parent's files.
if ( ! defined( 'BLUEWORX_LABS_PATH' ) ) {
	define( 'BLUEWORX_LABS_PATH', trailingslashit( get_template_directory() ) );
}

if ( ! defined( 'BLUEWORX_LABS_URL' ) ) {
	define( 'BLUEWORX_LABS_URL', trailingslashit( get_template_directory_uri() ) );
}

if ( ! defined( 'BLUEWORX_CUSTOM_LOGIN_SLUG' ) ) {
	define( 'BLUEWORX_CUSTOM_LOGIN_SLUG', 'admin_login' );
}

require_once BLUEWORX_LABS_PATH . 'includes/theme-urls.php';

// The shared admin design system's registrar. Required here, at load time
// rather than from inside admin-assets.php or any hook: registration has to run
// before any copy on the site enqueues, and the newest-wins comparison only
// works if every copy announces itself this early. See
// assets/blueworx-admin-design.php for why.
require_once BLUEWORX_LABS_PATH . 'assets/blueworx-admin-design.php';

require_once BLUEWORX_LABS_PATH . 'includes/helpers.php';
require_once BLUEWORX_LABS_PATH . 'includes/features.php';
require_once BLUEWORX_LABS_PATH . 'includes/design/color.php';
require_once BLUEWORX_LABS_PATH . 'includes/design/settings.php';
require_once BLUEWORX_LABS_PATH . 'includes/design/tokens.php';
require_once BLUEWORX_LABS_PATH . 'includes/design/output.php';
require_once BLUEWORX_LABS_PATH . 'includes/design/screen.php';
require_once BLUEWORX_LABS_PATH . 'includes/design/reference.php';
require_once BLUEWORX_LABS_PATH . 'includes/design/download.php';
require_once BLUEWORX_LABS_PATH . 'includes/upgrade.php';
require_once BLUEWORX_LABS_PATH . 'includes/theme-lifecycle.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-design.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-assets.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-theme.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-topbar-sites.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-menu-groups.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-menu-icons.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-menu-badges.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-menu-order.php';
require_once BLUEWORX_LABS_PATH . 'includes/login-security.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/sso.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/discovery.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/jwt.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/log.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/users.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/flow.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/ui.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/settings.php';
require_once BLUEWORX_LABS_PATH . 'includes/sso/logs-screen.php';
require_once BLUEWORX_LABS_PATH . 'includes/cache-refresh.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-settings.php';
require_once BLUEWORX_LABS_PATH . 'includes/guides.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-pages.php';
require_once BLUEWORX_LABS_PATH . 'includes/page-sources.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-guides.php';
require_once BLUEWORX_LABS_PATH . 'includes/disable-comments.php';
require_once BLUEWORX_LABS_PATH . 'includes/email-notifications.php';
require_once BLUEWORX_LABS_PATH . 'includes/page-excerpts.php';
require_once BLUEWORX_LABS_PATH . 'includes/translate.php';
require_once BLUEWORX_LABS_PATH . 'includes/profile-cleanup.php';
require_once BLUEWORX_LABS_PATH . 'includes/user-roles.php';
require_once BLUEWORX_LABS_PATH . 'includes/readonly-access.php';
require_once BLUEWORX_LABS_PATH . 'includes/support-access.php';
require_once BLUEWORX_LABS_PATH . 'includes/external-access.php';
require_once BLUEWORX_LABS_PATH . 'includes/mcp/oauth.php';
require_once BLUEWORX_LABS_PATH . 'includes/mcp/server.php';
require_once BLUEWORX_LABS_PATH . 'includes/mcp/forge.php';
require_once BLUEWORX_LABS_PATH . 'includes/mcp/surecart.php';
require_once BLUEWORX_LABS_PATH . 'includes/mcp/screen.php';
require_once BLUEWORX_LABS_PATH . 'includes/auto-updates.php';
require_once BLUEWORX_LABS_PATH . 'includes/admin-app-screens.php';

require_once BLUEWORX_LABS_PATH . 'includes/admin-bar.php';
require_once BLUEWORX_LABS_PATH . 'includes/dashboard-widgets.php';
require_once BLUEWORX_LABS_PATH . 'includes/security-hardening.php';
require_once BLUEWORX_LABS_PATH . 'includes/robots-txt.php';
require_once BLUEWORX_LABS_PATH . 'includes/tracking-codes.php';
require_once BLUEWORX_LABS_PATH . 'includes/media-tools.php';
require_once BLUEWORX_LABS_PATH . 'includes/content-tools.php';
require_once BLUEWORX_LABS_PATH . 'includes/revisions.php';
require_once BLUEWORX_LABS_PATH . 'includes/login-session.php';
require_once BLUEWORX_LABS_PATH . 'includes/login-redirect.php';
require_once BLUEWORX_LABS_PATH . 'includes/view-as-role.php';
require_once BLUEWORX_LABS_PATH . 'includes/display-names.php';
require_once BLUEWORX_LABS_PATH . 'includes/store/store.php';

/**
 * Lets WordPress work out who is signed in again, now the theme has loaded.
 *
 * On admin requests WordPress decides who the visitor is before any theme
 * loads, to pick the language for its own strings. A plugin is loaded by then;
 * a theme is not. So a request signed in only through this theme's own
 * determine_current_user filter, such as the support key header, was settled
 * as nobody, and the read-only block let it through as an anonymous visitor.
 *
 * Only an anonymous answer is thrown away, and only on a request carrying the
 * support key. Anything else — a visitor signed in by cookie, or a plugin that
 * deliberately set nobody, such as a second-factor check holding a session
 * back — is left exactly as it was.
 *
 * @return void
 */
function blueworx_theme_recheck_anonymous_user() {
	global $current_user;

	if ( empty( $_SERVER['HTTP_X_BLUEWORX_SUPPORT_KEY'] ) ) {
		return;
	}

	if ( $current_user instanceof WP_User && 0 === (int) $current_user->ID ) {
		$current_user = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Unset so WordPress asks again.
	}
}
blueworx_theme_recheck_anonymous_user();

/**
 * What every theme declares about itself.
 *
 * Matches the blank theme Clubhouse sites ran on before, so pages Clubhouse
 * draws keep their featured images, feed links and title tag.
 *
 * @return void
 */
function blueworx_theme_supports() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
}
add_action( 'after_setup_theme', 'blueworx_theme_supports' );

/**
 * Tells client plugins the BlueWorx base is loaded.
 *
 * Plugins load before the theme, so a plugin such as Clubhouse cannot call
 * theme functions at load time. It hooks this instead of plugins_loaded for
 * anything that needs them. Fires once, after every file above is loaded and
 * after this site's setup and migrations have run.
 *
 * @return void
 */
function blueworx_theme_signal_ready() {
	if ( did_action( 'blueworx_theme_ready' ) ) {
		return;
	}

	/**
	 * Fires once the BlueWorx theme has loaded.
	 *
	 * @param string $version The theme's version.
	 */
	do_action( 'blueworx_theme_ready', BLUEWORX_LABS_VERSION );
}
add_action( 'after_setup_theme', 'blueworx_theme_signal_ready', 20 );
