// @ts-check
const { expect } = require('@playwright/test');

const BASE = 'http://localhost:8000';

const USERS = {
  admin:   { email: 'admin@studysprint.local',          password: 'admin123' },
  alice:   { email: 'alice.martin@studysprint.local',   password: 'user123' },
  bob:     { email: 'bob.dupont@studysprint.local',     password: 'user123' },
  charlie: { email: 'charlie.bernard@studysprint.local',password: 'user123' },
  prof:    { email: 'prof.claire@studysprint.local',    password: 'user123' },
  profPhys:{ email: 'prof.marc@studysprint.local',      password: 'user123' },
};

/**
 * Se connecte avec l'utilisateur donné et attend la redirection post-login.
 */
async function loginAs(page, userKey) {
  const user = USERS[userKey];
  if (!user) throw new Error(`Utilisateur inconnu: ${userKey}`);
  await page.goto(`${BASE}/login`);
  await page.fill('input[name="email"]', user.email);
  await page.fill('input[name="password"]', user.password);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  // Vérifier qu'on n'est plus sur /login (connexion réussie)
  await expect(page).not.toHaveURL(/login/);
}

module.exports = { loginAs, USERS, BASE };
