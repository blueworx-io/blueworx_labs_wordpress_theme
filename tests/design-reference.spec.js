/**
 * The design system reference page.
 *
 * Shows the site's whole design system, drawn with the site's own styles, to
 * the people who set it — and to nobody else.
 */

import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  cacheBust,
  restoreAll,
} from './helpers.js';

const REFERENCE_PATH = '/?blueworx-design-system=1';
const DESIGN_PATH = '/wp-admin/admin.php?page=blueworx-design';

const SECTIONS = [
  'overview',
  'colours',
  'typography',
  'spacing',
  'layout',
  'buttons',
  'inputs',
  'badges',
  'alerts',
  'shadows',
  'cards',
  'navigation',
  'faqs',
  'images',
  'dark',
  'icons',
  'logo',
  'usage',
  'tokens',
];

/**
 * Sets the main colour on the Design screen and saves.
 */
async function setPrimary(page, hex) {
  await page.goto(DESIGN_PATH);
  await page.fill('#blueworx_design_primary_hex', hex);
  await page.locator('.bw-savebar button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');
}

/**
 * Sets the site name on the Design screen and saves.
 */
async function setSiteName(page, name) {
  await page.goto(DESIGN_PATH);
  await page.fill('#blueworx_design_site_name', name);
  await page.locator('.bw-savebar button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');
}

test.describe('Design system reference page', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('admins see all 19 sections, in order, linked from the Design screen', async ({ page }) => {
    await login(page);
    await page.goto(DESIGN_PATH);
    const link = page.getByRole('link', { name: 'View design system' });
    await expect(link).toHaveAttribute('href', /\?blueworx-design-system=1$/);

    await page.goto(cacheBust(REFERENCE_PATH));
    const ids = await page.locator('main section[id]').evaluateAll((all) => all.map((s) => s.id));
    expect(ids).toEqual(SECTIONS);
    await expect(page.locator('#tokens pre')).toContainText('--color-primary: #2f5bea;');
    await expect(page.locator('#tokens pre')).toContainText('@media (max-width: 639px)');
  });

  test('nobody signed out sees it', async ({ browser }) => {
    const anon = await browser.newPage();
    try {
      const response = await anon.goto(cacheBust(REFERENCE_PATH));
      const html = await response.text();
      expect(html).not.toContain('id="tokens"');
      expect(html).not.toContain('design-reference');
    } finally {
      await anon.close();
    }
  });

  test('the colours follow the Design screen', async ({ page }) => {
    await login(page);

    try {
      await setPrimary(page, '#0a7d5c');
      await page.goto(cacheBust(REFERENCE_PATH));
      await expect(page.locator('#colours [data-token="--color-primary"] .bw-ref-hex')).toHaveText('#0a7d5c');
    } finally {
      await restoreAll([['main colour', () => setPrimary(page, '#2f5bea')]]);
    }
  });

  test('with no logo, the site\'s first letter stands in', async ({ page }) => {
    await login(page);
    await page.goto(cacheBust(REFERENCE_PATH));
    const name = (await page.locator('#overview h1').innerText()).trim();
    await expect(page.locator('#logo .bw-ref-mark').first()).toHaveText(name.charAt(0).toUpperCase());
  });

  test('a site name with an apostrophe and an ampersand reads as typed', async ({ page }) => {
    await login(page);
    await page.goto(DESIGN_PATH);
    const original = await page.inputValue('#blueworx_design_site_name');

    try {
      await setSiteName(page, "Bob's Bikes & Sons");
      await page.goto(cacheBust(REFERENCE_PATH));
      await expect(page.locator('#overview h1')).toHaveText("Bob's Bikes & Sons");
    } finally {
      await restoreAll([['site name', () => setSiteName(page, original)]]);
    }
  });
});

test.describe('Design system reference page, for people who cannot change the design', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('a signed-in member sees the normal site, not the design system', async ({ page }) => {
    // The member global-setup.js adds: signed in, but not allowed to change the design.
    await page.goto('/admin_login/');
    await page.fill('#user_login', 'member');
    await page.fill('#user_pass', 'wptest-member-pw');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toHaveCount(1);

    const response = await page.request.get(cacheBust(REFERENCE_PATH));
    const html = await response.text();
    expect(html).not.toContain('id="tokens"');
    expect(html).not.toContain('design-reference');
  });
});

test.describe('Design system reference page, read by a screen reader', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('one main heading, then a heading for each section', async ({ page }) => {
    await login(page);
    await page.goto(cacheBust(REFERENCE_PATH));
    await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
    await expect(page.locator('main section[id] > .bw-ref-sec__head').getByRole('heading', { level: 2 })).toHaveCount(SECTIONS.length);
  });
});
