# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/); this project uses semantic
versioning.

Versions 1.x were the BlueWorx Labs | WordPress Enhancements plugin. Their
history is in the plugin's own repository,
[blueworx-io/blueworx_labs_wordpress](https://github.com/blueworx-io/blueworx_labs_wordpress/blob/main/CHANGELOG.md).

## [2.0.1] - 2026-10-25

### Changed
- **The theme is now called BlueWorx Enhance**, and its zip and folder are
  `blueworx-labs-wordpress-theme`. A site that installed 2.0.0 keeps its
  existing folder when it updates.

## [2.0.0] - 2026-10-24

### Changed
- **The Labs plugin is now a theme.** Everything the plugin does comes with
  it: the admin look, security and sign-in, support access, guest viewers,
  single sign-on, roles and View as, the shop and member area, guides,
  tracking codes, translation, cache and media tools, and the Claude
  connection. It updates itself from GitHub releases, like the plugin did.
- **Settings carry across.** A site moved from the plugin keeps its settings,
  roles and Claude connection. Activate the theme first, then switch the
  plugin off (1.96.4 or later) and its guest viewers and support key carry
  across too. The plugin can then be deleted.
- **Only administrators can change theme**, so a client can't switch the
  BlueWorx base off by accident. Other roles get the ability back if the site
  moves to another theme.
- **Support and guest viewer accounts are only powerful while the theme
  runs.** Anywhere it isn't running, such as after a theme change made from
  the Customizer or the theme being deleted, they can sign in and nothing
  more.
- **Switching to another theme removes the guest viewer and support
  accounts**, and ends any live support key, as deactivating the plugin did.

### Added
- **Client plugins can wait for the theme.** Plugins load before the theme, so
  plugins such as Clubhouse can now wait for the `blueworx_theme_ready` signal
  before using it.
- **Works as the default theme on a network.** Each new site sets itself up on
  its first visit.
- **The theme stays switched off next to the plugin.** While the 1.x plugin is
  active on the same site, the theme does nothing and shows a notice asking for
  the plugin to be switched off. The plugin keeps getting fixes on 1.x, and
  existing sites stay on it until they are moved across.

### Removed
- **Nothing is cleared out when the theme is deleted.** Themes get no
  uninstall step. Any support or guest accounts left behind can sign in and
  nothing more.
