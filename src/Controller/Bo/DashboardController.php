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
        
        // Fetch real users
        $users = $this->userRepository->findAll();
        
        // Mocking KPI mock data with real data count if possible, or mixing
        $mockData = $this->mockProvider->getDashboardData();
        
        // Let's replace the 'recent_users' in mock data with real users for the view
        // OR better: pass users separately
        
        $viewModel = [
            'state' => $state,
            'data' => $mockData,
            'users' => $users, // Passing real users
        ];

        return $this->render('bo/dashboard.html.twig', $viewModel);
    }
}
