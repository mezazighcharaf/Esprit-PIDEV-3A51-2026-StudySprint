<?php

namespace App\Service\Mock;

/**
 * FoMockDataProvider
 *
 * Source unique de donnees mock pour le Front-Office.
 * Les Controllers recuperent un "view model" (array structure) depuis ce provider.
 */
class FoMockDataProvider
{
    /**
     * Donnees pour le Dashboard FO
     */
    public function getDashboardData(): array
    {
        return [
            'user' => $this->getCurrentUser(),
            'priority' => [
                'subject' => 'Mathematiques',
                'chapter' => 'Chapitre 5 - Integrales',
                'progress' => 65,
                'time_remaining' => '2h30',
                'deadline' => 'Demain, 14h00',
            ],
            'kpis' => [
                [
                    'label' => 'Sessions cette semaine',
                    'value' => '12',
                    'trend' => '+3',
                    'trend_direction' => 'up',
                    'period' => 'vs semaine derniere',
                ],
                [
                    'label' => 'Temps total',
                    'value' => '8h45',
                    'trend' => '+1h20',
                    'trend_direction' => 'up',
                    'period' => 'vs semaine derniere',
                ],
                [
                    'label' => 'Quiz reussis',
                    'value' => '24/28',
                    'trend' => '86%',
                    'trend_direction' => 'up',
                    'period' => 'taux de reussite',
                ],
                [
                    'label' => 'Objectif hebdo',
                    'value' => '75%',
                    'trend' => null,
                    'trend_direction' => null,
                    'period' => '15h / 20h',
                ],
            ],
            'todos' => $this->getTodos(),
            'activities' => $this->getRecentActivities(),
        ];
    }

    /**
     * Donnees pour la page Planning
     */
    public function getPlanningData(): array
    {
        return [
            'user' => $this->getCurrentUser(),
            'current_month' => 'Janvier 2026',
            'calendar_days' => $this->getCalendarDays(),
            'sessions' => $this->getUpcomingSessions(),
            'stats' => [
                'sessions_this_week' => 8,
                'total_hours' => '12h30',
                'completion_rate' => 78,
            ],
        ];
    }

    /**
     * Donnees pour la page Training
     */
    public function getTrainingData(): array
    {
        return [
            'user' => $this->getCurrentUser(),
            'decks' => $this->getDecks(),
            'quizzes' => $this->getQuizzes(),
            'ai_generation' => [
                'available_credits' => 15,
                'used_this_month' => 35,
                'suggestions' => [
                    'Reviser les derivees partielles',
                    'Quiz sur les theoremes de convergence',
                    'Flashcards vocabulaire anglais B2',
                ],
            ],
            'history' => $this->getTrainingHistory(),
        ];
    }

    /**
     * Donnees pour la page Groupes
     */
    public function getGroupsData(): array
    {
        return [
            'user' => $this->getCurrentUser(),
            'groups' => $this->getGroups(),
            'invitations' => $this->getInvitations(),
            'feedbacks' => $this->getFeedbacks(),
        ];
    }

    // ─── PRIVATE HELPERS ───

    private function getCurrentUser(): array
    {
        return [
            'id' => 1,
            'name' => 'Marie Dupont',
            'email' => 'marie.dupont@email.com',
            'initials' => 'MD',
            'role' => 'Etudiante',
            'avatar' => null,
        ];
    }

    private function getTodos(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Terminer le chapitre 5 de Mathematiques',
                'subject' => 'Mathematiques',
                'due' => 'Aujourd\'hui',
                'completed' => false,
                'priority' => 'high',
            ],
            [
                'id' => 2,
                'title' => 'Reviser les flashcards Anglais',
                'subject' => 'Anglais',
                'due' => 'Demain',
                'completed' => false,
                'priority' => 'medium',
            ],
            [
                'id' => 3,
                'title' => 'Preparer le quiz de Physique',
                'subject' => 'Physique',
                'due' => 'Vendredi',
                'completed' => true,
                'priority' => 'low',
            ],
            [
                'id' => 4,
                'title' => 'Lire le chapitre 3 de Chimie',
                'subject' => 'Chimie',
                'due' => 'Samedi',
                'completed' => false,
                'priority' => 'medium',
            ],
        ];
    }

    private function getRecentActivities(): array
    {
        return [
            [
                'type' => 'quiz',
                'title' => 'Quiz Mathematiques - Integrales',
                'description' => 'Score: 18/20',
                'time' => 'Il y a 2 heures',
            ],
            [
                'type' => 'session',
                'title' => 'Session de revision',
                'description' => 'Physique - Cinematique (45 min)',
                'time' => 'Il y a 5 heures',
            ],
            [
                'type' => 'group',
                'title' => 'Groupe "Prepa MPSI"',
                'description' => 'Nouveau message de Thomas',
                'time' => 'Hier',
            ],
            [
                'type' => 'session',
                'title' => 'Flashcards terminees',
                'description' => 'Anglais - Vocabulaire B2 (32 cartes)',
                'time' => 'Hier',
            ],
        ];
    }

    private function getCalendarDays(): array
    {
        $days = [];
        // Jours du mois precedent (fin decembre)
        for ($i = 30; $i <= 31; $i++) {
            $days[] = ['day' => $i, 'current_month' => false, 'today' => false, 'has_event' => false];
        }
        // Jours du mois courant (janvier)
        for ($i = 1; $i <= 31; $i++) {
            $days[] = [
                'day' => $i,
                'current_month' => true,
                'today' => $i === 24,
                'has_event' => in_array($i, [3, 7, 10, 14, 18, 21, 24, 28]),
            ];
        }
        // Jours du mois suivant (debut fevrier)
        for ($i = 1; $i <= 8; $i++) {
            $days[] = ['day' => $i, 'current_month' => false, 'today' => false, 'has_event' => false];
        }
        return $days;
    }

    private function getUpcomingSessions(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Revision Mathematiques',
                'subject' => 'Mathematiques',
                'chapter' => 'Integrales',
                'date' => '2026-01-24',
                'time' => '14:00',
                'duration' => '1h30',
                'type' => 'revision',
            ],
            [
                'id' => 2,
                'title' => 'Quiz Physique',
                'subject' => 'Physique',
                'chapter' => 'Cinematique',
                'date' => '2026-01-25',
                'time' => '10:00',
                'duration' => '45min',
                'type' => 'quiz',
            ],
            [
                'id' => 3,
                'title' => 'Groupe d\'etude',
                'subject' => 'Chimie',
                'chapter' => 'Reactions acido-basiques',
                'date' => '2026-01-26',
                'time' => '16:00',
                'duration' => '2h',
                'type' => 'group',
            ],
        ];
    }

    private function getDecks(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Derivees et primitives',
                'subject' => 'Mathematiques',
                'cards_count' => 48,
                'cards_mastered' => 35,
                'last_review' => 'Hier',
                'next_review' => 'Aujourd\'hui',
            ],
            [
                'id' => 2,
                'title' => 'Vocabulaire B2',
                'subject' => 'Anglais',
                'cards_count' => 120,
                'cards_mastered' => 89,
                'last_review' => 'Il y a 2 jours',
                'next_review' => 'Demain',
            ],
            [
                'id' => 3,
                'title' => 'Formules cinematique',
                'subject' => 'Physique',
                'cards_count' => 32,
                'cards_mastered' => 28,
                'last_review' => 'Aujourd\'hui',
                'next_review' => 'Dans 3 jours',
            ],
            [
                'id' => 4,
                'title' => 'Tableau periodique',
                'subject' => 'Chimie',
                'cards_count' => 56,
                'cards_mastered' => 24,
                'last_review' => 'Il y a 5 jours',
                'next_review' => 'Aujourd\'hui',
            ],
        ];
    }

    private function getQuizzes(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Integrales - Niveau 2',
                'subject' => 'Mathematiques',
                'questions_count' => 20,
                'best_score' => 85,
                'attempts' => 3,
            ],
            [
                'id' => 2,
                'title' => 'Cinematique generale',
                'subject' => 'Physique',
                'questions_count' => 15,
                'best_score' => 92,
                'attempts' => 2,
            ],
        ];
    }

    private function getTrainingHistory(): array
    {
        return [
            [
                'type' => 'quiz',
                'title' => 'Quiz Mathematiques - Integrales',
                'score' => '18/20',
                'date' => 'Aujourd\'hui, 14:30',
            ],
            [
                'type' => 'deck',
                'title' => 'Flashcards Anglais B2',
                'score' => '32 cartes revisees',
                'date' => 'Hier, 16:00',
            ],
            [
                'type' => 'quiz',
                'title' => 'Quiz Physique - Cinematique',
                'score' => '14/15',
                'date' => 'Hier, 10:00',
            ],
        ];
    }

    private function getGroups(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Prepa MPSI - Maths',
                'description' => 'Groupe de travail mathematiques',
                'members_count' => 8,
                'role' => 'Membre',
                'last_activity' => 'Il y a 2 heures',
                'initials' => 'PM',
            ],
            [
                'id' => 2,
                'name' => 'Physique Avancee',
                'description' => 'Preparation aux concours',
                'members_count' => 12,
                'role' => 'Admin',
                'last_activity' => 'Hier',
                'initials' => 'PA',
            ],
            [
                'id' => 3,
                'name' => 'Anglais Conversation',
                'description' => 'Pratique orale et vocabulaire',
                'members_count' => 6,
                'role' => 'Membre',
                'last_activity' => 'Il y a 3 jours',
                'initials' => 'AC',
            ],
        ];
    }

    private function getInvitations(): array
    {
        return [
            [
                'id' => 1,
                'group_name' => 'Chimie Organique',
                'invited_by' => 'Sophie Martin',
                'date' => 'Il y a 1 jour',
            ],
        ];
    }

    private function getFeedbacks(): array
    {
        return [
            [
                'id' => 1,
                'from' => 'Thomas Bernard',
                'group' => 'Prepa MPSI - Maths',
                'message' => 'Excellente explication sur les integrales !',
                'date' => 'Hier',
                'rating' => 5,
            ],
            [
                'id' => 2,
                'from' => 'Julie Leroy',
                'group' => 'Physique Avancee',
                'message' => 'Merci pour le partage des ressources.',
                'date' => 'Il y a 3 jours',
                'rating' => 4,
            ],
        ];
    }
}
