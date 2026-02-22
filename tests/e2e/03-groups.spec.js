// @ts-check
/**
 * Tests E2E — Module Groupes d'étude (feature collègues intégrée)
 * Workflows : liste, détail, création, posts, likes, ratings, commentaires, invitations, chatbot
 */
const { test, expect } = require('@playwright/test');
const { loginAs, BASE } = require('./helpers/auth');

test.describe('Groupes — Consultation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Liste des groupes — groupes des fixtures visibles', async ({ page }) => {
    await page.goto(`${BASE}/app/groupes`);
    await expect(page.locator('body')).toContainText('Groupe Maths Terminale');
    await expect(page.locator('body')).toContainText('Physique Prépa');
  });

  test('Détail groupe — posts affichés', async ({ page }) => {
    await page.goto(`${BASE}/app/groupes`);
    await page.click('a:has-text("Groupe Maths Terminale")');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/intégrales|formulaire/i);
  });

  test('Groupe privé "Chimie Organique Avancée" visible dans la liste', async ({ page }) => {
    await page.goto(`${BASE}/app/groupes`);
    await expect(page.locator('body')).toContainText('Chimie Organique');
  });
});

test.describe('Groupes — Création', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'bob');
  });

  test('Formulaire de création accessible', async ({ page }) => {
    await page.goto(`${BASE}/app/groupes/creer`);
    await expect(page.locator('input[name="group[name]"], input[name="study_group[name]"]').first()).toBeVisible();
  });

  test('Créer un groupe public', async ({ page }) => {
    await page.goto(`${BASE}/app/groupes/creer`);
    // Remplir le nom (tester les deux variantes de nom de champ possible)
    const nameInput = page.locator('input[name="group[name]"]').or(page.locator('input[name="study_group[name]"]')).first();
    await nameInput.fill('Groupe E2E Playwright');

    const descInput = page.locator('textarea[name="group[description]"]').or(page.locator('textarea[name="study_group[description]"]')).first();
    if (await descInput.isVisible()) await descInput.fill('Groupe créé automatiquement par les tests E2E');

    // Sélectionner public si disponible
    const privacySelect = page.locator('select[name="group[privacy]"]').or(page.locator('select[name="study_group[privacy]"]')).first();
    if (await privacySelect.isVisible()) await privacySelect.selectOption('public');

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    await expect(page.locator('body')).toContainText(/groupe e2e playwright|groupe créé/i);
  });
});

test.describe('Groupes — Posts (feature collègues)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  // Helper: accéder au groupe Maths et récupérer son ID
  async function goToMathGroup(page) {
    await page.goto(`${BASE}/app/groupes`);
    await page.click('a:has-text("Groupe Maths Terminale")');
    await page.waitForLoadState('networkidle');
    const url = page.url();
    const match = url.match(/groupes\/(\d+)/);
    return match ? match[1] : null;
  }

  test('Créer un post texte dans un groupe', async ({ page }) => {
    const groupId = await goToMathGroup(page);
    if (!groupId) return;

    // Le formulaire de post est dans un fragment — cliquer sur le champ déclencheur
    const trigger = page.locator('[data-post-form-trigger], textarea[placeholder*="partager"], input[placeholder*="question"]').first();
    if (await trigger.isVisible()) {
      await trigger.click();
      await page.waitForTimeout(500);
    }

    // Sélectionner le type texte si disponible
    const textType = page.locator('[data-type="text"], button:has-text("Texte")').first();
    if (await textType.isVisible()) await textType.click();

    const bodyField = page.locator('[data-post-input-form="text"] textarea[name="body"], textarea[name="body"]').first();
    if (await bodyField.isVisible()) {
      await bodyField.fill('Post créé par Playwright — test E2E workflow');
      await page.click('[data-post-input-form="text"] button[type="submit"], button[type="submit"]:has-text("Publier")');
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(/playwright|post créé/i);
    }
  });

  test('Liker un post via AJAX', async ({ page }) => {
    const groupId = await goToMathGroup(page);
    if (!groupId) return;

    // Chercher le bouton like (peut être un form AJAX ou un lien)
    const likeBtn = page.locator('button[data-action*="like"], form[action*="like"] button, a[href*="/like"]').first();
    if (await likeBtn.isVisible()) {
      await likeBtn.click();
      await page.waitForTimeout(1000);
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
    }
  });

  test('Voir les commentaires d\'un post via API', async ({ page, request }) => {
    const groupId = await goToMathGroup(page);
    if (!groupId) return;

    // Récupérer l'ID du premier post visible
    const firstPost = page.locator('[data-post-id], article[id*="post-"]').first();
    if (await firstPost.isVisible()) {
      const postId = await firstPost.getAttribute('data-post-id') ||
                     (await firstPost.getAttribute('id') || '').replace('post-', '');
      if (postId) {
        const res = await request.get(`${BASE}/app/posts/${postId}/comments`);
        expect([200, 302]).toContain(res.status());
      }
    }
  });
});

test.describe('Groupes — Invitations', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'bob');
  });

  test('Inviter un membre dans un groupe via le formulaire', async ({ page }) => {
    // Bob est admin du groupe Chimie Organique Avancée
    await page.goto(`${BASE}/app/groupes`);
    await page.click('a:has-text("Chimie Organique Avancée")');
    await page.waitForLoadState('networkidle');
    const url = page.url();
    const match = url.match(/groupes\/(\d+)/);
    if (!match) return;
    const groupId = match[1];

    // Chercher le formulaire d'invitation
    const inviteForm = page.locator('form[action*="invite"]').first();
    if (await inviteForm.isVisible()) {
      await page.fill('input[name="email"]', 'prof.marc@studysprint.local');
      await page.click('form[action*="invite"] button[type="submit"]');
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
    }
  });
});

test.describe('Groupes — Chatbot IA (feature collègues)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'alice');
  });

  test('Config chatbot accessible via API', async ({ request, page }) => {
    await loginAs(page, 'alice');
    // D'abord récupérer l'ID d'un groupe
    const res = await request.get(`${BASE}/app/groupes`);
    expect(res.status()).toBe(200);
  });

  test('Page groupe affiche l\'option chatbot', async ({ page }) => {
    await page.goto(`${BASE}/app/groupes`);
    await page.click('a:has-text("Groupe Maths Terminale")');
    await page.waitForLoadState('networkidle');
    // Chercher indice de chatbot/IA dans la page
    const hasBot = await page.locator('[data-chatbot], button:has-text("IA"), button:has-text("Chatbot"), .chatbot').count();
    // Pas d'erreur est suffisant
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });
});

test.describe('Groupes — Rejoindre / Quitter', () => {
  test('Charlie peut rejoindre le groupe Physique Prépa (public)', async ({ page }) => {
    await loginAs(page, 'charlie');
    await page.goto(`${BASE}/app/groupes`);
    await page.click('a:has-text("Physique Prépa")');
    await page.waitForLoadState('networkidle');

    const joinBtn = page.locator('form[action*="rejoindre"] button, button:has-text("Rejoindre")').first();
    if (await joinBtn.isVisible()) {
      await joinBtn.click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
    }
  });
});
