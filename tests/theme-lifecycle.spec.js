/**
 * Setting a site up, and cleaning it up, without plugin hooks.
 *
 * A theme has no activation or deactivation hook. So a site is set up on its
 * first load, which is also what a new site on a network gets, and switching
 * to another theme does what deactivating the plugin did: the support account
 * and its key go. tests/php/theme-lifecycle-test.php covers the rules; this
 * proves them on a running site.
 */

import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect, isPlaceholder, login, restoreAll, readSupportKey } from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const VERSION = JSON.parse(readFileSync(path.join(__dirname, '..', 'package.json'), 'utf8')).version;

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

test.describe('Theme setup and clean-up', () => {
  test.skip(isPlaceholder, 'No real site configured');

  test('switching theme away ends support access', async ({ page }) => {
    try {
      await login(page);
      await page.goto('/wp-admin/admin.php?page=blueworx-support');
      await page.getByRole('button', { name: 'Generate key' }).click();
      expect((await readSupportKey(page)).trim()).toMatch(/^[0-9a-f]{64}$/);
      expect(themeState('support-state')).toBe('1|1');

      expect(themeState('switch-away')).toBe('twentytwentyfive');

      expect(themeState('support-state')).toBe('0|0');
    } finally {
      await restoreAll([['switch back to blueworx', async () => themeState('switch-back')]]);
    }
  });

  test('the support account is only powerful while the theme runs', async ({ page }) => {
    try {
      await login(page);
      await page.goto('/wp-admin/admin.php?page=blueworx-support');
      await page.getByRole('button', { name: 'Generate key' }).click();
      expect((await readSupportKey(page)).trim()).toMatch(/^[0-9a-f]{64}$/);

      // The role stores sign-in rights only; the theme grants the rest. A
      // theme change from the Customizer skips the clean-up, so this is what
      // keeps a leftover account harmless.
      expect(themeState('support-caps')).toBe('read|1|0');
    } finally {
      await restoreAll([['remove the support account', async () => themeState('support-off')]]);
    }
  });

  test('a fresh site is set up on first load', async ({ page }) => {
    themeState('forget-setup');
    expect(themeState('setup-version')).toBe('');

    const res = await page.goto('/');
    expect(res.status()).toBe(200);

    expect(themeState('setup-version')).toBe(VERSION);
  });
});
