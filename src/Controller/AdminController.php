<?php

namespace App\Controller;

use App\Service\BoDataProvider;
use App\Service\BoMockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\CsvExportService;
use App\Repository\QuizAttemptRepository;

#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly BoDataProvider $dataProvider,
        private readonly BoMockDataProvider $mockProvider
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
    public function analytics(): Response
    {
        return $this->render('bo/analytics.html.twig', [
            'state' => 'default',
            'data' => $this->dataProvider->getAnalyticsData(),
        ]);
    }

    #[Route('/users', name: 'users', methods: ['GET'])]
    public function users(): Response
    {
        return $this->render('bo/users.html.twig', [
            'state' => 'default',
            'data' => $this->mockProvider->getUsersOverview(),
        ]);
    }

    #[Route('/content', name: 'content', methods: ['GET'])]
    public function content(): Response
    {
        return $this->render('bo/content.html.twig', [
            'state' => 'default',
            'data' => $this->mockProvider->getContentOverview(),
        ]);
    }

    #[Route('/mentoring', name: 'mentoring', methods: ['GET'])]
    public function mentoring(): Response
    {
        return $this->render('bo/mentoring.html.twig', [
            'state' => 'default',
            'data' => $this->mockProvider->getMentoringData(),
        ]);
    }

    #[Route('/training', name: 'training', methods: ['GET'])]
    public function training(): Response
    {
        return $this->render('bo/training.html.twig', [
            'state' => 'default',
            'data' => $this->mockProvider->getTrainingOverview(),
        ]);
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
            $a->getFinishedAt()?->format('d/m/Y H:i') ?? 'En cours',
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
