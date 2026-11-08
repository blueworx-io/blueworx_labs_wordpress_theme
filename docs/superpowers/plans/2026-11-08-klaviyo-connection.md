# Klaviyo connection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A fresh, generic Klaviyo connection in the theme: keys, a queue that never makes a visitor wait, a log, and three plain functions plus hooks that everything else (and any site's plugin) sends through.

**Architecture:** Five files under `includes/klaviyo/`. `settings.php` holds the switch and the two keys (wp-config.php constants win). `client.php` talks to Klaviyo and carries out a "plan" (profile, then event, then list; safe to repeat). `queue.php` stores one option row per job, runs them in the background on cron with five retries over six hours, and keeps a 100-line log. `api.php` is the public surface: `blueworx_klaviyo_identify()`, `blueworx_klaviyo_track()`, `blueworx_klaviyo_subscribe()`, the `blueworx_klaviyo_plan` filter on every send, `blueworx_klaviyo_protected_properties`, `blueworx_klaviyo_sources`, `blueworx_klaviyo_resolve_job` for work a webhook must look up later. `screen.php` is the settings and log screens. Nothing in the theme names a client, a form or a product.

**Tech Stack:** PHP, the theme's design-system helpers, WP cron, Playwright, the theme's plain-PHP tests with a Klaviyo test double in mu-plugins.

**Spec:** blueworx_labs_wordpress_theme#35 (part of #21).

## Global Constraints

- Enhancement key `klaviyo`, section `tracking`, default `'0'`, detail button to the Klaviyo page, guide entries under the same key.
- Screens `blueworx-klaviyo` and `blueworx-klaviyo-log`, listed only while the function is on, reachable while off so they can explain themselves.
- Options all start `blueworx_klaviyo_` and never autoload. The private key is never shown back in full.
- Klaviyo API revision pinned to `2026-10-15`.
- Version 2.8.0 in the four version files; changelog in plain words.
- No JavaScript in this PR.

## Review Focus

- A plan with an empty value must never wipe a held property: pinned in the PHP test (clean) and the spec (held profile keeps `status`).
- A fill or a protected property must never overwrite a held value: PHP test and spec (`member_number` stays `3`).
- A Klaviyo that is down must be retried and recorded once: spec (two 503s, one event with one unique_id).
- Switching the function off must stop queuing and hide the menu, while the address still answers: spec.
- A badly formed key must be refused with the previous one kept: spec (`bad!`, `nope`).

---

### Task 1: Settings and client

**Files:** `includes/klaviyo/settings.php`, `includes/klaviyo/client.php`
**Produces:** `blueworx_klaviyo_enabled()`, `blueworx_klaviyo_connected()`, `blueworx_klaviyo_private_key()`, `blueworx_klaviyo_public_key()`, `blueworx_klaviyo_clean_public_key()`, `blueworx_klaviyo_clean_private_key()`, `blueworx_klaviyo_mask()`, `blueworx_klaviyo_profile_attributes( $plan, $existing )`, `blueworx_klaviyo_event_body()`, `blueworx_klaviyo_subscribe_body()`, `blueworx_klaviyo_send_plan()`, `blueworx_klaviyo_lists()`, `blueworx_klaviyo_test_connection()`.

- [x] PHP test: key formats, mask, profile attributes (fields vs properties, fill, guard, empty values), event body (value/currency/time), subscribe body, retryable statuses.
- [x] Implement; run `php tests/php/klaviyo-test.php`.

### Task 2: Queue and public API

**Files:** `includes/klaviyo/queue.php`, `includes/klaviyo/api.php`
**Produces:** `blueworx_klaviyo_queue_job( $key, $job )`, `blueworx_klaviyo_run_queue()`, `blueworx_klaviyo_log()`, `blueworx_klaviyo_log_lines()`, the three public functions, `blueworx_klaviyo_send( $plan, $source, $key )`, `blueworx_klaviyo_normalize_plan()`, filters as above.

- [x] PHP test: the three functions queue the right plan; filter adds and blocks; off and no-key paths; send_plan sequence with a stubbed transport.
- [x] Implement; run the PHP test.

### Task 3: Screens, wiring, guide

**Files:** `includes/klaviyo/screen.php`; `includes/bootstrap.php`, `includes/features.php`, `includes/admin-settings.php`, `includes/admin-assets.php`, `includes/guides.php`, `includes/theme-lifecycle.php`, `tests/helpers.js`, `package.json`.

- [x] Playwright spec `tests/klaviyo.spec.js` with `tests/fixtures/klaviyo.php` and `tests/fixtures/klaviyo-double.php`: screen, sends, retries, off.
- [x] Implement; run the spec on the local harness.

### Task 4: Docs and release

- [x] `docs/klaviyo-api.md`, changelog 2.8.0, version bump, this plan.

## Decisions taken during the build

- Protected properties are moved from "write every time" into "write only when empty" rather than dropped, so a site plugin's first write still lands.
- A plan that only carries an event still imports the profile by email first, so Klaviyo has a profile to attach it to.
- The event's currency is whatever the caller passes; the store work (#36) decides how to read SureCart's. Nothing is hard-coded.
- The spec's retry test loops `due` + `drain` until settled rather than asserting attempt counts, because the CLI's `spawn_cron()` can legitimately run the queue in the background between fixture calls.
