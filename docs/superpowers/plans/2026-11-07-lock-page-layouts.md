# Lock page layouts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An administrator can lock a page so a client changes its words and images but cannot move, add or remove blocks, and the lock holds through the REST API as well as the editor.

**Architecture:** One post meta flag, `_blueworx_page_lock`, that only `manage_options` can write (REST auth callback plus the meta write filters). While a non-administrator edits a locked page, the editor gets `templateLock: all` and block locking switched off; the server compares the shape of the block tree (names and nesting, never text or attributes) on every save and refuses a changed shape with a 403 through REST, or keeps the old content on any other write. A sidebar panel in the block editor shows the switch to administrators and a plain notice to everyone else, and the Pages list marks locked pages.

**Tech Stack:** PHP, plain JS on `wp.element` (no build), Playwright, the theme's plain-PHP tests.

**Spec:** blueworx_labs_wordpress_theme#32.

## Global Constraints

- Enhancement key `page_lock`, section `content`, default on, `guide => false`.
- No build step; `assets/js/page-lock.js` is a plain script using `wp.*` globals and passes `eslint assets/js`.
- Version 2.7.0 in the four version files; changelog in plain words.
- The block-shape parser is pure PHP (no WordPress functions) so the plain-PHP tests cover it.

## Review Focus

- A client changing only text or an image URL must save: pinned by the PHP test (attribute and text changes are not a shape change) and the REST test.
- A client adding, removing or reordering a block, at any depth, must be refused: pinned by the PHP test (nested shape) and the REST 403 test.
- A client must not be able to clear the lock through REST: pinned by the meta-write test.
- An administrator must still edit freely and unlock: pinned by the unlock test.
- Block comments with JSON attributes and self-closing blocks must parse: pinned by the parser test.

---

### Task 1: The lock module

**Files:**
- Create: `includes/page-lock.php`
- Modify: `includes/bootstrap.php` (require), `includes/features.php` (registry entry after `revisions`), `tests/helpers.js` (`FEATURE_SECTIONS`)
- Test: `tests/php/page-lock-test.php`, added to `test:php` in `package.json`

**Interfaces:**
- Produces: `blueworx_page_lock_shape( string $content ): array` (nested `[ name, children[] ]` pairs), `blueworx_page_lock_structure_changed( string $old, string $new ): bool`, `blueworx_page_is_locked( int $post_id ): bool`, `blueworx_page_lock_can_manage(): bool`, const `BLUEWORX_PAGE_LOCK_META`.

- [ ] Step 1: PHP test: shape of two paragraphs; nested group/columns; self-closing spacer; text-only change not a change; attribute change not a change; added/removed/reordered block is a change; nested change is a change; bare-name blocks read as `core/…`.
- [ ] Step 2: run `php tests/php/page-lock-test.php`, expect FAIL (functions missing).
- [ ] Step 3: implement the module: meta registration with `auth_callback`, the three meta write filters, `rest_pre_insert_{type}` refusal, `wp_insert_post_data` fallback, `block_editor_settings_all`, `display_post_states`, the editor asset enqueue.
- [ ] Step 4: run the PHP test, expect PASS. Commit.

### Task 2: The editor panel

**Files:**
- Create: `assets/js/page-lock.js`

- [ ] Step 1: register a `PluginDocumentSettingPanel` "Page lock": administrators see a toggle bound to the meta; everyone else sees a notice when locked. Uses `wp.editor.PluginDocumentSettingPanel` with the `wp.editPost` fallback.
- [ ] Step 2: `npx eslint assets/js`, expect clean. Commit.

### Task 3: The browser test

**Files:**
- Create: `tests/page-lock.spec.js`

- [ ] Step 1: admin creates a page and locks it through REST; an editor-role user can change text through REST, is refused (403) when adding a block, and cannot clear the lock; in the editor the inserter is disabled and the panel says the page is locked; the Pages list shows "Locked"; the admin unlocks and the editor can then add a block.
- [ ] Step 2: run against the harness, expect PASS. Commit.

### Task 4: Version, changelog

- [ ] Step 1: 2.7.0 in the four files; changelog entry; `npm run version:check`, `npm run test:php`, `npx eslint assets/js` once. Commit.
