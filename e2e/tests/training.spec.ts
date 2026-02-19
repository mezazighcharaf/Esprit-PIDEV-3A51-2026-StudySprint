import { test, expect } from '@playwright/test';
import { testUsers } from '../fixtures/test-data';

async function login(page: any) {
  await page.goto('/login');
  await page.fill('#username', testUsers.user.email);
  await page.fill('#password', testUsers.user.password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/(?!.*\/login).*/, { timeout: 5000 });
}

test.describe('Training — Quiz Module', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('quiz list page loads', async ({ page }) => {
    await page.goto('/fo/training/quizzes');
    await expect(page).toHaveURL(/\/fo\/training\/quizzes/);
  });

  test('quiz show page loads', async ({ page }) => {
    await page.goto('/fo/training/quizzes');

    const quizLink = page.locator('a[href*="/fo/training/quizzes/"]').first();
    if (await quizLink.count() > 0) {
      await quizLink.click();
      await page.waitForURL(/\/fo\/training\/quizzes\/\d+/, { timeout: 5000 });
      await expect(page.locator('body')).toBeVisible();
    }
  });

  test('create quiz manually — empty title fails (server-side)', async ({ page }) => {
    await page.goto('/fo/training/quizzes/manage/new');

    const titleField = page.locator('input[name*="title"]').first();
    if (await titleField.count() > 0) {
      await titleField.fill('');
      await page.click('button[type="submit"]');
      await expect(page).toHaveURL(/manage\/new/);
    }
  });

  test('AI generate quiz page loads', async ({ page }) => {
    await page.goto('/fo/training/quizzes/ai-generate');
    await expect(page.locator('form')).toBeVisible();
  });
});

test.describe('Training — Flashcards Module', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('decks list page loads', async ({ page }) => {
    await page.goto('/fo/training/decks');
    await expect(page).toHaveURL(/\/fo\/training\/decks/);
  });

  test('deck show page loads', async ({ page }) => {
    await page.goto('/fo/training/decks');

    const deckLink = page.locator('a[href*="/fo/training/decks/"]').first();
    if (await deckLink.count() > 0) {
      await deckLink.click();
      await page.waitForURL(/\/fo\/training\/decks\/\d+/, { timeout: 5000 });
      await expect(page.locator('body')).toBeVisible();
    }
  });

  test('create deck manually — empty title fails (server-side)', async ({ page }) => {
    await page.goto('/fo/training/decks/manage/new');

    const titleField = page.locator('input[name*="title"]').first();
    if (await titleField.count() > 0) {
      await titleField.fill('');
      await page.click('button[type="submit"]');
      await expect(page).toHaveURL(/manage\/new/);
    }
  });

  test('AI generate flashcards page loads', async ({ page }) => {
    await page.goto('/fo/training/decks/ai-generate');
    await expect(page.locator('form')).toBeVisible();
  });

  test('study mode page loads for a deck', async ({ page }) => {
    await page.goto('/fo/training/decks');

    const studyLink = page.locator('a[href*="/study"]').first();
    if (await studyLink.count() > 0) {
      await studyLink.click();
      await page.waitForURL(/\/study/, { timeout: 5000 });
      await expect(page.locator('body')).toBeVisible();
    }
  });
});
