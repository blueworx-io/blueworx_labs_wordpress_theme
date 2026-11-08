# Klaviyo site tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Klaviyo sees what people do on the site: its script on every public page, signed-in people identified, Viewed Product on SureCart product pages.

**Architecture:** One file, `includes/klaviyo/tracking.php`, on top of the connection (#35). It enqueues Klaviyo's script (`static.klaviyo.com/onsite/js/{public key}/klaviyo.js`, async) and a small plain script of the theme's own, `assets/js/klaviyo-tracking.js`, which carries Klaviyo's published stand-in object and makes the page's calls from a config the server localises: `identify` (the signed-in user), `events` (Viewed Product on `sc_product`, plus whatever `blueworx_klaviyo_page_events` adds) and `viewedItem` (Klaviyo's recently-viewed shape). Administrators are not tracked; `blueworx_klaviyo_tracking_allowed` is where cookie consent (#4) will plug in.

**Tech Stack:** PHP, plain JS (no build), Playwright, plain-PHP tests, the Klaviyo test double from #35 extended with a stand-in `sc_product` type.

**Spec:** blueworx_labs_wordpress_theme#38 (part of #21).

## Global Constraints

- No script without a public key, on admin screens, when the function is off, or for `manage_options` users.
- Product details use Klaviyo's catalogue names (`ProductName`, `ProductID`, `URL`, `ImageURL`, `Price`, `Currency`) and are read from `sc_get_product()` when SureCart is there, the post otherwise.
- Version 2.9.0; changelog in plain words.
- `assets/js/klaviyo-tracking.js` passes `eslint assets/js`; Klaviyo's stand-in snippet is kept verbatim inside an eslint-disable block.

## Review Focus

- An administrator browsing the site must leave no trace: PHP test and spec.
- A product with no price must still send Viewed Product: PHP test (no Price key, event still built).
- A signed-in person with a bad or missing email must not be identified: PHP test.
- Clearing the public key must remove the script for visitors at once: spec (cache purge on the option).
- A site plugin's page event must reach the page with the post it is on: PHP test.

---

### Task 1: Tracking module

**Files:** `includes/klaviyo/tracking.php`, `assets/js/klaviyo-tracking.js`, `includes/bootstrap.php`
**Produces:** `blueworx_klaviyo_tracking_active()`, `blueworx_klaviyo_tracking_identity( $user )`, `blueworx_klaviyo_viewed_product_details( $post_id )`, `blueworx_klaviyo_page_events()`, `blueworx_klaviyo_tracking_config( $identity, $events )`.

- [x] PHP test `tests/php/klaviyo-tracking-test.php`: active/inactive paths, identity, product details with and without SureCart, page events and filter, config shape, test-connection line.
- [x] Implement; run the PHP test.

### Task 2: Spec and fixtures

**Files:** `tests/klaviyo-tracking.spec.js`; `tests/fixtures/klaviyo.php` (`public`, `product`), `tests/fixtures/klaviyo-double.php` (stand-in `sc_product`, a price via the filter).

- [x] Spec: visitor gets the script and Viewed Product; member identified, admin not; no key or off means no script.
- [x] Run on the local harness.

### Task 3: Docs and release

- [x] Tracking section in `docs/klaviyo-api.md`, changelog 2.9.0, version bump, this plan.

## Decisions taken during the build

- The SureCart read uses `sc_get_product()` and the product's `metrics->min_price_amount` and `metrics->currency`, defensively; SureCart is not on the test site (#7), so that path is covered by the PHP test's stand-in object only.
- The featured image wins over SureCart's `image_url` when both exist, since it is what the site chose to show.
- Klaviyo's stand-in object is shipped inside the theme's own script rather than as a separate inline block, so there is one asset to cache and one place it lives.
