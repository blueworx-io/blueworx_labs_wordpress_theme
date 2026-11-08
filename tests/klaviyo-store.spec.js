/**
 * BlueWorx > Klaviyo: the SureCart store.
 *
 * The PHP check covers what each event sends. This covers the route only a
 * real site can show: the store's boxes on the Klaviyo screen, a signed
 * delivery to the webhook address queued and sent once however many times it
 * arrives, a forged one refused and flagged on the screen, and the events
 * landing in Klaviyo (the test double) in the store's currency.
 */

import { createHmac } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login, restoreAll } from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const SECRET = 'fixture-signing-secret';
const ROUTE = '/?rest_route=/blueworx/v1/klaviyo/surecart';
const KLAVIYO_PATH = '/wp-admin/admin.php?page=blueworx-klaviyo';

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

/**
 * Posts an event to the webhook address, signed the way SureCart signs it.
 */
function deliver(request, event, { secret = SECRET, age = 0 } = {}) {
  const body = JSON.stringify(event);
  const timestamp = String(Math.floor(Date.now() / 1000) - age);
  const signature = createHmac('sha256', secret).update(`${timestamp}.${body}`).digest('hex');

  return request.post(ROUTE, {
    data: body,
    headers: {
      'content-type': 'application/json',
      'x-webhook-signature': signature,
      'x-webhook-timestamp': timestamp,
    },
  });
}

/** The SureCart records the deliveries below refer to. */
const SUBSCRIPTION = {
  id: 'sub1',
  status: 'active',
  created_at: '2026-11-04T09:00:00+00:00',
  current_period_end_at: '2027-11-04T09:00:00+00:00',
  cancel_at_period_end: false,
  customer: { id: 'cus1', email: 'jane+test@example.com', first_name: 'Jane', last_name: 'Smith' },
  price: {
    id: 'price1',
    amount: 251900,
    currency: 'aud',
    recurring_interval: 'year',
    product: { id: 'prod1', slug: 'gold-membership', name: 'Gold Membership' },
  },
  current_period: { id: 'per1', status: 'paid', payment_retry_count: 1, checkout: { amount_due: 251900 } },
};

const PURCHASE = {
  id: 'pur1',
  subscription: 'sub1',
  initial_order: {
    id: 'ord1',
    checkout: { paid_amount: 251900, paid_at: '2026-11-04T09:00:00+00:00', metadata: { marketing_opt_in: 'on' } },
  },
};

const ONE_OFF = {
  id: 'pur2',
  subscription: null,
  quantity: 1,
  product: { id: 'prod2', slug: 'day-pass', name: 'Day pass' },
  initial_order: {
    id: 'ord2',
    checkout: {
      paid_amount: 4000,
      currency: 'aud',
      paid_at: '2026-11-05T09:00:00+00:00',
      customer: { id: 'cus2', email: 'sam@example.com', first_name: 'Sam' },
      metadata: {},
    },
  },
};

const RECORDS = {
  purchase: { pur1: PURCHASE, pur2: ONE_OFF },
  subscription: { sub1: SUBSCRIPTION },
};

const events = (calls) => calls.filter((call) => call.url.endsWith('/api/events')).map((call) => call.body.data.attributes);

test.describe('Klaviyo store', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );
  test.describe.configure({ mode: 'serial' });

  test.beforeAll(() => {
    fixture('setup');
    fixture('records', RECORDS);
  });

  test.afterAll(async () => {
    await restoreAll([['remove the fixture', async () => fixture('cleanup')]]);
  });

  test('the store\'s boxes save from the Klaviyo screen', async ({ page }) => {
    await login(page);
    await page.goto(KLAVIYO_PATH);

    await expect(page.getByTestId('blueworx-klaviyo-webhook-url')).toContainText('/blueworx/v1/klaviyo/surecart');
    await expect(page.locator('body')).toContainText('SureCart is not installed on this site');

    await page.fill('#blueworx_klaviyo_store_webhook_secret', SECRET);
    await page.locator('#blueworx_klaviyo_store_list').selectOption('XRYaZh');
    await page.fill('#blueworx_klaviyo_store_checkout_box', 'marketing_opt_in');
    await page.getByTestId('blueworx-klaviyo-test').click();
    await page.waitForLoadState('domcontentloaded');

    const test_notice = page.locator('.bw-notice--success').last();
    await expect(test_notice).toContainText('the webhook signing secret is set');
    await expect(test_notice).toContainText('the Members list is "Members"');
    await expect(page.locator('body')).toContainText('Saved: ••••••••cret');
    expect(await page.content()).not.toContain(SECRET);
  });

  test('a forged, old or unsigned delivery is refused, and the screen says so', async ({ page, request }) => {
    fixture('reset');
    const event = { id: 'evt1', type: 'purchase.created', data: { object: { id: 'pur1' } } };

    expect((await deliver(request, event, { secret: 'wrong' })).status()).toBe(401);
    expect((await deliver(request, event, { age: 3600 })).status()).toBe(401);
    expect((await request.post(ROUTE, { data: JSON.stringify(event) })).status()).toBe(401);

    const state = fixture('drain');
    expect(state.jobs, 'nothing refused is queued').toHaveLength(0);
    expect(events(state.calls), 'nothing refused reaches Klaviyo').toHaveLength(0);
    expect(state.log, 'a refused delivery is not a log line anyone can fill').toHaveLength(0);

    await login(page);
    await page.goto(KLAVIYO_PATH);
    await expect(page.locator('.bw-notice--warning')).toContainText('signature did not match');
  });

  test('a genuine delivery is sent once, however many times it arrives', async ({ request }) => {
    fixture('reset');
    const event = { id: 'evt2', type: 'purchase.created', data: { object: { id: 'pur1' } } };

    const first = await deliver(request, event);
    expect(first.status()).toBe(200);
    expect((await first.json()).status).toBe('queued');
    expect((await (await deliver(request, event)).json()).status).toBe('repeat');

    const state = drain();
    const sent = events(state.calls);
    expect(sent).toHaveLength(1);
    expect(sent[0].metric.data.attributes.name).toBe('Started Subscription');
    expect(sent[0].unique_id).toBe('started-sub1');
    expect(sent[0].value).toBe(2519);
    expect(sent[0].value_currency).toBe('AUD');
    expect(sent[0].properties.product_name).toBe('Gold Membership');
    expect(sent[0].properties.billing_interval).toBe('annual');
    expect(sent[0].properties.order_id).toBe('ord1');
    expect(sent[0].profile.data.attributes.email).toBe('jane+test@example.com');

    // A plus sign in an address survives the look-up.
    const lookups = state.calls.filter((call) => call.url.includes('/api/profiles?'));
    expect(decodeURIComponent(lookups[0].url)).toContain('equals(email,"jane+test@example.com")');

    const jane = state.profiles['jane+test@example.com'];
    expect(jane.first_name).toBe('Jane');
    expect(jane.properties.membership_status).toBe('active');
    expect(jane.properties.membership_plan).toBe('Gold Membership');
    expect(jane.properties.surecart_customer_id).toBe('cus1');
    expect(jane.properties.marketing_consent).toBe(true);

    const lists = state.calls.filter((call) => call.url.endsWith('/api/profile-subscription-bulk-create-jobs'));
    expect(lists).toHaveLength(1);
    expect(lists[0].body.data.relationships.list.data.id).toBe('XRYaZh');

    expect(state.log[0].label).toBe('Started Subscription');
    expect(state.log[0].outcome).toBe('sent');

    // Still a repeat after it has been sent.
    expect((await (await deliver(request, event)).json()).status).toBe('repeat');
  });

  test('a one-off purchase is Placed Order, and test orders stay out until asked', async ({ request }) => {
    fixture('reset');

    await deliver(request, { id: 'evt3', type: 'purchase.created', data: { object: { id: 'pur2' } } });
    let state = drain();
    let sent = events(state.calls);
    expect(sent).toHaveLength(1);
    expect(sent[0].metric.data.attributes.name).toBe('Placed Order');
    expect(sent[0].value).toBe(40);
    expect(sent[0].value_currency).toBe('AUD');
    expect(sent[0].properties.product_name).toBe('Day pass');
    expect(state.profiles['sam@example.com'].properties.last_order_date).toBe('2026-11-05');
    // No tick box at this checkout, so no list.
    expect(state.calls.filter((call) => call.url.endsWith('/api/profile-subscription-bulk-create-jobs'))).toHaveLength(0);

    const testOrder = { id: 'evt4', type: 'purchase.created', data: { object: { id: 'pur2', live_mode: false } } };
    expect((await (await deliver(request, testOrder)).json()).status).toBe('test_mode');

    fixture('store', { test_mode: '1' });
    expect((await (await deliver(request, testOrder)).json()).status).toBe('queued');
    fixture('store', { test_mode: '0' });

    expect((await (await deliver(request, { id: 'evt5', type: 'product.updated', data: { object: { id: 'x' } } })).json()).status).toBe('ignored');
  });

  test('renewal, failure, cancellation and refund each arrive once', async ({ request }) => {
    fixture('reset');
    fixture('records', {
      ...RECORDS,
      order: { ord9: { id: 'ord9', checkout: { id: 'chk9' }, subscription_for_test: 'sub1' } },
      refund: {
        ref1: {
          id: 'ref1',
          amount: 5000,
          reason: 'requested_by_customer',
          customer: SUBSCRIPTION.customer,
          charge: { checkout: { order: 'ord1', currency: 'aud', line_items: { data: [{ price: SUBSCRIPTION.price }] } } },
        },
      },
    });

    await deliver(request, { id: 'evt6', type: 'subscription.renewed', data: { object: { id: 'sub1' } } });
    await deliver(request, { id: 'evt7', type: 'order.payment_failed', data: { object: { id: 'ord9' } } });
    await deliver(request, { id: 'evt8', type: 'refund.succeeded', data: { object: { id: 'ref1' } } });

    let state = drain();
    let names = events(state.calls).map((e) => e.metric.data.attributes.name).sort();
    expect(names).toEqual(['Payment Failed', 'Refunded', 'Subscription Renewed']);

    const renewed = events(state.calls).find((e) => e.metric.data.attributes.name === 'Subscription Renewed');
    expect(renewed.value).toBe(2519);
    expect(renewed.properties.payment_number).toBe(2);

    const refunded = events(state.calls).find((e) => e.metric.data.attributes.name === 'Refunded');
    expect(refunded.value, 'a refund is never revenue').toBeUndefined();
    expect(refunded.properties.amount).toBe(50);
    expect(refunded.properties.currency).toBe('AUD');

    // Cancelling sends one event; access ending afterwards only changes the status.
    fixture('reset');
    fixture('records', { subscription: { sub1: { ...SUBSCRIPTION, cancel_at_period_end: true } } });
    await deliver(request, { id: 'evt9', type: 'subscription.set_to_cancel', data: { object: { id: 'sub1' } } });
    state = drain();
    expect(events(state.calls).map((e) => e.metric.data.attributes.name)).toEqual(['Subscription Cancelled']);
    expect(state.profiles['jane+test@example.com'].properties.membership_end_date).toBe('2027-11-04');

    fixture('records', {
      subscription: { sub1: { ...SUBSCRIPTION, status: 'canceled', cancel_at_period_end: true, ended_at: '2027-11-04T09:00:00+00:00' } },
    });
    await deliver(request, { id: 'evt10', type: 'subscription.canceled', data: { object: { id: 'sub1' } } });
    state = drain();
    expect(events(state.calls).map((e) => e.metric.data.attributes.name), 'no second cancellation event').toEqual(['Subscription Cancelled']);
    expect(state.profiles['jane+test@example.com'].properties.membership_status).toBe('cancelled');
    expect(state.log[0].label).toBe('Subscription status update');
  });

  test('switching the function off takes the address away', async ({ request }) => {
    fixture('off');
    const response = await deliver(request, { id: 'evt11', type: 'purchase.created', data: { object: { id: 'pur1' } } });
    expect(response.status()).toBe(404);
  });
});
