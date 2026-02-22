// @ts-check
/**
 * Tests E2E — APIs Externes
 * Workflows : Wikipedia (matières), Dictionary API (flashcards), LibreTranslate
 */
const { test, expect } = require('@playwright/test');
const { loginAs, BASE } = require('./helpers/auth');

test.describe('API Externe — Wikipedia', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Page matière Mathématiques — bloc Wikipedia rendu sans erreur', async ({ page }) => {
    await page.goto(`${BASE}/fo/subjects`);
    await page.click('a:has-text("Mathématiques Avancées")');
    await page.waitForLoadState('networkidle');
    // La page ne doit pas planter même si Wikipedia est unreachable
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    await expect(page.locator('body')).not.toContainText('An Error Occurred');
    await expect(page.locator('body')).toContainText(/mathématiques|suites|intégrales/i);
  });

  test('Page matière Physique — bloc Wikipedia', async ({ page }) => {
    await page.goto(`${BASE}/fo/subjects`);
    await page.click('a:has-text("Physique Quantique")');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });

  test('Wikipedia API directe répond', async ({ request }) => {
    const res = await request.get('https://fr.wikipedia.org/api/rest_v1/page/summary/Mathématiques');
    // Peut échouer si offline — on accepte 200 ou timeout
    if (res.status() === 200) {
      const body = await res.json();
      expect(body).toHaveProperty('extract');
    }
  });
});

test.describe('API Externe — DictionaryAPI', () => {
  test('GET /api/dictionary/mathematics → 200 ou 404', async ({ request }) => {
    const res = await request.get(`${BASE}/api/dictionary/mathematics`);
    expect([200, 404]).toContain(res.status());
    if (res.status() === 200) {
      const body = await res.json();
      expect(body).toHaveProperty('word');
      expect(body.word).toBe('mathematics');
    }
  });

  test('GET /api/dictionary/derivative → réponse valide', async ({ request }) => {
    const res = await request.get(`${BASE}/api/dictionary/derivative`);
    expect([200, 404]).toContain(res.status());
    expect(res.status()).not.toBe(500);
  });

  test('GET /api/dictionary/xyz_inexistant → 404 sans erreur 500', async ({ request }) => {
    const res = await request.get(`${BASE}/api/dictionary/xyz_inexistant_word`);
    expect(res.status()).not.toBe(500);
  });
});

test.describe('API Externe — LibreTranslate', () => {
  test('POST /api/translate fr→en', async ({ request }) => {
    const res = await request.post(`${BASE}/api/translate`, {
      data: { text: 'Bonjour le monde', source: 'fr', target: 'en' },
    });
    // LibreTranslate public peut être limité ou offline → accepter 200 / 422 / 503
    expect([200, 422, 503]).toContain(res.status());
    if (res.status() === 200) {
      const body = await res.json();
      expect(body).toHaveProperty('translatedText');
    }
  });

  test('POST /api/translate es→fr', async ({ request }) => {
    const res = await request.post(`${BASE}/api/translate`, {
      data: { text: 'Hola mundo', source: 'es', target: 'fr' },
    });
    expect([200, 422, 503]).toContain(res.status());
    expect(res.status()).not.toBe(500);
  });

  test('POST /api/translate sans texte → erreur propre (pas 500)', async ({ request }) => {
    const res = await request.post(`${BASE}/api/translate`, {
      data: { text: '', source: 'fr', target: 'en' },
    });
    expect(res.status()).not.toBe(500);
  });
});
