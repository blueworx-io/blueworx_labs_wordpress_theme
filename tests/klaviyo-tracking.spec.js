/**
 * BlueWorx > Klaviyo: tracking visitors.
 *
 * The PHP check covers who is identified and what a product page sends. This
 * covers what lands in the page a visitor gets: Klaviyo's script with the
 * public key, the identify for a signed-in member, Viewed Product on a product
 * page, and nothing at all for administrators, admin screens, a missing key or
 * a switched-off function.
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, LOGIN_PATH, login, cacheBust, restoreAll } from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const SCRIPT = 'https://static.klaviyo.com/onsite/js/AbC123/klaviyo.js';

/**
 * Runs the PHP fixture against the local harness and returns its last line.
 */
function fixture(command, arg) {
  const file = path.join(__dirname, 'fixtures', 'klaviyo.php');
  const wpLoad = path.join(__dirname, '..', '.wp-test', 'wp', 'wp-load.php');
  const args = [file, wpLoad, command];
  if (arg !== undefined) {
    args.push(String(arg));
  }
  const out = execFileSync('php', args, { encoding: 'utf8' }).trim();
  return JSON.parse(out.split('\n').pop());
}

/**
 * A page's HTML as a browser receives it, and the config the page hands
 * Klaviyo, parsed. Pass the `request` fixture for a visitor who is not signed
 * in, or a page's own `request` to see the page as that signed-in page does.
 */
async function pageHtml(requests, url) {
  const response = await requests.get(cacheBust(url));
  const html = await response.text();
  const match = html.match(/var blueworxKlaviyoTracking = (\{.*?\});/s);
  return { html, config: match ? JSON.parse(match[1]) : null };
}

test.describe('Klaviyo tracking', () => {
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

  test('a visitor gets Klaviyo\'s script, and a product page sends Viewed Product', async ({ request }) => {
    const product = fixture('product');

    const home = await pageHtml(request, '/');
    expect(home.html).toContain(SCRIPT);
    expect(home.html).toContain('klaviyo-tracking.js');
    expect(home.config).not.toBeNull();
    expect(home.config.identify).toEqual({});
    expect(home.config.events).toEqual([]);
    expect(home.config.viewedItem).toBeNull();

    const viewed = await pageHtml(request, product.url.replace(/^https?:\/\/[^/]+/, ''));
    expect(viewed.html).toContain(SCRIPT);
    expect(viewed.config.events).toHaveLength(1);
    expect(viewed.config.events[0].name).toBe('Viewed Product');
    expect(viewed.config.events[0].properties.ProductName).toBe('Test racket');
    expect(viewed.config.events[0].properties.ProductID).toBe(String(product.id));
    expect(viewed.config.events[0].properties.URL).toBe(product.url);
    expect(viewed.config.events[0].properties.Price).toBe(129.5);
    expect(viewed.config.events[0].properties.Currency).toBe('AUD');
    expect(viewed.config.viewedItem.Title).toBe('Test racket');
    expect(viewed.config.viewedItem.Metadata.Price).toBe(129.5);
  });

  test('a signed-in member is identified; an administrator is not tracked', async ({ page, browser }) => {
    // The member seeded by tests/global-setup.js.
    const context = await browser.newContext();
    const member = await context.newPage();
    await member.emulateMedia({ reducedMotion: 'reduce' });
    await member.goto(cacheBust(LOGIN_PATH));
    await member.fill('#user_login', 'member');
    await member.fill('#user_pass', 'wptest-member-pw');
    await member.click('#wp-submit');
    await member.waitForLoadState('domcontentloaded');

    const home = await pageHtml(member.request, '/');
    expect(home.html).toContain(SCRIPT);
    expect(home.config.identify.email).toBe('member@example.com');
    expect(home.config.identify.external_id).toMatch(/^\d+$/);
    await context.close();

    await login(page);
    const admin = await pageHtml(page.request, '/');
    expect(admin.html).not.toContain(SCRIPT);
    expect(admin.config).toBeNull();

    // Never on an admin screen either.
    await page.goto('/wp-admin/admin.php?page=blueworx-klaviyo');
    expect(await page.content()).not.toContain('static.klaviyo.com');

    // The screen says tracking is on.
    await expect(page.locator('body')).toContainText('Visitor tracking');
    await expect(page.locator('body')).toContainText('Administrators are not tracked');
  });

  test('no public key or no function means no script', async ({ page, request }) => {
    fixture('public', '');
    let home = await pageHtml(request, '/');
    expect(home.html).not.toContain('static.klaviyo.com');
    expect(home.config).toBeNull();

    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-klaviyo');
    await expect(page.locator('body')).toContainText('Needs the public API key above');

    fixture('public', 'AbC123');
    home = await pageHtml(request, '/');
    expect(home.html).toContain(SCRIPT);

    fixture('off');
    home = await pageHtml(request, '/');
    expect(home.html).not.toContain('static.klaviyo.com');
  });
});
