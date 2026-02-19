# Audit : Flow "Prof Certifié"

**Date** : 05/02/2026  
**Auteur** : Cascade (audit automatisé)

---

## A) Ce qui existe déjà

### 1. Entité `User` (`src/Entity/User.php`)
- **`userType`** : champ `VARCHAR(50)` avec `@Assert\Choice(['STUDENT', 'TEACHER', 'ADMIN'])`.
- **`roles`** : champ JSON, avec `ROLE_USER` ajouté automatiquement dans `getRoles()`.
- Le type `TEACHER` existe déjà comme valeur valide — il est utilisé dans les fixtures (`prof.claire@studysprint.local`).
- **Aucun champ** `verificationStatus`, `certifiedAt`, ou similaire n'existe sur `User`.

### 2. Fixtures (`src/DataFixtures/AppFixtures.php`)
- Un utilisateur `TEACHER` existe : `Claire Leroux` (`prof.claire@studysprint.local`, `userType: TEACHER`, `roles: [ROLE_USER]`).
- Ce teacher crée des matières, chapitres, groupes, quiz, flashcards — il a le **même périmètre fonctionnel** qu'un étudiant.
- Aucune notion de "certification" ou "demande" dans les fixtures.

### 3. BO existant
- **Pattern CRUD** bien établi : `src/Controller/Bo/*Controller.php` avec index paginé + search + sort, show, new, edit, delete.
- **Convention routes** : `/bo/{entity}` avec noms `bo_{entity}_index|show|new|edit|delete`.
- **Layout** : `templates/layouts/bo.html.twig` avec sidebar sectionnée (Gestion, Utilisateurs, Contenu, Planning, Communauté, Training).
- **Pagination** : partial `templates/bo/_pagination.html.twig` réutilisable.
- **Sécurité** : `denyAccessUnlessGranted('ROLE_ADMIN')` dans chaque controller + `access_control` dans `security.yaml`.
- **Aucune section** "Demandes" ou "Certification" dans le BO.

### 4. FO Profil (`src/Controller/Fo/ProfileController.php`)
- Routes : `/fo/profile` (show), `/fo/profile/edit`, `/fo/profile/ai-enhance`.
- Template `show.html.twig` affiche : avatar, fullName, email, `userType` (badge simple), niveau, spécialité, bio, suggestions IA.
- **Aucune section** certification/teacher dans le profil FO.

### 5. Badge component (`templates/components/_badge.html.twig`)
- Composant réutilisable avec variants : `neutral | primary | success | warning | error | info`.
- Usage : `{{ include('components/_badge.html.twig', {text: '...', variant: '...'}) }}`.

### 6. Affichage auteur dans les contenus FO
- **Posts** (`_post_card.html.twig`) : affiche `post.author.fullName` + initiale avatar. Pas de badge teacher.
- **Groupes, Matières, Chapitres** : affichent le `createdBy` sans badge.

### 7. Security
- `role_hierarchy: ROLE_ADMIN: ROLE_USER` — pas de `ROLE_TEACHER`.
- `access_control` : `/bo` → `ROLE_ADMIN`, `/fo` → `ROLE_USER`.

---

## B) Ce qui manque

| Élément | Status |
|---------|--------|
| Entité `TeacherCertificationRequest` | **À créer** |
| Migration DB pour la table | **À créer** |
| Repository avec méthodes de recherche/filtrage | **À créer** |
| Controller BO `/bo/certifications` (list, show, approve, reject) | **À créer** |
| Templates BO (index, show) | **À créer** |
| Entrée sidebar BO | **À ajouter** |
| Controller FO (submit request) | **À ajouter** dans `ProfileController` |
| Section certification dans `fo/profile/show.html.twig` | **À ajouter** |
| Badge "Prof certifié" sur profil FO | **À ajouter** |
| Badge "Prof certifié" sur contenus (posts, groupes, matières) | **À ajouter** |
| Helper `User::isCertifiedTeacher()` | **À ajouter** |
| Twig partial `_teacher_badge.html.twig` | **À créer** |
| Tests PHPUnit (sécurité + fonctionnel) | **À créer** |

---

## C) Décisions prises

### 1. Mécanisme de certification
**Décision** : Réutiliser `user_type = 'TEACHER'` comme marqueur final de certification.
- Quand une demande est APPROVED → `user.userType` passe de `STUDENT` à `TEACHER`.
- `isCertifiedTeacher()` = `userType === 'TEACHER'`.
- Pas besoin d'un champ supplémentaire sur `User` — la table `teacher_certification_requests` trace l'historique.

### 2. Table de demandes
**Décision** : Créer `teacher_certification_requests` avec :
- `id`, `user_id` (FK), `status` (PENDING/APPROVED/REJECTED), `motivation` (texte optionnel du demandeur), `reason` (motif admin), `requested_at`, `reviewed_at`, `reviewed_by` (FK nullable vers users).
- Contrainte applicative : pas de double PENDING (vérification en code + index unique partiel si supporté).

### 3. Pas de `ROLE_TEACHER`
**Décision** : Ne pas ajouter de rôle Symfony `ROLE_TEACHER`. Le `userType` suffit pour le badge. Le teacher garde `ROLE_USER` et le même périmètre fonctionnel.

### 4. Badge
**Décision** : Créer un partial `templates/components/_teacher_badge.html.twig` réutilisable, inséré à côté du nom de l'auteur dans les templates FO existants.

### 5. Routes
- **FO** : `POST /fo/profile/certification` → soumettre une demande.
- **BO** : `/bo/certifications` (index), `/bo/certifications/{id}` (show), `POST /bo/certifications/{id}/approve`, `POST /bo/certifications/{id}/reject`.
