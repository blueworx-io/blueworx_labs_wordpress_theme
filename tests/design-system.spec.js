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

const DESIGN_PATH = '/wp-admin/admin.php?page=blueworx-design';

const DEFAULT_LOOK = {
  primary: '#2f5bea',
  accent: '#f08a2c',
  background: '#ffffff',
  text: '#16181d',
};

/**
 * Fills the given boxes on the Design screen and saves.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @param {Object<string, string>} colors Colour boxes to fill, by setting name.
 * @param {Object<string, string>} selects Selects to set, by setting name.
 */
async function saveDesign(page, colors = {}, selects = {}) {
  await page.goto(DESIGN_PATH);
  for (const [key, value] of Object.entries(colors)) {
    await page.fill(`#blueworx_design_${key}_hex`, value);
  }
  for (const [key, value] of Object.entries(selects)) {
    await page.selectOption(`#blueworx_design_${key}`, value);
  }
  await page.locator('.bw-savebar button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');
}

/**
 * Puts the default look back.
 */
async function resetDesign(page) {
  await saveDesign(page, DEFAULT_LOOK, {
    heading_font: 'sora',
    body_font: 'inter',
    type_scale: 'standard',
    spacing: 'standard',
    corners: 'rounded',
    buttons: 'rounded',
  });
}

test.describe('BlueWorx > Design', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test.afterEach(async ({ page }) => {
    await restoreAll([['default look', () => resetDesign(page)]]);
  });

  test('the team changes the main colour and the whole site follows', async ({ page }) => {
    await page.goto(DESIGN_PATH);
    await expect(page.getByRole('heading', { name: 'Design', level: 1 })).toBeVisible();
    await expect(page.locator('#adminmenu a[href$="page=blueworx-design"]')).toHaveText('Design');

    await saveDesign(page, { primary: '#0A7D5C' });
    await expect(page.locator('.bw-notice--success')).toContainText('Saved');
    await expect(page.locator('#blueworx_design_primary_hex')).toHaveValue('#0a7d5c');

    const html = await homeHtml(page);
    expect(html).toContain('--color-primary:#0a7d5c;');
    expect(html).toContain('primary: #0a7d5c -->');
  });

  test('a hard-to-read colour is flagged but saved', async ({ page }) => {
    await saveDesign(page, { text: '#d0d0d0' });

    const warning = page.locator('.bw-notice--warning');
    await expect(warning).toContainText('Hard to read');
    await expect(warning).toContainText('Body text on the background');
    expect(await homeHtml(page)).toContain('--color-text:#d0d0d0;');
  });

  test('a bad colour never reaches the page', async ({ page }) => {
    await saveDesign(page, { primary: 'javascript:alert(1)' });

    const html = await homeHtml(page);
    expect(html).toContain('--color-primary:#2f5bea;');
    expect(html).not.toContain('javascript:alert');
  });

  test('the second colour can be left to be worked out', async ({ page }) => {
    await saveDesign(page, { accent: '' });

    await expect(page.locator('#blueworx_design_accent_hex')).toHaveValue('');
    const accent = (await homeHtml(page)).match(/--color-accent:(#[0-9a-f]{6});/);
    expect(accent).not.toBeNull();
    expect(accent[1]).not.toBe('#f08a2c');
  });

  test('a plugin preset restyles the site', async ({ page }) => {
    await page.goto(DESIGN_PATH);
    await page.selectOption('#blueworx_design_preset', 'fixture');
    await page.getByRole('button', { name: 'Use this preset' }).click();
    await page.waitForLoadState('domcontentloaded');

    await expect(page.locator('.bw-notice--success')).toContainText('Fixture preset');
    await expect(page.locator('#blueworx_design_primary_hex')).toHaveValue('#c6f24e');

    const html = await homeHtml(page);
    expect(html).toContain('--color-primary:#c6f24e;');
    expect(html).toContain('--font-heading:"Fixture Mono", monospace;');
    expect(html).toContain('--radius-md:0px;');
  });

  test('a plugin reads every named style', async ({ page }) => {
    // The contract in docs/design-system.md: 73 named styles.
    expect(await homeHtml(page)).toContain('<!-- design tokens seen: 73,');
  });

  test('site name and social links save', async ({ page }) => {
    await page.goto(DESIGN_PATH);
    const name = await page.inputValue('#blueworx_design_site_name');

    try {
      await page.fill('#blueworx_design_site_name', 'Fixture Site');
      await page.fill('#blueworx_design_social_facebook', 'https://facebook.com/fixture');
      await page.fill('#blueworx_design_social_x', 'javascript:alert(1)');
      await page.locator('.bw-savebar button[type="submit"]').click();
      await page.waitForLoadState('domcontentloaded');

      await expect(page.locator('#blueworx_design_site_name')).toHaveValue('Fixture Site');
      await expect(page.locator('#blueworx_design_social_facebook')).toHaveValue('https://facebook.com/fixture');
      await expect(page.locator('#blueworx_design_social_x')).toHaveValue('');
      expect(await homeHtml(page)).toContain('Fixture Site');
    } finally {
      await restoreAll([
        [
          'site name and links',
          async () => {
            await page.goto(DESIGN_PATH);
            await page.fill('#blueworx_design_site_name', name);
            await page.fill('#blueworx_design_social_facebook', '');
            await page.locator('.bw-savebar button[type="submit"]').click();
            await page.waitForLoadState('domcontentloaded');
          },
        ],
      ]);
    }
  });
});
