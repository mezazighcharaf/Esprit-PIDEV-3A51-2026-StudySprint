# StudySprint — Documentation Technique Exhaustive pour l'Equipe IT

> **Plateforme d'apprentissage intelligente avec IA locale intégrée**
> Version : 1.0 | Date : 13 février 2026
> Stack : Symfony 6.4 (PHP 8.2) + FastAPI (Python 3.14) + MySQL + Ollama (LLM local)

---

## Table des Matières

1. [Vue d'ensemble du projet](#1-vue-densemble-du-projet)
2. [Architecture technique](#2-architecture-technique)
3. [Structure du projet (arborescence)](#3-structure-du-projet)
4. [Base de données — Schéma relationnel](#4-base-de-données)
5. [Backend Symfony — Contrôleurs, Services, Entités](#5-backend-symfony)
6. [Backend FastAPI — Routers, Models, Schemas](#6-backend-fastapi)
7. [Module IA — AI Gateway](#7-module-ia--ai-gateway)
8. [AiGatewayService — Bridge Symfony ↔ FastAPI](#8-aigatewayservice)
9. [Modules fonctionnels détaillés](#9-modules-fonctionnels)
10. [Sécurité & Authentification](#10-sécurité--authentification)
11. [Tests](#11-tests)
12. [Fixtures (données de démonstration)](#12-fixtures)
13. [Migrations Doctrine](#13-migrations-doctrine)
14. [Configuration & Variables d'environnement](#14-configuration)
15. [Procédure de démarrage](#15-procédure-de-démarrage)
16. [URLs & Ports](#16-urls--ports)
17. [Dépendances](#17-dépendances)
18. [Audits & Corrections appliquées](#18-audits--corrections)

---

## 1. Vue d'ensemble du projet

### Objectif

StudySprint est une plateforme web d'apprentissage destinée aux étudiants et enseignants. Elle propose :

- **Matières & Chapitres** : organisation du contenu pédagogique
- **Quiz** : QCM avec scoring automatique (création manuelle ou génération IA)
- **Flashcards** : cartes mémo avec algorithme de répétition espacée SM-2 (création manuelle ou génération IA)
- **Plans de révision** : calendrier de tâches auto-générées par matière avec suggestions d'optimisation IA
- **Groupes d'étude** : espaces collaboratifs avec publications, commentaires et résumé IA
- **Profil utilisateur** : enrichissement IA (bio, objectifs, routine)
- **Certification enseignant** : workflow de demande / approbation
- **Monitoring IA (BO)** : tableau de bord admin pour superviser les générations IA

### Utilisateurs cibles

| Type | Rôle Symfony | Accès |
|------|-------------|-------|
| Étudiant | `ROLE_USER` | FO complet (quiz, decks, plans, groupes, profil) |
| Enseignant | `ROLE_USER` + `userType=TEACHER` | FO + création de contenu + badge "Prof certifié" |
| Administrateur | `ROLE_ADMIN` | FO + BO complet (CRUD, monitoring IA, certifications) |

---

## 2. Architecture technique

### Stack

| Couche | Technologie | Version |
|--------|------------|---------|
| Frontend | Twig (SSR) + CSS inline + Vanilla JS | — |
| Backend Web | Symfony | 6.4 (PHP ≥ 8.1) |
| API REST | FastAPI | 0.115.0 (Python 3.14) |
| ORM Symfony | Doctrine ORM | 3.6 |
| ORM Python | SQLAlchemy | 2.0.36 |
| Base de données | MySQL | 8.x |
| IA locale | Ollama | latest |
| Modèle LLM | vanilj/qwen2.5-14b-instruct-iq4_xs | quantized |
| Rendu math | KaTeX | intégré globalement |
| Auth API | JWT (python-jose HS256) | — |
| Auth Web | Symfony Security (sessions) | — |

### Diagramme d'architecture

```
┌───────────────────────────┐
│   Navigateur (Twig SSR)   │
└─────────────┬─────────────┘
              │ HTTP
              ▼
┌───────────────────────────┐         ┌─────────────────┐
│  Symfony  (port 8000)     │────────▶│   MySQL 3306    │
│  Controllers + Twig       │         │   studysprint   │
│  Sessions + CSRF          │         └────────▲────────┘
└─────────────┬─────────────┘                  │
              │ AiGatewayService               │ SQLAlchemy
              │ (HttpClient)                   │
              ▼                                │
┌───────────────────────────┐                  │
│  FastAPI  (port 8001)     │──────────────────┘
│  REST CRUD + AI Gateway   │
│  JWT Auth + Swagger       │
└─────────────┬─────────────┘
              │ httpx
              ▼
┌───────────────────────────┐
│  Ollama   (port 11434)    │
│  LLM local (Qwen2.5-14B) │
└───────────────────────────┘
```

### Flux de communication

1. **Symfony → MySQL** : Doctrine ORM pour le CRUD web classique (formulaires, pages, sessions)
2. **FastAPI → MySQL** : SQLAlchemy pour le CRUD REST et la persistance des générations IA
3. **Symfony → FastAPI** : Via `AiGatewayService` (HttpClient Symfony), uniquement pour les appels IA
4. **FastAPI → Ollama** : Via `httpx`, pour l'inférence LLM (génération quiz, flashcards, résumés, etc.)

**Point critique** : Symfony et FastAPI partagent la **même base de données MySQL** (`studysprint`). Les deux ORM (Doctrine et SQLAlchemy) modélisent les mêmes tables et doivent rester synchronisés.

---

## 3. Structure du projet

```
StudySprint/
├── .env                              # Variables Symfony
├── composer.json                     # Dépendances PHP
├── config/
│   ├── packages/                     # Config bundles Symfony
│   ├── routes.yaml
│   └── services.yaml                 # Injection de dépendances (AiGatewayService)
│
├── migrations/                       # Doctrine migrations
│   ├── Version20260202230552.php     # Schéma initial (17 tables)
│   ├── Version20260203182544.php     # Ajouts training
│   ├── Version20260205185349.php     # Champs IA (ai_summary, ai_tags, etc.)
│   └── Version20260205225054.php     # Table teacher_certification_requests
│
├── src/
│   ├── Controller/
│   │   ├── SecurityController.php    # Login/Register/Logout
│   │   ├── AdminController.php       # Dashboard admin
│   │   ├── Bo/                       # 12 contrôleurs Back-Office
│   │   │   ├── AiMonitoringController.php
│   │   │   ├── CertificationController.php
│   │   │   ├── ChapterController.php
│   │   │   ├── FlashcardDeckController.php
│   │   │   ├── GroupPostController.php
│   │   │   ├── PlanTaskController.php
│   │   │   ├── QuizController.php
│   │   │   ├── RevisionPlanController.php
│   │   │   ├── StudyGroupController.php
│   │   │   ├── SubjectController.php
│   │   │   ├── UserController.php
│   │   │   └── UserProfileController.php
│   │   └── Fo/                       # Contrôleurs Front-Office
│   │       ├── AiFeedbackController.php
│   │       ├── GroupsController.php
│   │       ├── PlanningController.php
│   │       ├── ProfileController.php
│   │       ├── SubjectsController.php
│   │       └── Training/
│   │           ├── QuizController.php
│   │           ├── QuizManageController.php
│   │           ├── DeckController.php
│   │           └── DeckManageController.php
│   │
│   ├── Entity/                       # 18 entités Doctrine
│   │   ├── User.php
│   │   ├── UserProfile.php
│   │   ├── Subject.php
│   │   ├── Chapter.php
│   │   ├── Quiz.php
│   │   ├── QuizAttempt.php
│   │   ├── QuizAttemptAnswer.php
│   │   ├── FlashcardDeck.php
│   │   ├── Flashcard.php
│   │   ├── FlashcardReviewState.php
│   │   ├── RevisionPlan.php
│   │   ├── PlanTask.php
│   │   ├── StudyGroup.php
│   │   ├── GroupMember.php
│   │   ├── GroupPost.php
│   │   ├── AiGenerationLog.php
│   │   ├── AiModel.php
│   │   └── TeacherCertificationRequest.php
│   │
│   ├── Repository/                   # 18 repositories Doctrine
│   ├── Form/                         # FormTypes (Bo/ + Fo/)
│   ├── Service/                      # 8 services métier
│   │   ├── AiGatewayService.php      # Bridge Symfony → FastAPI IA
│   │   ├── QuizScoringService.php    # Correction de quiz
│   │   ├── QuizTemplateService.php   # Templates quiz prédéfinis
│   │   ├── Sm2SchedulerService.php   # Algorithme SM-2
│   │   ├── FlashcardTipsService.php  # Conseils pédagogiques
│   │   ├── PlanGeneratorService.php  # Génération auto de plans
│   │   ├── BoDataProvider.php        # Données BO
│   │   └── BoMockDataProvider.php    # Données démo BO
│   └── DataFixtures/
│       └── AppFixtures.php           # Fixtures complètes
│
├── templates/
│   ├── layouts/                      # fo.html.twig, bo.html.twig, base
│   ├── security/                     # login, register
│   ├── components/                   # _teacher_badge, etc.
│   ├── fo/                           # ~54 templates Front-Office
│   │   ├── training/quizzes/         # index, show, play, result, ai_generate
│   │   ├── training/decks/           # index, show, review, ai_generate
│   │   ├── planning/                 # index, show, ai_confirm, etc.
│   │   ├── groups/                   # index, show, _post_card, etc.
│   │   └── profile/                  # show, edit
│   └── bo/                           # ~60 templates Back-Office
│       ├── ai_monitoring/            # dashboard, logs
│       ├── certifications/           # index, show
│       ├── chapters/, groups/, plans/, posts/, quizzes/, subjects/, tasks/
│       └── training/
│
├── tests/
│   ├── bootstrap.php
│   ├── Service/
│   │   ├── Sm2SchedulerServiceTest.php    # 12 tests
│   │   ├── QuizScoringServiceTest.php     # 11 tests
│   │   ├── PlanGeneratorServiceTest.php   # Tests plan generator
│   │   └── AiGatewayServiceTest.php       # 12 tests (mock HTTP)
│   ├── Controller/Bo/
│   │   ├── BoAccessSecurityTest.php       # 39 tests (13 routes × 3 rôles)
│   │   ├── BoAccessControlTest.php        # 37 tests (12 routes × 3 rôles + pagination)
│   │   └── TeacherCertificationTest.php   # 8 tests
│   └── Entity/
│       └── EntityValidationTest.php       # Tests de validation
│
├── e2e/                                 # Tests E2E Playwright
│   ├── playwright.config.ts             # Config (baseURL, timeout)
│   ├── package.json                     # Dépendances npm
│   ├── tsconfig.json
│   └── tests/
│       ├── auth.spec.ts                 # Login/register/logout
│       ├── subjects.spec.ts             # CRUD matières FO
│       ├── planning.spec.ts             # Planning FO
│       ├── groups.spec.ts               # Groupes FO
│       ├── training.spec.ts             # Quiz + Flashcards FO
│       ├── ai-failures.spec.ts          # Gestion erreurs IA
│       └── bo-admin.spec.ts             # CRUD BO admin
│
├── api/                              # FastAPI (Python)
│   ├── .env                          # Variables Python
│   ├── requirements.txt              # 26 packages
│   ├── run.py                        # Script démarrage uvicorn
│   ├── app/
│   │   ├── main.py                   # App FastAPI + middleware
│   │   ├── config.py                 # Settings Pydantic
│   │   ├── database.py               # SQLAlchemy engine + session
│   │   ├── dependencies.py           # JWT + RBAC guards
│   │   ├── models/                   # SQLAlchemy models (7 fichiers, 17 classes)
│   │   │   ├── user.py               # User, UserProfile
│   │   │   ├── subject.py            # Subject, Chapter
│   │   │   ├── training.py           # Quiz, QuizAttempt, QuizAttemptAnswer
│   │   │   ├── flashcard.py          # FlashcardDeck, Flashcard, FlashcardReviewState
│   │   │   ├── planning.py           # RevisionPlan, PlanTask
│   │   │   ├── group.py              # StudyGroup, GroupMember, GroupPost
│   │   │   └── ai.py                 # AiGenerationLog, AiModel
│   │   ├── schemas/                  # Pydantic DTOs (8 fichiers)
│   │   ├── services/                 # Business logic (Python ports)
│   │   │   ├── auth.py, sm2_scheduler.py, quiz_scoring.py, plan_generator.py
│   │   └── routers/                  # API endpoints (8 fichiers, ~70 routes)
│   │       ├── auth.py, users.py, subjects.py, planning.py
│   │       ├── groups.py, training.py, flashcards.py
│   │       └── ai.py                 # AI Gateway (~1050 lignes)
│   └── tests/
│       ├── test_ai_endpoints.py      # 10 tests intégration
│       └── test_ai_failures.py       # 25+ tests (validation, 404, edge cases)
│
└── docs/                             # Documentation additionnelle
```

---

## 4. Base de données

### Vue d'ensemble

- **Nom** : `studysprint`
- **Moteur** : MySQL 8.x
- **Nombre de tables** : 18
- **Partagée entre** : Symfony (Doctrine) et FastAPI (SQLAlchemy)

### Schéma relationnel

```
users (PK: id)
 ├── 1:1 → user_profiles (FK: user_id)
 ├── 1:N → subjects (FK: created_by)
 ├── 1:N → quizzes (FK: owner_id)
 ├── 1:N → flashcard_decks (FK: owner_id)
 ├── 1:N → revision_plans (FK: user_id)
 ├── 1:N → study_groups (FK: created_by)
 ├── 1:N → quiz_attempts (FK: user_id)
 ├── 1:N → flashcard_review_states (FK: user_id)
 ├── 1:N → ai_generation_logs (FK: user_id)
 └── 1:N → teacher_certification_requests (FK: user_id, reviewed_by_id)

subjects (PK: id)
 ├── 1:N → chapters (FK: subject_id) [UNIQUE: subject_id + order_no]
 ├── 1:N → quizzes (FK: subject_id)
 ├── 1:N → flashcard_decks (FK: subject_id)
 └── 1:N → revision_plans (FK: subject_id)

quizzes (PK: id)
 └── 1:N → quiz_attempts (FK: quiz_id)
         └── 1:N → quiz_attempt_answers (FK: attempt_id)

flashcard_decks (PK: id)
 └── 1:N → flashcards (FK: deck_id)
         └── 1:N → flashcard_review_states (FK: flashcard_id) [UNIQUE: user_id + flashcard_id]

revision_plans (PK: id)
 └── 1:N → plan_tasks (FK: plan_id, orphanRemoval)

study_groups (PK: id)
 ├── 1:N → group_members (FK: group_id) [UNIQUE: group_id + user_id]
 └── 1:N → group_posts (FK: group_id)
         └── 1:N → group_posts (self-ref FK: parent_post_id, replies)

ai_models (PK: id) [UNIQUE: name + base_url]
 └── 1:N → ai_generation_logs (FK: model_id)
```

### Tables détaillées

#### `users`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK AUTO_INCREMENT |
| `email` | VARCHAR(180) | UNIQUE, NOT NULL |
| `password` | VARCHAR(255) | NOT NULL (bcrypt) |
| `roles` | JSON | NOT NULL (ex: `["ROLE_USER"]`) |
| `full_name` | VARCHAR(255) | NOT NULL |
| `user_type` | VARCHAR(50) | NOT NULL (`STUDENT`, `TEACHER`, `ADMIN`) |
| `created_at` | DATETIME | NOT NULL (immutable) |
| `updated_at` | DATETIME | NOT NULL (lifecycle callback) |

#### `user_profiles`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `user_id` | INT FK | UNIQUE, ON DELETE CASCADE |
| `level` | VARCHAR(100) | nullable |
| `specialty` | VARCHAR(255) | nullable |
| `bio` | TEXT | nullable |
| `avatar_url` | VARCHAR(500) | nullable |
| `ai_suggested_bio` | TEXT | nullable |
| `ai_suggested_goals` | TEXT | nullable |
| `ai_suggested_routine` | TEXT | nullable |

#### `subjects`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `name` | VARCHAR(255) | NOT NULL |
| `code` | VARCHAR(50) | UNIQUE, NOT NULL |
| `description` | TEXT | nullable |
| `created_by` | INT FK → users | NOT NULL |
| `created_at` | DATETIME | NOT NULL |

#### `chapters`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `subject_id` | INT FK → subjects | NOT NULL, ON DELETE CASCADE |
| `title` | VARCHAR(255) | NOT NULL |
| `order_no` | INT | NOT NULL, UNIQUE(subject_id, order_no) |
| `summary` | TEXT | nullable (résumé manuel) |
| `content` | TEXT | nullable |
| `ai_summary` | TEXT | nullable |
| `ai_key_points` | JSON | nullable |
| `ai_tags` | JSON | nullable |
| `created_by` | INT FK → users | NOT NULL |
| `created_at` | DATETIME | NOT NULL |

#### `quizzes`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `owner_id` | INT FK → users | NOT NULL |
| `subject_id` | INT FK → subjects | NOT NULL |
| `chapter_id` | INT FK → chapters | nullable |
| `title` | VARCHAR(255) | NOT NULL |
| `difficulty` | VARCHAR(50) | NOT NULL (`EASY`, `MEDIUM`, `HARD`) |
| `template_key` | VARCHAR(100) | nullable |
| `questions` | JSON | NOT NULL (voir format ci-dessous) |
| `is_published` | BOOLEAN | NOT NULL, default false |
| `generated_by_ai` | BOOLEAN | NOT NULL, default false |
| `ai_meta` | JSON | nullable (`{provider, model, log_id}`) |
| `created_at` | DATETIME | NOT NULL |
| `updated_at` | DATETIME | NOT NULL |

**Format `questions` JSON :**
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
    "explanation": "La dérivée de x^n est n*x^(n-1)"
  }
]
```

#### `quiz_attempts`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `user_id` | INT FK → users | NOT NULL |
| `quiz_id` | INT FK → quizzes | NOT NULL, ON DELETE CASCADE |
| `started_at` | DATETIME | NOT NULL |
| `completed_at` | DATETIME | nullable |
| `score` | DECIMAL(5,2) | nullable (pourcentage) |
| `total_questions` | INT | NOT NULL |
| `correct_count` | INT | nullable |
| `duration_seconds` | INT | nullable |

#### `quiz_attempt_answers`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `attempt_id` | INT FK → quiz_attempts | ON DELETE CASCADE |
| `question_index` | INT | NOT NULL |
| `selected_choice_key` | VARCHAR(100) | NOT NULL |
| `is_correct` | BOOLEAN | NOT NULL |

#### `flashcard_decks`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `owner_id` | INT FK → users | NOT NULL |
| `subject_id` | INT FK → subjects | NOT NULL |
| `chapter_id` | INT FK → chapters | nullable |
| `title` | VARCHAR(255) | NOT NULL |
| `template_key` | VARCHAR(100) | nullable |
| `is_published` | BOOLEAN | default false |
| `generated_by_ai` | BOOLEAN | default false |
| `ai_meta` | JSON | nullable |
| `created_at` | DATETIME | NOT NULL |

#### `flashcards`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `deck_id` | INT FK → flashcard_decks | ON DELETE CASCADE |
| `front` | TEXT | NOT NULL (question) |
| `back` | TEXT | NOT NULL (réponse) |
| `hint` | VARCHAR(500) | nullable |
| `position` | INT | NOT NULL |
| `created_at` | DATETIME | NOT NULL |

#### `flashcard_review_states`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `user_id` | INT FK → users | ON DELETE CASCADE |
| `flashcard_id` | INT FK → flashcards | ON DELETE CASCADE |
| `repetitions` | INT | default 0 |
| `interval_days` | INT | default 1 |
| `ease_factor` | DECIMAL(4,2) | default 2.50 |
| `due_at` | DATE | NOT NULL |
| `last_reviewed_at` | DATETIME | nullable |

**Contrainte unique** : `(user_id, flashcard_id)`

#### `revision_plans`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `user_id` | INT FK → users | NOT NULL |
| `subject_id` | INT FK → subjects | NOT NULL |
| `chapter_id` | INT FK → chapters | nullable |
| `title` | VARCHAR(255) | NOT NULL |
| `start_date` | DATE | NOT NULL |
| `end_date` | DATE | NOT NULL |
| `status` | VARCHAR(50) | `DRAFT`, `ACTIVE`, `DONE` |
| `generated_by_ai` | BOOLEAN | default false |
| `ai_meta` | JSON | nullable |
| `created_at` | DATETIME | NOT NULL |

#### `plan_tasks`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `plan_id` | INT FK → revision_plans | ON DELETE CASCADE |
| `title` | VARCHAR(255) | NOT NULL |
| `task_type` | VARCHAR(50) | `REVISION`, `QUIZ`, `FLASHCARD`, `CUSTOM` |
| `start_at` | DATETIME | NOT NULL |
| `end_at` | DATETIME | NOT NULL |
| `status` | VARCHAR(50) | `TODO`, `DOING`, `DONE` |
| `priority` | SMALLINT | 1-3 |
| `notes` | TEXT | nullable |
| `created_at` | DATETIME | NOT NULL |

#### `study_groups`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `name` | VARCHAR(255) | NOT NULL |
| `description` | TEXT | nullable |
| `privacy` | VARCHAR(50) | `PUBLIC`, `PRIVATE` |
| `created_by` | INT FK → users | NOT NULL |
| `created_at` | DATETIME | NOT NULL |

#### `group_members`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `group_id` | INT FK → study_groups | NOT NULL |
| `user_id` | INT FK → users | NOT NULL |
| `member_role` | VARCHAR(50) | `owner`, `admin`, `member` |
| `joined_at` | DATETIME | NOT NULL |

**Contrainte unique** : `(group_id, user_id)`

#### `group_posts`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `group_id` | INT FK → study_groups | ON DELETE CASCADE |
| `author_id` | INT FK → users | NOT NULL |
| `parent_post_id` | INT FK → group_posts | nullable (self-ref, CASCADE) |
| `post_type` | VARCHAR(50) | `POST`, `COMMENT` |
| `title` | VARCHAR(255) | nullable |
| `body` | TEXT | NOT NULL |
| `attachment_url` | VARCHAR(500) | nullable |
| `ai_summary` | TEXT | nullable |
| `ai_category` | VARCHAR(100) | nullable |
| `ai_tags` | JSON | nullable |
| `created_at` | DATETIME | NOT NULL |

#### `ai_generation_logs`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `user_id` | INT FK → users | nullable, ON DELETE SET NULL |
| `model_id` | INT FK → ai_models | nullable, ON DELETE SET NULL |
| `feature` | VARCHAR(100) | NOT NULL (voir constantes) |
| `input_json` | JSON | NOT NULL |
| `prompt` | TEXT | NOT NULL |
| `output_json` | JSON | nullable |
| `status` | VARCHAR(50) | `pending`, `success`, `failed` |
| `error_message` | TEXT | nullable |
| `latency_ms` | INT | nullable |
| `user_feedback` | SMALLINT | nullable (1-5) |
| `idempotency_key` | VARCHAR(32) | nullable (SHA256 tronqué) |
| `created_at` | DATETIME | NOT NULL |

**Features** : `quiz`, `flashcard`, `revision_plan`, `summary`, `profile`, `planning_suggest`, `post_summary`

#### `ai_models`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `name` | VARCHAR(255) | NOT NULL |
| `provider` | VARCHAR(100) | NOT NULL (ex: `ollama`) |
| `base_url` | VARCHAR(500) | NOT NULL |
| `is_default` | BOOLEAN | default false |
| `created_at` | DATETIME | NOT NULL |

**Contrainte unique** : `(name, base_url)`

#### `teacher_certification_requests`
| Colonne | Type | Contraintes |
|---------|------|-------------|
| `id` | INT | PK |
| `user_id` | INT FK → users | NOT NULL |
| `status` | VARCHAR(20) | `PENDING`, `APPROVED`, `REJECTED` |
| `motivation` | TEXT | nullable |
| `reason` | TEXT | nullable (motif admin) |
| `requested_at` | DATETIME | NOT NULL |
| `reviewed_at` | DATETIME | nullable |
| `reviewed_by_id` | INT FK → users | nullable |

---

## 5. Backend Symfony

### Contrôleurs Front-Office (FO)

#### `SecurityController` — `/login`, `/register`, `/logout`
- Formulaires de connexion et d'inscription
- Hash bcrypt via `UserPasswordHasherInterface`
- Création automatique de `UserProfile` à l'inscription

#### `Fo\ProfileController` — `/fo/profile`
| Route | Méthode | Action |
|-------|---------|--------|
| `/fo/profile` | GET | Affichage profil + suggestions IA |
| `/fo/profile/edit` | POST | Modification profil |
| `/fo/profile/ai-enhance` | POST | Demande suggestions IA via `AiGatewayService::enhanceProfile()` |
| `/fo/profile/certification` | POST | Soumission demande certification enseignant |

#### `Fo\SubjectsController` — `/fo/subjects`
| Route | Méthode | Action |
|-------|---------|--------|
| `/fo/subjects` | GET | Liste des matières |
| `/fo/subjects/{id}` | GET | Détail matière + chapitres |
| `/fo/subjects/new` | POST | Création matière |
| `/fo/subjects/{id}/edit` | POST | Modification |
| `/fo/subjects/{id}/delete` | POST | Suppression |

#### `Fo\Training\QuizController` — `/fo/training/quizzes`
| Route | Méthode | Action |
|-------|---------|--------|
| `/fo/training/quizzes` | GET | Liste quiz publiés |
| `/fo/training/quizzes/{id}` | GET | Détail quiz |
| `/fo/training/quizzes/{id}/play` | GET | Interface de jeu |
| `/fo/training/quizzes/{id}/submit` | POST | Soumission réponses → scoring |
| `/fo/training/quizzes/{id}/result/{attemptId}` | GET | Résultat détaillé |
| `/fo/training/quizzes/ai-generate` | GET | Formulaire génération IA |
| `/fo/training/quizzes/ai-generate` | POST | Appel `AiGatewayService::generateQuiz()` (AJAX) |
| `/fo/training/quizzes/my` | GET | Mes quiz |
| `/fo/training/quizzes/history` | GET | Historique tentatives |

#### `Fo\Training\DeckController` — `/fo/training/decks`
| Route | Méthode | Action |
|-------|---------|--------|
| `/fo/training/decks` | GET | Liste decks publiés |
| `/fo/training/decks/{id}` | GET | Détail deck |
| `/fo/training/decks/{id}/study` | GET | Mode révision (cartes dues SM-2) |
| `/fo/training/decks/{id}/review` | POST | Soumettre revue SM-2 (Again/Hard/Good/Easy) |
| `/fo/training/decks/ai-generate` | GET | Formulaire génération IA |
| `/fo/training/decks/ai-generate` | POST | Appel `AiGatewayService::generateFlashcards()` (AJAX) |
| `/fo/training/decks/my` | GET | Mes decks |

#### `Fo\PlanningController` — `/fo/planning`
| Route | Méthode | Action |
|-------|---------|--------|
| `/fo/planning` | GET | Calendrier + sessions + IA |
| `/fo/planning/{id}` | GET | Détail plan + tâches |
| `/fo/planning/generate` | POST | Génération automatique via `PlanGeneratorService` |
| `/fo/planning/new` | POST | Création manuelle |
| `/fo/planning/{id}/edit` | POST | Modification plan |
| `/fo/planning/{id}/delete` | POST | Suppression |
| `/fo/planning/{planId}/tasks/new` | POST | Ajout tâche |
| `/fo/planning/tasks/{taskId}/edit` | POST | Modification tâche |
| `/fo/planning/tasks/{taskId}/toggle` | POST | Toggle TODO/DONE |
| `/fo/planning/{id}/ai-suggest` | POST | `AiGatewayService::suggestPlanOptimizations()` (AJAX) |
| `/fo/planning/ai-confirm` | GET | Page confirmation suggestions |
| `/fo/planning/{id}/ai-apply` | POST | `AiGatewayService::applyPlanSuggestions()` |

#### `Fo\GroupsController` — `/fo/groups`
| Route | Méthode | Action |
|-------|---------|--------|
| `/fo/groups` | GET | Liste groupes (publics + mes groupes) |
| `/fo/groups/{id}` | GET | Page groupe (hero + posts + sidebar) |
| `/fo/groups/new` | POST | Création groupe |
| `/fo/groups/{id}/edit` | POST | Modification |
| `/fo/groups/{id}/delete` | POST | Suppression |
| `/fo/groups/{id}/join` | POST | Rejoindre |
| `/fo/groups/{id}/leave` | POST | Quitter |
| `/fo/groups/{id}/post` | POST | Publier |
| `/fo/groups/{gid}/post/{pid}/comment` | POST | Commenter |
| `/fo/groups/{gid}/post/{pid}/delete` | POST | Supprimer post |
| `/fo/groups/{gid}/post/{pid}/ai-summarize` | POST | `AiGatewayService::summarizePost()` (AJAX) |

#### `Fo\AiFeedbackController` — `/fo/ai`
| Route | Méthode | Action |
|-------|---------|--------|
| `/fo/ai/feedback` | POST | `AiGatewayService::submitFeedback()` (AJAX, note 1-5) |

### Contrôleurs Back-Office (BO)

Tous les contrôleurs BO requièrent `ROLE_ADMIN` et offrent un CRUD complet (index, show, new, edit, delete) :

| Contrôleur | Préfixe | Entité |
|-----------|---------|--------|
| `UserController` | `/bo/users` | User |
| `UserProfileController` | `/bo/user-profiles` | UserProfile |
| `SubjectController` | `/bo/subjects` | Subject |
| `ChapterController` | `/bo/chapters` | Chapter (+ moveUp/moveDown + AI summarize) |
| `QuizController` | `/bo/quizzes` | Quiz |
| `FlashcardDeckController` | `/bo/flashcard-decks` | FlashcardDeck |
| `RevisionPlanController` | `/bo/plans` | RevisionPlan |
| `PlanTaskController` | `/bo/tasks` | PlanTask |
| `StudyGroupController` | `/bo/groups` | StudyGroup |
| `GroupPostController` | `/bo/posts` | GroupPost |
| `CertificationController` | `/bo/certifications` | TeacherCertificationRequest (approve/reject) |
| `AiMonitoringController` | `/bo/ai-monitoring` | Dashboard IA + logs paginés |

### Services métier Symfony

| Service | Responsabilité |
|---------|---------------|
| `AiGatewayService` | Proxy HTTP vers FastAPI AI Gateway (10 méthodes typées) |
| `QuizScoringService` | Correction quiz multi-format, calcul score, durée |
| `QuizTemplateService` | Générateur de templates de quiz prédéfinis |
| `Sm2SchedulerService` | Algorithme SM-2 de répétition espacée (conforme spec officielle) |
| `FlashcardTipsService` | Conseils pédagogiques pour la création de flashcards |
| `PlanGeneratorService` | Génération auto de plans : distribution tâches/chapitres, détection overlap, remplacement atomique |
| `BoDataProvider` | Agrégateur de données pour les pages BO |
| `BoMockDataProvider` | Données de démonstration BO |

### Algorithme SM-2 (SuperMemo 2)

Implémenté dans `Sm2SchedulerService::applyReview()` :

```
Entrées : FlashcardReviewState, quality (0-5)
  quality = 0 (Again) : reset repetitions=0, interval=1, EF INCHANGÉ
  quality = 3 (Hard)  : EF recalculé, interval calculé
  quality = 4 (Good)  : EF recalculé, interval × EF
  quality = 5 (Easy)  : EF recalculé, interval × EF

Formule EF : EF' = EF + (0.1 - (5-q) × (0.08 + (5-q) × 0.02))
EF minimum : 1.3
Intervalles : rep 1 → 1j, rep 2 → 6j, rep 3+ → interval × EF
```

---

## 6. Backend FastAPI

### Routers enregistrés (~70 routes)

| Router | Préfixe | Fichier | Nb routes |
|--------|---------|---------|-----------|
| Auth | `/api/v1/auth` | `auth.py` | 4 |
| Users | `/api/v1/users` | `users.py` | 7 |
| Subjects | `/api/v1/subjects` + `/api/v1/chapters` | `subjects.py` | 10 |
| Training | `/api/v1/training/quizzes` | `training.py` | 7 |
| Flashcards | `/api/v1/training/decks` + `/api/v1/training/review` | `flashcards.py` | 10 |
| Planning | `/api/v1/planning` | `planning.py` | 11 |
| Groups | `/api/v1/groups` + `/api/v1/posts` | `groups.py` | 11 |
| AI Gateway | `/api/v1/ai` | `ai.py` | 10 |

### Authentification API

- **Méthode** : JWT (HS256)
- **Access token** : 60 min
- **Refresh token** : 7 jours
- **Password** : bcrypt (compatible avec les hashes Symfony)
- **Guards** : `get_current_user`, `get_admin_user`, `get_teacher_or_admin`

### SQLAlchemy Models

Les modèles Python reflètent exactement les tables Doctrine (mêmes noms de colonnes, mêmes types, mêmes FK) :

| Fichier | Classes |
|---------|---------|
| `models/user.py` | `User`, `UserProfile` |
| `models/subject.py` | `Subject`, `Chapter` (avec `ai_summary`, `ai_key_points`, `ai_tags`) |
| `models/training.py` | `Quiz`, `QuizAttempt`, `QuizAttemptAnswer` |
| `models/flashcard.py` | `FlashcardDeck`, `Flashcard`, `FlashcardReviewState` |
| `models/planning.py` | `RevisionPlan`, `PlanTask` |
| `models/group.py` | `StudyGroup`, `GroupMember`, `GroupPost` (avec `ai_summary`, `ai_category`, `ai_tags`) |
| `models/ai.py` | `AiGenerationLog`, `AiModel` |

### Services Python (ports Symfony)

| Service | Port de | Fonctions |
|---------|---------|-----------|
| `sm2_scheduler.py` | `Sm2SchedulerService.php` | `apply_review`, `create_initial_state`, `get_next_review_dates`, `calculate_retention` |
| `quiz_scoring.py` | `QuizScoringService.php` | `score_attempt`, `get_missing_answers`, `get_detailed_results` |
| `plan_generator.py` | `PlanGeneratorService.php` | `find_overlapping_plan`, `generate_plan`, `replace_plan` |

---

## 7. Module IA — AI Gateway

### Vue d'ensemble

Le module IA est entièrement centralisé dans `api/app/routers/ai.py` (~1050 lignes). Toutes les interactions avec le LLM passent par ce fichier unique.

### Provider

- **Ollama** (local) : seul provider actif
- **Modèle** : `vanilj/qwen2.5-14b-instruct-iq4_xs:latest` (14B params, quantization IQ4_XS)
- **URL** : `http://localhost:11434`
- **Timeout** : 120s pour les générations, 5s pour le status check

### Endpoints IA

| Endpoint | Méthode | Description | Timeout | Feature log |
|----------|---------|-------------|---------|-------------|
| `/api/v1/ai/status` | GET | Vérifie disponibilité Ollama | 10s | — |
| `/api/v1/ai/generate/quiz` | POST | Génère quiz complet (questions + choix + réponses) | 120s | `quiz` |
| `/api/v1/ai/generate/flashcards` | POST | Génère deck de flashcards | 120s | `flashcard` |
| `/api/v1/ai/profile/enhance` | POST | Suggestions profil (bio, objectifs, routine) | 60s | `profile` |
| `/api/v1/ai/chapter/summarize` | POST | Résumé + points clés + tags d'un chapitre | 60s | `summary` |
| `/api/v1/ai/planning/suggest` | POST | Suggestions d'optimisation de plan | 120s | `planning_suggest` |
| `/api/v1/ai/planning/apply` | POST | Applique les suggestions IA au plan | 30s | `planning_suggest` |
| `/api/v1/ai/post/summarize` | POST | Résumé + catégorie + tags d'un post | 60s | `post_summary` |
| `/api/v1/ai/feedback` | POST | Feedback utilisateur (note 1-5) | 10s | — |
| `/api/v1/ai/logs/stats` | GET | Statistiques d'utilisation IA | 5s | — |

### Fonctionnement interne détaillé

```
1. Requête reçue (ex: POST /ai/generate/quiz)
2. Validation Pydantic (paramètres requis, types, bornes)
3. Vérification entité en DB (ex: subject_id existe ?)
4. Génération clé d'idempotence (SHA256 tronqué 32 chars)
5. Vérification doublon (même clé = même résultat)
6. Création AiGenerationLog (status=pending)
7. Construction du prompt contextuel (avec données DB)
8. Appel Ollama via httpx (call_ai_with_fallback)
   └── Jusqu'à 3 retries si parsing JSON échoue
9. Parsing réponse :
   └── parse_json_response() gère :
       - JSON pur
       - JSON dans bloc ```json ... ```
       - JSON dans bloc ``` ... ```
       - JSON extrait d'un texte environnant
10. Persistance en DB (quiz/deck/champs IA sur entité)
11. Mise à jour AiGenerationLog (status=success, output_json, latency_ms)
12. Retour JSON au client Symfony
```

### Fonctions utilitaires IA

| Fonction | Rôle |
|----------|------|
| `call_ai_with_fallback()` | Appelle Ollama, gère timeout/retries |
| `parse_json_response()` | Parse JSON depuis réponse LLM (gère markdown, code blocks) |
| `generate_idempotency_key()` | SHA256(user_id + feature + input) tronqué 32 chars |

### Configuration IA

| Paramètre | Valeur | Fichier |
|-----------|--------|---------|
| `ollama_base_url` | `http://localhost:11434` | `api/app/config.py` |
| `ollama_timeout` | `120` secondes | `api/app/config.py` |
| `ai_temperature` | `0.7` | `api/app/config.py` |
| `ai_max_tokens` | `4000` | `api/app/config.py` |
| `ai_max_retries` | `3` | `api/app/config.py` |

---

## 8. AiGatewayService

### Rôle

`src/Service/AiGatewayService.php` est le **pont centralisé** entre Symfony et le FastAPI AI Gateway. Il remplace tous les appels `HttpClientInterface` avec URLs codées en dur qui existaient auparavant dans les contrôleurs.

### Configuration

**`config/services.yaml`** :
```yaml
App\Service\AiGatewayService:
    arguments:
        $aiGatewayBaseUrl: '%env(AI_GATEWAY_BASE_URL)%'
```

**`.env`** :
```env
AI_GATEWAY_BASE_URL=http://localhost:8001
```

### Méthodes

| Méthode | Endpoint appelé | Timeout |
|---------|----------------|---------|
| `getStatus()` | `GET /api/v1/ai/status` | 10s |
| `generateQuiz(userId, subjectId, chapterId, numQuestions, difficulty, topic)` | `POST /api/v1/ai/generate/quiz` | 120s |
| `generateFlashcards(userId, subjectId, chapterId, numCards, topic, includeHints)` | `POST /api/v1/ai/generate/flashcards` | 120s |
| `enhanceProfile(userId, currentBio, currentLevel, currentSpecialty, goals)` | `POST /api/v1/ai/profile/enhance` | 60s |
| `summarizeChapter(userId, chapterId)` | `POST /api/v1/ai/chapter/summarize` | 60s |
| `suggestPlanOptimizations(userId, planId, optimizationGoals)` | `POST /api/v1/ai/planning/suggest` | 120s |
| `applyPlanSuggestions(userId, suggestionLogId)` | `POST /api/v1/ai/planning/apply` | 30s |
| `summarizePost(userId, postId)` | `POST /api/v1/ai/post/summarize` | 60s |
| `submitFeedback(userId, logId, rating)` | `POST /api/v1/ai/feedback` | 10s |
| `getStats()` | `GET /api/v1/ai/logs/stats` | 5s |

### Contrôleurs refactorisés

Les 8 contrôleurs suivants utilisent désormais `AiGatewayService` au lieu de `HttpClientInterface` :

1. `Fo\Training\QuizController` → `generateQuiz()`
2. `Fo\Training\DeckController` → `generateFlashcards()`
3. `Fo\ProfileController` → `enhanceProfile()`
4. `Fo\GroupsController` → `summarizePost()`
5. `Fo\PlanningController` → `suggestPlanOptimizations()` + `applyPlanSuggestions()`
6. `Fo\AiFeedbackController` → `submitFeedback()`
7. `Bo\ChapterController` → `summarizeChapter()`
8. `Bo\AiMonitoringController` → `getStats()`

**Zéro URL codée en dur** dans les contrôleurs.

---

## 9. Modules fonctionnels

### Module 1 — Authentification & Profil

**Inscription** : Formulaire Twig → création User + UserProfile → login automatique
**Connexion** : Symfony Security (session) pour le web, JWT pour l'API
**Profil** : bio, niveau, spécialité, avatar + suggestions IA (non-destructif)
**Certification enseignant** : PENDING → APPROVED/REJECTED (workflow admin BO)

### Module 2 — Matières & Chapitres

- CRUD complet FO + BO
- Chapitres ordonnés par matière (moveUp/moveDown avec transaction atomique)
- Résumé IA : summary + 5 key_points + 5 tags (persisté sur l'entité Chapter)

### Module 3 — Quiz

**Flux complet :**
```
[Index] → [AI Generate Form] → [AJAX POST] → [FastAPI génère quiz]
   → [Redirect vers quiz] → [Play] → [Submit] → [Scoring] → [Result]
```

- Génération IA : 1-20 questions, 3 difficultés, sujet optionnel
- Scoring automatique : `QuizScoringService` (4 formats de réponse supportés)
- Historique des tentatives par utilisateur
- Quiz créés par IA marqués `generated_by_ai = true` + `ai_meta`

### Module 4 — Flashcards (SM-2)

**Flux complet :**
```
[Index] → [AI Generate Form] → [AJAX POST] → [FastAPI génère deck]
   → [Redirect vers deck] → [Study] → [Review] → [Grade SM-2] → [Next card]
```

- Génération IA : 1-50 cartes, indices optionnels
- Révision espacée SM-2 : Again(0)/Hard(3)/Good(4)/Easy(5)
- État de révision par utilisateur et par carte (`FlashcardReviewState`)
- Calcul automatique de la prochaine date de révision

### Module 5 — Planning & Révision

**Génération auto :**
```
[Sélection matière + dates] → PlanGeneratorService
   → Crée tâches (REVISION/QUIZ/FLASHCARD) réparties sur la période
   → Détection overlap (409 Conflict)
```

**Suggestions IA (2 étapes) :**
```
1. [Bouton "Suggérer"] → AJAX POST → FastAPI analyse le plan → retourne suggestions
2. [Page de confirmation] → utilisateur revoit les suggestions
3. [Bouton "Appliquer"] → POST → FastAPI applique les modifications en DB
```

Actions IA supportées : `move`, `reschedule`, `delete`, changement de priorité

### Module 6 — Groupes d'étude

- Groupes publics/privés avec rôles (owner/admin/member)
- Publications + commentaires (self-referencing)
- Résumé IA par post : summary + catégorie (`question`, `discussion`, `resource`, `announcement`) + tags
- Badge "Prof certifié" sur les posts d'enseignants

### Module 7 — Monitoring IA (BO)

**Sidebar BO** : Section "Intelligence Artificielle" avec lien "Monitoring IA" (`bo_ai_monitoring_dashboard`), active state automatique.

**Dashboard** (`/bo/ai-monitoring`) :
- KPIs : total requêtes, succès, échecs, taux d'échec, latence moyenne
- Stats par feature (quiz, flashcard, planning, summary, etc.)
- Logs récents (20 derniers)
- Feedback moyen utilisateurs
- Intégration stats FastAPI en temps réel (via `AiGatewayService::getStats()`)
- **Bouton "Échecs uniquement"** : lien rapide vers `/bo/ai-monitoring/logs?status=failed`
- **Carte "Top 5 erreurs fréquentes"** : requête DQL `GROUP BY errorMessage, ORDER BY cnt DESC, LIMIT 5` avec exclusion des `NULL` et chaînes vides

**Logs** (`/bo/ai-monitoring/logs`) :
- Liste paginée (50/page) + filtres par feature et status
- **Compteur total filtré** (`totalLogs`) : affiche le nombre exact de résultats correspondant aux filtres actifs (pas la taille de la page)

---

## 10. Sécurité & Authentification

### Symfony (Web)

- **Méthode** : Sessions PHP + formulaires CSRF
- **Firewall** : `main` (form_login + logout)
- **Rôles** : `ROLE_USER` (tous), `ROLE_ADMIN` (BO)
- **Protection CSRF** : tokens sur tous les formulaires et actions POST
- **Hashing** : bcrypt via `UserPasswordHasherInterface`

### FastAPI (API)

- **Méthode** : JWT Bearer tokens (HS256)
- **Access token** : 60 minutes
- **Refresh token** : 7 jours
- **Hashing** : bcrypt (passlib, compatible Symfony)

### RBAC FastAPI

| Guard | Rôle requis |
|-------|------------|
| `get_current_user` | Tout utilisateur authentifié |
| `get_admin_user` | `ROLE_ADMIN` uniquement |
| `get_teacher_or_admin` | `userType = TEACHER` ou `ROLE_ADMIN` |

### BOLA Protection

Vérification systématique que l'utilisateur accède uniquement à ses propres ressources (ou est admin) :
```python
def require_owner_or_admin(resource_user_id, current_user):
    if not (current_user.id == resource_user_id or current_user.is_admin()):
        raise HTTPException(403)
```

### CORS

```python
allow_origins = ["http://localhost:8000", "http://localhost:3000", "http://localhost:8001"]
allow_credentials = True
allow_methods = ["*"]
allow_headers = ["*"]
```

---

## 11. Tests

### Tests Symfony (PHPUnit)

```bash
# Tous les tests
php vendor/bin/phpunit --testdox

# Tests BO uniquement (84 tests)
php vendor/bin/phpunit tests/Controller/Bo/ --testdox

# Mapping Doctrine (sans MySQL)
php bin/console doctrine:schema:validate --skip-sync
```

#### Tests de services

| Fichier | Tests | Description |
|---------|-------|-------------|
| `tests/Service/Sm2SchedulerServiceTest.php` | 12 | Algorithme SM-2 (quality < 3, EF floor, intervalles) |
| `tests/Service/QuizScoringServiceTest.php` | 11 | Scoring multi-format, edge cases |
| `tests/Service/PlanGeneratorServiceTest.php` | — | Génération de plans, overlap |
| `tests/Service/AiGatewayServiceTest.php` | 12 | Mock HTTP : toutes méthodes, erreurs, trailing slash URL |

#### Tests de contrôleurs BO (sécurité + fonctionnel)

| Fichier | Tests | Description |
|---------|-------|-------------|
| `tests/Controller/Bo/BoAccessSecurityTest.php` | 39 | 13 routes BO × 3 rôles (anon→redirect, user→403, admin→200) |
| `tests/Controller/Bo/BoAccessControlTest.php` | 37 | 12 routes BO × 3 rôles + test pagination markup |
| `tests/Controller/Bo/TeacherCertificationTest.php` | 8 | Sécurité + flow complet (submit, doublon bloqué, approve, reject, re-submit) |

#### Tests d'entités

| Fichier | Tests | Description |
|---------|-------|-------------|
| `tests/Entity/EntityValidationTest.php` | — | Validation contraintes entités |

#### Robustesse : pattern `requireDatabase()`

Les tests BO qui nécessitent MySQL utilisent un helper `requireDatabase()` qui tente une connexion DB via `$connection->executeQuery('SELECT 1')`. Si MySQL est indisponible, le test est marqué `skipped` (pas d'erreur). Cela permet :

- **MySQL OFF** : les tests anonymes (redirect) passent ✅, les tests DB sont skippés ↩ (0 erreur)
- **MySQL ON** : tous les 84 tests BO passent ✅ (0 skip)

```php
private function requireDatabase(): void
{
    $connection = self::getContainer()->get('doctrine')->getConnection();
    try {
        $connection->executeQuery('SELECT 1');
    } catch (\Exception $e) {
        $this->markTestSkipped('MySQL not available: ' . $e->getMessage());
    }
}
```

### Tests E2E Playwright

Le projet inclut une suite de tests end-to-end avec Playwright dans le dossier `e2e/`.

```bash
# Installation
cd e2e && npm install && npx playwright install

# Lancer tous les tests E2E (serveur Symfony requis sur localhost:8000)
cd e2e && npx playwright test

# Mode UI (debug interactif)
cd e2e && npx playwright test --ui
```

**Prérequis** : serveur Symfony démarré (`php -S localhost:8000 -t public`) + MySQL actif + fixtures chargées.

| Fichier | Scénario |
|---------|----------|
| `e2e/tests/auth.spec.ts` | Login / register / logout / redirections |
| `e2e/tests/subjects.spec.ts` | CRUD matières FO |
| `e2e/tests/planning.spec.ts` | Planning FO (création, tâches, toggle) |
| `e2e/tests/groups.spec.ts` | Groupes FO (join, post, commentaire) |
| `e2e/tests/training.spec.ts` | Quiz (play, submit, score) + Flashcards (review SM-2) |
| `e2e/tests/ai-failures.spec.ts` | Gestion erreurs IA (timeouts, fallbacks) |
| `e2e/tests/bo-admin.spec.ts` | CRUD BO admin (navigation, create, edit, delete) |

**Configuration** : `e2e/playwright.config.ts` — baseURL `http://127.0.0.1:8000`, timeout 30s.

### Tests Python (pytest)

```bash
cd api && pytest tests/ -v
```

| Fichier | Tests | Description |
|---------|-------|-------------|
| `api/tests/test_ai_endpoints.py` | 10 | Intégration : status, quiz gen, flashcard gen, chapter summarize, profile enhance, planning suggest, post summarize, logs stats, feedback, idempotency |
| `api/tests/test_ai_failures.py` | 25+ | Validation 422 (missing fields, invalid types, bounds), 404 (non-existent entities), malformed JSON, parse_json_response edge cases, idempotency key determinism |

**Note** : Les tests `test_ai_endpoints.py` nécessitent que FastAPI + Ollama soient démarrés. Ils se skippent automatiquement si le serveur n'est pas accessible.

---

## 12. Fixtures

### Chargement

```bash
php bin/console doctrine:fixtures:load --no-interaction
```

### Données créées (`src/DataFixtures/AppFixtures.php`)

| Catégorie | Quantité | Détails |
|-----------|----------|---------|
| Users | 4 | admin, alice (student), bob (student), prof.claire (teacher) |
| User Profiles | 2 | alice (Maths, LICENCE), bob (Physique, MASTER) |
| Subjects | 3 | Maths Avancées, Physique Quantique, Chimie Organique |
| Chapters | 8 | 3 maths + 3 physique + 2 chimie |
| Study Groups | 3 | Maths Terminale, Physique Prépa, Chimie Avancée |
| Group Members | 5 | Répartis entre les 3 groupes |
| Group Posts | 5 | 3 posts + 2 commentaires |
| Revision Plans | 2 | Maths (alice), Physique (bob) |
| Plan Tasks | 4 | Répartis sur les 2 plans |
| Quizzes | 2 | Intégrales (medium), MQ (hard), publiés |
| Quiz Attempts | 2 | alice 100%, bob 50% |
| Flashcard Decks | 2 | Formules Maths (6 cartes), Physique (4 cartes) |
| Flashcards | 10 | Avec review states initiaux (SM-2) |
| AI Model | 1 | Ollama qwen2.5-14b, is_default=true |
| AI Generation Logs | 7 | 6 success (quiz, flashcard, profile, summary, planning, post) + 1 failed |
| AI Fields | — | profile1 : ai_suggested_bio/goals/routine ; post1 : ai_summary/category/tags |

### Comptes de démonstration

| Email | Mot de passe | Rôle |
|-------|-------------|------|
| `admin@studysprint.local` | `admin123` | ADMIN |
| `alice.martin@studysprint.local` | `user123` | STUDENT |
| `bob.dupont@studysprint.local` | `user123` | STUDENT |
| `prof.claire@studysprint.local` | `user123` | TEACHER |

---

## 13. Migrations Doctrine

| Migration | Date | Description |
|-----------|------|-------------|
| `Version20260202230552` | 02/02/2026 | Schéma initial : 17 tables (users, subjects, chapters, quizzes, flashcards, plans, groups, AI) |
| `Version20260203182544` | 03/02/2026 | Ajouts training (quiz_attempts, quiz_attempt_answers) |
| `Version20260205185349` | 05/02/2026 | Champs IA : `ai_summary`, `ai_key_points`, `ai_tags` (chapters), `ai_summary`, `ai_category`, `ai_tags` (group_posts), `ai_suggested_bio/goals/routine` (user_profiles), `user_feedback`, `idempotency_key` (ai_generation_logs) |
| `Version20260205225054` | 05/02/2026 | Table `teacher_certification_requests` |

### Commandes

```bash
# Vérifier le schéma
php bin/console doctrine:schema:validate

# Appliquer les migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Voir l'état
php bin/console doctrine:migrations:status
```

---

## 14. Configuration

### Variables Symfony (`.env`)

| Variable | Valeur | Description |
|----------|--------|-------------|
| `DATABASE_URL` | `mysql://root:@127.0.0.1:3306/studysprint` | Connexion MySQL |
| `APP_SECRET` | `...` | Clé secrète Symfony |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default` | Transport async |
| `AI_GATEWAY_BASE_URL` | `http://localhost:8001` | URL du FastAPI AI Gateway |

### Variables FastAPI (`api/.env`)

| Variable | Valeur | Description |
|----------|--------|-------------|
| `DATABASE_URL` | `mysql+pymysql://root:@127.0.0.1:3306/studysprint` | Connexion MySQL (SQLAlchemy) |
| `JWT_SECRET_KEY` | `studysprint-jwt-secret-key-2026` | Secret JWT |
| `JWT_ALGORITHM` | `HS256` | Algorithme JWT |
| `JWT_ACCESS_TOKEN_EXPIRE_MINUTES` | `60` | TTL access token |
| `JWT_REFRESH_TOKEN_EXPIRE_DAYS` | `7` | TTL refresh token |
| `API_V1_PREFIX` | `/api/v1` | Préfixe API |
| `DEBUG` | `true` | Mode debug |
| `CORS_ORIGINS` | `["http://localhost:8000","http://localhost:3000"]` | CORS |
| `OLLAMA_BASE_URL` | `http://localhost:11434` | URL Ollama |
| `OLLAMA_TIMEOUT` | `120` | Timeout Ollama (secondes) |
| `AI_TEMPERATURE` | `0.7` | Température LLM |
| `AI_MAX_TOKENS` | `4000` | Max tokens par réponse |
| `AI_MAX_RETRIES` | `3` | Retries parsing JSON |

---

## 15. Procédure de démarrage

### Prérequis

- PHP ≥ 8.1 + extensions (ctype, iconv, pdo_mysql)
- Composer
- Python ≥ 3.11
- MySQL 8.x
- Ollama (optionnel, pour les fonctionnalités IA)

### Installation

```bash
# 1. Cloner le projet
git clone <repo> StudySprint && cd StudySprint

# 2. Installer les dépendances PHP
composer install

# 3. Créer la base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction

# 4. Charger les fixtures
php bin/console doctrine:fixtures:load --no-interaction

# 5. Installer les dépendances Python
cd api
pip install -r requirements.txt
cd ..

# 6. (Optionnel) Installer Ollama + modèle
ollama pull vanilj/qwen2.5-14b-instruct-iq4_xs:latest
```

### Démarrage (3 terminaux)

```bash
# Terminal 1 — Symfony
php -S localhost:8000 -t public

# Terminal 2 — FastAPI
cd api && python run.py

# Terminal 3 — Ollama (si IA nécessaire)
ollama serve
```

---

## 16. URLs & Ports

| Service | Port | URL |
|---------|------|-----|
| Symfony (web) | 8000 | http://localhost:8000 |
| FastAPI (API) | 8001 | http://localhost:8001 |
| Swagger UI | 8001 | http://localhost:8001/api/docs |
| ReDoc | 8001 | http://localhost:8001/api/redoc |
| OpenAPI JSON | 8001 | http://localhost:8001/api/openapi.json |
| Health Check | 8001 | http://localhost:8001/health |
| Ollama | 11434 | http://localhost:11434 |
| MySQL | 3306 | — |

### Pages principales

| Page | URL | Rôle requis |
|------|-----|-------------|
| Login | `/login` | — |
| Register | `/register` | — |
| Dashboard FO | `/fo` | ROLE_USER |
| Profil | `/fo/profile` | ROLE_USER |
| Quiz | `/fo/training/quizzes` | ROLE_USER |
| Flashcards | `/fo/training/decks` | ROLE_USER |
| Planning | `/fo/planning` | ROLE_USER |
| Groupes | `/fo/groups` | ROLE_USER |
| Dashboard BO | `/bo` | ROLE_ADMIN |
| Monitoring IA | `/bo/ai-monitoring` | ROLE_ADMIN |
| Certifications | `/bo/certifications` | ROLE_ADMIN |

---

## 17. Dépendances

### PHP (composer.json)

| Package | Version | Usage |
|---------|---------|-------|
| `symfony/framework-bundle` | 6.4.* | Core Symfony |
| `doctrine/orm` | ^3.6 | ORM |
| `doctrine/doctrine-migrations-bundle` | ^3.7 | Migrations |
| `symfony/security-bundle` | 6.4.* | Auth sessions |
| `symfony/form` | 6.4.* | Formulaires |
| `symfony/validator` | 6.4.* | Validation |
| `symfony/twig-bundle` | 6.4.* | Templates |
| `symfony/http-client` | 6.4.* | Client HTTP (AiGatewayService) |
| `lexik/jwt-authentication-bundle` | ^2.18 | JWT |
| `nelmio/cors-bundle` | ^2.6 | CORS |
| `doctrine/doctrine-fixtures-bundle` | ^3.5 | Fixtures (dev) |
| `phpunit/phpunit` | ^10.0 | Tests (dev) |

### Python (requirements.txt)

| Package | Version | Usage |
|---------|---------|-------|
| `fastapi` | 0.115.0 | Framework API |
| `uvicorn[standard]` | 0.32.0 | Serveur ASGI |
| `sqlalchemy` | 2.0.36 | ORM |
| `pymysql` | 1.1.1 | Driver MySQL |
| `python-jose[cryptography]` | 3.3.0 | JWT |
| `passlib[bcrypt]` | 1.7.4 | Password hashing |
| `pydantic` | 2.10.0 | Validation |
| `pydantic-settings` | 2.6.0 | Config |
| `httpx` | 0.28.0 | Client HTTP async |
| `openai` | 1.55.0 | SDK OpenAI (fallback) |
| `anthropic` | 0.39.0 | SDK Anthropic (fallback) |
| `pytest` | 8.3.4 | Tests |

---

## 18. Audits & Corrections appliquées

### Bugs corrigés

| Service | Bug | Correction | Priorité |
|---------|-----|-----------|----------|
| `Sm2SchedulerService` | EaseFactor modifié même quand quality < 3 (violait spec SM-2 officielle) | `$newEf = $ef;` dans le branch quality < 3 (EF préservé) | CRITIQUE |
| `PlanGeneratorService::replacePlan()` | `flush()` intermédiaire cassait l'atomicité (plan vide si exception) | Suppression du `flush()` intermédiaire, transaction atomique | HAUTE |
| `Bo\ChapterController` | `flush()` redondant après `wrapInTransaction()` | Suppression des `flush()` redondants (lignes 106, 136) | MINEURE |

### Refactoring IA

| Avant | Après |
|-------|-------|
| 8 contrôleurs avec `HttpClientInterface` + URL `http://localhost:8001` codée en dur | `AiGatewayService` centralisé, URL configurable via env |
| Pas de persistence dans les endpoints FastAPI `profile/enhance`, `chapter/summarize`, `post/summarize` | Persistence ajoutée : les résultats IA sont écrits en DB dans les entités cibles |
| SQLAlchemy models incomplets (colonnes IA manquantes) | Colonnes `ai_summary`, `ai_key_points`, `ai_tags`, `ai_suggested_*` ajoutées aux models Python |

### Améliorations Monitoring IA (février 2026)

| Composant | Avant | Après | Priorité |
|-----------|-------|-------|----------|
| Sidebar BO | Aucun lien vers Monitoring IA | Section "Intelligence Artificielle" + lien `bo_ai_monitoring_dashboard` | P0 |
| Page logs : compteur | `logs\|length` (taille page, max 50) | `totalLogs` (total filtré exact via COUNT query) | P0 |
| Dashboard : accès échecs | Navigation manuelle requise | Bouton "Échecs uniquement" → `/bo/ai-monitoring/logs?status=failed` | P1 |
| Dashboard : diagnostic | Aucun résumé erreurs | Carte "Top 5 erreurs fréquentes" (GROUP BY errorMessage, excl NULL/vide) | P2 |

### Renforcement tests BO (février 2026)

| Changement | Détail |
|-----------|--------|
| `BoAccessSecurityTest.php` (nouveau) | 13 routes BO × 3 rôles = 39 tests de sécurité |
| `BoAccessControlTest.php` (fixé) | Ajout `requireDatabase()` — skip propre si MySQL down |
| `TeacherCertificationTest.php` (fixé) | Ajout `requireDatabase()` — skip propre si MySQL down |
| Pattern `requireDatabase()` | Tous les tests Controller/Bo utilisent ce helper pour éviter les erreurs bruyantes sans MySQL |
| Total tests BO | 84 tests (26 anon sans DB + 58 DB-dependent) |

### Validation

```bash
# Schéma Doctrine (fonctionne sans MySQL)
php bin/console doctrine:schema:validate --skip-sync
# [OK] The mapping files are correct

# Tests Symfony — BO (84 tests)
php vendor/bin/phpunit tests/Controller/Bo/ --testdox
# MySQL OFF → 26 ✔ + 58 ↩ skipped = 0 erreur
# MySQL ON  → 84 ✔ = 0 erreur, 0 skip

# Tests Symfony — Tous
php vendor/bin/phpunit --testdox

# Tests E2E Playwright (serveur + MySQL requis)
cd e2e && npx playwright test

# Tests Python
cd api && pytest tests/ -v
# ~35 tests (skip si FastAPI/Ollama non démarrés)

# Health check FastAPI
curl http://localhost:8001/health
# {"status":"healthy","version":"1.0.0","database":"connected"}
```

---

> **Document généré le 13 février 2026, mis à jour le 17 février 2026** — StudySprint v1.0
> Pour toute question : consulter `PROJECT_DOCUMENTATION.md`, `API_REST_PLAN_2026.md`, `AUDIT_METIERS_AVANCES.md`, `IMPLEMENTATION_IA_COMPLETE.md`
