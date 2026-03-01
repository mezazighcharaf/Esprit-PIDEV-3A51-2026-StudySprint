<?php

namespace App\Controller\Fo;

use App\Service\Mock\FoMockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PlanningController extends AbstractController
{
    public function __construct(
        private FoMockDataProvider $mockProvider
    ) {}

    #[Route('/app/planning', name: 'app_planning')]
    public function index(Request $request): Response
    {
        $state = $request->query->get('state', 'default');

        $viewModel = [
            'state' => $state,
            'data' => $this->mockProvider->getPlanningData(),
        ];

        return $this->render('fo/planning.html.twig', $viewModel);
    }
}
