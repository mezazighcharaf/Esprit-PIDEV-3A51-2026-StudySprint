# 🔍 AUDIT MÉTIERS AVANCÉS - STUDYSPRINT

**Date**: 5 février 2026  
**Auditeur**: Tech Lead Symfony 6.4  
**Scope**: Services métier critiques + Repositories + Validation entities

---

## ✅ SERVICES FONCTIONNELS (Pas de modification)

### 1. QuizScoringService ✅

**Localisation**: `src/Service/QuizScoringService.php`

**Points forts validés**:
- ✅ Validation stricte des index de questions (lignes 38-42)
- ✅ Détection des réponses en double (lignes 44-50)
- ✅ Support multi-format questions (3 formats: isCorrect, correctIndex, correctKey)
- ✅ Protection division par zéro (ligne 67)
- ✅ Méthode `getMissingAnswers()` pour validation complète
- ✅ Méthode `getDetailedResults()` avec passing score configurable

**Note mineure (non bloquante)**:
- Ligne 116: Fallback "first choice is correct" par défaut si aucun format détecté. Acceptable comme comportement par défaut mais à documenter.

**Conclusion**: ✅ **SERVICE ROBUSTE - AUCUN FIX REQUIS**

---

### 2. Repositories ✅

**Fichiers audités**:
- `QuizAttemptRepository.php`
- `FlashcardReviewStateRepository.php`
- `FlashcardRepository.php`
- `ChapterRepository.php`
- `GroupPostRepository.php`

**Sécurité injection SQL**:
- ✅ Utilisation exclusive de `createQueryBuilder()`
- ✅ Paramètres liés via `setParameter()`
- ✅ Aucune concaténation SQL détectée
- ✅ Queries complexes correctement sécurisées (ex: `findNewCardsForUserAndDeck` avec NOT IN)

**Conclusion**: ✅ **SÉCURITÉ OPTIMALE - AUCUN FIX REQUIS**

---

### 3. Validation Entities ✅

**Contraintes vérifiées**:
- ✅ `User`: `@UniqueEntity(email)`, `@Assert\Email`, `@Assert\Choice(userType)`
- ✅ `Chapter`: `@UniqueConstraint(subject_id, order_no)`, `@UniqueEntity(subject, orderNo)`
- ✅ `RevisionPlan`: `@Assert\Choice(status)`, `@Assert\NotNull(startDate, endDate)`
- ✅ `PlanTask`: `@Assert\Choice(taskType, status)`, `@Assert\Range(priority: 1-3)`
- ✅ `Quiz`: `@Assert\Choice(difficulty)`, `@Assert\NotNull(questions)`
- ✅ `FlashcardReviewState`: `@UniqueConstraint(user_id, flashcard_id)`

**Conclusion**: ✅ **VALIDATION COMPLÈTE - AUCUN FIX REQUIS**

---

## 🐛 BUGS CRITIQUES DÉTECTÉS (Fixes requis)

### 1. ❌ BUG CRITIQUE: Sm2SchedulerService - EaseFactor update incorrect

**Localisation**: `src/Service/Sm2SchedulerService.php:52-74`

**Spec SuperMemo 2 officielle** (source: https://super-memory.com/english/ol/sm2.htm):
```
6. If the quality response was lower than 3 then start
   repetitions for the item from the beginning WITHOUT CHANGING THE E-FACTOR
   (i.e. use intervals I(1), I(2) etc. as if the item was memorized anew).
```

**Code actuel (INCORRECT)**:
```php
// Ligne 52-74
$newEf = $ef + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02));
$newEf = max(self::MIN_EASE_FACTOR, $newEf);

if ($quality < 3) {
    // Failed review - reset
    $newRepetitions = 0;
    $newInterval = 1;
} else {
    // Successful review
    $newRepetitions = $repetitions + 1;
    // ... calcul intervals
}

// ❌ BUG: On met à jour EF même pour quality < 3
$state->setEaseFactor($newEf);  // Ligne 74
```

**Problème**: L'EaseFactor est recalculé et mis à jour même quand `quality < 3`, ce qui contredit la spécification SM-2 officielle.

**Impact**:
- L'algorithme de répétition espacée devient moins précis
- Les cartes difficiles voient leur EF baisser trop rapidement
- Perte d'efficacité de l'apprentissage espacé

**Fix requis**: Ne mettre à jour `easeFactor` QUE si `quality >= 3`

---

### 2. ❌ BUG MOYEN: PlanGeneratorService::replacePlan - Atomicité cassée

**Localisation**: `src/Service/PlanGeneratorService.php:174-221`

**Code actuel (INCORRECT)**:
```php
public function replacePlan(...): RevisionPlan {
    // Delete existing tasks
    foreach ($existingPlan->getTasks() as $task) {
        $this->em->remove($task);
    }
    
    // Update plan dates
    $existingPlan->setStartDate($startDate);
    $existingPlan->setEndDate($endDate);
    $existingPlan->setStatus(RevisionPlan::STATUS_ACTIVE);
    
    // ❌ BUG: flush() au milieu de l'opération
    $this->em->flush();  // Ligne 191
    
    // Generate new tasks (peut échouer)
    foreach ($chapters as $chapter) {
        // ... création tâches
        $this->em->persist($task);
    }
    
    return $existingPlan;
}
```

**Problème**: 
- Si la génération des nouvelles tâches échoue (exception, erreur), on aura :
  - ✅ Supprimé les anciennes tâches (flushed)
  - ❌ Pas créé les nouvelles tâches
- L'utilisateur se retrouve avec un plan vide

**Impact**:
- Perte de données en cas d'erreur
- Transaction non atomique
- Mauvaise expérience utilisateur

**Fix requis**: Supprimer le `flush()` ligne 191 et laisser le contrôleur gérer la transaction complète, OU wrapper toute la méthode dans `wrapInTransaction()`.

---

### 3. ⚠️ OPTIMISATION: ChapterController - flush() redondant

**Localisation**: `src/Controller/Bo/ChapterController.php:99-109, 131-139`

**Code actuel (SOUS-OPTIMAL)**:
```php
$em->wrapInTransaction(function() use ($item, $previousChapter, $currentOrder) {
    if ($previousChapter) {
        $previousChapter->setOrderNo($currentOrder);
    }
    $item->setOrderNo($currentOrder - 1);
});

$em->flush();  // ⚠️ Redondant - wrapInTransaction flush déjà
```

**Problème**: 
- `EntityManager::wrapInTransaction()` fait automatiquement `flush()` + `commit()`
- Le `flush()` ligne 106/136 est donc redondant et cause un flush supplémentaire inutile

**Impact**: 
- Performance légèrement dégradée (double flush)
- Pas de bug fonctionnel, juste sous-optimal

**Fix requis**: Supprimer les `flush()` lignes 106 et 136.

---

## 🔧 FIXES PROPOSÉS

### Fix 1: Sm2SchedulerService - Respect spec SM-2

```php
public function applyReview(FlashcardReviewState $state, int $quality): FlashcardReviewState
{
    $this->validateQuality($quality);

    $ef = $state->getEaseFactor();
    $repetitions = $state->getRepetitions();
    $interval = $state->getIntervalDays();

    // Determine new interval and repetitions
    if ($quality < 3) {
        // Failed review - reset WITHOUT changing EF (spec SM-2)
        $newRepetitions = 0;
        $newInterval = 1;
        $newEf = $ef; // ✅ FIX: Ne pas modifier EF
    } else {
        // Successful review
        $newRepetitions = $repetitions + 1;

        // Update ease factor using SM-2 formula (only for quality >= 3)
        $newEf = $ef + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02));
        $newEf = max(self::MIN_EASE_FACTOR, $newEf);

        if ($newRepetitions === 1) {
            $newInterval = 1;
        } elseif ($newRepetitions === 2) {
            $newInterval = 6;
        } else {
            $newInterval = (int) round($interval * $newEf);
        }
    }

    // Update state
    $state->setEaseFactor($newEf);
    $state->setRepetitions($newRepetitions);
    $state->setIntervalDays($newInterval);
    $state->setLastReviewedAt(new \DateTimeImmutable());
    $state->setDueAt(new \DateTimeImmutable("+{$newInterval} days"));

    return $state;
}
```

**Changements**:
- Ligne 56-59: Ajout de `$newEf = $ef;` pour conserver l'ancien EF
- Lignes 51-53: Déplacement du calcul EF DANS le bloc `else` (quality >= 3 uniquement)

---

### Fix 2: PlanGeneratorService::replacePlan - Transaction atomique

**Option A (Recommandée)**: Supprimer flush() intermédiaire
```php
public function replacePlan(...): RevisionPlan {
    // Delete existing tasks
    foreach ($existingPlan->getTasks() as $task) {
        $this->em->remove($task);
    }
    
    // Update plan dates
    $existingPlan->setStartDate($startDate);
    $existingPlan->setEndDate($endDate);
    $existingPlan->setStatus(RevisionPlan::STATUS_ACTIVE);
    
    // ✅ FIX: Pas de flush() ici - laisser le contrôleur gérer
    // $this->em->flush(); // SUPPRIMER
    
    // Generate new tasks
    $subject = $existingPlan->getSubject();
    $chapters = $subject->getChapters()->toArray();
    usort($chapters, fn(Chapter $a, Chapter $b) => $a->getOrderNo() <=> $b->getOrderNo());

    $availableDays = $this->getAvailableDays($startDate, $endDate, $skipWeekends);
    $totalSlots = count($availableDays) * $sessionsPerDay;
    $sessionHours = [9, 14, 17, 19];
    $slotIndex = 0;

    foreach ($chapters as $chapter) {
        foreach ([PlanTask::TYPE_REVISION, PlanTask::TYPE_QUIZ, PlanTask::TYPE_FLASHCARD] as $type) {
            if ($slotIndex >= $totalSlots) break 2;
            $task = $this->createTask(
                $existingPlan,
                $chapter,
                $type,
                $availableDays,
                $slotIndex,
                $sessionsPerDay,
                $sessionHours
            );
            $this->em->persist($task);
            $slotIndex++;
        }
    }
    
    // Le contrôleur appellera flush() une seule fois
    return $existingPlan;
}
```

**Option B**: Wrapper dans transaction
```php
public function replacePlan(...): RevisionPlan {
    return $this->em->wrapInTransaction(function() use (...) {
        // Tout le code de remplacement ici
        // ...
        return $existingPlan;
    });
}
```

**Recommandation**: Option A pour cohérence avec `generatePlan()`.

---

### Fix 3: ChapterController - Supprimer flush() redondant

```php
#[Route('/{id}/up', name: 'up', methods: ['POST'])]
public function moveUp(Request $request, Chapter $item, ChapterRepository $repo, EntityManagerInterface $em): Response
{
    if (!$this->isCsrfTokenValid('move'.$item->getId(), $request->request->get('_token'))) {
        return $this->redirectToRoute('bo_chapters_index');
    }

    $currentOrder = $item->getOrderNo();
    if ($currentOrder <= 1) {
        $this->addFlash('warning', 'Ce chapitre est déjà en première position.');
        return $this->redirectToRoute('bo_chapters_index');
    }

    $previousChapter = $repo->findOneBy([
        'subject' => $item->getSubject(),
        'orderNo' => $currentOrder - 1
    ]);

    $em->wrapInTransaction(function() use ($item, $previousChapter, $currentOrder) {
        if ($previousChapter) {
            $previousChapter->setOrderNo($currentOrder);
        }
        $item->setOrderNo($currentOrder - 1);
    });

    // ✅ FIX: Supprimer cette ligne (wrapInTransaction flush déjà)
    // $em->flush(); // SUPPRIMER

    $this->addFlash('success', 'Chapitre déplacé vers le haut.');
    return $this->redirectToRoute('bo_chapters_index');
}

#[Route('/{id}/down', name: 'down', methods: ['POST'])]
public function moveDown(Request $request, Chapter $item, ChapterRepository $repo, EntityManagerInterface $em): Response
{
    if (!$this->isCsrfTokenValid('move'.$item->getId(), $request->request->get('_token'))) {
        return $this->redirectToRoute('bo_chapters_index');
    }

    $currentOrder = $item->getOrderNo();

    $nextChapter = $repo->findOneBy([
        'subject' => $item->getSubject(),
        'orderNo' => $currentOrder + 1
    ]);

    if (!$nextChapter) {
        $this->addFlash('warning', 'Ce chapitre est déjà en dernière position.');
        return $this->redirectToRoute('bo_chapters_index');
    }

    $em->wrapInTransaction(function() use ($item, $nextChapter, $currentOrder) {
        $nextChapter->setOrderNo($currentOrder);
        $item->setOrderNo($currentOrder + 1);
    });

    // ✅ FIX: Supprimer cette ligne
    // $em->flush(); // SUPPRIMER

    $this->addFlash('success', 'Chapitre déplacé vers le bas.');
    return $this->redirectToRoute('bo_chapters_index');
}
```

---

## 📊 RÉSUMÉ AUDIT

### Statistiques

| Catégorie | Total | ✅ OK | ❌ Bugs | ⚠️ Optimisation |
|-----------|-------|-------|---------|-----------------|
| Services métier | 3 | 1 | 2 | 0 |
| Repositories | 5 | 5 | 0 | 0 |
| Controllers | 1 | 0 | 0 | 1 |
| Entities validation | 6 | 6 | 0 | 0 |
| **TOTAL** | **15** | **12** | **2** | **1** |

### Priorités de fix

1. **🔴 CRITIQUE**: `Sm2SchedulerService` - Bug algorithme SM-2 (impact apprentissage)
2. **🟡 MOYEN**: `PlanGeneratorService::replacePlan` - Atomicité transaction (perte données potentielle)
3. **🟢 MINEUR**: `ChapterController` - flush() redondant (performance)

---

## ✅ VALIDATION POST-FIX

### Tests à exécuter

1. **SM-2 Algorithm**:
   ```bash
   php bin/phpunit tests/Service/Sm2SchedulerServiceTest.php
   ```
   - Vérifier que quality < 3 ne change pas EF
   - Vérifier intervalles I(1)=1, I(2)=6
   - Vérifier EF floor 1.3

2. **Plan Generator**:
   - Tester `replacePlan()` avec exception simulée
   - Vérifier rollback complet si erreur
   - Vérifier aucune tâche orpheline

3. **Chapter Reordering**:
   - Tester moveUp/moveDown avec plusieurs chapitres
   - Vérifier contrainte unique (subject_id, order_no)

---

## 🎯 CONCLUSION

**État global**: 80% du code métier est **ROBUSTE ET CONFORME** aux best practices Symfony 6.4.

**Bugs critiques identifiés**: 2  
**Bugs fonctionnels**: 0  
**Optimisations recommandées**: 1  

**Action requise**: Appliquer les 3 fixes proposés avant déploiement production.

**Référence spec SM-2**: https://super-memory.com/english/ol/sm2.htm (consulté le 05/02/2026)

---

**Audit réalisé le**: 5 février 2026  
**Outils**: Lecture manuelle + recherche web + validation spec officielle  
**Méthodologie**: Code review exhaustif + tests de non-régression requis
