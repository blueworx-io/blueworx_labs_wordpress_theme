/**
 * BlueWorx > Klaviyo: an event when a chosen page is viewed.
 *
 * The PHP check covers what a marked page sends. This covers the route only
 * a real site can show: the panel in the editor's sidebar saves the name
 * with the page, a visitor's copy of the page carries the event, an
 * administrator's does not, and switching it off sends nothing again.
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login, cacheBust, restoreAll } from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Runs the PHP fixture against the local harness and returns its last line.
 */
function fixture(command, arg) {
  const file = path.join(__dirname, 'fixtures', 'klaviyo.php');
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  const args = [file, wpLoad, command];
  if (arg !== undefined) {
    args.push(typeof arg === 'string' ? arg : JSON.stringify(arg));
  }
  const out = execFileSync('php', args, { encoding: 'utf8' }).trim();
  return JSON.parse(out.split('\n').pop());
}

/**
 * The config a page hands Klaviyo's script, as the given requester sees it.
 */
async function pageConfig(requests, url) {
  const html = await (await requests.get(cacheBust(url))).text();
  const match = html.match(/var blueworxKlaviyoTracking = (\{.*?\});/s);
  return match ? JSON.parse(match[1]) : null;
}

/**
 * Opens a page in the block editor and gets WordPress's own pop-ups out of
 * the way. See tests/page-lock.spec.js for why this loops.
 */
async function openEditor(page, id) {
  await page.goto(`/wp-admin/post.php?post=${id}&action=edit`);
  await expect(page.frameLocator('iframe[name="editor-canvas"]').locator('.editor-styles-wrapper')).toBeVisible({ timeout: 60_000 });
  const overlay = page.locator('.components-modal__screen-overlay');
  const deadline = Date.now() + 30000;
  let clearFor = 0;
  while (Date.now() < deadline && clearFor < 2) {
    if (await overlay.count()) {
      clearFor = 0;
      const dialog = page.getByRole('dialog').first();
      const takeOver = dialog.getByRole('link', { name: /take over/i }).or(dialog.getByRole('button', { name: /take over/i }));
      const close = dialog.getByRole('button', { name: /^close$/i }).first();
      if (await takeOver.count()) {
        await takeOver.first().click();
        await page.waitForLoadState('domcontentloaded');
        await expect(page.frameLocator('iframe[name="editor-canvas"]').locator('.editor-styles-wrapper')).toBeVisible({ timeout: 60_000 });
      } else if (await close.count()) {
        await close.click();
      } else {
        throw new Error('An unexpected pop-up is over the editor: ' + (await dialog.innerText()).slice(0, 400));
      }
      await overlay.first().waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
    } else {
      clearFor += 1;
    }
    await page.waitForTimeout(1000);
  }
  await expect(overlay).toHaveCount(0);
}

test.describe('Klaviyo page events', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );
  test.describe.configure({ mode: 'serial' });

  test.beforeAll(() => {
    fixture('setup');
  });

  test.afterAll(async () => {
    await restoreAll([['remove the fixture', async () => fixture('cleanup')]]);
  });

  test('a marked page sends its event to visitors, never to administrators', async ({ page, request }) => {
    const landing = fixture('page');
    const url = landing.url.replace(/^https?:\/\/[^/]+/, '');

    let config = await pageConfig(request, url);
    expect(config, 'the script is on the page').not.toBeNull();
    expect(config.events, 'nothing yet').toEqual([]);

    fixture('pageevent', { post_id: landing.id, name: 'Booked a Demo' });
    config = await pageConfig(request, url);
    expect(config.events).toHaveLength(1);
    expect(config.events[0].name).toBe('Booked a Demo');
    expect(config.events[0].properties.PageTitle).toBe('Klaviyo landing');
    expect(config.events[0].properties.URL).toBe(landing.url);
    expect(config.events[0].properties.PostType).toBe('page');

    await login(page);
    expect(await pageConfig(page.request, url), 'an administrator is not tracked at all').toBeNull();

    fixture('pageevent', { post_id: landing.id, name: '' });
    config = await pageConfig(request, url);
    expect(config.events, 'switched off again').toEqual([]);
  });

  test('the editor panel saves the event name with the page', async ({ page, request }) => {
    test.setTimeout(180_000);
    const landing = fixture('page');
    const url = landing.url.replace(/^https?:\/\/[^/]+/, '');

    await login(page);
    await openEditor(page, landing.id);

    const panel = page.locator('.blueworx-klaviyo-page-event');
    await expect(panel).toBeVisible();
    if ((await panel.locator('.components-panel__body-toggle').getAttribute('aria-expanded')) === 'false') {
      await panel.locator('.components-panel__body-toggle').click();
    }

    const toggle = panel.locator('input[type="checkbox"]');
    await expect(toggle).not.toBeChecked();
    await toggle.check();
    await panel.getByLabel('Event name').fill('Viewed Pricing');

    await page.keyboard.press('Control+s');
    await expect(page.locator('.components-snackbar, .components-notice').filter({ hasText: /updated|saved/i }).first()).toBeVisible({ timeout: 30_000 });

    const config = await pageConfig(request, url);
    expect(config.events).toHaveLength(1);
    expect(config.events[0].name).toBe('Viewed Pricing');

    // The Klaviyo screen lists page events among what sends.
    await page.goto('/wp-admin/admin.php?page=blueworx-klaviyo');
    await expect(page.locator('body')).toContainText('Page events');
  });
});
