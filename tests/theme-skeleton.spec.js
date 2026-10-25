/**
 * The plugin, running as a theme.
 *
 * WordPress has to see a theme called BlueWorx Enhance, load the whole console
 * from it, find its files under /themes/blueworx-labs-wordpress-theme/ (also
 * when a child theme is active), draw public pages in a working frame, keep it
 * updating itself, and tell client plugins once it has loaded.
 */

import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect, isPlaceholder, login, restoreAll } from './helpers.js';

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

const VERSION = JSON.parse(readFileSync(path.join(__dirname, '..', 'package.json'), 'utf8')).version;
const CONSOLE_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const ADMIN_CSS = /\/wp-content\/themes\/blueworx-labs-wordpress-theme\/assets\/css\/admin-theme\.css/;

test.describe('The theme', () => {
  test.skip(isPlaceholder, 'No real site configured');

  test('the theme is active and the console loads', async ({ page }) => {
    await login(page);

    const themes = await page.goto('/wp-admin/themes.php');
    expect(themes.status()).toBe(200);
    const active = await page.evaluate(
      () => (window._wpThemeSettings?.themes || []).find((theme) => theme.active)?.name
    );
    expect(active).toBe('BlueWorx Enhance');

    const screen = await page.goto(CONSOLE_PATH);
    expect(screen.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Enhancements' }).first()).toBeVisible();
  });

  test('assets load from the theme folder', async ({ page }) => {
    await login(page);
    await page.goto(CONSOLE_PATH);

    await expect(page.locator('link[href*="admin-theme.css"]')).toHaveAttribute('href', ADMIN_CSS);

    // The shared design system works its address out the plugin way, so this
    // is the one most likely to point under /plugins/.
    const design = page.locator('link[href*="blueworx-admin-design.css"]');
    await expect(design).toHaveAttribute('href', /\/wp-content\/themes\/blueworx-labs-wordpress-theme\/assets\/blueworx-admin-design\.css/);

    const css = await page.request.get(await design.getAttribute('href'));
    expect(css.status()).toBe(200);
  });

  test('a child theme still loads the parent\'s files', async ({ page }) => {
    try {
      expect(themeState('child-on')).toBe('blueworx-child');

      await login(page);
      await page.goto(CONSOLE_PATH);
      await expect(page.locator('link[href*="admin-theme.css"]')).toHaveAttribute('href', ADMIN_CSS);
    } finally {
      await restoreAll([['switch back to the parent', async () => themeState('child-off')]]);
    }
  });

  test('the public home page has a frame', async ({ page }) => {
    const res = await page.goto('/');
    expect(res.status()).toBe(200);

    await expect(page.locator('body')).toHaveClass(/\bhome\b/);

    // Only wp_head() and wp_footer() put core's own files on the page, so
    // finding one proves both ran.
    const html = await page.content();
    expect(html).toContain('/wp-includes/');
  });

  test('a missing page says so, under one heading', async ({ page }) => {
    const res = await page.goto('/no-such-page-here/');
    expect(res.status()).toBe(404);
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('h1')).toHaveText('Page not found');
  });

  test('the theme updates itself rather than offering a toggle', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/themes.php');

    const forced = await page.evaluate(
      () => (window._wpThemeSettings?.themes || []).find((theme) => theme.id === 'blueworx-labs-wordpress-theme')?.autoupdate?.forced
    );
    expect(forced).toBe(true);
  });

  test('client plugins are told once the theme has loaded', async ({ page }) => {
    // The global-setup mu-plugin listens for blueworx_theme_ready the way a
    // client plugin would, and prints what it heard into the page head.
    await page.goto('/');

    const html = await page.content();
    expect(html).toContain(`<!-- blueworx_theme_ready: ${VERSION}, store loaded: yes, times: 1 -->`);
  });
});
