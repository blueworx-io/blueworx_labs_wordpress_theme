=== BlueWorx Enhance ===
Contributors:      blueworx
Tags:              login, security, custom login url, hardening, cache
Requires at least: 5.0
Tested up to:      6.9
Requires PHP:      8.0
Stable tag:        2.3.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

The BlueWorx base for every site: admin, security, sign-in, shop, guides and the Claude connection.

== Description ==

The BlueWorx Enhance theme is the base every BlueWorx site runs on. It used to be the BlueWorx Labs | WordPress Enhancements plugin, and does everything that plugin did:

* Replacing the default `/wp-login.php` login URL with a custom `/admin_login` URL.
* Blocking all direct access to `/wp-login.php` - visitors are redirected to the homepage.
* Blocking unauthenticated access to `/wp-admin` - visitors are redirected to the homepage.
* Working reliably on both Apache and Nginx servers, including Cloudways stacks, without relying on `.htaccess` rewrite rules.
* Refreshing Cloudways/Varnish cache when posts or pages are published, updated, restored, or deleted.
* Disabling comments, suppressing selected admin emails, and cleaning up the user profile screen.
* Offering visitors an on-page translation switcher that runs entirely in their own browser (Chrome and Edge 138+), shown as text, flags, or both.
* Listing user roles alphabetically and letting a user hold more than one role at a time.
* Choosing how long a login lasts, from 24 hours up to until the person signs out.
* Tidying the toolbar and the dashboard, and hiding the toolbar on the front of the site for chosen roles.
* Duplicating a page or post as a draft, complete with its categories and field values.
* Replacing a media file without its address changing, scaling oversized images on upload, and optionally allowing sanitised SVG uploads for chosen roles.
* Capping the number of saved revisions per item.
* Turning XML-RPC off, and optionally hiding usernames in author page addresses.
* Editing robots.txt from the admin, and letting an administrator view the admin as another role.
* Providing a **Guides** page of written help for every function above and for everyday WordPress tasks, which other plugins can add their own guides to.

The new login URL and cache refresh status are displayed in the **BlueWorx** admin menu.

== Installation ==

1. Upload the `blueworx-labs-wordpress-theme` folder to `/wp-content/themes/`, or upload the zip under **Appearance > Themes > Add New**.
2. Activate the theme under **Appearance > Themes**. On a network, enable it for the network first.
3. If the BlueWorx Labs | WordPress Enhancements plugin is active, deactivate it now, after the theme. On plugin 1.96.4 or later its settings, guest viewers and support key all carry across, and the plugin can then be deleted. Deactivating it before the theme is active removes guest viewers and any support key.
4. Visit **BlueWorx** to see your new login URL and active enhancements.
5. Bookmark your new login URL before logging out.

== Frequently Asked Questions ==

= What is my new login URL? =
After activating the theme, go to **BlueWorx > Enhancements**. Your new login URL will be displayed there.

= What happens if I visit wp-login.php or wp-admin directly? =
You will be redirected to the homepage with a 301 redirect.

= Does this work on Nginx? =
Yes. The theme uses pure PHP request interception and does not rely on `.htaccess` or server-level rewrite rules.

= Will password reset emails still work? =
Yes. All password reset, logout, and account confirmation flows use the custom URL automatically.

= How does the cache refresh work? =
When a page or post changes, the theme refreshes the edited content, homepage, and related listing pages. A manual **Refresh Cache Now** button is also available in **BlueWorx > Cache**.

= Where do I find help for a particular function? =
**BlueWorx > Guides**, split into tabs matching the sections on the Enhancements page. Only functions that are switched on appear, so what you see there is what your site actually does. It also covers everyday WordPress tasks - pages versus posts, scheduling, undoing a change, images, menus, users and updates.

= Can another plugin add its own guides? =
Yes. It adds them with the `blueworx_guides` filter, and its own tab with `blueworx_guide_tabs`. See `docs/guides-api.md`. Guide content from another plugin is filtered through `wp_kses_post`, so it cannot introduce script into the admin.

= How does the translation feature work? =
A floating language button is added to the front end. When a visitor picks a language, their own browser translates the page using Chrome's built-in on-device translator - there is no translation service, no API key and no cost. The first use of a language downloads a small model into the browser. In browsers without that API, such as Safari and Firefox, the button does not appear. Search engines are unaffected: they still see the original language only. Right-to-left languages such as Arabic are translated correctly, but the page layout itself stays left-to-right.

= How do I let BlueWorx (or Claude Code) look at my site? =
Go to **BlueWorx > Enhancements > BlueWorx support access**, generate a key and open the 24-hour window. The key gives read-only wp-admin and REST access and nothing else. The same panel has a **Copy Claude Code prompt** button that puts the key, the site URLs and the access rules on your clipboard as one block to paste into Claude Code. Copy it on the screen that generates the key - that is the only time the prompt contains the key itself. `CONNECT_CLAUDE_CODE.md` in the theme repository has the same prompt in full.

== Development Layout ==

The theme is split into focused files for easier updates:

* `functions.php` checks the 1.x plugin is not also running, then loads `includes/bootstrap.php`, which loads everything else.
* `index.php` is the plain page frame public pages fall back to.
* `includes/` contains admin, login, cache, comments, email, and helper functions.
* `assets/js/` contains admin screen scripts.

== Changelog ==

Changes from 2.0.0 onward are tracked in CHANGELOG.md. Versions 1.x were
released as the BlueWorx Labs | WordPress Enhancements plugin, and their history
is in the blueworx-io/blueworx_labs_wordpress repository.
