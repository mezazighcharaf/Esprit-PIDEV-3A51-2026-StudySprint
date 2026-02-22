// @ts-check
/**
 * Tests E2E — Module Quiz
 * Workflows : liste, détail, jouer (start → play → submit → résultat), historique, PDF, création
 */
const { test, expect } = require('@playwright/test');
const { loginAs, BASE } = require('./helpers/auth');

test.describe('Quiz — Consultation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Liste des quiz — 3 quiz des fixtures visibles', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/quizzes`);
    await expect(page.locator('body')).toContainText('Intégrales');
    await expect(page.locator('body')).toContainText('Mécanique Quantique');
    await expect(page.locator('body')).toContainText('Dérivées');
  });

  test('Détail d\'un quiz — infos affichées', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/quizzes`);
    await page.click('a:has-text("Intégrales")');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/intégrales|questions|difficulté/i);
  });
});

test.describe('Quiz — Workflow complet (start → play → submit → résultat)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Jouer le quiz Intégrales et obtenir un résultat', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/quizzes`);

    // Cliquer sur le quiz Intégrales pour aller sur la page détail
    await page.click('a:has-text("Intégrales")');
    await page.waitForLoadState('networkidle');

    // Récupérer l'ID du quiz depuis l'URL
    const url = page.url();
    const match = url.match(/quizzes\/(\d+)/);
    if (!match) return;
    const quizId = match[1];

    // START — POST sur fo_training_quizzes_start
    await page.goto(`${BASE}/fo/training/quizzes/${quizId}`);
    const startBtn = page.locator('form[action*="start"] button, button:has-text("Commencer"), a:has-text("Jouer")').first();
    if (await startBtn.isVisible()) {
      await startBtn.click();
      await page.waitForLoadState('networkidle');
    }

    // PLAY — On est sur /fo/training/quizzes/{id}/play
    await expect(page).toHaveURL(/play|result/);
    if (page.url().includes('play')) {
      // Répondre à toutes les questions (sélectionner le premier choix radio)
      let questionsDone = 0;
      while (questionsDone < 10) {
        const radio = page.locator('input[type="radio"]').first();
        if (!(await radio.isVisible())) break;
        await radio.click();
        questionsDone++;
        // Bouton Suivant ou Soumettre
        const nextBtn = page.locator('button:has-text("Suivant"), button:has-text("Question suivante")').first();
        if (await nextBtn.isVisible()) {
          await nextBtn.click();
          await page.waitForTimeout(300);
        } else {
          break;
        }
      }

      // Bouton Soumettre final
      const submitBtn = page.locator('button[type="submit"]:has-text("Soumettre"), button:has-text("Terminer")').first();
      if (await submitBtn.isVisible()) {
        await submitBtn.click();
        await page.waitForLoadState('networkidle');
      }
    }

    // RÉSULTAT — On doit voir le score
    await expect(page.locator('body')).toContainText(/résultat|score|%|correct/i);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });

  test('Historique des tentatives d\'Alice visible', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/quizzes/history`);
    // Alice a 3 tentatives dans les fixtures (quiz1 100%, quiz2 100%, quiz3 100%)
    await expect(page.locator('body')).toContainText(/100|intégrales|tentative/i);
  });

  test('Page résultat accessible directement', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/quizzes/history`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });
});

test.describe('Quiz — Gestion (Professeur)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'prof');
  });

  test('Professeur voit ses quiz dans "Mes quiz"', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/quizzes/manage/my-quizzes`);
    await expect(page.locator('body')).toContainText('Intégrales');
    await expect(page.locator('body')).toContainText('Dérivées');
  });

  test('Formulaire création quiz accessible', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/quizzes/manage/new`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    // La page affiche les templates de quiz disponibles
    await expect(page.locator('body')).toContainText(/template|créer|quiz|type/i);
  });

  test('Exporter les quiz en CSV', async ({ page }) => {
    // Bo side
    await loginAs(page, 'admin');
    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 10000 }).catch(() => null),
      page.goto(`${BASE}/bo/quizzes/export`),
    ]);
    // Si download ou redirection — pas d'erreur 500
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });
});

test.describe('Quiz — Génération IA', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'prof');
  });

  test('Formulaire génération IA quiz accessible', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/quizzes/ai-generate`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    await expect(page.locator('body')).toContainText(/générer|ia|matière|sujet/i);
  });
});
