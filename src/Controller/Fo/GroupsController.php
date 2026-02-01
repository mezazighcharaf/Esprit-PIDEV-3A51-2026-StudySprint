<?php

namespace App\Controller\Fo;

use App\Service\Mock\FoMockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GroupsController extends AbstractController
{
    public function __construct(
        private FoMockDataProvider $mockProvider
    ) {}

    #[Route('/app/groupes', name: 'app_groups')]
    public function index(Request $request): Response
    {
        $state = $request->query->get('state', 'default');

        $viewModel = [
            'state' => $state,
            'data' => $this->mockProvider->getGroupsData(),
        ];

        return $this->render('fo/groups.html.twig', $viewModel);
    }
}
