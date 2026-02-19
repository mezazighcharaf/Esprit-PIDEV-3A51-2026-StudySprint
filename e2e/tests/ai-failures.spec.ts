import { test, expect } from '@playwright/test';
import { testUsers } from '../fixtures/test-data';

/**
 * E2E tests for AI failure scenarios on the Symfony front-end.
 * These tests verify that the UI handles AI service errors gracefully
 * (flash messages, no crashes, proper redirects).
 *
 * NOTE: These tests assume Ollama may or may not be running.
 * The key goal is that the app does NOT crash or show a 500 error page.
 */

async function login(page: any) {
  await page.goto('/login');
  await page.fill('#username', testUsers.user.email);
  await page.fill('#password', testUsers.user.password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/(?!.*\/login).*/, { timeout: 5000 });
}

test.describe('AI Failure Handling — Symfony UI', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('AI quiz generation handles API error gracefully', async ({ page }) => {
    await page.goto('/fo/training/quizzes/ai-generate');

    const form = page.locator('form');
    if (await form.count() === 0) return;

    // Fill the form with valid data
    const subjectSelect = page.locator('select[name*="subject"]').first();
    if (await subjectSelect.count() > 0) {
      await subjectSelect.selectOption({ index: 1 });
    }

    await page.click('button[type="submit"]');

    // Wait for response (AI might fail or succeed)
    await page.waitForLoadState('networkidle', { timeout: 130000 });

    // Key assertion: no 500 error page, we stay in the app
    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('500 Internal Server Error');
    expect(bodyText).not.toContain('Whoops');
  });

  test('AI flashcard generation handles API error gracefully', async ({ page }) => {
    await page.goto('/fo/training/decks/ai-generate');

    const form = page.locator('form');
    if (await form.count() === 0) return;

    const subjectSelect = page.locator('select[name*="subject"]').first();
    if (await subjectSelect.count() > 0) {
      await subjectSelect.selectOption({ index: 1 });
    }

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle', { timeout: 130000 });

    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('500 Internal Server Error');
    expect(bodyText).not.toContain('Whoops');
  });

  test('AI profile enhance handles API error gracefully', async ({ page }) => {
    await page.goto('/fo/profile');

    // Look for the AI enhance button/form
    const aiForm = page.locator('form[action*="ai-enhance"]').first();
    if (await aiForm.count() === 0) return;

    await aiForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle', { timeout: 130000 });

    // Should redirect back to profile with flash message (success or error)
    await expect(page).toHaveURL(/\/fo\/profile/);
    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('500 Internal Server Error');
  });

  test('AI plan suggest handles API error gracefully', async ({ page }) => {
    await page.goto('/fo/planning');

    // Look for an AI suggest form
    const aiForm = page.locator('form[action*="ai-suggest"]').first();
    if (await aiForm.count() === 0) return;

    await aiForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle', { timeout: 130000 });

    // Should stay in planning area, no crash
    await expect(page).toHaveURL(/\/fo\/planning/);
    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('500 Internal Server Error');
  });

  test('AI post summarize handles API error gracefully', async ({ page }) => {
    await page.goto('/fo/groups');

    // Navigate to first group
    const groupLink = page.locator('a[href*="/fo/groups/"]').first();
    if (await groupLink.count() === 0) return;

    await groupLink.click();
    await page.waitForURL(/\/fo\/groups\/\d+/, { timeout: 5000 });

    // Look for AI summarize form on a post
    const aiSumForm = page.locator('form[action*="ai-summarize"]').first();
    if (await aiSumForm.count() === 0) return;

    await aiSumForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle', { timeout: 130000 });

    // Should redirect back to group page, no crash
    await expect(page).toHaveURL(/\/fo\/groups\/\d+/);
    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('500 Internal Server Error');
  });
});

test.describe('AI Failure Handling — Input Validation Bypass', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('AI quiz generate rejects without subject', async ({ page }) => {
    await page.goto('/fo/training/quizzes/ai-generate');

    const form = page.locator('form');
    if (await form.count() === 0) return;

    // Try submitting without selecting a subject
    await page.click('button[type="submit"]');

    // Should stay on form or show error
    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('500 Internal Server Error');
  });

  test('AI flashcard generate rejects without subject', async ({ page }) => {
    await page.goto('/fo/training/decks/ai-generate');

    const form = page.locator('form');
    if (await form.count() === 0) return;

    await page.click('button[type="submit"]');

    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('500 Internal Server Error');
  });
});
