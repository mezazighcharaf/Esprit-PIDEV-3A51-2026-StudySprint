<?php

namespace App\Service;

class BoMockDataProvider
{
    public function getDashboardData(): array
    {
        return [
            'kpis' => [
                ['label' => 'Utilisateurs actifs', 'value' => '1,234', 'subtitle' => '+12% vs mois dernier', 'trend' => '+12%', 'trend_direction' => 'up'],
                ['label' => 'Sessions aujourd\'hui', 'value' => '456', 'subtitle' => 'Moyenne: 8h42', 'trend' => '+8%', 'trend_direction' => 'up'],
                ['label' => 'Matières', 'value' => '24', 'subtitle' => '156 chapitres', 'trend' => null, 'trend_direction' => null],
                ['label' => 'Quiz complétés', 'value' => '2,891', 'subtitle' => 'Cette semaine', 'trend' => '+23%', 'trend_direction' => 'up'],
                ['label' => 'Taux de réussite', 'value' => '76%', 'subtitle' => 'Moyenne globale', 'trend' => '+3%', 'trend_direction' => 'up'],
            ],
            'alerts' => [
                ['type' => 'warning', 'message' => '3 utilisateurs ont signalé des problèmes techniques'],
                ['type' => 'info', 'message' => 'Nouvelle version disponible: v2.1.0'],
            ],
            'recent_users' => [
                ['name' => 'Alice Martin', 'email' => 'alice.martin@example.com', 'initials' => 'AM', 'role' => 'Étudiant', 'status' => 'Actif', 'last_activity' => 'Il y a 5 min'],
                ['name' => 'Bob Dupont', 'email' => 'bob.dupont@example.com', 'initials' => 'BD', 'role' => 'Étudiant', 'status' => 'Actif', 'last_activity' => 'Il y a 12 min'],
                ['name' => 'Claire Leroux', 'email' => 'claire.leroux@example.com', 'initials' => 'CL', 'role' => 'Professeur', 'status' => 'Actif', 'last_activity' => 'Il y a 1h'],
                ['name' => 'David Chen', 'email' => 'david.chen@example.com', 'initials' => 'DC', 'role' => 'Étudiant', 'status' => 'Inactif', 'last_activity' => 'Il y a 3j'],
            ],
        ];
    }

    public function getAnalyticsData(): array
    {
        return [
            'kpis' => [
                ['label' => 'Sessions totales', 'value' => '12,456', 'subtitle' => 'Ce mois', 'trend' => '+18%', 'trend_direction' => 'up'],
                ['label' => 'Temps moyen/session', 'value' => '42 min', 'subtitle' => 'Moyenne', 'trend' => '+5%', 'trend_direction' => 'up'],
                ['label' => 'Taux de complétion', 'value' => '68%', 'subtitle' => 'Quiz & exercices', 'trend' => '-2%', 'trend_direction' => 'down'],
                ['label' => 'Score moyen', 'value' => '14.2/20', 'subtitle' => 'Tous quiz', 'trend' => '+1.2', 'trend_direction' => 'up'],
            ],
            'filters' => [
                ['label' => 'Cette semaine', 'value' => 'week', 'active' => false],
                ['label' => 'Ce mois', 'value' => 'month', 'active' => true],
                ['label' => 'Ce trimestre', 'value' => 'quarter', 'active' => false],
                ['label' => 'Cette année', 'value' => 'year', 'active' => false],
            ],
            'chart_data' => [
                'labels' => ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                'sessions' => [45, 52, 48, 61, 55, 28, 32],
                'users' => [32, 41, 38, 47, 42, 21, 25],
            ],
            'top_subjects' => [
                ['name' => 'Mathématiques', 'sessions' => 2341, 'completion' => 72],
                ['name' => 'Physique', 'sessions' => 1892, 'completion' => 68],
                ['name' => 'Chimie', 'sessions' => 1654, 'completion' => 65],
                ['name' => 'Biologie', 'sessions' => 1432, 'completion' => 71],
            ],
            'insights' => $this->getInsightsClean(),
        ];
    }

    public function getInsightsClean(): array
    {
        return [
            ['type' => 'success', 'text' => 'Physique : completion en hausse +15% cette semaine'],
            ['type' => 'warning', 'text' => 'Chimie : taux abandon élevé sur chapitre 3'],
            ['type' => 'info', 'text' => 'Pic d\'activité: mardi et jeudi entre 18h-20h'],
        ];
    }

    public function getUsersOverview(): array
    {
        return [
            'stats' => [
                'total' => 1234,
                'active' => 892,
                'inactive' => 287,
                'pending' => 55,
            ],
            'filters' => [
                ['label' => 'Tous', 'value' => 'all', 'active' => true],
                ['label' => 'Actifs', 'value' => 'active', 'active' => false],
                ['label' => 'Inactifs', 'value' => 'inactive', 'active' => false],
            ],
            'users' => [
                ['id' => 1, 'name' => 'Alice Martin', 'email' => 'alice@example.com', 'initials' => 'AM', 'role' => 'Étudiant', 'status' => 'Actif', 'joined' => '2024-01-15', 'last_activity' => 'Il y a 5 min'],
                ['id' => 2, 'name' => 'Bob Dupont', 'email' => 'bob@example.com', 'initials' => 'BD', 'role' => 'Étudiant', 'status' => 'Actif', 'joined' => '2024-02-20', 'last_activity' => 'Il y a 12 min'],
                ['id' => 3, 'name' => 'Claire Leroux', 'email' => 'claire@example.com', 'initials' => 'CL', 'role' => 'Professeur', 'status' => 'Actif', 'joined' => '2023-11-10', 'last_activity' => 'Il y a 1h'],
                ['id' => 4, 'name' => 'David Chen', 'email' => 'david@example.com', 'initials' => 'DC', 'role' => 'Étudiant', 'status' => 'Inactif', 'joined' => '2023-09-05', 'last_activity' => 'Il y a 3j'],
                ['id' => 5, 'name' => 'Emma Wilson', 'email' => 'emma@example.com', 'initials' => 'EW', 'role' => 'Étudiant', 'status' => 'Actif', 'joined' => '2024-01-28', 'last_activity' => 'Il y a 2h'],
            ],
        ];
    }

    public function getContentOverview(): array
    {
        return [
            'stats' => [
                'subjects' => 24,
                'chapters' => 156,
                'quizzes' => 342,
                'flashcards' => 1847,
            ],
            'tabs' => [
                ['label' => 'Matières', 'value' => 'subjects', 'active' => true],
                ['label' => 'Chapitres', 'value' => 'chapters', 'active' => false],
                ['label' => 'Quiz', 'value' => 'quizzes', 'active' => false],
                ['label' => 'Flashcards', 'value' => 'flashcards', 'active' => false],
            ],
            'recent_content' => [
                ['type' => 'Subject', 'title' => 'Mathématiques Avancées', 'author' => 'Prof. Martin', 'date' => '2024-02-28'],
                ['type' => 'Quiz', 'title' => 'Équations du 2nd degré', 'author' => 'Prof. Dupont', 'date' => '2024-02-27'],
                ['type' => 'Chapter', 'title' => 'Les forces en physique', 'author' => 'Prof. Chen', 'date' => '2024-02-26'],
            ],
        ];
    }

    public function getMentoringData(): array
    {
        return [
            'stats' => [
                'groups' => 48,
                'active_members' => 892,
                'posts_today' => 127,
                'avg_response_time' => '12 min',
            ],
            'active_groups' => [
                ['name' => 'Maths Terminale S', 'members' => 45, 'posts' => 234, 'last_activity' => 'Il y a 5 min'],
                ['name' => 'Physique Prépa', 'members' => 38, 'posts' => 189, 'last_activity' => 'Il y a 12 min'],
                ['name' => 'Chimie Organique', 'members' => 32, 'posts' => 156, 'last_activity' => 'Il y a 23 min'],
            ],
        ];
    }

    public function getTrainingOverview(): array
    {
        return [
            'stats' => [
                'quizzes_completed' => 2891,
                'avg_score' => 14.2,
                'flashcards_reviewed' => 15678,
                'study_time' => '1,234h',
            ],
            'popular_quizzes' => [
                ['title' => 'Équations différentielles', 'attempts' => 456, 'avg_score' => 15.3, 'difficulty' => 'HARD'],
                ['title' => 'Thermodynamique', 'attempts' => 398, 'avg_score' => 13.8, 'difficulty' => 'MEDIUM'],
                ['title' => 'Algèbre linéaire', 'attempts' => 367, 'avg_score' => 14.7, 'difficulty' => 'MEDIUM'],
            ],
            'popular_decks' => [
                ['title' => 'Vocabulaire Mathématiques', 'cards' => 120, 'reviews' => 2341],
                ['title' => 'Formules Physique', 'cards' => 95, 'reviews' => 1892],
                ['title' => 'Nomenclature Chimie', 'cards' => 87, 'reviews' => 1654],
            ],
        ];
    }
}
