# Standard blocks look right Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A page built from WordPress's standard blocks uses the site's design in the block editor and on the live page.

**Architecture:** The theme already pushes its named styles, fonts and base stylesheet into the block editor and registers the palette and type sizes. What is missing is coverage: the base stylesheet styles elements but not the layout blocks (buttons row, columns, group with a background, cover, quote, pullquote, separator, table block, media and text), and the theme never declared wide and full alignment, so the editor hides those options while the stylesheet waits for them. Every rule stays inside `:where()` so a plugin's own styles still win. Older sites keep Site styles off: a live site's look must never change on update, and the Design screen already says how to turn it on.

**Tech Stack:** CSS, PHP, Playwright.

**Spec:** blueworx_labs_wordpress_theme#31.

## Global Constraints

- Only named styles (`var(--…)`) in `assets/css/site.css`; no hand-written colours or sizes.
- Rules inside `:where()` so they carry no weight.
- Version 2.6.0 in `style.css`, `package.json`, `includes/bootstrap.php`, `readme.txt`; changelog entry in plain words.

## Review Focus

- A site that had the theme before 2.1.0 must look exactly the same after this update: pinned by the existing migration test, and nothing here runs while Site styles is off.
- The editor canvas and the live page must agree on the button colour and body font: pinned by the parity test.
- Wide and full alignment must be offered in the editor and honoured on the page: pinned by asserting the alignment classes reach the page unchanged.

---

### Task 1: Alignment support and block coverage

**Files:**
- Modify: `includes/bootstrap.php:204-211` (`blueworx_theme_supports`)
- Modify: `assets/css/site.css` (after the Images section)

- [ ] Step 1: add `add_theme_support( 'align-wide' );` to `blueworx_theme_supports()`.
- [ ] Step 2: add `:where()` rules for `.wp-block-buttons` (flex, gap `--space-3`, wrap), `.wp-block-button` (margin 0), `.wp-block-columns` (gap `--space-6`; stacks below 782px by core), `.wp-block-group.has-background` and `.wp-block-cover` (padding `--space-8`, radius `--radius-lg`), `.wp-block-cover__inner-container` (max width `--width-reading`), `.wp-block-quote.is-style-large`/`.wp-block-pullquote` (heading font, `--text-h3`, accent border), `.wp-block-separator` (as `hr`), `.wp-block-table table` (margin 0), `.wp-block-media-text` (gap `--space-6`, radius on the image), `.wp-block-list` nested spacing. Everything by named style.
- [ ] Step 3: commit.

### Task 2: The parity test

**Files:**
- Create: `tests/block-styles.spec.js`

- [ ] Step 1: test `standard blocks use the site's design on the live page and in the editor`: sign in, switch Site styles on (restore after), create a page through REST with a buttons row, two columns, a group with a background and a quote; visit it and assert the button's computed background is the primary colour and the heading font is the heading stack; open the page in the block editor and assert the same two values inside the canvas iframe; assert `alignwide` is offered (the toolbar's align menu lists "Wide width").
- [ ] Step 2: run against the harness, expect PASS. Commit.

### Task 3: Version, changelog, docs

- [ ] Step 1: 2.6.0 in the four files; changelog "Added: Pages built from standard blocks use the site's design…"; `docs/design-system.md` Base styles section lists the blocks covered and states the older-sites decision.
- [ ] Step 2: `npm run version:check`, `npm run test:php`. Commit.

## Decisions taken during the build

- WordPress's own button colour (dark grey, class-level weight) beat the weightless base rules, so buttons never took the brand colour. Fixed at source with the `wp_theme_json_data_default` filter, only while Site styles is on, rather than by giving site.css weight.
- The editor spaced columns and buttons rows with WordPress's block gap (24px) while the page used the theme's. Both now read one variable, `--wp--style--block-gap`, set to `--space-6`: on the page from site.css, in the editor from the same filter.
