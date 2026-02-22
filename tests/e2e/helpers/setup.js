const { execSync } = require('child_process');
const path = require('path');

const PROJECT_ROOT = path.resolve(__dirname, '../../..');

// Users de test — créés via console Symfony
const USERS = {
  student: {
    email: 'test.student@studysprint.test',
    password: 'Test1234!',
    nom: 'Dupont',
    prenom: 'Alice',
    role: 'ROLE_USER',
    discr: 'student',
  },
  teacher: {
    email: 'test.teacher@studysprint.test',
    password: 'Test1234!',
    nom: 'Martin',
    prenom: 'Bob',
    role: 'ROLE_TEACHER',
    discr: 'professor',
  },
  admin: {
    email: 'test.admin@studysprint.test',
    password: 'Test1234!',
    nom: 'Admin',
    prenom: 'Super',
    role: 'ROLE_ADMIN',
    discr: 'administrator',
  },
};

function runConsole(cmd) {
  try {
    return execSync(`php bin/console ${cmd}`, {
      cwd: PROJECT_ROOT,
      encoding: 'utf8',
      stdio: 'pipe',
    });
  } catch (e) {
    return e.stdout || '';
  }
}

function runSQL(sql) {
  try {
    return execSync(`php bin/console doctrine:query:sql "${sql.replace(/"/g, '\\"')}"`, {
      cwd: PROJECT_ROOT,
      encoding: 'utf8',
      stdio: 'pipe',
    });
  } catch (e) {
    return '';
  }
}

/**
 * Crée les utilisateurs de test s'ils n'existent pas déjà
 */
async function setupTestUsers() {
  for (const [key, user] of Object.entries(USERS)) {
    // Supprimer si déjà existant pour repartir proprement
    runSQL(`DELETE FROM users WHERE email = '${user.email}'`);

    // Hash du mot de passe via Symfony
    const hash = execSync(
      `php -r "echo password_hash('${user.password}', PASSWORD_BCRYPT);"`,
      { cwd: PROJECT_ROOT, encoding: 'utf8' }
    ).trim();

    runSQL(
      `INSERT INTO users (nom, prenom, email, mot_de_passe, role, statut, date_inscription, discr) ` +
      `VALUES ('${user.nom}', '${user.prenom}', '${user.email}', '${hash}', '${user.role}', 'actif', NOW(), '${user.discr}')`
    );
  }
  console.log('✅ Utilisateurs de test créés');
}

/**
 * Nettoie les données de test après les tests
 */
async function cleanupTestData() {
  for (const user of Object.values(USERS)) {
    runSQL(`DELETE FROM users WHERE email = '${user.email}'`);
  }
  runSQL(`DELETE FROM study_group WHERE name LIKE 'TEST_%'`);
  runSQL(`DELETE FROM subject WHERE name LIKE 'TEST_%'`);
  runSQL(`DELETE FROM revision_plan WHERE title LIKE 'TEST_%'`);
  console.log('🧹 Données de test nettoyées');
}

module.exports = { USERS, setupTestUsers, cleanupTestData, runSQL };
