<?php

namespace App\Controller\Fo;

use App\Entity\GroupPost;
use App\Entity\StudyGroup;
use App\Entity\GroupMember;
use App\Form\Fo\StudyGroupType;
use App\Repository\StudyGroupRepository;
use App\Repository\GroupPostRepository;
use App\Repository\GroupMemberRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\AiGatewayService;
use App\Service\QrCodeService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/fo/groups', name: 'fo_groups_')]
class GroupsController extends AbstractController
{
    private const POSTS_PER_PAGE = 10;

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        StudyGroupRepository $repository,
        GroupMemberRepository $memberRepo,
        UserRepository $userRepo
    ): Response {
        $currentUser = $this->getUser() ?? $userRepo->findOneBy([]);
        $q = $request->query->get('q');

        // Get user's groups with eager-loaded group to avoid N+1
        if ($currentUser) {
            $myGroupsQb = $memberRepo->createQueryBuilder('m')
                ->join('m.group', 'g')
                ->addSelect('g')
                ->where('m.user = :user')
                ->setParameter('user', $currentUser);
            if ($q) {
                $myGroupsQb->andWhere('g.name LIKE :q OR g.description LIKE :q')
                    ->setParameter('q', '%' . $q . '%');
            }
            $myGroupsMembers = $myGroupsQb->getQuery()->getResult();
        } else {
            $myGroupsMembers = [];
        }
        $myGroups = array_map(fn($m) => $m->getGroup(), $myGroupsMembers);
        $myGroupIds = array_map(fn($g) => $g->getId(), $myGroups);

        // Get other public groups
        $qb = $repository->createQueryBuilder('g')
            ->where('g.privacy = :public')
            ->setParameter('public', StudyGroup::PRIVACY_PUBLIC);

        if (!empty($myGroupIds)) {
            $qb->andWhere('g.id NOT IN (:myGroupIds)')
               ->setParameter('myGroupIds', $myGroupIds);
        }

        if ($q) {
            $qb->andWhere('g.name LIKE :q OR g.description LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        $otherGroups = $qb->getQuery()->getResult();

        return $this->render('fo/groups/index.html.twig', [
            'myGroups' => $myGroups,
            'otherGroups' => $otherGroups,
            'currentUser' => $currentUser,
            'q' => $q,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        int $id,
        Request $request,
        StudyGroupRepository $groupRepo,
        GroupPostRepository $postRepo,
        UserRepository $userRepo
    ): Response {
        $group = $groupRepo->find($id);

        if (!$group) {
            throw $this->createNotFoundException('Groupe introuvable');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * self::POSTS_PER_PAGE;

        // Get paginated root posts with author + replies eager-loaded to avoid N+1
        $posts = $postRepo->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->leftJoin('p.replies', 'r')
            ->addSelect('r')
            ->leftJoin('r.author', 'ra')
            ->addSelect('ra')
            ->andWhere('p.group = :group')
            ->andWhere('p.parentPost IS NULL')
            ->setParameter('group', $group)
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('r.createdAt', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults(self::POSTS_PER_PAGE)
            ->getQuery()
            ->getResult();

        // Count total for pagination
        $totalPosts = (int) $postRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.group = :group')
            ->andWhere('p.parentPost IS NULL')
            ->setParameter('group', $group)
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = ceil($totalPosts / self::POSTS_PER_PAGE);

        // Get current user for moderation checks
        $currentUser = $this->getUser() ?? $userRepo->findOneBy([]);
        $isOwnerOrAdmin = $currentUser && $this->canModerate($group, $currentUser);

        return $this->render('fo/groups/show.html.twig', [
            'group' => $group,
            'posts' => $posts,
            'currentUser' => $currentUser,
            'isOwnerOrAdmin' => $isOwnerOrAdmin,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/{id}/post', name: 'create_post', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function createPost(
        int $id,
        Request $request,
        StudyGroupRepository $groupRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $group = $groupRepo->find($id);

        if (!$group) {
            throw $this->createNotFoundException('Groupe introuvable');
        }

        if (!$this->isCsrfTokenValid('create_post_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $id]);
        }

        $body = trim($request->request->get('body', ''));
        $title = trim($request->request->get('title', ''));

        // Server-side validation
        if (empty($body)) {
            $this->addFlash('error', 'Le contenu du post ne peut pas être vide.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $id]);
        }

        if (mb_strlen($body) > 5000) {
            $this->addFlash('error', 'Le contenu du post ne peut pas dépasser 5000 caractères.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $id]);
        }

        if ($title !== '' && mb_strlen($title) > 255) {
            $this->addFlash('error', 'Le titre ne peut pas dépasser 255 caractères.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $id]);
        }

        $author = $this->getUser() ?? $userRepo->findOneBy([]);
        if (!$author) {
            $this->addFlash('error', 'Aucun utilisateur disponible.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $id]);
        }

        $post = new GroupPost();
        $post->setGroup($group);
        $post->setAuthor($author);
        $post->setBody($body);
        $post->setTitle($title !== '' ? $title : null);
        $post->setPostType(GroupPost::TYPE_POST);

        $em->persist($post);
        $em->flush();

        $this->addFlash('success', 'Post créé avec succès !');
        return $this->redirectToRoute('fo_groups_show', ['id' => $id]);
    }

    #[Route('/{groupId}/post/{postId}/comment', name: 'create_comment', methods: ['POST'], requirements: ['groupId' => '\d+', 'postId' => '\d+'])]
    public function createComment(
        int $groupId,
        int $postId,
        Request $request,
        StudyGroupRepository $groupRepo,
        GroupPostRepository $postRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $group = $groupRepo->find($groupId);
        $parentPost = $postRepo->find($postId);

        if (!$group || !$parentPost) {
            throw $this->createNotFoundException('Groupe ou post introuvable');
        }

        if (!$this->isCsrfTokenValid('create_comment_' . $postId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
        }

        $body = trim($request->request->get('body', ''));

        if (empty($body)) {
            $this->addFlash('error', 'Le commentaire ne peut pas être vide.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
        }

        if (mb_strlen($body) > 2000) {
            $this->addFlash('error', 'Le commentaire ne peut pas dépasser 2000 caractères.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
        }

        $author = $this->getUser() ?? $userRepo->findOneBy([]);
        if (!$author) {
            $this->addFlash('error', 'Aucun utilisateur disponible.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
        }

        $comment = new GroupPost();
        $comment->setGroup($group);
        $comment->setAuthor($author);
        $comment->setBody($body);
        $comment->setPostType(GroupPost::TYPE_COMMENT);
        $comment->setParentPost($parentPost);

        $em->persist($comment);
        $em->flush();

        $this->addFlash('success', 'Commentaire ajouté !');
        return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
    }

    #[Route('/{groupId}/post/{postId}/delete', name: 'delete_post', methods: ['POST'], requirements: ['groupId' => '\d+', 'postId' => '\d+'])]
    public function deletePost(
        int $groupId,
        int $postId,
        Request $request,
        StudyGroupRepository $groupRepo,
        GroupPostRepository $postRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $group = $groupRepo->find($groupId);
        $post = $postRepo->find($postId);

        if (!$group || !$post) {
            throw $this->createNotFoundException('Groupe ou post introuvable');
        }

        if (!$this->isCsrfTokenValid('delete_post_' . $postId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
        }

        $currentUser = $this->getUser() ?? $userRepo->findOneBy([]);

        // Check permissions: author can delete own, owner/admin can delete any
        $canDelete = $currentUser && (
            $post->getAuthor()->getId() === $currentUser->getId() ||
            $this->canModerate($group, $currentUser)
        );

        if (!$canDelete) {
            $this->addFlash('error', 'Vous n\'avez pas la permission de supprimer ce post.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
        }

        $em->remove($post);
        $em->flush();

        $this->addFlash('success', 'Post supprimé.');
        return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
    }

    // === STUDY GROUP CRUD ===

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function newGroup(Request $request, UserRepository $userRepo, EntityManagerInterface $em): Response
    {
        $group = new StudyGroup();
        $form = $this->createForm(StudyGroupType::class, $group);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentUser = $this->getUser() ?? $userRepo->findOneBy([]);
            if (!$currentUser) {
                $this->addFlash('error', 'Utilisateur non connecté.');
                return $this->redirectToRoute('fo_groups_index');
            }

            $group->setCreatedBy($currentUser);
            $em->persist($group);

            // Add creator as admin member
            $member = new GroupMember();
            $member->setGroup($group);
            $member->setUser($currentUser);
            $member->setMemberRole(GroupMember::ROLE_ADMIN);
            $em->persist($member);

            $em->flush();

            $this->addFlash('success', 'Groupe créé avec succès !');
            return $this->redirectToRoute('fo_groups_show', ['id' => $group->getId()]);
        }

        return $this->render('fo/groups/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function editGroup(int $id, Request $request, StudyGroupRepository $repository, EntityManagerInterface $em): Response
    {
        $group = $repository->find($id);
        
        if (!$group) {
            throw $this->createNotFoundException('Groupe introuvable');
        }

        $currentUser = $this->getUser();
        if (!$this->canModerate($group, $currentUser)) {
            $this->addFlash('error', 'Vous n\'avez pas la permission de modifier ce groupe.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $id]);
        }

        $form = $this->createForm(StudyGroupType::class, $group);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Groupe modifié avec succès !');
            return $this->redirectToRoute('fo_groups_show', ['id' => $group->getId()]);
        }

        return $this->render('fo/groups/edit.html.twig', [
            'group' => $group,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteGroup(int $id, Request $request, StudyGroupRepository $repository, EntityManagerInterface $em): Response
    {
        $group = $repository->find($id);
        
        if (!$group) {
            throw $this->createNotFoundException('Groupe introuvable');
        }

        $currentUser = $this->getUser();
        if (!$this->canModerate($group, $currentUser)) {
            $this->addFlash('error', 'Vous n\'avez pas la permission de supprimer ce groupe.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $id]);
        }

        if ($this->isCsrfTokenValid('delete_group_' . $group->getId(), $request->request->get('_token'))) {
            $em->remove($group);
            $em->flush();
            $this->addFlash('success', 'Groupe supprimé avec succès !');
        }

        return $this->redirectToRoute('fo_groups_index');
    }

    // === MEMBER MANAGEMENT ===

    #[Route('/{id}/join', name: 'join', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function joinGroup(
        int $id,
        Request $request,
        StudyGroupRepository $groupRepo,
        GroupMemberRepository $memberRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $group = $groupRepo->find($id);
        
        if (!$group) {
            throw $this->createNotFoundException('Groupe introuvable');
        }

        if (!$this->isCsrfTokenValid('join_group_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $id]);
        }

        $currentUser = $this->getUser() ?? $userRepo->findOneBy([]);
        if (!$currentUser) {
            $this->addFlash('error', 'Utilisateur non connecté.');
            return $this->redirectToRoute('fo_groups_index');
        }

        // Check if already member
        $existingMember = $memberRepo->findOneBy([
            'group' => $group,
            'user' => $currentUser
        ]);

        if ($existingMember) {
            $this->addFlash('info', 'Vous êtes déjà membre de ce groupe.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $id]);
        }

        // Check privacy
        if ($group->getPrivacy() === StudyGroup::PRIVACY_PRIVATE) {
            $this->addFlash('error', 'Ce groupe est privé. Vous devez être invité pour rejoindre.');
            return $this->redirectToRoute('fo_groups_index');
        }

        $member = new GroupMember();
        $member->setGroup($group);
        $member->setUser($currentUser);
        $member->setMemberRole(GroupMember::ROLE_MEMBER);

        $em->persist($member);
        $em->flush();

        $this->addFlash('success', 'Vous avez rejoint le groupe !');
        return $this->redirectToRoute('fo_groups_show', ['id' => $id]);
    }

    #[Route('/{id}/leave', name: 'leave', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function leaveGroup(
        int $id,
        Request $request,
        StudyGroupRepository $groupRepo,
        GroupMemberRepository $memberRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $group = $groupRepo->find($id);
        
        if (!$group) {
            throw $this->createNotFoundException('Groupe introuvable');
        }

        if (!$this->isCsrfTokenValid('leave_group_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $id]);
        }

        $currentUser = $this->getUser() ?? $userRepo->findOneBy([]);
        if (!$currentUser) {
            $this->addFlash('error', 'Utilisateur non connecté.');
            return $this->redirectToRoute('fo_groups_index');
        }

        // Cannot leave if creator
        if ($group->getCreatedBy()->getId() === $currentUser->getId()) {
            $this->addFlash('error', 'Le créateur ne peut pas quitter le groupe. Supprimez-le ou transférez la propriété.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $id]);
        }

        $member = $memberRepo->findOneBy([
            'group' => $group,
            'user' => $currentUser
        ]);

        if (!$member) {
            $this->addFlash('info', 'Vous n\'êtes pas membre de ce groupe.');
            return $this->redirectToRoute('fo_groups_index');
        }

        $em->remove($member);
        $em->flush();

        $this->addFlash('success', 'Vous avez quitté le groupe.');
        return $this->redirectToRoute('fo_groups_index');
    }

    #[Route('/{groupId}/members/{memberId}/promote', name: 'promote_member', methods: ['POST'], requirements: ['groupId' => '\d+', 'memberId' => '\d+'])]
    public function promoteMember(
        int $groupId,
        int $memberId,
        Request $request,
        StudyGroupRepository $groupRepo,
        GroupMemberRepository $memberRepo,
        EntityManagerInterface $em
    ): Response {
        $group = $groupRepo->find($groupId);
        $member = $memberRepo->find($memberId);
        
        if (!$group || !$member) {
            throw $this->createNotFoundException('Groupe ou membre introuvable');
        }

        $currentUser = $this->getUser();
        if (!$this->canModerate($group, $currentUser)) {
            $this->addFlash('error', 'Permission refusée.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
        }

        if (!$this->isCsrfTokenValid('promote_member_' . $memberId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
        }

        $member->setMemberRole(GroupMember::ROLE_ADMIN);
        $em->flush();

        $this->addFlash('success', 'Membre promu administrateur.');
        return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
    }

    #[Route('/{groupId}/members/{memberId}/kick', name: 'kick_member', methods: ['POST'], requirements: ['groupId' => '\d+', 'memberId' => '\d+'])]
    public function kickMember(
        int $groupId,
        int $memberId,
        Request $request,
        StudyGroupRepository $groupRepo,
        GroupMemberRepository $memberRepo,
        EntityManagerInterface $em
    ): Response {
        $group = $groupRepo->find($groupId);
        $member = $memberRepo->find($memberId);
        
        if (!$group || !$member) {
            throw $this->createNotFoundException('Groupe ou membre introuvable');
        }

        $currentUser = $this->getUser();
        if (!$this->canModerate($group, $currentUser)) {
            $this->addFlash('error', 'Permission refusée.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
        }

        if (!$this->isCsrfTokenValid('kick_member_' . $memberId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
        }

        // Cannot kick creator
        if ($member->getUser()->getId() === $group->getCreatedBy()->getId()) {
            $this->addFlash('error', 'Impossible d\'expulser le créateur du groupe.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
        }

        $em->remove($member);
        $em->flush();

        $this->addFlash('success', 'Membre expulsé du groupe.');
        return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
    }

    /**
     * Check if user can moderate (owner or admin of the group)
     */
    private function canModerate(StudyGroup $group, $user): bool
    {
        if (!$user) {
            return false;
        }

        // Creator of the group can always moderate
        if ($group->getCreatedBy()->getId() === $user->getId()) {
            return true;
        }

        // Check if user is admin via roles
        if (method_exists($user, 'getRoles') && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return false;
    }

    #[Route('/{groupId}/post/{postId}/ai-summarize', name: 'ai_summarize_post', methods: ['POST'], requirements: ['groupId' => '\d+', 'postId' => '\d+'])]
    public function aiSummarizePost(
        int $groupId,
        int $postId,
        Request $request,
        GroupPostRepository $postRepo,
        AiGatewayService $aiGateway,
        EntityManagerInterface $em
    ): Response {
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';
        $post = $postRepo->find($postId);

        if (!$post || $post->getGroup()->getId() !== $groupId) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Post introuvable'], 404);
            }
            throw $this->createNotFoundException('Post introuvable');
        }

        $token = $isAjax
            ? (json_decode($request->getContent(), true)['_token'] ?? '')
            : $request->request->get('_token');

        if (!$this->isCsrfTokenValid('ai_summarize_post_'.$postId, $token)) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
            }
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
        }

        // Call FastAPI AI Gateway
        try {
            $data = $aiGateway->summarizePost(
                $post->getAuthor()->getId(),
                $postId
            );

            // Update post with AI data
            $post->setAiSummary($data['summary'] ?? null);
            $post->setAiCategory($data['category'] ?? null);
            $post->setAiTags($data['tags'] ?? []);

            $em->flush();

            if ($isAjax) {
                return new JsonResponse([
                    'success' => true,
                    'summary' => $data['summary'] ?? '',
                    'category' => $data['category'] ?? '',
                    'tags' => $data['tags'] ?? [],
                    'ai_log_id' => $data['ai_log_id'] ?? null,
                ]);
            }

            $this->addFlash('success', 'Résumé IA généré avec succès !');
        } catch (\Exception $e) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Service IA indisponible: ' . $e->getMessage()], 503);
            }
            $this->addFlash('error', 'Impossible de contacter le service IA: ' . $e->getMessage());
        }

        return $this->redirectToRoute('fo_groups_show', ['id' => $groupId]);
    }

    #[Route('/{id}/qrcode', name: 'qrcode', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function qrCode(int $id, StudyGroupRepository $groupRepo, QrCodeService $qrService): Response
    {
        $group = $groupRepo->find($id);
        if (!$group) {
            throw $this->createNotFoundException('Groupe introuvable.');
        }

        $url = $this->generateUrl('fo_groups_show', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL);
        return $qrService->generateResponse($url);
    }
}
