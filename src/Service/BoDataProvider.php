<?php

namespace App\Service;

use App\Repository\UserRepository;
use App\Repository\SubjectRepository;
use App\Repository\ChapterRepository;
use App\Repository\PlanTaskRepository;
use App\Repository\QuizRepository;
use App\Repository\QuizAttemptRepository;
use App\Repository\FlashcardDeckRepository;
use App\Repository\FlashcardReviewStateRepository;
use App\Repository\StudyGroupRepository;
use App\Repository\ActivityLogRepository;
use App\Repository\AiGenerationLogRepository;
use Doctrine\ORM\EntityManagerInterface;

class BoDataProvider
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly SubjectRepository $subjectRepo,
        private readonly ChapterRepository $chapterRepo,
        private readonly PlanTaskRepository $taskRepo,
        private readonly QuizRepository $quizRepo,
        private readonly QuizAttemptRepository $attemptRepo,
        private readonly FlashcardDeckRepository $deckRepo,
        private readonly FlashcardReviewStateRepository $reviewStateRepo,
        private readonly StudyGroupRepository $groupRepo,
        private readonly ActivityLogRepository $activityLogRepo,
        private readonly AiGenerationLogRepository $aiLogRepo,
        private readonly EntityManagerInterface $em
    ) {}

    // ─────────────────────────────────────────────
    // DASHBOARD
    // ─────────────────────────────────────────────

    public function getDashboardData(): array
    {
        $totalUsers    = $this->userRepo->count([]);
        $totalSubjects = $this->subjectRepo->count([]);
        $totalAttempts = $this->attemptRepo->count([]);
        $totalGroups   = $this->groupRepo->count([]);

        // New users this month
        $startOfMonth = new \DateTimeImmutable('first day of this month 00:00:00');
        $newUsersThisMonth = (int) $this->userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.dateInscription >= :start')
            ->setParameter('start', $startOfMonth)
            ->getQuery()->getSingleScalarResult();

        // Recent users (real data)
        $recentUsers = $this->userRepo->findBy([], ['id' => 'DESC'], 5);
        $recentUsersData = array_map(function ($u) {
            return [
                'id'            => $u->getId(),
                'name'          => $u->getFullName(),
                'email'         => $u->getEmail(),
                'initials'      => $u->getInitials(),
                'role'          => ucfirst(strtolower($u->getRole() ?? 'user')),
                'created_at'    => $u->getDateInscription() ? $u->getDateInscription()->format('d/m/Y') : '—',
            ];
        }, $recentUsers);

        // Activity last 7 days (from ActivityLog)
        $activityData = $this->buildActivityTrend7Days();

        // AI health (from AiGenerationLog)
        $totalAiLogs   = $this->aiLogRepo->count([]);
        $successAiLogs = (int) $this->aiLogRepo->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.status = :s')->setParameter('s', 'success')
            ->getQuery()->getSingleScalarResult();
        $aiSuccessRate = $totalAiLogs > 0 ? round(($successAiLogs / $totalAiLogs) * 100) : 0;
        $avgLatency = (float) ($this->aiLogRepo->createQueryBuilder('l')
            ->select('AVG(l.latencyMs)')
            ->where('l.latencyMs IS NOT NULL')
            ->getQuery()->getSingleScalarResult() ?? 0);

        return [
            'kpis' => [
                ['label' => 'Utilisateurs',     'value' => (string) $totalUsers,    'subtitle' => '+' . $newUsersThisMonth . ' ce mois',        'trend' => null, 'trend_direction' => null, 'icon' => 'users'],
                ['label' => 'Matières',          'value' => (string) $totalSubjects, 'subtitle' => 'Contenus disponibles',                       'trend' => null, 'trend_direction' => null, 'icon' => 'book'],
                ['label' => 'Quiz complétés',    'value' => (string) $totalAttempts, 'subtitle' => 'Total tentatives',                           'trend' => null, 'trend_direction' => null, 'icon' => 'check'],
                ['label' => 'Groupes actifs',    'value' => (string) $totalGroups,   'subtitle' => 'Communautés',                                'trend' => null, 'trend_direction' => null, 'icon' => 'group'],
            ],
            'alerts' => [
                ['type' => 'info',    'message' => $totalUsers . ' utilisateurs enregistrés'],
                ['type' => 'success', 'message' => 'IA : ' . $aiSuccessRate . '% de succès — latence moy. ' . round($avgLatency) . 'ms'],
            ],
            'recent_users'    => $recentUsersData,
            'activity_7days'  => $activityData,
            'ai_success_rate' => $aiSuccessRate,
            'ai_avg_latency'  => round($avgLatency),
        ];
    }

    // ─────────────────────────────────────────────
    // ANALYTICS
    // ─────────────────────────────────────────────

    public function getAnalyticsData(): array
    {
        $startOfMonth = new \DateTimeImmutable('first day of this month 00:00:00');
        $endOfMonth   = new \DateTimeImmutable('last day of this month 23:59:59');

        // Tasks stats via SQL
        $statsRow = $this->taskRepo->createQueryBuilder('t')
            ->select(
                'COUNT(t.id) as totalCount',
                'SUM(CASE WHEN t.status = :done THEN 1 ELSE 0 END) as completedCount',
                'AVG(TIMESTAMPDIFF(MINUTE, t.startAt, t.endAt)) as avgMinutes'
            )
            ->where('t.startAt >= :start')
            ->andWhere('t.startAt <= :end')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->setParameter('done', 'DONE')
            ->getQuery()->getOneOrNullResult();

        $totalSessions    = (int) ($statsRow['totalCount'] ?? 0);
        $avgMinutes       = (int) round((float) ($statsRow['avgMinutes'] ?? 0));
        $completedCount   = (int) ($statsRow['completedCount'] ?? 0);
        $completionRate   = $totalSessions > 0 ? round(($completedCount / $totalSessions) * 100) : 0;

        // Average quiz score via SQL
        $avgScoreRaw = $this->attemptRepo->createQueryBuilder('a')
            ->select('AVG(a.score)')
            ->where('a.completedAt IS NOT NULL')
            ->getQuery()->getSingleScalarResult();
        $avgScore = $avgScoreRaw ? round((float) $avgScoreRaw, 1) : 0;

        // Top subjects by session count
        $subjectsData = $this->em->createQueryBuilder()
            ->select('s.id, s.name, COUNT(t.id) as session_count')
            ->from('App\Entity\Subject', 's')
            ->leftJoin('s.revisionPlans', 'rp')
            ->leftJoin('rp.tasks', 't')
            ->groupBy('s.id, s.name')
            ->orderBy('session_count', 'DESC')
            ->setMaxResults(5)
            ->getQuery()->getResult();

        $maxSessions = (int) max(array_column($subjectsData, 'session_count') ?: [1]);
        $topSubjects = array_map(fn($d) => [
            'name'       => $d['name'],
            'value'      => (int) $d['session_count'],
            'percentage' => $maxSessions > 0 ? round(((int) $d['session_count'] / $maxSessions) * 100) : 0,
        ], $subjectsData);

        // Activity trend last 7 days (real data)
        $activityTrend = $this->buildActivityTrend7Days();

        return [
            'kpis' => [
                ['label' => 'Sessions ce mois',     'value' => (string) $totalSessions,  'subtitle' => 'Tâches planifiées',      'trend' => null, 'trend_direction' => null],
                ['label' => 'Durée moy/session',    'value' => $avgMinutes . ' min',      'subtitle' => 'Moyenne mensuelle',      'trend' => null, 'trend_direction' => null],
                ['label' => 'Taux de complétion',   'value' => $completionRate . '%',     'subtitle' => 'Tâches terminées',       'trend' => $completionRate >= 70 ? 'Bon' : 'À améliorer', 'trend_direction' => $completionRate >= 70 ? 'up' : 'down'],
                ['label' => 'Score moyen quiz',     'value' => $avgScore . '%',           'subtitle' => 'Tous quiz complétés',    'trend' => null, 'trend_direction' => null],
            ],
            'filters' => [
                ['name' => 'period', 'label' => 'Période', 'options' => [
                    ['value' => 'week',    'label' => 'Cette semaine'],
                    ['value' => 'month',   'label' => 'Ce mois'],
                    ['value' => 'quarter', 'label' => 'Ce trimestre'],
                ], 'selected' => 'month'],
            ],
            'activity_trend' => $activityTrend,
            'top_subjects'   => $topSubjects,
            'anomalies'      => [],
            'insights' => [
                ['type' => 'success', 'text' => $totalSessions . ' sessions planifiées ce mois'],
                ['type' => 'info',    'text' => 'Taux de complétion : ' . $completionRate . '%'],
                ['type' => $avgScore >= 50 ? 'success' : 'warning', 'text' => 'Score moyen quiz : ' . $avgScore . '%'],
            ],
        ];
    }

    // ─────────────────────────────────────────────
    // USERS OVERVIEW (vraies données)
    // ─────────────────────────────────────────────

    public function getUsersOverviewReal(int $page = 1, int $perPage = 20, string $q = '', string $sort = 'id', string $dir = 'DESC'): array
    {
        $allowedSort = ['id', 'email', 'role', 'dateInscription'];
        if (!in_array($sort, $allowedSort)) $sort = 'id';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->userRepo->createQueryBuilder('u');
        if ($q) {
            $qb->where('u.email LIKE :q OR u.nom LIKE :q OR u.prenom LIKE :q')->setParameter('q', "%$q%");
        }
        $qb->orderBy("u.$sort", $dir);

        $total = (int) (clone $qb)->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
        $users = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        // Count by role
        $byRole = $this->em->createQueryBuilder()
            ->select('u.role, COUNT(u.id) as cnt')
            ->from('App\Entity\User', 'u')
            ->groupBy('u.role')
            ->getQuery()->getResult();

        $typeCounts = ['student' => 0, 'teacher' => 0, 'admin' => 0];
        foreach ($byRole as $row) {
            $r = strtolower($row['role'] ?? '');
            if (str_contains($r, 'student')) $typeCounts['student'] += (int)$row['cnt'];
            elseif (str_contains($r, 'professor') || str_contains($r, 'teacher')) $typeCounts['teacher'] += (int)$row['cnt'];
            elseif (str_contains($r, 'admin')) $typeCounts['admin'] += (int)$row['cnt'];
        }

        // New users this month
        $newThisMonth = (int) $this->userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.dateInscription >= :start')
            ->setParameter('start', new \DateTimeImmutable('first day of this month 00:00:00'))
            ->getQuery()->getSingleScalarResult();

        // Registrations last 8 weeks for sparkline
        $weeklyRegistrations = [];
        for ($i = 7; $i >= 0; $i--) {
            $weekStart = new \DateTimeImmutable("-$i weeks monday this week 00:00:00");
            $weekEnd   = (clone $weekStart)->modify('+6 days 23:59:59');
            $count = (int) $this->userRepo->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.dateInscription >= :s')->andWhere('u.dateInscription <= :e')
                ->setParameter('s', $weekStart)->setParameter('e', $weekEnd)
                ->getQuery()->getSingleScalarResult();
            $weeklyRegistrations[] = ['label' => $weekStart->format('d/m'), 'count' => $count];
        }

        $usersData = array_map(function ($u) {
            return [
                'id'         => $u->getId(),
                'name'       => $u->getFullName(),
                'email'      => $u->getEmail(),
                'initials'   => $u->getInitials(),
                'type'       => $u->getRole(),
                'role'       => ucfirst(strtolower(str_replace('ROLE_', '', $u->getRole() ?? 'user'))),
                'created_at' => $u->getDateInscription() ? $u->getDateInscription()->format('d/m/Y') : '—',
                'is_admin'   => in_array('ROLE_ADMIN', $u->getRoles()),
            ];
        }, $users);

        return [
            'stats' => [
                'total'      => $total,
                'students'   => $typeCounts['student'],
                'teachers'   => $typeCounts['teacher'],
                'admins'     => $typeCounts['admin'],
                'new_month'  => $newThisMonth,
            ],
            'users'                 => $usersData,
            'total'                 => $total,
            'page'                  => $page,
            'total_pages'           => (int) ceil($total / $perPage),
            'weekly_registrations'  => $weeklyRegistrations,
            'q'                     => $q,
            'sort'                  => $sort,
            'dir'                   => $dir,
        ];
    }

    // ─────────────────────────────────────────────
    // CONTENT OVERVIEW (vraies données)
    // ─────────────────────────────────────────────

    public function getContentOverviewReal(): array
    {
        $totalSubjects = $this->subjectRepo->count([]);
        $totalChapters = $this->chapterRepo->count([]);
        $totalQuizzes  = $this->quizRepo->count([]);
        $publishedQuizzes = (int) $this->quizRepo->createQueryBuilder('q')
            ->select('COUNT(q.id)')->where('q.isPublished = :pub')->setParameter('pub', true)
            ->getQuery()->getSingleScalarResult();
        $totalDecks = $this->deckRepo->count([]);
        $publishedDecks = (int) $this->deckRepo->createQueryBuilder('d')
            ->select('COUNT(d.id)')->where('d.isPublished = :pub')->setParameter('pub', true)
            ->getQuery()->getSingleScalarResult();

        // Subjects with chapter counts
        $subjects = $this->em->createQueryBuilder()
            ->select('s.id, s.name, s.code, s.createdAt, COUNT(c.id) as chapterCount')
            ->from('App\Entity\Subject', 's')
            ->leftJoin('s.chapters', 'c')
            ->groupBy('s.id, s.name, s.code, s.createdAt')
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()->getResult();

        $subjectsData = array_map(fn($s) => [
            'id'           => $s['id'],
            'name'         => $s['name'],
            'code'         => $s['code'] ?? '—',
            'chapters'     => (int) $s['chapterCount'],
            'created_at'   => $s['createdAt']->format('d/m/Y'),
        ], $subjects);

        // Chapters with subject name
        $chapters = $this->em->createQueryBuilder()
            ->select('c.id, c.title, c.orderNo, c.createdAt, s.name as subjectName')
            ->from('App\Entity\Chapter', 'c')
            ->join('c.subject', 's')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()->getResult();

        $chaptersData = array_map(fn($c) => [
            'id'           => $c['id'],
            'title'        => $c['title'],
            'subject'      => $c['subjectName'],
            'order'        => $c['orderNo'],
            'created_at'   => $c['createdAt']->format('d/m/Y'),
        ], $chapters);

        // Recent quizzes
        $recentQuizzes = $this->em->createQueryBuilder()
            ->select('q.id, q.title, q.difficulty, q.isPublished, q.createdAt, s.name as subjectName')
            ->from('App\Entity\Quiz', 'q')
            ->leftJoin('q.subject', 's')
            ->orderBy('q.createdAt', 'DESC')
            ->setMaxResults(15)
            ->getQuery()->getResult();

        $quizzesData = array_map(fn($q) => [
            'id'         => $q['id'],
            'title'      => $q['title'],
            'subject'    => $q['subjectName'] ?? '—',
            'difficulty' => $q['difficulty'],
            'published'  => (bool) $q['isPublished'],
            'created_at' => $q['createdAt']->format('d/m/Y'),
        ], $recentQuizzes);

        return [
            'stats' => [
                'subjects'         => $totalSubjects,
                'chapters'         => $totalChapters,
                'quizzes'          => $totalQuizzes,
                'published_quizzes'=> $publishedQuizzes,
                'decks'            => $totalDecks,
                'published_decks'  => $publishedDecks,
            ],
            'subjects' => $subjectsData,
            'chapters' => $chaptersData,
            'quizzes'  => $quizzesData,
        ];
    }

    // ─────────────────────────────────────────────
    // TRAINING OVERVIEW (vraies données)
    // ─────────────────────────────────────────────

    public function getTrainingOverviewReal(): array
    {
        $startOfMonth = new \DateTimeImmutable('first day of this month 00:00:00');
        $startOfWeek  = new \DateTimeImmutable('monday this week 00:00:00');

        // Quiz attempts stats
        $totalAttempts = $this->attemptRepo->count([]);
        $monthAttempts = (int) $this->attemptRepo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.startedAt >= :start')->setParameter('start', $startOfMonth)
            ->getQuery()->getSingleScalarResult();
        $avgScore = (float) ($this->attemptRepo->createQueryBuilder('a')
            ->select('AVG(a.score)')
            ->where('a.completedAt IS NOT NULL')
            ->getQuery()->getSingleScalarResult() ?? 0);
        $passRate = (float) ($this->attemptRepo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.completedAt IS NOT NULL')->andWhere('a.score >= 50')
            ->getQuery()->getSingleScalarResult() ?? 0);
        $completedCount = (int) $this->attemptRepo->createQueryBuilder('a')
            ->select('COUNT(a.id)')->where('a.completedAt IS NOT NULL')
            ->getQuery()->getSingleScalarResult();
        $passRatePct = $completedCount > 0 ? round(($passRate / $completedCount) * 100) : 0;

        // Top 10 most played quizzes (via QuizAttempt, since Quiz has no inverse attempts relation)
        $topQuizzes = $this->em->createQueryBuilder()
            ->select('q.id, q.title, q.difficulty, COUNT(a.id) as attempts, AVG(a.score) as avgScore')
            ->from('App\Entity\QuizAttempt', 'a')
            ->join('a.quiz', 'q')
            ->where('a.completedAt IS NOT NULL')
            ->groupBy('q.id, q.title, q.difficulty')
            ->orderBy('attempts', 'DESC')
            ->setMaxResults(10)
            ->getQuery()->getResult();

        $topQuizzesData = array_map(fn($q) => [
            'id'         => $q['id'],
            'title'      => $q['title'],
            'difficulty' => $q['difficulty'],
            'attempts'   => (int) $q['attempts'],
            'avg_score'  => round((float) $q['avgScore'], 1),
        ], $topQuizzes);

        // Flashcard reviews this week
        $reviewsThisWeek = (int) $this->reviewStateRepo->createQueryBuilder('rs')
            ->select('COUNT(rs.id)')
            ->where('rs.lastReviewedAt >= :start')->setParameter('start', $startOfWeek)
            ->getQuery()->getSingleScalarResult();

        // Recent quiz attempts
        $recentAttempts = $this->attemptRepo->createQueryBuilder('a')
            ->leftJoin('a.quiz', 'q')->addSelect('q')
            ->leftJoin('a.user', 'u')->addSelect('u')
            ->where('a.completedAt IS NOT NULL')
            ->orderBy('a.completedAt', 'DESC')
            ->setMaxResults(15)
            ->getQuery()->getResult();

        $attemptsData = array_map(fn($a) => [
            'user'       => $a->getUser()->getFullName(),
            'quiz'       => $a->getQuiz()->getTitle(),
            'score'      => round((float) $a->getScore(), 1),
            'passed'     => $a->getScore() >= 50,
            'duration'   => $a->getDurationSeconds() ? round($a->getDurationSeconds() / 60) . ' min' : '—',
            'date'       => $a->getCompletedAt()->format('d/m/Y H:i'),
        ], $recentAttempts);

        return [
            'stats' => [
                'total_attempts'   => $totalAttempts,
                'month_attempts'   => $monthAttempts,
                'avg_score'        => round($avgScore, 1),
                'pass_rate'        => $passRatePct,
                'reviews_week'     => $reviewsThisWeek,
            ],
            'top_quizzes'     => $topQuizzesData,
            'recent_attempts' => $attemptsData,
        ];
    }

    // ─────────────────────────────────────────────
    // MENTORING OVERVIEW (vraies données)
    // ─────────────────────────────────────────────

    public function getMentoringOverviewReal(): array
    {
        $totalGroups = $this->groupRepo->count([]);
        $publicGroups = (int) $this->groupRepo->createQueryBuilder('g')
            ->select('COUNT(g.id)')->where('g.privacy = :pub')->setParameter('pub', 'PUBLIC')
            ->getQuery()->getSingleScalarResult();

        // Groups with member & post counts
        $groups = $this->em->createQueryBuilder()
            ->select('g.id, g.name, g.privacy, g.createdAt, COUNT(DISTINCT m.id) as memberCount, COUNT(DISTINCT p.id) as postCount')
            ->from('App\Entity\StudyGroup', 'g')
            ->leftJoin('g.members', 'm')
            ->leftJoin('g.posts', 'p')
            ->groupBy('g.id, g.name, g.privacy, g.createdAt')
            ->orderBy('memberCount', 'DESC')
            ->setMaxResults(20)
            ->getQuery()->getResult();

        $groupsData = array_map(fn($g) => [
            'id'          => $g['id'],
            'name'        => $g['name'],
            'privacy'     => $g['privacy'],
            'members'     => (int) $g['memberCount'],
            'posts'       => (int) $g['postCount'],
            'created_at'  => $g['createdAt']->format('d/m/Y'),
        ], $groups);

        // Recent posts
        $recentPosts = $this->em->createQueryBuilder()
            ->select('p.id, p.body, p.createdAt, g.name as groupName, CONCAT(u.prenom, \' \', u.nom) as authorName')
            ->from('App\Entity\GroupPost', 'p')
            ->join('p.group', 'g')
            ->join('p.author', 'u')
            ->where('p.parentPost IS NULL')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()->getResult();

        $postsData = array_map(fn($p) => [
            'id'         => $p['id'],
            'excerpt'    => mb_substr($p['body'], 0, 80) . (mb_strlen($p['body']) > 80 ? '…' : ''),
            'group'      => $p['groupName'],
            'author'     => $p['authorName'],
            'created_at' => $p['createdAt']->format('d/m/Y H:i'),
        ], $recentPosts);

        // Total posts & members
        $totalMembers = (int) $this->em->createQueryBuilder()
            ->select('COUNT(m.id)')->from('App\Entity\GroupMember', 'm')
            ->getQuery()->getSingleScalarResult();
        $totalPosts = (int) $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')->from('App\Entity\GroupPost', 'p')
            ->where('p.parentPost IS NULL')
            ->getQuery()->getSingleScalarResult();

        return [
            'stats' => [
                'total_groups'  => $totalGroups,
                'public_groups' => $publicGroups,
                'total_members' => $totalMembers,
                'total_posts'   => $totalPosts,
            ],
            'groups'       => $groupsData,
            'recent_posts' => $postsData,
        ];
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    /**
     * Builds the activity trend structure expected by analytics.html.twig:
     * { labels: ['Lun','Mar',...], data: [12,8,...], previous: [10,5,...] }
     */
    private function buildActivityTrend7Days(): array
    {
        $rows = $this->activityLogRepo->getDailyActivityForUser7Days();
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[$r['day']] = (int) $r['count'];
        }

        $labels   = [];
        $data     = [];
        $previous = [];
        $dayNames = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

        for ($i = 6; $i >= 0; $i--) {
            $day     = new \DateTimeImmutable("-$i days");
            $dayKey  = $day->format('Y-m-d');
            $prevKey = $day->modify('-7 days')->format('Y-m-d');
            $labels[]   = $dayNames[(int) $day->format('w')] . ' ' . $day->format('d/m');
            $data[]     = $byDay[$dayKey] ?? 0;
            $previous[] = $byDay[$prevKey] ?? 0;
        }

        return ['labels' => $labels, 'data' => $data, 'previous' => $previous];
    }
}
