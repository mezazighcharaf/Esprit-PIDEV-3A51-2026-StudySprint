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

        // Determine state based on data
        $data = $this->mockProvider->getGroupsData();
        if ($state === 'default' && empty($data['groups']) && empty($data['invitations'])) {
            $state = 'empty';
        }

        $viewModel = [
            'state' => $state,
            'data' => $data,
        ];

        return $this->render('fo/groups.html.twig', $viewModel);
    }

    #[Route('/app/groupes/{id}', name: 'app_group_detail')]
    public function detail(int $id): Response
    {
        $groupsData = $this->mockProvider->getGroupsData();
        
        // Find the group by ID
        $group = null;
        foreach ($groupsData['groups'] as $g) {
            if ($g['id'] === $id) {
                $group = $g;
                break;
            }
        }

        if (!$group) {
            throw $this->createNotFoundException('Groupe non trouvé');
        }

        // Mock posts data for the group
        $posts = [
            [
                'id' => 1,
                'author' => 'Jean Dupont',
                'author_initials' => 'JD',
                'author_role' => 'admin',
                'content' => 'Bonjour! J\'ai trouvé une super ressource pour les intégrales. Voici mon approche personnelle...',
                'created_at' => 'Il y a 2 heures',
                'likes' => 0,
                'rating' => 4,
                'comments_count' => 3,
            ],
            [
                'id' => 2,
                'author' => 'Marie Leroy',
                'author_initials' => 'ML',
                'author_role' => 'member',
                'content' => 'Des questions sur le chapitre 5? Posez vos questions ici!',
                'created_at' => 'Il y a 5 heures',
                'likes' => 5,
                'rating' => 5,
                'comments_count' => 8,
            ],
            [
                'id' => 3,
                'author' => 'Sophie Martin',
                'author_initials' => 'SM',
                'author_role' => 'member',
                'content' => 'Quelqu\'un peut expliquer la méthode de substitution pour les intégrales complexes?',
                'created_at' => 'Il y a 8 heures',
                'likes' => 2,
                'rating' => 3,
                'comments_count' => 5,
            ],
        ];

        $viewModel = [
            'group' => $group,
            'posts' => $posts,
            'user' => $groupsData['user'],
        ];

        return $this->render('fo/group-detail.html.twig', $viewModel);
    }
}
