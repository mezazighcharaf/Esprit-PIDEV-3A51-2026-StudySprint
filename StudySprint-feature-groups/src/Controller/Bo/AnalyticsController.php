<?php

namespace App\Controller\Bo;

use App\Service\Mock\BoMockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AnalyticsController extends AbstractController
{
    public function __construct(
        private BoMockDataProvider $mockProvider,
        private \App\Repository\UserRepository $userRepository
    ) {}

    #[Route('/admin/analytics', name: 'admin_analytics')]
    public function index(Request $request): Response
    {
        $state = $request->query->get('state', 'default');

        // Fetch real statistics
        $registrationStats = $this->userRepository->countByRegistrationYear();
        $ageStatsRaw = $this->userRepository->countStudentsByAgeRange();
        $countryStats = $this->userRepository->countUsersByCountry();
        $profExperienceStats = $this->userRepository->countProfessorExperience();
        $userKpis = $this->userRepository->getUsersKpiData();

        // Process Age Distribution by Sex for Chart.js grouped bars
        $ageLabels = ['Moins de 18', '18-25', '26-35', 'Plus de 35'];
        $maleData = array_fill(0, 4, 0);
        $femaleData = array_fill(0, 4, 0);

        foreach ($ageStatsRaw as $row) {
            $labelIndex = array_search($row['ageRange'], $ageLabels);
            if ($labelIndex !== false) {
                if (($row['sex'] ?? 'H') === 'H') {
                    $maleData[$labelIndex] = (int) $row['count'];
                } elseif (($row['sex'] ?? '') === 'F') {
                    $femaleData[$labelIndex] = (int) $row['count'];
                }
            }
        }

        $viewModel = [
            'state' => $state,
            'data' => $this->mockProvider->getAnalyticsData(),
            'stats' => [
                'registrations' => $registrationStats,
                'age_distribution' => [
                    'labels' => $ageLabels,
                    'datasets' => [
                        ['label' => 'Hommes', 'data' => $maleData],
                        ['label' => 'Femmes', 'data' => $femaleData],
                    ]
                ],
                'countries' => $countryStats,
                'professor_experience' => $profExperienceStats,
                'professor_hierarchy' => $this->userRepository->countProfessorsByCountryAndEstablishment(),
                'student_hierarchy' => $this->userRepository->countStudentsByCountryAndEstablishment(),
                'user_kpis' => $userKpis,
            ]
        ];

        return $this->render('bo/analytics.html.twig', $viewModel);
    }
}
