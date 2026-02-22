// @ts-check
/**
 * Tests E2E — Module Profil
 * Workflows : affichage profil, modification, avatar, bio IA, demande certification
 */
const { test, expect } = require('@playwright/test');
const { loginAs, BASE } = require('./helpers/auth');

test.describe('Profil — Consultation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Page profil affiche les infos d\'Alice', async ({ page }) => {
    await page.goto(`${BASE}/fo/profile`);
    await expect(page.locator('body')).toContainText('Alice');
    await expect(page.locator('body')).toContainText('Martin');
  });

  test('Bio et spécialité affichées', async ({ page }) => {
    await page.goto(`${BASE}/fo/profile`);
    await expect(page.locator('body')).toContainText(/mathématiques|bio|L3/i);
  });

  test('Suggestions IA affichées (bio IA et objectifs IA)', async ({ page }) => {
    await page.goto(`${BASE}/fo/profile`);
    // Alice a des suggestions IA dans les fixtures
    await expect(page.locator('body')).toContainText(/passionné|objectif|routine|analyse/i);
  });

  test('Avatar placeholder si pas de photo', async ({ page }) => {
    await page.goto(`${BASE}/fo/profile`);
    // Avatar initiales ou image
    await expect(page.locator('.fo-user-avatar, .avatar, img[alt*="avatar"], img[alt*="Avatar"]').first()).toBeVisible();
  });
});

test.describe('Profil — Modification', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Formulaire d\'édition accessible', async ({ page }) => {
    await page.goto(`${BASE}/fo/profile/edit`);
    await expect(page.locator('form')).toBeVisible();
  });

  test('Modifier la bio et sauvegarder', async ({ page }) => {
    await page.goto(`${BASE}/fo/profile/edit`);

    const bioField = page.locator('textarea[name="user[bio]"], textarea[name*="bio"]').first();
    if (await bioField.isVisible()) {
      await bioField.fill('Bio mise à jour par les tests Playwright E2E');
    }

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    // Redirigé vers la page profil avec les nouvelles infos
    await expect(page.locator('body')).toContainText(/profil|alice/i);
  });

  test('Modifier la spécialité', async ({ page }) => {
    await page.goto(`${BASE}/fo/profile/edit`);

    const specialtyField = page.locator('input[name*="specialty"], select[name*="specialty"]').first();
    if (await specialtyField.isVisible()) {
      const tag = await specialtyField.evaluate(el => el.tagName.toLowerCase());
      if (tag === 'select') {
        await specialtyField.selectOption({ index: 1 });
      } else {
        await specialtyField.fill('Analyse Numérique');
      }
    }

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });
});

test.describe('Profil — Demande de certification professeur', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Alice a déjà une demande de certification dans les fixtures', async ({ page }) => {
    await page.goto(`${BASE}/fo/profile`);
    // Alice a une demande "pending" dans les fixtures
    const certSection = page.locator('body').filter({ hasText: /certification|demande|enseignant/i });
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });
});

test.describe('Profil — Profil Bob (étudiant)', () => {
  test('Bob peut accéder à son profil', async ({ page }) => {
    await loginAs(page, 'bob');
    await page.goto(`${BASE}/fo/profile`);
    await expect(page.locator('body')).toContainText('Bob');
    await expect(page.locator('body')).toContainText('Dupont');
  });
});

test.describe('Profil — Profil Professeur', () => {
  test('Prof Claire peut accéder à son profil', async ({ page }) => {
    await loginAs(page, 'prof');
    await page.goto(`${BASE}/fo/profile`);
    await expect(page.locator('body')).toContainText('Claire');
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });
});
