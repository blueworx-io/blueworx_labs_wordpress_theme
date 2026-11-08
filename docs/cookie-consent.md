# Cookie consent

The theme handles cookie consent through the free SureCookie plugin and
writes no consent code of its own.

**Switching it on** (Enhancements › Analytics & Tracking › Cookie consent)
installs SureCookie from wordpress.org, switches it on and applies the house
defaults: ask first (opt-in), block tracking until the visitor agrees, keep a
consent log, scanner off, banner linking to the site's cookies and privacy
pages. SureCookie's own menu is removed; its screens stay reachable from a
link on the theme's Cookie consent screen for people who may manage options
(super admins on a network). If the install cannot happen, the screen says
why and offers the manual route.

**The Cookie consent screen** holds the settings a site owner changes: the
banner's heading and message, where it sits, and whether a small button lets
visitors change their choice later. Everything else stays on the defaults.

**Switching it off** deactivates SureCookie. Its settings and consent log
stay in the database until the plugin is deleted, so switching back on is
instant.

## Tracking codes and consent

With consent in force, each tracking code is written to the page the way
SureCookie blocks a script (`type="text/plain"` with
`data-surecookie-category`), so the browser holds it and SureCookie runs it
the moment the visitor agrees, with no reload. Tag Manager and Analytics
wait for analytics consent, the Meta pixel for marketing consent. The
no-script fallbacks are not printed, because nothing could hold them back.

For a plugin's own scripts:

```php
// The opening tag, held back until marketing consent while consent is on.
$tag = apply_filters( 'blueworx_tracking_script_tag', '<script async src="https://example.com/t.js">', 'marketing', 'Example' );
```

`blueworx_consent_active()` says whether consent is in force;
`blueworx_consent_full_access` filters who may open SureCookie's own screens;
`blueworx_cookie_declarations` lists SureCookie's own cookie on the cookies
page while it runs.

## Decided during the build

SureCookie's cookie scanner sends the site's page addresses to SureCookie's
service once a month. It is left off by default. A site that wants it turns
it on from SureCookie's own screens.
