# Klaviyo forms Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Any form on the site can send its sign-ups to a Klaviyo list, set up from the Klaviyo screen with no code.

**Architecture:** One file, `includes/klaviyo/forms.php`, on the connection (#35). Connections live in one option (`blueworx_klaviyo_forms`): kind, match, list, consent, tag. Three adapters turn a submission into one common shape (fields with name, label, value, checked) and hand it to `blueworx_klaviyo_form_submitted()`: Elementor (`elementor_pro/forms/new_record`), SureForms (`srfm_submission_accepted`), and plain HTML forms through a public REST route fed by `assets/js/klaviyo-forms.js`. A pure matcher finds the email, names and marketing answer by label or name; a pure builder makes the plan (fill names, set signup properties, Submitted Form event, list only when consented). The Website forms card is a repeater on the Klaviyo screen with `assets/js/klaviyo-forms-admin.js` adding and removing rows.

**Tech Stack:** PHP, plain JS (no build), WP REST, Playwright, plain-PHP tests, the Klaviyo test double with a stand-in Elementor record.

**Spec:** blueworx_labs_wordpress_theme#37 (part of #21).

## Global Constraints

- Nothing waits on Klaviyo; every submission only queues through `blueworx_klaviyo_send( $plan, 'forms' )`.
- Unticked marketing box: identified, `marketing_consent` false, never subscribed. No box: the submission is the consent.
- The REST route exists only while an HTML form is connected, accepts only connected form ids, and is rate limited to 20 a minute per address.
- `signup_consent_wording`, never `consent_text` (Klaviyo drops that name).
- Version 2.10.0; changelog in plain words.

## Review Focus

- A form with a single "Your name" box must still give a first and last name: PHP test.
- A field whose label does not say email but holds one must still be found: PHP test.
- A wrong list ID must refuse the save and keep the previous connections: PHP test (clean) and the screen's notice.
- Removing one connection must leave the others sending: spec.
- A form nobody connected must be refused at the REST door with 404: spec.

---

### Task 1: Module

**Files:** `includes/klaviyo/forms.php`, `assets/js/klaviyo-forms.js`, `assets/js/klaviyo-forms-admin.js`, `includes/bootstrap.php`
**Produces:** `blueworx_klaviyo_form_connections()`, `blueworx_klaviyo_clean_connections( $posted )`, `blueworx_klaviyo_form_values( $fields )`, `blueworx_klaviyo_form_plan( $connection, $values, $page )`, `blueworx_klaviyo_form_submitted( $kind, $match, $fields, $page )`, `blueworx_klaviyo_sureforms_fields( $data )`.

- [x] PHP test `tests/php/klaviyo-forms-test.php`.
- [x] Implement; run the PHP test.

### Task 2: Spec and fixtures

- [x] Fixture: `forms`, `elementor`, `sureforms`, `html` commands; a stand-in Elementor record in the double.
- [x] Spec `tests/klaviyo-forms.spec.js`: connect two forms from the screen, submissions land, unticked never subscribed, removal stops one form only.

### Task 3: Docs and release

- [x] Forms section in `docs/klaviyo-api.md`, changelog 2.10.0, version bump, this plan.

## Decisions taken during the build

- The HTML catcher uses `sendBeacon` so the copy survives the form's own navigation, and the route answers 202 with nothing a spammer could learn.
- The list picker is a dropdown of the account's lists when the key works, and a plain box otherwise, so the screen still works before the key is in.
- Other form plugins (Contact Form 7, WPForms, Gravity) are not adapted yet; a plugin can call `blueworx_klaviyo_form_submitted()` with the `html` kind for any of them.
