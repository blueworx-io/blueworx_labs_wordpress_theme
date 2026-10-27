# Site design system — settings and named styles (#17, part 1 of 3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every site running the theme gets a design system — colours, fonts, type sizes, spacing, corners, buttons and brand settings — published as named CSS styles that public pages, the block editor and plugins all read.

**Architecture:** One option (`blueworx_design`) holds the team's choices. Pure functions turn those choices into a flat list of named styles (CSS custom properties), with readable shades worked out from the brand colour. The list is printed on every public page and in the block editor, a base stylesheet styles plain WordPress content from it, and two filters let a plugin add fonts and ready-made presets.

**Tech Stack:** PHP (procedural, `blueworx_` prefix, WordPress coding standards), plain CSS, one small vanilla JS file, Playwright + the repo's plain-PHP check scripts.

**Spec:** GitHub issue #17 ("Site design system for every site") and the approved mock-up https://claude.ai/artifact/LF8b4rvmRCDqFLkZdK34Bq (section 19 lists the named styles and default values).

**Split of #17:** this plan is part 1. Part 2 is the read-only design system screen (the mock-up's 19 sections drawn from the site's values). Part 3 is the "Download design system" zip. Each is its own PR and its own minor version.

## Global Constraints

- Version: minor bump to **2.1.0** (style.css, `BLUEWORX_LABS_VERSION`, package.json, readme.txt Stable tag) with a CHANGELOG entry, once for the PR. Rebase on #23 (2.0.3) first.
- No new npm or Composer dependency. Fonts are local woff2 files; nothing is loaded from Google.
- Text domain `blueworx-labs-wordpress`; every function prefixed `blueworx_`; every file starts with the `ABSPATH` guard.
- Admin screens use the existing admin design helpers (`blueworx_open_admin_page`, `blueworx_ds_card`, `blueworx_ds_field`, `blueworx_ds_select`, `blueworx_ds_input`, `blueworx_ds_notice`, `.bw-savebar`) and keep the BlueWorx admin look — the site design system never styles admin screens.
- Named styles use exactly the mock-up's names (below). A plugin reads `var(--color-primary)` etc. and never hard-codes a value.
- Skins are not in the theme. The theme ships no presets; it only provides the filter.
- Saving purges the page cache via `blueworx_purge_site_cache( 'design' )`.
- Readability threshold is WCAG AA, 4.5:1, for every text pair.
- Copy is plain and short, written for the team member using the screen.

### Named styles (the contract)

Colours: `--color-primary`, `--color-primary-hover`, `--color-primary-pressed`, `--color-primary-tint`, `--color-on-primary`, `--color-primary-50` … `--color-primary-900` (50,100,200…900), `--color-accent`, `--color-accent-text`, `--color-accent-tint`, `--color-on-accent`, `--color-bg`, `--color-surface`, `--color-border`, `--color-border-strong`, `--color-placeholder`, `--color-text-muted`, `--color-text`, `--color-dark-raised`, `--color-success`, `--color-warning`, `--color-error`, `--color-info` and each with `-tint`.
Fonts: `--font-heading`, `--font-body`.
Type: `--text-display`, `--text-h1` … `--text-h6`, `--text-lead`, `--text-body`, `--text-small`, `--text-caption`.
Spacing: `--space-1`, `-2`, `-3`, `-4`, `-6`, `-8`, `-12`, `-16`, `-24`.
Corners: `--radius-sm`, `--radius-md`, `--radius-lg`, `--radius-xl`, `--radius-full`, `--radius-button`.
Effects: `--shadow-sm`, `--shadow-md`, `--shadow-lg`, `--focus-ring`, `--overlay`.
Layout: `--width-reading`, `--width-page`, `--width-wide`, `--gutter`, `--section-space`.

The mock-up's `--blue-50…900` scale is renamed `--color-primary-50…900`, because the brand colour is not always blue.

### Default look (a fresh site)

Primary `#2F5BEA`, accent `#F08A2C`, background `#FFFFFF`, text `#16181D`. Headings Sora, body Inter. Type, spacing, corners and buttons on "standard"/"rounded". With these settings the named styles equal the mock-up's values where the mock-up gives one, except derived shades, which only have to be within the rules below.

## Review Focus

1. **A site already running the theme updates to 2.1.0** — it must not change how its public pages look. Base element styles are a new feature, `site_styles`, on for new sites and written off for existing ones by a migration. The named styles themselves are always printed (they style nothing on their own). Test in Task 4.
2. **A pale or mid-tone brand colour (e.g. `#F5D90A` yellow, `#808080` grey)** — link text, button text and accent text must still read at 4.5:1; anything that cannot is flagged on save, not silently saved. Test in Task 1 and Task 3.
3. **A dark background (e.g. `#111111` bg with `#F2F2F2` text)** — shades must go the right way (hover gets lighter, not darker), and every pair in the readability report must still pass. Test in Task 1 and Task 2.
4. **Bad input from a form or another plugin** — a colour like `red`, `#12`, `javascript:` or a font id that does not exist must fall back to the default, never reach the page. Test in Task 2.
5. **A preset from a plugin that sets only some keys, or a bad value** — the rest comes from the defaults and the bad value is cleaned like any other input. Test in Task 2.

---

## File structure

- Create `includes/design/color.php` — pure colour maths, no WordPress.
- Create `includes/design/settings.php` — defaults, choices, fonts and presets registries, reading/cleaning/saving the option.
- Create `includes/design/tokens.php` — settings → named styles, the CSS text, and the readability report.
- Create `includes/design/output.php` — prints styles on public pages and in the editor, editor palette, `site_styles` feature gate.
- Create `includes/design/screen.php` — the BlueWorx › Design screen and its save handler.
- Create `assets/css/site.css` — base styles for plain WordPress content, written only with named styles.
- Create `assets/js/design-screen.js` — media pickers for the extra logos.
- Create `docs/design-system.md` — the named styles list and the two filters, for plugin authors.
- Modify `includes/bootstrap.php` (require the five files), `includes/features.php` (add `site_styles`), `includes/upgrade.php` (migration 15), `includes/admin-settings.php` (menu entry), `package.json` (`test:php`), `tests/global-setup.js` (test plugin hooks).
- Tests: `tests/php/design-color-test.php`, `tests/php/design-settings-test.php`, `tests/php/design-tokens-test.php`, `tests/design-system.spec.js`.

---

### Task 1: Colour maths

**Files:**
- Create: `includes/design/color.php`
- Test: `tests/php/design-color-test.php`; add it to `test:php` in `package.json`

**Interfaces:**
- Produces (all pure, all hex in lowercase `#rrggbb`):
  - `blueworx_color_normalize( string $hex ): string` — `#rgb`/`rrggbb`/`#rrggbb` → `#rrggbb`; anything else → `''`.
  - `blueworx_color_contrast( string $a, string $b ): float` — WCAG ratio.
  - `blueworx_color_mix( string $a, string $b, float $weight_a ): string`.
  - `blueworx_color_pole( string $bg ): string` — `#000000` or `#ffffff`, whichever contrasts more with `$bg`.
  - `blueworx_color_readable_on( string $color, string $bg, float $min = 4.5 ): string` — the colour nearest `$color`, stepping toward the pole in twentieths, that reaches `$min` on `$bg`.
  - `blueworx_color_text_on( string $fill, string $ink ): string` — `$ink` or `#ffffff`, whichever reads better on `$fill`.
  - `blueworx_color_partner( string $primary, string $bg, string $ink ): string` — the default second colour.
  - `blueworx_color_scale( string $hex ): array` — keys `50,100,200,300,400,500,600,700,800,900`.

Port the maths from Clubhouse's `includes/theme/class-color-engine.php` (`relative_luminance`, `contrast_ratio`, `mix`, `pole_for`, `to_hsl`/`from_hsl`, `default_secondary`) as functions. `blueworx_color_partner` is `default_secondary`. Scale: 50–400 mix with white at weights 0.08, 0.17, 0.34, 0.55, 0.78; 500 is the colour; 600–900 mix with black at 0.82, 0.66, 0.52, 0.36.

- [ ] **Step 1: Write the failing checks** in the style of `tests/php/tracking-codes-test.php` (`require __DIR__ . '/stubs.php'`, `check()`, `finish()`):

```php
check( 'normalises short hex', blueworx_color_normalize( '#ABC' ), '#aabbcc' );
check( 'refuses a word', blueworx_color_normalize( 'red' ), '' );
check( 'refuses script', blueworx_color_normalize( 'javascript:alert(1)' ), '' );
check( 'black on white is 21', round( blueworx_color_contrast( '#000000', '#ffffff' ), 2 ), 21.0 );
check( 'pole on white is black', blueworx_color_pole( '#ffffff' ), '#000000' );
check( 'pole on dark is white', blueworx_color_pole( '#111111' ), '#ffffff' );
check( 'readable colour is left alone', blueworx_color_readable_on( '#16181d', '#ffffff' ), '#16181d' );
foreach ( array( '#f5d90a', '#808080', '#2f5bea', '#c6f24e', '#00ffff' ) as $c ) {
	check( "$c made readable on white", blueworx_color_contrast( blueworx_color_readable_on( $c, '#ffffff' ), '#ffffff' ) >= 4.5, true );
	check( "$c made readable on dark", blueworx_color_contrast( blueworx_color_readable_on( $c, '#111111' ), '#111111' ) >= 4.5, true );
}
check( 'white text on brand blue', blueworx_color_text_on( '#2f5bea', '#16181d' ), '#ffffff' );
check( 'ink text on yellow', blueworx_color_text_on( '#f5d90a', '#16181d' ), '#16181d' );
check( 'scale has ten steps', array_keys( blueworx_color_scale( '#2f5bea' ) ), array( 50, 100, 200, 300, 400, 500, 600, 700, 800, 900 ) );
check( 'scale 500 is the colour', blueworx_color_scale( '#2f5bea' )[500], '#2f5bea' );
check( 'partner differs from primary', blueworx_color_partner( '#2f5bea', '#ffffff', '#16181d' ) !== '#2f5bea', true );
```

- [ ] **Step 2: Run** `php tests/php/design-color-test.php` — expect a fatal "Call to undefined function".
- [ ] **Step 3: Implement** `includes/design/color.php`.
- [ ] **Step 4: Run** the same command — expect "All checks passed."
- [ ] **Step 5: Commit** — "Add the colour maths the site design system uses".

---

### Task 2: Settings, fonts and presets

**Files:**
- Create: `includes/design/settings.php`
- Test: `tests/php/design-settings-test.php` (stub `get_option`/`update_option` come from `stubs.php`; stub `apply_filters` already supports `$GLOBALS['filters']`; add `esc_url_raw`, `absint`, `get_template_directory_uri` stubs locally)

**Interfaces:**
- Consumes: `blueworx_color_normalize()`.
- Produces:
  - `blueworx_design_defaults(): array` — keys and defaults: `primary '#2f5bea'`, `accent '#f08a2c'` (empty string means "work it out"), `background '#ffffff'`, `text '#16181d'`, `heading_font 'sora'`, `body_font 'inter'`, `type_scale 'standard'`, `spacing 'standard'`, `corners 'rounded'`, `buttons 'rounded'`, `logo_dark 0`, `logo_mark 0`, `social array()`, `preset ''`.
  - `blueworx_design_choices(): array` — `type_scale`: compact 0.875 / standard 1 / large 1.125. `spacing`: compact 0.75 / standard 1 / roomy 1.25. `corners`: square / subtle / rounded / round. `buttons`: square / rounded / pill. Each entry `array( 'label' => …, 'value' => … )`.
  - `blueworx_design_social_networks(): array` — `facebook, instagram, x, linkedin, youtube, tiktok` → label.
  - `blueworx_design_fonts(): array` — id → `array( 'label', 'family', 'stack', 'files' => array( weight => url ) )`. Built in: `sora` (400, 600, 700), `inter` (400, 500, 600) from `assets/fonts/`, `system` (no files, `system-ui, -apple-system, "Segoe UI", sans-serif`), `serif` (no files, `Georgia, "Times New Roman", serif`). Passed through filter `blueworx_design_fonts`.
  - `blueworx_design_presets(): array` — filter `blueworx_design_presets`, default `array()`. Each: id → `array( 'label', 'description', 'settings' => partial settings )`.
  - `blueworx_design_sanitize( array $raw ): array` — every key from defaults; anything missing or invalid takes the default; colours via `blueworx_color_normalize` (empty `accent` allowed); fonts must exist in `blueworx_design_fonts()`; choices must exist; logo ids via `absint`; social URLs via `esc_url_raw`, only `http`/`https`, only known networks.
  - `blueworx_design_settings(): array` — `blueworx_design_sanitize( (array) get_option( 'blueworx_design', array() ) )`, so a stored value is cleaned on the way out too.
  - `blueworx_design_save( array $raw ): array` — cleans and stores; returns what was stored.
  - `blueworx_design_apply_preset( string $id ): bool` — merges the preset's settings over the defaults, saves with `preset` set to `$id`; false for an unknown id.
  - `blueworx_design_capability(): string` — filter `blueworx_design_capability`, default `manage_options`.

- [ ] **Step 1: Write the failing checks:**

```php
check( 'fresh site gets defaults', blueworx_design_settings(), blueworx_design_defaults() );
$clean = blueworx_design_sanitize( array( 'primary' => 'red', 'heading_font' => 'comic', 'corners' => 'wobbly', 'social' => array( 'facebook' => 'javascript:alert(1)', 'myspace' => 'https://x.test' ) ) );
check( 'bad colour falls back', $clean['primary'], '#2f5bea' );
check( 'unknown font falls back', $clean['heading_font'], 'sora' );
check( 'unknown choice falls back', $clean['corners'], 'rounded' );
check( 'bad and unknown social links dropped', $clean['social'], array() );
check( 'empty accent is kept as automatic', blueworx_design_sanitize( array( 'accent' => '' ) )['accent'], '' );
$GLOBALS['filters']['blueworx_design_fonts'] = function ( $fonts ) { $fonts['syne'] = array( 'label' => 'Syne', 'family' => 'Syne', 'stack' => '"Syne", sans-serif', 'files' => array( 700 => 'https://club.test/syne.woff2' ) ); return $fonts; };
check( 'a plugin font can be chosen', blueworx_design_sanitize( array( 'heading_font' => 'syne' ) )['heading_font'], 'syne' );
$GLOBALS['filters']['blueworx_design_presets'] = function () { return array( 'court' => array( 'label' => 'Court', 'description' => '', 'settings' => array( 'primary' => '#c6f24e', 'corners' => 'nonsense' ) ) ); };
check( 'preset applies', blueworx_design_apply_preset( 'court' ), true );
check( 'preset colour saved', blueworx_design_settings()['primary'], '#c6f24e' );
check( 'preset bad value cleaned', blueworx_design_settings()['corners'], 'rounded' );
check( 'preset untouched keys are defaults', blueworx_design_settings()['body_font'], 'inter' );
check( 'preset recorded', blueworx_design_settings()['preset'], 'court' );
check( 'unknown preset refused', blueworx_design_apply_preset( 'nope' ), false );
```

- [ ] **Step 2: Run** `php tests/php/design-settings-test.php` — expect failure.
- [ ] **Step 3: Implement** `includes/design/settings.php`.
- [ ] **Step 4: Run** — expect "All checks passed."
- [ ] **Step 5: Commit** — "Store the site's design choices, with fonts and presets plugins can add".

---

### Task 3: Named styles and the readability report

**Files:**
- Create: `includes/design/tokens.php`
- Test: `tests/php/design-tokens-test.php`

**Interfaces:**
- Consumes: Task 1 functions; `blueworx_design_settings()`, `blueworx_design_choices()`, `blueworx_design_fonts()`.
- Produces:
  - `blueworx_design_tokens( ?array $settings = null ): array` — every name in "Named styles" → value; `null` reads the saved settings. Passed through filter `blueworx_design_tokens` (second argument: the settings).
  - `blueworx_design_phone_tokens( ?array $settings = null ): array` — the `--text-*` values used below 640px.
  - `blueworx_design_token( string $name ): string` — one value, `''` if unknown.
  - `blueworx_design_css( ?array $settings = null ): string` — `:root{…}` plus `@media (max-width:639px){:root{…}}`.
  - `blueworx_design_font_face_css( ?array $settings = null ): string` — `@font-face` rules for only the two chosen fonts' files, `font-display:swap`.
  - `blueworx_design_readability( ?array $tokens = null ): array` — list of `array( 'label', 'text', 'background', 'ratio', 'passes' )`.

Rules (pole = `blueworx_color_pole( bg )`):
- primary as saved; hover = `readable_on( mix( primary, pole, 0.82 ), bg )`; pressed = `mix( primary, pole, 0.66 )`; tint = `mix( primary, bg, 0.08 )`; on-primary = `text_on( primary, text )`; 50–900 from `blueworx_color_scale`.
- accent = saved, or `blueworx_color_partner( primary, bg, text )` when empty; accent-text = `readable_on( accent, bg )`; accent-tint = `mix( accent, bg, 0.12 )`; on-accent = `text_on( accent, text )`.
- surface = `mix( text, bg, 0.035 )`, border = `mix( text, bg, 0.15 )`, border-strong = `mix( text, bg, 0.42 )`, placeholder = `readable_on( mix( text, bg, 0.62 ), bg )`, text-muted = `readable_on( mix( text, bg, 0.75 ), bg )`, dark-raised = `mix( text, bg, 0.92 )`.
- status starts from `#1d7a46`, `#9a5108`, `#c42b2b`, `#0b6bcb`, each through `readable_on( …, bg )`; tints `mix( status, bg, 0.10 )`.
- Type px (desktop / phone): display 64/40, h1 48/34, h2 36/28, h3 28/24, h4 22/20, h5 18/17, h6 14/14, lead 19/17, body 16/16, small 14/14, caption 12/12. `type_scale` multiplies display–h5 only, rounded to whole px.
- Spacing 4, 8, 12, 16, 24, 32, 48, 64, 96 × `spacing` value, rounded; `--section-space: clamp(56px, 8vw, 96px)` and `--gutter: clamp(16px, 3vw, 32px)` with each px number × the same value.
- Corners (sm/md/lg/xl): square 0/0/0/0, subtle 2/4/6/10, rounded 4/8/12/20, round 8/12/20/32; `--radius-full: 9999px`. `--radius-button`: square 0, rounded = `--radius-md` value, pill 9999px.
- Shadows, overlay and layout widths as the mock-up; `--focus-ring: 0 0 0 3px` + primary as `rgba(r, g, b, .28)`.
- Fonts: `--font-heading`/`--font-body` = the chosen font's `stack`.
- Readability pairs: body text on background; muted text on background; placeholder on background; links (primary-hover) on background; button text on primary; accent text on background; text on surface; each status colour on background and on its tint.

- [ ] **Step 1: Write the failing checks:**

```php
$t = blueworx_design_tokens( blueworx_design_defaults() );
check( 'primary passes through', $t['--color-primary'], '#2f5bea' );
check( 'white on default blue', $t['--color-on-primary'], '#ffffff' );
check( 'h1 is 48px', $t['--text-h1'], '48px' );
check( 'space-24 is 96px', $t['--space-24'], '96px' );
check( 'rounded md is 8px', $t['--radius-md'], '8px' );
check( 'heading font is Sora', strpos( $t['--font-heading'], '"Sora"' ) === 0, true );
foreach ( $named_styles_list as $name ) { check( "$name present", isset( $t[ $name ] ), true ); } // the full list from Global Constraints, written out
check( 'phone h1 is 34px', blueworx_design_phone_tokens( blueworx_design_defaults() )['--text-h1'], '34px' );
check( 'large scale grows h1', blueworx_design_tokens( array_merge( blueworx_design_defaults(), array( 'type_scale' => 'large' ) ) )['--text-h1'], '54px' );
check( 'pill buttons', blueworx_design_tokens( array_merge( blueworx_design_defaults(), array( 'buttons' => 'pill' ) ) )['--radius-button'], '9999px' );
check( 'empty accent is worked out', blueworx_design_tokens( array_merge( blueworx_design_defaults(), array( 'accent' => '' ) ) )['--color-accent'] !== '', true );
foreach ( array( 'defaults' => array(), 'yellow' => array( 'primary' => '#f5d90a' ), 'grey' => array( 'primary' => '#808080' ), 'dark' => array( 'background' => '#111111', 'text' => '#f2f2f2' ) ) as $label => $over ) {
	$report = blueworx_design_readability( blueworx_design_tokens( array_merge( blueworx_design_defaults(), $over ) ) );
	foreach ( $report as $row ) {
		if ( 'Button text on the main colour' === $row['label'] && 'grey' === $label ) { continue; } // mid-grey cannot carry text; flagged, see next check
		check( "$label: {$row['label']}", $row['passes'], true );
	}
}
$grey = blueworx_design_readability( blueworx_design_tokens( array_merge( blueworx_design_defaults(), array( 'primary' => '#767676' ) ) ) );
check( 'mid-grey button text is flagged', in_array( false, wp_list_pluck_stub( $grey, 'passes' ), true ), true );
check( 'dark hover gets lighter', blueworx_color_contrast( blueworx_design_tokens( array_merge( blueworx_design_defaults(), array( 'background' => '#111111', 'text' => '#f2f2f2' ) ) )['--color-primary-hover'], '#111111' ) >= 4.5, true );
check( 'css has root and phone block', (bool) preg_match( '/^:root\{.*\}@media \(max-width:639px\)\{:root\{.*\}\}$/s', blueworx_design_css( blueworx_design_defaults() ) ), true );
check( 'only chosen fonts loaded', substr_count( blueworx_design_font_face_css( blueworx_design_defaults() ), '@font-face' ), 6 );
check( 'system font loads nothing', blueworx_design_font_face_css( array_merge( blueworx_design_defaults(), array( 'heading_font' => 'system', 'body_font' => 'system' ) ) ), '' );
```

(`wp_list_pluck_stub` is a local `array_column` wrapper; `#808080` and `#767676` both sit where neither black nor white text reaches 4.5:1 on the fill — if `#808080` turns out to pass, keep the skip only for `#767676`.)

- [ ] **Step 2: Run** `php tests/php/design-tokens-test.php` — expect failure.
- [ ] **Step 3: Implement** `includes/design/tokens.php`.
- [ ] **Step 4: Run** — expect "All checks passed." Also re-run Tasks 1–2 checks.
- [ ] **Step 5: Commit** — "Turn the site's design choices into named styles, with readability checks".

---

### Task 4: Public pages, the editor, and the switch for existing sites

**Files:**
- Create: `includes/design/output.php`, `assets/css/site.css`
- Modify: `includes/bootstrap.php` (require `includes/design/color.php`, `settings.php`, `tokens.php`, `output.php`, `screen.php` after `features.php`), `includes/features.php` (new `site_styles` in `appearance`), `includes/upgrade.php` (`blueworx_get_labs_db_version()` → 15; `blueworx_migrate_site_styles_default()` run under `$stored_version < 15`, before the version row write, same shape as `blueworx_migrate_rest_users_default()`)
- Test: `tests/design-system.spec.js` (first half)

**Interfaces:**
- Consumes: `blueworx_design_css()`, `blueworx_design_font_face_css()`, `blueworx_design_tokens()`, `blueworx_feature_enabled()`.
- Produces:
  - Style handle `blueworx-design` — always registered and enqueued on public pages; its inline CSS is the font faces plus the named styles. Plugins declare it as a dependency to be sure the names exist.
  - Style handle `blueworx-site` (`assets/css/site.css`, depends on `blueworx-design`) — enqueued only when `site_styles` is on.
  - Feature `site_styles`: label "Site styles", description "Styles headings, text, links, buttons, forms, tables, FAQs and images on public pages from the site's design system. Off on sites that had the theme before 2.1.0, so their pages do not change until you choose.", `default` '1', `guide` false.
  - Editor: `add_theme_support( 'editor-color-palette', … )` with primary, accent, text, muted, background, surface (values from tokens), `editor-font-sizes` small/body/lead/h3/h2/h1, and the same inline CSS in the editor through `enqueue_block_editor_assets` scoped to `.editor-styles-wrapper` (plus `site.css` when `site_styles` is on).

`site.css` covers, using only named styles: `body` (bg, text, body font, `--text-body`, line-height 1.65), `h1`–`h6` (heading font, sizes, weights and line-heights from the mock-up's type rows; h6 capitals with 0.08em spacing), links (primary-hover, underlined, `font-weight:500`), `:focus-visible` (`--focus-ring`), buttons (`.wp-element-button`, `.wp-block-button__link`, `button`, `input[type=submit]`: primary fill, on-primary text, `--radius-button`, hover/pressed), inputs/selects/textareas (border-strong, `--radius-md`, placeholder colour), `.wp-block-table` and `table`, `details`/`summary` (the FAQ look), images and captions, `.is-style-dark` / `.has-dark-background` bands (text as background, bg as text, dark-raised panels), and `.alignwide`/`.alignfull`/content width using the layout widths. Nothing in it targets `#wpadminbar` or admin screens.

- [ ] **Step 1: Write the failing specs** in `tests/design-system.spec.js`, same imports and `test.skip(isPlaceholder…)` as `tests/tracking-codes.spec.js`:
  - `named styles are on every public page`: GET `/` → the `#blueworx-design-inline-css` style contains `--color-primary:#2f5bea` and `--text-h1:48px`; `getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim()` is `#2f5bea`.
  - `fonts load from the site, not Google`: the page HTML contains `/assets/fonts/sora-700.woff2` and no `fonts.googleapis.com`.
  - `site styles switch`: with `site_styles` on (via `setFeature` + `saveEnhancements`), `getComputedStyle(h1).fontFamily` contains `Sora` on a published test page; with it off, `blueworx-site-css` is absent from the HTML. Restore with `restoreAll`.
  - `the editor uses the same colours`: open `/wp-admin/post-new.php?post_type=page`, the editor iframe or wrapper's computed `--color-primary` is `#2f5bea`.
- [ ] **Step 2: Run** `npx playwright test tests/design-system.spec.js` against the local harness (port 8886, see the theme-test-harness note) — expect failures.
- [ ] **Step 3: Implement** output.php, site.css, the feature entry, the migration, and the bootstrap requires.
- [ ] **Step 4: Run** the spec, `npm run test:php`, and `tests/theme-skeleton.spec.js` — expect all pass.
- [ ] **Step 5: Commit** — "Print the design system on public pages and in the editor".

---

### Task 5: The Design screen

**Files:**
- Create: `includes/design/screen.php`, `assets/js/design-screen.js`
- Modify: `includes/admin-settings.php` (submenu "Design", slug `blueworx-design`, capability `blueworx_design_capability()`, callback `blueworx_render_design_page`, placed straight after Enhancements)
- Test: `tests/design-system.spec.js` (second half)

**Interfaces:**
- Consumes: Tasks 2–3.
- Produces: admin-post action `blueworx_save_design` (nonce `blueworx_save_design`); admin-post action `blueworx_apply_design_preset` (nonce `blueworx_apply_design_preset`, field `preset`).

Screen, top to bottom, all with the admin design helpers:
1. Notice after save: success, or a warning listing each failing pair from `blueworx_design_readability()` ("Hard to read: link text on the background (3.1:1). It still saved — pick a darker or lighter colour to fix it.").
2. **Presets** card — only when `blueworx_design_presets()` is not empty: a select and "Use this preset" button, with the note "Replaces the settings below."
3. **Brand** card — site name (writes the core `blogname`), logo (core `custom_logo` theme mod; add `add_theme_support( 'custom-logo' )` in `blueworx_theme_supports()`), logo for dark backgrounds (`logo_dark`), small mark (`logo_mark`), each a media picker; favicon is a link to Settings › General ("WordPress keeps the site icon there"); social links, one URL input per network.
4. **Colours** card — main colour, second colour (help: "Leave empty and one is picked to go with the main colour"), background, text. `<input type="color">` paired with a hex text box, ids `blueworx_design_primary` etc. Beside each, a small swatch row showing the worked-out hover, tint and text-on shades from the saved settings.
5. **Fonts and type** card — heading font, body font (selects from `blueworx_design_fonts()`), type size (`type_scale`).
6. **Spacing, corners and buttons** card — `spacing`, `corners`, `buttons` selects.
7. `.bw-savebar` with "Nothing changes until you save." and a Save button.

Saving: `blueworx_require_post_request()`, capability check, `check_admin_referer`, `blueworx_design_save()`, update `blogname` with `sanitize_text_field`, set `custom_logo`, `blueworx_purge_site_cache( 'design' )`, transient notice, redirect back. `design-screen.js` opens `wp.media` for the three logo pickers and writes the attachment id into a hidden input; enqueue it with `wp_enqueue_media()` only on this screen.

- [ ] **Step 1: Write the failing specs** (add to `tests/design-system.spec.js`):
  - `the team changes the main colour and the whole site follows`: on `/wp-admin/admin.php?page=blueworx-design` fill `#blueworx_design_primary_hex` with `#0a7d5c`, save; GET `/` shows `--color-primary:#0a7d5c`. Restore the default after.
  - `a hard-to-read colour is flagged but saved`: save background `#ffffff`, main colour `#f5d90a`, text `#d0d0d0`; the notice contains "Hard to read"; the page shows `--color-text:#d0d0d0`. Restore.
  - `a bad colour never reaches the page`: post `blueworx_design_primary=javascript:alert(1)` through the form (fill the hex box); the page shows the previous primary.
  - `a plugin preset restyles the site`: the test plugin (Step 3) registers preset `fixture` with primary `#c6f24e` and font `fixture-mono`; choose it, apply; GET `/` shows `--color-primary:#c6f24e` and `--font-heading:"Fixture Mono"`; and the test plugin's page marker (below) reads the value. Restore.
  - `a plugin reads every named style`: GET `/` contains the test plugin's comment `<!-- design tokens seen: N -->` where N equals the number of named styles in the contract.
  - `site name, logo and social links save`: set the site name to "Fixture Site" and a Facebook URL; reload shows both. Restore.
- [ ] **Step 2: Add the test plugin hooks** to `TEST_HOOKS_PHP` in `tests/global-setup.js`: `blueworx_design_presets` adds `fixture`; `blueworx_design_fonts` adds `fixture-mono` (family "Fixture Mono", no files); a `wp_head` action prints `<!-- design tokens seen: %d -->` from `count( blueworx_design_tokens() )`.
- [ ] **Step 3: Run** the spec — expect the new tests to fail.
- [ ] **Step 4: Implement** screen.php, design-screen.js and the menu entry.
- [ ] **Step 5: Run** the spec — expect all pass. Run `tests/admin-design-system.spec.js` and `tests/enhancements-function-cards.spec.js` too, since a menu entry and a feature were added.
- [ ] **Step 6: Commit** — "Add the Design screen for the site's design system".

---

### Task 6: Docs, version, final checks

**Files:**
- Create: `docs/design-system.md`
- Modify: `style.css`, `includes/bootstrap.php`, `package.json`, `readme.txt` → 2.1.0; `CHANGELOG.md`

- [ ] **Step 1: Write `docs/design-system.md`** for plugin authors: the named styles table (name, what it is for, default), "depend on the `blueworx-design` style handle", the `blueworx_design_fonts`, `blueworx_design_presets`, `blueworx_design_tokens` and `blueworx_design_capability` filters with one short example each, and the rule "never hard-code a design value in a plugin".
- [ ] **Step 2: Bump to 2.1.0** and add the changelog entry under `### Added`: "**Every site has its own design system.** Our team sets the colours, fonts, sizes, spacing, corners and buttons once on BlueWorx › Design, and every public page, the editor and our plugins use them. Hard-to-read colour pairs are flagged. Sites that already had the theme keep their current look until Site styles is switched on."
- [ ] **Step 3: Run** `npm run version:check`, `npm run test:php`, the full Playwright suite against the local harness, then the linter once (`npm run lint` and `vendor/bin/phpcs`). Report lint findings to Luke; don't loop on them.
- [ ] **Step 4: Commit**, push `site-design-system`, open the PR "Site design system: settings and named styles" linking #17 (part 1 of 3).
