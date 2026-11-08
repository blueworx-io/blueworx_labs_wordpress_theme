/**
 * The page frame: the header and footer around every public page.
 *
 * With Site styles on, every page the theme draws gets the same header and
 * footer, on a desktop and on a phone. With it off, nothing is drawn, so a
 * site that has not chosen the theme's look sees no change. The store's own
 * pages serve their own document and never get the frame.
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
const PAGE = '/sample-page/';
const CHECKOUT = '/checkout-fixture/';
const DESKTOP = { width: 1440, height: 900 };
const PHONE = { width: 390, height: 844 };

async function switchSiteStyles(page, on) {
  await page.goto(SETTINGS_PATH);
  await setFeature(page, 'site_styles', on);
  await saveEnhancements(page);
}

test.describe('The page frame', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('every page gets the header and footer, on a desktop and on a phone', async ({ page, context }) => {
    test.setTimeout(240_000);
    await login(page);
    await page.goto(SETTINGS_PATH);
    const wasOn = await featureIsOn(page, 'site_styles');

    try {
      if (!wasOn) await switchSiteStyles(page, true);

      // Logged out, so the admin bar is not in the way of the sticky header.
      await context.clearCookies();
      await page.setViewportSize(DESKTOP);
      await page.goto(cacheBust(PAGE));

      await expect(page.locator('body')).toHaveClass(/blueworx-frame/);
      await expect(page.locator('.blueworx-skip')).toHaveAttribute('href', '#content');

      const header = page.locator('header.blueworx-header');
      await expect(header).toBeVisible();
      await expect(header.locator('.blueworx-brand')).toHaveAttribute('rel', 'home');
      const home = new URL('/', page.url()).href;
      await expect(header.locator('.blueworx-brand')).toHaveAttribute('href', home);

      // The menu: the site has none set, so its pages stand in, with the page
      // being read marked as current.
      const nav = header.locator('.blueworx-header__nav .blueworx-nav');
      await expect(nav).toBeVisible();
      await expect(nav.locator('.current-menu-item > a')).toHaveText('Sample Page');
      await expect(nav.locator('.current-menu-item > a')).toHaveAttribute('aria-current', 'page');
      // The store's pages are never offered as places to browse to.
      await expect(nav.locator('a[href*="checkout-fixture"]')).toHaveCount(0);

      // The phone menu is for phones.
      await expect(header.locator('.blueworx-menu')).toBeHidden();

      // The header is sticky at the top, and the footer carries the copyright.
      const box = await header.boundingBox();
      expect(box.y).toBeCloseTo(0, 0);
      const footer = page.locator('footer.blueworx-footer');
      await expect(footer).toBeVisible();
      await expect(footer.locator('.blueworx-footer__copyright')).toHaveText(
        new RegExp(`© ${new Date().getFullYear()} .+`)
      );
      await expect(footer.locator('.blueworx-footer__menu .blueworx-nav a', { hasText: 'Sample Page' })).toHaveCount(1);

      // A phone: the bar's menu folds into a drawer behind a button, which
      // opens, lists the same pages, and closes on Escape.
      await page.setViewportSize(PHONE);
      await page.goto(cacheBust(PAGE));
      await expect(page.locator('.blueworx-header__nav')).toBeHidden();
      const button = page.locator('.blueworx-menu__button');
      await expect(button).toBeVisible();
      await expect(button).toHaveAttribute('aria-expanded', 'false');
      await button.click();
      await expect(button).toHaveAttribute('aria-expanded', 'true');
      const drawer = page.locator('.blueworx-menu__drawer');
      await expect(drawer).toBeVisible();
      await expect(drawer.locator('.blueworx-nav a', { hasText: 'Sample Page' })).toBeVisible();
      await page.keyboard.press('Escape');
      await expect(drawer).toBeHidden();
      await expect(button).toHaveAttribute('aria-expanded', 'false');

      // Nothing pushes a phone sideways.
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth
      );
      expect(overflow).toBeLessThanOrEqual(0);

      // The checkout serves its own document: no frame around a payment page.
      await page.goto(cacheBust(CHECKOUT));
      await expect(page.locator('header.blueworx-header')).toHaveCount(0);
      await expect(page.locator('footer.blueworx-footer')).toHaveCount(0);
    } finally {
      await restoreAll([
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

  test('with Site styles off, no header or footer is drawn', async ({ page, context }) => {
    test.setTimeout(240_000);
    await login(page);
    await page.goto(SETTINGS_PATH);
    const wasOn = await featureIsOn(page, 'site_styles');

    try {
      if (wasOn) await switchSiteStyles(page, false);

      await context.clearCookies();
      await page.goto(cacheBust(PAGE));
      await expect(page.locator('main#content')).toBeVisible();
      await expect(page.locator('header.blueworx-header')).toHaveCount(0);
      await expect(page.locator('footer.blueworx-footer')).toHaveCount(0);
      await expect(page.locator('.blueworx-skip')).toHaveCount(0);
      await expect(page.locator('body')).not.toHaveClass(/blueworx-frame/);
      await expect(page.locator('link[href*="frame.css"]')).toHaveCount(0);
    } finally {
      await restoreAll([
        [
          'site styles',
          async () => {
            if (wasOn) {
              await login(page);
              await switchSiteStyles(page, true);
            }
          },
        ],
      ]);
    }
  });
});
