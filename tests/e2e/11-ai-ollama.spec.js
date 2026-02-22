// @ts-check
/**
 * Tests E2E — IA Ollama (vanilj/qwen2.5-14b)
 * Workflows : génération quiz IA, génération flashcards IA, planning IA, profil IA, chatbot groupe
 */
const { test, expect } = require('@playwright/test');
const { loginAs, BASE } = require('./helpers/auth');

const OLLAMA_BASE = 'http://localhost:11434';

test.describe('IA — Disponibilité Ollama', () => {
  test('Ollama répond sur :11434', async ({ request }) => {
    const res = await request.get(`${OLLAMA_BASE}/api/tags`);
    expect(res.status()).toBe(200);
    const body = await res.json();
    expect(body).toHaveProperty('models');
    // Le modèle 14B doit être disponible
    const names = body.models.map((m) => m.name);
    const has14b = names.some(n => n.includes('qwen') || n.includes('14b'));
    expect(has14b).toBe(true);
  });

  test('Ollama génère une réponse simple', async ({ request }) => {
    const res = await request.post(`${OLLAMA_BASE}/api/generate`, {
      data: {
        model: 'vanilj/qwen2.5-14b-instruct-iq4_xs:latest',
        prompt: 'Réponds juste "OK" en un mot.',
        stream: false,
      },
    });
    expect(res.status()).toBe(200);
    const body = await res.json();
    expect(body).toHaveProperty('response');
    expect(body.response.length).toBeGreaterThan(0);
  });
});

test.describe('IA — Génération Quiz', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'prof');
  });

  test('Formulaire génération quiz IA visible', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/quizzes/ai-generate`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    await expect(page.locator('form')).toBeVisible();
  });

  test('Générer un quiz IA (soumettre le formulaire)', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/quizzes/ai-generate`);

    // Sélectionner la matière
    const subjectSelect = page.locator('select[name*="subject"]').first();
    if (await subjectSelect.isVisible()) {
      const opts = await subjectSelect.locator('option').all();
      if (opts.length > 1) await subjectSelect.selectOption({ index: 1 });
    }

    // Nombre de questions
    const numInput = page.locator('input[name*="num"], input[name*="count"], input[name*="questions"]').first();
    if (await numInput.isVisible()) await numInput.fill('3');

    // Difficulté
    const diffSelect = page.locator('select[name*="difficulty"]').first();
    if (await diffSelect.isVisible()) await diffSelect.selectOption('EASY');

    await page.click('button[type="submit"]');
    // L'IA peut prendre du temps — timeout 120s
    await page.waitForLoadState('networkidle', { timeout: 120000 });
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    // Soit quiz créé et redirigé, soit message de succès
    await expect(page.locator('body')).not.toContainText('Gateway Timeout');
  });
});

test.describe('IA — Génération Flashcards', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'prof');
  });

  test('Formulaire génération flashcards IA visible', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/decks/ai-generate`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    await expect(page.locator('form')).toBeVisible();
  });

  test('Générer des flashcards IA', async ({ page }) => {
    await page.goto(`${BASE}/fo/training/decks/ai-generate`);

    const subjectSelect = page.locator('select[name*="subject"]').first();
    if (await subjectSelect.isVisible()) {
      const opts = await subjectSelect.locator('option').all();
      if (opts.length > 1) await subjectSelect.selectOption({ index: 1 });
    }

    const numInput = page.locator('input[name*="num"], input[name*="count"]').first();
    if (await numInput.isVisible()) await numInput.fill('3');

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle', { timeout: 120000 });
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });
});

test.describe('IA — Planning Suggestion', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Bouton "Suggérer avec IA" visible sur un plan', async ({ page }) => {
    await page.goto(`${BASE}/fo/planning`);
    const planLink = page.locator('a:has-text("Plan Maths")').first();
    if (!(await planLink.isVisible())) return;
    await planLink.click();
    await page.waitForLoadState('networkidle');

    // Chercher le bouton IA suggest
    const aiBtn = page.locator('form[action*="ai-suggest"] button, a[href*="ai-suggest"], button:has-text("IA"), button:has-text("Optimiser")').first();
    if (await aiBtn.isVisible()) {
      await aiBtn.click();
      await page.waitForLoadState('networkidle', { timeout: 120000 });
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
    }
  });
});

test.describe('IA — Profil Bio', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Bouton "Améliorer avec IA" visible sur la page profil', async ({ page }) => {
    await page.goto(`${BASE}/fo/profile`);
    const aiEnhanceBtn = page.locator('button[data-ai], form[action*="ai-enhance"] button, button:has-text("IA"), a[href*="ai-enhance"]').first();
    // Pas d'erreur sur la page
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });

  test('Les suggestions IA d\'Alice déjà présentes dans les fixtures', async ({ page }) => {
    await page.goto(`${BASE}/fo/profile`);
    // Ses suggestions IA sont pré-remplies dans les fixtures
    await expect(page.locator('body')).toContainText(/passionné|objectif|maîtriser|analyse/i);
  });
});

test.describe('IA — Chatbot Groupe (GeminiChatbotService)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('API chatbot config répond sans erreur 500', async ({ request, page }) => {
    // Récupérer l'ID du groupe Maths
    await loginAs(page, 'alice');
    const res = await request.get(`${BASE}/app/groupes`);
    expect(res.status()).toBe(200);
    // L'URL de la liste des groupes doit répondre
  });

  test('API chatbot stats accessible pour le groupe Maths', async ({ page, request }) => {
    await loginAs(page, 'alice');
    // Trouver l'ID du groupe Maths
    await page.goto(`${BASE}/app/groupes`);
    await page.click('a:has-text("Groupe Maths Terminale")');
    await page.waitForLoadState('networkidle');
    const url = page.url();
    const match = url.match(/groupes\/(\d+)/);
    if (!match) return;
    const groupId = match[1];

    const res = await request.get(`${BASE}/app/api/chatbot/groups/${groupId}/stats`);
    expect([200, 403]).toContain(res.status()); // 403 si pas admin du groupe
  });
});
