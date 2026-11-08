/**
 * Blog and post pages.
 *
 * With Site styles on, the list of posts, a category page, a search and a
 * single post are all drawn by the theme, inside the frame, and read right.
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
const TITLE = 'Blog check: strings and grips';

async function switchSiteStyles(page, on) {
  await page.goto(SETTINGS_PATH);
  await setFeature(page, 'site_styles', on);
  await saveEnhancements(page);
}

async function restNonce(page) {
  return (await (await page.request.get('/wp-admin/admin-ajax.php?action=rest-nonce')).text()).trim();
}

test.describe('Blog and posts', () => {
  test.skip(
    isPlaceholder || !ADMIN_USER || !ADMIN_PASS,
    'No real staging/preview URL and/or WP_ADMIN_USER / WP_ADMIN_PASS configured yet.'
  );

  test('the list, a category, a search and a post are all drawn by the theme', async ({ page }) => {
    test.setTimeout(240_000);
    await login(page);
    await page.goto(SETTINGS_PATH);
    const wasOn = await featureIsOn(page, 'site_styles');
    const nonce = await restNonce(page);
    let postId = 0;

    try {
      if (!wasOn) await switchSiteStyles(page, true);

      const created = await page.request.post('/wp-json/wp/v2/posts', {
        headers: { 'X-WP-Nonce': nonce },
        data: {
          title: TITLE,
          status: 'publish',
          content: '<p>Strings, grips and everything else a racket needs.</p>',
          excerpt: 'A short excerpt about rackets.',
        },
      });
      expect(created.ok(), await created.text()).toBe(true);
      const post = await created.json();
      postId = post.id;

      // The list of posts: a card for the post, with its date and excerpt.
      const home = await page.request.get('/wp-json/wp/v2/settings', { headers: { 'X-WP-Nonce': nonce } });
      const settings = await home.json();
      const listPath = settings.page_for_posts ? `/?p=${settings.page_for_posts}` : '/';
      await page.goto(cacheBust(listPath));
      await expect(page.locator('h1.blueworx-page__title')).toBeVisible();
      const card = page.locator('.blueworx-posts .blueworx-card', { hasText: TITLE });
      await expect(card).toHaveCount(1);
      await expect(card.locator('.blueworx-post__date')).not.toBeEmpty();
      await expect(card.locator('.blueworx-card__excerpt')).toHaveText('A short excerpt about rackets.');
      await expect(card.locator('.blueworx-card__title a')).toHaveAttribute('href', post.link);
      await expect(page.locator('header.blueworx-header')).toBeVisible();

      // The post: by-line, title, content, and the category it is in.
      await page.goto(cacheBust(post.link));
      await expect(page.locator('h1.blueworx-page__title')).toHaveText(TITLE);
      await expect(page.locator('.blueworx-post__meta .blueworx-post__date')).not.toBeEmpty();
      await expect(page.locator('.blueworx-post__meta .blueworx-post__author')).not.toBeEmpty();
      const category = page.locator('.blueworx-post__meta .blueworx-post__category').first();
      await expect(category).toBeVisible();
      await expect(page.locator('.entry-content')).toContainText('Strings, grips');

      // The category page lists it under the category's own name, with no
      // "Category:" prefix.
      const categoryName = await category.textContent();
      await category.click();
      await expect(page.locator('h1.blueworx-page__title')).toHaveText(categoryName.trim());
      await expect(page.locator('.blueworx-posts .blueworx-card', { hasText: TITLE })).toHaveCount(1);

      // A search finds it, and says what was searched for.
      await page.goto(cacheBust('/?s=grips'));
      await expect(page.locator('h1.blueworx-page__title')).toHaveText('Search results for “grips”');
      await expect(page.locator('.blueworx-posts .blueworx-card', { hasText: TITLE })).toHaveCount(1);

      // A search for nothing says so rather than showing an empty page.
      await page.goto(cacheBust('/?s=zzqqxxnothing'));
      await expect(page.locator('.blueworx-list__empty')).toContainText('Nothing matched');
    } finally {
      await restoreAll([
        [
          'post',
          async () => {
            if (postId) {
              await page.request.delete(`/wp-json/wp/v2/posts/${postId}?force=true`, { headers: { 'X-WP-Nonce': nonce } });
            }
          },
        ],
        ['site styles', async () => { if (!wasOn) await switchSiteStyles(page, false); }],
      ]);
    }
  });
});
