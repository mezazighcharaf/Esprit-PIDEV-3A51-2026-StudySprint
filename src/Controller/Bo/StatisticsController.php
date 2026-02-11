<?php

namespace App\Controller\Bo;

use App\Repository\UserRepository;
use App\Service\Mock\BoMockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class StatisticsController extends AbstractController
{
    public function __construct(
        private BoMockDataProvider $mockProvider,
        private UserRepository $userRepository
    ) {}

    #[Route('/admin/statistiques', name: 'admin_statistics')]
    public function index(): Response
    {
        // Real Data
        $userKpis = $this->userRepository->getUsersKpiData();
        $regTrends = $this->userRepository->countByRegistrationYear();
        $ageRanges = $this->userRepository->countStudentsByAgeRange();
        $countryStats = $this->userRepository->countUsersByCountry();
        $profExperience = $this->userRepository->countProfessorExperience();

        // Mock Data for advanced analytics
        $analyticsData = $this->mockProvider->getAnalyticsData();

        return $this->render('bo/statistics.html.twig', [
            'user_kpis' => $userKpis,
            'registration_trends' => $regTrends,
            'age_ranges' => $ageRanges,
            'countries' => $countryStats,
            'professor_experience' => $profExperience,
            'analytics' => $analyticsData,
        ]);
    }
}
