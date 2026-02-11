<?php

namespace App\Controller\Bo;

use App\Repository\UserRepository;
use App\Service\Mock\BoMockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    public function __construct(
        private BoMockDataProvider $mockProvider,
        private UserRepository $userRepository
    ) {}

    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function index(Request $request): Response
    {
        $state = $request->query->get('state', 'default');
        
        // Fetch real statistics
        $userKpis = $this->userRepository->getUsersKpiData();
        $recentUsers = $this->userRepository->findRecentUsers(5); // Show last 5
        
        $mockData = $this->mockProvider->getDashboardData();
        
        $viewModel = [
            'state' => $state,
            'data' => $mockData,
            'recent_users' => $recentUsers,
            'user_kpis' => $userKpis,
        ];

        return $this->render('bo/dashboard.html.twig', $viewModel);
    }
}
