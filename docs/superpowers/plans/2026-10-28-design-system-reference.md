# Design system reference page (#17, part 2 of 3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** A read-only page showing the site's whole design system, laid out like the approved mock-up (19 sections), drawn from the site's own settings and in the site's own styles.

**Architecture:** A front-end URL, `/?blueworx-design-system=1`, open only to people who can change the design. It renders a full HTML page through the theme's normal head (so the named styles, fonts and plugin styles load), plus `assets/css/site.css` always and a reference stylesheet. Every specimen is real markup styled by named styles, so the page *is* the site's look. The Design screen links to it.

**Spec:** issue #17 (the "design system screen" bullet) and the mock-up https://claude.ai/artifact/LF8b4rvmRCDqFLkZdK34Bq. Builds on part 1 (PR #24); stacked branch `site-design-reference`.

## Global Constraints

- Version **2.2.0** with a changelog entry, once for the PR.
- Ships only from `includes/` and `assets/` (scripts/build-zip.mjs allowlist), so the template lives in `includes/design/`.
- Every colour, font, size and corner on the page is a named style (`var(--…)`) or a value read from the settings — never typed by hand. The page's own frame (section cards, jump bar) uses the named styles too.
- Not an admin screen, so it does not use the admin design system; it must never load on an admin screen.
- Nobody without `blueworx_design_capability()` sees it: logged-out visitors and other users get the site's normal page, with nothing about the design system in it. Sent with `noindex` and no-cache headers.
- Plain, short copy that names things as the Design screen does ("Main colour", "Second colour").

## Review Focus

1. A logged-out visitor or a subscriber requests the URL — sees the normal home page, no design system markup. Test in Task 1.
2. A site with no logo uploaded — the Logo section shows the site's first letter on the main colour, not a broken image. Test in Task 2.
3. A dark-background site — the frame and specimens stay readable (they use the same named styles). Covered by part 1's readability guarantees; spot-check by screenshot.
4. A site name with HTML or quotes — escaped everywhere it appears. Test in Task 2.
5. The tokens block must match exactly what pages get — built by the same function the download will use. Test in Task 1.

---

### Task 1: The page, its access rule, and the tokens file text

**Files:** Create `includes/design/reference.php`, `includes/design/reference-page.php` (markup), `assets/css/design-reference.css`, `assets/js/design-reference.js` (copy button). Modify `includes/design/tokens.php` (add `blueworx_design_tokens_file()`), `includes/bootstrap.php`, `includes/design/screen.php` (header link "View design system"), `package.json`. Tests: `tests/php/design-tokens-test.php`, `tests/design-reference.spec.js`.

**Interfaces:**
- `blueworx_design_tokens_file( ?array $settings = null ): string` — readable CSS: `:root {` then one `  --name: value;` per line in groups with `/* Colours */`-style comments, `}`, then the phone `@media (max-width: 639px)` block. Uses `blueworx_design_declarations()`'s cleaning. Part 3 writes it as `tokens.css`.
- `blueworx_design_reference_url(): string` — `home_url( '/?blueworx-design-system=1' )`.
- `blueworx_design_reference_allowed(): bool` — logged in and `current_user_can( blueworx_design_capability() )`.
- `template_redirect` handler `blueworx_design_reference_render()` — when the query arg is present and allowed: `nocache_headers()`, `X-Robots-Tag: noindex`, enqueue `blueworx-design`, `blueworx-site`, `blueworx-design-reference` (css) and its script, include `reference-page.php`, exit.

- [ ] PHP check: `blueworx_design_tokens_file()` starts with `:root {`, contains `  --color-primary: #2f5bea;` and every named style exactly once in the root block, and contains `@media (max-width: 639px)`.
- [ ] Specs (fail first): admin sees `section#overview` … `section#tokens` (19 ids, in order) and the tokens `<pre>` contains `--color-primary: #2f5bea;`; logged-out request has no `id="tokens"` and no `blueworx-design-reference`; the Design screen header links to the page.
- [ ] Implement; run PHP checks and the spec; commit.

### Task 2: The 19 sections, from the site's settings

Port the mock-up's sections in order: Overview, Colours, Typography, Spacing & radius, Layout, Buttons, Inputs, Badges & states, Alerts, Shadows, Cards, Navigation, FAQs & tables, Images, Dark sections, Icons, Logo, Usage, CSS tokens. Values come from `blueworx_design_settings()`, `blueworx_design_tokens()`, `blueworx_design_fonts()` and `blueworx_design_readability()`:
- Colours: swatches for brand, the ten-step scale, neutrals and messages, each with its hex, named style and a readability grade (AAA ≥ 7, AA ≥ 4.5, "Large text" ≥ 3, else "Decoration") for the text that goes on it; plus the readability report as a table with pass/fail.
- Typography: the chosen fonts' names, weights (from their files) and the type rows using `--text-*`.
- Spacing, radius, layout: from the tokens.
- Logo: the uploaded logo, dark logo, mark and site icon where set; otherwise the first letter of the site name on the main colour.
- Overview: site name, tagline, logo.
- Buttons, inputs, badges, alerts, shadows, cards, navigation, FAQs, tables, images, dark band: real elements (`button`, `input`, `details`, `table`) styled by site.css and small reference classes that use only named styles.

- [ ] Specs (fail first): changing the main colour on the Design screen changes the Colours section's first swatch hex; with no logo the Logo section shows the first letter; a site name of `Bob's <b>Bikes</b>` appears escaped (text `Bob's <b>Bikes</b>`, no `<b>` element in the overview heading).
- [ ] Implement; screenshot the page at desktop and phone widths and fix anything broken; run the spec; commit.

### Task 3: Version and finish

- [ ] 2.2.0 in all four places, changelog "**See the whole design system on one page.** BlueWorx › Design › View design system shows every colour, font, size, button and block as the site draws them, with readability scores."
- [ ] `npm run test:php`, the design specs, lint once; push; PR against `site-design-system` (stacked on #24).
