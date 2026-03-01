# Module Matières & Chapitres — Documentation Développeur

> **Projet :** StudySprint
> **Stack :** Symfony 6.4 · PHP 8.2 · MariaDB 10.4 · Twig
> **Dernière MàJ :** Mars 2026

---

## 1. À quoi sert ce module ?

Ce module permet aux étudiants de **créer et organiser leurs matières** (ex : Mathématiques, Physique) et de les découper en **chapitres ordonnés** (ex : Chapitre 1 — Intégrales). Les chapitres servent ensuite de base pour générer des **Quiz** et des **Flashcards** (y compris via IA).

Un admin (back-office) peut aussi gérer toutes les matières/chapitres et lancer une **analyse IA** sur un chapitre (résumé, points clés, tags).

---

## 2. Architecture fichiers

```
src/
├── Entity/
│   ├── Subject.php              ← Entité Matière
│   └── Chapter.php              ← Entité Chapitre
├── Repository/
│   ├── SubjectRepository.php    ← Requêtes custom (findAllWithChapters)
│   └── ChapterRepository.php    ← Repository basique
├── Form/
│   ├── Fo/
│   │   ├── SubjectType.php      ← Formulaire FO matière
│   │   └── ChapterType.php      ← Formulaire FO chapitre
│   └── Bo/
│       ├── SubjectType.php      ← Formulaire BO matière
│       └── ChapterType.php      ← Formulaire BO chapitre
├── Controller/
│   ├── Fo/
│   │   └── SubjectsController.php  ← CRUD FO + Wikipedia + Outils linguistiques
│   └── Bo/
│       ├── SubjectController.php   ← CRUD BO matières
│       └── ChapterController.php   ← CRUD BO chapitres + IA + réordonnement
├── Service/
│   ├── AiGatewayService.php     ← Appels vers FastAPI (résumé IA, quiz, flashcards)
│   ├── WikipediaService.php     ← API Wikipedia FR
│   ├── DictionaryService.php    ← API Dictionnaire EN
│   └── TranslationService.php   ← API LibreTranslate

templates/
├── fo/subjects/
│   ├── index.html.twig          ← Liste des matières (grille de cartes)
│   ├── show.html.twig           ← Détail matière + chapitres + Wikipedia + dico/traduction
│   ├── new.html.twig            ← Formulaire création matière
│   ├── edit.html.twig           ← Formulaire édition matière
│   ├── chapter_new.html.twig    ← Formulaire création chapitre
│   └── chapter_edit.html.twig   ← Formulaire édition chapitre
├── bo/subjects/
│   ├── index.html.twig          ← Table paginée + recherche
│   ├── show.html.twig           ← Détail matière
│   ├── new.html.twig            ← Formulaire BO
│   └── edit.html.twig           ← Formulaire BO
└── bo/chapters/
    ├── index.html.twig          ← Table paginée + recherche + boutons ↑↓
    ├── show.html.twig           ← Détail chapitre + bouton IA + carte analyse IA
    ├── new.html.twig
    └── edit.html.twig
```

---

## 3. Base de données

### Table `subjects`

| Colonne       | Type              | Description                     |
|---------------|-------------------|---------------------------------|
| id            | INT (PK, auto)    | Identifiant unique              |
| name          | VARCHAR(120)      | Nom de la matière (obligatoire) |
| code          | VARCHAR(30), UNI  | Code matière (ex: MATH301)      |
| description   | TEXT, nullable     | Description libre               |
| created_by_id | INT (FK → User)   | Qui a créé cette matière        |
| created_at    | DATETIME           | Date de création (auto)         |

### Table `chapters`

| Colonne        | Type              | Description                              |
|----------------|-------------------|------------------------------------------|
| id             | INT (PK, auto)    | Identifiant unique                       |
| subject_id     | INT (FK → Subject) | Matière parente (CASCADE DELETE)         |
| title          | VARCHAR(160)      | Titre du chapitre (obligatoire)          |
| order_no       | INT (default 1)   | Ordre dans la matière                    |
| summary        | TEXT, nullable     | Résumé court                             |
| content        | TEXT, nullable     | Contenu complet                          |
| attachment_url | VARCHAR(255)      | Lien vers fichier PDF/Word uploadé       |
| ai_summary     | TEXT, nullable     | Résumé généré par IA                     |
| ai_key_points  | JSON, nullable     | Points clés extraits par IA (tableau)    |
| ai_tags        | JSON, nullable     | Tags/mots-clés générés par IA (tableau)  |
| created_by_id  | INT (FK → User)   | Qui a créé ce chapitre                   |
| created_at     | DATETIME           | Date de création (auto)                  |

**Contrainte unique :** `(subject_id, order_no)` — pas deux chapitres avec le même numéro d'ordre dans une même matière.

### Relations

```
User ──1:N──▶ Subject ──1:N──▶ Chapter
                │                  │
                ├──1:N──▶ Quiz     ├──1:N──▶ Quiz
                ├──1:N──▶ FlashcardDeck   ├──1:N──▶ FlashcardDeck
                └──1:N──▶ RevisionPlan    └──1:N──▶ RevisionPlan
```

---

## 4. Routes

### Front Office (FO) — Étudiant

| Méthode | URL                                                      | Nom                          | Action                      |
|---------|----------------------------------------------------------|------------------------------|-----------------------------|
| GET     | `/fo/subjects`                                           | `fo_subjects_index`          | Liste des matières          |
| GET/POST| `/fo/subjects/new`                                       | `fo_subjects_new`            | Créer une matière           |
| GET     | `/fo/subjects/{id}`                                      | `fo_subjects_show`           | Voir matière + chapitres    |
| GET/POST| `/fo/subjects/{id}/edit`                                 | `fo_subjects_edit`           | Modifier matière            |
| POST    | `/fo/subjects/{id}/delete`                               | `fo_subjects_delete`         | Supprimer matière           |
| GET/POST| `/fo/subjects/{subjectId}/chapters/new`                  | `fo_subjects_chapter_new`    | Ajouter un chapitre         |
| GET/POST| `/fo/subjects/{subjectId}/chapters/{chapterId}/edit`     | `fo_subjects_chapter_edit`   | Modifier un chapitre        |
| POST    | `/fo/subjects/{subjectId}/chapters/{chapterId}/delete`   | `fo_subjects_chapter_delete` | Supprimer un chapitre       |

### Back Office (BO) — Admin

| Méthode | URL                                | Nom                        | Action                          |
|---------|------------------------------------|----------------------------|---------------------------------|
| GET     | `/bo/subjects`                     | `bo_subjects_index`        | Table paginée des matières      |
| GET/POST| `/bo/subjects/new`                 | `bo_subjects_new`          | Créer                           |
| GET     | `/bo/subjects/{id}`                | `bo_subjects_show`         | Voir détail                     |
| GET/POST| `/bo/subjects/{id}/edit`           | `bo_subjects_edit`         | Modifier                        |
| POST    | `/bo/subjects/{id}`                | `bo_subjects_delete`       | Supprimer                       |
| GET     | `/bo/chapters`                     | `bo_chapters_index`        | Table paginée des chapitres     |
| GET/POST| `/bo/chapters/new`                 | `bo_chapters_new`          | Créer                           |
| GET     | `/bo/chapters/{id}`                | `bo_chapters_show`         | Voir détail + carte IA          |
| GET/POST| `/bo/chapters/{id}/edit`           | `bo_chapters_edit`         | Modifier                        |
| POST    | `/bo/chapters/{id}`                | `bo_chapters_delete`       | Supprimer                       |
| POST    | `/bo/chapters/{id}/up`             | `bo_chapters_up`           | Monter dans l'ordre             |
| POST    | `/bo/chapters/{id}/down`           | `bo_chapters_down`         | Descendre dans l'ordre          |
| POST    | `/bo/chapters/{id}/ai-summarize`   | `bo_chapters_ai_summarize` | Générer résumé/tags par IA      |

---

## 5. Fonctionnalités détaillées

### 5.1 CRUD Matières (FO)

- **Créer** : formulaire avec nom (requis), code (optionnel), description. L'utilisateur connecté est automatiquement mis comme `createdBy`.
- **Voir** : affiche le détail + la liste des chapitres ordonnés + infos Wikipedia + outils linguistiques.
- **Modifier** : accessible uniquement par le propriétaire ou un admin.
- **Supprimer** : protection CSRF, propriétaire ou admin uniquement. Supprime aussi tous les chapitres (CASCADE).

### 5.2 CRUD Chapitres (FO)

- **Créer** : titre, numéro d'ordre, résumé, fichier joint (PDF/Word). Le fichier est uploadé dans `public/uploads/chapters/`.
- **Modifier** : remplace le fichier si un nouveau est fourni.
- **Supprimer** : propriétaire du chapitre, propriétaire de la matière, ou admin.
- **Ordre** : le numéro d'ordre est unique par matière (contrainte BDD).

### 5.3 Réordonnement des chapitres (BO)

Les boutons ↑ et ↓ dans le BO échangent le `orderNo` de deux chapitres adjacents dans une **transaction atomique** :

```php
// moveUp : échange orderNo avec le chapitre précédent
$prev = findOneBy(['subject' => same, 'orderNo' => current - 1]);
$prev->setOrderNo($current);
$chapter->setOrderNo($current - 1);
$em->flush();
```

### 5.4 Upload de fichiers

- **Types acceptés** : `.pdf`, `.doc`, `.docx`
- **Stockage** : `public/uploads/chapters/chap_<uniqid>.<ext>`
- **Accès** : URL relative `/uploads/chapters/...` stockée dans `chapter.attachmentUrl`
- Le fichier est directement accessible via le navigateur (dossier public).

---

## 6. Intégrations IA

### 6.1 Résumé IA d'un chapitre (BO uniquement)

**Bouton** : "Générer résumé & tags (IA)" sur la page show d'un chapitre dans le BO.

**Flux** :
1. Admin clique → POST `/bo/chapters/{id}/ai-summarize`
2. Le controller appelle `AiGatewayService->summarizeChapter(userId, chapterId)`
3. Le service fait un POST HTTP vers `http://localhost:8001/api/v1/ai/summarize-chapter`
4. Le serveur FastAPI (Python + Ollama LLM) analyse le contenu du chapitre
5. Retour : `{ summary, key_points[], tags[] }`
6. Stocké dans l'entité : `chapter.aiSummary`, `chapter.aiKeyPoints`, `chapter.aiTags`

**Affichage** : Carte "Analyse IA" sur la page show avec :
- Résumé en texte
- Points clés (liste à puces avec ✓)
- Tags (badges colorés)

### 6.2 Génération de Quiz par IA

Depuis le module Quiz, l'étudiant peut générer un quiz pour une matière/chapitre donné :

```
AiGatewayService->generateQuiz(userId, subjectId, chapterId, nbQuestions, difficulty, topic)
→ POST http://localhost:8001/api/v1/ai/generate-quiz
→ Retour : { quiz_id, title, questions_count, ... }
```

### 6.3 Génération de Flashcards par IA

Depuis le module Flashcards, l'étudiant peut générer un deck pour une matière/chapitre :

```
AiGatewayService->generateFlashcards(userId, subjectId, chapterId, nbCards, topic, includeHints)
→ POST http://localhost:8001/api/v1/ai/generate-flashcards
→ Retour : { deck_id, title, cards_count, ... }
```

---

## 7. APIs externes intégrées

### 7.1 Wikipedia

- **Service** : `WikipediaService`
- **URL** : `GET https://fr.wikipedia.org/api/rest_v1/page/summary/{nom_matiere}`
- **Utilisé dans** : `SubjectsController->show()` — affiche un encart avec le résumé Wikipedia de la matière, une image et un lien.

### 7.2 Dictionnaire

- **Service** : `DictionaryService`
- **Route interne** : `GET /api/dictionary/{word}`
- **URL externe** : `GET https://api.dictionaryapi.dev/api/v2/entries/en/{word}`
- **Utilisé dans** : Modal JS sur la page show d'une matière — l'étudiant tape un mot et obtient sa définition.

### 7.3 Traduction

- **Service** : `TranslationService`
- **Route interne** : `POST /api/translate` (JSON: `{text, source, target}`)
- **URL externe** : `POST https://libretranslate.com/translate`
- **Utilisé dans** : Modal JS sur la page show — l'étudiant traduit un texte entre plusieurs langues.

---

## 8. Sécurité & Permissions

| Action                    | Qui peut ?                           | Protection       |
|---------------------------|--------------------------------------|-------------------|
| Voir les matières (FO)    | Tout utilisateur connecté            | `ROLE_USER`       |
| Créer une matière (FO)    | Tout utilisateur connecté            | `ROLE_USER`       |
| Modifier une matière (FO) | Propriétaire OU admin                | Code controller   |
| Supprimer une matière (FO)| Propriétaire OU admin                | CSRF + code       |
| Modifier un chapitre (FO) | Tout utilisateur connecté            | `ROLE_USER`       |
| Supprimer un chapitre (FO)| Propriétaire chapitre/matière OU admin | CSRF + code     |
| Tout le BO                | Admin uniquement                     | `ROLE_ADMIN`      |
| Supprimer (BO)            | Admin                                | CSRF              |
| IA Résumé (BO)            | Admin                                | CSRF              |

**Tokens CSRF utilisés** :
- `delete_subject_{id}`
- `delete_chapter_{id}`
- `move_{id}` (réordonnement)
- `ai_summarize{id}` (résumé IA)

---

## 9. Serveurs requis pour le dev

| Service          | Port  | Commande                                                         |
|------------------|-------|------------------------------------------------------------------|
| Symfony          | 8000  | `symfony server:start`                                           |
| FastAPI (IA)     | 8001  | `cd api && uvicorn main:app --port 8001 --reload`                |
| Ollama (LLM)     | 11434 | `ollama serve` (doit tourner en fond)                            |
| MySQL/MariaDB    | 3306  | XAMPP (Apache + MySQL)                                           |

**Variable d'environnement** dans `.env` :
```
AI_GATEWAY_BASE_URL=http://localhost:8001
```

---

## 10. Pour commencer à coder

### Ajouter un champ à Subject

1. Modifier `src/Entity/Subject.php` — ajouter la propriété + getter/setter
2. Créer la migration : `php bin/console make:migration`
3. Exécuter : `php bin/console doctrine:migrations:migrate`
4. Ajouter le champ dans les formulaires (`src/Form/Fo/SubjectType.php` et/ou `Bo/SubjectType.php`)
5. Afficher dans les templates correspondants

### Ajouter une fonctionnalité à un chapitre

1. Si c'est un appel IA → ajouter la méthode dans `AiGatewayService.php` + le endpoint dans `api/main.py`
2. Ajouter la route dans le controller (`SubjectsController.php` FO ou `ChapterController.php` BO)
3. Ajouter le bouton/lien dans le template

### Tester le module

```bash
# Lancer les serveurs
symfony server:start -d
cd api && python -m uvicorn main:app --port 8001 --reload &
ollama serve &

# Accéder
# FO : http://127.0.0.1:8000/fo/subjects
# BO : http://127.0.0.1:8000/bo/subjects
```

---

## 11. Résumé visuel

```
┌─────────────────────────────────────────────────────────┐
│                    MODULE MATIÈRES                       │
│                                                         │
│  FO (Étudiant)              BO (Admin)                  │
│  ┌──────────────┐          ┌──────────────┐             │
│  │ Liste grille │          │ Table paginée│             │
│  │ + recherche  │          │ + recherche  │             │
│  └──────┬───────┘          │ + tri colonnes│            │
│         │                  └──────┬───────┘             │
│         ▼                         ▼                     │
│  ┌──────────────┐          ┌──────────────┐             │
│  │ Détail + Wiki│          │ Détail       │             │
│  │ + Dico/Trad  │          └──────────────┘             │
│  └──────┬───────┘                                       │
│         │                                               │
│         ▼                                               │
│  ┌──────────────────────────────────────────┐           │
│  │            CHAPITRES                      │           │
│  │  FO : CRUD + upload PDF                   │           │
│  │  BO : CRUD + réordonnement ↑↓ + IA       │           │
│  │       └→ Résumé IA (summary, key_points,  │          │
│  │          tags) via FastAPI + Ollama LLM    │          │
│  └───────────────┬──────────────────────────┘           │
│                  │                                       │
│    ┌─────────────┼─────────────┐                        │
│    ▼             ▼             ▼                        │
│  Quiz IA    Flashcards IA   Planning                    │
│  (module)   (module)        (module)                    │
└─────────────────────────────────────────────────────────┘
```
