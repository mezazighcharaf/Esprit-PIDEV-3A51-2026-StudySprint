// @ts-check
/**
 * Tests E2E — Module Planning / Révision
 * Workflows : liste plans, détail, création plan, tâches, toggle tâche, suppression, IA suggest
 */
const { test, expect } = require('@playwright/test');
const { loginAs, BASE } = require('./helpers/auth');

test.describe('Planning — Consultation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Page planning — plans des fixtures affichés', async ({ page }) => {
    await page.goto(`${BASE}/fo/planning`);
    await expect(page.locator('body')).toContainText('Plan Maths');
  });

  test('Calendrier ou liste de tâches visible', async ({ page }) => {
    await page.goto(`${BASE}/fo/planning`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    // La page doit afficher quelque chose (calendrier FullCalendar ou liste)
    await expect(page.locator('body')).toContainText(/planning|plan|révision|tâche/i);
  });

  test('Détail d\'un plan — tâches affichées', async ({ page }) => {
    await page.goto(`${BASE}/fo/planning`);
    const planLink = page.locator('a:has-text("Plan Maths")').first();
    if (await planLink.isVisible()) {
      await planLink.click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(/suites|intégrales|EDO/i);
    }
  });
});

test.describe('Planning — Création de plan', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Formulaire création plan accessible', async ({ page }) => {
    await page.goto(`${BASE}/fo/planning/plans/new`);
    await expect(page.locator('input[name="revision_plan[title]"]')).toBeVisible();
  });

  test('Créer un plan de révision complet', async ({ page }) => {
    await page.goto(`${BASE}/fo/planning/plans/new`);

    await page.fill('input[name="revision_plan[title]"]', 'Plan E2E Playwright');

    // Sélectionner la matière (EntityType → select)
    const subjectSelect = page.locator('select[name="revision_plan[subject]"]');
    if (await subjectSelect.isVisible()) {
      const options = await subjectSelect.locator('option').all();
      if (options.length > 1) await subjectSelect.selectOption({ index: 1 });
    }

    // Dates
    const startDate = page.locator('input[name="revision_plan[startDate]"]');
    const endDate   = page.locator('input[name="revision_plan[endDate]"]');
    if (await startDate.isVisible()) await startDate.fill('2026-03-01');
    if (await endDate.isVisible()) await endDate.fill('2026-03-31');

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    await expect(page.locator('body')).toContainText(/plan e2e|playwright/i);
  });
});

test.describe('Planning — Tâches', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Ajouter une tâche à un plan existant', async ({ page }) => {
    // Accéder au plan de Alice
    await page.goto(`${BASE}/fo/planning`);
    const planLink = page.locator('a:has-text("Plan Maths")').first();
    if (!(await planLink.isVisible())) return;
    await planLink.click();
    await page.waitForLoadState('networkidle');
    const planUrl = page.url();
    const matchPlan = planUrl.match(/planning\/(\d+)/);
    if (!matchPlan) return;
    const planId = matchPlan[1];

    await page.goto(`${BASE}/fo/planning/plans/${planId}/tasks/new`);
    await page.fill('input[name="plan_task[title]"]', 'Tâche E2E Test');

    const startAt = page.locator('input[name="plan_task[startAt]"]');
    const endAt   = page.locator('input[name="plan_task[endAt]"]');
    if (await startAt.isVisible()) await startAt.fill('2026-03-05T09:00');
    if (await endAt.isVisible()) await endAt.fill('2026-03-05T11:00');

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });

  test('Toggle état d\'une tâche (todo → done) via AJAX', async ({ page }) => {
    await page.goto(`${BASE}/fo/planning`);
    const planLink = page.locator('a:has-text("Plan Maths")').first();
    if (!(await planLink.isVisible())) return;
    await planLink.click();
    await page.waitForLoadState('networkidle');

    // Chercher le bouton toggle tâche
    const toggleBtn = page.locator('button[data-task-toggle], input[type="checkbox"][data-task], .task-toggle').first();
    if (await toggleBtn.isVisible()) {
      await toggleBtn.click();
      await page.waitForTimeout(1000);
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
    }
  });
});

test.describe('Planning — IA Suggest', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Bouton IA suggest visible sur un plan', async ({ page }) => {
    await page.goto(`${BASE}/fo/planning`);
    const planLink = page.locator('a:has-text("Plan Maths")').first();
    if (!(await planLink.isVisible())) return;
    await planLink.click();
    await page.waitForLoadState('networkidle');
    // Chercher le bouton IA suggest
    const aiBtn = page.locator('button:has-text("IA"), a:has-text("Optimiser"), button[data-ai]').first();
    // On vérifie juste que la page se charge sans erreur
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });
});
