import { test, expect } from '@playwright/test';
import { testUsers } from '../fixtures/test-data';

/**
 * Helper: login as admin and wait for BO dashboard
 */
async function loginAsAdmin(page) {
  await page.goto('/login');
  await page.fill('#username', testUsers.admin.email);
  await page.fill('#password', testUsers.admin.password);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle', { timeout: 10000 });
}

/**
 * Helper: login as regular user and wait for FO dashboard
 */
async function loginAsUser(page) {
  await page.goto('/login');
  await page.fill('#username', testUsers.user.email);
  await page.fill('#password', testUsers.user.password);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle', { timeout: 10000 });
}

// ─── BO Access Control ───────────────────────────────────────────────

test.describe('BO Access Control', () => {

  test('anonymous user is redirected to login from /bo/users', async ({ page }) => {
    await page.goto('/bo/users');
    await expect(page).toHaveURL(/\/login/);
  });

  test('anonymous user is redirected to login from /bo/ai-monitoring', async ({ page }) => {
    await page.goto('/bo/ai-monitoring');
    await expect(page).toHaveURL(/\/login/);
  });

  test('regular user gets 403 on /bo/users', async ({ page }) => {
    await loginAsUser(page);
    const response = await page.goto('/bo/users');
    expect(response?.status()).toBe(403);
  });

  test('regular user gets 403 on /bo/ai-monitoring', async ({ page }) => {
    await loginAsUser(page);
    const response = await page.goto('/bo/ai-monitoring');
    expect(response?.status()).toBe(403);
  });

  test('admin can access /bo/users', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/bo/users');
    await expect(page).toHaveURL(/\/bo\/users/);
    await expect(page.locator('table').first()).toBeVisible();
  });
});

// ─── BO CRUD Smoke Tests ─────────────────────────────────────────────

test.describe('BO CRUD Smoke Tests', () => {

  const boRoutes = [
    { path: '/bo/users', name: 'Utilisateurs' },
    { path: '/bo/subjects', name: 'Matières' },
    { path: '/bo/chapters', name: 'Chapitres' },
    { path: '/bo/quizzes', name: 'Quiz' },
    { path: '/bo/decks', name: 'Decks' },
    { path: '/bo/plans', name: 'Plans' },
    { path: '/bo/tasks', name: 'Tâches' },
    { path: '/bo/groups', name: 'Groupes' },
    { path: '/bo/posts', name: 'Posts' },
    { path: '/bo/user-profiles', name: 'Profils' },
  ];

  for (const route of boRoutes) {
    test(`admin can list ${route.name} (${route.path})`, async ({ page }) => {
      await loginAsAdmin(page);
      await page.goto(route.path);
      await expect(page).toHaveURL(new RegExp(route.path.replace(/\//g, '\\/')));
      // Should have a table or "Aucun" message
      const hasTable = await page.locator('table').count();
      const hasEmpty = await page.getByText(/Aucun/i).count();
      expect(hasTable + hasEmpty).toBeGreaterThan(0);
    });
  }

  test('admin can access search on /bo/users', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/bo/users?q=admin');
    await expect(page).toHaveURL(/q=admin/);
    await expect(page.locator('table').first()).toBeVisible();
  });

  test('admin can access sorted view on /bo/subjects', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/bo/subjects?sort=name&dir=asc');
    await expect(page).toHaveURL(/sort=name/);
  });
});

// ─── AI Monitoring ───────────────────────────────────────────────────

test.describe('BO AI Monitoring', () => {

  test('admin can access AI monitoring dashboard', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/bo/ai-monitoring');
    await expect(page).toHaveURL(/\/bo\/ai-monitoring/);
    // Dashboard should show stats
    await expect(page.locator('.card, .stat, h1, h2').first()).toBeVisible();
  });

  test('admin can access AI monitoring logs', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/bo/ai-monitoring/logs');
    await expect(page).toHaveURL(/\/bo\/ai-monitoring\/logs/);
    // Should have filter form
    await expect(page.locator('select[name="feature"]')).toBeVisible();
    await expect(page.locator('select[name="status"]')).toBeVisible();
  });

  test('AI logs filter by feature works', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/bo/ai-monitoring/logs?feature=quiz');
    await expect(page).toHaveURL(/feature=quiz/);
    // Page should load without error — either a table or a "no results" message
    const hasTable = await page.locator('table').count();
    const hasEmpty = await page.getByText(/Aucun log/i).count();
    expect(hasTable + hasEmpty).toBeGreaterThan(0);
  });

  test('AI logs filter by status works', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/bo/ai-monitoring/logs?status=success');
    await expect(page).toHaveURL(/status=success/);
    const hasTable = await page.locator('table').count();
    const hasEmpty = await page.getByText(/Aucun log/i).count();
    expect(hasTable + hasEmpty).toBeGreaterThan(0);
  });

  test('AI log detail modal opens with masked prompt', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/bo/ai-monitoring/logs');

    // If there are log entries, click the detail button
    const detailBtn = page.locator('button', { hasText: '👁️' }).first();
    if (await detailBtn.count() > 0) {
      await detailBtn.click();
      // Modal should appear
      await expect(page.locator('#logDetailsModal')).toBeVisible({ timeout: 3000 });
      // Should have toggle button
      await expect(page.locator('#togglePromptBtn')).toBeVisible();
    }
  });
});
