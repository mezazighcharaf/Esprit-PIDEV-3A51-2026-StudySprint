# StudySprint - Conventions Base de Données & Code

## 1. Conventions de Nommage SQL

### Tables
| Type | Convention | Exemple |
|------|------------|---------|
| Table standard | `snake_case` | `quiz_attempt`, `study_session` |
| Table jointure N:N | `{table1}_{table2}` | `content_item_tag`, `group_member` |

### Colonnes
| Type | Convention | Exemple |
|------|------------|---------|
| ID primaire | `id` | `id BIGINT UNSIGNED AUTO_INCREMENT` |
| Clé étrangère | `{table_singulier}_id` | `user_id`, `quiz_id` |
| Booléen | `is_{adjective}` | `is_active`, `is_published` |
| Date/Time | `{action}_at` | `created_at`, `updated_at`, `finished_at` |
| JSON | `{name}_json` | `preferences_json`, `config_json` |
| Compteur | `{noun}_count` | `view_count`, `attempt_count` |

### Contraintes
| Type | Convention | Exemple |
|------|------------|---------|
| Primary Key | Implicite | `PRIMARY KEY (id)` |
| Foreign Key | `fk_{table}_{reference}` | `fk_quiz_owner` |
| Unique | `uniq_{table}_{column}` | `uniq_users_email` |
| Index | `idx_{table}_{column}` | `idx_quiz_difficulty` |

### Types de données standards
| Donnée | Type SQL | Notes |
|--------|----------|-------|
| ID | `BIGINT UNSIGNED AUTO_INCREMENT` | Toujours unsigned |
| Email | `VARCHAR(180)` | Standard Symfony |
| Mot de passe | `VARCHAR(255)` | Hash bcrypt |
| Titre court | `VARCHAR(120-200)` | Selon contexte |
| Texte long | `TEXT` | Pour contenu |
| JSON | `JSON` | Pas de default |
| Boolean | `TINYINT(1)` | DEFAULT 0 ou 1 |
| Date | `DATE` | Format YYYY-MM-DD |
| DateTime | `DATETIME` | Avec DEFAULT CURRENT_TIMESTAMP si création |
| Enum-like | `VARCHAR(20-30)` | Avec COMMENT listant les valeurs |

---

## 2. Conventions Symfony / PHP

### Structure des dossiers
```
src/
├── Controller/
│   ├── Bo/                    # BackOffice (admin)
│   │   ├── Training/          # Module Training
│   │   ├── Content/           # Module Content
│   │   ├── Planning/          # Module Planning
│   │   └── Encadrement/       # Module Encadrement
│   └── Fo/                    # FrontOffice (user)
│       ├── Training/
│       ├── Content/
│       └── ...
├── Entity/                    # Entités Doctrine
├── Repository/                # Repositories
├── Form/                      # Form Types
├── Service/                   # Services métier
└── DataFixtures/              # Fixtures de test
```

### Nommage des fichiers
| Type | Convention | Exemple |
|------|------------|---------|
| Entity | `PascalCase` singulier | `Quiz.php`, `FlashcardReview.php` |
| Repository | `{Entity}Repository` | `QuizRepository.php` |
| Controller | `{Resource}Controller` | `QuizController.php` |
| Form | `{Entity}Type` | `QuizType.php` |
| Service | `{Domain}Service` | `QuizScoringService.php` |

### Nommage des routes
| Contexte | Pattern | Exemple |
|----------|---------|---------|
| BO Liste | `admin_{module}_{resource}_index` | `admin_training_quiz_index` |
| BO Créer | `admin_{module}_{resource}_new` | `admin_training_quiz_new` |
| BO Voir | `admin_{module}_{resource}_show` | `admin_training_quiz_show` |
| BO Modifier | `admin_{module}_{resource}_edit` | `admin_training_quiz_edit` |
| BO Supprimer | `admin_{module}_{resource}_delete` | `admin_training_quiz_delete` |
| FO | `{module}_{resource}_{action}` | `training_quiz_play` |

### Nommage des templates
```
templates/
├── bo/
│   └── training/
│       └── quiz/
│           ├── index.html.twig
│           ├── new.html.twig
│           ├── show.html.twig
│           └── edit.html.twig
└── fo/
    └── training/
        └── quiz/
            ├── index.html.twig
            └── play.html.twig
```

---

## 3. Rôles Utilisateurs

| Rôle | Description | Accès |
|------|-------------|-------|
| `ROLE_USER` | Utilisateur standard (étudiant) | FO uniquement |
| `ROLE_TUTOR` | Tuteur/Prof | FO + fonctions encadrement |
| `ROLE_ADMIN` | Administrateur | BO + FO complet |

Hiérarchie dans `security.yaml`:
```yaml
role_hierarchy:
    ROLE_TUTOR: ROLE_USER
    ROLE_ADMIN: [ROLE_TUTOR, ROLE_USER]
```

---

## 4. Modules et Responsabilités

| Module | Tables | Responsable |
|--------|--------|-------------|
| **Users** | `users`, `user_profile` | Dev Users |
| **Content** | `subject`, `chapter`, `content_item`, `content_tag`, `content_item_tag` | Dev Content |
| **Planning** | `sprint`, `study_session` | Dev Planning |
| **Encadrement** | `tutorship`, `student_question`, `feedback`, `study_group`, `group_member`, `group_invitation`, `group_post` | Dev Encadrement |
| **Training** | `quiz*`, `deck`, `flashcard*`, `training_kpi_daily`, `ai_*` | Dev Training |

---

## 5. Règles de Développement

### Entités partagées
- **`User`** : Ne pas modifier sans accord de l'équipe
- **`Subject`** : Utilisé par plusieurs modules (Content, Training, Planning)

### Migrations
1. **Toujours** utiliser `make:migration` (jamais SQL direct)
2. **Nommer** les migrations clairement
3. **Tester** sur une copie de la DB avant merge
4. **Communiquer** avant de modifier une table partagée

### Foreign Keys
- `ON DELETE CASCADE` : Pour les enfants qui n'ont pas de sens sans parent (quiz_question → quiz)
- `ON DELETE RESTRICT` : Pour éviter suppression accidentelle (quiz → owner/user)
- `ON DELETE SET NULL` : Pour les références optionnelles (content_item → chapter)

### Valeurs par défaut
- `created_at` : `DEFAULT CURRENT_TIMESTAMP`
- `updated_at` : `NULL` (mis à jour par Doctrine)
- `is_*` : `DEFAULT 0` ou `DEFAULT 1` selon le cas
- `status` : `DEFAULT 'pending'` ou premier état logique

---

## 6. Git Workflow pour DB

```bash
# Avant de créer une migration
git pull origin main

# Créer la migration
php bin/console make:migration

# Vérifier le SQL généré
cat migrations/VersionXXX.php

# Tester localement
php bin/console doctrine:migrations:migrate

# Commit
git add migrations/
git commit -m "feat(db): add {table} table for {module}"
```

---

## 7. Checklist Avant Merge

- [ ] Migration testée localement
- [ ] Pas de conflit avec autres migrations
- [ ] Conventions de nommage respectées
- [ ] FK et index appropriés
- [ ] Pas de modification sur tables d'autres modules sans accord
- [ ] Documentation mise à jour si nouvelle table
