/**
 * BlueWorx > Klaviyo.
 *
 * The PHP check covers the rules: what is written to a profile, what counts as
 * an event, which keys are accepted. This covers what only a real site can
 * show: the screens, and the whole route from a plugin calling the connection,
 * through the queue, to Klaviyo (a test double) and the log — including a
 * Klaviyo that is down and comes back.
 */

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
  restoreAll,
} from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const KLAVIYO_PATH = '/wp-admin/admin.php?page=blueworx-klaviyo';
const LOG_PATH = '/wp-admin/admin.php?page=blueworx-klaviyo-log';
const PRIVATE_KEY = 'pk_fixture0123456789abcdefghij';

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
 * Runs the queue until the newest log line is settled, or gives up.
 */
function drainUntilSettled() {
  let state = fixture('drain');
  for (let i = 0; i < 6 && state.log.length && state.log[0].outcome === 'retrying'; i += 1) {
    fixture('due');
    state = fixture('drain');
  }
  return state;
}

/**
 * Switches the function on or off from Enhancements.
 */
async function switchKlaviyo(page, on) {
  await page.goto(SETTINGS_PATH);
  await setFeature(page, 'klaviyo', on);
  await saveEnhancements(page);
}

test.describe('Klaviyo connection', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );
  test.describe.configure({ mode: 'serial' });

  test.afterAll(async () => {
    await restoreAll([['remove the fixture', async () => fixture('cleanup')]]);
  });

  test('the screen: switch on, refuse bad keys, save good ones and test them', async ({ page }) => {
    fixture('cleanup');
    fixture('double');

    await login(page);

    // Off by default, so neither page is in the menu yet.
    await page.goto(SETTINGS_PATH);
    await expect(page.locator('#adminmenu a[href$="page=blueworx-klaviyo"]')).toHaveCount(0);

    await switchKlaviyo(page, true);
    await expect(page.locator('#adminmenu a[href$="page=blueworx-klaviyo"]')).toHaveText('Klaviyo');
    await expect(page.locator('#adminmenu a[href$="page=blueworx-klaviyo-log"]')).toHaveText('Klaviyo log');

    await page.goto(KLAVIYO_PATH);
    await expect(page.locator('.bw-page__header, .bw-page-header, header').first()).toContainText('Not connected');

    // A badly formed key is refused out loud and nothing is saved.
    await page.fill('#blueworx_klaviyo_public_key', 'bad!');
    await page.fill('#blueworx_klaviyo_private_key', 'nope');
    await page.getByTestId('blueworx-klaviyo-save').click();
    await page.waitForLoadState('domcontentloaded');
    const refused = page.locator('.bw-notice--danger');
    await expect(refused).toContainText('Public API key');
    await expect(refused).toContainText('Private API key');
    await expect(page.locator('#blueworx_klaviyo_public_key')).toHaveValue('');
    await expect(page.locator('body')).toContainText('Not connected');

    // Good keys save, and the test names the lists the double serves.
    await page.fill('#blueworx_klaviyo_public_key', 'AbC123');
    await page.fill('#blueworx_klaviyo_private_key', PRIVATE_KEY);
    await page.getByTestId('blueworx-klaviyo-test').click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved.');
    await expect(page.locator('.bw-notice--success').last()).toContainText('Newsletter, Members');
    await expect(page.locator('body')).toContainText('Connected');
    await expect(page.locator('#blueworx_klaviyo_public_key')).toHaveValue('AbC123');

    // The private key is never shown back, only its tail.
    await expect(page.locator('#blueworx_klaviyo_private_key')).toHaveValue('');
    await expect(page.locator('body')).toContainText('Saved: ••••••••ghij');
    expect(await page.content()).not.toContain(PRIVATE_KEY);

    // A plugin built for the site shows up under "What sends to Klaviyo".
    await expect(page.locator('body')).toContainText('Test double');

    // Saving with the key box empty keeps the saved key.
    await page.getByTestId('blueworx-klaviyo-save').click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toContainText('Saved: ••••••••ghij');

    await page.goto(LOG_PATH);
    await expect(page.locator('body')).toContainText('Nothing has been sent yet');
  });

  test('a plugin identifies, tracks and subscribes, and each reaches Klaviyo once', async ({ page }) => {
    fixture('setup');
    fixture('profiles', {
      'held@example.com': {
        first_name: 'Held',
        properties: { member_number: '3', status: 'customer' },
      },
    });

    expect(
      fixture('identify', {
        email: 'New@Example.com',
        properties: { plan: 'Gold', member_number: '17' },
        args: { fill: { first_name: 'Sam' }, source: 'fixture' },
      })
    ).toBe(true);
    expect(
      fixture('identify', {
        email: 'held@example.com',
        properties: { plan: 'Gold', member_number: '17' },
        args: { fill: { first_name: 'Sam' }, source: 'fixture' },
      })
    ).toBe(true);
    expect(
      fixture('track', {
        event: 'Placed Order',
        email: 'new@example.com',
        properties: { total: 20, items: 2 },
        args: { value: 20, currency: 'aud', unique_id: 'order-1', list: 'XRYaZh', source: 'fixture' },
      })
    ).toBe(true);
    expect(fixture('subscribe', { email: 'other@example.com', list: 'Y5fFQA', args: { source: 'fixture' } })).toBe(true);

    // A plan the site plugin's filter returns as null is never queued.
    expect(
      fixture('identify', { email: 'blocked@example.com', properties: { plan: 'Gold' }, args: { source: 'fixture-blocked' } })
    ).toBe(false);

    const state = drainUntilSettled();

    expect(state.jobs).toEqual([]);
    expect(state.log.map((line) => line.outcome)).toEqual(['sent', 'sent', 'sent', 'sent']);
    expect(state.log.map((line) => line.label).sort()).toEqual(
      ['List subscription', 'Placed Order', 'Profile update', 'Profile update'].sort()
    );

    // A new profile gets everything, including what the site plugin's filter added.
    const fresh = state.profiles['new@example.com'];
    expect(fresh.first_name).toBe('Sam');
    expect(fresh.properties.plan).toBe('Gold');
    expect(fresh.properties.member_number).toBe('17');
    expect(fresh.properties.seen_by_site_plugin).toBe(true);

    // A held profile keeps its name and its protected property; the plain one changes.
    const held = state.profiles['held@example.com'];
    expect(held.first_name).toBe('Held');
    expect(held.properties.member_number).toBe('3');
    expect(held.properties.status).toBe('customer');
    expect(held.properties.plan).toBe('Gold');

    // The event went once, with its value as revenue in the store's currency.
    const events = state.calls.filter((call) => call.url.endsWith('/api/events'));
    expect(events).toHaveLength(1);
    expect(events[0].body.data.attributes.unique_id).toBe('order-1');
    expect(events[0].body.data.attributes.value).toBe(20);
    expect(events[0].body.data.attributes.value_currency).toBe('AUD');
    expect(events[0].body.data.attributes.properties.total).toBe(20);
    expect(events[0].auth).toBe(`Klaviyo-API-Key ${PRIVATE_KEY}`);

    // Two list subscriptions: the order's Members list and the plain subscribe.
    const lists = state.calls
      .filter((call) => call.url.endsWith('/api/profile-subscription-bulk-create-jobs'))
      .map((call) => call.body.data.relationships.list.data.id)
      .sort();
    expect(lists).toEqual(['XRYaZh', 'Y5fFQA']);

    // Nothing ever went to the real Klaviyo: every call hit the double.
    expect(state.calls.every((call) => call.url.startsWith('https://a.klaviyo.com/api/'))).toBe(true);

    await login(page);
    await page.goto(LOG_PATH);
    const rows = page.locator('[data-testid="blueworx-klaviyo-log"] tbody tr');
    await expect(rows).toHaveCount(4);
    await expect(rows.filter({ hasText: 'Placed Order' })).toContainText('new@example.com');
    await expect(rows.first().locator('.bw-badge, [class*="badge"]').first()).toContainText('Sent');
  });

  test('a Klaviyo that is down is retried until it comes back', async ({ page }) => {
    fixture('reset');
    fixture('fail', '2');

    expect(fixture('track', { event: 'Signed Up', email: 'retry@example.com', args: { unique_id: 'signup-1' } })).toBe(true);

    let state = fixture('drain');
    const firstTry = state.log.find((line) => line.label === 'Signed Up');
    expect(firstTry.outcome).toBe('retrying');
    expect(firstTry.detail).toContain('The double is down');

    await login(page);
    await page.goto(LOG_PATH);
    await expect(page.locator('[data-testid="blueworx-klaviyo-log"]')).toContainText('Will try again');

    // Not due yet, so another run leaves it waiting.
    state = fixture('drain');
    expect(state.jobs.length + state.log.filter((line) => line.outcome === 'sent').length).toBeGreaterThan(0);

    state = drainUntilSettled();
    expect(state.jobs).toEqual([]);
    expect(state.log[0].outcome).toBe('sent');
    expect(state.log.filter((line) => line.outcome === 'retrying').length).toBeGreaterThanOrEqual(1);

    // Recorded once in Klaviyo however many tries it took.
    const events = state.calls.filter((call) => call.url.endsWith('/api/events'));
    expect(events).toHaveLength(1);
    expect(events[0].body.data.attributes.unique_id).toBe('signup-1');
  });

  test('switching off stops all sending and hides the screens', async ({ page }) => {
    fixture('reset');
    await login(page);
    await switchKlaviyo(page, false);

    expect(fixture('track', { event: 'Signed Up', email: 'off@example.com' })).toBe(false);
    const state = fixture('state');
    expect(state.jobs).toEqual([]);
    expect(state.log).toEqual([]);

    await page.goto(SETTINGS_PATH);
    await expect(page.locator('#adminmenu a[href$="page=blueworx-klaviyo"]')).toHaveCount(0);

    // The address still answers, and says why nothing is happening.
    await page.goto(KLAVIYO_PATH);
    await expect(page.locator('body')).toContainText('The Klaviyo connection is switched off');
  });
});
