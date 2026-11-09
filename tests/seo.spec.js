/**
 * Theme > SEO.
 *
 * The PHP check covers which tag comes from where. This covers the parts only a
 * real site can show: the switch lists the screen under Theme, the screen
 * saves, the tags really land in the page a visitor gets, a page's own excerpt
 * wins over the default, and it all leaves again when the switch goes off.
 */

import {
  test,
  expect,
  isPlaceholder,
  ADMIN_USER,
  ADMIN_PASS,
  login,
  cacheBust,
  setFeature,
  saveEnhancements,
  restoreAll,
} from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';
const SEO_PATH = '/wp-admin/admin.php?page=blueworx-seo';
const THEME_ROW = '#adminmenu > li#toplevel_page_blueworx-theme';

// The smallest valid PNG: one transparent pixel.
const PIXEL = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
  'base64'
);

/**
 * Switches the function on or off from Enhancements.
 */
async function switchSeo(page, on) {
  await page.goto(SETTINGS_PATH);
  await setFeature(page, 'seo', on);
  await saveEnhancements(page);
}

/**
 * Fills the SEO screen and saves.
 */
async function saveSeo(page, { siteName, description, image }) {
  await page.goto(SEO_PATH);
  await page.fill('#blueworx_seo_site_name', siteName);
  await page.fill('#blueworx_seo_description', description);
  // The picker writes an attachment id into a hidden box; set it directly
  // rather than driving WordPress's media library.
  await page.locator('#blueworx_seo_image').evaluate((el, value) => {
    el.value = value;
  }, image ? String(image) : '');
  await page.locator('.bw-savebar button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');
}

/**
 * A REST nonce for the signed-in user.
 */
async function restNonce(page) {
  return (await (await page.request.get('/wp-admin/admin-ajax.php?action=rest-nonce')).text()).trim();
}

/**
 * The head of a page, as a visitor's browser receives it.
 */
async function headOf(page, path) {
  const html = await (await page.request.get(cacheBust(path))).text();
  return html.slice(0, html.indexOf('</head>'));
}

test.describe('SEO', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  const created = { media: null, page: null };

  test.afterEach(async ({ page }) => {
    const nonce = await restNonce(page);

    await restoreAll([
      ['switch on', () => switchSeo(page, true)],
      ['empty the settings', () => saveSeo(page, { siteName: '', description: '', image: null })],
      ['switch off', () => switchSeo(page, false)],
      [
        'delete the test page',
        async () => {
          if (created.page) {
            await page.request.delete(`/wp-json/wp/v2/pages/${created.page}?force=true`, {
              headers: { 'X-WP-Nonce': nonce },
            });
            created.page = null;
          }
        },
      ],
      [
        'delete the test image',
        async () => {
          if (created.media) {
            await page.request.delete(`/wp-json/wp/v2/media/${created.media}?force=true`, {
              headers: { 'X-WP-Nonce': nonce },
            });
            created.media = null;
          }
        },
      ],
    ]);
  });

  test('the site defaults reach every page, a page\'s own excerpt wins, and off means gone', async ({ page }) => {
    await login(page);

    // Off by default: not in the menu, and nothing on the page.
    await page.goto(SETTINGS_PATH);
    await expect(page.locator('#adminmenu a[href$="page=blueworx-seo"]')).toHaveCount(0);
    expect(await headOf(page, '/')).not.toContain('og:title');

    await switchSeo(page, true);

    // Listed first under Theme.
    await expect(page.locator(`${THEME_ROW} .wp-submenu li > a`).first()).toHaveText('SEO');

    const nonce = await restNonce(page);

    const upload = await page.request.post('/wp-json/wp/v2/media', {
      headers: {
        'X-WP-Nonce': nonce,
        'Content-Type': 'image/png',
        'Content-Disposition': 'attachment; filename="bw-seo-share.png"',
      },
      data: PIXEL,
    });
    expect(upload.ok(), await upload.text()).toBe(true);
    const media = await upload.json();
    created.media = media.id;

    await saveSeo(page, {
      siteName: 'Share Name',
      description: 'A default description for "every" page.',
      image: media.id,
    });
    await expect(page.locator('.bw-notice--success')).toContainText('Settings saved.');
    await expect(page.locator('#blueworx_seo_site_name')).toHaveValue('Share Name');
    await expect(page.locator('.bw-pagehead')).toContainText('Live');

    const home = await headOf(page, '/');

    expect(home).toContain('<meta name="description" content="A default description for &quot;every&quot; page." />');
    expect(home).toContain('<meta property="og:type" content="website" />');
    expect(home).toContain('<meta property="og:site_name" content="Share Name" />');
    expect(home).toMatch(/<meta property="og:title" content="[^"]+" \/>/);
    expect(home).toMatch(/<meta property="og:url" content="[^"]+" \/>/);
    expect(home).toContain(`<meta property="og:image" content="${media.source_url}" />`);
    expect(home).toContain('<meta name="twitter:card" content="summary_large_image" />');
    expect(home).toContain(`<meta name="twitter:image" content="${media.source_url}" />`);

    // One canonical link, not the theme's and core's side by side.
    expect(home.match(/rel="canonical"/g) || []).toHaveLength(1);

    // A page with an excerpt of its own uses that, and its own title.
    const made = await page.request.post('/wp-json/wp/v2/pages', {
      headers: { 'X-WP-Nonce': nonce },
      data: {
        title: 'SEO Test Page',
        status: 'publish',
        excerpt: 'This page speaks for itself.',
        content: '<!-- wp:paragraph --><p>Body.</p><!-- /wp:paragraph -->',
      },
    });
    expect(made.ok(), await made.text()).toBe(true);
    const made_page = await made.json();
    created.page = made_page.id;

    const single = await headOf(page, new URL(made_page.link).pathname + new URL(made_page.link).search);

    expect(single).toContain('<meta property="og:title" content="SEO Test Page" />');
    expect(single).toContain('<meta name="description" content="This page speaks for itself." />');
    expect(single).toContain('<meta property="og:description" content="This page speaks for itself." />');
    expect(single.match(/rel="canonical"/g) || []).toHaveLength(1);

    // Never on an admin screen.
    await page.goto(SEO_PATH);
    expect(await page.content()).not.toContain('og:site_name');

    await switchSeo(page, false);
    expect(await headOf(page, '/')).not.toContain('og:site_name');
  });

  test('switched off, the screen explains itself instead of offering a form', async ({ page }) => {
    await login(page);
    await page.goto(SEO_PATH);

    await expect(page.locator('.bw-pagehead__h1')).toHaveText('SEO');
    await expect(page.locator('.bw-notice').filter({ hasText: 'SEO is switched off' })).toBeVisible();
    await expect(page.locator('#blueworx_seo_site_name')).toHaveCount(0);
  });
});
