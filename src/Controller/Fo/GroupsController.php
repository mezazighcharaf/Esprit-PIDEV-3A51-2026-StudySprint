<?php

namespace App\Controller\Fo;

use App\Dto\GroupCreateDTO;
use App\Dto\GroupUpdateDTO;
use App\Dto\PostCreateDTO;
use App\Entity\User;
use App\Form\Fo\GroupFormType;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupPostRepository;
use App\Repository\PostCommentRepository;
use App\Repository\StudyGroupRepository;
use App\Repository\UserRepository;
use App\Service\GroupInvitationService;
use App\Service\GroupService;
use App\Service\PostInteractionService;
use App\Service\PostService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use App\Repository\GroupInvitationRepository;
use App\Entity\GroupInvitation;

class GroupsController extends AbstractController
{
    public function __construct(
        private StudyGroupRepository $groupRepository,
        private GroupMemberRepository $memberRepository,
        private GroupPostRepository $postRepository,
        private GroupInvitationRepository $invitationRepository,
        private PostCommentRepository $commentRepository,
        private GroupService $groupService,
        private GroupInvitationService $invitationService,
        private PostService $postService,
        private PostInteractionService $postInteractionService,
        private UserRepository $userRepository,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Route('/app/groupes', name: 'app_groups')]
    public function index(Request $request): Response
    {
        $state = $request->query->get('state', 'default');
        /** @var User|null $user */
        $user = $this->getUser();

        // Fetch groups where user is a member
        $userGroups = [];
        $invitations = [];
        if ($user) {
            $memberShips = $this->memberRepository->findGroupsByUser($user);
            foreach ($memberShips as $membership) {
                $group = $membership->getGroup();
                $userGroups[] = [
                    'id' => $group->getId(),
                    'name' => $group->getName(),
                    'description' => $group->getDescription(),
                    'initials' => $this->getInitials($group->getName()),
                    'members_count' => count($this->memberRepository->findByGroup($group)),
                    'members' => $this->formatGroupMembers($this->memberRepository->findByGroup($group)),
                    'role' => $membership->getMemberRole(),
                    'last_activity' => $group->getLastActivity() ? $this->formatTimeAgo($group->getLastActivity()) : $this->formatTimeAgo($group->getCreatedAt()),
                    'activity_timestamp' => $group->getLastActivity() ? $group->getLastActivity()->getTimestamp() : $group->getCreatedAt()->getTimestamp(),
                    'subject' => $group->getSubject(),
                    'privacy' => $group->getPrivacy(),
                    'created_at' => $this->formatTimeAgo($group->getCreatedAt()),
                ];
            }

            // Fetch pending invitations
            $pendingInvitations = $this->invitationRepository->findPendingByEmail($user->getEmail());
            foreach ($pendingInvitations as $invitation) {
                $invitations[] = [
                    'id' => $invitation->getId(),
                    'group_name' => $invitation->getGroup()->getName(),
                    'invited_by' => $invitation->getInvitedBy() ? $invitation->getInvitedBy()->getFullName() : 'Système',
                    'date' => $this->formatTimeAgo($invitation->getInvitedAt()),
                ];
            }
        }

        // Determine state based on data
        if ($state === 'default') {
            // State remains default to always show structural elements (tabs, cards)
        }

        $viewModel = [
            'state' => $state,
            'data' => [
                'user' => [
                    'id' => $user?->getId(),
                    'name' => $user ? $user->getPrenom() . ' ' . $user->getNom() : 'Guest',
                    'email' => $user?->getEmail(),
                    'initials' => $this->getInitials($user ? $user->getPrenom() . ' ' . $user->getNom() : null),
                ],
                'groups' => $userGroups,
                'invitations' => $invitations,
                'available_groups' => $user ? $this->getSortedAvailableGroups($user) : [],
                'feedbacks' => [],
            ],
        ];

        return $this->render('fo/groups.html.twig', $viewModel);
    }

    /**
     * Helper to get available groups sorted by member count
     */
    private function getSortedAvailableGroups(User $user): array
    {
        $groups = $this->groupService->getAvailableGroupsForUser($user);
        
        $availableGroups = array_map(fn($g) => [
            'id' => $g->getId(),
            'name' => $g->getName(),
            'description' => $g->getDescription(),
            'initials' => $this->getInitials($g->getName()),
            'members_count' => $this->memberRepository->countByGroup($g),
            'subject' => $g->getSubject()
        ], $groups);

        // Sort by members_count DESC
        usort($availableGroups, fn($a, $b) => $b['members_count'] <=> $a['members_count']);

        return $availableGroups;
    }

    #[Route('/app/groupes/creer', name: 'app_group_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $dto = new GroupCreateDTO();
        $form = $this->createForm(GroupFormType::class, $dto, [
            'data_class' => GroupCreateDTO::class,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $group = $this->groupService->createGroup($dto, $user);
                $this->addFlash('success', 'Groupe créé avec succès');
                return $this->redirectToRoute('app_group_detail', ['id' => $group->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erreur lors de la création du groupe');
            }
        }

        return $this->render('fo/group-create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/app/groupes/{id}/modifier', name: 'app_group_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $group = $this->groupRepository->find($id);

        if (!$group) {
            throw $this->createNotFoundException('Groupe non trouvé');
        }

        // Check permissions
        if (!$this->groupService->canEditGroup($group, $user)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas la permission de modifier ce groupe');
        }

        $dto = new GroupUpdateDTO();
        $dto->id = $group->getId();
        $dto->name = $group->getName();
        $dto->description = $group->getDescription();
        $dto->privacy = $group->getPrivacy();
        $dto->subject = $group->getSubject();

        $form = $this->createForm(GroupFormType::class, $dto, [
            'data_class' => GroupUpdateDTO::class,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->groupService->updateGroup($group, $dto, $user);
                $this->addFlash('success', 'Groupe modifié avec succès');
                return $this->redirectToRoute('app_group_detail', ['id' => $group->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erreur lors de la modification du groupe');
            }
        }

        return $this->render('fo/group-edit.html.twig', [
            'form' => $form->createView(),
            'group' => $group,
        ]);
    }

    #[Route('/app/groupes/{id}/supprimer', name: 'app_group_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
            }
            return $this->redirectToRoute('app_login');
        }

        $group = $this->groupRepository->find($id);

        if (!$group) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'Groupe non trouvé'], Response::HTTP_NOT_FOUND);
            }
            throw $this->createNotFoundException('Groupe non trouvé');
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('delete-group-' . $group->getId(), $request->request->get('_token'))) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'Token CSRF invalide'], Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        // Check permissions - only Admin can delete
        if (!$this->groupService->canDeleteGroup($group, $user)) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'Vous n\'avez pas les permissions pour supprimer ce groupe'], Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('Seuls les administrateurs du groupe peuvent le supprimer');
        }

        try {
            $this->groupService->deleteGroup($group, $user);
            if ($isAjax) {
                return $this->json(['success' => true]);
            }
            $this->addFlash('success', 'Groupe supprimé avec succès');
        } catch (\Exception $e) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('danger', 'Erreur lors de la suppression du groupe');
        }

        return $this->redirectToRoute('app_groups');
    }

    #[Route('/app/groupes/{id}/leave', name: 'app_group_leave', methods: ['POST'])]
    public function leave(int $id, Request $request): Response
    {
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
            }
            return $this->redirectToRoute('app_login');
        }

        $group = $this->groupRepository->find($id);

        if (!$group) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'Groupe non trouvé'], Response::HTTP_NOT_FOUND);
            }
            throw $this->createNotFoundException('Groupe non trouvé');
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('leave-group-' . $group->getId(), $request->request->get('_token'))) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'Token CSRF invalide'], Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        try {
            $this->groupService->removeMember($group, $user, $user);
            if ($isAjax) {
                return $this->json(['success' => true]);
            }
            $this->addFlash('success', 'Vous avez quitté le groupe');
        } catch (\Exception $e) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_groups');
    }

    #[Route('/app/groupes/{id}/invite', name: 'app_group_invite', methods: ['POST'])]
    public function invite(int $id, Request $request): Response
    {
        $group = $this->groupRepository->find($id);
        if (!$group) {
            throw $this->createNotFoundException('Groupe non trouvé');
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid(
            'invite-members-' . $group->getId(),
            $request->request->get('_token')
        )) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('app_group_detail', ['id' => $id]);
        }

        $emailsInput = $request->request->get('emails', '');
        $emails = preg_split('/\R/', $emailsInput);
        $emails = array_map('trim', $emails);
        $emails = array_filter($emails);

        if (empty($emails)) {
            $this->addFlash('error', 'Aucune adresse email valide.');
            return $this->redirectToRoute('app_group_detail', ['id' => $id]);
        }

        $role = $request->request->get('role', 'member');

        try {
            $created = $this->invitationService->inviteUsers($group, $emails, $user, $role);
            $this->addFlash(
                'success',
                sprintf('%d invitation(s) envoyée(s)', count($created))
            );
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_group_detail', ['id' => $id]);
    }

    #[Route('/app/invitations/{id}/{action}', name: 'app_invitation_respond', methods: ['POST'])]
    public function respondInvitation(int $id, string $action, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
             return $this->redirectToRoute('app_login');
        }

        $invitation = $this->groupRepository->getEntityManager()->getRepository(GroupInvitation::class)->find($id);

        if (!$invitation) {
            throw $this->createNotFoundException('Invitation non trouvée');
        }

        // Verify ownership (email match)
        if (strtolower($invitation->getEmail()) !== strtolower($user->getEmail())) {
            throw $this->createAccessDeniedException('Cette invitation ne vous est pas destinée');
        }

        if (!$this->isCsrfTokenValid('respond-invitation-' . $invitation->getId(), $request->request->get('_token'))) {
             $this->addFlash('error', 'Token CSRF invalide');
             return $this->redirectToRoute('app_groups');
        }

        try {
            if ($action === 'accept') {
                $this->invitationService->acceptInvitation($invitation, $user);
                $this->addFlash('success', 'Invitation acceptée, vous avez rejoint le groupe !');
            } elseif ($action === 'decline') {
                $this->invitationService->declineInvitation($invitation, $user);
                $this->addFlash('success', 'Invitation refusée');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_groups');
    }

    #[Route('/app/groupes/join/code', name: 'app_group_join_code', methods: ['POST'])]
    public function joinByCode(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
             return $this->redirectToRoute('app_login');
        }

        $code = trim($request->request->get('code'));

        if (empty($code)) {
            $this->addFlash('error', 'Veuillez entrer un code');
            return $this->redirectToRoute('app_groups');
        }

        try {
            $this->invitationService->acceptInvitationByCode($code, $user);
            $this->addFlash('success', 'Vous avez rejoint le groupe avec succès');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_groups');
    }

    #[Route('/app/groupes/{id}/rejoindre', name: 'app_group_join_public', methods: ['POST'])]
    public function joinPublic(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $group = $this->groupRepository->find($id);
        if (!$group) {
            throw $this->createNotFoundException('Groupe non trouvé');
        }

        if ($group->getPrivacy() !== 'public') {
            $this->addFlash('error', 'Ce groupe est privé. Vous avez besoin d\'une invitation pour le rejoindre.');
            return $this->redirectToRoute('app_group_detail', ['id' => $id]);
        }

        try {
            $this->groupService->addMember($group, $user, 'member');
            $this->addFlash('success', 'Bienvenue dans le groupe !');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_group_detail', ['id' => $id]);
    }

    #[Route('/app/groupes/{id}', name: 'app_group_detail')]
    public function detail(int $id): Response
    {
        $group = $this->groupRepository->find($id);

        if (!$group) {
            throw $this->createNotFoundException('Groupe non trouvé');
        }
/** @var User|null $user */
        
        $user = $this->getUser();
        
        // Get group members
        $members = $this->memberRepository->findByGroup($group);
        
        // Get sort parameter
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $sort = $request->query->get('sort', 'date');
        
        // Get group posts with sorting
        $dbPosts = $this->postRepository->findAllByGroupSorted($group, $sort);
        
        // Format posts for template
        $posts = [];
        foreach ($dbPosts as $post) {
            $author = $post->getAuthor();
            $authorRole = $this->memberRepository->getUserRoleInGroup($group, $author);
            
            // Get post stats
            $stats = $this->postInteractionService->getPostStats($post, $user);
            
            // Check if user can delete this post
            $canDelete = false;
            if ($user) {
                $canDelete = $this->postService->canDeletePost($post, $user);
            }
            
            $posts[] = [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'body' => $post->getBody(),
                'attachment_url' => $post->getAttachmentUrl(),
                'author' => $author->getPrenom() . ' ' . $author->getNom(),
                'author_initials' => $this->getInitials($author->getPrenom() . ' ' . $author->getNom()),
                'author_role' => $authorRole ?? 'member',
                'created_at' => $this->formatTimeAgo($post->getCreatedAt()),
                'likes_count' => $stats['likesCount'],
                'comments_count' => $stats['commentsCount'],
                'avg_rating' => $stats['averageRating'],
                'user_liked' => $stats['userLiked'],
                'user_rating' => $stats['userRating'],
                'can_delete' => $canDelete,
            ];
        }

        $groupData = [
            'id' => $group->getId(),
            'name' => $group->getName(),
            'description' => $group->getDescription(),
            'initials' => $this->getInitials($group->getName()),
            'members_count' => count($members),
            'members' => $this->formatGroupMembers($members),
            'subject' => $group->getSubject(),
            'privacy' => $group->getPrivacy(),
            'created_at' => $this->formatTimeAgo($group->getCreatedAt()),
        ];

        $viewModel = [
            'group' => $groupData,
            'posts' => $posts,
            'user' => [
                'id' => $user?->getId(),
                'name' => $user ? $user->getPrenom() . ' ' . $user->getNom() : 'Guest',
                'email' => $user?->getEmail(),
                'initials' => $this->getInitials($user ? $user->getPrenom() . ' ' . $user->getNom() : null),
            ],
            'can_edit' => $user ? $this->groupService->canEditGroup($group, $user) : false,
            'can_delete' => $user ? $this->groupService->canDeleteGroup($group, $user) : false,
            'can_invite' => $user ? $this->groupService->canEditGroup($group, $user) : false,
            'is_member' => $user ? $this->memberRepository->findOneBy(['group' => $group, 'user' => $user]) !== null : false,
            'current_sort' => $sort,
        ];

        return $this->render('fo/group-detail.html.twig', $viewModel);
    }

    #[Route('/app/groupes/{id}/posts', name: 'app_group_posts', methods: ['GET'])]
    public function getPosts(int $id, Request $request): Response
    {
        $group = $this->groupRepository->find($id);
        if (!$group) {
            throw $this->createNotFoundException('Groupe non trouvé');
        }

        $user = $this->getUser();
        $sort = $request->query->get('sort', 'date');

        // Get group posts with sorting
        $dbPosts = $this->postRepository->findAllByGroupSorted($group, $sort);
        
        // Format posts for template
        $posts = [];
        foreach ($dbPosts as $post) {
            $author = $post->getAuthor();
            $authorRole = $this->memberRepository->getUserRoleInGroup($group, $author);
            
            // Get post stats
            $stats = $this->postInteractionService->getPostStats($post, $user);
            
            // Check if user can delete this post
            $canDelete = false;
            if ($user) {
                $canDelete = $this->postService->canDeletePost($post, $user);
            }
            
            $posts[] = [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'body' => $post->getBody(),
                'attachment_url' => $post->getAttachmentUrl(),
                'author' => $author->getPrenom() . ' ' . $author->getNom(),
                'author_initials' => $this->getInitials($author->getPrenom() . ' ' . $author->getNom()),
                'author_role' => $authorRole ?? 'member',
                'created_at' => $this->formatTimeAgo($post->getCreatedAt()),
                'likes_count' => $stats['likesCount'],
                'comments_count' => $stats['commentsCount'],
                'avg_rating' => $stats['averageRating'],
                'user_liked' => $stats['userLiked'],
                'user_rating' => $stats['userRating'],
                'can_delete' => $canDelete,
            ];
        }

        return $this->render('fo/fragments/_posts_list.html.twig', [
            'posts' => $posts,
            'user' => $user
        ]);
    }

    #[Route('/app/groupes/{groupId}/membres/{userId}/role', name: 'app_group_member_change_role', methods: ['POST'])]
    public function changeMemberRole(int $groupId, int $userId, Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            return $this->json(['error' => 'Groupe non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('group-member-' . $groupId, $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        $newRole = $request->request->get('role');
        $validRoles = ['member', 'moderator', 'admin'];

        if (!in_array($newRole, $validRoles)) {
            return $this->json(['error' => 'Rôle invalide'], Response::HTTP_BAD_REQUEST);
        }
        
        // Get the user to promote/demote
        $memberUser = $this->userRepository->find($userId);
        
        if (!$memberUser) {
            return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->groupService->changeMemberRole($group, $memberUser, $newRole, $currentUser);
            
            return $this->json([
                'success' => true,
                'message' => 'Rôle du membre mis à jour',
                'user_id' => $userId,
                'new_role' => $newRole,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }
    }

    #[Route('/app/groupes/{groupId}/membres/{userId}/remove', name: 'app_group_member_remove', methods: ['POST'])]
    public function removeMember(int $groupId, int $userId, Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            return $this->json(['error' => 'Groupe non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('group-member-' . $groupId, $request->request->get('_token'))) {
            return $this->json(['error' => 'Token CSRF invalide'], Response::HTTP_FORBIDDEN);
        }

        // Get the user to remove
        $memberUser = $this->userRepository->find($userId);
        
        if (!$memberUser) {
            return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->groupService->removeMember($group, $memberUser, $currentUser);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Membre retiré du groupe',
                'user_id' => $userId,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Helper to format group members
     */
    private function formatGroupMembers(array $memberShips): array
    {
        $members = [];
        foreach ($memberShips as $membership) {
            $user = $membership->getUser();
            $members[] = [
                'id' => $membership->getId(),
                'user_id' => $user->getId(),
                'name' => $user->getPrenom() . ' ' . $user->getNom(),
                'email' => $user->getEmail(),
                'initials' => $this->getInitials($user->getPrenom() . ' ' . $user->getNom()),
                'role' => $membership->getMemberRole(),
            ];
        }
        return $members;
    }

    /**
     * Helper to get initials from a string (e.g., name or group name)
     */
    private function getInitials(?string $name): string
    {
        if (!$name) {
            return '';
        }

        $parts = explode(' ', trim($name));
        $initials = '';
        
        foreach ($parts as $part) {
            if (!empty($part)) {
                $initials .= strtoupper(mb_substr($part, 0, 1));
                if (strlen($initials) === 2) {
                    break;
                }
            }
        }
        
        return $initials;
    }

    /**
     * Helper to format time ago
     */
    private function formatTimeAgo(\DateTimeInterface $date): string
    {
        $now = new \DateTime();
        $diff = $now->getTimestamp() - $date->getTimestamp();

        if ($diff < 60) {
            return 'À l\'instant';
        } elseif ($diff < 3600) {
            $minutes = intdiv($diff, 60);
            return "Il y a $minutes minute" . ($minutes > 1 ? 's' : '');
        } elseif ($diff < 86400) {
            $hours = intdiv($diff, 3600);
            return "Il y a $hours heure" . ($hours > 1 ? 's' : '');
        } elseif ($diff < 604800) {
            $days = intdiv($diff, 86400);
            return "Il y a $days jour" . ($days > 1 ? 's' : '');
        } else {
            return $date->format('d F Y');
        }
    }

    // ==================== POST ENDPOINTS ====================

    /**
     * Create a new post in a group (AJAX)
     */
    #[Route('/app/groupes/{id}/posts/create', name: 'app_post_create', methods: ['POST'])]
    public function createPost(int $id, Request $request): JsonResponse
    {
        $group = $this->groupRepository->find($id);
        if (!$group) {
            return $this->json(['error' => 'Groupe non trouvé'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        // CSRF validation
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('create-post-' . $id, $token)) {
            return $this->json(['error' => 'Token CSRF invalide'], Response::HTTP_FORBIDDEN);
        }

        try {
            $dto = new PostCreateDTO();
            $dto->title = $request->request->get('title');
            $dto->body = $request->request->get('body');
            $dto->postType = $request->request->get('post_type', 'text');
            $dto->attachmentUrl = $request->request->get('attachment_url');

            // Handle file upload (take first file if multiple)
            $files = $request->files->get('files');
            if (is_array($files) && count($files) > 0) {
                $dto->file = $files[0];
            } elseif ($files instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $dto->file = $files;
            }

            $post = $this->postService->createPost($group, $user, $dto);

            // Get post stats
            $stats = $this->postInteractionService->getPostStats($post, $user);

            // Generate CSRF tokens for the new post interactions
            $tokens = [
                'like' => $this->csrfTokenManager->getToken('like-post-' . $post->getId())->getValue(),
                'rate' => $this->csrfTokenManager->getToken('rate-post-' . $post->getId())->getValue(),
                'delete' => $this->csrfTokenManager->getToken('delete-post-' . $post->getId())->getValue(),
                'comment' => $this->csrfTokenManager->getToken('comment-post-' . $post->getId())->getValue(),
            ];

            return new JsonResponse([
                'success' => true,
                'message' => 'Post créé avec succès',
                'csrf_tokens' => $tokens,
                'post' => [
                    'id' => $post->getId(),
                    'title' => $post->getTitle(),
                    'body' => $post->getBody(),
                    'type' => $post->getPostType(),
                    'attachmentUrl' => $post->getAttachmentUrl(),
                    'author' => [
                        'id' => $user->getId(),
                        'name' => $user->getPrenom() . ' ' . $user->getNom(),
                        'initials' => $this->getInitials($user->getPrenom() . ' ' . $user->getNom()),
                        'role' => $this->groupService->getGroupRole($group, $user), // Add logic to get role if needed, or simplify
                    ],
                    'createdAt' => $post->getCreatedAt()->format('c'),
                    'timeAgo' => $this->formatTimeAgo($post->getCreatedAt()),
                    'stats' => $stats,
                    'canDelete' => true, // Author can always delete
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Delete a post (AJAX)
     */
    #[Route('/app/groupes/{groupId}/posts/{postId}/delete', name: 'app_post_delete', methods: ['POST'])]
    public function deletePost(int $groupId, int $postId, Request $request): JsonResponse
    {
        $post = $this->postRepository->find($postId);
        if (!$post) {
            return new JsonResponse(['error' => 'Post non trouvé'], 404);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        // CSRF validation
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-post-' . $postId, $token)) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        try {
            $this->postService->deletePost($post, $user);
            return new JsonResponse([
                'success' => true,
                'message' => 'Post supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Toggle like on a post (AJAX)
     */
    #[Route('/app/posts/{id}/like', name: 'app_post_like', methods: ['POST'])]
    public function toggleLike(int $id, Request $request): JsonResponse
    {
        $post = $this->postRepository->find($id);
        if (!$post) {
            return new JsonResponse(['error' => 'Post non trouvé'], 404);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        // CSRF validation
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('like-post-' . $id, $token)) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        try {
            $result = $this->postInteractionService->toggleLike($post, $user);
            return new JsonResponse([
                'success' => true,
                'message' => $result['liked'] ? 'Post aimé' : 'Like retiré',
                'liked' => $result['liked'],
                'likesCount' => $result['likesCount']
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Rate a post (AJAX)
     */
    #[Route('/app/posts/{id}/rate', name: 'app_post_rate', methods: ['POST'])]
    public function ratePost(int $id, Request $request): JsonResponse
    {
        $post = $this->postRepository->find($id);
        if (!$post) {
            return new JsonResponse(['error' => 'Post non trouvé'], 404);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        // CSRF validation
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('rate-post-' . $id, $token)) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        $rating = (int) $request->request->get('rating');

        try {
            $result = $this->postInteractionService->ratePost($post, $user, $rating);
            return new JsonResponse([
                'success' => true,
                'message' => 'Note enregistrée',
                'userRating' => $result['userRating'],
                'averageRating' => $result['averageRating'],
                'ratingsCount' => $result['ratingsCount']
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get comments for a post (AJAX)
     */
    #[Route('/app/posts/{id}/comments', name: 'app_post_comments', methods: ['GET'])]
    public function getComments(int $id): JsonResponse
    {
        $post = $this->postRepository->find($id);
        if (!$post) {
            return new JsonResponse(['error' => 'Post non trouvé'], 404);
        }

        /** @var User $user */
        $user = $this->getUser();

        $comments = $this->commentRepository->findByPostWithReplies($post);
        
        $commentsData = array_map(function($comment) use ($user, $post) {
            return [
                'id' => $comment->getId(),
                'body' => $comment->getBody(),
                'author' => [
                    'id' => $comment->getAuthor()->getId(),
                    'name' => $comment->getAuthor()->getPrenom() . ' ' . $comment->getAuthor()->getNom(),
                    'initials' => $this->getInitials($comment->getAuthor()->getPrenom() . ' ' . $comment->getAuthor()->getNom()),
                ],
                'parentId' => $comment->getParentComment()?->getId(),
                'createdAt' => $comment->getCreatedAt()->format('c'),
                'timeAgo' => $this->formatTimeAgo($comment->getCreatedAt()),
                'canDelete' => $user && ($comment->getAuthor()->getId() === $user->getId() || 
                    $this->groupService->isGroupAdmin($post->getGroup(), $user))
            ];
        }, $comments);

        return new JsonResponse([
            'success' => true,
            'comments' => $commentsData
        ]);
    }

    /**
     * Create a comment on a post (AJAX)
     */
    #[Route('/app/posts/{id}/comments/create', name: 'app_comment_create', methods: ['POST'])]
    public function createComment(int $id, Request $request): JsonResponse
    {
        $post = $this->postRepository->find($id);
        if (!$post) {
            return new JsonResponse(['error' => 'Post non trouvé'], 404);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        // CSRF validation
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('comment-post-' . $id, $token)) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        $body = $request->request->get('body');
        $parentId = $request->request->get('parent_id');
        
        $parent = null;
        if ($parentId) {
            $parent = $this->commentRepository->find($parentId);
        }

        try {
            $comment = $this->postInteractionService->addComment($post, $user, $body, $parent);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Commentaire ajouté',
                'comment' => [
                    'id' => $comment->getId(),
                    'body' => $comment->getBody(),
                    'author' => [
                        'id' => $user->getId(),
                        'name' => $user->getPrenom() . ' ' . $user->getNom(),
                        'initials' => $this->getInitials($user->getPrenom() . ' ' . $user->getNom()),
                    ],
                    'parentId' => $parent?->getId(),
                    'createdAt' => $comment->getCreatedAt()->format('c'),
                    'timeAgo' => $this->formatTimeAgo($comment->getCreatedAt()),
                    'canDelete' => true
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Delete a comment (AJAX)
     */
    #[Route('/app/comments/{id}/delete', name: 'app_comment_delete', methods: ['POST'])]
    public function deleteComment(int $id, Request $request): JsonResponse
    {
        $comment = $this->commentRepository->find($id);
        if (!$comment) {
            return new JsonResponse(['error' => 'Commentaire non trouvé'], 404);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        // CSRF validation
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-comment-' . $id, $token)) {
            return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
        }

        try {
            $this->postInteractionService->deleteComment($comment, $user);
            return new JsonResponse([
                'success' => true,
                'message' => 'Commentaire supprimé'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }
}

