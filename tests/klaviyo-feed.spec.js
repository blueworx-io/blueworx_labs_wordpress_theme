/**
 * BlueWorx > Klaviyo: the product feed.
 *
 * The PHP check covers the shape of an item. This covers the route only a
 * real site can show: the address on the Klaviyo screen serves the products
 * with their prices, a wrong key gets a 404, and making a new address stops
 * the old one.
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login, restoreAll } from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const ROUTE = '/?rest_route=/blueworx/v1/klaviyo/feed';

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

test.describe('Klaviyo feed', () => {
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

  test('the feed lists the products with their prices, and only to the right key', async ({ request }) => {
    fixture('reset');
    const product = fixture('product');
    const key = fixture('feedkey');
    expect(key).toHaveLength(32);

    const response = await request.get(`${ROUTE}&key=${key}`);
    expect(response.status()).toBe(200);
    const items = await response.json();
    const racket = items.find((item) => item.$id === String(product.id));
    expect(racket).toBeDefined();
    expect(racket.$title).toBe('Test racket');
    expect(racket.$link).toBe(product.url);
    expect(racket.$price).toBe(129.5);
    expect(racket.currency).toBe('AUD');

    expect((await request.get(`${ROUTE}&key=${key}x`)).status()).toBe(404);
    expect((await request.get(ROUTE)).status()).toBe(404);
  });

  test('the screen shows the address and can make a new one', async ({ page, request }) => {
    const oldKey = fixture('feedkey');

    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-klaviyo');
    await expect(page.getByTestId('blueworx-klaviyo-feed-url')).toContainText(`key=${oldKey}`);
    await expect(page.locator('body')).toContainText('1 product, built');

    await page.locator('input[name="blueworx_klaviyo_feed_new_secret"]').check();
    await page.getByTestId('blueworx-klaviyo-save').click();
    await page.waitForLoadState('domcontentloaded');

    const newKey = fixture('feedkey');
    expect(newKey).not.toBe(oldKey);
    await expect(page.getByTestId('blueworx-klaviyo-feed-url')).toContainText(`key=${newKey}`);
    expect((await request.get(`${ROUTE}&key=${oldKey}`)).status()).toBe(404);
    expect((await request.get(`${ROUTE}&key=${newKey}`)).status()).toBe(200);

    fixture('off');
    expect((await request.get(`${ROUTE}&key=${newKey}`)).status()).toBe(404);
  });
});
