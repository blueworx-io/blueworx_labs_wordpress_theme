/**
 * The Theme menu.
 *
 * Design System and Cache moved out of the BlueWorx menu into a row of their
 * own. Their addresses did not change, so the things worth checking are that
 * the row is where it should be, holds what it should, and that nothing is
 * left behind under BlueWorx.
 */

import { test, expect, login, DASH_PATH } from './helpers.js';

const THEME_ROW = '#adminmenu > li#toplevel_page_blueworx-theme';
const BLUEWORX_ROW = '#adminmenu > li.menu-top:has(> a[href*="page=blueworx-labs-wordpress"])';

test.describe('Theme menu', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('sits directly below BlueWorx', async ({ page }) => {
    await page.goto(DASH_PATH);

    const ids = await page
      .locator('#adminmenu > li.menu-top')
      .evaluateAll((els) => els.map((el) => el.id));

    const blueworx = ids.indexOf('toplevel_page_blueworx-labs-wordpress');

    expect(blueworx).toBeGreaterThanOrEqual(0);
    expect(ids[blueworx + 1]).toBe('toplevel_page_blueworx-theme');

    await expect(page.locator(`${THEME_ROW} > a .wp-menu-name`)).toHaveText('Theme');
  });

  test('holds Design System and Cache, and BlueWorx no longer does', async ({ page }) => {
    await page.goto(DASH_PATH);

    const labels = await page
      .locator(`${THEME_ROW} .wp-submenu li > a`)
      .evaluateAll((els) => els.map((el) => el.textContent.trim()));

    expect(labels).toEqual(['Design System', 'Cache']);

    for (const slug of ['blueworx-design', 'blueworx-cache']) {
      await expect(page.locator(`${BLUEWORX_ROW} .wp-submenu a[href$="page=${slug}"]`)).toHaveCount(0);
    }
  });

  test('the old addresses still open the screens, under Theme', async ({ page }) => {
    const screens = [
      ['/wp-admin/admin.php?page=blueworx-design', 'Design System'],
      ['/wp-admin/admin.php?page=blueworx-cache', 'Cache'],
    ];

    for (const [path, title] of screens) {
      await page.goto(path);

      await expect(page.locator('.bw-pagehead__h1')).toHaveText(title);
      await expect(page.locator(THEME_ROW)).toHaveClass(/wp-has-current-submenu/);
      await expect(page.locator(`${THEME_ROW} .wp-submenu li.current > a`)).toHaveText(title);
    }
  });

  test('the bare Theme address lands on its first screen', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=blueworx-theme');

    await expect(page).toHaveURL(/page=blueworx-design/);
  });
});
