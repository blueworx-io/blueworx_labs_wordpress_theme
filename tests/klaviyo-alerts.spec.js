/**
 * BlueWorx > Klaviyo: emails when sending goes wrong.
 *
 * The PHP check covers when an email is due and what it says. This covers
 * the route through a real site: a send that fails through every retry ends
 * in one email, ten failures in the hour still mean one email, a refused key
 * is emailed once and the screen says so, and the screen shows the last
 * successful send once the key works again. The test double catches the mail.
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
 * Runs the queue until nothing is waiting or retrying.
 */
function drain() {
  let state = fixture('drain');
  for (let i = 0; i < 8 && (state.jobs.length || (state.log.length && state.log[0].outcome === 'retrying')); i += 1) {
    fixture('due');
    state = fixture('drain');
  }
  return state;
}

const track = (event, email) => fixture('track', { event, email, properties: { n: 1 }, args: { unique_id: `${event}-${email}` } });

test.describe('Klaviyo alerts', () => {
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

  test('a send that fails for good is emailed once, with the reason and a link to the log', async () => {
    fixture('reset');
    fixture('fail', 500);

    track('Submitted Form', 'sam@example.com');
    let state = drain();
    expect(state.log[0].outcome).toBe('failed');
    expect(state.jobs).toHaveLength(0);

    let mail = fixture('mail');
    expect(mail).toHaveLength(1);
    expect(mail[0].subject).toContain('Klaviyo: 1 send failed');
    expect(mail[0].message).toContain('Submitted Form: sam@example.com');
    expect(mail[0].message).toContain('The double is down');
    expect(mail[0].message).toContain('page=blueworx-klaviyo-log');

    // Ten more inside the hour: no second email until the hour is up.
    for (let i = 0; i < 10; i += 1) {
      track('Placed Order', `c${i}@example.com`);
    }
    state = drain();
    expect(state.log.filter((line) => line.outcome === 'failed')).toHaveLength(11);
    expect(fixture('mail')).toHaveLength(1);

    fixture('alerts', { last_sent: Math.floor(Date.now() / 1000) - 7200 });
    fixture('tick');
    mail = fixture('mail');
    expect(mail).toHaveLength(2);
    expect(mail[1].subject).toContain('Klaviyo: 10 sends failed');
    expect(mail[1].message).toContain('c0@example.com');
    expect(mail[1].message).toContain('c9@example.com');
  });

  test('a refused key is emailed once, the screen says so, and a working key clears it', async ({ page }) => {
    fixture('reset');
    fixture('refuse', 50);

    track('Submitted Form', 'one@example.com');
    track('Submitted Form', 'two@example.com');
    track('Submitted Form', 'three@example.com');
    fixture('drain');
    fixture('due');
    fixture('drain');

    const mail = fixture('mail');
    expect(mail).toHaveLength(1);
    expect(mail[0].subject).toContain('refusing the private API key');
    expect(mail[0].message).toContain('Missing or invalid private key');

    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-klaviyo');
    await expect(page.locator('body')).toContainText('Klaviyo is refusing the private API key');
    await expect(page.locator('body')).toContainText('Nothing has reached Klaviyo yet');

    // The key works again: the held sends go through, the warning clears and
    // the last successful send shows.
    fixture('refuse', 0);
    const state = drain();
    expect(state.log.filter((line) => line.outcome === 'sent')).toHaveLength(3);
    expect(fixture('mail'), 'no further email').toHaveLength(1);

    await page.reload();
    await expect(page.locator('body')).not.toContainText('Klaviyo is refusing the private API key');
    await expect(page.locator('body')).toContainText('Last successful send');
    await expect(page.locator('body')).not.toContainText('Nothing has reached Klaviyo yet');

    // A fresh refusal is worth another email.
    fixture('refuse', 50);
    track('Submitted Form', 'four@example.com');
    fixture('drain');
    expect(fixture('mail')).toHaveLength(2);
  });

  test('the alert address saves from the screen, and a bad one is refused', async ({ page }) => {
    fixture('reset');
    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-klaviyo');

    // Passes the browser's own check but not WordPress's: no dot in the domain.
    await page.fill('#blueworx_klaviyo_alert_email', 'nobody@localhost');
    await page.getByTestId('blueworx-klaviyo-save').click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('.bw-notice--danger', { hasText: 'not saved' })).toContainText('Email failures to');

    await page.fill('#blueworx_klaviyo_alert_email', 'marketing@example.com');
    await page.getByTestId('blueworx-klaviyo-save').click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');
    await expect(page.locator('#blueworx_klaviyo_alert_email')).toHaveValue('marketing@example.com');
  });
});
