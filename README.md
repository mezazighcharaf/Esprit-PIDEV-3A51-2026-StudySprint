# StudySprint

Plateforme web de gestion d'apprentissage collaboratif — Symfony 6.4 LTS.

---

## Prérequis

- PHP >= 8.1
- Composer
- MySQL / MariaDB 10.4+
- Node.js (optionnel, pour les assets)
- Symfony CLI (`scoop install symfony-cli` ou [télécharger ici](https://symfony.com/download))
- XAMPP ou tout autre serveur MySQL local
- Python 3.10+ (pour le microservice IA FastAPI)

---

## Installation

### 1. Cloner le projet

```bash
git clone https://github.com/mezazighcharaf/StudySprint.git
cd StudySprint
```

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Configurer les variables d'environnement

Copier le fichier `.env` et l'adapter :

```bash
cp .env .env.local
```

Modifier `.env.local` avec vos valeurs :

```env
# Base de données
DATABASE_URL="mysql://root:@127.0.0.1:3306/studysprint?serverVersion=mariadb-10.4.32&charset=utf8mb4"

# Mercure (hub temps réel)
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=studysprint_mercure_secret_key_change_me

# Gemini AI (optionnel)
GEMINI_API_KEY=your_gemini_api_key_here
```

### 4. Créer la base de données et appliquer les migrations

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Installer Mercure (hub temps réel)

Mercure est utilisé pour les notifications en temps réel. Le binaire **n'est pas inclus dans le repo** (trop lourd).

**Téléchargement :**
👉 [https://github.com/dunglas/mercure/releases](https://github.com/dunglas/mercure/releases)

Choisir la version **Windows AMD64** (`mercure_Windows_x86_64.zip`), extraire et placer `mercure.exe` à la **racine du projet** (à côté de `composer.json`).

**Démarrage :**

Double-cliquer sur `start-mercure.bat` ou exécuter :

```bash
./mercure.exe run --config mercure.Caddyfile
```

Le hub écoute sur `http://localhost:3000`.

---

## Lancer l'application

### Étape 1 — Démarrer MySQL

Lancer XAMPP et activer le service **MySQL**.

### Étape 2 — Démarrer le hub Mercure

```bash
# Double-cliquer sur start-mercure.bat
# ou en ligne de commande :
./mercure.exe run --config mercure.Caddyfile
```

### Étape 3 — Démarrer le serveur Symfony

```bash
symfony server:start --no-tls
```

L'application est accessible sur : **http://localhost:8000**

### Étape 4 — Microservice IA (optionnel)

Le microservice FastAPI gère les fonctionnalités IA (génération de planning, résumés, flashcards, chatbot).

```bash
cd api
pip install -r requirements.txt
uvicorn app.main:app --reload --port 8001
```

Le service écoute sur `http://localhost:8001`. Toutes les fonctionnalités non-IA fonctionnent sans lui.

---

## Structure du projet

```
StudySprint/
├── api/                        # Microservice FastAPI (IA)
├── config/                     # Configuration Symfony
├── migrations/                 # Migrations Doctrine
├── public/                     # Point d'entrée web
├── src/
│   ├── Controller/
│   │   ├── Bo/                 # Back-office admin
│   │   ├── Fo/                 # Front-office utilisateur
│   │   └── Api/                # Endpoints API REST
│   ├── Dto/                    # Data Transfer Objects
│   ├── Entity/                 # Entités Doctrine
│   ├── Form/                   # Formulaires Symfony
│   ├── Repository/             # Repositories Doctrine
│   ├── Security/               # Voters et UserChecker
│   ├── Service/                # Services métier
│   └── Twig/                   # Extensions Twig custom
├── templates/
│   ├── bo/                     # Templates back-office
│   ├── fo/                     # Templates front-office
│   ├── components/             # Composants réutilisables
│   └── emails/                 # Templates d'emails
├── mercure.Caddyfile           # Config hub Mercure
├── start-mercure.bat           # Script démarrage Mercure (Windows)
└── .env                        # Variables d'environnement
```

---

## Modules fonctionnels

| Module | Routes | Description |
|--------|--------|-------------|
| Auth | `/login`, `/register` | Inscription, connexion, reset password |
| Profil | `/fo/profile` | Avatar, bio, stats, certification |
| Matières | `/fo/subjects` | CRUD matières et chapitres, Wikipedia |
| Planning | `/fo/planning` | Calendrier, plans, tâches, IA |
| Groupes | `/fo/groups` | Posts, commentaires, invitations, QR code |
| Quiz | `/fo/training/quizzes` | Sessions chrono, historique, SM-2 |
| Flashcards | `/fo/training/decks` | Révision espacée, génération IA |
| Leaderboard | `/fo/leaderboard` | Classement global |
| Notifications | `/fo/notifications` | Temps réel via Mercure SSE |
| Back-office | `/admin`, `/bo` | Dashboard, analytics, CRUD, IA monitoring |

---

## APIs externes utilisées

- **Wikipedia FR** — résumé automatique des matières
- **Dictionary API** — définition de mots en anglais (`/api/dictionary/{word}`)
- **LibreTranslate** — traduction de contenu (`/api/translate`)
- **Gemini AI** — chatbot dans les groupes (nécessite `GEMINI_API_KEY`)

---

## Rôles utilisateur

| Rôle | Accès |
|------|-------|
| `ROLE_USER` (Student) | FO complet |
| `ROLE_TEACHER` (Professor) | FO + création de contenu |
| `ROLE_ADMIN` (Administrator) | FO + Back-office complet |

---

## Commandes utiles

```bash
# Vider le cache
php bin/console cache:clear

# Vérifier le mapping Doctrine
php bin/console doctrine:schema:validate

# Générer une migration après modification d'entité
php bin/console doctrine:migrations:diff

# Appliquer les migrations
php bin/console doctrine:migrations:migrate

# Lister toutes les routes
php bin/console debug:router

# Créer un admin manuellement
php bin/console app:create-admin
```

---

## Stack technique

- **Backend** — Symfony 6.4 LTS, PHP 8.1+, Doctrine ORM
- **Base de données** — MariaDB 10.4.32
- **Frontend** — Twig, CSS custom, JavaScript vanilla, Chart.js, FullCalendar.js
- **Temps réel** — Mercure SSE (MercureBundle 0.4.2)
- **Images** — LiipImagineBundle 2.17 (GD driver)
- **Auth API** — LexikJWTAuthenticationBundle
- **Pagination** — KnpPaginatorBundle
- **QR Code** — Endroid QrCode
- **PDF** — Dompdf
- **IA** — FastAPI Python + Gemini API

---

## Branches

| Branche | Rôle |
|---------|------|
| `main` | Production stable |
| `integration` | Branche d'intégration principale |
| `feature/groups` | Module groupes (mergé dans integration) |

---

## Contributeurs

Projet réalisé dans le cadre d'un projet académique.
