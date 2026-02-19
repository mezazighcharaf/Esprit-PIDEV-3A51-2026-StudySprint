# StudySprint — Documentation Technique Complète

> **Plateforme d'apprentissage intelligente avec IA intégrée**
> Stack: Symfony 7 (PHP 8.2) + FastAPI (Python 3.14) + MySQL + Ollama (LLM local)

---

## Table des Matières

1. [Architecture Générale](#1-architecture-générale)
2. [Module Authentification & Utilisateurs](#2-module-authentification--utilisateurs)
3. [Module Matières & Chapitres](#3-module-matières--chapitres)
4. [Module Training — Quiz](#4-module-training--quiz)
5. [Module Training — Flashcards](#5-module-training--flashcards)
6. [Module Planning & Révision](#6-module-planning--révision)
7. [Module Groupes d'Étude](#7-module-groupes-détude)
8. [Module Profil Utilisateur](#8-module-profil-utilisateur)
9. [Module IA — AI Gateway](#9-module-ia--ai-gateway)
10. [Back-Office (BO) — Administration](#10-back-office-bo--administration)
11. [Services Métier (Symfony)](#11-services-métier-symfony)
12. [Schéma Relationnel Complet](#12-schéma-relationnel-complet)
13. [Routes API FastAPI](#13-routes-api-fastapi)
14. [Configuration & Environnement](#14-configuration--environnement)

---

## 1. Architecture Générale

### Stack Technique

| Couche | Technologie |
|--------|------------|
| **Frontend** | Twig templates + CSS inline + JavaScript vanilla |
| **Backend Symfony** | Symfony 7 (PHP 8.2), Doctrine ORM |
| **API REST** | FastAPI (Python 3.14), SQLAlchemy, Pydantic |
| **Base de données** | MySQL (`studysprint`) |
| **IA locale** | Ollama + modèle `vanilj/qwen2.5-14b-instruct-iq4_xs:latest` |
| **Math rendering** | KaTeX (intégré globalement via `base.html.twig`) |
| **Authentification** | Symfony Security (session) + JWT (API) |

### Architecture Dual Backend

```
┌─────────────────────┐      ┌──────────────────────┐      ┌─────────────┐
│   Navigateur Web    │─────▶│  Symfony (port 8000)  │─────▶│   MySQL DB  │
│   (Templates Twig)  │      │  Controllers + Twig   │      │ studysprint │
└─────────────────────┘      └──────────┬───────────┘      └──────┬──────┘
                                        │ HTTP Client              │
                                        ▼                          │
                             ┌──────────────────────┐              │
                             │ FastAPI (port 8001)   │──────────────┘
                             │ API REST + AI Gateway │
                             └──────────┬───────────┘
                                        │ httpx
                                        ▼
                             ┌──────────────────────┐
                             │  Ollama (port 11434)  │
                             │  LLM Local (Qwen2.5)  │
                             └──────────────────────┘
```

- **Symfony** : Gère le rendu des pages, l'authentification, les formulaires, et le CRUD principal
- **FastAPI** : API REST pour le CRUD avancé et passerelle IA (AI Gateway) qui communique avec Ollama
- **Ollama** : Serveur d'inférence LLM local, utilisé par la FastAPI pour toutes les générations IA

---

## 2. Module Authentification & Utilisateurs

### Entités

#### `User` — Table `users`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant auto-incrémenté |
| `email` | VARCHAR(180) UNIQUE | Email de connexion |
| `password` | VARCHAR | Hash bcrypt |
| `roles` | JSON | Rôles Symfony (`ROLE_USER`, `ROLE_ADMIN`) |
| `full_name` | VARCHAR(255) | Nom complet |
| `user_type` | VARCHAR(50) | Type : `STUDENT`, `TEACHER`, `ADMIN` |
| `created_at` | DATETIME_IMMUTABLE | Date de création |
| `updated_at` | DATETIME_IMMUTABLE | Dernière modification (lifecycle callback) |

**Relations :**
- `OneToOne` → `UserProfile` (cascade persist/remove)
- `OneToMany` → `Subject`, `Chapter`, `StudyGroup`, `Quiz`, `FlashcardDeck`, `RevisionPlan`

#### `UserProfile` — Table `user_profiles`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `user_id` | FK → users | Relation OneToOne |
| `level` | VARCHAR(100) | Niveau d'études |
| `specialty` | VARCHAR(255) | Spécialité |
| `bio` | TEXT | Biographie |
| `avatar_url` | VARCHAR(500) | URL avatar |
| `ai_suggested_bio` | TEXT | Bio suggérée par l'IA |
| `ai_suggested_goals` | TEXT | Objectifs suggérés par l'IA |
| `ai_suggested_routine` | TEXT | Routine suggérée par l'IA |

### CRUD & Fonctionnalités

**Symfony (FO) — `SecurityController`**
- `GET /login` — Formulaire de connexion
- `POST /logout` — Déconnexion
- `GET /register` — Formulaire d'inscription
- `POST /register` — Création de compte avec hash de mot de passe

**Symfony (FO) — `ProfileController`**
- `GET /fo/profile` — Affichage du profil
- `POST /fo/profile/edit` — Modification du profil
- `POST /fo/profile/ai-enhance` — Demande de suggestions IA pour le profil

**Symfony (BO) — `UserController`**
- CRUD complet (list, show, edit, delete) pour l'administration des utilisateurs

**FastAPI — `/api/v1/users`**
- `GET /` — Liste paginée des utilisateurs
- `GET /{id}` — Détails d'un utilisateur
- `POST /` — Création
- `PUT /{id}` — Mise à jour
- `DELETE /{id}` — Suppression

**FastAPI — `/api/v1/auth`**
- `POST /login` — Authentification JWT (access token + refresh token)
- `POST /refresh` — Rafraîchissement du token

### IA dans ce module

**Endpoint :** `POST /api/v1/ai/profile/enhance`

L'IA analyse le profil actuel de l'utilisateur (bio, niveau, spécialité, objectifs) et génère :
- **Bio suggérée** : biographie professionnelle et engageante (2-3 phrases)
- **Objectifs SMART** : objectifs clairs et mesurables
- **Routine d'étude** : routine quotidienne personnalisée

Les suggestions sont stockées dans `UserProfile` (champs `ai_suggested_*`) et ne remplacent PAS les données de l'utilisateur — il peut les accepter ou non.

---

## 3. Module Matières & Chapitres

### Entités

#### `Subject` — Table `subjects`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `name` | VARCHAR(255) | Nom de la matière |
| `code` | VARCHAR(50) UNIQUE | Code court (ex: MATH101) |
| `description` | TEXT | Description |
| `created_by` | FK → users | Créateur |
| `created_at` | DATETIME_IMMUTABLE | Date de création |

**Relations :**
- `OneToMany` → `Chapter`, `Quiz`, `FlashcardDeck`, `RevisionPlan`

#### `Chapter` — Table `chapters`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `subject_id` | FK → subjects | Matière parente (CASCADE) |
| `title` | VARCHAR(255) | Titre du chapitre |
| `order_no` | INT | Numéro d'ordre (unique par matière) |
| `summary` | TEXT | Résumé manuel |
| `content` | TEXT | Contenu complet |
| `ai_summary` | TEXT | Résumé généré par l'IA |
| `ai_key_points` | JSON | Points clés générés par l'IA |
| `ai_tags` | JSON | Tags générés par l'IA |
| `created_by` | FK → users | Créateur |
| `created_at` | DATETIME_IMMUTABLE | Date de création |

**Contrainte unique :** `(subject_id, order_no)`

**Relations :**
- `OneToMany` → `Quiz`, `FlashcardDeck`, `RevisionPlan`

### CRUD & Fonctionnalités

**Symfony (FO) — `SubjectsController`**
- `GET /fo/subjects` — Liste des matières de l'utilisateur
- `GET /fo/subjects/{id}` — Détail avec liste des chapitres
- `POST /fo/subjects/new` — Création d'une matière
- `POST /fo/subjects/{id}/edit` — Modification
- `POST /fo/subjects/{id}/delete` — Suppression

**Symfony (BO) — `SubjectController` + `ChapterController`**
- CRUD complet pour matières et chapitres (administration)

**FastAPI — `/api/v1/subjects`**
- `GET /` — Liste paginée avec filtres (search, user_id)
- `GET /{id}` — Détails avec chapitres
- `POST /` — Création
- `PUT /{id}` — Mise à jour
- `DELETE /{id}` — Suppression
- `GET /{id}/chapters` — Chapitres d'une matière
- `POST /{id}/chapters` — Ajout d'un chapitre
- `PUT /chapters/{id}` — Modification d'un chapitre
- `DELETE /chapters/{id}` — Suppression d'un chapitre

### IA dans ce module

**Endpoint :** `POST /api/v1/ai/chapter/summarize`

L'IA analyse le contenu d'un chapitre et génère :
- **Résumé** : synthèse concise du chapitre (3-5 phrases)
- **Points clés** : liste de 5 points essentiels à retenir
- **Tags** : 5 mots-clés pour catégoriser le contenu

Les résultats sont persistés dans les champs `ai_summary`, `ai_key_points`, `ai_tags` du chapitre.

---

## 4. Module Training — Quiz

### Entités

#### `Quiz` — Table `quizzes`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `owner_id` | FK → users | Propriétaire |
| `subject_id` | FK → subjects | Matière |
| `chapter_id` | FK → chapters (nullable) | Chapitre optionnel |
| `title` | VARCHAR(255) | Titre du quiz |
| `difficulty` | VARCHAR(50) | `EASY`, `MEDIUM`, `HARD` |
| `template_key` | VARCHAR(100) | Clé de template |
| `questions` | JSON | Questions (format structuré) |
| `is_published` | BOOLEAN | Publié ou brouillon |
| `generated_by_ai` | BOOLEAN | Généré par l'IA |
| `ai_meta` | JSON | Métadonnées IA (provider, model, log_id) |
| `created_at` | DATETIME_IMMUTABLE | Date de création |
| `updated_at` | DATETIME_IMMUTABLE | Dernière modification |

**Format JSON `questions` :**
```json
[
  {
    "text": "Quelle est la dérivée de x² ?",
    "choices": [
      {"key": "A", "text": "x"},
      {"key": "B", "text": "2x"},
      {"key": "C", "text": "x²"},
      {"key": "D", "text": "2"}
    ],
    "correct_key": "B",
    "explanation": "La dérivée de x^n est n*x^(n-1), donc 2x"
  }
]
```

#### `QuizAttempt` — Table `quiz_attempts`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `user_id` | FK → users | Utilisateur |
| `quiz_id` | FK → quizzes (CASCADE) | Quiz tenté |
| `started_at` | DATETIME_IMMUTABLE | Début de la tentative |
| `completed_at` | DATETIME_IMMUTABLE | Fin (null si en cours) |
| `score` | DECIMAL(5,2) | Score en pourcentage |
| `total_questions` | INT | Nombre total de questions |
| `correct_count` | INT | Réponses correctes |
| `duration_seconds` | INT | Durée en secondes |

**Relations :**
- `OneToMany` → `QuizAttemptAnswer` (cascade persist/remove)

#### `QuizAttemptAnswer` — Table `quiz_attempt_answers`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `attempt_id` | FK → quiz_attempts (CASCADE) | Tentative |
| `question_index` | INT | Index de la question |
| `selected_choice_key` | VARCHAR(100) | Clé de la réponse choisie |
| `is_correct` | BOOLEAN | Correct ou non |

### CRUD & Fonctionnalités

**Symfony (FO) — `QuizController` + `QuizManageController`**
- `GET /fo/training/quizzes` — Liste des quiz disponibles
- `GET /fo/training/quizzes/{id}` — Détail d'un quiz
- `POST /fo/training/quizzes/{id}/start` — Démarrer une tentative
- `POST /fo/training/quizzes/{id}/submit` — Soumettre les réponses
- `GET /fo/training/quizzes/{id}/results/{attemptId}` — Résultats détaillés
- `GET /fo/training/quizzes/ai-generate` — Formulaire de génération IA
- `POST /fo/training/quizzes/ai-generate` — Lancer la génération IA
- `GET /fo/training/quizzes/manage/new` — Créer un quiz manuellement
- `POST /fo/training/quizzes/manage/{id}/edit` — Modifier un quiz
- `POST /fo/training/quizzes/manage/{id}/delete` — Supprimer un quiz

**Symfony (BO) — `QuizController`**
- CRUD complet pour l'administration des quiz

**FastAPI — `/api/v1/training`**
- `GET /quizzes` — Liste paginée avec filtres
- `GET /quizzes/{id}` — Détails d'un quiz
- `POST /quizzes` — Création
- `PUT /quizzes/{id}` — Mise à jour
- `DELETE /quizzes/{id}` — Suppression
- `POST /quizzes/{id}/attempt` — Démarrer une tentative
- `POST /attempts/{id}/submit` — Soumettre des réponses
- `GET /attempts/{id}` — Résultat d'une tentative

### Services Métier

- **`QuizScoringService`** : Corrige une tentative en comparant les réponses soumises aux réponses correctes. Supporte 4 formats de réponse (isCorrect par choix, correctIndex, correctKey, correct_key). Calcule le score en pourcentage et la durée.
- **`QuizTemplateService`** : Génère des templates de quiz prédéfinis pour des sujets courants.

### IA dans ce module

**Endpoint :** `POST /api/v1/ai/generate/quiz`

**Paramètres :**
- `user_id`, `subject_id`, `chapter_id` (optionnel)
- `num_questions` (1-20, défaut: 5)
- `difficulty` (`EASY`, `MEDIUM`, `HARD`)
- `topic` (sujet spécifique optionnel)

**Fonctionnement :**
1. Vérifie l'idempotence (évite les doublons via clé de hachage)
2. Construit un prompt contextuel avec la matière, le chapitre et le sujet
3. Appelle Ollama via `call_ai_with_fallback()`
4. Parse la réponse JSON (avec retry jusqu'à 3 fois)
5. Crée l'entité `Quiz` en DB avec `generated_by_ai = true`
6. Log tout dans `AiGenerationLog` (input, prompt, output, latence, statut)
7. Retourne le quiz_id au contrôleur Symfony qui redirige vers la page du quiz

---

## 5. Module Training — Flashcards

### Entités

#### `FlashcardDeck` — Table `flashcard_decks`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `owner_id` | FK → users | Propriétaire |
| `subject_id` | FK → subjects | Matière |
| `chapter_id` | FK → chapters (nullable) | Chapitre optionnel |
| `title` | VARCHAR(255) | Titre du deck |
| `template_key` | VARCHAR(100) | Clé de template |
| `cards` | JSON | Cartes (legacy, on utilise la relation) |
| `is_published` | BOOLEAN | Publié ou brouillon |
| `generated_by_ai` | BOOLEAN | Généré par l'IA |
| `ai_meta` | JSON | Métadonnées IA |
| `created_at` | DATETIME_IMMUTABLE | Date de création |

**Relations :**
- `OneToMany` → `Flashcard` (cascade persist/remove, ordered by position)

#### `Flashcard` — Table `flashcards`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `deck_id` | FK → flashcard_decks (CASCADE) | Deck parent |
| `front` | TEXT | Recto (question/concept) |
| `back` | TEXT | Verso (réponse/définition) |
| `hint` | VARCHAR(500) | Indice optionnel |
| `position` | INT | Position dans le deck |
| `created_at` | DATETIME_IMMUTABLE | Date de création |

**Relations :**
- `OneToMany` → `FlashcardReviewState` (cascade remove)

#### `FlashcardReviewState` — Table `flashcard_review_states`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `user_id` | FK → users (CASCADE) | Utilisateur |
| `flashcard_id` | FK → flashcards (CASCADE) | Carte |
| `repetitions` | INT | Nombre de répétitions réussies |
| `interval_days` | INT | Intervalle en jours (défaut: 1) |
| `ease_factor` | DECIMAL(4,2) | Facteur de facilité SM-2 (défaut: 2.50) |
| `due_at` | DATE_IMMUTABLE | Prochaine révision due |
| `last_reviewed_at` | DATETIME_IMMUTABLE | Dernière révision |

**Contrainte unique :** `(user_id, flashcard_id)`

### CRUD & Fonctionnalités

**Symfony (FO) — `DeckController` + `DeckManageController`**
- `GET /fo/training/decks` — Liste des decks
- `GET /fo/training/decks/{id}` — Détail d'un deck
- `GET /fo/training/decks/{id}/study` — Mode étude (révision espacée)
- `POST /fo/training/decks/{id}/review` — Soumettre une revue (Again/Hard/Good/Easy)
- `GET /fo/training/decks/ai-generate` — Formulaire de génération IA
- `POST /fo/training/decks/ai-generate` — Lancer la génération IA
- `GET /fo/training/decks/manage/new` — Créer un deck manuellement
- `POST /fo/training/decks/manage/{id}/edit` — Modifier un deck
- `POST /fo/training/decks/manage/{id}/delete` — Supprimer un deck

**Symfony (BO) — `FlashcardDeckController`**
- CRUD complet pour l'administration

**FastAPI — `/api/v1/flashcards`**
- `GET /decks` — Liste paginée des decks
- `GET /decks/{id}` — Détails avec cartes
- `POST /decks` — Création d'un deck
- `PUT /decks/{id}` — Modification
- `DELETE /decks/{id}` — Suppression
- `POST /decks/{id}/cards` — Ajouter une carte
- `PUT /cards/{id}` — Modifier une carte
- `DELETE /cards/{id}` — Supprimer une carte
- `GET /decks/{id}/study` — Cartes dues pour révision
- `POST /cards/{id}/review` — Soumettre une revue SM-2

### Services Métier

- **`Sm2SchedulerService`** : Implémentation de l'algorithme SM-2 (SuperMemo 2) pour la répétition espacée.
  - Qualités : `Again (0)`, `Hard (3)`, `Good (4)`, `Easy (5)`
  - Si qualité < 3 → réinitialisation (repetitions = 0, interval = 1 jour)
  - Calcul du nouveau facteur de facilité : `EF' = EF + (0.1 - (5 - q) * (0.08 + (5 - q) * 0.02))`
  - Facteur minimum : 1.3
  - Intervalles : rep 1 → 1j, rep 2 → 6j, rep 3+ → interval × easeFactor
- **`FlashcardTipsService`** : Fournit des conseils pédagogiques pour la création de flashcards (concision, bidirectionnel, mnémotechnique, etc.)

### IA dans ce module

**Endpoint :** `POST /api/v1/ai/generate/flashcards`

**Paramètres :**
- `user_id`, `subject_id`, `chapter_id` (optionnel)
- `num_cards` (1-50, défaut: 10)
- `topic` (optionnel)
- `include_hints` (boolean, défaut: true)

**Fonctionnement :**
1. Vérifie l'idempotence
2. Construit un prompt contextuel
3. Appelle Ollama et parse les flashcards JSON
4. Crée le `FlashcardDeck` + les entités `Flashcard` individuelles en DB
5. Chaque carte a `front`, `back`, `hint` (optionnel), `position`
6. Log complet dans `AiGenerationLog`

---

## 6. Module Planning & Révision

### Entités

#### `RevisionPlan` — Table `revision_plans`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `user_id` | FK → users | Utilisateur |
| `subject_id` | FK → subjects | Matière |
| `chapter_id` | FK → chapters (nullable) | Chapitre optionnel |
| `title` | VARCHAR(255) | Titre du plan |
| `start_date` | DATE_IMMUTABLE | Date de début |
| `end_date` | DATE_IMMUTABLE | Date de fin |
| `status` | VARCHAR(50) | `DRAFT`, `ACTIVE`, `DONE` |
| `generated_by_ai` | BOOLEAN | Généré par l'IA |
| `ai_meta` | JSON | Métadonnées IA |
| `created_at` | DATETIME_IMMUTABLE | Date de création |

**Relations :**
- `OneToMany` → `PlanTask` (cascade persist/remove, orphanRemoval)

#### `PlanTask` — Table `plan_tasks`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `plan_id` | FK → revision_plans (CASCADE) | Plan parent |
| `title` | VARCHAR(255) | Titre de la tâche |
| `task_type` | VARCHAR(50) | `REVISION`, `QUIZ`, `FLASHCARD`, `CUSTOM` |
| `start_at` | DATETIME_IMMUTABLE | Début planifié |
| `end_at` | DATETIME_IMMUTABLE | Fin planifiée |
| `status` | VARCHAR(50) | `TODO`, `DOING`, `DONE` |
| `priority` | SMALLINT | Priorité 1-3 |
| `notes` | TEXT | Notes libres |
| `created_at` | DATETIME_IMMUTABLE | Date de création |

### CRUD & Fonctionnalités

**Symfony (FO) — `PlanningController`**
- `GET /fo/planning` — Calendrier mensuel + sessions à venir + section IA
- `GET /fo/planning/{id}` — Détail d'un plan avec tâches
- `POST /fo/planning/generate` — Générer un plan automatique (via `PlanGeneratorService`)
- `POST /fo/planning/new` — Créer un plan manuellement
- `POST /fo/planning/{id}/edit` — Modifier un plan
- `POST /fo/planning/{id}/delete` — Supprimer un plan
- `POST /fo/planning/{planId}/tasks/new` — Ajouter une tâche
- `POST /fo/planning/tasks/{taskId}/edit` — Modifier une tâche
- `POST /fo/planning/tasks/{taskId}/toggle` — Basculer TODO/DONE
- `POST /fo/planning/{id}/ai-suggest` — Demander suggestions IA
- `GET /fo/planning/ai-confirm` — Page de confirmation des suggestions
- `POST /fo/planning/ai-apply` — Appliquer les suggestions IA

**Symfony (BO) — `RevisionPlanController` + `PlanTaskController`**
- CRUD complet pour l'administration des plans et tâches

**FastAPI — `/api/v1/planning`**
- `GET /plans` — Liste paginée des plans
- `GET /plans/{id}` — Détails avec tâches
- `POST /plans` — Création
- `PUT /plans/{id}` — Mise à jour
- `DELETE /plans/{id}` — Suppression
- `POST /plans/{id}/tasks` — Ajouter une tâche
- `PUT /tasks/{id}` — Modifier une tâche
- `DELETE /tasks/{id}` — Supprimer une tâche

### Services Métier

- **`PlanGeneratorService`** :
  - `findOverlappingPlan()` : Vérifie les chevauchements de plans
  - `generatePlan()` : Génère automatiquement un plan avec des tâches réparties sur la période, un créneau par chapitre (types REVISION, QUIZ, FLASHCARD)
  - `regeneratePlan()` : Supprime et recrée les tâches d'un plan existant

### IA dans ce module

**Endpoint 1 :** `POST /api/v1/ai/planning/suggest`

Analyse un plan de révision existant et propose des optimisations :
- Déplacement de tâches (`move`, `reschedule`)
- Suppression de tâches redondantes (`delete`)
- Répartition de la charge de travail
- Ajustement des priorités

**Flux en 2 étapes :**
1. **Suggest** : L'IA analyse le plan et retourne des suggestions avec explications
2. **Confirm** : L'utilisateur revoit les suggestions sur une page de confirmation
3. **Apply** : `POST /api/v1/ai/planning/apply` applique les modifications aux tâches en DB

**Endpoint 2 :** `POST /api/v1/ai/planning/apply`

Applique les suggestions précédemment générées :
- Vérifie que les tâches appartiennent au plan de l'utilisateur
- Actions supportées : `delete` (supprime la tâche), `move`/`reschedule` (change dates et priorité)

---

## 7. Module Groupes d'Étude

### Entités

#### `StudyGroup` — Table `study_groups`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `name` | VARCHAR(255) | Nom du groupe |
| `description` | TEXT | Description |
| `privacy` | VARCHAR(50) | `PUBLIC`, `PRIVATE` |
| `created_by` | FK → users | Créateur |
| `created_at` | DATETIME_IMMUTABLE | Date de création |

**Relations :**
- `OneToMany` → `GroupMember` (cascade persist/remove, orphanRemoval)
- `OneToMany` → `GroupPost` (cascade persist/remove, orphanRemoval)

#### `GroupMember` — Table `group_members`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `group_id` | FK → study_groups | Groupe |
| `user_id` | FK → users | Membre |
| `member_role` | VARCHAR(50) | `owner`, `admin`, `member` |
| `joined_at` | DATETIME_IMMUTABLE | Date d'adhésion |

**Contrainte unique :** `(group_id, user_id)`

#### `GroupPost` — Table `group_posts`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `group_id` | FK → study_groups (CASCADE) | Groupe |
| `author_id` | FK → users | Auteur |
| `parent_post_id` | FK → group_posts (nullable, CASCADE) | Post parent (commentaire) |
| `post_type` | VARCHAR(50) | `POST`, `COMMENT` |
| `title` | VARCHAR(255) | Titre (optionnel) |
| `body` | TEXT | Contenu |
| `attachment_url` | VARCHAR(500) | Pièce jointe |
| `ai_summary` | TEXT | Résumé IA |
| `ai_category` | VARCHAR(100) | Catégorie IA |
| `ai_tags` | JSON | Tags IA |
| `created_at` | DATETIME_IMMUTABLE | Date de création |

**Relations :**
- `OneToMany` → `GroupPost` (self-referencing: replies)

### CRUD & Fonctionnalités

**Symfony (FO) — `GroupsController`**
- `GET /fo/groups` — Liste des groupes (publics + mes groupes)
- `GET /fo/groups/{id}` — Page du groupe (hero + publications + sidebar)
- `POST /fo/groups/new` — Créer un groupe
- `POST /fo/groups/{id}/edit` — Modifier un groupe
- `POST /fo/groups/{id}/delete` — Supprimer un groupe
- `POST /fo/groups/{id}/join` — Rejoindre un groupe
- `POST /fo/groups/{id}/leave` — Quitter un groupe
- `POST /fo/groups/{id}/post` — Créer une publication
- `POST /fo/groups/{groupId}/post/{postId}/comment` — Commenter
- `POST /fo/groups/{groupId}/post/{postId}/delete` — Supprimer un post
- `POST /fo/groups/{groupId}/post/{postId}/ai-summarize` — Résumer un post avec l'IA

**Symfony (BO) — `StudyGroupController` + `GroupPostController`**
- CRUD complet pour l'administration

**FastAPI — `/api/v1/groups`**
- `GET /` — Liste paginée des groupes
- `GET /{id}` — Détails avec membres et posts
- `POST /` — Création
- `PUT /{id}` — Mise à jour
- `DELETE /{id}` — Suppression
- `POST /{id}/join` — Rejoindre
- `POST /{id}/leave` — Quitter
- `GET /{id}/posts` — Publications paginées
- `POST /{id}/posts` — Créer un post
- `POST /posts/{id}/reply` — Répondre à un post
- `DELETE /posts/{id}` — Supprimer

### IA dans ce module

**Endpoint :** `POST /api/v1/ai/post/summarize`

L'IA analyse le contenu d'un post de groupe et génère :
- **Résumé** : synthèse concise (1-2 phrases)
- **Catégorie** : `question`, `discussion`, `ressource`, `annonce`, `autre`
- **Tags** : 3 mots-clés

Les résultats sont persistés dans `GroupPost` (champs `ai_summary`, `ai_category`, `ai_tags`).

**UX :** Bouton "Résumer (IA)" avec spinner de chargement et compteur de secondes.

---

## 8. Module Profil Utilisateur

### Fonctionnalités

**Symfony (FO) — `ProfileController`**
- Affichage et édition du profil (niveau, spécialité, bio, avatar)
- Demande de suggestions IA via l'endpoint `/api/v1/ai/profile/enhance`
- Affichage des suggestions IA dans des champs dédiés (non-destructif)

Voir section [Module Authentification & Utilisateurs](#2-module-authentification--utilisateurs) pour les détails de l'entité `UserProfile`.

---

## 9. Module IA — AI Gateway

### Architecture

Le gateway IA est centralisé dans `api/app/routers/ai.py` et gère **toutes** les interactions avec le LLM.

### Entités

#### `AiGenerationLog` — Table `ai_generation_logs`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `user_id` | FK → users (SET NULL) | Utilisateur |
| `model_id` | FK → ai_models (SET NULL) | Modèle IA utilisé |
| `feature` | VARCHAR(100) | Feature : `quiz`, `flashcard`, `revision_plan`, `summary`, `profile`, `planning_suggest`, `post_summary` |
| `input_json` | JSON | Données d'entrée |
| `prompt` | TEXT | Prompt envoyé au LLM |
| `output_json` | JSON | Réponse parsée |
| `status` | VARCHAR(50) | `pending`, `success`, `failed` |
| `error_message` | TEXT | Message d'erreur |
| `latency_ms` | INT | Temps de réponse en ms |
| `user_feedback` | SMALLINT | Note utilisateur (1-5) |
| `idempotency_key` | VARCHAR(32) | Clé anti-doublon (SHA256 tronqué) |
| `created_at` | DATETIME_IMMUTABLE | Date de création |

#### `AiModel` — Table `ai_models`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `name` | VARCHAR(255) | Nom du modèle |
| `provider` | VARCHAR(100) | Provider (ex: `ollama`) |
| `base_url` | VARCHAR(500) | URL de base |
| `is_default` | BOOLEAN | Modèle par défaut |
| `created_at` | DATETIME_IMMUTABLE | Date de création |

### Endpoints IA Complets

| Endpoint | Méthode | Description | Feature |
|----------|---------|-------------|---------|
| `/api/v1/ai/status` | GET | Vérifie la disponibilité d'Ollama | — |
| `/api/v1/ai/generate/quiz` | POST | Génère un quiz complet | `quiz` |
| `/api/v1/ai/generate/flashcards` | POST | Génère un deck de flashcards | `flashcard` |
| `/api/v1/ai/profile/enhance` | POST | Suggestions de profil | `profile` |
| `/api/v1/ai/chapter/summarize` | POST | Résumé de chapitre | `summary` |
| `/api/v1/ai/planning/suggest` | POST | Suggestions d'optimisation de plan | `planning_suggest` |
| `/api/v1/ai/planning/apply` | POST | Applique les suggestions | `planning_suggest` |
| `/api/v1/ai/post/summarize` | POST | Résumé de post de groupe | `post_summary` |
| `/api/v1/ai/feedback` | POST | Feedback utilisateur (1-5) | — |
| `/api/v1/ai/logs/stats` | GET | Statistiques d'utilisation IA | — |

### Fonctionnement Interne

1. **Provider** : Ollama uniquement (modèle `vanilj/qwen2.5-14b-instruct-iq4_xs:latest`)
2. **Fallback** : Vérifie d'abord la disponibilité d'Ollama, retourne HTTP 503 si indisponible
3. **Idempotence** : Chaque requête génère une clé SHA256 pour éviter les générations en doublon
4. **Retry** : Jusqu'à 3 tentatives de parsing JSON en cas d'erreur
5. **Parsing** : Gère les réponses markdown (```json), les blocs de code, et l'extraction JSON
6. **Logging** : Chaque génération est loguée (input, prompt, output, latence, statut)
7. **Feedback** : Les utilisateurs peuvent noter les générations (1 à 5 étoiles)

### Configuration IA

| Paramètre | Valeur | Description |
|-----------|--------|-------------|
| `ollama_base_url` | `http://localhost:11434` | URL d'Ollama |
| `ollama_timeout` | `120s` | Timeout de génération |
| `ai_temperature` | `0.7` | Créativité du modèle |
| `ai_max_tokens` | `4000` | Tokens max par réponse |
| `ai_max_retries` | `3` | Tentatives de retry |

---

## 10. Back-Office (BO) — Administration

### Contrôleurs BO

| Contrôleur | Préfixe Route | Entité Gérée |
|-----------|---------------|-------------|
| `UserController` | `/bo/users` | User |
| `UserProfileController` | `/bo/user-profiles` | UserProfile |
| `SubjectController` | `/bo/subjects` | Subject |
| `ChapterController` | `/bo/chapters` | Chapter |
| `QuizController` | `/bo/quizzes` | Quiz |
| `FlashcardDeckController` | `/bo/flashcard-decks` | FlashcardDeck |
| `RevisionPlanController` | `/bo/plans` | RevisionPlan |
| `PlanTaskController` | `/bo/tasks` | PlanTask |
| `StudyGroupController` | `/bo/groups` | StudyGroup |
| `GroupPostController` | `/bo/posts` | GroupPost |
| `AiMonitoringController` | `/bo/ai-monitoring` | AiGenerationLog |

### Dashboard IA Monitoring

**Route :** `GET /bo/ai-monitoring`

Affiche :
- Total des requêtes IA, succès, échecs, taux d'échec
- Latence moyenne
- Statistiques par feature (quiz, flashcard, planning, etc.)
- Logs récents (20 derniers)
- Feedback moyen des utilisateurs
- Intégration des stats de l'API FastAPI

**Route :** `GET /bo/ai-monitoring/logs`

Liste paginée et filtrable des logs IA (par feature, par statut).

---

## 11. Services Métier (Symfony)

| Service | Rôle |
|---------|------|
| `QuizScoringService` | Correction des quiz (multi-format), calcul du score |
| `QuizTemplateService` | Templates de quiz prédéfinis |
| `Sm2SchedulerService` | Algorithme SM-2 de répétition espacée pour flashcards |
| `FlashcardTipsService` | Conseils pédagogiques pour la création de flashcards |
| `PlanGeneratorService` | Génération automatique de plans de révision avec tâches réparties |
| `BoDataProvider` | Fournisseur de données pour les pages BO |
| `BoMockDataProvider` | Données de démo pour le BO |

---

## 12. Schéma Relationnel Complet

```
users ──────────────────────┐
  │ 1:1  user_profiles      │
  │ 1:N  subjects            │
  │ 1:N  chapters            │
  │ 1:N  quizzes             │
  │ 1:N  flashcard_decks     │
  │ 1:N  revision_plans      │
  │ 1:N  study_groups        │
  │ 1:N  ai_generation_logs  │
  │                          │
subjects ───────────────┐    │
  │ 1:N  chapters       │    │
  │ 1:N  quizzes        │    │
  │ 1:N  flashcard_decks│    │
  │ 1:N  revision_plans │    │
  │                     │    │
chapters ───────────┐   │    │
  │ 1:N  quizzes    │   │    │
  │ 1:N  flashcard_decks│    │
  │ 1:N  revision_plans │    │
  │                     │    │
quizzes                 │    │
  │ 1:N  quiz_attempts  │    │
  │                     │    │
quiz_attempts           │    │
  │ 1:N  quiz_attempt_answers│
  │                          │
flashcard_decks              │
  │ 1:N  flashcards          │
  │                          │
flashcards                   │
  │ 1:N  flashcard_review_states
  │                          │
revision_plans               │
  │ 1:N  plan_tasks          │
  │                          │
study_groups                 │
  │ 1:N  group_members       │
  │ 1:N  group_posts         │
  │                          │
group_posts                  │
  │ 1:N  group_posts (self-ref: replies)
  │                          │
ai_models                    │
  │ 1:N  ai_generation_logs  │
```

### Tables (17 au total)

1. `users`
2. `user_profiles`
3. `subjects`
4. `chapters`
5. `quizzes`
6. `quiz_attempts`
7. `quiz_attempt_answers`
8. `flashcard_decks`
9. `flashcards`
10. `flashcard_review_states`
11. `revision_plans`
12. `plan_tasks`
13. `study_groups`
14. `group_members`
15. `group_posts`
16. `ai_generation_logs`
17. `ai_models`

---

## 13. Routes API FastAPI

### Routers Enregistrés

| Router | Préfixe | Fichier |
|--------|---------|---------|
| Auth | `/api/v1/auth` | `auth.py` |
| Users | `/api/v1/users` | `users.py` |
| Subjects | `/api/v1/subjects` | `subjects.py` |
| Training | `/api/v1/training` | `training.py` |
| Flashcards | `/api/v1/flashcards` | `flashcards.py` |
| Planning | `/api/v1/planning` | `planning.py` |
| Groups | `/api/v1/groups` | `groups.py` |
| AI Gateway | `/api/v1/ai` | `ai.py` |

### Modèles SQLAlchemy (Python)

| Fichier | Modèles |
|---------|---------|
| `models/user.py` | `User`, `UserProfile` |
| `models/subject.py` | `Subject`, `Chapter` |
| `models/training.py` | `Quiz`, `QuizAttempt`, `QuizAttemptAnswer` |
| `models/flashcard.py` | `FlashcardDeck`, `Flashcard`, `FlashcardReviewState` |
| `models/planning.py` | `RevisionPlan`, `PlanTask` |
| `models/group.py` | `StudyGroup`, `GroupMember`, `GroupPost` |
| `models/ai.py` | `AiGenerationLog`, `AiModel` |

---

## 14. Configuration & Environnement

### Symfony (.env)

- `DATABASE_URL` : Connexion MySQL
- `APP_SECRET` : Clé secrète Symfony
- `MESSENGER_TRANSPORT_DSN` : Transport async

### FastAPI (config.py)

| Variable | Défaut | Description |
|----------|--------|-------------|
| `database_url` | `mysql+pymysql://root:@127.0.0.1:3306/studysprint` | Connexion DB |
| `jwt_secret_key` | `your-super-secret-key-...` | Secret JWT |
| `jwt_algorithm` | `HS256` | Algorithme JWT |
| `jwt_access_token_expire_minutes` | `60` | Expiration access token |
| `jwt_refresh_token_expire_days` | `7` | Expiration refresh token |
| `api_v1_prefix` | `/api/v1` | Préfixe API |
| `ollama_base_url` | `http://localhost:11434` | URL Ollama |
| `ollama_timeout` | `120` | Timeout Ollama |
| `ai_temperature` | `0.7` | Température LLM |
| `ai_max_tokens` | `4000` | Max tokens |
| `ai_max_retries` | `3` | Retries parsing |
| `rate_limit_per_minute` | `60` | Rate limit global |
| `cors_origins` | `localhost:8000,3000` | CORS |

### Ports

| Service | Port |
|---------|------|
| Symfony (dev server) | `8000` |
| FastAPI (uvicorn) | `8001` |
| MySQL | `3306` |
| Ollama | `11434` |

---

## 14. Flow "Prof Certifié"

### Principe
Un utilisateur peut demander la certification « Professeur » depuis son profil FO. Un administrateur examine la demande dans le BO et l'approuve ou la refuse. À l'approbation, l'utilisateur passe en `userType = TEACHER` et un badge « Prof certifié » s'affiche sur son profil et ses contenus.

### États de la demande
| État | Description |
|------|-------------|
| `NONE` | Aucune demande — bouton "Demander la certification" visible |
| `PENDING` | Demande soumise — message "En cours de traitement", bouton désactivé |
| `REJECTED` | Demande refusée — motif affiché + bouton "Redemander" |
| `APPROVED` | Certifié — badge "Prof certifié" affiché, plus de formulaire |

### Règles métier
- **Un seul `PENDING` par user** : vérifié côté serveur (idempotence).
- **`APPROVED`** : `user.userType` passe à `TEACHER`. Plus de nouvelle demande possible.
- **`REJECTED`** : l'utilisateur peut re-soumettre une demande.
- **Traçabilité** : chaque décision enregistre `reviewedAt`, `reviewedBy` (admin), et `reason` (motif optionnel).

### Table `teacher_certification_requests`
| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Identifiant |
| `user_id` | FK → users | Demandeur |
| `status` | VARCHAR(20) | PENDING / APPROVED / REJECTED |
| `motivation` | TEXT nullable | Motivation du demandeur |
| `reason` | TEXT nullable | Motif de refus (admin) |
| `requested_at` | DATETIME | Date de soumission |
| `reviewed_at` | DATETIME nullable | Date de traitement |
| `reviewed_by_id` | FK → users nullable | Admin ayant traité |

### Routes

**FO :**
| Route | Méthode | Description |
|-------|---------|-------------|
| `/fo/profile` | GET | Profil avec section certification |
| `/fo/profile/certification` | POST | Soumettre une demande |

**BO :**
| Route | Méthode | Description |
|-------|---------|-------------|
| `/bo/certifications` | GET | Liste paginée + filtrable |
| `/bo/certifications/{id}` | GET | Détail d'une demande |
| `/bo/certifications/{id}/approve` | POST | Approuver |
| `/bo/certifications/{id}/reject` | POST | Refuser (motif optionnel) |

### Badge "Prof certifié"
- Partial Twig : `templates/components/_teacher_badge.html.twig`
- Affiché sur : profil FO, posts (auteur + commentaires), groupes (créateur), matières (créateur)
- Condition : `user.isCertifiedTeacher()` (= `userType === 'TEACHER'`)

### Fichiers créés/modifiés
- `src/Entity/TeacherCertificationRequest.php` — Entité
- `src/Repository/TeacherCertificationRequestRepository.php` — Repository
- `src/Entity/User.php` — Ajout `isCertifiedTeacher()`
- `src/Controller/Fo/ProfileController.php` — Route certification + passage `certRequest` au template
- `src/Controller/Bo/CertificationController.php` — CRUD BO
- `templates/fo/profile/show.html.twig` — Section certification
- `templates/bo/certifications/index.html.twig` — Liste BO
- `templates/bo/certifications/show.html.twig` — Détail + actions BO
- `templates/layouts/bo.html.twig` — Sidebar entry
- `templates/components/_teacher_badge.html.twig` — Badge réutilisable
- `templates/fo/groups/_post_card.html.twig` — Badge auteur posts
- `templates/fo/groups/show.html.twig` — Badge créateur groupe
- `templates/fo/subjects/show.html.twig` — Badge créateur matière
- `tests/Controller/Bo/TeacherCertificationTest.php` — 8 tests (sécurité + fonctionnel)
- `migrations/Version20260205225054.php` — Migration DB
- `docs/teacher_certification_audit.md` — Rapport d'audit

### Tests
```bash
php vendor/bin/phpunit tests/Controller/Bo/TeacherCertificationTest.php
```
8 tests : sécurité (anonymous redirect, user 403, admin 200) + fonctionnel (submit, double-pending blocked, approve, reject, resubmit after rejection).

---

> **Document généré le 05/02/2026** — StudySprint v1.0
