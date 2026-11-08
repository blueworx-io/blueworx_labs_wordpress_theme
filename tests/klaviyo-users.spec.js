/**
 * BlueWorx > Klaviyo: the site's own users.
 *
 * The PHP check covers what a profile carries and who is left out. This
 * covers the route through a real site: a new account reaches Klaviyo (the
 * test double) with its role and a Created Account event, an administrator
 * never does, a role change updates the profile, and the one-off "send
 * everyone" button on the Klaviyo screen sends every existing account.
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

const events = (calls) => calls.filter((call) => call.url.endsWith('/api/events')).map((call) => call.body.data.attributes);

test.describe('Klaviyo users', () => {
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

  test('a new account reaches Klaviyo with its role; an administrator never does', async () => {
    fixture('reset');

    const pat = fixture('user', { user_login: 'klaviyo-pat', user_email: 'klaviyo-pat@example.com', first_name: 'Pat', last_name: 'Member', role: 'subscriber' });
    const boss = fixture('user', { user_login: 'klaviyo-boss', user_email: 'klaviyo-boss@example.com', first_name: 'Boss', role: 'administrator' });
    expect(pat.id).toBeGreaterThan(0);
    expect(boss.id).toBeGreaterThan(0);

    let state = drain();
    const profile = state.profiles['klaviyo-pat@example.com'];
    expect(profile).toBeDefined();
    expect(profile.first_name).toBe('Pat');
    expect(profile.external_id).toBe(String(pat.id));
    expect(profile.properties.site_role).toBe('subscriber');
    expect(profile.properties.account_created).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(state.profiles['klaviyo-boss@example.com'], 'an administrator is never sent').toBeUndefined();

    const sent = events(state.calls);
    expect(sent).toHaveLength(1);
    expect(sent[0].metric.data.attributes.name).toBe('Created Account');
    expect(sent[0].unique_id).toBe(`account-${pat.id}`);
    expect(sent[0].properties.site_role).toBe('subscriber');
    expect(state.calls.filter((call) => call.url.endsWith('/api/profile-subscription-bulk-create-jobs')), 'never a list').toHaveLength(0);

    // A role change updates the profile; a sign-in notes the date.
    fixture('role', { id: pat.id, role: 'editor' });
    fixture('login', pat.id);
    state = drain();
    expect(state.profiles['klaviyo-pat@example.com'].properties.site_role).toBe('editor');
    expect(state.profiles['klaviyo-pat@example.com'].properties.last_login).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(events(state.calls), 'no second event for a change').toHaveLength(1);
  });

  test('the screen sends every existing account once, staff left out', async ({ page }) => {
    fixture('reset');

    await login(page);
    await page.goto('/wp-admin/admin.php?page=blueworx-klaviyo');
    await expect(page.locator('body')).toContainText('Website users');
    await expect(page.locator('body')).toContainText('Never sent everyone');

    await page.getByTestId('blueworx-klaviyo-sync-users').click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved');

    const state = drain();
    // The member seeded by tests/global-setup.js and the account made above.
    expect(state.profiles['member@example.com']).toBeDefined();
    expect(state.profiles['member@example.com'].properties.site_role).toBe('subscriber');
    expect(state.profiles['klaviyo-pat@example.com']).toBeDefined();
    expect(state.profiles['klaviyo-boss@example.com']).toBeUndefined();
    expect(Object.keys(state.profiles).some((email) => email.startsWith(`${ADMIN_USER}@`) || email === 'admin@example.com'), 'the admin is never sent').toBe(false);
    expect(events(state.calls), 'a one-off send is profiles only').toHaveLength(0);
    expect(state.jobs).toHaveLength(0);

    await page.reload();
    await expect(page.locator('body')).toContainText('Everyone was last sent');
  });
});
