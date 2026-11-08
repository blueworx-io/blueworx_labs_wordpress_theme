# Klaviyo follow-ups Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Five additions to the Klaviyo connection, each its own stacked PR: Started Checkout (#43), WordPress user sync (#44), failure emails (#48), per-page events (#46), product feed (#47).

**Architecture:** Each is one new file under `includes/klaviyo/`, hooked into the connection through the filters the first four PRs left (`blueworx_klaviyo_screen_cards`, `blueworx_klaviyo_save_settings`, `blueworx_klaviyo_sources`, `blueworx_klaviyo_test_connection`, `blueworx_klaviyo_page_events`, `blueworx_klaviyo_resolve_job`, `blueworx_klaviyo_sent`). Everything sends through `blueworx_klaviyo_send()`, so the plan filter and the queue apply as before. Pure plan builders get a PHP check; the route only a real site can show gets a Playwright spec against the test double.

**Tech Stack:** PHP 7.4+, WordPress hooks, plain JS on the block editor's React (no build), Playwright, the existing `tests/fixtures/klaviyo.php` CLI and `klaviyo-double.php` mu-plugin.

**Spec:** GitHub issues #43, #44, #46, #47, #48 under #21. #45 (consent) waits for #4.

## Global Constraints

- Version bump + CHANGELOG entry per PR: 2.12.0 checkout, 2.13.0 users, 2.14.0 alerts, 2.15.0 page events, 2.16.0 feed. Four places: style.css, package.json, readme.txt stable tag, BLUEWORX_LABS_VERSION.
- No new dependencies. PHPCS clean (phpcs:ignore only with a reason). Run lint once at the end.
- Nothing names a client. Every send passes `blueworx_klaviyo_plan`.

## Review Focus

- A checkout updated many times a minute must not flood the queue: a repeat with the same contents is a `repeat`, never a second job.
- A user sync must never put a list on a plan; lists stay opt-in.
- The failure email must be one per hour however many jobs fail, and the key-refused email once until the key works again.
- The page-event panel must not send for administrators or on admin screens: it rides on the tracking script, which already refuses both.
- The feed must answer 404, not 401, to a wrong key, and never query SureCart on the request.

---

### Task 1: Started Checkout (#43) — `includes/klaviyo/checkout.php`

**Interfaces:** `blueworx_klaviyo_checkout_plan( array $checkout, array $settings, string $page_url ): ?array`; `blueworx_klaviyo_checkout_noticed( array|object $checkout ): string` (queued|repeat|skipped|test_mode|off); `rest_post_dispatch` filter on `/surecart/v1/checkouts…` non-GET; filter `blueworx_klaviyo_checkout_url( $url, $checkout )`.

- [ ] PHP check `tests/php/klaviyo-checkout-test.php`: plan from a draft checkout with two items (value, currency from the price when the checkout has none, Items, ItemNames, CheckoutURL carries checkout_id, unique_id changes when quantity changes, null without email, null when status is paid).
- [ ] Fixture `checkout <json>` → prints the noticed result. Spec `tests/klaviyo-checkout.spec.js`: one event after a draft with email; repeat → `repeat` and one event; changed quantity → second event; no email → `skipped`; test order → `test_mode` until the store box is ticked.
- [ ] Source line "Abandoned checkouts"; docs section; CHANGELOG 2.12.0.

### Task 2: Website users (#44) — `includes/klaviyo/users.php`

**Interfaces:** `blueworx_klaviyo_user_plan( $user, string $reason ): ?array`; `blueworx_klaviyo_user_excluded_roles(): string[]` (filter `blueworx_klaviyo_user_excluded_roles`, default administrator); `blueworx_klaviyo_user_changed( int $user_id, string $reason )`; hooks user_register, wp_login, profile_update, set_user_role, add_user_role, remove_user_role; resolver `users-batch` with data {offset}; card with "Send every user now" button handled in the save filter; setting `users_last_sync`.

- [ ] PHP check: plan has first/last name, external_id, site_role, account_created; login adds last_login; admin → null; filter can exclude a role.
- [ ] Fixture `user <json>` (wp_insert_user; returns id), `login <user_id>` (do_action wp_login), `role <json>`; spec: register → profile; admin → nothing; button → batch → every non-admin profile present.
- [ ] Source line; docs; CHANGELOG 2.13.0.

### Task 3: Failure emails (#48) — `includes/klaviyo/alerts.php`

**Interfaces:** queue fires `blueworx_klaviyo_gave_up( $label, $email, $detail )` and `blueworx_klaviyo_key_refused( $detail )`; client's `$failed` carries `status`. `blueworx_klaviyo_alert_should_send( array $pending, int $last_sent, int $now ): bool`; `blueworx_klaviyo_alert_message( array $pending, string $log_url ): array{subject, body}`; setting `alert_email` (default admin_email), filter `blueworx_klaviyo_alert_recipients`; `last_sent_at` recorded on `blueworx_klaviyo_sent`; card "Alerts" with the email box and "Last successful send".

- [ ] PHP check: should_send false under an hour, true after; message lists each failure once; key-refused flag clears on a send.
- [ ] Double records `wp_mail` via `pre_wp_mail` into `blueworx_klaviyo_double_mail_*`; fixture `mail` prints them. Spec: fail 99 + track → drain to failure → one mail; ten jobs → still one; key refused (double answers 401 when `blueworx_klaviyo_double_refuse` set) → one mail; fix → sends resume, no more mail.
- [ ] CHANGELOG 2.14.0; docs.

### Task 4: Page events (#46) — `includes/klaviyo/page-events.php`, `assets/js/klaviyo-page-event.js`

**Interfaces:** meta `_blueworx_klaviyo_event` (string, REST, auth edit_post) on every public post type; filter adds `{name: meta, properties: {PageTitle, URL, PostType}}` to `blueworx_klaviyo_page_events`; editor panel `blueworx-klaviyo-event` with toggle + text, only when the function is on.

- [ ] PHP check: event added for a page with meta, none without, name trimmed.
- [ ] Fixture `pageevent <json>` {post_id, name}; spec: public page config carries the event; editor panel saves the name (openEditor pattern from page-lock.spec.js).
- [ ] CHANGELOG 2.15.0; docs.

### Task 5: Product feed (#47) — `includes/klaviyo/feed.php`

**Interfaces:** setting `feed_secret` (made on first screen view); GET `blueworx/v1/klaviyo/feed?key=`; `blueworx_klaviyo_feed_items(): array` from published `sc_product` through `blueworx_klaviyo_viewed_product_details()` + stock, filter `blueworx_klaviyo_feed_items`; cache option `feed_cache` {built, items} rebuilt on tick when older than an hour and on `save_post_sc_product`; card with address, item count, built time, "Make a new address" checkbox.

- [ ] PHP check: item shape ($id, $title, $link, $image_link, $description, $price, currency, $inventory_policy), wrong key 404.
- [ ] Spec: feed with key lists Test racket at 129.5 AUD; wrong key 404; regenerate → old 404.
- [ ] CHANGELOG 2.16.0; docs.
