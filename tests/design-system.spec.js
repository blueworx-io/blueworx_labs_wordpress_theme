/**
 * The site design system.
 *
 * The PHP checks cover the maths: every named style exists, follows the
 * choices and reads. This covers what only a real site shows: the named styles
 * and the chosen fonts reach every public page and the block editor, the base
 * styles follow their switch, and the team can change the design from
 * BlueWorx > Design and see the whole site follow.
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

/**
 * The home page's HTML, as a visitor's browser receives it.
 */
async function homeHtml(page) {
  const response = await page.request.get(cacheBust('/'));
  return response.text();
}

/**
 * Switches the base site styles on or off from Enhancements.
 */
async function switchSiteStyles(page, on) {
  await page.goto(SETTINGS_PATH);
  await setFeature(page, 'site_styles', on);
  await saveEnhancements(page);
}

test.describe('Site design system on public pages', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('named styles are on every public page', async ({ page }) => {
    const html = await homeHtml(page);
    const block = html.match(/<style id="blueworx-design-inline-css">([\s\S]*?)<\/style>/);

    expect(block, 'the design system style block').not.toBeNull();
    expect(block[1]).toContain('--color-primary:#2f5bea;');
    expect(block[1]).toContain('--text-h1:48px;');

    await page.goto(cacheBust('/'));
    const primary = await page.evaluate(() =>
      getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim()
    );
    expect(primary).toBe('#2f5bea');
  });

  test('fonts load from the site, not Google', async ({ page }) => {
    const html = await homeHtml(page);

    expect(html).toContain('/assets/fonts/sora-700.woff2');
    expect(html).toContain('/assets/fonts/inter-400.woff2');
    expect(html).not.toContain('fonts.googleapis.com');
  });

  test('base site styles follow their switch', async ({ page }) => {
    await login(page);
    await page.goto(SETTINGS_PATH);
    const wasOn = await featureIsOn(page, 'site_styles');

    try {
      await switchSiteStyles(page, true);
      await page.goto(cacheBust('/'));
      await expect(page.locator('link#blueworx-site-css')).toHaveCount(1);
      const font = await page.evaluate(() => getComputedStyle(document.querySelector('h1')).fontFamily);
      expect(font).toContain('Sora');

      await switchSiteStyles(page, false);
      expect(await homeHtml(page)).not.toContain('blueworx-site-css');
      // The named styles stay: plugins still read them.
      expect(await homeHtml(page)).toContain('blueworx-design-inline-css');
    } finally {
      await restoreAll([['site styles', () => switchSiteStyles(page, wasOn)]]);
    }
  });

  test('the editor uses the same colours', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/post-new.php?post_type=page');

    // The content area is a frame of its own, added once the editor has loaded.
    const wrapper = page.frameLocator('iframe[name="editor-canvas"]').locator('.editor-styles-wrapper');

    await expect(wrapper).toBeVisible({ timeout: 30_000 });
    const primary = await wrapper.evaluate((el) =>
      getComputedStyle(el).getPropertyValue('--color-primary').trim()
    );
    expect(primary).toBe('#2f5bea');
  });
});
