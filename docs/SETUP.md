# StudySprint - Guide de Configuration

## Prérequis

- PHP 8.1+
- Composer
- MariaDB 10.4+ ou MySQL 5.7+
- Symfony CLI (optionnel mais recommandé)

## Installation

### 1. Configuration de la base de données

Créez votre base de données et configurez le fichier `.env.local`:

```bash
# .env.local
DATABASE_URL="mysql://root:@127.0.0.1:3306/studysprint?serverVersion=10.4.32-MariaDB&charset=utf8mb4"
```

**Important**: Adaptez `serverVersion` à votre version exacte de MariaDB/MySQL.

### 2. Installation des dépendances

```bash
composer install
```

### 3. Migrations de la base de données

Créez le schéma de base de données:

```bash
php bin/console doctrine:migrations:migrate
```

Validez le schéma:

```bash
php bin/console doctrine:schema:validate
```

**Résultat attendu:**
```
[OK] The mapping files are correct.
[OK] The database schema is in sync with the mapping files.
```

### 4. Chargement des fixtures (données de démonstration)

**ATTENTION**: Cette commande va **purger** toutes les données existantes et les remplacer par les fixtures.

```bash
php bin/console doctrine:fixtures:load
```

Confirmez avec `yes` quand demandé.

**Contenu des fixtures:**
- **4 utilisateurs**:
  - `admin@studysprint.local` / `admin123` (ROLE_ADMIN - accès BO)
  - `alice.martin@studysprint.local` / `user123` (ROLE_USER - accès FO)
  - `bob.dupont@studysprint.local` / `user123` (ROLE_USER)
  - `prof.claire@studysprint.local` / `user123` (TEACHER)
  
- **3 matières** (Maths, Physique, Chimie) + **8 chapitres**
- **3 groupes d'étude** + 5 membres + **5 posts/commentaires**
- **2 plans de révision** + 4 tâches
- **2 quiz** avec questions/réponses + 2 tentatives utilisateur
- **2 decks de flashcards** (10 cartes) + review states

**Alternative (append sans purge)**:
```bash
php bin/console doctrine:fixtures:load --append
```
Cette commande ajoute les fixtures sans supprimer les données existantes, mais peut créer des doublons.

### 5. Vider le cache

```bash
php bin/console cache:clear
```

### 6. Démarrer le serveur

**Avec Symfony CLI** (recommandé):
```bash
# Si PATH non rafraîchi dans la session PowerShell:
$env:Path = [System.Environment]::GetEnvironmentVariable("Path","User") + ";" + [System.Environment]::GetEnvironmentVariable("Path","Machine")

# Démarrer le serveur
symfony serve -d --no-tls
```

**Sans Symfony CLI**:
```bash
php -S localhost:8000 -t public
```

### 7. Accéder à l'application

- **Login**: http://localhost:8000/login
- **Back Office** (admin): http://localhost:8000/admin
- **Front Office** (étudiant): http://localhost:8000/fo/subjects

## Comptes de test

### Admin (Back Office)
- Email: `admin@studysprint.local`
- Mot de passe: `admin123`
- Accès: `/admin` et `/bo/*`

### Étudiant (Front Office)
- Email: `alice.martin@studysprint.local` ou `bob.dupont@studysprint.local`
- Mot de passe: `user123`
- Accès: `/fo/*`

## Vérifications

### Routes disponibles
```bash
php bin/console debug:router
```

### État de la base de données
```bash
# Compter les entités
php bin/console doctrine:query:sql "SELECT 
  (SELECT COUNT(*) FROM users) as users,
  (SELECT COUNT(*) FROM subjects) as subjects,
  (SELECT COUNT(*) FROM chapters) as chapters,
  (SELECT COUNT(*) FROM study_groups) as groups,
  (SELECT COUNT(*) FROM group_posts) as posts,
  (SELECT COUNT(*) FROM revision_plans) as plans,
  (SELECT COUNT(*) FROM quizzes) as quizzes,
  (SELECT COUNT(*) FROM flashcard_decks) as decks"
```

### Validation du schéma
```bash
php bin/console doctrine:schema:validate
```

## Dépannage

### Erreur "Connection refused"
- Vérifiez que MariaDB/MySQL est démarré
- Vérifiez les credentials dans `.env.local`

### Erreur "serverVersion mismatch"
- Mettez à jour `serverVersion` dans `DATABASE_URL` pour correspondre à votre version exacte

### Page blanche / 500 error
```bash
# Vider le cache et vérifier les logs
php bin/console cache:clear
tail -f var/log/dev.log
```

### Fixtures échouent
```bash
# Recréer la base complètement
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
```

## Architecture des routes

### Public
- `/login` - Connexion
- `/register` - Inscription
- `/` - Page d'accueil (redirige selon rôle)

### Back Office (ROLE_ADMIN)
- `/admin` - Dashboard overview
- `/admin/analytics` - Analytics overview
- `/bo/users` - CRUD Users
- `/bo/subjects` - CRUD Matières
- `/bo/chapters` - CRUD Chapitres
- `/bo/groups` - CRUD Groupes
- `/bo/plans` - CRUD Plans de révision
- `/bo/quizzes` - CRUD Quiz
- `/bo/decks` - CRUD Flashcard Decks

### Front Office (ROLE_USER)
- `/fo/subjects` - Liste des matières
- `/fo/planning` - Plans de révision
- `/fo/groups` - Groupes d'étude + posts/commentaires
- `/fo/training/quizzes` - Quiz avec tentatives
- `/fo/training/decks` - Flashcards avec révision espacée (SM2)

## Références

- [Symfony 6.4 Documentation](https://symfony.com/doc/6.4/index.html)
- [Doctrine Fixtures Bundle](https://symfony.com/bundles/DoctrineFixturesBundle/current/index.html)
- [Symfony Security](https://symfony.com/doc/6.4/security.html)
