import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  setFeature,
  saveEnhancements,
  featureIsOn,
} from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const WP_LOAD = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');

/**
 * Ends any role preview outright, via tests/fixtures/clear-view-as.php.
 *
 * A preview takes the settings screens away, so a test that fails mid-preview
 * cannot click its way out. This goes at the stored preview directly.
 */
function clearViewAs() {
  const fixture = path.join(__dirname, 'fixtures', 'clear-view-as.php');

  return execFileSync('php', [fixture, WP_LOAD], { encoding: 'utf8' }).trim();
}

/**
 * What the admin account really holds, read from the database.
 *
 * @return {{caps: Object<string, boolean>, level: number}} Stored roles and user level.
 */
function stored() {
  const fixture = path.join(__dirname, 'fixtures', 'view-as-role.php');

  return JSON.parse(
    execFileSync('php', [fixture, WP_LOAD, 'stored', ADMIN_USER], { encoding: 'utf8' }).trim()
  );
}

/**
 * What the page says the signed-in user is, from the test hooks mu-plugin
 * (tests/global-setup.js): the roles off wp_get_current_user(), off a fresh
 * get_userdata(), and current_user_can() asked for two roles by name.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @return {Promise<{roles: string, userdata: string, can: string}>} What was printed.
 */
async function seenAs(page) {
  const html = await page.content();
  const read = (name) => (html.match(new RegExp(`<!-- ${name}: (.*?) -->`)) || [])[1];

  return {
    roles: read('bw_test_roles'),
    userdata: read('bw_test_userdata_roles'),
    can: read('bw_test_can'),
  };
}

test.describe('Viewing as a role changes the role other plugins see', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  // Starts clean, and ends clean whatever happened: a stranded preview would
  // fail every later spec for a reason that has nothing to do with it.
  test.beforeAll(() => {
    clearViewAs();
  });

  test.afterAll(() => {
    clearViewAs();
  });

  test('the role is swapped for the preview and never saved', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');

    const wasOn = await featureIsOn(page, 'view_as_role');

    if (!wasOn) {
      await setFeature(page, 'view_as_role', true);
      await saveEnhancements(page);
    }

    try {
      await page.goto('/wp-admin/index.php');

      expect(await seenAs(page)).toEqual({
        roles: 'administrator',
        userdata: 'administrator',
        can: 'subscriber=0 administrator=1',
      });

      const trigger = page.locator('.bw-viewas__trigger');

      await trigger.click();
      await page.locator('.bw-viewas__menu .bw-viewas__option[value="subscriber"]').click();
      await expect(trigger).toHaveClass(/is-active/);

      // Every way a plugin might ask now says subscriber, and nothing says
      // administrator.
      const asSubscriber = {
        roles: 'subscriber',
        userdata: 'subscriber',
        can: 'subscriber=1 administrator=0',
      };

      expect(await seenAs(page)).toEqual(asSubscriber);

      // The front end too: that is where a members-only area decides what to show.
      await page.goto('/');
      expect(await seenAs(page)).toEqual(asSubscriber);

      // Saving your own profile mid-preview leaves the account as it was.
      // The hero's Save Changes is what a person clicks; core's own button is
      // hidden behind it (see tests/admin-theme.spec.js).
      await page.goto('/wp-admin/profile.php');
      await page.locator('#bw-profile-save').click();
      await page.waitForURL(/updated=/);

      expect(stored()).toEqual({ caps: { administrator: true }, level: 10 });

      // And so does a plugin writing back the roles off the user object it was
      // handed: removing the only role it can see, or saving that object's
      // roles straight over the stored ones.
      await page.goto('/wp-admin/index.php?bw_test_role_write=remove');
      expect(stored()).toEqual({ caps: { administrator: true }, level: 10 });

      await page.goto('/wp-admin/index.php?bw_test_role_write=meta');
      expect(stored()).toEqual({ caps: { administrator: true }, level: 10 });
      expect(await seenAs(page)).toEqual(asSubscriber);

      // Leaving is choosing your own view again, and the real role is simply
      // there: nothing was written, so nothing needed putting back.
      await trigger.click();
      await page.locator('.bw-viewas__menu .bw-viewas__option[value=""]').click();
      await expect(trigger).not.toHaveClass(/is-active/);

      expect(await seenAs(page)).toEqual({
        roles: 'administrator',
        userdata: 'administrator',
        can: 'subscriber=0 administrator=1',
      });
    } finally {
      clearViewAs();

      if (!wasOn) {
        await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
        await setFeature(page, 'view_as_role', false);
        await saveEnhancements(page);
      }
    }
  });

  test('a preview abandoned mid-way leaves nothing to restore', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');

    const wasOn = await featureIsOn(page, 'view_as_role');

    if (!wasOn) {
      await setFeature(page, 'view_as_role', true);
      await saveEnhancements(page);
    }

    try {
      await page.goto('/wp-admin/index.php');
      await page.locator('.bw-viewas__trigger').click();
      await page.locator('.bw-viewas__menu .bw-viewas__option[value="subscriber"]').click();
      await expect(page.locator('.bw-viewas__trigger')).toHaveClass(/is-active/);

      // The tab is closed here, mid-preview, and the preview is never ended.
      // The account was never anything but an administrator.
      await page.close();

      expect(stored()).toEqual({ caps: { administrator: true }, level: 10 });
    } finally {
      clearViewAs();

      if (!wasOn) {
        const restore = await page.context().newPage();
        await restore.goto('/wp-admin/admin.php?page=blueworx-labs-wordpress');
        await setFeature(restore, 'view_as_role', false);
        await saveEnhancements(restore);
      }
    }
  });
});
