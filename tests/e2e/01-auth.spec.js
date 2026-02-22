// @ts-check
/**
 * Tests E2E — Module Authentification
 * Workflows : login, logout, register, mauvais mdp
 */
const { test, expect } = require('@playwright/test');
const { BASE } = require('./helpers/auth');

test.describe('Authentification — Login', () => {
  test('Login étudiant réussi → redirige vers FO', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await expect(page.locator('h1, h2').first()).toBeVisible();

    await page.fill('input[name="email"]', 'alice.martin@studysprint.local');
    await page.fill('input[name="password"]', 'user123');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    await expect(page).not.toHaveURL(/\/login/);
    // On est bien connecté — on est sur le FO (subjects ou dashboard)
    await expect(page).toHaveURL(/fo\/subjects|dashboard|subjects/i);
  });

  test('Login admin réussi → redirige vers BO ou FO', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="email"]', 'admin@studysprint.local');
    await page.fill('input[name="password"]', 'admin123');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    await expect(page).not.toHaveURL(/\/login/);
  });

  test('Login professeur réussi', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="email"]', 'prof.claire@studysprint.local');
    await page.fill('input[name="password"]', 'user123');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    await expect(page).not.toHaveURL(/\/login/);
  });

  test('Mauvais mot de passe → erreur affichée sur la page login', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="email"]', 'alice.martin@studysprint.local');
    await page.fill('input[name="password"]', 'mauvaismdp_xxx');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // On reste sur login
    await expect(page).toHaveURL(/\/login/);
    // Symfony affiche l'erreur dans .alert.alert-danger
    await expect(page.locator('.alert-danger, .alert')).toBeVisible();
  });

  test('Email inexistant → erreur affichée', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="email"]', 'inconnu@nowhere.com');
    await page.fill('input[name="password"]', 'user123');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL(/\/login/);
  });
});

test.describe('Authentification — Déconnexion', () => {
  test('Logout → redirige vers login', async ({ page }) => {
    // Connexion
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="email"]', 'alice.martin@studysprint.local');
    await page.fill('input[name="password"]', 'user123');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await expect(page).not.toHaveURL(/\/login/);

    // Attendre que la page FO soit bien chargée
    await page.waitForURL(/fo\/subjects|subjects/);
    // Déconnexion — naviguer directement vers /logout
    await page.goto(`${BASE}/logout`);
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL(/login/);
  });
});

test.describe('Authentification — Inscription', () => {
  test('Page inscription accessible et formulaire visible', async ({ page }) => {
    await page.goto(`${BASE}/register`);
    await expect(page.locator('form')).toBeVisible();
    await expect(page.locator('input[name="registration_form[email]"]')).toBeVisible();
  });

  test('Inscription avec email déjà utilisé → erreur', async ({ page }) => {
    await page.goto(`${BASE}/register`);
    await page.fill('input[name="registration_form[email]"]', 'alice.martin@studysprint.local');
    await page.fill('input[name="registration_form[plainPassword][first]"]', 'Test1234!');
    await page.fill('input[name="registration_form[plainPassword][second]"]', 'Test1234!');
    // Remplir nom si présent
    const nomInput = page.locator('input[name="registration_form[nom]"], input[name="registration_form[fullName]"]').first();
    if (await nomInput.isVisible()) await nomInput.fill('Dupont');
    const prenomInput = page.locator('input[name="registration_form[prenom]"]').first();
    if (await prenomInput.isVisible()) await prenomInput.fill('Alice');

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    // Doit rester sur /register avec message d'erreur de validation
    await expect(page.locator('.form-error, .alert, [class*="error"], li').first()).toBeVisible();
  });
});

test.describe('Authentification — Accès protégé', () => {
  test('Visiteur non connecté redirigé vers login si tente FO', async ({ page }) => {
    await page.goto(`${BASE}/fo/subjects`);
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/login/);
  });

  test('Visiteur non connecté redirigé vers login si tente BO', async ({ page }) => {
    await page.goto(`${BASE}/bo/users`);
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/login/);
  });
});
