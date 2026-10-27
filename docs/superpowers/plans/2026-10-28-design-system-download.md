# Design system download (#17, part 3 of 3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** A "Download design system" button that gives a zip built from the site's current settings, matching what the design system page shows.

**Architecture:** A pure function lists the zip's contents (path → text, or path → file on disk) from the settings. A handler checks the request, builds the zip with WordPress's own PclZip (core, needs only zlib — `ZipArchive` is missing on some hosts and on the local test machine), streams it and deletes the temporary file. The colour list on the page and `colours.json` come from one shared palette function.

**Spec:** issue #17 ("A Download design system button…"), mock-up's download panel. Stacked on part 2 (#25), branch `site-design-download`.

## Global Constraints

- Version **2.3.0**, changelog entry.
- No new dependency: PclZip from `wp-admin/includes/class-pclzip.php`.
- Zip root folder `<site-name-slug>-design-system/`, containing: `README.md`, `tokens/tokens.css` (exactly `blueworx_design_tokens_file()`), `tokens/tokens.json`, `tokens/colours.json`, `fonts/*.woff2` + `fonts/fonts.css` for the chosen fonts that have local files, `logos/` with whichever of logo, dark logo, mark and site icon are set (original files, keeping their extension), `logos/LOGO-USAGE.md`.
- Only for `blueworx_design_capability()`, with a nonce. Nothing is written anywhere but a temp file that is deleted after sending.
- A font whose files are not on this site (another host) is listed in the README as "not included", never fetched.

## Review Focus

1. A site with no logos and device fonts only — the zip still builds, with no empty folders, and the README says what is missing. Test in Task 1.
2. A plugin font hosted elsewhere — not fetched, named in the README. Test in Task 1.
3. A site name with spaces, accents or symbols — the folder name is a clean slug, never empty. Test in Task 1.
4. Someone without the capability, or with a stale nonce, hits the download address — refused, no file. Test in Task 2.
5. tokens.css in the zip must be byte-for-byte what the page shows. Test in Task 1.

---

### Task 1: The zip's contents

**Files:** Create `includes/design/download.php`; modify `includes/design/tokens.php` (move the page's colour lists into `blueworx_design_palette()`), `includes/design/reference-page.php` (use it); test `tests/php/design-download-test.php`.

**Interfaces:**
- `blueworx_design_palette(): array` — groups `brand`, `neutrals`, `messages`, each a list of `array( 'token', 'label', 'use' )`, in the page's order.
- `blueworx_design_download_folder( string $site_name ): string` — `sanitize_title( $name ) . '-design-system'`, or `site-design-system` when the slug is empty.
- `blueworx_design_download_manifest( ?array $settings = null ): array` — relative path (under the root folder) → `array( 'content' => string )` or `array( 'file' => absolute path )`.
- `blueworx_design_local_path( string $url ): string` — a URL under the theme or `wp-content` mapped to a readable file, else `''`.

- [ ] PHP checks (stub `sanitize_title`, `get_bloginfo`, `get_theme_mod`, `get_attached_file`, `get_option( 'site_icon' )`, `content_url`): default settings give `tokens/tokens.css` equal to `blueworx_design_tokens_file()`, `tokens/tokens.json` decoding to 74 root styles, `tokens/colours.json` with the main colour's hex, `fonts/sora-700.woff2` pointing at the theme file, `fonts/fonts.css` naming `sora-700.woff2`, `README.md`, `logos/LOGO-USAGE.md`, and no `logos/logo.*` when no logo is set; device fonts give no `fonts/` entries; a font on another host gives no font file and the README names it; folder for `"Café & Co!"` is `cafe-co-design-system`, for `"!!!"` is `site-design-system`.
- [ ] Implement; run; commit.

### Task 2: The button and the handler

**Files:** Modify `includes/design/download.php` (handler), `includes/design/reference-page.php` (button in the top bar), `includes/design/screen.php` (header button); test `tests/design-download.spec.js`.

**Interfaces:**
- `blueworx_design_download_url(): string` — `wp_nonce_url( admin_url( 'admin-post.php?action=blueworx_download_design' ), 'blueworx_download_design' )`.
- admin-post action `blueworx_download_design` → `blueworx_design_handle_download()`: capability, `check_admin_referer`, build with PclZip in `wp_tempnam()`, send `Content-Type: application/zip`, `Content-Disposition: attachment; filename="<folder>.zip"`, `nocache_headers()`, delete the temp file, exit.

- [ ] Specs (fail first): admin clicks "Download design system" on the design system page → a download named `<slug>-design-system.zip` whose bytes contain the entry names `tokens/tokens.css`, `tokens/colours.json`, `fonts/sora-700.woff2`, `README.md`; the Design screen header has the same button; the address without a valid nonce returns WordPress's "link has expired" page, not a zip; a signed-out request gets no zip.
- [ ] Implement; run the spec, then the design specs; commit.

### Task 3: Version and finish

- [ ] 2.3.0, changelog "**Download the design system.** One button gives a zip of the site's colours, fonts, logos and named styles, ready for designers and other tools."
- [ ] `npm run test:php`, design specs, lint once; push; PR against `site-design-reference`.
