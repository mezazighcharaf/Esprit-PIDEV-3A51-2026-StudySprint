# Module Planning — Guide Développeur

## Vue d'ensemble

Le module Planning permet à un étudiant de créer et gérer des plans de révision avec des tâches planifiées sur un calendrier. Il intègre une génération automatique de plan et une optimisation par IA.

---

## Fichiers à connaître

### Contrôleur
- `src/Controller/Fo/PlanningController.php` — tout le module est ici

### Entités
- `src/Entity/RevisionPlan.php` — le plan de révision
- `src/Entity/PlanTask.php` — une tâche dans un plan

### Repositories
- `src/Repository/RevisionPlanRepository.php`
- `src/Repository/PlanTaskRepository.php`

### Formulaires
- `src/Form/Fo/RevisionPlanType.php` — créer/modifier un plan
- `src/Form/Fo/PlanTaskType.php` — créer/modifier une tâche
- `src/Form/Fo/GeneratePlanType.php` — générer un plan automatiquement

### Services
- `src/Service/PlanGeneratorService.php` — génération automatique du plan
- `src/Service/AiGatewayService.php` — appels IA (suggestions, apply)

### Templates
- `templates/fo/planning/index.html.twig` — calendrier principal
- `templates/fo/planning/show.html.twig` — détail d'un plan
- `templates/fo/planning/plan_new.html.twig` — formulaire création plan
- `templates/fo/planning/plan_edit.html.twig` — formulaire modification plan
- `templates/fo/planning/task_new.html.twig` — formulaire création tâche
- `templates/fo/planning/task_edit.html.twig` — formulaire modification tâche
- `templates/fo/planning/session_new.html.twig` — créer une session manuelle
- `templates/fo/planning/generate.html.twig` — assistant génération automatique
- `templates/fo/planning/ai_confirm.html.twig` — confirmer suggestions IA

---

## Entité RevisionPlan

**Table :** `revision_plans`

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| id | int | auto | Clé primaire |
| user | User | oui | Propriétaire du plan |
| subject | Subject | oui | Matière concernée |
| chapter | Chapter | non | Chapitre optionnel |
| title | string(255) | oui | Titre du plan |
| startDate | DateTimeImmutable | oui | Date de début |
| endDate | DateTimeImmutable | oui | Date de fin |
| status | string(50) | oui | DRAFT / ACTIVE / DONE |
| generatedByAi | bool | non | Généré par IA ? |
| aiMeta | array JSON | non | Métadonnées IA |
| createdAt | DateTimeImmutable | auto | Date de création |
| tasks | Collection\<PlanTask\> | - | Tâches du plan |

**Constantes :**
```php
RevisionPlan::STATUS_DRAFT   // 'DRAFT'
RevisionPlan::STATUS_ACTIVE  // 'ACTIVE'
RevisionPlan::STATUS_DONE    // 'DONE'
```

---

## Entité PlanTask

**Table :** `plan_tasks`

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| id | int | auto | Clé primaire |
| plan | RevisionPlan | oui | Plan parent (CASCADE DELETE) |
| title | string(255) | oui | Titre de la tâche |
| taskType | string(50) | oui | REVISION / QUIZ / FLASHCARD / CUSTOM |
| startAt | DateTimeImmutable | oui | Début de la session |
| endAt | DateTimeImmutable | oui | Fin de la session |
| status | string(50) | oui | TODO / DOING / DONE |
| priority | int (1-3) | oui | 1=Basse 2=Moyenne 3=Haute |
| notes | text | non | Notes libres |
| createdAt | DateTimeImmutable | auto | Date de création |

**Constantes :**
```php
PlanTask::TYPE_REVISION   // 'REVISION'
PlanTask::TYPE_QUIZ       // 'QUIZ'
PlanTask::TYPE_FLASHCARD  // 'FLASHCARD'
PlanTask::TYPE_CUSTOM     // 'CUSTOM'

PlanTask::STATUS_TODO     // 'TODO'
PlanTask::STATUS_DOING    // 'DOING'
PlanTask::STATUS_DONE     // 'DONE'
```

---

## Routes

Toutes les routes sont préfixées `/fo/planning` avec le nom `fo_planning_`

| Route | URL | Méthode | Description |
|-------|-----|---------|-------------|
| `fo_planning_index` | `/fo/planning` | GET | Calendrier + stats |
| `fo_planning_events_json` | `/fo/planning/events.json` | GET | JSON pour FullCalendar |
| `fo_planning_generate` | `/fo/planning/generate` | GET/POST | Génération auto |
| `fo_planning_show` | `/fo/planning/{id}` | GET | Détail d'un plan |
| `fo_planning_plan_new` | `/fo/planning/plans/new` | GET/POST | Créer un plan |
| `fo_planning_plan_edit` | `/fo/planning/plans/{id}/edit` | GET/POST | Modifier un plan |
| `fo_planning_plan_delete` | `/fo/planning/plans/{id}/delete` | POST | Supprimer un plan |
| `fo_planning_session_new` | `/fo/planning/sessions/new` | GET/POST | Session manuelle |
| `fo_planning_task_new` | `/fo/planning/plans/{planId}/tasks/new` | GET/POST | Créer une tâche |
| `fo_planning_task_edit` | `/fo/planning/plans/{planId}/tasks/{taskId}/edit` | GET/POST | Modifier une tâche |
| `fo_planning_task_delete` | `/fo/planning/plans/{planId}/tasks/{taskId}/delete` | POST | Supprimer une tâche |
| `fo_planning_task_toggle` | `/fo/planning/tasks/{taskId}/toggle` | POST | Basculer TODO↔DONE |
| `fo_planning_ai_suggest` | `/fo/planning/{id}/ai-suggest` | POST | Obtenir suggestions IA |
| `fo_planning_ai_confirm` | `/fo/planning/{id}/ai-confirm` | GET | Confirmer suggestions IA |
| `fo_planning_ai_apply` | `/fo/planning/{id}/ai-apply` | POST | Appliquer suggestions IA |

---

## Service PlanGeneratorService

Utilisé par `fo_planning_generate` pour créer automatiquement un plan.

```php
// Vérifier si un plan existe déjà
$existing = $planGenerator->findOverlappingPlan($user, $subject, $start, $end);

// Générer un nouveau plan
$plan = $planGenerator->generatePlan(
    user: $user,
    subject: $subject,
    startDate: $start,
    endDate: $end,
    sessionsPerDay: 2,    // 1 à 4
    skipWeekends: false
);

// Remplacer un plan existant
$plan = $planGenerator->replacePlan($existing, $start, $end, 2, false);
```

La génération distribue les tâches (REVISION, QUIZ, FLASHCARD) par chapitre sur les jours disponibles. Heures par défaut : 9h, 14h, 17h, 19h.

---

## Service AiGatewayService (partie Planning)

Appelle le FastAPI Gateway sur `http://localhost:8001`.

```php
// Obtenir des suggestions IA pour un plan
$result = $aiGateway->suggestPlanOptimizations(
    userId: $user->getId(),
    planId: $plan->getId(),
    optimizationGoals: 'Maximiser la mémorisation'
);
// Retourne: { suggestions: [], explanation: string, ai_log_id: int, can_apply: bool }

// Appliquer les suggestions
$result = $aiGateway->applyPlanSuggestions(
    userId: $user->getId(),
    suggestionLogId: $aiLogId
);
// Retourne: { message: string, applied_count: int, total_suggestions: int }
```

**Important :** le FastAPI gateway doit tourner sur `http://localhost:8001`. Sans lui, les fonctions IA retournent une erreur gracieuse.

---

## Endpoint AJAX calendrier

`GET /fo/planning/events.json` retourne le format FullCalendar :

```json
[
  {
    "id": 1,
    "title": "Réviser Suites et séries",
    "start": "2026-02-09T09:00:00",
    "end": "2026-02-09T11:00:00",
    "color": "#667eea",
    "extendedProps": {
      "status": "TODO",
      "priority": 2,
      "type": "REVISION"
    }
  }
]
```

Couleurs par statut :
- TODO → `#667eea` (bleu)
- DOING → `#f59e0b` (orange)
- DONE → `#10b981` (vert)

---

## Données de test (Fixtures)

Deux plans sont créés dans `src/DataFixtures/AppFixtures.php` :

- **Plan Maths — Examen final** (alice, MATH101, 01/02 → 28/02/2026, ACTIVE)
  - 5 tâches : révisions suites, intégrales, EDO, postulats MQ, opérateurs
- **Révisions Physique Quantique** (bob, PHYS201, 10/02 → 28/02/2026, ACTIVE)
  - 2 tâches : postulats MQ, opérateurs

Comptes de test :
- `alice.martin@studysprint.local` / `user123`
- `bob.dupont@studysprint.local` / `user123`

---

## Points d'attention

- Les statuts dans les **fixtures** utilisent les minuscules (`todo`, `done`, `in_progress`) mais les constantes de l'entité utilisent les MAJUSCULES (`TODO`, `DONE`, `DOING`). Vérifier la cohérence.
- Le toggle de tâche bascule entre `TODO` et `DONE` uniquement.
- Les suggestions IA sont stockées en **session PHP** temporairement avec la clé `ai_suggestions_{planId}`.
- La protection CSRF est activée sur toutes les routes POST.
