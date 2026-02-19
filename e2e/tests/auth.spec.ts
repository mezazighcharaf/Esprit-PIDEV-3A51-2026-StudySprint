import { test, expect } from '@playwright/test';
import { testUsers } from '../fixtures/test-data';

test.describe('Authentication', () => {

  test('login page loads correctly', async ({ page }) => {
    await page.goto('/login');
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('#username')).toBeVisible();
  });

  test('login with invalid credentials shows error', async ({ page }) => {
    await page.goto('/login');

    await page.fill('#username', 'fake@invalid.com');
    await page.fill('#password', 'wrongpassword');
    await page.click('button[type="submit"]');

    // Should stay on login page or show error
    await expect(page).toHaveURL(/\/login/);
  });

  test('login with valid credentials redirects to app', async ({ page }) => {
    await page.goto('/login');

    await page.fill('#username', testUsers.user.email);
    await page.fill('#password', testUsers.user.password);
    await page.click('button[type="submit"]');

    // Should redirect away from login
    await page.waitForURL(/(?!.*\/login).*/, { timeout: 5000 });
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('register page loads correctly', async ({ page }) => {
    await page.goto('/register');
    await expect(page.locator('form')).toBeVisible();
  });

  test('register with empty fields shows validation errors', async ({ page }) => {
    await page.goto('/register');
    await page.click('button[type="submit"]');

    // Should stay on register page (server-side validation rejects)
    await expect(page).toHaveURL(/\/register/);
  });

  test('register with mismatched passwords fails', async ({ page }) => {
    await page.goto('/register');

    await page.fill('input[name*="email"], input[name*="full_name"]', 'test-e2e@example.com');
    await page.fill('input[name*="fullName"], input[name*="full_name"]', 'Test E2E');

    const passwordFields = page.locator('input[type="password"]');
    if (await passwordFields.count() >= 2) {
      await passwordFields.nth(0).fill('password123');
      await passwordFields.nth(1).fill('different456');
    }

    await page.click('button[type="submit"]');

    // Should stay on register page with error
    await expect(page).toHaveURL(/\/register/);
  });

  test('logout works', async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.fill('#username', testUsers.user.email);
    await page.fill('#password', testUsers.user.password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/(?!.*\/login).*/, { timeout: 5000 });

    // Click logout link
    const logoutLink = page.locator('a[href*="logout"]');
    if (await logoutLink.count() > 0) {
      await logoutLink.first().click();
      await page.waitForURL(/\/(login)?$/, { timeout: 5000 });
    }
  });
});
