# 🔍 AUDIT INTÉGRATION IA - STUDYSPRINT

**Date**: 5 février 2026  
**Objectif**: Implémenter intégration IA complète avec Ollama (Qwen2.5-14B) pour les 5 modules

---

## ✅ DÉJÀ PRÉSENT (Code existant)

### 1. Entités Symfony avec champs IA

| Entité | Champs IA présents | Status |
|--------|-------------------|--------|
| `Quiz` | `generatedByAi`, `aiMeta` | ✅ Complet |
| `FlashcardDeck` | `generatedByAi`, `aiMeta` | ✅ Complet |
| `RevisionPlan` | `generatedByAi`, `aiMeta` | ✅ Complet |
| `AiGenerationLog` | Table complète (feature, input/output, status, latency) | ✅ Existe |
| `AiModel` | Table modèles IA | ✅ Existe |

### 2. FastAPI Router AI (`api/app/routers/ai.py`)

| Endpoint | Méthode | Fonctionnalité | Status |
|----------|---------|----------------|--------|
| `/ai/status` | GET | Check Ollama/OpenAI/Anthropic | ✅ Implémenté |
| `/ai/generate/quiz` | POST | Génération quiz + DB write | ✅ Implémenté |
| `/ai/generate/flashcards` | POST | Génération deck + DB write | ✅ Implémenté |
| `/ai/profile/enhance` | POST | Suggestions profil | ✅ Implémenté |
| `/ai/chapter/summarize` | POST | Résumé chapitre | ✅ Implémenté |
| `/ai/planning/suggest` | POST | Suggestions plan | ✅ Implémenté |
| `/ai/planning/apply` | POST | Apply suggestions (2-step) | ✅ Implémenté |
| `/ai/post/summarize` | POST | Résumé post | ✅ Implémenté |
| `/ai/feedback` | POST | Feedback utilisateur | ✅ Implémenté |
| `/ai/logs/stats` | GET | Stats monitoring BO | ✅ Implémenté |

### 3. Fonctionnalités implémentées

- ✅ Idempotency keys (anti-double-click)
- ✅ JSON validation stricte avec retry
- ✅ Fallback chain (Ollama → OpenAI → Anthropic)
- ✅ Logging exhaustif (latency, status, errors)
- ✅ DB write atomique
- ✅ Parsing JSON robuste (gère markdown code blocks)

---

## ❌ MANQUANT (À implémenter)

### 1. Migrations Doctrine pour champs IA manquants

| Entité | Champs à ajouter | Type |
|--------|-----------------|------|
| `UserProfile` | `ai_suggested_bio`, `ai_suggested_goals`, `ai_suggested_routine` | TEXT NULL |
| `Chapter` | `ai_summary`, `ai_key_points` (JSON), `ai_tags` (JSON) | TEXT + JSON NULL |
| `GroupPost` | `ai_summary`, `ai_category`, `ai_tags` (JSON) | VARCHAR + JSON NULL |
| `AiGenerationLog` | `user_feedback` (rating 1-5), `idempotency_key` | TINYINT + VARCHAR(32) |

### 2. Configuration Ollama

Fichiers à créer/modifier:
- `.env`: Variables `OLLAMA_BASE_URL`, `OLLAMA_MODEL`, `AI_TEMPERATURE`, `AI_MAX_TOKENS`
- `api/app/config.py`: Settings Pydantic pour Ollama

### 3. Controllers Symfony (FO/BO)

#### Module M5 - Quizzes (PRIORITAIRE)
- [ ] `FO: /fo/training/quizzes/ai-generate` - Formulaire génération quiz
- [ ] `FO: POST /fo/training/quizzes/ai-generate` - Appel API FastAPI + redirect
- [ ] Template: Bouton "Générer un quiz (IA)" dans `/fo/training/quizzes/index.html.twig`
- [ ] Template: Formulaire modal/page génération

#### Module M5 - Flashcards (PRIORITAIRE)
- [ ] `FO: /fo/training/decks/ai-generate` - Formulaire génération deck
- [ ] `FO: POST /fo/training/decks/ai-generate` - Appel API + redirect
- [ ] Template: Bouton "Générer un deck (IA)" dans `/fo/training/decks/index.html.twig`

#### Module 2 - Chapitres
- [ ] `BO: /bo/chapters/{id}/ai-summarize` - Action résumé chapitre
- [ ] Template BO: Bouton "Générer résumé & tags (IA)" dans `bo/chapters/show.html.twig`
- [ ] Template FO: Affichage résumé IA si présent

#### Module 3 - Planning
- [ ] `FO: /fo/planning/plans/{id}/ai-suggest` - Get suggestions
- [ ] `FO: /fo/planning/plans/{id}/ai-apply` - Apply suggestions (2-step)
- [ ] Template: Bouton "Suggérer ajustements (IA)" + modal confirmation

#### Module 1 - Profil
- [ ] `FO: /fo/profile/ai-enhance` - Get suggestions profil
- [ ] Template: Bouton "Améliorer mon profil (IA)" + modal affichage suggestions

#### Module 4 - Posts
- [ ] `FO: /fo/groups/{groupId}/posts/{postId}/ai-summarize` - Action résumé
- [ ] Template: Bouton "Résumer (IA)" sur chaque post card

### 4. Monitoring BO Dashboard

- [ ] Controller: `BO: /bo/ai-monitoring` - Dashboard stats
- [ ] Template: `bo/ai_monitoring/dashboard.html.twig`
  - Stats globales (total, success, failed, latency)
  - Graphiques par module
  - Tableau logs récents
  - Feedback utilisateur moyen

### 5. Fixtures

- [ ] `fixtures/AiModelFixtures.php` - Modèle Ollama Qwen2.5-14B
- [ ] Données démo pour tester chaque feature

### 6. Tests

- [ ] Test FastAPI: `test_quiz_generation.py` - Génération + DB write
- [ ] Test E2E: Quiz generate → play → submit → result
- [ ] Test E2E: Deck generate → review SM-2 → grade
- [ ] Test: Planning suggestions + apply

---

## 📋 PLAN D'IMPLÉMENTATION

### Phase 1: Infrastructure (30 min)
1. ✅ Migration Doctrine: champs IA manquants
2. ✅ Config Ollama dans `.env` et `config.py`
3. ✅ Vérifier SQLAlchemy models alignment

### Phase 2: Module M5 - Quizzes (1h)
4. ✅ Controller FO: Quiz AI generation
5. ✅ Templates: Bouton + formulaire
6. ✅ Test flow complet

### Phase 3: Module M5 - Flashcards (45 min)
7. ✅ Controller FO: Deck AI generation
8. ✅ Templates: Bouton + formulaire
9. ✅ Test SM-2 integration

### Phase 4: Modules 1-4 (2h)
10. ✅ Module 2: Chapitre résumé (BO + FO)
11. ✅ Module 3: Planning suggestions 2-step
12. ✅ Module 1: Profil enhancement
13. ✅ Module 4: Post résumé

### Phase 5: Monitoring BO (1h)
14. ✅ Dashboard controller
15. ✅ Template dashboard
16. ✅ Intégration feedback

### Phase 6: Fixtures & Tests (1h)
17. ✅ Fixtures IA
18. ✅ Tests unitaires FastAPI
19. ✅ Tests E2E Symfony

### Phase 7: Validation (30 min)
20. ✅ Migrations apply
21. ✅ Cache clear
22. ✅ Tests validation
23. ✅ Documentation

**Total estimé**: ~6-7 heures

---

## 🎯 CRITÈRES DE SUCCÈS

- [ ] 5 modules ont boutons IA visibles et fonctionnels
- [ ] Quiz generation: formulaire → API → DB → jouable
- [ ] Deck generation: formulaire → API → DB → SM-2 OK
- [ ] Planning: suggest → modal confirmation → apply
- [ ] Monitoring BO: dashboard avec stats + logs + feedback
- [ ] Aucune route existante cassée
- [ ] Tests passent
- [ ] Fixtures chargées

---

**Statut actuel**: Infrastructure analysée, prêt pour implémentation
