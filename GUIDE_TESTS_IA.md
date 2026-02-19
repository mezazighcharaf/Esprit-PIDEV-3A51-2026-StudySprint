# 🧪 GUIDE DE TESTS - IMPLÉMENTATION IA

Ce guide vous permet de valider complètement l'implémentation IA de StudySprint.

---

## 📋 PRÉ-REQUIS

### Services en cours d'exécution

```bash
# Terminal 1: Ollama
ollama serve
# Vérifier: curl http://localhost:11434/api/tags

# Terminal 2: FastAPI
cd c:\Users\charaf\Desktop\StudySprint\api
python -m uvicorn app.main:app --reload --port 8001
# Vérifier: curl http://localhost:8001/api/v1/ai/status

# Terminal 3: Symfony
cd c:\Users\charaf\Desktop\StudySprint
symfony server:start --port=8000
# OU: php -S localhost:8000 -t public
```

### Base de données
```bash
# Vérifier que la migration est appliquée
php bin/console doctrine:migrations:status

# Si nécessaire
php bin/console doctrine:migrations:migrate
```

---

## 🎯 NIVEAU 1: TESTS RAPIDES (5 MIN)

### A. Test FastAPI Status
```bash
curl http://localhost:8001/api/v1/ai/status
```

**Résultat attendu**:
```json
{
  "status": "ok",
  "providers": {
    "ollama": {
      "available": true,
      "model": "qwen2.5:14b"
    }
  }
}
```

### B. Test Symfony Homepage
```bash
# Ouvrir navigateur
http://localhost:8000
```

**Résultat attendu**: Page d'accueil sans erreur 500

### C. Test Monitoring Dashboard
```bash
# Ouvrir navigateur
http://localhost:8000/bo/ai-monitoring
```

**Résultat attendu**: Dashboard avec métriques (peut être à zéro si aucune génération)

---

## 🧪 NIVEAU 2: TESTS AUTOMATISÉS (10 MIN)

### A. Tests Python (Endpoints FastAPI)

```bash
# Installer pytest si nécessaire
pip install pytest requests

# Lancer les tests
cd c:\Users\charaf\Desktop\StudySprint
python api\tests\test_ai_endpoints.py
```

**Résultat attendu**: Tous les tests passent (10/10)

### B. Tests cURL (Exemples)

#### Quiz Generation
```bash
curl -X POST http://localhost:8001/api/v1/ai/generate/quiz \
  -H "Content-Type: application/json" \
  -d '{
    "subject_id": 1,
    "chapter_id": 1,
    "num_questions": 3,
    "difficulty": "MEDIUM",
    "topic": "Python basics"
  }'
```

#### Flashcard Generation
```bash
curl -X POST http://localhost:8001/api/v1/ai/generate/flashcards \
  -H "Content-Type: application/json" \
  -d '{
    "subject_id": 1,
    "chapter_id": 1,
    "num_cards": 5,
    "topic": "Data structures",
    "include_hints": true
  }'
```

#### Chapter Summary
```bash
curl -X POST http://localhost:8001/api/v1/ai/chapter/summarize \
  -H "Content-Type: application/json" \
  -d '{"chapter_id": 1}'
```

---

## 🎭 NIVEAU 3: TESTS MANUELS E2E (30 MIN)

### MODULE 5: QUIZ & FLASHCARDS (PRIORITAIRE)

#### Test 1: Génération Quiz Complète
1. **Naviguer**: http://localhost:8000/fo/training/quizzes
2. **Cliquer**: "Générer un quiz (IA)" (bouton gradient violet)
3. **Remplir formulaire**:
   - Matière: Sélectionner une matière
   - Chapitre: Sélectionner un chapitre
   - Nombre de questions: 5
   - Difficulté: MEDIUM
   - Sujet (optionnel): "Python variables"
4. **Soumettre** et **attendre 30-60 secondes**
5. **Vérifier redirection**: Page index avec nouveau quiz

**Validation**:
- ✅ Quiz apparaît dans la liste
- ✅ Titre contient le sujet
- ✅ Badge difficulté correct (MEDIUM)
- ✅ Bouton "Commencer" visible

#### Test 2: Jouer au Quiz Généré
1. **Cliquer**: "Commencer" sur le quiz généré
2. **Jouer**: Répondre aux 5 questions
3. **Soumettre** les réponses
4. **Vérifier résultat**: Score affiché

**Validation**:
- ✅ 5 questions affichées
- ✅ 4 choix par question
- ✅ Score calculé correctement
- ✅ Redirection vers page résultats
- ✅ Quiz dans historique

#### Test 3: Génération Flashcards Complète
1. **Naviguer**: http://localhost:8000/fo/training/decks
2. **Cliquer**: "Générer un deck (IA)"
3. **Remplir formulaire**:
   - Matière: Sélectionner
   - Chapitre: Sélectionner
   - Nombre de cartes: 10
   - Inclure indices: ✓
4. **Soumettre** et **attendre 40-90 secondes**

**Validation**:
- ✅ Deck apparaît dans la liste
- ✅ "10 cartes" affiché
- ✅ Bouton "Réviser" visible

#### Test 4: Réviser Flashcards
1. **Cliquer**: "Réviser" sur le deck
2. **Voir question** (front)
3. **Cliquer**: "Révéler réponse" (back affiché)
4. **Noter difficulté**: EASY/MEDIUM/HARD
5. **Répéter** pour 3-4 cartes

**Validation**:
- ✅ Front/back affichés correctement
- ✅ Indices visibles si option activée
- ✅ État SM-2 mis à jour (next_review_at)
- ✅ Message de fin après dernière carte

---

### MODULE 2: CHAPITRES

#### Test 5: Résumé Chapitre (BO)
1. **Naviguer**: http://localhost:8000/bo/chapters (admin)
2. **Sélectionner** un chapitre avec contenu
3. **Cliquer**: "Générer résumé & tags (IA)"
4. **Attendre 20-30 secondes**

**Validation**:
- ✅ Flash message "Résumé IA généré avec succès"
- ✅ Card "Analyse IA" affichée avec:
  - Résumé (paragraphe)
  - 5 points clés (liste)
  - 5 tags (badges bleus)

**Vérifier en DB**:
```sql
SELECT ai_summary, ai_key_points, ai_tags 
FROM chapters 
WHERE id = [ID];
```

---

### MODULE 3: PLANNING

#### Test 6: Suggestions Planning (2-step)
1. **Naviguer**: http://localhost:8000/fo/planning
2. **Créer** un plan avec 5-10 tâches (ou utiliser existant)
3. **Ouvrir détails** du plan
4. **Cliquer**: "Suggérer ajustements (IA)"
5. **Attendre 30-40 secondes**
6. **Page confirmation** affichée

**Validation page confirm**:
- ✅ Explication globale affichée
- ✅ Liste des suggestions (move/reschedule/delete...)
- ✅ Badges colorés par action
- ✅ Warning avant application
- ✅ Boutons "Appliquer" et "Annuler"

#### Test 7: Application Suggestions
1. **Sur page confirmation**, cliquer "Appliquer les suggestions"
2. **Attendre 5-10 secondes**
3. **Redirection** vers détails plan

**Validation**:
- ✅ Flash message "X modifications effectuées"
- ✅ Tâches modifiées dans la liste
- ✅ Dates/priorités mises à jour

**Vérifier en DB**:
```sql
SELECT * FROM plan_tasks WHERE plan_id = [ID] ORDER BY start_at;
```

---

### MODULE 1: PROFIL

#### Test 8: Amélioration Profil
1. **Naviguer**: http://localhost:8000/fo/profile
2. **Cliquer**: "Améliorer mon profil (IA)"
3. **Attendre 20-30 secondes**

**Validation**:
- ✅ Flash message "Suggestions IA générées"
- ✅ Card "Suggestions IA" affichée avec:
  - Bio suggérée (paragraphe)
  - Objectifs suggérés (liste)
  - Routine d'étude suggérée (planning)
- ✅ Note explicative en bas

**Vérifier champs manuels intacts**:
```sql
SELECT bio, ai_suggested_bio 
FROM user_profiles 
WHERE user_id = [ID];
-- bio doit rester inchangé, ai_suggested_bio rempli
```

---

### MODULE 4: POSTS GROUPES

#### Test 9: Résumé Post
1. **Naviguer**: http://localhost:8000/fo/groups
2. **Entrer** dans un groupe avec posts
3. **Trouver** un post long (>100 mots)
4. **Cliquer**: "Résumer (IA)" sous le post
5. **Attendre 15-25 secondes**

**Validation**:
- ✅ Bouton disparaît
- ✅ Card gradient affichée avec:
  - Icône éclair + "Résumé IA"
  - Badge catégorie (question/discussion/ressource/annonce)
  - Résumé court (2-3 phrases)
  - Tags (3-5 badges bleus)

---

### MONITORING BO

#### Test 10: Dashboard Monitoring
1. **Naviguer**: http://localhost:8000/bo/ai-monitoring
2. **Observer métriques**:
   - Total requêtes (devrait être > 0 après tests précédents)
   - Succès / Échecs
   - Taux d'échec
   - Latence moyenne
3. **Cliquer**: "Voir tous les logs"

**Validation**:
- ✅ Stats mises à jour en temps réel
- ✅ Stats par module affichées
- ✅ 20 logs récents visibles

#### Test 11: Logs Détaillés
1. **Sur page logs**, filtrer par module "quiz"
2. **Cliquer 👁️** sur un log
3. **Modal détails** affichée

**Validation**:
- ✅ Prompt complet affiché
- ✅ Input JSON formaté
- ✅ Output JSON formaté
- ✅ Fermeture modal fonctionne

---

## 🔍 NIVEAU 4: TESTS DE ROBUSTESSE (20 MIN)

### Test 12: Timeout & Retry
```bash
# Test avec grand nombre de questions (devrait timeout ou prendre >60s)
curl -X POST http://localhost:8001/api/v1/ai/generate/quiz \
  -H "Content-Type: application/json" \
  -d '{
    "subject_id": 1,
    "chapter_id": 1,
    "num_questions": 20,
    "difficulty": "HARD",
    "topic": "Advanced algorithms"
  }'
```

**Validation**:
- ✅ Réponse en <120s OU erreur timeout propre
- ✅ Log créé avec status "failed" si timeout

### Test 13: JSON Invalide (Retry)
Le système doit retry jusqu'à 3 fois si l'IA génère du JSON invalide.

**Validation manuelle**:
- Générer quiz/deck et observer logs FastAPI
- Si parsing JSON échoue, vérifier 2-3 retry attempts
- Vérifier `ai_generation_logs.metadata` contient retry count

### Test 14: Idempotency
```bash
# Même requête 2 fois de suite
curl -X POST http://localhost:8001/api/v1/ai/generate/quiz \
  -H "Content-Type: application/json" \
  -d '{
    "subject_id": 99,
    "chapter_id": 99,
    "num_questions": 2,
    "difficulty": "EASY",
    "topic": "Test idempotency"
  }'

# Répéter exactement la même commande
curl -X POST http://localhost:8001/api/v1/ai/generate/quiz \
  -H "Content-Type: application/json" \
  -d '{
    "subject_id": 99,
    "chapter_id": 99,
    "num_questions": 2,
    "difficulty": "EASY",
    "topic": "Test idempotency"
  }'
```

**Validation**:
- ✅ 2ème requête retourne même quiz_id OU erreur explicite
- ✅ Aucun crash / 500 error

### Test 15: CSRF Protection
```bash
# Tester POST Symfony sans token CSRF (doit échouer)
curl -X POST http://localhost:8000/fo/training/quizzes/ai-generate \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "subject_id=1&num_questions=3"
```

**Validation**:
- ✅ Erreur "Token CSRF invalide" OU redirection avec flash error
- ✅ Aucun quiz créé en DB

---

## 📊 NIVEAU 5: TESTS DATABASE (15 MIN)

### Vérifications SQL

#### Check 1: Migrations appliquées
```sql
SELECT version FROM doctrine_migration_versions 
WHERE version LIKE '%20260205185349%';
-- Doit retourner 1 ligne
```

#### Check 2: Champs IA présents
```sql
DESCRIBE user_profiles;
-- Doit contenir: ai_suggested_bio, ai_suggested_goals, ai_suggested_routine

DESCRIBE chapters;
-- Doit contenir: ai_summary, ai_key_points, ai_tags

DESCRIBE group_posts;
-- Doit contenir: ai_summary, ai_category, ai_tags

DESCRIBE ai_generation_logs;
-- Doit contenir: user_feedback, idempotency_key
```

#### Check 3: Logs générés
```sql
SELECT feature, status, COUNT(*) as count
FROM ai_generation_logs
GROUP BY feature, status;
-- Doit afficher quiz, flashcard, profile, etc.
```

#### Check 4: Entités avec IA data
```sql
-- Quizzes générés par IA
SELECT id, title, generated_by_ai 
FROM quizzes 
WHERE generated_by_ai = 1 
LIMIT 5;

-- Decks générés par IA
SELECT id, title, generated_by_ai 
FROM flashcard_decks 
WHERE generated_by_ai = 1 
LIMIT 5;

-- Chapitres avec résumé IA
SELECT id, title, LENGTH(ai_summary) as summary_length
FROM chapters 
WHERE ai_summary IS NOT NULL 
LIMIT 5;

-- Posts avec résumé IA
SELECT id, ai_category, ai_tags
FROM group_posts 
WHERE ai_summary IS NOT NULL 
LIMIT 5;
```

---

## ✅ CHECKLIST VALIDATION COMPLÈTE

### Infrastructure
- [ ] Ollama répond sur port 11434
- [ ] FastAPI répond sur port 8001
- [ ] Symfony répond sur port 8000
- [ ] MySQL accessible
- [ ] Migration appliquée (Version20260205185349)
- [ ] Cache Symfony cleared

### Endpoints FastAPI (10/10)
- [ ] GET /ai/status → 200 OK
- [ ] POST /ai/generate/quiz → 200 OK + quiz créé
- [ ] POST /ai/generate/flashcards → 200 OK + deck créé
- [ ] POST /ai/chapter/summarize → 200 OK + DB updated
- [ ] POST /ai/profile/enhance → 200 OK + DB updated
- [ ] POST /ai/planning/suggest → 200 OK + session stored
- [ ] POST /ai/planning/apply → 200 OK + tasks updated
- [ ] POST /ai/post/summarize → 200 OK + DB updated
- [ ] POST /ai/feedback → 200 OK + log updated
- [ ] GET /ai/logs/stats → 200 OK + stats returned

### Boutons UI (8 boutons)
- [ ] Quiz: "Générer un quiz (IA)" visible
- [ ] Flashcard: "Générer un deck (IA)" visible
- [ ] Chapitre BO: "Générer résumé & tags (IA)" visible
- [ ] Planning: "Suggérer ajustements (IA)" visible
- [ ] Profil: "Améliorer mon profil (IA)" visible
- [ ] Post: "Résumer (IA)" visible (sous chaque post)
- [ ] Monitoring BO: "Voir tous les logs" accessible
- [ ] Tous les boutons ont style gradient violet

### Flows E2E (7 flows)
- [ ] Quiz: Génération → Affichage → Jouer → Résultat → Historique
- [ ] Flashcard: Génération → Affichage → Révision → SM-2 update
- [ ] Chapitre: Bouton BO → API → Affichage résumé FO
- [ ] Planning: Suggest → Confirm → Apply → Tasks updated
- [ ] Profil: Bouton → API → Affichage suggestions (champs manuels intacts)
- [ ] Post: Bouton → API → Affichage résumé inline
- [ ] Monitoring: Dashboard → Logs → Détails modal

### Sécurité
- [ ] Tous les POST Symfony ont CSRF token
- [ ] POST sans CSRF échoue proprement
- [ ] Idempotency empêche doublons
- [ ] Timeouts configurés (30-120s)
- [ ] Erreurs gérées sans crash

### Database
- [ ] Logs créés dans `ai_generation_logs`
- [ ] Latency enregistrée (latency_ms)
- [ ] Status correct (success/failed/pending)
- [ ] Input/output JSON valide
- [ ] Idempotency key unique par génération
- [ ] Quizzes/decks référencent user_id
- [ ] Chapitres ont ai_summary rempli
- [ ] Posts ont ai_category + ai_tags

### Performance
- [ ] Quiz 5Q: 30-60s
- [ ] Deck 10 cards: 40-90s
- [ ] Chapter summary: 20-30s
- [ ] Planning suggest: 30-40s
- [ ] Profile enhance: 20-30s
- [ ] Post summary: 15-25s
- [ ] Taux de succès > 90%

---

## 🚨 PROBLÈMES COURANTS & SOLUTIONS

### Problème 1: "Impossible de contacter le service IA"
**Solution**:
```bash
# Vérifier Ollama
curl http://localhost:11434/api/tags

# Redémarrer si nécessaire
pkill ollama
ollama serve

# Vérifier FastAPI
curl http://localhost:8001/api/v1/ai/status
```

### Problème 2: Quiz/Deck généré mais non jouable
**Cause**: `is_published = false` par défaut (volontaire)

**Solution**:
```sql
UPDATE quizzes SET is_published = 1 WHERE id = [ID];
-- OU publier via BO
```

### Problème 3: Timeout après 120s
**Cause**: Modèle trop lent ou trop de questions

**Solutions**:
- Réduire `num_questions` / `num_cards`
- Utiliser modèle plus petit: `ollama pull qwen2.5:7b`
- Augmenter timeout dans `api/app/config.py`

### Problème 4: JSON parsing failed
**Normal**: Le système retry automatiquement (jusqu'à 3x)

**Si persiste**:
- Vérifier logs FastAPI pour voir prompt exact
- Ajuster prompt dans `api/app/services/ai_service.py`

### Problème 5: Monitoring affiche 0 requêtes
**Cause**: Aucune génération IA effectuée encore

**Solution**: Lancer au moins 1 génération (quiz/deck/summary) avant

---

## 📸 CAPTURES D'ÉCRAN ATTENDUES

### Quiz Generation Form
![Quiz Form](https://via.placeholder.com/800x400?text=Formulaire+Generation+Quiz)
- Selects matière/chapitre
- Input nombre questions
- Radio difficulté
- Bouton gradient "Générer"

### Planning Confirmation Modal
![Planning Confirm](https://via.placeholder.com/800x400?text=Modal+Confirmation+Planning)
- Explication globale
- Liste suggestions avec badges
- Warning avant application
- Boutons Appliquer/Annuler

### Monitoring Dashboard
![Monitoring](https://via.placeholder.com/800x400?text=Dashboard+Monitoring+IA)
- 6 cards métriques
- Table stats par module
- Logs récents

---

## 🎯 CRITÈRES DE SUCCÈS FINAUX

**L'implémentation IA est validée SI**:

✅ **Tous** les endpoints FastAPI répondent 200 OK  
✅ **Tous** les boutons UI sont visibles et cliquables  
✅ **Au moins 3** flows E2E fonctionnent bout-en-bout  
✅ **Monitoring** affiche stats avec au moins 5 logs  
✅ **Database** contient entités IA (quiz/deck/summary)  
✅ **Aucun** crash 500 lors des générations  
✅ **CSRF** protection active sur tous les POST  
✅ **Latences** < 120s pour toutes les générations  

---

## 📞 AIDE & SUPPORT

Si un test échoue systématiquement:

1. **Consulter logs FastAPI** (terminal uvicorn)
2. **Consulter logs Symfony** (`var/log/dev.log`)
3. **Vérifier DB** (queries SQL ci-dessus)
4. **Tester endpoint isolé** (cURL)
5. **Redémarrer services** (Ollama, FastAPI, Symfony)

---

**Temps total estimé**: 1h30 (tous niveaux confondus)  
**Temps recommandé**: 45 min (niveaux 1-3 seulement)
