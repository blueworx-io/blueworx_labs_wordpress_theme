# BlueWorx theme, stage 1: the plugin as a theme

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the Labs plugin's code as an installable, self-updating WordPress theme, version 2.0.0, with every current function working and today's Clubhouse running on top unchanged.

**Architecture:** A new repo starts from this repo at v1.96.3. The plugin bootstrap becomes `functions.php`, a blank `index.php` gives the public pages a minimal frame (Clubhouse takes them over anyway), and every place the code assumes it is a plugin changes to its theme equivalent. Stages 2–4 (moving the editor and look in from Clubhouse, Claude's page tools and the next Clubhouse, and the network-wide connection) each get their own plan once this lands.

**Tech Stack:** PHP 8.x WordPress theme (classic, no block templates), plugin-update-checker v5 (theme mode), Playwright, the PHP check scripts in `tests/php`, and the foundation's `wp-test-env.mjs` and shared GitHub workflows.

**Spec:** `docs/superpowers/specs/2026-10-23-blueworx-theme-design.md`

## Global Constraints

- New repo: `blueworx-io/blueworx_labs_wordpress_theme`. Theme folder and slug: `blueworx`. Text domain stays `blueworx-labs-wordpress`.
- Theme version starts at `2.0.0`. `BLUEWORX_LABS_VERSION` is defined as `'2.0.0'`, so Clubhouse's `>= 1.88.0` check still passes.
- Function, option, role, route and hook names stay exactly as in the plugin, so a site moved from the plugin keeps its settings.
- The theme never runs alongside the 1.x plugin.
- Never a `Requires Plugins` header, nor any hard dependency on another plugin.
- CI guardrails apply: lint, build, version bump plus changelog on every PR, `approved-deps.json` for any new dependency, a Playwright or PHP check for every behaviour change.
- The adherence hook applies: own classes are `blueworx-<feature>__*`, never new `bw-*`, no inline `<svg>`. Edit with the Edit tool or Node, never PowerShell.
- Paths resolve with `get_template_directory()` and `get_template_directory_uri()`, never the stylesheet versions, so a child theme still finds the parent's files.

## Review Focus

1. **The theme is switched on while the 1.x plugin is still active.** It stays dormant with a notice and no fatal error. See Task 3.
2. **A new subsite is created on a network where the theme is already the default.** Its roles, options and migrations are set up on its first load. See Task 4.
3. **A child theme is active with `blueworx` as its parent.** Assets and includes still load from the parent. See Task 2.
4. **Requests that never draw a page:** the custom login path, `wp-login.php` being blocked, XML-RPC being refused, REST calls signed with an application password, and cron. These must still behave the same now that the code loads after `plugins_loaded`. See Task 7.
5. **The site switches to another theme while a support key is live.** The key ends and the guest and support accounts are removed. See Task 4.

---

### Task 1: Theme support in the foundation (repo `bluegroup_core_foundation`)

**Files (foundation repo):**
- Modify: `scripts/wp-test-env.mjs` to add `--theme <dir>`
- Modify: `.github/workflows/ci-wordpress.yml` to add a `theme_slug` input
- Modify: `.github/workflows/release-wordpress.yml` to add a `theme_slug` input
- Modify: `assets/blueworx-admin-design.php` (the shared registrar), around line 104

**Interfaces:**
- Produces: `node wp-test-env.mjs up --theme . [--theme-slug blueworx] [--port N]`. It links the directory into `wp-content/themes/<slug>`, where the slug is passed as `--theme-slug` and defaults to the directory's name, so this repo passes `blueworx`. It then runs `switch_theme( '<slug>' )`. It takes `--plugin` and `--theme` together, so a plugin like Clubhouse can sit on top.
- Produces: workflow inputs `theme_slug: blueworx`, an alternative to `plugin_slug`. Exactly one of the two is required. With `theme_slug`, the version is read from the `Version:` line of `style.css`, the test site uses `--theme`, and the release zip's top folder is `<theme_slug>/`.
- Produces: the registrar returns a URL under `get_template_directory_uri()` when the winning copy's file sits inside `get_template_directory()`, and `plugin_dir_url()` otherwise.

- [ ] **Step 1:** Write the failing tests in the foundation's existing test style for each script or workflow change. Cover: a theme fixture linked and activated, the version read from `style.css`, and the zip's folder named after `theme_slug`.
- [ ] **Step 2:** Run them and confirm they fail.
- [ ] **Step 3:** Implement the three changes. Keep plugin mode byte-for-byte unchanged.
- [ ] **Step 4:** Run the foundation's tests and confirm they pass. Also confirm `up --plugin .` on this repo still provisions as before.
- [ ] **Step 5:** Commit, open a PR in the foundation, and after merge cut a new tag (next minor after the current `ci-wordpress` and `release-wordpress` tags). Record the tag. Task 7 pins it.

### Task 2: New repo with the theme skeleton

**Files (new repo `blueworx_labs_wordpress_theme`, created from this repo's `main` at `v1.96.3` with history kept):**
- Rename: `blueworx-labs-wordpress.php` to `functions.php`
- Create: `style.css` (header only), `index.php`
- Delete: `uninstall.php`
- Modify: `scripts/build-zip.mjs`, `package.json`, `readme.txt`, `CHANGELOG.md`
- Test: `tests/theme-skeleton.spec.js`

**Interfaces:**
- Produces: the constants `BLUEWORX_LABS_PATH`, which is `trailingslashit( get_template_directory() )`, and `BLUEWORX_LABS_URL`, which is `trailingslashit( get_template_directory_uri() )`.
- Produces: `BLUEWORX_LABS_THEME_FILE`, the path to `functions.php`. It replaces `BLUEWORX_LABS_PLUGIN_FILE`, and every use of the old name is removed.

- [x] **Step 0:** Done 2026-10-23. Luke created `blueworx-io/blueworx_labs_wordpress_theme`, and `main` was pushed from the plugin at `v1.96.3` with history and without tags.
- [ ] **Step 1: Write the failing spec.** In `tests/theme-skeleton.spec.js`:
  - `the theme is active and the console loads`: `/wp-admin/themes.php` shows "BlueWorx" as the active theme, and `/wp-admin/admin.php?page=blueworx-labs-wordpress` returns 200 with the Enhancements heading.
  - `assets load from the theme folder`: the admin theme stylesheet link's `href` contains `/wp-content/themes/blueworx/assets/css/admin-theme.css`.
  - `a child theme still loads the parent's files`: using a fixture `tests/fixtures/child-theme.php create|delete`, which writes and activates `wp-content/themes/blueworx-child` with `Template: blueworx`, the same stylesheet `href` still points at `/themes/blueworx/`.
  - `the public home page has a frame`: `/` returns 200, its `<body>` has the class `home`, and its HTML contains `/wp-includes/`, which proves `wp_head()` and `wp_footer()` ran.
- [ ] **Step 2:** Provision with `wp-test-env.mjs up --theme . --theme-slug blueworx` and run the spec. Expect it to fail, because no `style.css` exists yet.
- [ ] **Step 3: Implement.**
  - `style.css` header: `Theme Name: BlueWorx`, `Version: 2.0.0`, `Text Domain: blueworx-labs-wordpress`, `Requires at least` and `Requires PHP` copied from the plugin header.
  - `functions.php` is the old main file, using the constants above and with the plugin header removed.
  - `index.php` is a blank frame: doctype, `language_attributes()`, `wp_head()`, `body_class()`, `wp_body_open()`, the loop with `the_content()`, and `wp_footer()`.
  - Add `add_theme_support( 'title-tag' )`.
  - In `build-zip.mjs`, set `SLUG = 'blueworx'` and `REQUIRED = ['functions.php', 'style.css', 'index.php', 'readme.txt', 'includes', 'assets', 'plugin-update-checker']`.
  - Set `package.json` `"name": "blueworx"`, `"version": "2.0.0"`.
  - Start a new `CHANGELOG.md` at `## [2.0.0]`, pointing to the plugin repo for 1.x history.
- [ ] **Step 4:** Run the spec and confirm it passes. Run `npm run build`, then `unzip -l dist/blueworx.zip` shows every entry under `blueworx/`.
- [ ] **Step 5:** Commit.

### Task 3: Stay dormant next to the 1.x plugin

**Files:**
- Modify: `functions.php` (top, before any `require_once`)
- Create: `tests/fixtures/fake-labs-plugin.php`, a CLI fixture that writes or removes an mu-plugin defining `BLUEWORX_LABS_VERSION` as `'1.96.3'`
- Test: `tests/plugin-conflict.spec.js`

**Interfaces:**
- Produces: `blueworx_theme_plugin_conflict_notice(): void`, the admin notice. It uses `blueworx_ds_notice()` style markup copied inline, because the design-system helpers are not loaded in this state.

- [ ] **Step 1: Write the failing spec.** With the fixture active:
  - `with the plugin active the theme stays out of the way`: `/wp-admin/` returns 200 and shows a notice containing "Switch off the BlueWorx Labs plugin".
  - `the theme's console is not registered`: `admin.php?page=blueworx-labs-wordpress` does not show the theme's version.
  - `afterAll` removes the fixture.
- [ ] **Step 2:** Run it and confirm it fails, either with a fatal "cannot redeclare" or a missing notice.
- [ ] **Step 3: Implement.** If `BLUEWORX_LABS_VERSION` is already defined when `functions.php` starts, it means the plugin loaded first. Register the notice on `admin_notices` and `return` before defining anything else.
- [ ] **Step 4:** Run it and confirm it passes.
- [ ] **Step 5:** Commit.

### Task 4: Set up, upgrade and clean up without plugin hooks

**Files:**
- Create: `includes/theme-lifecycle.php`, required from `functions.php` right after `includes/upgrade.php`
- Modify: `functions.php` to remove `register_activation_hook` and `register_deactivation_hook`
- Modify: `includes/upgrade.php:721` to change `plugins_loaded` to `after_setup_theme`, priority 5
- Test: `tests/php/theme-lifecycle-test.php`, `tests/theme-lifecycle.spec.js`

**Interfaces:**
- Consumes: `blueworx_labs_on_activate()` and `blueworx_labs_deactivate_site()`, unchanged from the plugin.
- Produces:
  - `blueworx_theme_maybe_setup_site(): void` on `after_setup_theme` priority 6. It runs `blueworx_labs_on_activate()` once per site per theme version, tracked in the option `blueworx_theme_setup_version`.
  - `blueworx_theme_on_switch_away( string $new_name, WP_Theme $new_theme, WP_Theme $old_theme ): void` on `switch_theme`. It calls `blueworx_labs_deactivate_site()` when `$old_theme->get_template()` is `'blueworx'`.
  - `blueworx_theme_remove_switch_themes(): void`, called from setup. It removes `switch_themes` from every role except `administrator`.

- [ ] **Step 1: Write the failing PHP check** `theme-lifecycle-test.php`. Stub the core functions in the style of the other `tests/php` scripts, then check:
  - Setup runs on first load, and the option equals `'2.0.0'`.
  - Setup does not run a second time at the same version.
  - Setup runs again when the version changes.
  - Switching away from `blueworx` calls the clean-up once.
  - Switching between two other themes calls nothing.
  - After setup, no non-admin role has `switch_themes`.
- [ ] **Step 2: Write the failing spec** `theme-lifecycle.spec.js`:
  - `switching theme away ends support access`: generate a support key on the Support screen, switch to `twentytwentyfive` with a fixture, and expect the `blueworx_support` user to be gone and the key option empty. `afterAll` switches back to `blueworx`.
  - `a fresh site is set up on first load`: delete `blueworx_theme_setup_version` with a fixture, load `/wp-admin/`, and expect the option to equal `'2.0.0'`.
- [ ] **Step 3:** Run both and confirm they fail.
- [ ] **Step 4:** Implement `includes/theme-lifecycle.php` and the two edits.
- [ ] **Step 5:** Run both and confirm they pass. Also run `tests/php/multisite-accounts-test.php`, which must still pass.
- [ ] **Step 6:** Commit.

### Task 5: Self-references and updates

**Files:**
- Modify: `functions.php` (update checker), `includes/auto-updates.php`, `includes/helpers.php`, `includes/login-security.php:311-316`, `includes/mcp/oauth.php:298-305`
- Test: `tests/php/auto-updates-test.php`, `tests/php/login-security-multisite-test.php`, `tests/php/mcp-oauth-test.php` (update their stubs)

**Interfaces:**
- Produces: `blueworx_theme_active_here(): bool` in `includes/helpers.php`. It returns true when `get_option( 'template' )` is `'blueworx'`, and it is used inside `switch_to_blog()`. It replaces both "is the plugin active on this site" checks.
- Produces: `blueworx_force_own_auto_update( $update, $item )` on `auto_update_theme`, matching `'blueworx' === $item->theme`.

- [ ] **Step 1:** Change the three test scripts' stubs from `active_plugins` and `active_sitewide_plugins` to `get_option( 'template' )`. Add two checks: the theme on `'blueworx'` counts as running, and the theme on `'twentytwentyfive'` doesn't. In `auto-updates-test.php`, add: the `blueworx` theme item is forced on, and other themes are left alone.
- [ ] **Step 2:** Run all three and confirm they fail.
- [ ] **Step 3: Implement.**
  - The update checker: `PucFactory::buildUpdateChecker( 'https://github.com/blueworx-io/blueworx_labs_wordpress_theme/', get_template_directory() . '/functions.php', 'blueworx' )`, with the same token and release-asset settings as today.
  - The helper, plus the two call sites.
  - Swap the auto-update filter.
- [ ] **Step 4:** Run all `tests/php/*-test.php` and confirm every one passes.
- [ ] **Step 5:** Commit.

### Task 6: The "base is ready" signal for client plugins

**Files:**
- Modify: `functions.php` (end)
- Test: `tests/php/theme-ready-test.php`, plus the mu-plugin written by `tests/global-setup.js`

**Interfaces:**
- Produces: `do_action( 'blueworx_theme_ready', BLUEWORX_LABS_VERSION )`, fired once on `after_setup_theme` priority 20, after every include has loaded. Client plugins hook it in place of `plugins_loaded` for anything that calls theme functions.

- [ ] **Step 1: Write the failing checks.** In the PHP check, the action fires once with `'2.0.0'`, and a listener sees `function_exists( 'blueworx_store_dashboard_screen' )` as true. Extend the global-setup mu-plugin so it records whether the action fired, then add a spec assertion that a page load reports it.
- [ ] **Step 2:** Run them and confirm they fail.
- [ ] **Step 3:** Implement the action.
- [ ] **Step 4:** Run them and confirm they pass.
- [ ] **Step 5:** Commit.

### Task 7: Test suite and CI on the theme

**Files:**
- Modify: `.github/workflows/ci.yml` and `.github/workflows/release.yml` to pin the Task 1 foundation tag and use `theme_slug: blueworx`
- Modify: `tests/global-setup.js` (provisioning hint `--theme .`), `tests/design-system-migration.spec.js:63` (path becomes `/wp-content/themes/blueworx/...`), `tests/admin-theme.spec.js:1217` (the plugins-list row check becomes the themes screen's active-theme check), `tests/helpers.js` if it names the plugin path
- Test: the full suite

- [ ] **Step 1:** Make the edits above. Search `tests/` for `plugins/blueworx-labs-wordpress` and `data-plugin=` and fix every hit.
- [ ] **Step 2:** Reset the test site's feature options as described in the `full-suite-baseline` memory, then run the full suite with one worker. Expect the same pass count as the plugin's last full run, about 376. The only allowed failures are the six known update-notice ones, unless the test site's WordPress has been updated.
- [ ] **Step 3: Review Focus item 4.** Confirm these pass unchanged: `login-redirect.spec.js`, `login-session.spec.js`, `php-checks.spec.js` (`xmlrpc` and `login-redirect`), `application-passwords.spec.js`, and `support-access.spec.js`. If any fail because a hook now runs too late, move that hook onto `after_setup_theme` or `init`, never onto `plugins_loaded`.
- [ ] **Step 4:** Push the branch and open a PR. CI must go green on the foundation's theme workflow.
- [ ] **Step 5:** Commit any fixes and record the new baseline in the PR description.

### Task 8: Clubhouse on the theme, then release 2.0.0

**Files:**
- No code in Clubhouse. If something there needs to change, it goes in the stage 3 plan, unless it blocks this check.
- Modify: `CHANGELOG.md`, `readme.txt`

- [ ] **Step 1:** Provision the test site with `--theme .` plus `--plugin ../blueworx_labs_clubhouse`. Run Clubhouse's own smoke tests against it. Confirm the store pages, the Guides tab and the club pages all render, and that the admin shows no "Labs missing or too old" notice.
- [ ] **Step 2:** If Clubhouse checks for Labs before `after_setup_theme`, record exactly where. Either it moves to `blueworx_theme_ready` in Clubhouse (a one-line fix, done in Clubhouse's own repo with its own PR), or it goes in stage 3. Ask Luke which.
- [ ] **Step 3:** Write the 2.0.0 changelog entry in plain words: "The Labs plugin is now a theme", what sites get, and that the plugin keeps getting fixes on 1.x.
- [ ] **Step 4:** Once the PR is merged, tag `v2.0.0` and confirm the release workflow publishes `blueworx-2.0.0.zip` with every entry under `blueworx/`.
- [ ] **Step 5:** With Luke, enable the theme for the clubhouse.blueworx.io network and set it on one test subsite. Check the admin, login and console there.
