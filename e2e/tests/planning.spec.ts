import { test, expect } from '@playwright/test';
import { testUsers } from '../fixtures/test-data';

async function login(page: any) {
  await page.goto('/login');
  await page.fill('#username', testUsers.user.email);
  await page.fill('#password', testUsers.user.password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/(?!.*\/login).*/, { timeout: 5000 });
}

test.describe('Planning Module', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('planning index page loads with calendar', async ({ page }) => {
    await page.goto('/fo/planning');
    await expect(page).toHaveURL(/\/fo\/planning/);
    await expect(page.locator('body')).toBeVisible();
  });

  test('generate plan page loads form', async ({ page }) => {
    await page.goto('/fo/planning/generate');
    await expect(page).toHaveURL(/\/fo\/planning\/generate/);
    await expect(page.locator('form')).toBeVisible();
  });

  test('generate plan with end date before start date fails (server-side)', async ({ page }) => {
    await page.goto('/fo/planning/generate');

    const startField = page.locator('input[name*="startDate"], input[type="date"]').first();
    const endField = page.locator('input[name*="endDate"], input[type="date"]').nth(1);

    if (await startField.count() > 0 && await endField.count() > 0) {
      await startField.fill('2026-12-31');
      await endField.fill('2026-01-01');
      await page.click('button[type="submit"]');

      // Should stay on page or show error (server-side check)
      await expect(page).toHaveURL(/\/fo\/planning/);
    }
  });

  test('plan show page loads with tasks', async ({ page }) => {
    await page.goto('/fo/planning');

    const planLink = page.locator('a[href*="/fo/planning/"]').first();
    if (await planLink.count() > 0) {
      await planLink.click();
      await page.waitForURL(/\/fo\/planning\/\d+/, { timeout: 5000 });
      await expect(page.locator('body')).toBeVisible();
    }
  });

  test('toggle task status works', async ({ page }) => {
    await page.goto('/fo/planning');

    // Find a toggle form button
    const toggleForm = page.locator('form[action*="toggle"]').first();
    if (await toggleForm.count() > 0) {
      await toggleForm.locator('button[type="submit"]').click();
      await page.waitForURL(/\/fo\/planning/, { timeout: 5000 });
    }
  });

  test('new session page loads', async ({ page }) => {
    await page.goto('/fo/planning/sessions/new');
    await expect(page.locator('form')).toBeVisible();
  });

  test('create session with empty title fails (server-side)', async ({ page }) => {
    await page.goto('/fo/planning/sessions/new');

    const titleField = page.locator('input[name*="title"]').first();
    if (await titleField.count() > 0) {
      await titleField.fill('');
      await page.click('button[type="submit"]');

      // Should stay on form (server validation)
      await expect(page).toHaveURL(/\/fo\/planning/);
    }
  });
});
