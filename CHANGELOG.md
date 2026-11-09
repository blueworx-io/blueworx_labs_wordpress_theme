# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/); this project uses semantic
versioning.

Versions 1.x were the BlueWorx Labs | WordPress Enhancements plugin. Their
history is in the plugin's own repository,
[blueworx-io/blueworx_labs_wordpress](https://github.com/blueworx-io/blueworx_labs_wordpress/blob/main/CHANGELOG.md).

## [2.24.0] - 2026-11-09

### Added
- **A Theme menu.** A new Theme row sits directly below BlueWorx and holds
  the screens that shape the site itself.

### Changed
- **Design and Cache have moved to the Theme menu.** Design is now called
  Design System. Existing links and bookmarks to both screens still work,
  and the Cache switch stays on Enhancements.

## [2.23.2] - 2026-11-08

### Fixed
- **Every sign-in screen in the site's look.** The "check your email" stop
  after a password reset or a registration now has its own heading instead
  of "Welcome Back". Lost password, a bad reset link, registration (open or
  closed), signed out and check-your-email are all checked by a test, so
  none can fall back to the plain WordPress screen unnoticed.

## [2.23.1] - 2026-11-08

### Changed
- **The shared admin design system is up to date.** The theme's copy now
  matches the foundation's (v1.22.0), and the check that keeps it matching
  blocks a change again instead of only warning.

## [2.23.0] - 2026-11-08

### Changed
- **Klaviyo's site script waits for cookie consent.** With Cookie consent on,
  Klaviyo's script and the page's calls to it are held back until the
  visitor agrees to marketing cookies, start the moment they do with no
  reload, and stop, with Klaviyo's cookie cleared, if they withdraw.
  Sending from the site itself (forms, the store, website users) is not
  affected: that rests on the consent given on the form or at checkout.

## [2.22.0] - 2026-11-08

### Added
- **Cookie consent, through SureCookie.** A new Cookie consent switch under
  Enhancements, Analytics & Tracking. Switching it on installs the free
  SureCookie plugin from wordpress.org, switches it on and sets it up the
  house way: ask first, block tracking until the visitor agrees, keep a
  consent log, scanner off, banner linking to the cookies and privacy pages.
  SureCookie's own menu is taken away; a Cookie consent screen here holds
  the banner's heading and message, where it sits, and whether a small
  button lets visitors change their mind. If the install cannot happen, the
  screen says why and offers the manual route. Tag Manager and Analytics
  now wait for analytics consent and the Meta pixel for marketing consent,
  and each starts the moment the visitor agrees, with no reload. Switching
  it off deactivates SureCookie and the codes load as before.

## [2.21.0] - 2026-11-08

### Added
- **Legal pages and a proper not-found page.** Every site gets a privacy
  policy, a cookies page and terms and conditions: ordinary pages, on a new
  Legal page template with the date they last changed, with starter text
  that names the site. A new site gets them published, so its footer is
  complete from the first day; a site that already ran the theme gets them
  as drafts to read and publish. The cookies page lists the cookies the site
  actually sets, including the tracking codes and Klaviyo when they are on,
  and plugins can add theirs. A missing address now gets a page that says
  so, with a search box and the way home.

## [2.20.0] - 2026-11-08

### Changed
- **"View as" now changes the role other plugins see.** Previewing a role
  used to change only what you were allowed to do, so shop, membership and
  club plugins that check the role by name still showed you the admin
  version. Now they show what that member sees. Your account is never
  changed: saving your profile, or a plugin updating you mid-preview, cannot
  leave you on the previewed role, and ending the preview, or just closing
  the tab, leaves you exactly as you were. Website users mid-preview are not
  sent to Klaviyo as members.

## [2.19.0] - 2026-11-08

### Added
- **Blog and post pages.** With Site styles on, the theme draws the list of
  posts, category, tag, date and author pages, search results and single
  posts: cards with the picture, date and excerpt, a by-line with the date,
  author and categories, tags, previous and next links, and page numbers
  under a long list. Headings drop WordPress's "Category:" prefix, and an
  empty search says so. All inside the header and footer, in the site's
  design.

## [2.18.0] - 2026-11-08

### Added
- **A Plugin check under the BlueWorx menu.** It lists every installed
  plugin and marks each one Approved or Not approved against the BlueWorx
  approved list, which the site fetches once a day. While any plugin is not
  on the list, a notice on every admin screen links to the check. Nothing is
  ever deactivated or removed. Clubhouse V1 is not on the list, so it is
  flagged on a theme site.

## [2.17.1] - 2026-11-08

### Fixed
- **Saving Enhancements clears the cache.** Saving the Enhancements screen,
  including the feature switches, Site styles and the translation widget
  settings, now clears the site's page cache straight away, the way the
  Design and Tracking codes screens already did. Visitors see the change as
  soon as it is saved, and the Cache screen shows "Enhancements" as the last
  refresh.

## [2.17.0] - 2026-11-08

### Added
- **A header and footer on every public page.** With Site styles on, every
  page the theme draws gets the same header, with the site's logo or name,
  the header menu and a menu button on phones, and the same footer, with the
  footer menu, the social links from the Design screen, the privacy policy
  and the copyright line. Set the menus under Appearance, Menus; until then
  the site's pages stand in. Both take their colours, fonts and spacing from
  the site's design system. A plugin that draws its own header and footer
  turns the theme's off with the blueworx_site_frame filter, and adds a
  button to the header with blueworx_header_actions. Sites that had the
  theme before, with Site styles off, see no change.

## [2.16.0] - 2026-11-08

### Added
- **A product feed for Klaviyo.** The Klaviyo screen shows a feed address,
  with a secret in it, that lists every published SureCart product the way
  Klaviyo's catalogue import expects: name, link, image, description, price,
  currency and stock. Paste it into Klaviyo under Content, Products, and
  emails can show real products with live prices and recommend them. Built
  in the background, rebuilt every hour and when a product is saved, so a
  fetch never slows the store. "Make a new address" stops the old one.

## [2.15.0] - 2026-11-08

### Added
- **Any page can send a Klaviyo event when viewed.** A Klaviyo panel in the
  editor's sidebar, on every page, post and public post type: tick the box,
  name the event, and every view by a visitor Klaviyo knows reaches their
  profile as that event, with the page's title and address. For thank-you,
  landing and pricing pages. Sent by the site script, so administrators are
  never counted and the same consent rules apply.

## [2.14.0] - 2026-11-08

### Added
- **An email when Klaviyo sending goes wrong.** A send that fails for good
  is emailed to the site's admin email, or an address set on the Klaviyo
  screen, with the reason and a link to the log: one email an hour at most,
  listing everything that failed in it. A private key Klaviyo refuses is
  emailed once, and not again until a key has worked. The Klaviyo screen now
  shows when the last send went through.

## [2.13.0] - 2026-11-08

### Added
- **Website accounts reach Klaviyo.** Everyone with an account is kept up to
  date in Klaviyo as they register, sign in or change role: name, site role,
  join date and last sign-in, plus a Created Account event for a new
  account, so a welcome flow can run for people who never buy anything.
  Administrators and support roles are never sent, and nobody is added to a
  list. A button on the Klaviyo screen sends every existing account once, in
  small batches, for a site switching Klaviyo on with users already in place.

## [2.12.0] - 2026-11-08

### Added
- **Abandoned checkouts reach Klaviyo.** On a SureCart site, the moment a
  customer at the checkout has given an email, Klaviyo gets Started Checkout
  with the items, the total and a link back to the checkout, so its
  abandoned-cart emails can run. A changed cart sends it again with the new
  contents; the same cart saved again is sent once. Test checkouts stay out
  until the store box asks for them.

## [2.11.0] - 2026-11-08

### Added
- **The store sends to Klaviyo.** On a SureCart site, add the webhook address
  shown on BlueWorx, Klaviyo to the SureCart dashboard with its signing
  secret, and every order and subscription reaches Klaviyo: Placed Order,
  Started Subscription, Subscription Renewed, Payment Failed, Subscription
  Cancelled, Plan Changed and Refunded, each with the product, price, dates
  and IDs in the store's own currency. Customers who tick the marketing box at
  checkout join the Members list; unticked never does. Each event reaches
  Klaviyo once however many times SureCart retries, and a wrong secret refuses
  every delivery and says so on the screen.

## [2.10.0] - 2026-11-08

### Added
- **Any form can feed Klaviyo.** On BlueWorx, Klaviyo, add a form connection:
  which form (an Elementor form by name, a SureForms form by ID, or any HTML
  form by the id on its tag), which Klaviyo list, the consent wording shown
  beside the button, and a short source tag. Each sign-up is identified by
  email, recorded as Submitted Form, and subscribed to the list when they
  agreed. A form with a marketing tick box respects it: unticked is never
  subscribed. Fields are found by their labels, so a form can change without
  breaking the connection.

## [2.9.0] - 2026-11-08

### Added
- **Klaviyo sees your visitors.** With the public API key saved, Klaviyo's
  own script is on every public page, so Klaviyo knows who is active on the
  site and its sign-up forms and pop-ups work. People who are signed in are
  identified by email, and a SureCart product page sends Viewed Product with
  the product's name, price and picture. Administrators are never tracked.
  Until cookie consent arrives (#4), the script loads for every visitor, the
  same as the tracking codes.

## [2.8.0] - 2026-11-08

### Added
- **Klaviyo connection.** Connect a site to its Klaviyo account from BlueWorx,
  Klaviyo: paste the account's keys, press Save and test connection, and the
  screen names the lists it can see. Everything sent to Klaviyo goes through a
  queue, so a form or a checkout never waits for Klaviyo, and a Klaviyo that is
  down is retried for six hours. A log keeps the last 100 sends. Nothing sends
  yet on its own: visitor tracking, forms and the store each arrive in their
  own update, and a plugin built for the site can send through the connection
  now (see docs/klaviyo-api.md). Off by default under Enhancements, Analytics
  & Tracking.

## [2.7.0] - 2026-11-07

### Added
- **Locked pages.** An administrator can lock a page from the editor's
  sidebar. Clients can then change its words and images but not add, move or
  remove blocks, in the editor or through a connected tool, and only an
  administrator can unlock it. Locked pages are marked in the Pages list. On
  by default under Enhancements, Content.

## [2.6.0] - 2026-11-07

### Added
- **Pages built from standard blocks use the site's design.** Buttons,
  columns, bands with a background, covers, quotes, separators, tables and
  media-and-text blocks all take the site's colours, fonts, spacing and
  corners, and look the same in the editor as on the live page. Wide and full
  width are offered when editing. Needs Site styles on; sites that had the
  theme before 2.1.0 keep it off until you choose, so nothing changes on
  update.

### Fixed
- **Buttons take the site's main colour.** WordPress's own dark grey button
  colour was winning over the site's; it now follows the design, in the
  editor and on the page.

## [2.5.0] - 2026-11-02

### Changed
- **Merge users is quicker to use.** Results show as you type, with no page
  reload, and the search icon sits inside the box. Picking an account shows
  both side by side, and you choose which one to keep from there.

## [2.4.0] - 2026-11-01

### Added
- **Merge users.** Turn two accounts for the same person into one from the
  Users list. Profile details, posts, comments, roles, SureCart purchases and
  LearnDash courses move to the account you keep, and the other is deleted.
  Off until you switch it on under Enhancements. Nobody is emailed.

## [2.3.1] - 2026-10-31

### Fixed
- **The login page no longer shows errors before you sign in.** "The username
  field is empty" appeared as soon as the page opened.
- **Edit Menu is readable again.** Each menu item sits on one line with its
  arrows, instead of being spread down the card.

## [2.3.0] - 2026-10-28

### Added
- **Download the design system.** One button, on the Design screen and the
  design system page, gives a zip of the site's colours, fonts, logos and
  named styles, with a short read-me, ready for designers and other tools.

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
