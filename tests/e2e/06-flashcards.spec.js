// @ts-check
/**
 * Tests E2E — Module Flashcards / Decks
 * Workflows : liste decks, révision SM-2 (flip → grade), création deck, API Dictionary, API Translate
 */
const { test, expect } = require('@playwright/test');
const { loginAs, BASE } = require('./helpers/auth');

test.describe('Flashcards — Consultation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Liste des decks — decks des fixtures visibles', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/decks`);
    await expect(page.locator('body')).toContainText('Formules Mathématiques Essentielles');
    await expect(page.locator('body')).toContainText('Constantes et Relations Physiques');
  });

  test('Détail d\'un deck — flashcards affichées', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/decks`);
    await page.click('a:has-text("Formules Mathématiques Essentielles")');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/dérivée|cos|sin|formule/i);
  });
});

test.describe('Flashcards — Révision SM-2', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Démarrer une session de révision', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/decks`);
    // Cliquer sur "Réviser" du premier deck
    const reviewBtn = page.locator('a[href*="/review"]').first();
    if (await reviewBtn.isVisible()) {
      await reviewBtn.click();
      await page.waitForLoadState('networkidle');
      // On doit voir une carte à réviser ou "aucune carte due"
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
    }
  });

  test('Workflow révision complet : flip → grade "Bien"', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/decks`);
    const reviewBtn = page.locator('a[href*="/review"]').first();
    if (!(await reviewBtn.isVisible())) return;
    await reviewBtn.click();
    await page.waitForLoadState('networkidle');

    // S'il y a des cartes à réviser
    const flipBtn = page.locator('button:has-text("Voir la réponse"), button[data-action*="flip"], .flip-btn').first();
    if (!(await flipBtn.isVisible())) return; // Pas de carte due

    // 1. Retourner la carte
    await flipBtn.click();
    await page.waitForTimeout(500);

    // 2. Évaluer avec "Bien" (grade=good)
    const goodBtn = page.locator('button[value="good"], button:has-text("Bien")').first();
    if (await goodBtn.isVisible()) {
      await goodBtn.click();
      await page.waitForLoadState('networkidle');
      // Soit carte suivante, soit page "session terminée"
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
    }
  });

  test('Workflow révision : grade "Difficile"', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/decks`);
    const reviewBtn = page.locator('a[href*="/review"]').first();
    if (!(await reviewBtn.isVisible())) return;
    await reviewBtn.click();
    await page.waitForLoadState('networkidle');

    const flipBtn = page.locator('button:has-text("Voir la réponse"), button[data-action*="flip"]').first();
    if (!(await flipBtn.isVisible())) return;
    await flipBtn.click();
    await page.waitForTimeout(500);

    const hardBtn = page.locator('button[value="hard"], button:has-text("Difficile")').first();
    if (await hardBtn.isVisible()) {
      await hardBtn.click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
    }
  });

  test('Workflow révision : grade "À revoir" (again)', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/decks`);
    const reviewBtn = page.locator('a[href*="/review"]').first();
    if (!(await reviewBtn.isVisible())) return;
    await reviewBtn.click();
    await page.waitForLoadState('networkidle');

    const flipBtn = page.locator('button:has-text("Voir la réponse"), button[data-action*="flip"]').first();
    if (!(await flipBtn.isVisible())) return;
    await flipBtn.click();
    await page.waitForTimeout(500);

    const againBtn = page.locator('button[value="again"], button:has-text("À revoir"), button:has-text("Revoir")').first();
    if (await againBtn.isVisible()) {
      await againBtn.click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
    }
  });
});

test.describe('Flashcards — Gestion (Professeur)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'prof');
  });

  test('Professeur voit ses decks dans "Mes decks"', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/decks/manage/my-decks`);
    await expect(page.locator('body')).toContainText('Formules Mathématiques Essentielles');
  });

  test('Formulaire création deck accessible', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/decks/manage/new`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    await expect(page.locator('input[name*="title"], input[name*="name"]').first()).toBeVisible();
  });

  test('Créer un nouveau deck', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/decks/manage/new`);
    const titleInput = page.locator('input[name*="title"]').or(page.locator('input[name*="name"]')).first();
    if (!(await titleInput.isVisible())) return;
    await titleInput.fill('Deck E2E Playwright');

    const subjectSelect = page.locator('select[name*="subject"]').first();
    if (await subjectSelect.isVisible()) {
      const options = await subjectSelect.locator('option').all();
      if (options.length > 1) await subjectSelect.selectOption({ index: 1 });
    }

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });

  test('Générer des flashcards par IA', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/decks/ai-generate`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    await expect(page.locator('body')).toContainText(/générer|ia|sujet|deck/i);
  });
});

test.describe('Flashcards — API Dictionary (externe)', () => {
  test('GET /api/dictionary/{word} → JSON valide', async ({ request }) => {
    const res = await request.get(`${BASE}/api/dictionary/mathematics`);
    expect([200, 404]).toContain(res.status());
    if (res.status() === 200) {
      const body = await res.json();
      expect(body).toHaveProperty('word');
    }
  });

  test('GET /api/dictionary/bonjour → réponse sans erreur 500', async ({ request }) => {
    const res = await request.get(`${BASE}/api/dictionary/bonjour`);
    expect(res.status()).not.toBe(500);
  });
});

test.describe('Flashcards — API LibreTranslate (externe)', () => {
  test('POST /api/translate → réponse JSON', async ({ request }) => {
    const res = await request.post(`${BASE}/api/translate`, {
      data: { text: 'Bonjour le monde', source: 'fr', target: 'en' },
    });
    // LibreTranslate peut être offline → 503 acceptable
    expect([200, 422, 503]).toContain(res.status());
    if (res.status() === 200) {
      const body = await res.json();
      expect(body).toHaveProperty('translatedText');
    }
  });
});
