<?php
/**
 * BlueWorx theme: the starting point WordPress loads.
 *
 * Everything the theme does lives in includes/. This file only decides whether
 * it may load at all, then hands over to includes/bootstrap.php.
 *
 * Kept this small on purpose. PHP declares a file's top-level functions as soon
 * as the file is read, before any line of it runs, so an early return cannot
 * stop a function in this same file clashing with one the 1.x plugin already
 * declared. A clash there is a fatal error on every page, so nothing the
 * plugin also declares may live in this file.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * The 1.x plugin (blueworx-labs-wordpress) is the same code under the same
 * function, option and role names. Plugins load before the theme, so if its
 * version constant is already set the plugin is running, and loading the theme
 * on top would declare everything twice. Stay out of the way and say why.
 */
if ( defined( 'BLUEWORX_LABS_VERSION' ) ) {
	if ( ! function_exists( 'blueworx_theme_plugin_conflict_notice' ) ) {
		/**
		 * Asks for the 1.x plugin to be switched off while the theme is active.
		 *
		 * Markup copied from blueworx_ds_notice() rather than calling it: that
		 * helper belongs to whichever copy of the code is running, and here
		 * that is the plugin's, at whatever version it happens to be.
		 *
		 * @return void
		 */
		function blueworx_theme_plugin_conflict_notice() {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			// Only 1.96.4 onwards leaves guests and the support key in place
			// when it is switched off under this theme.
			$keeps_accounts = version_compare( BLUEWORX_LABS_VERSION, '1.96.4', '>=' );

			printf(
				'<div class="bw-notice bw-notice--warning" role="status"><div class="bw-notice__body"><p class="bw-notice__title">%1$s</p><p class="bw-notice__text">%2$s</p></div></div>',
				esc_html__( 'Switch off the BlueWorx Labs plugin', 'blueworx-labs-wordpress' ),
				$keeps_accounts
					? esc_html__( 'The BlueWorx theme does everything the plugin does, so it stays switched off while the plugin is active. Deactivate the BlueWorx Labs | WordPress Enhancements plugin to use the theme. Your settings, guest viewers and support key carry across, and the plugin can then be deleted.', 'blueworx-labs-wordpress' )
					: esc_html__( 'The BlueWorx theme does everything the plugin does, so it stays switched off while the plugin is active. Update the BlueWorx Labs | WordPress Enhancements plugin to 1.96.4 or later, then deactivate it. Updating first keeps your guest viewers and support key; your settings carry across either way.', 'blueworx-labs-wordpress' )
			);
		}
	}
	add_action( 'admin_notices', 'blueworx_theme_plugin_conflict_notice' );
	add_action( 'network_admin_notices', 'blueworx_theme_plugin_conflict_notice' );
	return;
}

require_once get_template_directory() . '/includes/bootstrap.php';
