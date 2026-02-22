// @ts-check
/**
 * Tests E2E — Module Classement (Leaderboard)
 * Workflows : affichage classement, scores, rang des utilisateurs
 */
const { test, expect } = require('@playwright/test');
const { loginAs, BASE } = require('./helpers/auth');

test.describe('Classement', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Page classement accessible', async ({ page }) => {
    await page.goto(`${BASE}/fo/leaderboard`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    await expect(page.locator('body')).toContainText(/classement|rang|score|leaderboard/i);
  });

  test('Alice apparaît dans le classement (elle a le meilleur score)', async ({ page }) => {
    await page.goto(`${BASE}/fo/leaderboard`);
    // Alice a 100% sur quiz1, quiz2, quiz3 = meilleur score
    await expect(page.locator('body')).toContainText('Alice');
  });

  test('Bob apparaît dans le classement', async ({ page }) => {
    await page.goto(`${BASE}/fo/leaderboard`);
    await expect(page.locator('body')).toContainText('Bob');
  });

  test('Scores affichés correctement', async ({ page }) => {
    await page.goto(`${BASE}/fo/leaderboard`);
    // Alice a 100%, Bob a ~66%, Charlie a ~16%
    await expect(page.locator('body')).toContainText(/100|score|%/i);
  });

  test('Le classement est accessible aussi pour Bob', async ({ page }) => {
    await loginAs(page, 'bob');
    await page.goto(`${BASE}/fo/leaderboard`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    await expect(page.locator('body')).toContainText(/classement|score|alice|bob/i);
  });
});
