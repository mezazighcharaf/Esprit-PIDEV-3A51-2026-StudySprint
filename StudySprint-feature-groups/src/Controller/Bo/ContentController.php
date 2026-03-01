<?php

namespace App\Controller\Bo;

use App\Service\Mock\BoMockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContentController extends AbstractController
{
    public function __construct(
        private BoMockDataProvider $mockProvider
    ) {}

    #[Route('/admin/contenu', name: 'admin_content')]
    public function index(Request $request): Response
    {
        $state = $request->query->get('state', 'default');

        $viewModel = [
            'state' => $state,
            'data' => $this->mockProvider->getContentData(),
        ];

        return $this->render('bo/content.html.twig', $viewModel);
    }
}
