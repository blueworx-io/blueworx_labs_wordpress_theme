# Klaviyo store Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every SureCart site sends its orders and subscriptions to Klaviyo the same way, with nothing in the theme about any one client's products.

**Architecture:** One file, `includes/klaviyo/store.php`, on the connection (#35). A webhook of the theme's own (`/blueworx/v1/klaviyo/surecart`, HMAC-signed, replay-guarded) checks each delivery, marks it seen and queues a job with the `surecart` resolver; the queue's background run then looks the records up through `blueworx_klaviyo_surecart_record()` (SureCart's models, or the filter a test double answers) and turns them into plans with pure builders, one per event. The currency comes from the record, then the store's account, never a constant. The SureCart card on the Klaviyo screen holds the webhook address, the secret, the Members list, the checkout box name and the test-orders switch, saved through the screen's filter.

**Tech Stack:** PHP, WP REST, Playwright, plain-PHP tests, the Klaviyo test double serving seeded SureCart records.

**Spec:** blueworx_labs_wordpress_theme#36 (part of #21).

## Global Constraints

- Only money actually paid is `value`; refunds and amounts owed are properties.
- Each SureCart event is accepted once (`blueworx_klaviyo_seen_` markers, pruned after 30 days); each Klaviyo event has a unique id built from the record.
- Nothing names a client, a product or a date. The PHP test asserts the word "founding" never appears in a plan.
- Version 2.11.0; changelog in plain words.

## Review Focus

- A purchase with no subscription must be Placed Order, not dropped: PHP test and spec.
- A renewal SureCart has not yet confirmed as paid must wait, not send: PHP test.
- One cancellation must be one event, with only a status update when access ends: spec.
- A wrong secret must refuse the delivery and show on the screen without writing to the log: spec.
- A zero-decimal currency must not be divided by 100: PHP test.

---

### Task 1: Module

**Files:** `includes/klaviyo/store.php`, `includes/klaviyo/api.php` (plans carry `after`), `includes/bootstrap.php`

- [x] PHP test `tests/php/klaviyo-store-test.php`: helpers, every plan builder, working out an event, the signature, accepting an event, the resolver.
- [x] Implement; run the PHP test.

### Task 2: Spec and fixtures

- [x] Fixture: `records` and `store` commands; the double answers `blueworx_klaviyo_surecart_record`, `blueworx_klaviyo_payment_number` and `blueworx_klaviyo_subscription_for_order`.
- [x] Spec `tests/klaviyo-store.spec.js`: the boxes save; forged deliveries refused and flagged; a genuine one sent once; Placed Order and test orders; renewal, failure, refund and cancellation; the address goes with the switch.

### Task 3: Docs and release

- [x] Store section in `docs/klaviyo-api.md` with the PadlX founding rules as the worked example; changelog 2.11.0; version bump; this plan.

## Decisions taken during the build

- `after` (what to remember once a plan has gone through) is carried on the plan and acted on from the `blueworx_klaviyo_sent` action, so the store never writes memory for a send that failed.
- The webhook route exists whether or not SureCart is installed, so a site can set the secret before the store goes live; the card says plainly when SureCart is missing.
- SureCart itself is not on the test site (#7): the model look-ups are pinned only by their shape, and the fixture's seeded records stand in for them.
