/**
 * The design system download.
 *
 * The PHP check covers what goes in the zip. This covers what only a real site
 * shows: the buttons are there, the zip arrives with the right name and the
 * right files in it, and nobody without the right to it gets one.
 */

import { readFileSync } from 'node:fs';
import { test, expect, isPlaceholder, baseURL, ADMIN_USER, ADMIN_PASS, login, cacheBust } from './helpers.js';

const REFERENCE_PATH = '/?blueworx-design-system=1';
const DESIGN_PATH = '/wp-admin/admin.php?page=blueworx-design';
const DOWNLOAD_PATH = '/wp-admin/admin-post.php?action=blueworx_download_design';

test.describe('Design system download', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the design system page hands over a zip of the whole system', async ({ page }) => {
    await login(page);
    await page.goto(cacheBust(REFERENCE_PATH));

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('link', { name: 'Download design system' }).click(),
    ]);

    expect(download.suggestedFilename()).toMatch(/^[a-z0-9-]+-design-system\.zip$/);

    // A zip keeps each entry's name as plain text, so the names can be read
    // straight off the bytes without unpacking it.
    const bytes = readFileSync(await download.path()).toString('latin1');
    expect(bytes.startsWith('PK')).toBe(true);
    for (const entry of ['tokens/tokens.css', 'tokens/tokens.json', 'tokens/colours.json', 'fonts/sora-700.woff2', 'fonts/fonts.css', 'README.md', 'logos/LOGO-USAGE.md']) {
      expect(bytes, entry).toContain(`-design-system/${entry}`);
    }
  });

  test('the Design screen offers it too', async ({ page }) => {
    await login(page);
    await page.goto(DESIGN_PATH);
    await expect(page.getByRole('link', { name: 'Download design system' })).toHaveAttribute(
      'href',
      /action=blueworx_download_design.*_wpnonce=/
    );
  });

  test('without a valid link, no zip', async ({ page, browser }) => {
    await login(page);
    const stale = await page.request.get(`${DOWNLOAD_PATH}&_wpnonce=nope`);
    expect(stale.headers()['content-type'] || '').not.toContain('application/zip');

    const anon = await browser.newContext();
    try {
      const response = await anon.request.get(`${baseURL}${DOWNLOAD_PATH}`, { maxRedirects: 0 });
      expect(response.headers()['content-type'] || '').not.toContain('application/zip');
    } finally {
      await anon.close();
    }
  });
});

test.describe('Design system download, kept to the people who set the design', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('a member with a valid link of their own still gets no zip', async ({ page }) => {
    await page.goto('/admin_login/');
    await page.fill('#user_login', 'member');
    await page.fill('#user_pass', 'wptest-member-pw');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toHaveCount(1);

    const home = await (await page.request.get(cacheBust('/'))).text();
    const nonce = home.match(/design download nonce: ([0-9a-f]+)/);
    expect(nonce, 'the test plugin hands out a nonce').not.toBeNull();

    const response = await page.request.get(`${DOWNLOAD_PATH}&_wpnonce=${nonce[1]}`);
    expect(response.status()).toBe(403);
    expect(response.headers()['content-type'] || '').not.toContain('application/zip');
  });

  test('stray output from elsewhere on the site does not spoil the zip', async ({ page }) => {
    await login(page);
    await page.goto(DESIGN_PATH);
    const href = await page.getByRole('link', { name: 'Download design system' }).getAttribute('href');

    const response = await page.request.get(`${href.replace(/&amp;/g, '&')}&bw_noise=1`);
    const body = await response.body();
    expect(response.headers()['content-type']).toContain('application/zip');
    expect(body.subarray(0, 2).toString('latin1')).toBe('PK');
    expect(Number(response.headers()['content-length'])).toBe(body.length);
  });
});
