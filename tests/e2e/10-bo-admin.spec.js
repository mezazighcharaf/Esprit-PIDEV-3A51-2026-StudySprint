// @ts-check
/**
 * Tests E2E — Back-Office Admin
 * Workflows : dashboard, users CRUD, matières BO, quiz BO, groupes BO, certifications, AI monitoring
 */
const { test, expect } = require('@playwright/test');
const { loginAs, BASE } = require('./helpers/auth');

test.describe('BO — Accès et Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'admin');
  });

  test('Dashboard BO accessible (/admin)', async ({ page }) => {
    await page.goto(`${BASE}/admin`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    await expect(page.locator('body')).toContainText(/dashboard|studysprint|admin/i);
  });

  test('Dashboard BO — statistiques affichées', async ({ page }) => {
    await page.goto(`${BASE}/admin`);
    // Doit afficher des chiffres (users, quiz, groupes)
    await expect(page.locator('body')).toContainText(/utilisateur|quiz|groupe|matière/i);
  });

  test('Analytics accessible', async ({ page }) => {
    await page.goto(`${BASE}/admin/analytics`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });

  test('Section Encadrement (groupes) dans /admin/mentoring', async ({ page }) => {
    await page.goto(`${BASE}/admin/mentoring`);
    await expect(page.locator('body')).toContainText(/groupe|encadrement|group/i);
  });
});

test.describe('BO — Gestion Utilisateurs', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'admin');
  });

  test('Liste des utilisateurs — tous les utilisateurs des fixtures', async ({ page }) => {
    await page.goto(`${BASE}/bo/users`);
    await expect(page.locator('body')).toContainText('alice.martin@studysprint.local');
    await expect(page.locator('body')).toContainText('bob.dupont@studysprint.local');
    await expect(page.locator('body')).toContainText('prof.claire@studysprint.local');
  });

  test('Détail d\'un utilisateur accessible', async ({ page }) => {
    await page.goto(`${BASE}/bo/users`);
    const firstLink = page.locator('a[href*="/bo/users/"]').first();
    if (await firstLink.isVisible()) {
      await firstLink.click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
    }
  });

  test('Exporter les utilisateurs en CSV', async ({ page }) => {
    await page.goto(`${BASE}/bo/users/export`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });

  test('Créer un utilisateur via BO', async ({ page }) => {
    await page.goto(`${BASE}/bo/users/new`);
    await expect(page.locator('form')).toBeVisible();
    // Remplir le formulaire
    const emailInput = page.locator('input[name*="email"]').first();
    if (await emailInput.isVisible()) {
      await emailInput.fill('newuser.e2e@studysprint.local');
    }
    const nomInput = page.locator('input[name*="nom"]').first();
    if (await nomInput.isVisible()) await nomInput.fill('E2E');
    const prenomInput = page.locator('input[name*="prenom"]').first();
    if (await prenomInput.isVisible()) await prenomInput.fill('Test');
    const pwdInput = page.locator('input[type="password"]').first();
    if (await pwdInput.isVisible()) await pwdInput.fill('Test1234!');

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });
});

test.describe('BO — Gestion Matières', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'admin');
  });

  test('Liste matières BO', async ({ page }) => {
    await page.goto(`${BASE}/bo/subjects`);
    await expect(page.locator('body')).toContainText('Mathématiques Avancées');
    await expect(page.locator('body')).toContainText('Physique Quantique');
  });

  test('Détail matière BO', async ({ page }) => {
    await page.goto(`${BASE}/bo/subjects`);
    const link = page.locator('a[href*="/bo/subjects/"]').first();
    if (await link.isVisible()) {
      await link.click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
    }
  });

  test('Créer une matière via BO', async ({ page }) => {
    await page.goto(`${BASE}/bo/subjects/new`);
    const nameInput = page.locator('input[name*="name"]').first();
    if (await nameInput.isVisible()) await nameInput.fill('Matière BO E2E');
    const codeInput = page.locator('input[name*="code"]').first();
    if (await codeInput.isVisible()) await codeInput.fill('BOE2E01');
    // Sélectionner un créateur si nécessaire
    const creatorSelect = page.locator('select[name*="createdBy"]').first();
    if (await creatorSelect.isVisible()) {
      const opts = await creatorSelect.locator('option').all();
      if (opts.length > 1) await creatorSelect.selectOption({ index: 1 });
    }
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });
});

test.describe('BO — Gestion Quiz', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'admin');
  });

  test('Liste quiz BO', async ({ page }) => {
    await page.goto(`${BASE}/bo/quizzes`);
    await expect(page.locator('body')).toContainText('Intégrales');
    await expect(page.locator('body')).toContainText('Dérivées');
  });

  test('Détail quiz BO', async ({ page }) => {
    await page.goto(`${BASE}/bo/quizzes`);
    const link = page.locator('a[href*="/bo/quizzes/"]').first();
    if (await link.isVisible()) {
      await link.click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
    }
  });
});

test.describe('BO — Gestion Groupes', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'admin');
  });

  test('Liste groupes BO', async ({ page }) => {
    await page.goto(`${BASE}/bo/groups`);
    await expect(page.locator('body')).toContainText(/groupe maths|physique prépa/i);
  });
});

test.describe('BO — Certifications', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'admin');
  });

  test('Liste des demandes de certification', async ({ page }) => {
    await page.goto(`${BASE}/bo/certifications`);
    // Alice a une demande "pending" dans les fixtures
    await expect(page.locator('body')).toContainText(/alice|certification|demande|pending/i);
  });

  test('Détail certification d\'Alice', async ({ page }) => {
    await page.goto(`${BASE}/bo/certifications`);
    const link = page.locator('a[href*="/bo/certifications/"]').first();
    if (await link.isVisible()) {
      await link.click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
      await expect(page.locator('body')).toContainText(/motivation|alice|certific/i);
    }
  });

  test('Approuver une demande de certification', async ({ page }) => {
    await page.goto(`${BASE}/bo/certifications`);
    const link = page.locator('a[href*="/bo/certifications/"]').first();
    if (!(await link.isVisible())) return;
    await link.click();
    await page.waitForLoadState('networkidle');

    const approveBtn = page.locator('form[action*="approve"] button, button:has-text("Approuver")').first();
    if (await approveBtn.isVisible()) {
      await approveBtn.click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).not.toContainText('Internal Server Error');
    }
  });
});

test.describe('BO — AI Monitoring', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'admin');
  });

  test('Dashboard AI Monitoring accessible', async ({ page }) => {
    await page.goto(`${BASE}/bo/ai-monitoring`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    await expect(page.locator('body')).toContainText(/ia|génération|log|modèle/i);
  });

  test('Logs de génération IA affichés', async ({ page }) => {
    await page.goto(`${BASE}/bo/ai-monitoring/logs`);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    // 6 logs dans les fixtures
    await expect(page.locator('body')).toContainText(/quiz|flashcard|profile|planning/i);
  });
});

test.describe('BO — Accès refusé', () => {
  test('Étudiant ne peut pas accéder au BO (/bo/users)', async ({ page }) => {
    await loginAs(page, 'alice');
    await page.goto(`${BASE}/bo/users`);
    await page.waitForLoadState('networkidle');
    // Doit être redirigé ou voir une erreur 403
    await expect(page).not.toHaveURL(`${BASE}/bo/users`);
  });

  test('Professeur ne peut pas accéder au BO', async ({ page }) => {
    await loginAs(page, 'prof');
    await page.goto(`${BASE}/bo/users`);
    await page.waitForLoadState('networkidle');
    await expect(page).not.toHaveURL(`${BASE}/bo/users`);
  });
});
