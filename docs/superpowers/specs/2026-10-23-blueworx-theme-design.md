# BlueWorx theme (2.0): design

Issue: #223. Decided with Luke on 2026-10-22 and 2026-10-23.

## Why

Two plugins act as a theme today without being one. The Labs plugin (this repo) draws the shop, checkout, member area, login and the whole admin. The Clubhouse plugin takes over every public page: it draws the header, footer and menu, applies the branding, and sits on top of a blank theme (blankslate). Making the base a real theme gives every site one look and one engine, and makes the split clean: the theme is the platform, and a client plugin like Clubhouse adds that client's pages.

## How sites get built

1. A staff member adds a site. Most new client sites go on the clubhouse multisite (clubhouse.blueworx.io), on a temporary subdomain.
2. Staff connect Claude to it, and Claude fills in the pages, posts and settings.
3. The client reviews the site on the temporary address and signs off.
4. The site launches on the client's domain. About 9 in 10 sites stay on the multisite after launch.

The theme also runs on single sites that aren't on the multisite.

## Goals

- **One theme with everything Labs does today.** The admin re-skin, security and login hardening, support access, guest viewers, single sign-on, user roles and View as, the shop, checkout and member area, guides, tracking codes, translation, cache tools, media tools, and the Claude connection. That's every function in `includes/features.php`, working the same as on the plugin.
- **The public look moves in from Clubhouse.** Branding (colours, fonts, logo, favicon), the looks and their stylesheets, the header, footer and menu, and the code that turns saved page fields into a finished page. Every public page goes through the theme's own templates: club pages, posts, archives, search and 404.
- **The page editor moves in from Clubhouse.** It is the React editing screen vendored from bluegroup_core_foundation, with one GET and one POST route per screen. The theme owns the editor and its rendering. Plugins register the pages, sections and fields.
- **Claude can fill pages, not just posts.** The theme's Claude connection reads and writes page-editor fields for any registered screen. Today it only edits post content, so it can't fill club pages at all. This is the step-2 workflow above, so it's a requirement, not a nice-to-have.
- **Client plugins plug in.** Clubhouse registers its pages, collections and page fields with the theme. It also adds its guides, settings screens and shop panels through the hooks Labs already offers (`blueworx_guides`, `blueworx_store_*`, `blueworx_page_source` and the rest).
- **Pages without a registered editor screen keep the block editor**, drawn inside the theme's look. A site with only the theme edits its pages that way. Generic editor pages for theme-only sites can come later.
- **Works on a multisite.** The theme is enabled for the whole network, and each site keeps its own settings, branding and content.
- **Two kinds of Claude connection.** BlueWorx staff are super admins on the network. They connect once (#240) and can make quick changes on any site without connecting to each one. Each client site also has its own staff, who use that site's own connection, limited to their site, as today.
- **Updates itself** the way the plugin does, from GitHub releases, starting at version 2.0.0.

## Non-goals

- **Changing the plugin.** It stays on 1.x and gets fixes only. Existing sites stay on it until Luke moves them one at a time. Elementor sites stay on it for good, because they need Hello Elementor as their theme.
- **Migrating existing sites automatically.** That includes the one live Clubhouse site, which keeps the old plugin and the old Clubhouse.
- **Hosting.** Certificates for launched domains are a Cloudways problem: the wildcard certificate on clubhouse blocks per-domain certificates. That needs solving before the first launch, but outside this work.
- **New features in the first release**, beyond what's listed above. The other feature issues (#180, #183, #184, #185, #213) come after the port.

## Anti-goals (a failure even if it works)

- Club-specific content or wording ends up in the theme. The theme must make sense on a site with no Clubhouse.
- The theme breaks, or shows broken pages, when no client plugin is installed. A site with only the theme gets plain, working pages it can edit.
- A client can switch away from the theme. Remove `switch_themes` from the client role.
- Posts lose the block editor. Posts keep it, and are drawn inside the theme's look.

## Constraints

- **Load order.** Plugins load before the theme, so Clubhouse can't call theme functions at load time. The theme fires a "base is ready" action once it has set up. Client plugins wait for that action, and show an admin notice if the theme isn't active. Never use a `Requires Plugins` header (see the harness note in memory).
- **No activation hook.** Themes have no activation, deactivation or uninstall hooks. Anything the plugin does on activation, such as creating roles and running upgrades, must run on `after_switch_theme`, or on first load per site, so that new subsites on a network get set up too. The plugin's network fixes from 1.96.1 show what each site needs.
- **Early code.** The custom login path, Site Protection and support access run on `plugins_loaded` or `init` today. A theme's `functions.php` loads later than `plugins_loaded`, so each early hook has to be checked and moved. The migrations hook in `includes/upgrade.php` runs on `plugins_loaded` today.
- **Repo rules.** The CI guardrails, the approved dependencies list, Playwright tests for new behaviour, the design-system adherence hook (own classes are `blueworx-*`, never new `bw-*`), and versioning with a changelog all apply.

## Decided

- **A new repo**, starting from this repo's code. The plugin repo stays as the frozen 1.x line.
- **Theme versions start at 2.0.0.**
- **Clubhouse's next version needs the theme**, and doesn't support the old plugin.
- **The editor, rendering, branding and looks move from Clubhouse into the theme.** Clubhouse keeps the club pages, the collections and its club-specific settings.

## Left to the builder (marked as my calls where they matter)

- **Never alongside the plugin (my call).** The theme and the 1.x plugin use the same function, option and role names, so they never run together. If the plugin is active, the theme stays dormant and shows a notice asking for the plugin to be switched off. The theme keeps the plugin's option and role names, so when Luke moves a site across, its settings come with it.
- **Clubhouse's other parts (my call).** The parts that aren't about clubs move into the theme: SEO tags and the SEO report, the email sender name, and wrapping other plugins' pages in the header and footer. Demo mode and the AI import stay in Clubhouse. Clubhouse keeps its Owner and Content Editor roles. Every role except administrators and super admins loses `switch_themes`.
- **Switching away from the theme (my call).** On `switch_theme`, the theme removes guest viewer and support accounts and ends any live support key on that site, as the plugin does on deactivation. An accidental switch only costs those temporary accounts, and that's safer than leaving them live with nothing to keep them read-only.
- **Names (my call).** The repo is `blueworx_labs_wordpress_theme` and the theme folder is `blueworx`. The text domain stays `blueworx-labs-wordpress` for the port, so the move doesn't touch every string. Change any of these if Luke prefers others.
- **Storage.** How the theme names its options and page fields. Clubhouse's current names (`page_<section>_<field>` meta, `clubhouse_branding`, `clubhouse_menu` and so on) don't have to carry over, because no site migrates. Use theme-level names for anything the theme owns.
- **The registration API** for screens, sections and fields: its shape and names.
- **Order of work (my call).** First, port the plugin into a theme at full parity, with the suite passing. Then move the editor, rendering and look in from Clubhouse. Then add the Claude page tools and Clubhouse's next version. Finally, the network-wide Claude connection, and rollout on the clubhouse multisite.
- **Testing.** The foundation's test-site script installs a plugin (`--plugin`). It may need a theme mode. Check before planning the test work.
