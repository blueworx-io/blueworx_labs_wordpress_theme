/**
 * Legal pages and the not-found page.
 *
 * Every site gets a privacy policy, a cookies page and terms, on the Legal
 * page template, linked from the footer once published; the cookies page
 * lists the cookies the site sets. A missing address gets a proper not-found
 * page with a search and a way back.
 */

import { execFileSync } from 'node:child_process';

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

function fixture(command) {
  return execFileSync('php', ['tests/fixtures/legal.php', '.wp-test/wp/wp-load.php', command], { encoding: 'utf8' })
    .trim()
    .split('\n')
    .pop();
}

async function switchSiteStyles(page, on) {
  await page.goto(SETTINGS_PATH);
  await setFeature(page, 'site_styles', on);
  await saveEnhancements(page);
}

test.describe('Legal and error pages', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the three legal pages exist, read right and reach the footer; a missing page is handled', async ({ page, context }) => {
    test.setTimeout(240_000);
    await login(page);
    await page.goto(SETTINGS_PATH);
    const wasOn = await featureIsOn(page, 'site_styles');

    try {
      if (!wasOn) await switchSiteStyles(page, true);

      const ids = JSON.parse(fixture('seed'));
      expect(Object.keys(ids).sort()).toEqual(['cookies', 'privacy', 'terms']);

      await context.clearCookies();

      // The cookies page: the Legal template, the date, and the live list.
      await page.goto(cacheBust(`/?p=${ids.cookies}`));
      await expect(page.locator('h1.blueworx-page__title')).toHaveText('Cookies');
      await expect(page.locator('.blueworx-legal__updated')).toContainText('Last updated');
      const table = page.locator('.blueworx-cookies table');
      await expect(table).toBeVisible();
      await expect(table.locator('tbody tr')).not.toHaveCount(0);
      await expect(table).toContainText('wordpress_logged_in_');

      // The terms name the site; the privacy policy is WordPress's own page.
      await page.goto(cacheBust(`/?p=${ids.terms}`));
      await expect(page.locator('h1.blueworx-page__title')).toHaveText('Terms and conditions');
      await expect(page.locator('.entry-content')).toContainText('These are the terms');

      // All three are linked from the footer, in order.
      const legal = page.locator('.blueworx-footer__legal a');
      await expect(legal).toHaveText(['Privacy Policy', 'Cookies', 'Terms and conditions']);

      // Not found: says so, offers a search and the way home, and is a 404.
      const response = await page.goto(cacheBust('/no-such-page-anywhere/'));
      expect(response.status()).toBe(404);
      await expect(page.locator('h1.blueworx-page__title')).toHaveText('Page not found');
      await expect(page.locator('.blueworx-404__search input[type="search"]')).toBeVisible();
      await expect(page.locator('.blueworx-404__links .blueworx-btn--primary')).toHaveText('Back to the home page');
      await expect(page.locator('header.blueworx-header')).toBeVisible();
    } finally {
      await restoreAll([
        ['legal pages', async () => { fixture('reset'); }],
        [
          'site styles',
          async () => {
            if (!wasOn) {
              await login(page);
              await switchSiteStyles(page, false);
            }
          },
        ],
      ]);
    }
  });
});
