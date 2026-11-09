/**
 * Export users.
 *
 * The PHP check covers how the spreadsheet is put together. This covers what
 * only a real site shows: the link sits last in the Users menu, the file that
 * arrives is a real workbook holding the site's users, and nobody but an
 * administrator can get it.
 */

import { Buffer } from 'node:buffer';
import { readFileSync } from 'node:fs';
import { inflateRawSync } from 'node:zlib';
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login, restoreAll, setFeature, saveEnhancements } from './helpers.js';

const EXPORT_PATH = '/wp-admin/admin-post.php?action=blueworx_export_users';
const ENHANCEMENTS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const XLSX_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

/**
 * Reads one file out of a zip.
 *
 * @param {Buffer} zip  The whole zip.
 * @param {string} name The file's path inside it.
 * @return {string} Its contents, or '' when it is not there.
 */
function unzip(zip, name) {
  let at = zip.lastIndexOf(Buffer.from([0x50, 0x4b, 0x05, 0x06]));
  const entries = zip.readUInt16LE(at + 10);
  at = zip.readUInt32LE(at + 16);

  for (let i = 0; i < entries; i++) {
    const method = zip.readUInt16LE(at + 10);
    const size = zip.readUInt32LE(at + 20);
    const nameLength = zip.readUInt16LE(at + 28);
    const skip = zip.readUInt16LE(at + 30) + zip.readUInt16LE(at + 32);
    const local = zip.readUInt32LE(at + 42);

    if (zip.toString('utf8', at + 46, at + 46 + nameLength) === name) {
      const start = local + 30 + zip.readUInt16LE(local + 26) + zip.readUInt16LE(local + 28);
      const data = zip.subarray(start, start + size);

      return (method === 0 ? data : inflateRawSync(data)).toString('utf8');
    }

    at += 46 + nameLength + skip;
  }

  return '';
}

test.describe('Export users', () => {
  test.skip(isPlaceholder || !ADMIN_USER || !ADMIN_PASS, 'No test site configured.');

  test('Export sits last in the Users menu and downloads every user as a spreadsheet', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/users.php');

    const link = page.locator('#menu-users .wp-submenu li a').last();
    await expect(link).toHaveText('Export');
    await expect(link).toHaveAttribute('href', /action=blueworx_export_users.*_wpnonce=/);

    const [download] = await Promise.all([page.waitForEvent('download'), link.click()]);
    expect(download.suggestedFilename()).toMatch(/^[a-z0-9-]+-users-\d{4}-\d{2}-\d{2}\.xlsx$/);

    const zip = readFileSync(await download.path());
    expect(unzip(zip, '[Content_Types].xml'), 'Excel finds the sheet').toContain('/xl/worksheets/sheet1.xml');
    expect(unzip(zip, 'xl/workbook.xml')).toContain('<sheet name="Users"');

    const sheet = unzip(zip, 'xl/worksheets/sheet1.xml');
    const headings = sheet.slice(0, sheet.indexOf('</row>'));

    for (const heading of ['ID', 'Username', 'Email', 'First name', 'Last name', 'Display name', 'Role', 'Registered', 'Website', 'nickname']) {
      expect(headings, `a ${heading} column`).toContain(`>${heading}</t>`);
    }

    expect(sheet, 'the administrator is in it').toContain(`>${ADMIN_USER}</t>`);
    expect(sheet, 'so is everybody else').toContain('>member</t>');
    expect(sheet, 'with their role by name').toContain('>Administrator</t>');

    for (const secret of ['session_tokens', 'capabilities', 'user_pass', '_application_passwords', '$P$', '$wp$']) {
      expect(sheet, `${secret} is never exported`).not.toContain(secret);
    }
  });

  test('without a valid link, no spreadsheet', async ({ page, browser, baseURL }) => {
    await login(page);
    const stale = await page.request.get(`${EXPORT_PATH}&_wpnonce=nope`);
    expect(stale.headers()['content-type'] || '').not.toContain(XLSX_TYPE);

    const anon = await browser.newContext();
    try {
      const response = await anon.request.get(`${baseURL}${EXPORT_PATH}`, { maxRedirects: 0 });
      expect(response.headers()['content-type'] || '').not.toContain(XLSX_TYPE);
    } finally {
      await anon.close();
    }
  });

  test('someone who is not an administrator gets no link and no spreadsheet', async ({ page }) => {
    await page.goto('/admin_login/');
    await page.fill('#user_login', 'member');
    await page.fill('#user_pass', 'wptest-member-pw');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toHaveCount(1);

    await page.goto('/wp-admin/profile.php');
    await expect(page.locator('#adminmenu a[href*="blueworx_export_users"]')).toHaveCount(0);

    const response = await page.request.get(`${EXPORT_PATH}&_wpnonce=nope`);
    expect(response.status()).toBe(403);
    expect(response.headers()['content-type'] || '').not.toContain(XLSX_TYPE);
  });

  test('switched off on Enhancements, the link and the spreadsheet both go', async ({ page }) => {
    await login(page);
    await page.goto(ENHANCEMENTS_PATH);

    try {
      await setFeature(page, 'user_export', false);
      await saveEnhancements(page);

      await page.goto('/wp-admin/users.php');
      await expect(page.locator('#adminmenu a[href*="blueworx_export_users"]')).toHaveCount(0);

      const response = await page.request.get(`${EXPORT_PATH}&_wpnonce=nope`);
      expect(response.status()).toBe(403);
    } finally {
      await restoreAll([
        [
          'switch Export users back on',
          async () => {
            await page.goto(ENHANCEMENTS_PATH);
            await setFeature(page, 'user_export', true);
            await saveEnhancements(page);
          },
        ],
      ]);
    }
  });
});
