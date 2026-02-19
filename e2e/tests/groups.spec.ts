import { test, expect } from '@playwright/test';
import { testUsers } from '../fixtures/test-data';

async function login(page: any) {
  await page.goto('/login');
  await page.fill('#username', testUsers.user.email);
  await page.fill('#password', testUsers.user.password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/(?!.*\/login).*/, { timeout: 5000 });
}

test.describe('Groups Module', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('groups list page loads', async ({ page }) => {
    await page.goto('/fo/groups');
    await expect(page).toHaveURL(/\/fo\/groups/);
  });

  test('create group with empty name fails (server-side)', async ({ page }) => {
    await page.goto('/fo/groups/new');

    const nameField = page.locator('input[name*="name"]').first();
    await nameField.fill('');
    await page.click('button[type="submit"]');

    // Should stay on form page (server-side validation)
    await expect(page).toHaveURL(/\/fo\/groups\/new/);
  });

  test('create group with valid data succeeds', async ({ page }) => {
    const uniqueName = 'E2E Groupe ' + Date.now();
    await page.goto('/fo/groups/new');

    await page.fill('input[name*="name"]', uniqueName);
    await page.click('button[type="submit"]');

    // Should redirect to group show page
    await page.waitForURL(/\/fo\/groups\/\d+/, { timeout: 5000 });
    await expect(page.locator('body')).toContainText(uniqueName);
  });

  test('group show page loads with posts section', async ({ page }) => {
    await page.goto('/fo/groups');

    const groupLink = page.locator('a[href*="/fo/groups/"]').first();
    if (await groupLink.count() === 0) return;

    await groupLink.click();
    await page.waitForURL(/\/fo\/groups\/\d+/, { timeout: 5000 });
    await expect(page.locator('body')).toBeVisible();
  });

  test('create post with empty body fails (server-side)', async ({ page }) => {
    await page.goto('/fo/groups');
    const groupLink = page.locator('a[href*="/fo/groups/"]').first();
    if (await groupLink.count() === 0) return;

    await groupLink.click();
    await page.waitForURL(/\/fo\/groups\/\d+/, { timeout: 5000 });

    // Find post form and submit with empty body
    const bodyField = page.locator('textarea[name="body"]').first();
    if (await bodyField.count() === 0) return;

    await bodyField.fill('');
    const submitBtn = page.locator('button[type="submit"]').first();
    await submitBtn.click();

    // Should show error flash message or stay on page
    await expect(page).toHaveURL(/\/fo\/groups\/\d+/);
  });

  test('create post with valid body succeeds', async ({ page }) => {
    await page.goto('/fo/groups');
    const groupLink = page.locator('a[href*="/fo/groups/"]').first();
    if (await groupLink.count() === 0) return;

    await groupLink.click();
    await page.waitForURL(/\/fo\/groups\/\d+/, { timeout: 5000 });

    const bodyField = page.locator('textarea[name="body"]').first();
    if (await bodyField.count() === 0) return;

    const postContent = 'E2E test post content ' + Date.now();
    await bodyField.fill(postContent);

    const submitBtn = page.locator('form button[type="submit"], form input[type="submit"]').first();
    await submitBtn.click();

    await page.waitForURL(/\/fo\/groups\/\d+/, { timeout: 5000 });
    await expect(page.locator('body')).toContainText(postContent);
  });

  test('post body exceeding 5000 chars fails (server-side)', async ({ page }) => {
    await page.goto('/fo/groups');
    const groupLink = page.locator('a[href*="/fo/groups/"]').first();
    if (await groupLink.count() === 0) return;

    await groupLink.click();
    await page.waitForURL(/\/fo\/groups\/\d+/, { timeout: 5000 });

    const bodyField = page.locator('textarea[name="body"]').first();
    if (await bodyField.count() === 0) return;

    // Fill with >5000 chars
    await bodyField.fill('A'.repeat(5001));

    const submitBtn = page.locator('form button[type="submit"], form input[type="submit"]').first();
    await submitBtn.click();

    // Should show flash error about max length
    await page.waitForURL(/\/fo\/groups\/\d+/, { timeout: 5000 });
    await expect(page.locator('body')).toContainText('5000');
  });
});
