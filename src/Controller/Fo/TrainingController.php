<?php

namespace App\Controller\Fo;

use App\Service\Mock\FoMockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TrainingController extends AbstractController
{
    public function __construct(
        private FoMockDataProvider $mockProvider
    ) {}

    #[Route('/app/training', name: 'app_training')]
    public function index(Request $request): Response
    {
        $state = $request->query->get('state', 'default');

        $viewModel = [
            'state' => $state,
            'data' => $this->mockProvider->getTrainingData(),
        ];

        return $this->render('fo/training.html.twig', $viewModel);
    }
}
