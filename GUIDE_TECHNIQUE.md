# 🚀 Guide Technique Complet StudySprint

Ce document est votre référence pour comprendre le fonctionnement interne et l'architecture du projet StudySprint.

---

## � 1. Gestion des Utilisateurs (`gestion-user`)

Le module `gestion-user` est le pilier de la sécurité et de l'identité de l'application.

*   **L'Entité `User` (`src/Entity/User.php`)** : Stocke l'email, le mot de passe haché et les informations de profil (nom, spécialité, etc.).
*   **Hiérarchie des Rôles** :
    *   `ROLE_USER` : Accès standard (étudiants).
    *   `ROLE_PROFESSOR` : Fonctionnalités pédagogiques.
    *   `ROLE_ADMIN` : Accès au Back Office (administration globale).
*   **Composants Clés** :
    *   **Contrôleurs** : `ProfileController` (Front Office) et `Bo\UsersController` (Back Office).
    *   **Sécurité** : Utilisation de **Voters** (ex: `GroupVoter`) pour vérifier les droits d'action en temps réel.
    *   **Services** : Réinitialisation de mot de passe par email et gestion des avatars.

---

## 🤖 2. Intelligence Artificielle et APIs

L'application intègre une IA moderne pour assister les étudiants.

*   **IA Gemini (Google)** :
    *   **Modèle** : `gemini-2.5-flash`.
    *   **Service** : `GeminiChatbotService`.
*   **Reconnaissance Faciale (Biométrie)** :
    *   **Langage** : **JavaScript** (via la bibliothèque `face-api.js` et `TensorFlow.js`).
    *   **Format de Données** : **JSON** (l'IA transforme le visage en une liste de 128 nombres stockés au format JSON).
    *   **Gestion Serveur** : **PHP** (Symfony reçoit et compare les données).
    *   *Note : Il n'y a pas de Python ou de Java dans ce module.*
*   **APIs Internes (AJAX)** :
    *   Utilisent le format **JSON** pour communiquer entre le navigateur (JS) et le serveur (PHP).

---

## ⚙️ 3. Configuration et Fichiers Systèmes

### Le fichier `.env` (Tableau de bord)
Il centralise les paramètres variables et secrets :
*   `DATABASE_URL` : Connexion à MySQL (`studysprint`).
*   `MAILER_DSN` : Configuration pour l'envoi d'emails via Gmail.
*   `GEMINI_API_KEY` : Votre clé secrète pour activer l'IA.

### Fichiers de configuration (`config/`)
*   **`bundles.php`** : Liste et active les extensions Symfony (Framework, Twig, Doctrine, Security).
*   **`preload.php`** : Optimise la vitesse en pré-chargeant les classes PHP en mémoire.
*   **`routes.yaml`** : Carte routière faisant le lien entre les URLs et les contrôleurs.
*   **`services.yaml`** : Définit comment les différentes briques (classes) du projet se connectent entre elles (Injection de dépendances).

---

## 📂 4. Structure des fichiers PHP (`src/`) : L'Analogie du Restaurant

Pour bien comprendre à quoi servent les fichiers dans `src/`, imaginez un restaurant :

*   **`Entity/` (Les Ingrédients)** : La définition de vos données (ex: un utilisateur, un groupe).
*   **`Repository/` (Le Magasinier)** : Va chercher les bons ingrédients en stock (Base de données).
*   **`Controller/` (Le Serveur)** : Prend la commande du client (URL) et transmet les instructions.
*   **`Service/` (Le Cuisinier)** : Prépare les plats complexes (IA, logique métier).
*   **`Form/` (Le Menu)** : Définit ce que l'utilisateur peut choisir et saisir.
*   **`Dto/` (Le Bon de commande)** : Un format propre pour transporter les données saisies vers le cuisinier.
*   **`Enum/` (La Carte fixe)** : Listes d'options prédéfinies (ex: roles Admin/Membre).
*   **`Security/` (Le Vigile)** : Vérifie que le client a le droit d'entrer ou de modifier un plat.

---

## 🛠️ 5. Maintenance et Débogage

*   **Migrations** : Fichiers dans `migrations/` pour mettre à jour la base de données sans perdre de données.
*   **Web Profiler** : La barre noire en bas de page pour analyser les performances et les erreurs en temps réel.

---

## 🚀 6. Lancement Rapide (Commandes Terminal)

Pour lancer le projet dans VS Code, ouvrez un terminal (**Ctrl + `**) et exécutez ces commandes :

1.  **Installer les dépendances** (si c'est la première fois) :
    ```bash
    composer install
    ```

2.  **Mettre à jour la base de données** :
    ```bash
    php bin/console doctrine:migrations:migrate
    ```

3.  **Lancer le serveur de développement** :
    ```bash
    php -S 127.0.0.1:8000 -t public
    ```
    *Ensuite, ouvrez votre navigateur à l'adresse : **http://127.0.0.1:8000***

---

> [!TIP]
> Ce guide est conçu pour vous aider à maîtriser l'architecture StudySprint et à la développer sereinement.
