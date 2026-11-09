/**
 * Every sign-in screen uses the site's look.
 *
 * tests/login-design.spec.js covers the sign-in form in detail. This walks the
 * other screens WordPress serves from the same address: lost password, reset
 * with a bad link, registration (closed and open), signed out, and the "check
 * your email" stop, and makes sure none of them falls back to the plain
 * WordPress screen.
 */

import { execFileSync } from 'node:child_process';

import { test, expect, isPlaceholder, ADMIN_USER, ADMIN_PASS, LOGIN_PATH, cacheBust } from './helpers.js';

const DESKTOP = { width: 1440, height: 900 };

async function openScreen(page, context, query) {
  await context.clearCookies();
  await page.setViewportSize(DESKTOP);
  await page.goto(cacheBust(`${LOGIN_PATH}${query}`));
}

/** The panel, the card and the theme's stylesheet, with WordPress's logo heading out of the way. */
async function expectThemed(page) {
  await expect(page.locator('.bw-login-panel')).toBeVisible();
  await expect(page.locator('#login')).toBeVisible();
  await expect(page.locator('link[href*="login-theme.css"]')).toHaveCount(1);
  await expect(page.locator('#login h1')).toBeHidden();
}

/** Opens or closes registration; prints the previous value. */
function registration(command) {
  return execFileSync('php', ['tests/fixtures/registration.php', '.wp-test/wp/wp-load.php', command], { encoding: 'utf8' }).trim();
}

test.describe('Every sign-in screen', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('lost password, a bad reset link, signed out and check-your-email all use the look', async ({ page, context }) => {
    await openScreen(page, context, '?action=lostpassword');
    await expectThemed(page);
    await expect(page.locator('.bw-login-title')).toHaveText('Forgot Your Password?');
    await expect(page.locator('#user_login')).toBeVisible();

    // A reset link that is wrong lands back on the lost-password form with an
    // error, in the same look.
    await openScreen(page, context, '?action=rp&key=not-a-real-key&login=admin');
    await expectThemed(page);
    await expect(page.locator('.bw-login-title')).toHaveText('Forgot Your Password?');
    await expect(page.locator('#login_error')).toBeVisible();

    await openScreen(page, context, '?loggedout=true');
    await expectThemed(page);
    await expect(page.locator('.bw-login-title')).toHaveText('Welcome Back');
    await expect(page.locator('#login .message')).toContainText('logged out');

    await openScreen(page, context, '?checkemail=confirm');
    await expectThemed(page);
    await expect(page.locator('.bw-login-title')).toHaveText('Check Your Email');
    await expect(page.locator('#login .message')).toContainText('Check your email');
  });

  test('registration uses the look whether it is closed or open', async ({ page, context }) => {
    // Closed: WordPress sends the visitor back to the sign-in form with a note.
    await openScreen(page, context, '?action=register');
    await expectThemed(page);
    await expect(page.locator('#login_error, #login .message')).toContainText('registration');

    // Open: the registration form itself.
    const before = registration('on');

    try {
      await openScreen(page, context, '?action=register');
      await expectThemed(page);
      await expect(page.locator('.bw-login-title')).toHaveText('Create an Account');
      await expect(page.locator('#user_login')).toBeVisible();
      await expect(page.locator('#user_email')).toBeVisible();
      await expect(page.locator('#wp-submit')).toHaveValue('Create an Account');

      // And the sign-in form now offers the way in for newcomers.
      await openScreen(page, context, '');
      await expect(page.locator('.bw-login-register-lead')).toHaveText('New here?');
    } finally {
      registration('1' === before ? 'on' : 'off');
    }
  });
});
