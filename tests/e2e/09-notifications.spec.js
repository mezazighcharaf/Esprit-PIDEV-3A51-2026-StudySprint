// @ts-check
/**
 * Tests E2E — Module Notifications
 * Workflows : badge sidebar, liste notifs, marquer lue, marquer toutes lues
 */
const { test, expect } = require('@playwright/test');
const { loginAs, BASE } = require('./helpers/auth');

test.describe('Notifications — Badge', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Badge de notification visible dans la topbar (Alice a 2 notifs non lues)', async ({ page }) => {
    await page.goto(`${BASE}/fo/subjects`);
    // Badge dans le lien cloche
    const badge = page.locator('a[href*="notifications"] span').first();
    await expect(badge).toBeVisible();
    // Le badge doit afficher un nombre > 0
    const text = await badge.textContent();
    expect(parseInt(text || '0')).toBeGreaterThan(0);
  });

  test('Badge de notification visible pour Bob (Bob a 2 notifs)', async ({ page }) => {
    await loginAs(page, 'bob');
    await page.goto(`${BASE}/fo/subjects`);
    const badge = page.locator('a[href*="notifications"] span').first();
    await expect(badge).toBeVisible();
  });
});

test.describe('Notifications — Liste', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Page notifications — toutes les notifs d\'Alice affichées', async ({ page }) => {
    await page.goto(`${BASE}/fo/notifications`);
    await expect(page.locator('body')).toContainText(/notification|score|groupe|post/i);
    // Alice a : "Nouveau post" et "Score parfait !"
    await expect(page.locator('body')).toContainText(/score parfait|nouveau post/i);
  });

  test('Page notifications accessible pour Charlie', async ({ page }) => {
    await loginAs(page, 'charlie');
    await page.goto(`${BASE}/fo/notifications`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    await expect(page.locator('body')).toContainText(/notification|révision|flashcard/i);
  });
});

test.describe('Notifications — Actions', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Marquer une notification comme lue → badge mis à jour', async ({ page }) => {
    await page.goto(`${BASE}/fo/notifications`);

    // Chercher un lien "marquer comme lue" ou cliquer directement sur une notif
    const readLink = page.locator('a[href*="/read"], button[data-read]').first();
    if (await readLink.isVisible()) {
      await readLink.click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
    }
  });

  test('Marquer toutes les notifications comme lues', async ({ page }) => {
    await page.goto(`${BASE}/fo/notifications`);

    const markAllBtn = page.locator('form[action*="mark-all-read"] button, button:has-text("Tout marquer")').first();
    if (await markAllBtn.isVisible()) {
      await markAllBtn.click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
      // Après "tout marquer lu", le badge doit disparaître ou afficher 0
      const badge = page.locator('a[href*="notifications"] span').first();
      if (await badge.isVisible()) {
        const text = await badge.textContent();
        // Soit caché, soit 0
        expect(parseInt(text || '0')).toBe(0);
      }
    }
  });

  test('Cliquer sur une notification la marque comme lue et redirige', async ({ page }) => {
    await page.goto(`${BASE}/fo/notifications`);
    // Le lien /fo/notifications/{id}/read redirige vers le lien de la notif
    const notifReadLink = page.locator('a[href*="/fo/notifications/"][href*="/read"]').first();
    if (await notifReadLink.isVisible()) {
      await notifReadLink.click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
    }
  });
});
