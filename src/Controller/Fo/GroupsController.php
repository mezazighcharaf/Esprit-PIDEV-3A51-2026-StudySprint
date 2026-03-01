<?php

namespace App\Controller\Fo;

use App\Dto\GroupCreateDTO;
use App\Dto\GroupUpdateDTO;
use App\Dto\PostCreateDTO;
use App\Entity\GroupInvitation;
use App\Entity\User;
use App\Form\Fo\GroupFormType;
use App\Repository\GroupInvitationRepository;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupPostRepository;
use App\Repository\PostCommentRepository;
use App\Repository\PostLikeRepository;
use App\Repository\PostRatingRepository;
use App\Repository\StudyGroupRepository;
use App\Repository\UserRepository;
use App\Security\Voter\GroupVoter;
use App\Service\AvatarService;
use App\Service\ContentSanitizer;
use App\Service\FormattingService;
use App\Service\GroupInputValidator;
use App\Service\GroupInvitationService;
use App\Service\GroupRoleChecker;
use App\Service\GroupService;
use App\Service\InvitationMailer;
use App\Service\PostInteractionService;
use App\Service\PostService;
use App\Service\TranslationService;
use App\Service\AI\GeminiChatbotService;
use App\Repository\ChatbotConfigRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class GroupsController extends AbstractController
{
    // Input validation constants
    private const VALID_SORT_OPTIONS = ['date', 'likes', 'comments', 'rating'];
    private const VALID_MEMBER_ROLES = ['member', 'moderator', 'admin'];
    private const VALID_INVITATION_ACTIONS = ['accept', 'decline'];
    private const MAX_INVITATION_CODE_LENGTH = 12;

    public function __construct(
        private StudyGroupRepository $groupRepository,
        private GroupMemberRepository $memberRepository,
        private GroupPostRepository $postRepository,
        private GroupInvitationRepository $invitationRepository,
        private PostCommentRepository $commentRepository,
        private PostRatingRepository $ratingRepository,
        private PostLikeRepository $likeRepository,
        private GroupService $groupService,
        private GroupInvitationService $invitationService,
        private PostService $postService,
        private PostInteractionService $postInteractionService,
        private UserRepository $userRepository,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private FormattingService $formattingService,
        private ValidatorInterface $validator,
        private ContentSanitizer $contentSanitizer,
        private TranslationService $translationService,
        private GeminiChatbotService $chatbotService,
        private ChatbotConfigRepository $chatbotConfigRepository,
        private GroupInputValidator $groupInputValidator,
    ) {}

    // ==================== HELPER METHODS ====================

    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest(Request $request): bool
    {
        return (string)$request->headers->get('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Create a standardized JSON error response
     */
    private function errorResponse(string $message, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return $this->json(['success' => false, 'error' => $message], $status);
    }

    /**
     * Create a standardized JSON success response
     */
    /** @param array<string, mixed> $data */
    private function successResponse(array $data = [], string $message = ''): JsonResponse
    {
        return $this->json(array_merge(['success' => true, 'message' => $message], $data));
    }

    /**
     * Extract form errors as an array of strings
     *
     * @return list<string>
     */
    private function getFormErrors(\Symfony\Component\Form\FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return $errors;
    }

    // ==================== PUBLIC ROUTES ====================

    #[Route('/app/groupes', name: 'app_groups')]
    public function index(Request $request): Response
    {
        $state = $request->query->get('state', 'default');
        /** @var User|null $user */
        $user = $this->getUser();

        // Fetch groups where user is a member (optimized query)
        $userGroups = [];
        $invitations = [];
        if ($user) {
            // Use optimized query with counts
            $membershipData = $this->memberRepository->findGroupsByUserWithCounts($user);
            
            // Get all groups to batch load members
            $groups = array_values(array_filter(array_map(fn($data) => $data['membership']->getGroup(), $membershipData)));
            $membersByGroup = $this->memberRepository->findMembersByGroups($groups);
            
            foreach ($membershipData as $data) {
                $membership = $data['membership'];
                $group = $membership->getGroup();
                if (!$group) continue;
                
                $groupId = $group->getId();
                $members = $membersByGroup[$groupId] ?? [];
                
                $userGroups[] = [
                    'id' => $groupId,
                    'name' => $group->getName(),
                    'description' => $group->getDescription(),
                    'initials' => $this->formattingService->getInitials($group->getName()),
                    'members_count' => $data['memberCount'],
                    'members' => $this->formattingService->formatGroupMembers($members),
                    'role' => $membership->getMemberRole(),
                    'last_activity' => $group->getLastActivity() ? $this->formattingService->formatTimeAgo($group->getLastActivity()) : $this->formattingService->formatTimeAgo($group->getCreatedAt() ?? new \DateTimeImmutable()),
                    'activity_timestamp' => $group->getLastActivity() ? $group->getLastActivity()->getTimestamp() : ($group->getCreatedAt() ? $group->getCreatedAt()->getTimestamp() : 0),
                    'subject' => $group->getSubject(),
                    'privacy' => $group->getPrivacy(),
                    'created_at' => $this->formattingService->formatTimeAgo($group->getCreatedAt() ?? new \DateTimeImmutable()),
                ];
            }

            // Fetch pending invitations (received)
            $pendingInvitations = $this->invitationRepository->findPendingByEmail((string) $user->getEmail());
            foreach ($pendingInvitations as $invitation) {
                $invitations[] = [
                    'id' => $invitation->getId(),
                    'group' => [
                        'id' => $invitation->getGroup()->getId(),
                        'name' => $invitation->getGroup()->getName(),
                    ],
                    'email' => $invitation->getEmail(),
                    'invitedBy' => $invitation->getInvitedBy() ? $invitation->getInvitedBy()->getFullName() : 'Système',
                    'invitedAt' => $invitation->getInvitedAt(),
                    'status' => $invitation->getStatus(),
                    'role' => $invitation->getRole(),
                ];
            }

            // Fetch sent invitations
            $sentInvitations = $this->invitationRepository->findSent($user);
            $sentInvitationsData = [];
            foreach ($sentInvitations as $invitation) {
                $sentInvitationsData[] = [
                    'id' => $invitation->getId(),
                    'group' => [
                        'id' => $invitation->getGroup()->getId(),
                        'name' => $invitation->getGroup()->getName(),
                    ],
                    'email' => $invitation->getEmail(),
                    'code' => $invitation->getCode(),
                    'invitedAt' => $invitation->getInvitedAt(),
                    'status' => $invitation->getStatus(),
                    'role' => $invitation->getRole(),
                ];
            }
        }

        $viewModel = [
            'state' => $state,
            'data' => [
                'user' => $this->formattingService->formatUserForView($user),
                'groups' => $userGroups,
                'invitations' => $invitations,
                'sent_invitations' => $sentInvitationsData ?? [],
                'available_groups' => $user ? $this->getSortedAvailableGroups($user) : [],
                'feedbacks' => $user ? $this->getUserFeedbacks($user) : [],
            ],
        ];

        return $this->render('fo/groups.html.twig', $viewModel);
    }

    /**
     * Helper to get available groups sorted by member count
     *
     * @return list<array<string, mixed>>
     */
    private function getSortedAvailableGroups(User $user): array
    {
        $groups = $this->groupService->getAvailableGroupsForUser($user);
        
        $availableGroups = array_map(fn($g) => [
            'id' => $g->getId(),
            'name' => $g->getName(),
            'description' => $g->getDescription(),
            'initials' => $this->formattingService->getInitials($g->getName()),
            'members_count' => $this->memberRepository->countByGroup($g),
            'subject' => $g->getSubject()
        ], $groups);

        // Sort by members_count DESC
        usort($availableGroups, fn($a, $b) => $b['members_count'] <=> $a['members_count']);

        return $availableGroups;
    }

    /**
     * Build feedbacks (ratings, likes, comments) received on the user's posts
     *
     * @return list<array<string, mixed>>
     */
    private function getUserFeedbacks(User $user, int $limit = 50): array
    {
        $feedbacks = [];

        // Ratings received on user's posts
        $ratings = $this->ratingRepository->findByPostAuthorOrderByCreatedAtDesc($user, $limit);
        foreach ($ratings as $rating) {
            $post = $rating->getPost();
            if (!$post) continue;
            $group = $post->getGroup();
            if (!$group) continue;
            $rater = $rating->getUser();
            $postExcerpt = $post->getTitle() ?: $this->formattingService->truncateText($post->getBody() ?? '', 80);
            $feedbacks[] = [
                'type' => 'rating',
                'sort_at' => ($rating->getCreatedAt() ? $rating->getCreatedAt()->getTimestamp() : 0),
                'from' => $rater ? $this->formattingService->formatUserName($rater) : 'Utilisateur',
                'from_initials' => $rater ? $this->formattingService->getInitials($this->formattingService->formatUserName($rater)) : 'U',
                'group' => $group->getName(),
                'group_id' => $group->getId(),
                'post_id' => $post->getId(),
                'rating' => $rating->getRating(),
                'comment' => $postExcerpt,
                'date' => $rating->getCreatedAt() ? $this->formattingService->formatTimeAgo($rating->getCreatedAt()) : 'récemment',
            ];
        }

        // Likes received on user's posts
        $likes = $this->likeRepository->findByPostAuthorOrderByCreatedAtDesc($user, $limit);
        foreach ($likes as $like) {
            $post = $like->getPost();
            if (!$post) continue;
            $group = $post->getGroup();
            if (!$group) continue;
            $liker = $like->getUser();
            $postExcerpt = $post->getTitle() ?: $this->formattingService->truncateText($post->getBody() ?? '', 80);
            $feedbacks[] = [
                'type' => 'like',
                'sort_at' => ($like->getCreatedAt() ? $like->getCreatedAt()->getTimestamp() : 0),
                'from' => $liker ? $this->formattingService->formatUserName($liker) : 'Utilisateur',
                'from_initials' => $liker ? $this->formattingService->getInitials($this->formattingService->formatUserName($liker)) : 'U',
                'group' => $group->getName(),
                'group_id' => $group->getId(),
                'post_id' => $post->getId(),
                'comment' => $postExcerpt,
                'date' => $like->getCreatedAt() ? $this->formattingService->formatTimeAgo($like->getCreatedAt()) : 'récemment',
            ];
        }

        // Comments received on user's posts
        $comments = $this->commentRepository->findByPostAuthorOrderByCreatedAtDesc($user, $limit);
        foreach ($comments as $comment) {
            $post = $comment->getPost();
            if (!$post) continue;
            $group = $post->getGroup();
            if (!$group) continue;
            $commentAuthor = $comment->getAuthor();
            $feedbacks[] = [
                'type' => 'comment',
                'sort_at' => ($comment->getCreatedAt() ? $comment->getCreatedAt()->getTimestamp() : 0),
                'from' => $commentAuthor ? $this->formattingService->formatUserName($commentAuthor) : 'Utilisateur',
                'from_initials' => $commentAuthor ? $this->formattingService->getInitials($this->formattingService->formatUserName($commentAuthor)) : 'U',
                'group' => $group->getName(),
                'group_id' => $group->getId(),
                'post_id' => $post->getId(),
                'body' => $this->formattingService->truncateText($comment->getBody() ?? '', 120),
                'date' => $comment->getCreatedAt() ? $this->formattingService->formatTimeAgo($comment->getCreatedAt()) : 'récemment',
            ];
        }

        // Sort all feedbacks by date DESC
        usort($feedbacks, fn($a, $b) => $b['sort_at'] <=> $a['sort_at']);

        return array_slice($feedbacks, 0, $limit);
    }

    #[Route('/app/groupes/creer', name: 'app_group_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $isAjax = $this->isAjaxRequest($request);

        $dto = new GroupCreateDTO();
        $form = $this->createForm(GroupFormType::class, $dto, [
            'data_class' => GroupCreateDTO::class,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $group = $this->groupService->createGroup($dto, $user);
                    /** @phpstan-ignore-next-line */
                    if ($isAjax) {
                        return $this->successResponse([
                            'redirect' => $this->generateUrl('app_group_detail', ['id' => $group->getId()]),
                        ], 'Groupe créé avec succès');
                    }
                    
                    $this->addFlash('success', 'Groupe créé avec succès');
                    return $this->redirectToRoute('app_group_detail', ['id' => $group->getId()]);
                } catch (\Exception $e) {
                    /** @phpstan-ignore-next-line */
                    if ($isAjax) {
                        return $this->errorResponse('Erreur lors de la création du groupe');
                    }
                    $this->addFlash('danger', 'Erreur lors de la création du groupe');
                }
            } else {
                // Form has validation errors
                /** @phpstan-ignore-next-line */
                if ($isAjax) {
                    $errors = $this->getFormErrors($form);
                    return $this->errorResponse(
                        !empty($errors) ? implode(', ', $errors) : 'Veuillez corriger les erreurs du formulaire'
                    );
                }
            }
        }

        return $this->render('fo/group-create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/app/groupes/{id}/modifier', name: 'app_group_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $isAjax = $this->isAjaxRequest($request);

        $group = $this->groupRepository->find($id);

        if (!$group) {
            /** @phpstan-ignore-next-line */
            if ($isAjax) {
                return $this->errorResponse('Groupe non trouvé', Response::HTTP_NOT_FOUND);
            }
            throw $this->createNotFoundException('Groupe non trouvé');
        }

        // Check permissions using Voter
        $this->denyAccessUnlessGranted(GroupVoter::EDIT, $group);

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

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $this->groupService->updateGroup($group, $dto, $user);
                    /** @phpstan-ignore-next-line */
                    if ($isAjax) {
                        return $this->successResponse([
                            'redirect' => $this->generateUrl('app_group_detail', ['id' => $group->getId()]),
                        ], 'Groupe modifié avec succès');
                    }
                    
                    $this->addFlash('success', 'Groupe modifié avec succès');
                    return $this->redirectToRoute('app_group_detail', ['id' => $group->getId()]);
                } catch (\Exception $e) {
                    /** @phpstan-ignore-next-line */
                    if ($isAjax) {
                        return $this->errorResponse('Erreur lors de la modification du groupe: ' . $e->getMessage());
                    }
                    $this->addFlash('danger', 'Erreur lors de la modification du groupe: ' . $e->getMessage());
                }
            } else {
                // Form has validation errors
                /** @phpstan-ignore-next-line */
                if ($isAjax) {
                    $errors = $this->getFormErrors($form);
                    return $this->errorResponse(
                        !empty($errors) ? implode(', ', $errors) : 'Veuillez corriger les erreurs du formulaire'
                    );
                }
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
        $isAjax = $this->isAjaxRequest($request);

        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            /** @phpstan-ignore-next-line */
            if ($isAjax) {
                return $this->errorResponse('Non authentifié', Response::HTTP_UNAUTHORIZED);
            }
            return $this->redirectToRoute('app_login');
        }

        $group = $this->groupRepository->find($id);

        if (!$group) {
            /** @phpstan-ignore-next-line */
            if ($isAjax) {
                return $this->errorResponse('Groupe non trouvé', Response::HTTP_NOT_FOUND);
            }
            throw $this->createNotFoundException('Groupe non trouvé');
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('delete-group-' . $group->getId(), (string) (string) $request->request->get('_token'))) {
            /** @phpstan-ignore-next-line */
            if ($isAjax) {
                return $this->errorResponse('Token CSRF invalide', Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        // Check permissions using Voter - only Admin can delete
        if (!$this->isGranted(GroupVoter::DELETE, $group)) {
            /** @phpstan-ignore-next-line */
            if ($isAjax) {
                return $this->errorResponse('Vous n\'avez pas les permissions pour supprimer ce groupe', Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('Seuls les administrateurs du groupe peuvent le supprimer');
        }

        try {
            $this->groupService->deleteGroup($group, $user);
            /** @phpstan-ignore-next-line */
            if ($isAjax) {
                return $this->successResponse([], 'Groupe supprimé avec succès');
            }
            $this->addFlash('success', 'Groupe supprimé avec succès');
        } catch (\Exception $e) {
            /** @phpstan-ignore-next-line */
            if ($isAjax) {
                return $this->errorResponse($e->getMessage());
            }
            $this->addFlash('danger', 'Erreur lors de la suppression du groupe');
        }

        return $this->redirectToRoute('app_groups');
    }

    #[Route('/app/groupes/{id}/leave', name: 'app_group_leave', methods: ['POST'])]
    public function leave(int $id, Request $request): Response
    {
        $isAjax = $this->isAjaxRequest($request);

        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            /** @phpstan-ignore-next-line */
            if ($isAjax) {
                return $this->errorResponse('Non authentifié', Response::HTTP_UNAUTHORIZED);
            }
            return $this->redirectToRoute('app_login');
        }

        $group = $this->groupRepository->find($id);

        if (!$group) {
            /** @phpstan-ignore-next-line */
            if ($isAjax) {
                return $this->errorResponse('Groupe non trouvé', Response::HTTP_NOT_FOUND);
            }
            throw $this->createNotFoundException('Groupe non trouvé');
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('leave-group-' . $group->getId(), (string) (string) $request->request->get('_token'))) {
            /** @phpstan-ignore-next-line */
            if ($isAjax) {
                return $this->errorResponse('Token CSRF invalide', Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        try {
            $this->groupService->removeMember($group, $user, $user);
            /** @phpstan-ignore-next-line */
            if ($isAjax) {
                return $this->successResponse([], 'Vous avez quitté le groupe');
            }
            $this->addFlash('success', 'Vous avez quitté le groupe');
        } catch (\Exception $e) {
            /** @phpstan-ignore-next-line */
            if ($isAjax) {
                return $this->errorResponse($e->getMessage());
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

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('invite-members-' . $group->getId(), (string) (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('app_group_detail', ['id' => $id]);
        }

        // Parse and validate emails using GroupInputValidator
        $emailsInput = (string) $request->request->get('emails', '');
        $rawEmails = preg_split('/[\r\n,;]+/', $emailsInput) ?: [];
        $rawEmails = array_map('trim', $rawEmails);
        $rawEmails = array_filter($rawEmails);

        if (empty($rawEmails)) {
            $this->addFlash('error', 'Veuillez entrer au moins une adresse email.');
            return $this->redirectToRoute('app_group_detail', ['id' => $id]);
        }

        // Use GroupInputValidator for email validation and disposable domain check
        try {
            $emailValidation = $this->groupInputValidator->validateEmails($rawEmails);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_group_detail', ['id' => $id]);
        }
        
        if (empty($emailValidation['valid'])) {
            $this->addFlash('error', 'Aucune adresse email valide trouvée.');
            return $this->redirectToRoute('app_group_detail', ['id' => $id]);
        }

        // Validate role using GroupInputValidator
        $role = (string) $request->request->get('role', 'member');
        try {
            $this->groupInputValidator->validateRole($role);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', 'Rôle invalide.');
            return $this->redirectToRoute('app_group_detail', ['id' => $id]);
        }

        try {
            $created = $this->invitationService->inviteUsers($group, $emailValidation['valid'], $user, $role);
            
            $message = sprintf('%d invitation(s) envoyée(s)', count($created));
            if (!empty($emailValidation['invalid'])) {
                $message .= sprintf(' (%d email(s) invalide(s) ignoré(s))', count($emailValidation['invalid']));
            }
            
            $this->addFlash('success', $message);
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_group_detail', ['id' => $id]);
    }

    #[Route('/app/invitations/{id}/{action}', name: 'app_invitation_respond', methods: ['POST'], requirements: ['action' => 'accept|decline'])]
    public function respondInvitation(int $id, string $action, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Validate action parameter
        if (!in_array($action, self::VALID_INVITATION_ACTIONS, true)) {
            $this->addFlash('error', 'Action invalide');
            return $this->redirectToRoute('app_groups');
        }

        $invitation = $this->invitationRepository->find($id);

        if (!$invitation) {
            throw $this->createNotFoundException('Invitation non trouvée');
        }

        // Verify ownership (email match)
        if (strtolower((string) $invitation->getEmail()) !== strtolower((string) $user->getEmail())) {
            throw $this->createAccessDeniedException('Cette invitation ne vous est pas destinée');
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('respond-invitation-' . $invitation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('app_groups');
        }

        try {
            if ($action === 'accept') {
                $this->invitationService->acceptInvitation($invitation, $user);
                $this->addFlash('success', 'Invitation acceptée, vous avez rejoint le groupe !');
            } else {
                $this->invitationService->declineInvitation($invitation, $user);
                $this->addFlash('success', 'Invitation refusée');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_groups');
    }

    #[Route('/app/invitations/{id}/cancel', name: 'app_invitation_cancel', methods: ['POST'])]
    public function cancelInvitation(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $invitation = $this->invitationRepository->find($id);

        if (!$invitation) {
            throw $this->createNotFoundException('Invitation non trouvée');
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('cancel-invitation-' . $invitation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('app_groups');
        }

        try {
            $this->invitationService->cancelInvitation($invitation, $user);
            $this->addFlash('success', 'Invitation annulée');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_groups');
    }

    /**
     * Accept an invitation via email link or QR code (token-based)
     */
    #[Route('/app/invitation/{token}', name: 'app_invitation_accept_token')]
    public function acceptByToken(string $token, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            // Store the token URL so user can be redirected after login
            $request->getSession()->set('_security.main.target_path',
                $this->generateUrl('app_invitation_accept_token', ['token' => $token])
            );
            $this->addFlash('info', 'Veuillez vous connecter pour accepter l\'invitation.');
            return $this->redirectToRoute('app_login');
        }

        try {
            $this->invitationService->acceptInvitationByToken($token, $user);
            $this->addFlash('success', 'Invitation acceptée, vous avez rejoint le groupe !');
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

        // CSRF validation
        if (!$this->isCsrfTokenValid('join-by-code', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('app_groups');
        }

        $code = trim((string) $request->request->get('code', ''));

        if (empty($code)) {
            $this->addFlash('error', 'Veuillez entrer un code');
            return $this->redirectToRoute('app_groups');
        }

        // Validate code format (hex string, max length)
        if (strlen($code) > self::MAX_INVITATION_CODE_LENGTH || !preg_match('/^INV-[A-F0-9]{8}$/i', $code)) {
            $this->addFlash('error', 'Code d\'invitation invalide');
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

        // CSRF validation
        if (!$this->isCsrfTokenValid('join-group-' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('app_groups');
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
        
        // Build a map of user roles for quick lookup
        $userRolesMap = [];
        foreach ($members as $member) {
            $mUser = $member->getUser();
            if ($mUser) {
                $userRolesMap[$mUser->getId()] = $member->getMemberRole();
            }
        }
        
        // Get sort parameter with validation
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $sort = $request->query->get('sort', 'date');
        
        // Validate sort option
        if (!in_array($sort, self::VALID_SORT_OPTIONS, true)) {
            $sort = 'date';
        }
        
        // Get group posts with stats (optimized - fixes N+1)
        $postsWithStats = $this->postRepository->findByGroupWithStats($group, $user, $sort);
        
        // Check current user role for permission checks
        $currentUserRole = $user ? ($userRolesMap[$user->getId()] ?? null) : null;
        $isAdminOrMod = in_array($currentUserRole, ['admin', 'moderator'], true);
        
        // Format posts for template
        $posts = [];
        foreach ($postsWithStats as $postData) {
            $post = $postData['post'];
            $author = $post->getAuthor();
            $authorId = $author ? $author->getId() : 0;
            if (!$authorId || !$author) continue;
            
            // Check if user can delete this post
            $canDelete = false;
            if ($user) {
                $canDelete = ($author->getId() === $user->getId()) || ($currentUserRole === 'admin');
            }
            
            $posts[] = [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'body' => $post->getBody(),
                'post_type' => $post->getPostType(),
                'attachment_url' => $post->getAttachmentUrl(),
                'author' => $this->formattingService->formatUserName($author ),
                'author_initials' => $this->formattingService->getInitials($this->formattingService->formatUserName($author )),
                'author_role' => $userRolesMap[$authorId] ?? 'member',
                'created_at' => $this->formattingService->formatTimeAgo($post->getCreatedAt() ?? new \DateTimeImmutable()),
                'likes_count' => $postData['likesCount'],
                'comments_count' => $postData['commentsCount'],
                'avg_rating' => $postData['avgRating'],
                'user_liked' => $postData['userLiked'],
                'user_rating' => $postData['userRating'],
                'can_delete' => $canDelete,
            ];
        }

        $groupData = [
            'id' => $group->getId(),
            'name' => $group->getName(),
            'description' => $group->getDescription(),
            'initials' => $this->formattingService->getInitials($group->getName()),
            'members_count' => count($members),
            'members' => $this->formattingService->formatGroupMembers($members),
            'subject' => $group->getSubject(),
            'privacy' => $group->getPrivacy(),
            'created_at' => $this->formattingService->formatTimeAgo($group->getCreatedAt() ?? new \DateTimeImmutable()),
        ];

        $isMember = $currentUserRole !== null;

        // Chatbot config
        $chatbotConfig = $this->chatbotConfigRepository->findByGroup($group);

        $viewModel = [
            'group' => $groupData,
            'posts' => $posts,
            'user' => $this->formattingService->formatUserForView($user),
            'can_edit' => $this->isGranted(GroupVoter::EDIT, $group),
            'can_delete' => $this->isGranted(GroupVoter::DELETE, $group),
            'can_invite' => $this->isGranted(GroupVoter::INVITE, $group),
            'is_member' => $isMember,
            'current_sort' => $sort,
            'chatbot' => $chatbotConfig ? [
                'enabled' => $chatbotConfig->isEnabled(),
                'botName' => $chatbotConfig->getBotName(),
                'personality' => $chatbotConfig->getPersonality(),
                'triggerMode' => $chatbotConfig->getTriggerMode(),
                'triggerKeywords' => $chatbotConfig->getTriggerKeywords(),
                'subjectContext' => $chatbotConfig->getSubjectContext(),
                'language' => $chatbotConfig->getLanguage(),
                'maxResponseLength' => $chatbotConfig->getMaxResponseLength(),
            ] : null,
            'is_admin' => $currentUserRole === 'admin',
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

        /** @var User|null $user */
        $user = $this->getUser();
        $sort = $request->query->get('sort', 'date');
        
        // Validate sort option
        if (!in_array($sort, self::VALID_SORT_OPTIONS, true)) {
            $sort = 'date';
        }

        // Get member roles map for quick lookup
        $members = $this->memberRepository->findByGroup($group);
        $userRolesMap = [];
        foreach ($members as $member) {
            if ($mUser = $member->getUser()) {
                $userRolesMap[$mUser->getId()] = $member->getMemberRole();
            }
        }
        
        $currentUserRole = $user ? ($userRolesMap[$user->getId()] ?? null) : null;

        // Get group posts with stats (optimized - fixes N+1)
        $postsWithStats = $this->postRepository->findByGroupWithStats($group, $user, $sort);
        
        // Format posts for template
        $posts = [];
        foreach ($postsWithStats as $postData) {
            $post = $postData['post'];
            $author = $post->getAuthor();
            $authorId = $author ? $author->getId() : 0;
            if (!$authorId) continue;
            
            // Check if user can delete this post
            $canDelete = false;
            if ($user) {
                $canDelete = ($author->getId() === $user->getId()) || ($currentUserRole === 'admin');
            }
            
            $posts[] = [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'body' => $post->getBody(),
                'post_type' => $post->getPostType(),
                'attachment_url' => $post->getAttachmentUrl(),
                'author' => $this->formattingService->formatUserName($author ),
                'author_initials' => $this->formattingService->getInitials($this->formattingService->formatUserName($author )),
                'author_role' => $userRolesMap[$authorId] ?? 'member',
                'created_at' => $this->formattingService->formatTimeAgo($post->getCreatedAt() ?? new \DateTimeImmutable()),
                'likes_count' => $postData['likesCount'],
                'comments_count' => $postData['commentsCount'],
                'avg_rating' => $postData['avgRating'],
                'user_liked' => $postData['userLiked'],
                'user_rating' => $postData['userRating'],
                'can_delete' => $canDelete,
            ];
        }

        return $this->render('fo/fragments/_posts_list.html.twig', [
            'posts' => $posts,
            'user' => $this->formattingService->formatUserForView($user),
        ]);
    }

    #[Route('/app/groupes/{groupId}/membres/{userId}/role', name: 'app_group_member_change_role', methods: ['POST'])]
    public function changeMemberRole(int $groupId, int $userId, Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->errorResponse('Non authentifié', Response::HTTP_UNAUTHORIZED);
        }

        // Validate user ID
        if (!$this->groupInputValidator->isValidId($userId)) {
            return $this->errorResponse('ID utilisateur invalide');
        }

        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            return $this->errorResponse('Groupe non trouvé', Response::HTTP_NOT_FOUND);
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('group-member-' . $groupId, (string) $request->request->get('_token'))) {
            return $this->errorResponse('Token CSRF invalide', Response::HTTP_FORBIDDEN);
        }

        // Validate role
        $newRole = $request->request->get('role');
        if (!in_array($newRole, self::VALID_MEMBER_ROLES, true)) {
            return $this->errorResponse('Rôle invalide');
        }
        
        // Get the user to promote/demote
        $memberUser = $this->userRepository->find($userId);
        if (!$memberUser) {
            return $this->errorResponse('Utilisateur non trouvé', Response::HTTP_NOT_FOUND);
        }

        try {
            $isAppAdmin = $this->isGranted('ROLE_ADMIN');
            $this->groupService->changeMemberRole($group, $memberUser, $newRole, $currentUser, $isAppAdmin);
            
            return $this->successResponse([
                'user_id' => $userId,
                'new_role' => $newRole,
            ], 'Rôle du membre mis à jour');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_FORBIDDEN);
        }
    }

    #[Route('/app/groupes/{groupId}/membres/{userId}/remove', name: 'app_group_member_remove', methods: ['POST'])]
    public function removeMember(int $groupId, int $userId, Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->errorResponse('Non authentifié', Response::HTTP_UNAUTHORIZED);
        }

        // Validate user ID
        if (!$this->groupInputValidator->isValidId($userId)) {
            return $this->errorResponse('ID utilisateur invalide');
        }

        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            return $this->errorResponse('Groupe non trouvé', Response::HTTP_NOT_FOUND);
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('group-member-' . $groupId, (string) $request->request->get('_token'))) {
            return $this->errorResponse('Token CSRF invalide', Response::HTTP_FORBIDDEN);
        }

        // Get the user to remove
        $memberUser = $this->userRepository->find($userId);
        if (!$memberUser) {
            return $this->errorResponse('Utilisateur non trouvé', Response::HTTP_NOT_FOUND);
        }

        try {
            $isAppAdmin = $this->isGranted('ROLE_ADMIN');
            $this->groupService->removeMember($group, $memberUser, $currentUser, $isAppAdmin);
            
            return $this->successResponse([
                'user_id' => $userId,
            ], 'Membre retiré du groupe');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_FORBIDDEN);
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
            return $this->errorResponse('Groupe non trouvé', Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->errorResponse('Non authentifié', Response::HTTP_UNAUTHORIZED);
        }

        // CSRF validation
        if (!$this->isCsrfTokenValid('create-post-' . $id, (string) $request->request->get('_token'))) {
            return $this->errorResponse('Token CSRF invalide', Response::HTTP_FORBIDDEN);
        }

        try {
            $dto = new PostCreateDTO();
            $dto->title = trim((string) $request->request->get('title', ''));
            // Sanitize the body content to prevent XSS attacks
            $rawBody = trim((string) $request->request->get('body', ''));
            $dto->body = $this->contentSanitizer->sanitizeRich($rawBody);
            $dto->postType = (string) $request->request->get('post_type', 'text');
            $dto->attachmentUrl = trim((string) $request->request->get('attachment_url', ''));

            // Handle file upload (take first file if multiple)
            $files = $request->files->get('files');
            if (is_array($files) && count($files) > 0) {
                $dto->file = $files[0];
            } elseif ($files instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $dto->file = $files;
            }

            // Validate DTO using Symfony Validator
            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse(implode(', ', $errorMessages));
            }

            $post = $this->postService->createPost($group, $user, $dto);

            // Get post stats
            $stats = $this->postInteractionService->getPostStats($post, $user);

            // Get user's role in the group
            $membership = $this->memberRepository->findOneBy(['group' => $group, 'user' => $user]);
            $userRole = $membership ? $membership->getMemberRole() : 'member';

            // Generate CSRF tokens for the new post interactions
            $tokens = [
                'like' => $this->csrfTokenManager->getToken('like-post-' . $post->getId())->getValue(),
                'rate' => $this->csrfTokenManager->getToken('rate-post-' . $post->getId())->getValue(),
                'delete' => $this->csrfTokenManager->getToken('delete-post-' . $post->getId())->getValue(),
                'comment' => $this->csrfTokenManager->getToken('comment-post-' . $post->getId())->getValue(),
            ];

            // Build author data with role
            $authorData = $this->formattingService->formatUserForView($user);
            $authorData['role'] = $userRole;

            // Build attachment info for file posts
            $attachmentName = null;
            if ($post->getPostType() === 'file' && $post->getAttachmentUrl()) {
                // Extract original-ish name: remove the uniqid suffix
                $basename = basename($post->getAttachmentUrl());
                $attachmentName = $basename;
                // For the original file name from DTO
                if ($dto->file) {
                    $attachmentName = $dto->file->getClientOriginalName();
                }
            }

            $responseData = [
                'csrf_tokens' => $tokens,
                'post' => [
                    'id' => $post->getId(),
                    'title' => $post->getTitle(),
                    'body' => $post->getBody(),
                    'type' => $post->getPostType(),
                    'attachmentUrl' => $post->getAttachmentUrl(),
                    'attachmentName' => $attachmentName,
                    'author' => $authorData,
                    'createdAt' => ($post->getCreatedAt() ?? new \DateTimeImmutable())->format('c'),
                    'timeAgo' => $this->formattingService->formatTimeAgo($post->getCreatedAt() ?? new \DateTimeImmutable()),
                    'stats' => $stats,
                    'canDelete' => true,
                ]
            ];

            // Trigger chatbot auto-comment on new post
            try {
                $botComment = $this->chatbotService->processNewPost($post, $group, $user);
                if ($botComment) {
                    $responseData['botReply'] = [
                        'id' => $botComment->getId(),
                        'body' => $botComment->getBody(),
                        'isBot' => true,
                        'botName' => $botComment->getBotName(),
                        'author' => [
                            'name' => $botComment->getBotName() ?? 'StudyBot',
                            'initials' => 'AI',
                        ],
                        'createdAt' => $botComment->getCreatedAt()?->format('c') ?? date('c'),
                        'timeAgo' => 'à l\'instant',
                        'canDelete' => false,
                    ];
                }
            } catch (\Exception $e) {
                // Bot reply is not critical - silently fail
            }

            return $this->successResponse($responseData, 'Post créé avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Delete a post (AJAX)
     */
    #[Route('/app/groupes/{groupId}/posts/{postId}/delete', name: 'app_post_delete', methods: ['POST'])]
    public function deletePost(int $groupId, int $postId, Request $request): JsonResponse
    {
        // Validate post ID
        if (!$this->groupInputValidator->isValidId($postId)) {
            return $this->errorResponse('ID de post invalide');
        }

        $post = $this->postRepository->find($postId);
        if (!$post) {
            return $this->errorResponse('Post non trouvé', Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->errorResponse('Non authentifié', Response::HTTP_UNAUTHORIZED);
        }

        // CSRF validation
        if (!$this->isCsrfTokenValid('delete-post-' . $postId, (string) $request->request->get('_token'))) {
            return $this->errorResponse('Token CSRF invalide', Response::HTTP_FORBIDDEN);
        }

        try {
            $this->postService->deletePost($post, $user);
            return $this->successResponse([], 'Post supprimé avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_FORBIDDEN);
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
            return $this->errorResponse('Post non trouvé', Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->errorResponse('Non authentifié', Response::HTTP_UNAUTHORIZED);
        }

        // CSRF validation
        if (!$this->isCsrfTokenValid('like-post-' . $id, (string) $request->request->get('_token'))) {
            return $this->errorResponse('Token CSRF invalide', Response::HTTP_FORBIDDEN);
        }

        try {
            $result = $this->postInteractionService->toggleLike($post, $user);
            return $this->successResponse([
                'liked' => $result['liked'],
                'likesCount' => $result['likesCount']
            ], $result['liked'] ? 'Post aimé' : 'Like retiré');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_FORBIDDEN);
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
            return $this->errorResponse('Post non trouvé', Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->errorResponse('Non authentifié', Response::HTTP_UNAUTHORIZED);
        }

        // CSRF validation
        if (!$this->isCsrfTokenValid('rate-post-' . $id, (string) $request->request->get('_token'))) {
            return $this->errorResponse('Token CSRF invalide', Response::HTTP_FORBIDDEN);
        }

        // Validate rating input
        $ratingInput = $request->request->get('rating');
        if (!is_numeric($ratingInput)) {
            return $this->errorResponse('Note invalide');
        }
        
        $rating = (int) $ratingInput;
        if ($rating < 1 || $rating > 5) {
            return $this->errorResponse('La note doit être entre 1 et 5');
        }

        try {
            $result = $this->postInteractionService->ratePost($post, $user, $rating);
            return $this->successResponse([
                'userRating' => $result['userRating'],
                'averageRating' => $result['averageRating'],
                'ratingsCount' => $result['ratingsCount']
            ], 'Note enregistrée');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
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
            return $this->errorResponse('Post non trouvé', Response::HTTP_NOT_FOUND);
        }

        /** @var User|null $user */
        $user = $this->getUser();

        $comments = $this->commentRepository->findByPostWithReplies($post);
        
        $commentsData = array_map(function($comment) use ($user, $post) {
            $author = $comment->getAuthor();
            $group = $post->getGroup();
            $canDelete = $user && $author && ($author->getId() === $user->getId() || 
                ($group && $this->groupService->isGroupAdmin($group, $user)));
            
            $data = [
                'id' => $comment->getId(),
                'body' => $comment->getBody(),
                'author' => $comment->isBot()
                    ? ['name' => $comment->getBotName() ?? 'StudyBot', 'initials' => 'AI']
                    : $this->formattingService->formatUserForView($author),
                'parentId' => $comment->getParentComment()?->getId(),
                'parentAuthor' => $comment->getParentComment()
                    ? ($comment->getParentComment()->isBot()
                        ? ($comment->getParentComment()->getBotName() ?? 'StudyBot')
                        : ($comment->getParentComment()->getAuthor() ? $comment->getParentComment()->getAuthor()->getFullName() : 'Utilisateur'))
                    : null,
                'createdAt' => $comment->getCreatedAt()?->format('c') ?? date('c'),
                'timeAgo' => $this->formattingService->formatTimeAgo($comment->getCreatedAt() ?? new \DateTimeImmutable()),
                'canDelete' => $canDelete,
                'isBot' => $comment->isBot(),
                'botName' => $comment->getBotName(),
            ];
            
            // Include delete token only if user can delete
            if ($canDelete) {
                $data['deleteToken'] = $this->csrfTokenManager->getToken('delete-comment-' . $comment->getId())->getValue();
            }
            
            return $data;
        }, $comments);

        return $this->successResponse(['comments' => $commentsData]);
    }

    /**
     * Create a comment on a post (AJAX)
     */
    #[Route('/app/posts/{id}/comments/create', name: 'app_comment_create', methods: ['POST'])]
    public function createComment(int $id, Request $request): JsonResponse
    {
        $post = $this->postRepository->find($id);
        if (!$post) {
            return $this->errorResponse('Post non trouvé', Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->errorResponse('Non authentifié', Response::HTTP_UNAUTHORIZED);
        }

        // CSRF validation
        if (!$this->isCsrfTokenValid('comment-post-' . $id, (string) (string) $request->request->get('_token'))) {
            return $this->errorResponse('Token CSRF invalide', Response::HTTP_FORBIDDEN);
        }

        // Validate comment body
        $body = trim((string) $request->request->get('body', ''));
        if (empty($body)) {
            return $this->errorResponse('Le commentaire ne peut pas être vide');
        }
        
        try {
            // Validate comment body length using GroupInputValidator
            $this->groupInputValidator->validateCommentBody($body);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage());
        }
        
        // Sanitize the comment body to prevent XSS
        $body = $this->contentSanitizer->sanitizePlain($body);
        
        // Validate parent ID if provided
        $parent = null;
        $parentId = $request->request->get('parent_id');
        if ($parentId !== null && $parentId !== '') {
            if (!is_numeric($parentId)) {
                return $this->errorResponse('ID de commentaire parent invalide');
            }
            $parent = $this->commentRepository->find((int) $parentId);
            if ($parentId && !$parent) {
                return $this->errorResponse('Commentaire parent non trouvé', Response::HTTP_NOT_FOUND);
            }
        }

        try {
            $comment = $this->postInteractionService->addComment($post, $user, $body, $parent);

            $responseData = [
                'comment' => [
                    'id' => $comment->getId(),
                    'body' => $comment->getBody(),
                    'author' => $this->formattingService->formatUserForView($user),
                    'parentId' => $parent?->getId(),
                    'parentAuthor' => $parent
                        ? ($parent->isBot()
                            ? ($parent->getBotName() ?? 'StudyBot')
                            : ($parent->getAuthor() ? $parent->getAuthor()->getFullName() : 'Utilisateur'))
                        : null,
                    'createdAt' => ($comment->getCreatedAt() ?? new \DateTimeImmutable())->format('c'),
                    'timeAgo' => $this->formattingService->formatTimeAgo($comment->getCreatedAt() ?? new \DateTimeImmutable()),
                    'canDelete' => true,
                    'isBot' => false,
                    'botName' => null,
                    'deleteToken' => $this->csrfTokenManager->getToken('delete-comment-' . $comment->getId())->getValue(),
                ]
            ];

            // Trigger chatbot auto-reply if applicable
            try {
                $groupForBot = $post->getGroup();
                if (!$groupForBot) throw new \RuntimeException('Groupe non trouvé');

                $isReplyToBot = $parent && $parent->isBot();
                $botComment = $this->chatbotService->processComment(
                    $body,
                    $post,
                    $groupForBot,
                    $user,
                    $isReplyToBot ? $comment : null // Pass the member's comment as parent for threading
                );
                if ($botComment) {
                    $responseData['botReply'] = [
                        'id' => $botComment->getId(),
                        'body' => $botComment->getBody(),
                        'isBot' => true,
                        'botName' => $botComment->getBotName(),
                        'parentId' => $botComment->getParentComment()?->getId(),
                        'parentAuthor' => $user->getFullName(),
                        'author' => [
                            'name' => $botComment->getBotName() ?? 'StudyBot',
                            'initials' => 'AI',
                        ],
                        'createdAt' => $botComment->getCreatedAt()?->format('c') ?? date('c'),
                        'timeAgo' => 'à l\'instant',
                        'canDelete' => false,
                    ];
                }
            } catch (\Exception $e) {
                // Silently fail - bot reply is not critical
            }

            return $this->successResponse($responseData, 'Commentaire ajouté');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
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
            return $this->errorResponse('Commentaire non trouvé', Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->errorResponse('Non authentifié', Response::HTTP_UNAUTHORIZED);
        }

        // CSRF validation
        if (!$this->isCsrfTokenValid('delete-comment-' . $id, (string) $request->request->get('_token'))) {
            return $this->errorResponse('Token CSRF invalide', Response::HTTP_FORBIDDEN);
        }

        try {
            $this->postInteractionService->deleteComment($comment, $user);
            return $this->successResponse([], 'Commentaire supprimé');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_FORBIDDEN);
        }
    }

    // ==================== TRANSLATION API ====================

    /**
     * API: Translate a post's text content via Lingva Translate (external API)
     */
    #[Route('/app/api/translate', name: 'app_api_translate', methods: ['POST'])]
    public function translatePost(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->errorResponse('Non authentifié', Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        $text = $data['text'] ?? '';
        $targetLang = $data['target'] ?? 'fr';
        $sourceLang = $data['source'] ?? 'auto';

        if (empty(trim($text))) {
            return $this->errorResponse('Texte vide');
        }

        // Validate target language
        $supportedLangs = array_keys($this->translationService->getSupportedLanguages());
        if (!in_array($targetLang, $supportedLangs, true)) {
            return $this->errorResponse('Langue cible non supportée');
        }

        $translation = $this->translationService->translate($text, $targetLang, $sourceLang);

        if ($translation === null) {
            return $this->errorResponse('Échec de la traduction. Veuillez réessayer.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json([
            'success' => true,
            'translation' => $translation,
            'source' => $sourceLang,
            'target' => $targetLang,
        ]);
    }

    /**
     * API: Get supported translation languages
     */
    #[Route('/app/api/translate/languages', name: 'app_api_translate_languages', methods: ['GET'])]
    public function getTranslationLanguages(): JsonResponse
    {
        return $this->json([
            'languages' => $this->translationService->getSupportedLanguages(),
        ]);
    }
}

