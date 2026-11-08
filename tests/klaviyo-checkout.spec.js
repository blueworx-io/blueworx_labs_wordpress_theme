/**
 * BlueWorx > Klaviyo: checkouts started but not paid.
 *
 * The PHP check covers what Started Checkout carries. This covers the route
 * through a real site: a checkout SureCart's form has saved is queued once,
 * sent to Klaviyo (the test double) with the cart and a link back, repeated
 * saves do nothing, a changed cart sends again, and test checkouts stay out
 * until asked for.
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login, restoreAll } from './helpers.js';

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
 * Runs the queue until nothing is retrying.
 */
function drain() {
  let state = fixture('drain');
  for (let i = 0; i < 6 && state.log.length && state.log[0].outcome === 'retrying'; i += 1) {
    fixture('due');
    state = fixture('drain');
  }
  return state;
}

/** A checkout the way SureCart's form saves it. */
const checkout = (overrides = {}) => ({
  id: 'chk1',
  object: 'checkout',
  status: 'draft',
  email: 'sam@example.com',
  first_name: 'Sam',
  live_mode: true,
  currency: 'aud',
  total_amount: 54800,
  line_items: {
    data: [
      {
        quantity: 2,
        total_amount: 50800,
        price: { id: 'price1', amount: 25400, product: { id: 'prod1', slug: 'racket', name: 'Racket', permalink: 'https://example.test/racket/' } },
      },
      { quantity: 1, price: { id: 'price2', amount: 4000, product: { id: 'prod2', name: 'Grip' } } },
    ],
  },
  ...overrides,
});

const events = (calls) => calls.filter((call) => call.url.endsWith('/api/events')).map((call) => call.body.data.attributes);

test.describe('Klaviyo checkouts', () => {
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

  test('a checkout with an email sends Started Checkout once, and again when the cart changes', async () => {
    fixture('reset');

    expect(fixture('checkout', checkout({ email: '' })), 'no email yet: nothing to send').toBe('skipped');
    expect(fixture('checkout', checkout())).toBe('queued');
    expect(fixture('checkout', checkout()), 'the same cart saved again').toBe('repeat');

    let state = drain();
    let sent = events(state.calls);
    expect(sent).toHaveLength(1);
    expect(sent[0].metric.data.attributes.name).toBe('Started Checkout');
    expect(sent[0].unique_id).toMatch(/^checkout-chk1-/);
    expect(sent[0].value).toBe(548);
    expect(sent[0].value_currency).toBe('AUD');
    expect(sent[0].properties.ItemNames).toEqual(['Racket', 'Grip']);
    expect(sent[0].properties.Items).toHaveLength(2);
    expect(sent[0].properties.Items[0].Quantity).toBe(2);
    expect(sent[0].properties.Items[0].RowTotal).toBe(508);
    expect(sent[0].properties.CheckoutURL).toContain('checkout_id=chk1');
    expect(sent[0].profile.data.attributes.email).toBe('sam@example.com');
    expect(state.profiles['sam@example.com'].first_name).toBe('Sam');
    expect(state.log[0].label).toBe('Started Checkout');
    expect(state.log[0].outcome).toBe('sent');

    const changed = checkout({ total_amount: 62800 });
    changed.line_items.data[1].quantity = 3;
    expect(fixture('checkout', changed), 'a changed cart').toBe('queued');
    expect(fixture('checkout', changed)).toBe('repeat');

    state = drain();
    sent = events(state.calls);
    expect(sent).toHaveLength(2);
    expect(sent[1].value).toBe(628);
    expect(sent[1].unique_id).not.toBe(sent[0].unique_id);
  });

  test('a paid checkout, a test checkout and a switched-off function send nothing', async ({ page }) => {
    fixture('reset');

    expect(fixture('checkout', checkout({ id: 'chk2', status: 'paid' }))).toBe('skipped');
    expect(fixture('checkout', checkout({ id: 'chk3', live_mode: false }))).toBe('test_mode');

    fixture('store', { test_mode: '1' });
    expect(fixture('checkout', checkout({ id: 'chk3', live_mode: false }))).toBe('queued');
    fixture('store', { test_mode: '0' });

    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-klaviyo');
    await expect(page.locator('body')).toContainText('Abandoned checkouts');

    fixture('off');
    expect(fixture('checkout', checkout({ id: 'chk4' }))).toBe('off');
  });
});
