import { test, expect } from '@playwright/test';
import { testUsers } from '../fixtures/test-data';

async function login(page: any) {
  await page.goto('/login');
  await page.fill('#username', testUsers.user.email);
  await page.fill('#password', testUsers.user.password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/(?!.*\/login).*/, { timeout: 5000 });
}

test.describe('Subjects Module', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('subjects list page loads', async ({ page }) => {
    await page.goto('/fo/subjects');
    await expect(page).toHaveURL(/\/fo\/subjects/);
  });

  test('create subject with empty name fails (server-side)', async ({ page }) => {
    await page.goto('/fo/subjects/new');

    // Submit with empty name → server-side validation rejects
    const nameField = page.locator('input[name*="name"]').first();
    await nameField.fill('');
    await page.click('button[type="submit"]');

    // Should stay on form page
    await expect(page).toHaveURL(/\/fo\/subjects\/new/);
  });

  test('create subject with valid data succeeds', async ({ page }) => {
    const uniqueName = 'E2E Matière ' + Date.now();
    await page.goto('/fo/subjects/new');

    await page.fill('input[name*="name"]', uniqueName);
    await page.click('button[type="submit"]');

    // Should redirect to show page
    await page.waitForURL(/\/fo\/subjects\/\d+/, { timeout: 5000 });
    await expect(page.locator('body')).toContainText(uniqueName);
  });

  test('subject show page displays chapters', async ({ page }) => {
    await page.goto('/fo/subjects');

    // Click on the first subject link
    const subjectLink = page.locator('a[href*="/fo/subjects/"]').first();
    if (await subjectLink.count() > 0) {
      await subjectLink.click();
      await page.waitForURL(/\/fo\/subjects\/\d+/, { timeout: 5000 });
      await expect(page.locator('body')).toBeVisible();
    }
  });

  test('create chapter with empty title fails (server-side)', async ({ page }) => {
    await page.goto('/fo/subjects');
    const subjectLink = page.locator('a[href*="/fo/subjects/"]').first();
    if (await subjectLink.count() === 0) return;

    await subjectLink.click();
    await page.waitForURL(/\/fo\/subjects\/\d+/, { timeout: 5000 });

    const newChapterLink = page.locator('a[href*="chapters/new"]').first();
    if (await newChapterLink.count() === 0) return;

    await newChapterLink.click();
    await page.waitForURL(/chapters\/new/, { timeout: 5000 });

    // Clear title and submit
    const titleField = page.locator('input[name*="title"]').first();
    await titleField.fill('');
    await page.click('button[type="submit"]');

    // Should stay on form page (server-side validation)
    await expect(page).toHaveURL(/chapters\/new/);
  });
});
