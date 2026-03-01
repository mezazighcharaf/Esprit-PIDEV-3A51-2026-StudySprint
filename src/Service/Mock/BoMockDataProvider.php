<?php

namespace App\Service\Mock;

/**
 * BoMockDataProvider
 *
 * Source unique de donnees mock pour le Back-Office.
 * Les Controllers recuperent un "view model" (array structure) depuis ce provider.
 */
class BoMockDataProvider
{
    /**
     * Donnees pour le Dashboard BO
     */
    /** @return array<mixed> */     /** @return array<mixed> */
        /** @return array<mixed> */
    public function getDashboardData(): array
    {
        return [
            'kpis' => [
                [
                    'label' => 'Utilisateurs actifs',
                    'value' => '58',
                    'trend' => '+12%',
                    'trend_direction' => 'up',
                    'subtitle' => 'vs semaine derniere',
                ],
                [
                    'label' => 'Sessions (7j)',
                    'value' => '128',
                    'trend' => '+8%',
                    'trend_direction' => 'up',
                    'subtitle' => 'vs semaine derniere',
                ],
                [
                    'label' => 'Completion',
                    'value' => '62%',
                    'trend' => '-3%',
                    'trend_direction' => 'down',
                    'subtitle' => 'taux moyen',
                ],
                [
                    'label' => 'Quiz passes',
                    'value' => '74',
                    'trend' => '+15%',
                    'trend_direction' => 'up',
                    'subtitle' => 'cette semaine',
                ],
                [
                    'label' => 'Groupes actifs',
                    'value' => '12',
                    'trend' => null,
                    'trend_direction' => null,
                    'subtitle' => 'sur 18 total',
                ],
            ],
            'recent_users' => $this->getRecentUsers(),
            'alerts' => [
                ['type' => 'warning', 'message' => '3 utilisateurs n\'ont pas de groupe assigne'],
                ['type' => 'info', 'message' => '5 nouvelles inscriptions cette semaine'],
            ],
        ];
    }

    /**
     * Donnees pour la page Analytics (PRIORITE)
     * Utilisé par Twig ET API JSON
     */
    /** @return array<mixed> */     /** @return array<mixed> */
        /** @return array<mixed> */
    public function getAnalyticsData(): array
    {
        return [
            'filters' => $this->getAnalyticsFilters(),
            'kpis' => [
                [
                    'label' => 'Utilisateurs actifs',
                    'value' => '58',
                    'trend' => '+12%',
                    'trend_direction' => 'up',
                    'subtitle' => 'sur 7 jours',
                    'sparkline' => [45, 48, 52, 49, 55, 58, 58],
                ],
                [
                    'label' => 'Sessions (7j)',
                    'value' => '128',
                    'trend' => '+8%',
                    'trend_direction' => 'up',
                    'subtitle' => 'total semaine',
                    'sparkline' => [95, 102, 98, 115, 120, 125, 128],
                ],
                [
                    'label' => 'Completion',
                    'value' => '62%',
                    'trend' => '-3%',
                    'trend_direction' => 'down',
                    'subtitle' => 'taux moyen',
                    'sparkline' => [65, 64, 63, 62, 61, 62, 62],
                ],
                [
                    'label' => 'Quiz passes',
                    'value' => '74',
                    'trend' => '+15%',
                    'trend_direction' => 'up',
                    'subtitle' => 'cette semaine',
                    'sparkline' => [52, 58, 62, 65, 68, 71, 74],
                ],
                [
                    'label' => 'Taux reussite',
                    'value' => '74%',
                    'trend' => '+5%',
                    'trend_direction' => 'up',
                    'subtitle' => 'moyenne quiz',
                    'sparkline' => [68, 70, 71, 72, 73, 74, 74],
                ],
            ],
            'activity_trend' => $this->getActivityTrend(),
            'top_subjects' => $this->getTopSubjects(),
            'insights' => $this->getInsightsClean(),
            'anomalies' => $this->getAnomalies(),
        ];
    }

    /**
     * Donnees pour la page Utilisateurs
     */
    /** @return array<mixed> */     /** @return array<mixed> */
        /** @return array<mixed> */
    public function getUsersData(): array
    {
        return [
            'stats' => [
                'total' => 156,
                'active' => 128,
                'inactive' => 18,
                'pending' => 10,
            ],
            'filters' => [
                [
                    'name' => 'status',
                    'label' => 'Statut',
                    'options' => [
                        ['value' => 'all', 'label' => 'Tous'],
                        ['value' => 'active', 'label' => 'Actifs'],
                        ['value' => 'inactive', 'label' => 'Inactifs'],
                        ['value' => 'pending', 'label' => 'En attente'],
                    ],
                    'selected' => 'all',
                ],
                [
                    'name' => 'role',
                    'label' => 'Role',
                    'options' => [
                        ['value' => 'all', 'label' => 'Tous'],
                        ['value' => 'student', 'label' => 'Etudiants'],
                        ['value' => 'mentor', 'label' => 'Encadrants'],
                        ['value' => 'admin', 'label' => 'Admins'],
                    ],
                    'selected' => 'all',
                ],
            ],
            'users' => $this->getUsers(),
        ];
    }

    /**
     * Donnees pour la page Contenu
     */
    /** @return array<mixed> */     /** @return array<mixed> */
        /** @return array<mixed> */
    public function getContentData(): array
    {
        return [
            'tabs' => [
                ['id' => 'subjects', 'label' => 'Matieres', 'count' => 8, 'active' => true],
                ['id' => 'chapters', 'label' => 'Chapitres', 'count' => 45],
                ['id' => 'resources', 'label' => 'Ressources', 'count' => 128],
            ],
            'subjects' => $this->getSubjects(),
            'chapters' => $this->getChapters(),
            'resources' => $this->getResources(),
        ];
    }

    /**
     * Donnees pour la page Encadrement
     */
    /** @return array<mixed> */     /** @return array<mixed> */
        /** @return array<mixed> */
    public function getMentoringData(): array
    {
        return [
            'tabs' => [
                ['id' => 'groups', 'label' => 'Groupes', 'count' => 18, 'active' => true],
                ['id' => 'invitations', 'label' => 'Invitations', 'count' => 5],
                ['id' => 'feedbacks', 'label' => 'Feedbacks', 'count' => 32],
            ],
            'groups' => $this->getGroups(),
            'invitations' => $this->getMentoringInvitations(),
            'feedbacks' => $this->getMentoringFeedbacks(),
        ];
    }

    /**
     * Donnees pour la page Training (M5)
     */
    /** @return array<mixed> */     /** @return array<mixed> */
        /** @return array<mixed> */
    public function getTrainingData(): array
    {
        return [
            'tabs' => [
                ['id' => 'templates', 'label' => 'Templates de quiz', 'count' => 24, 'active' => true],
                ['id' => 'results', 'label' => 'Resultats', 'count' => 156],
                ['id' => 'logs', 'label' => 'Logs IA', 'count' => 89],
            ],
            'templates' => $this->getQuizTemplates(),
            'results' => $this->getQuizResults(),
            'logs' => $this->getAiLogs(),
        ];
    }

    // ─── PRIVATE HELPERS ───

        /** @return array<mixed> */
    private function getAnalyticsFilters(): array
    {
        return [
            [
                'name' => 'period',
                'label' => 'Periode',
                'options' => [
                    ['value' => '7d', 'label' => '7 derniers jours'],
                    ['value' => '30d', 'label' => '30 derniers jours'],
                    ['value' => '90d', 'label' => '90 derniers jours'],
                    ['value' => 'custom', 'label' => 'Personnalise'],
                ],
                'selected' => '7d',
            ],
            [
                'name' => 'subject',
                'label' => 'Matiere',
                'options' => [
                    ['value' => 'all', 'label' => 'Toutes'],
                    ['value' => 'math', 'label' => 'Mathematiques'],
                    ['value' => 'physics', 'label' => 'Physique'],
                    ['value' => 'chemistry', 'label' => 'Chimie'],
                    ['value' => 'english', 'label' => 'Anglais'],
                ],
                'selected' => 'all',
            ],
            [
                'name' => 'group',
                'label' => 'Groupe',
                'options' => [
                    ['value' => 'all', 'label' => 'Tous'],
                    ['value' => '1', 'label' => 'Prepa MPSI'],
                    ['value' => '2', 'label' => 'Terminale S'],
                    ['value' => '3', 'label' => 'Licence 1'],
                ],
                'selected' => 'all',
            ],
            [
                'name' => 'role',
                'label' => 'Role',
                'options' => [
                    ['value' => 'all', 'label' => 'Tous'],
                    ['value' => 'student', 'label' => 'Etudiants'],
                    ['value' => 'mentor', 'label' => 'Encadrants'],
                ],
                'selected' => 'all',
            ],
        ];
    }

        /** @return array<mixed> */
    private function getActivityTrend(): array
    {
        return [
            'labels' => ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
            'data' => [45, 62, 58, 71, 89, 42, 38],
            'previous' => [38, 55, 48, 62, 75, 35, 32],
        ];
    }

        /** @return array<mixed> */
    private function getTopSubjects(): array
    {
        return [
            ['name' => 'Mathematiques', 'value' => 342, 'percentage' => 100],
            ['name' => 'Physique', 'value' => 285, 'percentage' => 83],
            ['name' => 'Anglais', 'value' => 198, 'percentage' => 58],
            ['name' => 'Chimie', 'value' => 156, 'percentage' => 46],
            ['name' => 'Programmation', 'value' => 124, 'percentage' => 36],
        ];
    }
    /** @return array<mixed> */
    /** @phpstan-ignore-next-line */
    private function getInsights(): array
    {
        return [
            [
                'type' => 'success',
                'text' => '<strong>Physique</strong> : completion en hausse +15% cette semaine',
                'action' => 'Voir les details',
            ],
            [
                'type' => 'warning',
                'text' => '<strong>POP PO2</strong> inactifs : ajuster les rappels',
                'action' => 'Configurer les notifications',
            ],
            [
                'type' => 'error',
                'text' => '<strong>Groupes inactifs</strong> : Algo 2S > 1 semaine d\'inactivite',
                'action' => 'Contacter l\'encadrant',
            ],
            [
                'type' => 'info',
                'text' => '<strong>Nouvelle matiere</strong> : Anglais enregistre une forte croissance',
                'action' => 'Creer des ressources',
            ],
        ];
    }

        /** @return array<mixed> */
    private function getInsightsClean(): array
    {
        return [
            [
                'type' => 'success',
                'text' => 'Physique : completion en hausse +15% cette semaine',
            ],
            [
                'type' => 'warning',
                'text' => 'POP PO2 inactifs : ajuster les rappels',
            ],
            [
                'type' => 'error',
                'text' => 'Groupes inactifs : Algo 2S > 1 semaine d\'inactivite',
            ],
            [
                'type' => 'info',
                'text' => 'Nouvelle matiere : Anglais enregistre une forte croissance',
            ],
        ];
    }

        /** @return array<mixed> */
    private function getAnomalies(): array
    {
        return [
            [
                'user' => 'Jean Martin',
                'email' => 'j.martin@email.com',
                'type' => 'Inactivite prolongee',
                'details' => 'Aucune connexion depuis 14 jours',
                'severity' => 'warning',
            ],
            [
                'user' => 'Sophie Durand',
                'email' => 's.durand@email.com',
                'type' => 'Taux echec eleve',
                'details' => '65% d\'echec sur les quiz Maths',
                'severity' => 'error',
            ],
            [
                'user' => 'Pierre Lefebvre',
                'email' => 'p.lefebvre@email.com',
                'type' => 'Sessions tres courtes',
                'details' => 'Moyenne 5 min/session',
                'severity' => 'warning',
            ],
        ];
    }

        /** @return array<mixed> */
    private function getRecentUsers(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Marie Dupont',
                'email' => 'marie.dupont@email.com',
                'role' => 'Etudiant',
                'status' => 'Actif',
                'last_activity' => 'Il y a 5 min',
                'initials' => 'MD',
            ],
            [
                'id' => 2,
                'name' => 'Jean Martin',
                'email' => 'jean.martin@email.com',
                'role' => 'Etudiant',
                'status' => 'Actif',
                'last_activity' => 'Il y a 15 min',
                'initials' => 'JM',
            ],
            [
                'id' => 3,
                'name' => 'Sophie Durand',
                'email' => 'sophie.durand@email.com',
                'role' => 'Encadrant',
                'status' => 'Actif',
                'last_activity' => 'Il y a 1h',
                'initials' => 'SD',
            ],
        ];
    }

        /** @return array<mixed> */
    private function getUsers(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Marie Dupont',
                'email' => 'marie.dupont@email.com',
                'role' => 'Etudiant',
                'status' => 'Actif',
                'group' => 'Prepa MPSI',
                'sessions' => 45,
                'last_activity' => '24 jan 2026',
                'initials' => 'MD',
            ],
            [
                'id' => 2,
                'name' => 'Jean Martin',
                'email' => 'jean.martin@email.com',
                'role' => 'Etudiant',
                'status' => 'Inactif',
                'group' => 'Terminale S',
                'sessions' => 12,
                'last_activity' => '10 jan 2026',
                'initials' => 'JM',
            ],
            [
                'id' => 3,
                'name' => 'Sophie Durand',
                'email' => 'sophie.durand@email.com',
                'role' => 'Encadrant',
                'status' => 'Actif',
                'group' => 'Prepa MPSI',
                'sessions' => 89,
                'last_activity' => '24 jan 2026',
                'initials' => 'SD',
            ],
            [
                'id' => 4,
                'name' => 'Pierre Lefebvre',
                'email' => 'pierre.lefebvre@email.com',
                'role' => 'Etudiant',
                'status' => 'En attente',
                'group' => '-',
                'sessions' => 0,
                'last_activity' => '-',
                'initials' => 'PL',
            ],
            [
                'id' => 5,
                'name' => 'Claire Bernard',
                'email' => 'claire.bernard@email.com',
                'role' => 'Admin',
                'status' => 'Actif',
                'group' => '-',
                'sessions' => 156,
                'last_activity' => '24 jan 2026',
                'initials' => 'CB',
            ],
        ];
    }

        /** @return array<mixed> */
    private function getSubjects(): array
    {
        return [
            ['id' => 1, 'name' => 'Mathematiques', 'code' => 'MATH', 'chapters' => 12, 'resources' => 48, 'status' => 'Actif', 'created' => '15 sep 2025'],
            ['id' => 2, 'name' => 'Physique', 'code' => 'PHY', 'chapters' => 10, 'resources' => 35, 'status' => 'Actif', 'created' => '15 sep 2025'],
            ['id' => 3, 'name' => 'Chimie', 'code' => 'CHI', 'chapters' => 8, 'resources' => 28, 'status' => 'Actif', 'created' => '20 sep 2025'],
            ['id' => 4, 'name' => 'Anglais', 'code' => 'ENG', 'chapters' => 6, 'resources' => 42, 'status' => 'Actif', 'created' => '01 oct 2025'],
            ['id' => 5, 'name' => 'Programmation', 'code' => 'PROG', 'chapters' => 9, 'resources' => 24, 'status' => 'Brouillon', 'created' => '10 jan 2026'],
        ];
    }

        /** @return array<mixed> */
    private function getChapters(): array
    {
        return [
            ['id' => 1, 'name' => 'Integrales', 'subject' => 'Mathematiques', 'resources' => 8, 'quizzes' => 3, 'status' => 'Publie'],
            ['id' => 2, 'name' => 'Derivees', 'subject' => 'Mathematiques', 'resources' => 6, 'quizzes' => 2, 'status' => 'Publie'],
            ['id' => 3, 'name' => 'Cinematique', 'subject' => 'Physique', 'resources' => 5, 'quizzes' => 2, 'status' => 'Publie'],
            ['id' => 4, 'name' => 'Dynamique', 'subject' => 'Physique', 'resources' => 7, 'quizzes' => 3, 'status' => 'Brouillon'],
        ];
    }

        /** @return array<mixed> */
    private function getResources(): array
    {
        return [
            ['id' => 1, 'name' => 'Cours - Integrales', 'type' => 'PDF', 'subject' => 'Mathematiques', 'chapter' => 'Integrales', 'downloads' => 234],
            ['id' => 2, 'name' => 'Exercices corriges', 'type' => 'PDF', 'subject' => 'Mathematiques', 'chapter' => 'Integrales', 'downloads' => 189],
            ['id' => 3, 'name' => 'Video explicative', 'type' => 'Video', 'subject' => 'Physique', 'chapter' => 'Cinematique', 'downloads' => 312],
        ];
    }

        /** @return array<mixed> */
    private function getGroups(): array
    {
        return [
            ['id' => 1, 'name' => 'Prepa MPSI - Maths', 'mentor' => 'Sophie Durand', 'members' => 12, 'sessions' => 45, 'status' => 'Actif'],
            ['id' => 2, 'name' => 'Terminale S', 'mentor' => 'Marc Petit', 'members' => 8, 'sessions' => 28, 'status' => 'Actif'],
            ['id' => 3, 'name' => 'Licence 1 Physique', 'mentor' => 'Julie Moreau', 'members' => 15, 'sessions' => 62, 'status' => 'Actif'],
            ['id' => 4, 'name' => 'Algo 2S', 'mentor' => 'Thomas Bernard', 'members' => 6, 'sessions' => 8, 'status' => 'Inactif'],
        ];
    }

        /** @return array<mixed> */
    private function getMentoringInvitations(): array
    {
        return [
            ['id' => 1, 'user' => 'Alice Martin', 'group' => 'Prepa MPSI', 'date' => '23 jan 2026', 'status' => 'En attente'],
            ['id' => 2, 'user' => 'Bob Dupont', 'group' => 'Terminale S', 'date' => '22 jan 2026', 'status' => 'En attente'],
            ['id' => 3, 'user' => 'Carla Leroy', 'group' => 'Licence 1', 'date' => '20 jan 2026', 'status' => 'Expiree'],
        ];
    }

        /** @return array<mixed> */
    private function getMentoringFeedbacks(): array
    {
        return [
            ['id' => 1, 'from' => 'Marie Dupont', 'to' => 'Sophie Durand', 'group' => 'Prepa MPSI', 'rating' => 5, 'comment' => 'Excellentes explications', 'date' => '24 jan 2026'],
            ['id' => 2, 'from' => 'Jean Martin', 'to' => 'Marc Petit', 'group' => 'Terminale S', 'rating' => 4, 'comment' => 'Tres bon suivi', 'date' => '23 jan 2026'],
        ];
    }

        /** @return array<mixed> */
    private function getQuizTemplates(): array
    {
        return [
            ['id' => 1, 'name' => 'Quiz Integrales Niveau 1', 'subject' => 'Mathematiques', 'questions' => 20, 'uses' => 45, 'created' => '15 dec 2025'],
            ['id' => 2, 'name' => 'Quiz Cinematique', 'subject' => 'Physique', 'questions' => 15, 'uses' => 38, 'created' => '18 dec 2025'],
            ['id' => 3, 'name' => 'Vocabulaire B2', 'subject' => 'Anglais', 'questions' => 30, 'uses' => 62, 'created' => '20 dec 2025'],
        ];
    }

        /** @return array<mixed> */
    private function getQuizResults(): array
    {
        return [
            ['id' => 1, 'user' => 'Marie Dupont', 'quiz' => 'Quiz Integrales', 'score' => '18/20', 'date' => '24 jan 2026', 'duration' => '12 min'],
            ['id' => 2, 'user' => 'Jean Martin', 'quiz' => 'Quiz Cinematique', 'score' => '12/15', 'date' => '23 jan 2026', 'duration' => '8 min'],
            ['id' => 3, 'user' => 'Sophie Durand', 'quiz' => 'Vocabulaire B2', 'score' => '28/30', 'date' => '22 jan 2026', 'duration' => '15 min'],
        ];
    }

        /** @return array<mixed> */
    private function getAiLogs(): array
    {
        return [
            ['id' => 1, 'user' => 'Marie Dupont', 'action' => 'Generation de deck', 'subject' => 'Mathematiques', 'status' => 'success', 'date' => '24 jan 2026 14:30'],
            ['id' => 2, 'user' => 'Jean Martin', 'action' => 'Generation de quiz', 'subject' => 'Physique', 'status' => 'success', 'date' => '24 jan 2026 11:15'],
            ['id' => 3, 'user' => 'System', 'action' => 'Optimisation modele', 'subject' => '-', 'status' => 'success', 'date' => '24 jan 2026 03:00'],
            ['id' => 4, 'user' => 'Pierre Lefebvre', 'action' => 'Generation de deck', 'subject' => 'Chimie', 'status' => 'error', 'date' => '23 jan 2026 16:45'],
        ];
    }
}
