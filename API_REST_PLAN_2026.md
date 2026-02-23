# 📡 API REST STUDYSPRINT - IMPLÉMENTATION FASTAPI

**Date**: 5 février 2026
**Version**: 1.0.0
**Stack**: FastAPI (Python 3.14) + Symfony 6.4 (Web)
**Architecture**: Hybride (Symfony Web + FastAPI REST + FastAPI IA Gateway)

---

## 🏗️ ARCHITECTURE HYBRIDE IMPLÉMENTÉE

```
┌─────────────────────────────────────────────────────────────┐
│                      FRONTEND                                │
│              (Templates Twig / Future React)                 │
└─────────────────────────┬───────────────────────────────────┘
                          │
         ┌────────────────┼────────────────┐
         │                │                │
         ▼                ▼                ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│  Symfony 6.4    │ │   FastAPI       │ │   FastAPI       │
│  Port: 8000     │ │   Port: 8001    │ │   (IA Gateway)  │
│                 │ │                 │ │                 │
│  /bo/*          │ │  /api/v1/*      │ │  /api/v1/ai/*   │
│  /fo/*          │ │  REST CRUD      │ │  Quiz Gen       │
│  /admin/*       │ │  JWT Auth       │ │  Flashcard Gen  │
│  SSR + Forms    │ │  Swagger Docs   │ │  OpenAI/Claude  │
└────────┬────────┘ └────────┬────────┘ └────────┬────────┘
         │                   │                   │
         └───────────────────┴───────────────────┘
                             │
                    ┌────────▼────────┐
                    │   MySQL/MariaDB │
                    │   studysprint   │
                    │   (Shared DB)   │
                    └─────────────────┘
```

---

## 📁 STRUCTURE DU PROJET API

```
api/
├── .env                     # Configuration locale
├── .env.example             # Template configuration
├── requirements.txt         # Dépendances Python (26 packages)
├── run.py                   # Script démarrage dev server
│
└── app/
    ├── __init__.py
    ├── main.py              # Application FastAPI principale
    ├── config.py            # Configuration (pydantic-settings)
    ├── database.py          # SQLAlchemy connection (même DB Symfony)
    ├── dependencies.py      # Auth dependencies (JWT, RBAC)
    │
    ├── models/              # SQLAlchemy ORM Models (17 entités)
    │   ├── __init__.py
    │   ├── user.py          # User, UserProfile
    │   ├── subject.py       # Subject, Chapter
    │   ├── planning.py      # RevisionPlan, PlanTask
    │   ├── group.py         # StudyGroup, GroupMember, GroupPost
    │   ├── training.py      # Quiz, QuizAttempt, QuizAttemptAnswer
    │   └── flashcard.py     # FlashcardDeck, Flashcard, FlashcardReviewState
    │
    ├── schemas/             # Pydantic DTOs (Request/Response)
    │   ├── __init__.py
    │   ├── common.py        # PaginatedResponse, ErrorResponse
    │   ├── auth.py          # LoginRequest, Token, TokenData
    │   ├── user.py          # UserCreate, UserResponse, etc.
    │   ├── subject.py       # SubjectCreate, ChapterResponse, etc.
    │   ├── planning.py      # PlanCreate, TaskResponse, etc.
    │   ├── group.py         # GroupCreate, PostResponse, etc.
    │   ├── training.py      # QuizResponse, QuizSubmit, etc.
    │   └── flashcard.py     # DeckResponse, ReviewGrade, etc.
    │
    ├── services/            # Business Logic Services
    │   ├── __init__.py
    │   ├── auth.py          # JWT + Password hashing (bcrypt)
    │   ├── sm2_scheduler.py # SM-2 Algorithm (port Symfony)
    │   ├── quiz_scoring.py  # Quiz Scoring (port Symfony)
    │   └── plan_generator.py # Plan Generation (port Symfony)
    │
    └── routers/             # API Endpoints
        ├── __init__.py
        ├── auth.py          # /api/v1/auth/*
        ├── users.py         # /api/v1/users/*
        ├── subjects.py      # /api/v1/subjects/*, /api/v1/chapters/*
        ├── planning.py      # /api/v1/planning/*
        ├── groups.py        # /api/v1/groups/*, /api/v1/posts/*
        ├── training.py      # /api/v1/training/quizzes/*
        ├── flashcards.py    # /api/v1/training/decks/*, /api/v1/training/review/*
        └── ai.py            # /api/v1/ai/*
```

---

## 🔐 CONFIGURATION

### Fichier .env (api/.env)

```env
# Database (même DB que Symfony)
DATABASE_URL=mysql+pymysql://root:@127.0.0.1:3306/studysprint

# JWT Configuration
JWT_SECRET_KEY=studysprint-jwt-secret-key-2026
JWT_ALGORITHM=HS256
JWT_ACCESS_TOKEN_EXPIRE_MINUTES=60
JWT_REFRESH_TOKEN_EXPIRE_DAYS=7

# API Configuration
API_V1_PREFIX=/api/v1
DEBUG=true
ENVIRONMENT=development

# CORS (Symfony + Frontend)
CORS_ORIGINS=["http://localhost:8000","http://localhost:3000","http://localhost:8001"]

# AI Gateway (optionnel)
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
AI_MODEL_DEFAULT=gpt-4o-mini
AI_TEMPERATURE=0.7
AI_MAX_TOKENS=2000

# Rate Limiting
RATE_LIMIT_PER_MINUTE=60
RATE_LIMIT_AUTH_PER_MINUTE=5
```

---

## 📡 ENDPOINTS IMPLÉMENTÉS (70 routes)

### Module 0: Authentication (`/api/v1/auth`)

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| POST | `/auth/login` | Connexion → JWT tokens | ❌ |
| POST | `/auth/refresh` | Rafraîchir access token | JWT |
| POST | `/auth/logout` | Déconnexion | JWT |
| GET | `/auth/me` | Info utilisateur courant | JWT |

**Exemple Login:**
```bash
curl -X POST http://localhost:8001/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"alice.martin@studysprint.local","password":"user123"}'
```

**Response:**
```json
{
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "token_type": "bearer",
  "expires_in": 3600,
  "user": {
    "id": 48,
    "email": "alice.martin@studysprint.local",
    "full_name": "Alice Martin",
    "roles": ["ROLE_USER"]
  }
}
```

---

### Module 1: Users (`/api/v1/users`)

| Méthode | Endpoint | Description | Auth | RBAC |
|---------|----------|-------------|------|------|
| GET | `/users` | Liste paginée | JWT | ADMIN |
| GET | `/users/{id}` | Détails user | JWT | ADMIN/SELF |
| POST | `/users` | Créer user | JWT | ADMIN |
| PUT | `/users/{id}` | Modifier user | JWT | ADMIN/SELF |
| DELETE | `/users/{id}` | Supprimer user | JWT | ADMIN |
| GET | `/users/{id}/profile` | Profil user | JWT | USER |
| PUT | `/users/{id}/profile` | Modifier profil | JWT | SELF |

**Paramètres de recherche:**
- `?page=1&limit=10` - Pagination
- `?q=alice` - Recherche nom/email
- `?user_type=STUDENT` - Filtrer par type

---

### Module 2: Subjects & Chapters (`/api/v1/subjects`, `/api/v1/chapters`)

| Méthode | Endpoint | Description | Auth | RBAC |
|---------|----------|-------------|------|------|
| GET | `/subjects` | Liste matières | JWT | USER |
| GET | `/subjects/{id}` | Détails + chapitres | JWT | USER |
| POST | `/subjects` | Créer matière | JWT | TEACHER/ADMIN |
| PUT | `/subjects/{id}` | Modifier matière | JWT | OWNER/ADMIN |
| DELETE | `/subjects/{id}` | Supprimer matière | JWT | OWNER/ADMIN |
| GET | `/subjects/{id}/chapters` | Chapitres triés | JWT | USER |
| POST | `/subjects/{id}/chapters` | Créer chapitre | JWT | OWNER/ADMIN |
| PUT | `/chapters/{id}` | Modifier chapitre | JWT | OWNER/ADMIN |
| DELETE | `/chapters/{id}` | Supprimer chapitre | JWT | OWNER/ADMIN |
| POST | `/chapters/{id}/reorder` | Réordonner | JWT | OWNER/ADMIN |

**Paramètres de recherche:**
- `?q=math` - Recherche nom/code
- `?sort=name&dir=asc` - Tri

---

### Module 3: Planning (`/api/v1/planning`)

| Méthode | Endpoint | Description | Auth | RBAC |
|---------|----------|-------------|------|------|
| GET | `/planning/plans` | Mes plans | JWT | USER |
| GET | `/planning/plans/{id}` | Détails plan + tâches | JWT | OWNER |
| POST | `/planning/plans` | Créer plan manuel | JWT | USER |
| POST | `/planning/plans/generate` | Générer plan auto | JWT | USER |
| PUT | `/planning/plans/{id}` | Modifier plan | JWT | OWNER |
| DELETE | `/planning/plans/{id}` | Supprimer plan | JWT | OWNER |
| GET | `/planning/tasks` | Mes tâches (filtrées) | JWT | USER |
| POST | `/planning/tasks` | Créer tâche | JWT | USER |
| PUT | `/planning/tasks/{id}` | Modifier tâche | JWT | OWNER |
| PATCH | `/planning/tasks/{id}/status` | Changer statut | JWT | OWNER |
| DELETE | `/planning/tasks/{id}` | Supprimer tâche | JWT | OWNER |

**Génération automatique:**
```bash
curl -X POST http://localhost:8001/api/v1/planning/plans/generate \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "subject_id": 1,
    "start_date": "2026-02-10",
    "end_date": "2026-02-28",
    "sessions_per_day": 2,
    "skip_weekends": true,
    "replace_existing": false
  }'
```

**Détection d'overlap:** Retourne `409 Conflict` si plan existant chevauche les dates.

---

### Module 4: Groups & Posts (`/api/v1/groups`, `/api/v1/posts`)

| Méthode | Endpoint | Description | Auth | RBAC |
|---------|----------|-------------|------|------|
| GET | `/groups` | Groupes publics + mes groupes | JWT | USER |
| GET | `/groups/{id}` | Détails groupe | JWT | MEMBER/PUBLIC |
| POST | `/groups` | Créer groupe | JWT | USER |
| PUT | `/groups/{id}` | Modifier groupe | JWT | OWNER/ADMIN |
| DELETE | `/groups/{id}` | Supprimer groupe | JWT | OWNER |
| GET | `/groups/{id}/posts` | Feed paginé DESC | JWT | MEMBER |
| POST | `/groups/{id}/posts` | Créer post | JWT | MEMBER |
| POST | `/posts/{id}/comments` | Ajouter commentaire | JWT | MEMBER |
| DELETE | `/posts/{id}` | Supprimer post | JWT | AUTHOR/MOD |
| POST | `/groups/{id}/join` | Rejoindre groupe | JWT | USER |
| POST | `/groups/{id}/leave` | Quitter groupe | JWT | MEMBER |

**Paramètres feed:**
- `?page=1&limit=10` - Pagination
- `?my_groups=true` - Seulement mes groupes

---

### Module 5A: Quizzes (`/api/v1/training/quizzes`)

| Méthode | Endpoint | Description | Auth | RBAC |
|---------|----------|-------------|------|------|
| GET | `/training/quizzes` | Liste quiz publiés | JWT | USER |
| GET | `/training/quizzes/history` | Historique tentatives | JWT | USER |
| GET | `/training/quizzes/{id}` | Détails quiz | JWT | USER |
| POST | `/training/quizzes/{id}/start` | Démarrer tentative | JWT | USER |
| POST | `/training/quizzes/{id}/attempts/{aid}/submit` | Soumettre réponses | JWT | OWNER |
| GET | `/training/quizzes/{id}/attempts/{aid}/result` | Résultat détaillé | JWT | OWNER |
| GET | `/training/quizzes/{id}/stats` | Stats quiz | JWT | TEACHER/ADMIN |

**Flow Quiz:**
1. `POST /start` → Retourne questions (sans réponses correctes)
2. User répond dans l'UI
3. `POST /submit` → Scoring avec `QuizScoringService`
4. `GET /result` → Détails avec corrections

**Formats de questions supportés:**
- `choices[].is_correct: true/false`
- `question.correct_index: N`
- `question.correct_key: "key"`

---

### Module 5B: Flashcards SM-2 (`/api/v1/training/decks`, `/api/v1/training/review`)

| Méthode | Endpoint | Description | Auth | RBAC |
|---------|----------|-------------|------|------|
| GET | `/training/decks` | Liste decks publiés | JWT | USER |
| GET | `/training/decks/{id}` | Détails deck + cards | JWT | USER |
| GET | `/training/decks/{id}/review` | Cartes à réviser | JWT | USER |
| POST | `/training/review/{state_id}/grade` | Noter carte SM-2 | JWT | OWNER |
| GET | `/training/decks/{id}/stats` | Stats révision | JWT | USER |

**Algorithme SM-2 implémenté:**
```
Quality ratings:
- 0 (Again): Reset à 0 reps, interval=1, EF inchangé
- 3 (Hard): EF baisse, interval calculé
- 4 (Good): EF stable, interval * EF
- 5 (Easy): EF augmente, interval * EF

EF minimum: 1.3
EF default: 2.5
```

**Exemple grade:**
```bash
curl -X POST http://localhost:8001/api/v1/training/review/23/grade \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"quality": "good"}'
```

**Response:**
```json
{
  "state_id": 23,
  "new_repetitions": 3,
  "new_ease_factor": 2.5,
  "new_interval_days": 15,
  "next_due_at": "2026-02-20",
  "estimated_next_reviews": {
    "again": "2026-02-06",
    "hard": "2026-02-07",
    "good": "2026-02-20",
    "easy": "2026-03-05"
  }
}
```

---

### Module 6: AI Gateway (`/api/v1/ai`)

| Méthode | Endpoint | Description | Auth | RBAC |
|---------|----------|-------------|------|------|
| GET | `/ai/status` | État service IA | JWT | USER |
| POST | `/ai/generate/quiz` | Générer questions | JWT | TEACHER/ADMIN |
| POST | `/ai/generate/flashcards` | Générer flashcards | JWT | TEACHER/ADMIN |

**Providers supportés:**
- OpenAI (GPT-4o-mini, GPT-4)
- Anthropic (Claude 3 Haiku, Sonnet)

**Exemple génération quiz:**
```bash
curl -X POST http://localhost:8001/api/v1/ai/generate/quiz \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "subject_id": 1,
    "chapter_id": 1,
    "num_questions": 5,
    "difficulty": "MEDIUM",
    "topic": "Intégrales définies"
  }'
```

---

## 🔒 SÉCURITÉ IMPLÉMENTÉE

### JWT Authentication
- **Algorithme**: HS256
- **TTL Access Token**: 60 minutes
- **TTL Refresh Token**: 7 jours
- **Password Hashing**: bcrypt (compatible Symfony)

### RBAC (Role-Based Access Control)
```python
# dependencies.py
async def get_admin_user(current_user: User = Depends(get_current_user)) -> User:
    if not current_user.is_admin():
        raise HTTPException(403, "Admin access required")
    return current_user

async def get_teacher_or_admin(current_user: User = Depends(get_current_user)) -> User:
    if current_user.user_type not in ["TEACHER", "ADMIN"] and not current_user.is_admin():
        raise HTTPException(403, "Teacher or admin access required")
    return current_user
```

### BOLA Protection (Broken Object Level Authorization)
```python
def require_owner_or_admin(resource_user_id: int, current_user: User):
    if not (current_user.id == resource_user_id or current_user.is_admin()):
        raise HTTPException(403, "You don't have permission")
```

### CORS Configuration
```python
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8000", "http://localhost:3000"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)
```

---

## 📦 DÉPENDANCES PYTHON

```txt
# Core FastAPI
fastapi==0.115.0
uvicorn[standard]==0.32.0
python-multipart==0.0.12

# Database (même MySQL que Symfony)
sqlalchemy==2.0.36
pymysql==1.1.1

# Authentication
python-jose[cryptography]==3.3.0
passlib[bcrypt]==1.7.4
bcrypt==4.2.1

# Validation
pydantic==2.10.0
pydantic-settings==2.6.0
email-validator==2.2.0

# AI Gateway
openai==1.55.0
anthropic==0.39.0

# Utils
python-dotenv==1.0.1
httpx==0.28.0
```

---

## 🚀 DÉMARRAGE

### Terminal 1 - Symfony (Web)
```bash
cd StudySprint
php -S localhost:8000 -t public
```

### Terminal 2 - FastAPI (API)
```bash
cd StudySprint/api
python run.py
```

### URLs d'accès
| Service | URL |
|---------|-----|
| Symfony Web | http://localhost:8000 |
| FastAPI API | http://localhost:8001 |
| Swagger Docs | http://localhost:8001/api/docs |
| ReDoc | http://localhost:8001/api/redoc |
| OpenAPI JSON | http://localhost:8001/api/openapi.json |
| Health Check | http://localhost:8001/health |

---

## 🧪 TESTS API

### Test avec curl

```bash
# 1. Login
TOKEN=$(curl -s -X POST http://localhost:8001/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"alice.martin@studysprint.local","password":"user123"}' \
  | jq -r '.access_token')

# 2. Get current user
curl -H "Authorization: Bearer $TOKEN" http://localhost:8001/api/v1/auth/me

# 3. List subjects
curl -H "Authorization: Bearer $TOKEN" http://localhost:8001/api/v1/subjects

# 4. Start quiz
curl -X POST -H "Authorization: Bearer $TOKEN" \
  http://localhost:8001/api/v1/training/quizzes/1/start

# 5. Get flashcards to review
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8001/api/v1/training/decks/1/review
```

### Test avec Python
```python
import httpx

BASE = "http://localhost:8001/api/v1"

# Login
resp = httpx.post(f"{BASE}/auth/login", json={
    "email": "alice.martin@studysprint.local",
    "password": "user123"
})
token = resp.json()["access_token"]
headers = {"Authorization": f"Bearer {token}"}

# Get subjects
subjects = httpx.get(f"{BASE}/subjects", headers=headers).json()
print(subjects)
```

---

## 📊 SERVICES MÉTIER (Ports Symfony → Python)

### SM2SchedulerService
**Symfony**: `src/Service/Sm2SchedulerService.php`
**FastAPI**: `api/app/services/sm2_scheduler.py`

Fonctions portées:
- `apply_review(state, quality)` - Algorithme SM-2 complet
- `create_initial_state(user, flashcard)` - État initial
- `button_to_quality(button)` - again/hard/good/easy → 0/3/4/5
- `get_next_review_dates(state)` - Prédiction dates
- `calculate_retention(state)` - Estimation rétention

### QuizScoringService
**Symfony**: `src/Service/QuizScoringService.php`
**FastAPI**: `api/app/services/quiz_scoring.py`

Fonctions portées:
- `score_attempt(attempt, answers, quiz)` - Scoring complet
- `get_missing_answers(quiz, answers)` - Questions sans réponse
- `get_detailed_results(attempt, quiz, answers)` - Résultats détaillés

### PlanGeneratorService
**Symfony**: `src/Service/PlanGeneratorService.php`
**FastAPI**: `api/app/services/plan_generator.py`

Fonctions portées:
- `find_overlapping_plan(db, user, subject, dates)` - Détection overlap
- `generate_plan(db, user, subject, dates, options)` - Génération auto
- `replace_plan(db, plan, dates, options)` - Remplacement

---

## ✅ VALIDATION

### Schéma validé
```bash
php bin/console doctrine:schema:validate
# [OK] The mapping files are correct
```

### Tests Symfony (Services)
```bash
php vendor/bin/phpunit tests/Service/
# Sm2SchedulerServiceTest: 12 tests ✓
# QuizScoringServiceTest: 11 tests ✓
```

### Health Check FastAPI
```bash
curl http://localhost:8001/health
# {"status":"healthy","version":"1.0.0","database":"connected"}
```

---

## 📝 AUDIT MÉTIERS AVANCÉS

Voir `AUDIT_METIERS_AVANCES.md` pour l'audit complet des services métier.

**Résumé des fixes appliqués:**

| Service | Bug | Fix | Status |
|---------|-----|-----|--------|
| Sm2SchedulerService | EF modifié même si quality < 3 | EF conservé si échec | ✅ Appliqué |
| PlanGeneratorService | flush() intermédiaire | Supprimé pour atomicité | ✅ Appliqué |
| ChapterController | flush() redondant après wrapInTransaction | Supprimé | ✅ Appliqué |

---

**Document mis à jour le**: 5 février 2026
**Version API**: 1.0.0
**Auteur**: Tech Lead StudySprint
