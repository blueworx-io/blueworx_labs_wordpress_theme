/**
 * The theme next to the 1.x plugin.
 *
 * Both are the same code under the same names, so they can never run together.
 * Plugins load first, so when the plugin is on the theme has to stay out of the
 * way — no fatal error, no second console — and say why.
 *
 * The plugin is stood in for by an mu-plugin that defines its version constant,
 * which is the one thing the theme looks at. Signing in happens before it goes
 * on: with the theme out of the way there is no custom login page to use.
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect, isPlaceholder, login } from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Runs tests/fixtures/theme-state.php against the local harness.
 *
 * @param {string} command Fixture command.
 * @return {string} Fixture stdout.
 */
function themeState(command) {
  const fixture = path.join(__dirname, 'fixtures', 'theme-state.php');
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  return execFileSync('php', [fixture, wpLoad, command], { encoding: 'utf8' }).trim();
}

test.describe('The theme with the 1.x plugin still on', () => {
  test.skip(isPlaceholder, 'No real site configured');

  test.beforeEach(async ({ page }) => {
    await login(page);
    themeState('plugin-on');
  });

  test.afterEach(() => {
    themeState('plugin-off');
  });

  test('with the plugin active the theme stays out of the way', async ({ page }) => {
    const res = await page.goto('/wp-admin/');
    expect(res.status()).toBe(200);
    await expect(page.locator('body')).toContainText('Switch off the BlueWorx Labs plugin');
  });

  test('the theme\'s console is not registered', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
    await expect(page.locator('body')).not.toContainText('Enhancements');
  });

  test('public pages still load', async ({ page }) => {
    const res = await page.goto('/');
    expect(res.status()).toBe(200);
  });
});
