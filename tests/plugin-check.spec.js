/**
 * BlueWorx > Plugin check.
 *
 * The PHP check covers the matching and the list's upkeep. This covers what
 * only a real site shows: the admin notice while a plugin is flagged, the
 * Approved / Not approved badges, and the all-clear when nothing is. The test
 * site has no real plugins, so tests/fixtures/plugin-check.php installs one
 * stand-in and picks which list the screen sees.
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login } from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SCREEN = '/wp-admin/admin.php?page=blueworx-plugin-check';

/**
 * Picks the list the site sees: 'none', 'all' or 'off'.
 */
function fixture(mode) {
  const file = path.join(__dirname, 'fixtures', 'plugin-check.php');
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  execFileSync('php', [file, wpLoad, mode], { encoding: 'utf8' });
}

test.describe('Plugin check', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );
  test.describe.configure({ mode: 'serial' });

  test.afterAll(() => {
    fixture('off');
  });

  test('an unapproved plugin shows the notice and the badge, and nothing is deactivated', async ({ page }) => {
    fixture('none');
    await login(page);

    // The notice is on screens that have nothing to do with plugins.
    await page.goto('/wp-admin/index.php');
    const notice = page.locator('.blueworx-plugin-check-notice');
    await expect(notice).toContainText('not on the BlueWorx approved list');
    await notice.getByRole('link', { name: 'Open Plugin check' }).click();

    await expect(page.getByRole('heading', { name: 'Plugin check' }).first()).toBeVisible();
    const row = page.locator('[data-testid="plugin-row"][data-slug="bw-test-unapproved"]');
    await expect(row).toContainText('BW Test Unapproved');
    await expect(row).toContainText('1.2.3');
    await expect(row).toContainText('Inactive');
    await expect(row).toContainText('Not approved');
    await expect(page.getByText('All clear. Every installed plugin')).toHaveCount(0);

    // Checking again keeps the flag and says when the list was last fetched.
    await page.getByRole('button', { name: 'Check again' }).click();
    await expect(page.getByText('Checked again.')).toBeVisible();
    await expect(row).toContainText('Not approved');
    await expect(page.locator('[data-testid="plugin-check-fetched"]')).toBeVisible();
  });

  test('only approved plugins shows all clear and no notice', async ({ page }) => {
    fixture('all');
    await login(page);

    await page.goto('/wp-admin/index.php');
    await expect(page.locator('.blueworx-plugin-check-notice')).toHaveCount(0);

    await page.goto(SCREEN);
    await expect(page.getByText('All clear. Every installed plugin is on the approved list.')).toBeVisible();
    const row = page.locator('[data-testid="plugin-row"][data-slug="bw-test-unapproved"]');
    await expect(row).toContainText('Approved');
    await expect(row).not.toContainText('Not approved');
  });
});
