# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/); this project uses semantic
versioning.

Versions 1.x were the BlueWorx Labs | WordPress Enhancements plugin. Their
history is in the plugin's own repository,
[blueworx-io/blueworx_labs_wordpress](https://github.com/blueworx-io/blueworx_labs_wordpress/blob/main/CHANGELOG.md).

## [2.2.0] - 2026-10-28

### Added
- **See the whole design system on one page.** BlueWorx › Design › View
  design system shows every colour, font, size, button and block as the site
  draws them, with a readability score for every text pairing and the named
  styles ready to copy. Only people who can change the design can open it.

## [2.1.0] - 2026-10-27

### Added
- **Every site has its own design system.** Our team sets the colours, fonts,
  sizes, spacing, corners, buttons, logos and social links once on BlueWorx ›
  Design, and public pages, the editor and our plugins all use them. Shades
  are worked out from the brand colour so text stays readable, and any colour
  pair that is hard to read is flagged.
- **Plugins can offer ready-made looks and fonts** on the Design screen.
  Clubhouse V2's skins will arrive this way.
- **Site styles**, a new switch under Enhancements › Appearance, styles
  headings, text, buttons, forms, tables, FAQs and images from the design
  system. It is on for new sites. Sites that already had the theme keep their
  current look until it is switched on.

## [2.0.3] - 2026-10-27

### Changed
- **Checks on a change now run faster.** The theme uses the newest shared test
  setup, which keeps the test browser between runs and adds more test machines
  as the tests grow. Nothing changes on a site.

## [2.0.2] - 2026-10-26

### Fixed
- **Claude can now see when Forge work was added.** Each task shows the day it
  was added and last changed, and Claude can list work added before a date,
  oldest first, to find items that have sat too long.
- **Claude can make the client a Forge task's reviewer.** Before, it could only
  pick a staff member.
- **Claude now sees who did a Forge chore or reminder, and how long it took.**
  These showed no person and no hours, so audits couldn't count the time.

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
