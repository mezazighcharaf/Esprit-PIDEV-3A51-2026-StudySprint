<?php

namespace App\Controller;

use App\Service\BoDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\CsvExportService;
use App\Repository\QuizAttemptRepository;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly BoDataProvider $dataProvider,
    ) {}

    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('bo/dashboard.html.twig', [
            'state' => 'default',
            'data' => $this->dataProvider->getDashboardData(),
        ]);
    }

    #[Route('/analytics', name: 'analytics', methods: ['GET'])]
    public function analytics(Request $request): Response
    {
        $period = $request->query->get('period', 'month');
        if (!in_array($period, ['week', 'month', 'quarter'])) $period = 'month';
        return $this->render('bo/analytics.html.twig', [
            'state' => 'default',
            'data'  => $this->dataProvider->getAnalyticsData($period),
        ]);
    }

    #[Route('/users', name: 'users', methods: ['GET'])]
    public function users(Request $request): Response
    {
        $data = $this->dataProvider->getUsersOverviewReal(
            page:    max(1, $request->query->getInt('page', 1)),
            perPage: 20,
            q:       $request->query->get('q', ''),
            sort:    $request->query->get('sort', 'id'),
            dir:     $request->query->get('dir', 'DESC'),
        );
        $state = empty($data['users']) ? 'empty' : 'default';
        return $this->render('bo/users.html.twig', ['state' => $state, 'data' => $data]);
    }

    #[Route('/content', name: 'content', methods: ['GET'])]
    public function content(Request $request): Response
    {
        $q    = $request->query->get('q', '');
        $type = $request->query->get('type', '');
        $data = $this->dataProvider->getContentOverviewReal($q, $type);
        $state = empty($data['subjects']) ? 'empty' : 'default';
        return $this->render('bo/content.html.twig', ['state' => $state, 'data' => $data, 'q' => $q, 'type' => $type]);
    }

    #[Route('/mentoring', name: 'mentoring', methods: ['GET'])]
    public function mentoring(Request $request): Response
    {
        $q       = $request->query->get('q', '');
        $privacy = $request->query->get('privacy', '');
        $data = $this->dataProvider->getMentoringOverviewReal($q, $privacy);
        $state = empty($data['groups']) ? 'empty' : 'default';
        return $this->render('bo/mentoring.html.twig', ['state' => $state, 'data' => $data, 'q' => $q, 'privacy' => $privacy]);
    }

    #[Route('/training', name: 'training', methods: ['GET'])]
    public function training(Request $request): Response
    {
        $period = $request->query->get('period', 'month');
        if (!in_array($period, ['week', 'month', 'quarter'])) $period = 'month';
        $q = $request->query->get('q', '');
        $data = $this->dataProvider->getTrainingOverviewReal($period, $q);
        $state = empty($data['recent_attempts']) ? 'empty' : 'default';
        return $this->render('bo/training.html.twig', ['state' => $state, 'data' => $data, 'period' => $period, 'q' => $q]);
    }

    #[Route('/analytics/export', name: 'analytics_export', methods: ['GET'])]
    public function analyticsExport(QuizAttemptRepository $attemptRepo, CsvExportService $csv): Response
    {
        $attempts = $attemptRepo->findBy([], ['id' => 'DESC']);

        $rows = array_map(fn($a) => [
            $a->getId(),
            $a->getUser()?->getFullName() ?? '-',
            $a->getQuiz()?->getTitle() ?? '-',
            $a->getScore(),
            $a->getStartedAt()?->format('d/m/Y H:i'),
            $a->getCompletedAt()?->format('d/m/Y H:i') ?? 'En cours',
        ], $attempts);

        return $csv->export(
            'analytics_export_' . date('Y-m-d') . '.csv',
            ['ID', 'Utilisateur', 'Quiz', 'Score (%)', 'Début', 'Fin'],
            $rows
        );
    }

    #[Route('/components', name: 'components', methods: ['GET'])]
    public function components(): Response
    {
        return $this->render('bo/components.html.twig');
    }
}
