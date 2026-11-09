/**
 * Klaviyo waits for cookie consent.
 *
 * With Cookie consent on, Klaviyo's script and the page's calls to it are
 * held back until the visitor agrees to marketing cookies, start the moment
 * they do, and stop, with Klaviyo's cookie cleared, if they withdraw.
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

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
  restoreAll,
} from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const SCRIPT = 'https://static.klaviyo.com/onsite/js/AbC123/klaviyo.js';

function fixture(command, arg) {
  const args = [path.join(__dirname, 'fixtures', 'klaviyo.php'), path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php'), command];
  if (arg !== undefined) args.push(String(arg));
  const out = execFileSync('php', args, { encoding: 'utf8' }).trim();
  return JSON.parse(out.split('\n').pop());
}

/** Saves Enhancements with the budget the SureCookie install needs. */
async function switchConsent(page, on) {
  await page.goto(SETTINGS_PATH);
  await setFeature(page, 'cookie_consent', on);
  await page.getByRole('button', { name: 'Save Changes' }).click({ timeout: 300_000 });
  await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved', { timeout: 300_000 });
}

test.describe('Klaviyo and cookie consent', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the script waits for marketing consent, starts on it, and stops on withdrawal', async ({ page, context }) => {
    test.setTimeout(420_000);
    page.setDefaultNavigationTimeout(300_000);

    fixture('setup');
    await login(page);
    await page.goto(SETTINGS_PATH);
    const consentWasOn = await featureIsOn(page, 'cookie_consent');

    try {
      if (!consentWasOn) await switchConsent(page, true);

      // Held back, in SureCookie's shape, with the address moved aside.
      await context.clearCookies();
      const html = await (await page.request.get(cacheBust('/'))).text();
      expect(html).toContain(`data-surecookie-category="marketing" data-surecookie-name="Klaviyo" data-surecookie-original-type="none" data-surecookie-src="${SCRIPT}"`);
      // A leading space: the address is still on the page, but only as data-surecookie-src.
      expect(html).not.toContain(` src="${SCRIPT}"`);
      expect(html).toMatch(/<script type="text\/plain" data-surecookie-category="marketing" data-surecookie-name="Klaviyo"[^>]*data-surecookie-src="[^"]*klaviyo-tracking\.js/);

      // Klaviyo's own script never leaves the machine in a test.
      await page.route('**/static.klaviyo.com/**', (route) =>
        route.fulfill({ status: 200, contentType: 'application/javascript', body: 'window.klaviyoStubLoaded = true;' })
      );

      await page.goto(cacheBust('/'));
      await expect(page.locator('#surecookie-public-root')).toBeAttached();
      await expect(page.locator(`script[src="${SCRIPT}"]`)).toHaveCount(0);
      expect(await page.evaluate(() => window.klaviyoStubLoaded === true)).toBe(false);

      // Agreeing starts it at once, with no reload.
      await page.getByRole('button', { name: 'Accept All' }).click();
      await expect(page.locator(`script[src="${SCRIPT}"]:not([type="text/plain"])`)).toHaveCount(1);
      await expect.poll(() => page.evaluate(() => window.klaviyoStubLoaded === true)).toBe(true);
      await expect(page.locator('script[src*="klaviyo-tracking.js"]:not([type="text/plain"])')).toHaveCount(1);

      // The page's own script has run once window.klaviyo, its stand-in, exists.
      await expect.poll(() => page.evaluate(() => typeof window.klaviyo !== 'undefined')).toBe(true);

      // Withdrawing clears Klaviyo's cookie and stops the page's calls.
      await page.evaluate(() => {
        document.cookie = '__kla_id=abc; path=/';
      });
      expect(await page.evaluate(() => document.cookie.includes('__kla_id='))).toBe(true);
      await page.evaluate(() => window.surecookieManager.clearConsent());
      await expect.poll(() => page.evaluate(() => window.blueworxKlaviyoStopped === true)).toBe(true);
      expect(await page.evaluate(() => document.cookie.includes('__kla_id='))).toBe(false);
    } finally {
      await restoreAll([
        [
          'cookie consent',
          async () => {
            await login(page);
            await page.goto(SETTINGS_PATH);
            if ((await featureIsOn(page, 'cookie_consent')) !== consentWasOn) await switchConsent(page, consentWasOn);
          },
        ],
        ['klaviyo fixture', async () => fixture('cleanup')],
      ]);
    }
  });
});
