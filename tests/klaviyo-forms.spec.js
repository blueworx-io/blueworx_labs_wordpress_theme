/**
 * BlueWorx > Klaviyo: website forms.
 *
 * The PHP check covers how a form's answers are found and what a sign-up
 * sends. This covers what only a real site can show: an admin connects two
 * forms from the screen with no code, a submission to each lands in the right
 * list with the right consent wording, a form that is not connected never
 * reaches Klaviyo, and removing a connection stops that form and nothing else.
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, login, cacheBust, restoreAll } from './helpers.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
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

const ELEMENTOR_FIELDS = {
  name: { title: 'Your name', value: 'Sam Jones', type: 'text' },
  email: { title: 'Email', value: 'sam@example.com', type: 'email' },
};

test.describe('Klaviyo forms', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );
  test.describe.configure({ mode: 'serial' });

  test.beforeAll(() => {
    fixture('setup');
    fixture('forms', []);
  });

  test.afterAll(async () => {
    await restoreAll([['remove the fixture', async () => fixture('cleanup')]]);
  });

  test('an admin connects two forms from the screen, and each sends to its list', async ({ page }) => {
    await login(page);
    await page.goto(KLAVIYO_PATH);

    await expect(page.locator('[data-forms-empty]')).toBeVisible();

    // First form: an Elementor form onto the Newsletter list.
    await page.locator('[data-add-form]').click();
    let row = page.locator('[data-form-row]').nth(0);
    await row.locator('select[name$="[kind]"]').selectOption('elementor');
    await row.locator('input[name$="[match]"]').fill('Stay up to date');
    await row.locator('[name$="[list]"]').selectOption('Y5fFQA');
    await row.locator('textarea[name$="[consent]"]').fill('Keep me posted about events.');
    await row.locator('input[name$="[tag]"]').fill('newsletter');

    // Second form: a plain HTML form onto the Members list.
    await page.locator('[data-add-form]').click();
    row = page.locator('[data-form-row]').nth(1);
    await row.locator('select[name$="[kind]"]').selectOption('html');
    await row.locator('input[name$="[match]"]').fill('footer-signup');
    await row.locator('[name$="[list]"]').selectOption('XRYaZh');
    await row.locator('textarea[name$="[consent]"]').fill('Yes, email me.');
    await row.locator('input[name$="[tag]"]').fill('footer');

    await page.getByTestId('blueworx-klaviyo-test').click();
    await page.waitForLoadState('domcontentloaded');

    await expect(page.locator('.bw-notice--success').first()).toContainText('Settings saved.');
    await expect(page.locator('.bw-notice--success').last()).toContainText('Form newsletter subscribes to "Newsletter"');
    await expect(page.locator('.bw-notice--success').last()).toContainText('Form footer subscribes to "Members"');
    await expect(page.locator('[data-form-row]')).toHaveCount(2);
    await expect(page.locator('body')).toContainText('2 forms connected: newsletter, footer');

    // A submission to each form.
    fixture('elementor', { name: 'Stay Up To Date', fields: ELEMENTOR_FIELDS });
    const posted = fixture('html', {
      form: 'footer-signup',
      page: 'https://example.test/',
      fields: [
        { name: 'email', label: 'Email address', value: 'pat@example.com', checked: null },
        { name: 'fname', label: 'First name', value: 'Pat', checked: null },
        { name: 'news', label: 'Email me', value: 'on', checked: true },
      ],
    });
    expect(posted.status).toBe(202);
    expect(posted.data.queued).toBe(true);

    // And one to a form nobody connected.
    fixture('elementor', { name: 'Contact us', fields: ELEMENTOR_FIELDS });

    const state = drain();

    expect(state.log.map((line) => line.outcome)).toEqual(['sent', 'sent']);
    expect(state.log.map((line) => line.label).sort()).toEqual(['Form: footer', 'Form: newsletter']);

    const sam = state.profiles['sam@example.com'];
    expect(sam.first_name).toBe('Sam');
    expect(sam.last_name).toBe('Jones');
    expect(sam.properties.signup_source).toBe('newsletter');
    expect(sam.properties.signup_consent_wording).toBe('Keep me posted about events.');
    expect(sam.properties.marketing_consent).toBe(true);
    expect(sam.properties.signup_page).toBe('https://example.test/elementor-page/');

    const pat = state.profiles['pat@example.com'];
    expect(pat.first_name).toBe('Pat');
    expect(pat.properties.signup_source).toBe('footer');
    expect(pat.properties.signup_consent_wording).toBe('Yes, email me.');

    const lists = state.calls
      .filter((call) => call.url.endsWith('/api/profile-subscription-bulk-create-jobs'))
      .map((call) => [call.body.data.attributes.profiles.data[0].attributes.email, call.body.data.relationships.list.data.id])
      .sort();
    expect(lists).toEqual([
      ['pat@example.com', 'XRYaZh'],
      ['sam@example.com', 'Y5fFQA'],
    ]);

    const events = state.calls.filter((call) => call.url.endsWith('/api/events'));
    expect(events.map((call) => call.body.data.attributes.metric.data.attributes.name)).toEqual(['Submitted Form', 'Submitted Form']);

    // The catcher is on the public page while an HTML form is connected.
    const html = await (await page.request.get(cacheBust('/'))).text();
    expect(html).toContain('klaviyo-forms.js');
    expect(html).toContain('"forms":["footer-signup"]');
  });

  test('an unticked marketing box is identified but never subscribed', async () => {
    fixture('reset');
    const posted = fixture('html', {
      form: 'footer-signup',
      page: 'https://example.test/',
      fields: [
        { name: 'email', label: 'Email address', value: 'quiet@example.com', checked: null },
        { name: 'news', label: 'Email me', value: '', checked: false },
      ],
    });
    expect(posted.data.queued).toBe(true);

    const state = drain();
    expect(state.log[0].outcome).toBe('sent');
    expect(state.profiles['quiet@example.com'].properties.marketing_consent).toBe(false);
    expect(state.calls.filter((call) => call.url.endsWith('/api/profile-subscription-bulk-create-jobs'))).toEqual([]);

    // A form nobody connected is refused at the door.
    expect(fixture('html', { form: 'other-form', page: '', fields: [] }).status).toBe(404);
  });

  test('removing a connection stops that form and nothing else', async ({ page }) => {
    fixture('reset');
    await login(page);
    await page.goto(KLAVIYO_PATH);

    await page.locator('[data-form-row]').nth(1).locator('[data-remove-form]').click();
    await expect(page.locator('[data-form-row]')).toHaveCount(1);
    await page.getByTestId('blueworx-klaviyo-save').click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('[data-form-row]')).toHaveCount(1);

    expect(fixture('html', { form: 'footer-signup', page: '', fields: [] }).status).toBe(404);
    fixture('elementor', { name: 'Stay up to date', fields: ELEMENTOR_FIELDS });

    const state = drain();
    expect(state.log.map((line) => line.label)).toEqual(['Form: newsletter']);

    // No HTML form connected, so no catcher on the page.
    const html = await (await page.request.get(cacheBust('/'))).text();
    expect(html).not.toContain('klaviyo-forms.js');
  });
});
