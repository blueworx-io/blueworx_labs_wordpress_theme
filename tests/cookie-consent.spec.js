/**
 * Cookie consent through SureCookie.
 *
 * Switching the function on installs SureCookie from wordpress.org and sets it
 * up; the tracking codes are then held back until the visitor agrees, and run
 * the moment they do, with no reload. Switching it off leaves no banner and
 * the codes loading as before.
 *
 * The install is the real thing: this downloads SureCookie from wordpress.org
 * (about 7MB) the first time it runs on a site.
 */

import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  cacheBust,
  setFeature,
  featureIsOn,
  saveEnhancements,
  restoreAll,
} from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const TRACKING_PATH = '/wp-admin/admin.php?page=blueworx-tracking';
const CONSENT_PATH = '/wp-admin/admin.php?page=blueworx-consent';
const GA_ID = 'G-CONSENT12';

async function switchFeature(page, feature, on) {
  await page.goto(SETTINGS_PATH);
  await setFeature(page, feature, on);
  await saveEnhancements(page);
}

/**
 * Saves Enhancements when the save itself does the SureCookie install, which
 * downloads from wordpress.org inside the request and takes longer than the
 * suite's usual budget for one click.
 */
async function switchConsent(page, on) {
  await page.goto(SETTINGS_PATH);
  await setFeature(page, 'cookie_consent', on);
  await page.getByRole('button', { name: 'Save Changes' }).click({ timeout: 300_000 });
  await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved', { timeout: 300_000 });
}

async function readIds(page) {
  await page.goto(TRACKING_PATH);
  return {
    gtm: await page.inputValue('#blueworx_tracking_gtm'),
    ga: await page.inputValue('#blueworx_tracking_ga'),
    meta: await page.inputValue('#blueworx_tracking_meta'),
  };
}

async function saveIds(page, ids) {
  await page.goto(TRACKING_PATH);
  await page.fill('#blueworx_tracking_gtm', ids.gtm);
  await page.fill('#blueworx_tracking_ga', ids.ga);
  await page.fill('#blueworx_tracking_meta', ids.meta);
  await page.locator('.bw-savebar button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');
}

async function homeHtml(page) {
  return (await page.request.get(cacheBust('/'))).text();
}

test.describe('Cookie consent', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('switching on installs SureCookie, holds the tracking codes back until the visitor agrees, and switching off undoes it', async ({ page, context }) => {
    // The install downloads from wordpress.org inside the save request.
    test.setTimeout(420_000);
    page.setDefaultNavigationTimeout(300_000);

    await login(page);
    await page.goto(SETTINGS_PATH);
    const trackingWasOn = await featureIsOn(page, 'tracking_codes');
    const consentWasOn = await featureIsOn(page, 'cookie_consent');
    let idsBefore = null;

    try {
      if (!trackingWasOn) await switchFeature(page, 'tracking_codes', true);
      idsBefore = await readIds(page);
      await saveIds(page, { gtm: '', ga: GA_ID, meta: '' });

      // Before: the Analytics script runs for everyone.
      let html = await homeHtml(page);
      expect(html).toContain(`<script async src="https://www.googletagmanager.com/gtag/js?id=${GA_ID}">`);

      if (!consentWasOn) await switchConsent(page, true);

      // The screen says it worked.
      await page.goto(CONSENT_PATH);
      await expect(page.locator('.bw-notice--success')).toContainText('SureCookie is installed and running');
      // SureCookie's own menu is gone; the theme's screen is in the menu.
      await expect(page.locator('#adminmenu a[href="admin.php?page=surecookie"]')).toHaveCount(0);
      await expect(page.locator('#adminmenu a[href="admin.php?page=blueworx-consent"]')).toHaveCount(1);

      // The banner's words save through the theme's screen.
      await page.fill('#blueworx_consent_message_heading', 'Cookies on this site');
      await page.locator('.bw-savebar button[type="submit"]').click();
      await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
      await expect(page.locator('#blueworx_consent_message_heading')).toHaveValue('Cookies on this site');

      // Held back: the script is text the browser does not run, in
      // SureCookie's shape, and the no-script fallbacks are gone.
      html = await homeHtml(page);
      expect(html).toContain('id="surecookie-public-root"');
      expect(html).toContain(
        `<script type="text/plain" data-surecookie-category="analytics" data-surecookie-name="Google Analytics" data-surecookie-original-type="none" data-surecookie-src="https://www.googletagmanager.com/gtag/js?id=${GA_ID}" async>`
      );
      expect(html).not.toContain(`<script async src="https://www.googletagmanager.com/gtag/js?id=${GA_ID}">`);
      // SureCookie's own settings payload names Tag Manager's addresses, so
      // look for the fallback frame itself, not the address.
      expect(html).not.toContain('<noscript><iframe src="https://www.googletagmanager.com/ns.html');

      // A visitor: no Analytics until they agree, then it loads at once.
      await context.clearCookies();
      await page.goto(cacheBust('/'));
      await expect(page.locator('#surecookie-banner-heading')).toContainText('Cookies on this site');
      await expect(page.locator(`script[src*="gtag/js?id=${GA_ID}"]`)).toHaveCount(0);
      await page.getByRole('button', { name: 'Accept All' }).click();
      await expect(page.locator(`script[src*="gtag/js?id=${GA_ID}"]:not([type="text/plain"])`)).toHaveCount(1);
      const cookies = await context.cookies();
      expect(cookies.some((c) => c.name === 'surecookie_user_consent')).toBe(true);

      // Off again: no banner, and the script runs for everyone as before.
      await login(page);
      await switchConsent(page, false);
      html = await homeHtml(page);
      expect(html).not.toContain('id="surecookie-public-root"');
      expect(html).toContain(`<script async src="https://www.googletagmanager.com/gtag/js?id=${GA_ID}">`);
    } finally {
      await restoreAll([
        [
          'cookie consent',
          async () => {
            await login(page);
            await page.goto(SETTINGS_PATH);
            if ((await featureIsOn(page, 'cookie_consent')) !== consentWasOn) {
              await switchConsent(page, consentWasOn);
            }
          },
        ],
        ['tracking ids', async () => { if (idsBefore) await saveIds(page, idsBefore); }],
        ['tracking codes', async () => { if (!trackingWasOn) await switchFeature(page, 'tracking_codes', false); }],
      ]);
    }
  });
});
