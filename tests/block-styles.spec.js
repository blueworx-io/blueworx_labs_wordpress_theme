/**
 * Standard blocks and the site's design.
 *
 * Pages are built in WordPress's block editor from its standard blocks. With
 * Site styles on, those blocks have to take the site's colours and fonts in
 * two places that can drift apart: the live page, and the editor's canvas.
 * This proves both agree, and that wide and full alignment are on offer.
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
  featureIsOn,
  saveEnhancements,
  restoreAll,
} from './helpers.js';

const SETTINGS_PATH = '/wp-admin/admin.php?page=blueworx-labs-wordpress';

const CONTENT = [
  '<!-- wp:heading --><h2 class="wp-block-heading">Blocks on the page</h2><!-- /wp:heading -->',
  '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Primary action</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
  '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>Left column.</p><!-- /wp:paragraph --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>Right column.</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->',
  '<!-- wp:group {"align":"full","backgroundColor":"surface"} --><div class="wp-block-group alignfull has-surface-background-color has-background"><!-- wp:quote --><blockquote class="wp-block-quote"><p>A quote inside a band.</p></blockquote><!-- /wp:quote --></div><!-- /wp:group -->',
].join('');

async function switchSiteStyles(page, on) {
  await page.goto(SETTINGS_PATH);
  await setFeature(page, 'site_styles', on);
  await saveEnhancements(page);
}

async function restNonce(page) {
  return (await (await page.request.get('/wp-admin/admin-ajax.php?action=rest-nonce')).text()).trim();
}

/**
 * The computed styles compared between the live page and the editor canvas.
 * Runs inside the browser against whichever document it is given.
 */
function readStyles(doc) {
  const button = doc.querySelector('.wp-block-button__link');
  const heading = doc.querySelector('h2.wp-block-heading');
  const columns = doc.querySelector('.wp-block-columns');
  const band = doc.querySelector('.wp-block-group.has-background');
  return {
    buttonBackground: button && getComputedStyle(button).backgroundColor,
    buttonRadius: button && getComputedStyle(button).borderRadius,
    headingFont: heading && getComputedStyle(heading).fontFamily,
    columnsGap: columns && getComputedStyle(columns).columnGap,
    bandPadding: band && getComputedStyle(band).paddingTop,
    primary: getComputedStyle(doc.documentElement).getPropertyValue('--color-primary').trim(),
  };
}

function hexToRgb(hex) {
  const n = parseInt(hex.replace('#', ''), 16);
  return `rgb(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255})`;
}

test.describe('Standard blocks', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test("standard blocks use the site's design on the live page and in the editor", async ({ page }) => {
    test.setTimeout(240_000);
    await login(page);
    await page.goto(SETTINGS_PATH);
    const wasOn = await featureIsOn(page, 'site_styles');
    const nonce = await restNonce(page);
    let pageId = 0;

    try {
      if (!wasOn) await switchSiteStyles(page, true);

      const created = await page.request.post('/wp-json/wp/v2/pages', {
        headers: { 'X-WP-Nonce': nonce },
        data: { title: 'Block styles check', status: 'publish', content: CONTENT },
      });
      expect(created.ok(), await created.text()).toBe(true);
      const post = await created.json();
      pageId = post.id;

      // The live page.
      await page.goto(cacheBust(post.link));
      await expect(page.locator('.wp-block-button__link')).toBeVisible();
      const live = await page.evaluate(readStyles, await page.evaluateHandle(() => document));
      expect(live.primary).not.toBe('');
      expect(live.buttonBackground).toBe(hexToRgb(live.primary));
      expect(live.headingFont.toLowerCase()).toContain('sora');
      expect(live.columnsGap).not.toBe('normal');
      expect(live.bandPadding).not.toBe('0px');
      // Full width reached the page unchanged.
      await expect(page.locator('.wp-block-group.alignfull')).toHaveCount(1);

      // The editor canvas.
      await page.goto(`/wp-admin/post.php?post=${pageId}&action=edit`);
      const guide = page.getByRole('dialog', { name: /welcome/i });
      await guide.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
      if (await guide.isVisible()) await guide.getByRole('button', { name: /close/i }).click();
      const canvas = page.frameLocator('iframe[name="editor-canvas"]');
      await expect(canvas.locator('.wp-block-button__link')).toBeVisible({ timeout: 60_000 });
      const frame = page.frame({ name: 'editor-canvas' });
      const editor = await frame.evaluate(readStyles, await frame.evaluateHandle(() => document));
      expect(editor.buttonBackground).toBe(live.buttonBackground);
      expect(editor.buttonRadius).toBe(live.buttonRadius);
      expect(editor.headingFont).toBe(live.headingFont);
      expect(editor.columnsGap).toBe(live.columnsGap);

      // Wide and full alignment are offered on a group block.
      await canvas.locator('.wp-block-group').first().click();
      await page.getByRole('button', { name: /^Align$/ }).click();
      await expect(page.getByRole('menuitemradio', { name: /Wide width/ })).toBeVisible();
      await expect(page.getByRole('menuitemradio', { name: /Full width/ })).toBeVisible();
    } finally {
      await restoreAll([
        ['the test page', async () => {
          if (pageId) await page.request.delete(`/wp-json/wp/v2/pages/${pageId}?force=true`, { headers: { 'X-WP-Nonce': nonce } });
        }],
        ['site styles', async () => { if (!wasOn) await switchSiteStyles(page, false); }],
      ]);
    }
  });
});
