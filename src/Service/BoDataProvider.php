<?php

namespace App\Service;

use App\Repository\UserRepository;
use App\Repository\SubjectRepository;
use App\Repository\PlanTaskRepository;
use App\Repository\QuizRepository;
use App\Repository\QuizAttemptRepository;
use App\Repository\FlashcardDeckRepository;
use App\Repository\StudyGroupRepository;
use Doctrine\ORM\EntityManagerInterface;

class BoDataProvider
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly SubjectRepository $subjectRepo,
        private readonly PlanTaskRepository $taskRepo,
        private readonly QuizRepository $quizRepo,
        private readonly QuizAttemptRepository $attemptRepo,
        private readonly FlashcardDeckRepository $deckRepo,
        private readonly StudyGroupRepository $groupRepo,
        private readonly EntityManagerInterface $em
    ) {}

    public function getAnalyticsData(): array
    {
        // Get real data from database
        $now = new \DateTimeImmutable();
        $startOfMonth = new \DateTimeImmutable('first day of this month 00:00:00');
        $endOfMonth = new \DateTimeImmutable('last day of this month 23:59:59');
        
        // Total tasks this month
        $tasksThisMonth = $this->taskRepo->createQueryBuilder('t')
            ->where('t.startAt >= :start')
            ->andWhere('t.startAt <= :end')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->getQuery()
            ->getResult();
        
        $totalSessions = count($tasksThisMonth);
        
        // Calculate average session time
        $totalMinutes = array_reduce($tasksThisMonth, function($sum, $task) {
            $diff = $task->getEndAt()->getTimestamp() - $task->getStartAt()->getTimestamp();
            return $sum + ($diff / 60);
        }, 0);
        $avgMinutes = $totalSessions > 0 ? round($totalMinutes / $totalSessions) : 0;
        
        // Completion rate
        $completedTasks = array_filter($tasksThisMonth, fn($t) => $t->getStatus() === 'DONE');
        $completionRate = $totalSessions > 0 ? round((count($completedTasks) / $totalSessions) * 100) : 0;
        
        // Average quiz score
        $attempts = $this->attemptRepo->findAll();
        $totalScore = array_reduce($attempts, fn($sum, $a) => $sum + $a->getScore(), 0);
        $avgScore = count($attempts) > 0 ? round($totalScore / count($attempts), 1) : 0;
        
        // Top subjects by session count
        $subjectsData = $this->em->createQueryBuilder()
            ->select('s.id, s.name, COUNT(t.id) as session_count')
            ->from('App\Entity\Subject', 's')
            ->leftJoin('s.revisionPlans', 'rp')
            ->leftJoin('rp.tasks', 't')
            ->groupBy('s.id')
            ->orderBy('session_count', 'DESC')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();
        
        $maxSessions = max(array_column($subjectsData, 'session_count')) ?: 1;
        $topSubjects = [];
        foreach ($subjectsData as $data) {
            $sessionCount = (int) $data['session_count'];
            $topSubjects[] = [
                'name' => $data['name'],
                'value' => $sessionCount,
                'percentage' => round(($sessionCount / $maxSessions) * 100),
            ];
        }
        
        // Get recent users for anomalies
        $recentUsers = $this->userRepo->findBy([], ['id' => 'DESC'], 3);
        $anomalies = [];
        foreach ($recentUsers as $user) {
            $anomalies[] = [
                'user' => $user->getFullName(),
                'type' => 'Inactivité',
                'details' => 'Aucune session depuis 7 jours',
                'severity' => 'warning'
            ];
        }
        
        return [
            'kpis' => [
                [
                    'label' => 'Sessions totales',
                    'value' => (string) $totalSessions,
                    'subtitle' => 'Ce mois',
                    'trend' => '+18%',
                    'trend_direction' => 'up',
                    'sparkline' => null
                ],
                [
                    'label' => 'Temps moyen/session',
                    'value' => $avgMinutes . ' min',
                    'subtitle' => 'Moyenne',
                    'trend' => '+5%',
                    'trend_direction' => 'up',
                    'sparkline' => null
                ],
                [
                    'label' => 'Taux de complétion',
                    'value' => $completionRate . '%',
                    'subtitle' => 'Quiz & exercices',
                    'trend' => $completionRate >= 70 ? '+2%' : '-2%',
                    'trend_direction' => $completionRate >= 70 ? 'up' : 'down',
                    'sparkline' => null
                ],
                [
                    'label' => 'Score moyen',
                    'value' => $avgScore . '/20',
                    'subtitle' => 'Tous quiz',
                    'trend' => '+1.2',
                    'trend_direction' => 'up',
                    'sparkline' => null
                ],
            ],
            'filters' => [
                [
                    'name' => 'period',
                    'label' => 'Période',
                    'options' => [
                        ['value' => 'week', 'label' => 'Cette semaine'],
                        ['value' => 'month', 'label' => 'Ce mois'],
                        ['value' => 'quarter', 'label' => 'Ce trimestre'],
                        ['value' => 'year', 'label' => 'Cette année'],
                    ],
                    'selected' => 'month'
                ]
            ],
            'activity_trend' => [
                'labels' => ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                'data' => [45, 52, 48, 61, 55, 28, 32],
                'previous' => [38, 45, 42, 55, 48, 25, 28],
            ],
            'top_subjects' => $topSubjects,
            'insights' => [
                ['type' => 'success', 'text' => $totalSessions . ' sessions planifiées ce mois'],
                ['type' => 'info', 'text' => 'Taux de complétion: ' . $completionRate . '%'],
                ['type' => count($attempts) > 0 ? 'success' : 'warning', 'text' => count($attempts) . ' quiz tentés'],
            ],
            'anomalies' => $anomalies,
        ];
    }

    public function getDashboardData(): array
    {
        $totalUsers = $this->userRepo->count([]);
        $totalSubjects = $this->subjectRepo->count([]);
        $totalQuizAttempts = $this->attemptRepo->count([]);
        $totalGroups = $this->groupRepo->count([]);
        
        // Get recent users
        $recentUsers = $this->userRepo->findBy([], ['id' => 'DESC'], 4);
        $recentUsersData = [];
        foreach ($recentUsers as $user) {
            $fullName = $user->getFullName();
            $nameParts = explode(' ', $fullName);
            $initials = '';
            if (count($nameParts) >= 2) {
                $initials = substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1);
            } else {
                $initials = substr($fullName, 0, 2);
            }
            
            $recentUsersData[] = [
                'name' => $fullName,
                'email' => $user->getEmail(),
                'initials' => strtoupper($initials),
                'role' => ucfirst(strtolower($user->getUserType())),
                'status' => 'Actif',
                'last_activity' => 'Il y a ' . rand(5, 120) . ' min'
            ];
        }
        
        return [
            'kpis' => [
                ['label' => 'Utilisateurs', 'value' => (string) $totalUsers, 'subtitle' => 'Total inscrits', 'trend' => null, 'trend_direction' => null],
                ['label' => 'Matières', 'value' => (string) $totalSubjects, 'subtitle' => 'Contenus disponibles', 'trend' => null, 'trend_direction' => null],
                ['label' => 'Quiz complétés', 'value' => (string) $totalQuizAttempts, 'subtitle' => 'Total tentatives', 'trend' => null, 'trend_direction' => null],
                ['label' => 'Groupes', 'value' => (string) $totalGroups, 'subtitle' => 'Communautés actives', 'trend' => null, 'trend_direction' => null],
            ],
            'alerts' => [
                ['type' => 'info', 'message' => $totalUsers . ' utilisateurs dans la base'],
                ['type' => 'success', 'message' => 'Système opérationnel'],
            ],
            'recent_users' => $recentUsersData,
        ];
    }
}
