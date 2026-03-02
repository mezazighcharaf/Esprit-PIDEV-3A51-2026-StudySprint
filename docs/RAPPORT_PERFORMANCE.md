# Rapport de Performance et Qualite

Projet: StudySprint  
Date: 2026-03-02

## 1) PHPStan

### Avant
- Resultat initial observe: 160 erreurs (atelier), puis 118 restantes durant correction.

### Apres
- Commande:
```bash
vendor/bin/phpstan analyse --no-progress
```
- Resultat final: `OK - No errors` (niveau configure dans `phpstan.neon`).

Preuve capture:
- Capture terminal "No errors" (a coller dans le Word).

## 2) PHPUnit

### Suite complete
- Commande:
```bash
vendor/bin/phpunit
```
- Resultat actuel:
  - 151 tests
  - 232 assertions
  - 0 erreur / 0 echec
  - 58 tests ignores (skipped)

### Correctif applique
- `tests/Entity/EntityValidationTest.php` adapte au modele User abstrait:
  - `User` remplace par `Student` concret.
  - anciens champs (`userType`, `fullName` comme propriete, `password`) adaptes au modele actuel (`role`, `nom/prenom`, `setPassword`).

Preuve capture:
- Capture terminal PHPUnit "OK, but some tests were skipped!".

## 3) Doctrine Doctor (Profiler)

### Constat observe (captures landing)
- Performance metrics:
  - Total execution: ~921 ms
  - Symfony init: ~97 ms
  - Peak memory: ~44 MiB
- Doctrine queries landing: 0 (normal).
- Doctrine Doctor:
  - Total issues: 73 (3 critical, 9 warnings, 61 info)
  - Profiler overhead: ~730 ms

### Analyse
- Le gros cout vient de l'outil Doctrine Doctor dans le profiler (overhead), pas de la page landing elle-meme.
- Les corrections code (security/integrity) ont ete appliquees; la partie configuration DB reste principalement environnement (MariaDB strict mode, timezone, buffer pool).

Preuve capture:
- Capture profiler onglet Performance.
- Capture profiler onglet Doctrine.
- Capture profiler onglet Doctrine Doctor.

## 4) Performance globale (page Landing + Groups)

### Mesure propre conseillee (pour jury)
1. Mesurer avec profiler actif pour comparatif pedagogique.
2. Mentionner explicitement que Doctor ajoute un overhead fort.
3. Completer avec une mesure "ressenti utilisateur" hors Doctor (refresh normal).

Tableau a coller dans Word:

| Indicateur | Avant | Apres | Preuve |
|---|---:|---:|---|
| Landing - execution time | 921 ms | (mesure finale) | Capture Performance |
| Landing - memoire pic | 44 MiB | (mesure finale) | Capture Performance |
| Landing - SQL queries | 0 | 0 | Capture Doctrine |
| Doctrine Doctor overhead | 730 ms | (mesure finale) | Capture Doctrine Doctor |
| Groups - execution time | (capture avant) | (capture apres) | Capture Performance |

## 5) Commandes de validation jury

```bash
# 1) Migrations
php bin/console doctrine:migrations:migrate --no-interaction

# 2) Validation schema (DB doit etre demarree)
php bin/console doctrine:schema:validate

# 3) Lints
php bin/console lint:container
php bin/console lint:twig templates/bo templates/layouts templates/fo

# 4) Statique
vendor/bin/phpstan analyse --no-progress

# 5) Tests
vendor/bin/phpunit
```

## 6) Seed DB (fixtures)

```bash
# ATTENTION: purge la base locale
php bin/console doctrine:fixtures:load --no-interaction
```

Comptes de test disponibles:
- admin@studysprint.local / admin123 (ROLE_ADMIN)
- alice.martin@studysprint.local / user123 (Student)
- bob.dupont@studysprint.local / user123 (Student)
- charlie.bernard@studysprint.local / user123 (Student)
- prof.claire@studysprint.local / user123 (ROLE_TEACHER)
- prof.marc@studysprint.local / user123 (ROLE_TEACHER)

Source: `src/DataFixtures/AppFixtures.php`.
