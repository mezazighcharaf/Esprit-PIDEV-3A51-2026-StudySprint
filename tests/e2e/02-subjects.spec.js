// @ts-check
/**
 * Tests E2E — Module Matières
 * Workflows : liste, détail, création, modification, suppression, chapitres, Wikipedia
 */
const { test, expect } = require('@playwright/test');
const { loginAs, BASE } = require('./helpers/auth');

test.describe('Matières — Consultation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Liste des matières — 3 matières des fixtures affichées', async ({ page }) => {
    await page.goto(`${BASE}/fo/subjects`);
    await expect(page.locator('body')).toContainText('Mathématiques Avancées');
    await expect(page.locator('body')).toContainText('Physique Quantique');
    await expect(page.locator('body')).toContainText('Chimie Organique');
  });

  test('Détail matière — chapitres affichés', async ({ page }) => {
    await page.goto(`${BASE}/fo/subjects`);
    // Le lien vers le détail est le bouton "Voir" dans la card Maths
    const mathRow = page.locator('text=Mathématiques Avancées').locator('..').locator('..');
    const viewBtn = page.locator('a[href*="/fo/subjects/"]').first();
    await viewBtn.click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText('Suites et séries');
    await expect(page.locator('body')).toContainText('Intégrales');
    await expect(page.locator('body')).toContainText('Équations différentielles');
  });

  test('Détail matière Physique — chapitres MQ affichés', async ({ page }) => {
    await page.goto(`${BASE}/fo/subjects`);
    // Trouver le lien "Voir" de Physique Quantique
    const physRow = page.locator('text=Physique Quantique');
    await physRow.waitFor();
    // Naviguer directement en cherchant le lien contenant l'ID de physique
    const links = page.locator('a[href*="/fo/subjects/"]');
    const count = await links.count();
    // Cliquer sur le 2ème lien (Physique est le 2ème sujet)
    if (count >= 2) await links.nth(1).click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText('Postulats');
    await expect(page.locator('body')).toContainText('Opérateurs');
  });

  test('Détail matière — bloc Wikipedia rendu (ou absent sans erreur)', async ({ page }) => {
    await page.goto(`${BASE}/fo/subjects`);
    const viewBtn = page.locator('a[href*="/fo/subjects/"]').first();
    await viewBtn.click();
    await page.waitForLoadState('networkidle');
    // Page ne doit pas avoir d'erreur 500
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    await expect(page.locator('body')).not.toContainText('An Error Occurred');
  });
});

test.describe('Matières — Création (Professeur)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'prof');
  });

  test('Accès au formulaire de création', async ({ page }) => {
    await page.goto(`${BASE}/fo/subjects/new`);
    await expect(page.locator('input[name="subject[name]"]')).toBeVisible();
    await expect(page.locator('input[name="subject[code]"]')).toBeVisible();
  });

  test('Créer une matière → redirigé vers détail', async ({ page }) => {
    await page.goto(`${BASE}/fo/subjects/new`);
    await page.fill('input[name="subject[name]"]', 'Informatique E2E Test');
    await page.fill('input[name="subject[code]"]', 'E2ETEST01');
    const desc = page.locator('textarea[name="subject[description]"]');
    if (await desc.isVisible()) await desc.fill('Matière créée par Playwright');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    // Redirigé vers le détail ou la liste — matière créée
    await expect(page.locator('body')).toContainText('Informatique E2E Test');
  });
});

test.describe('Matières — Chapitres (Professeur)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'prof');
  });

  test('Ajouter un chapitre à une matière', async ({ page }) => {
    // Récupérer l'ID de la matière Maths via le premier lien "Voir"
    await page.goto(`${BASE}/fo/subjects`);
    await page.locator('a[href*="/fo/subjects/"]').first().click();
    await page.waitForLoadState('networkidle');
    const url = page.url();
    const match = url.match(/subjects\/(\d+)/);
    if (!match) return;
    const subjectId = match[1];

    await page.goto(`${BASE}/fo/subjects/${subjectId}/chapters/new`);
    await page.fill('input[name="chapter[title]"]', 'Chapitre E2E Test');
    const summary = page.locator('textarea[name="chapter[summary]"]');
    if (await summary.isVisible()) await summary.fill('Résumé du chapitre test');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });
});
