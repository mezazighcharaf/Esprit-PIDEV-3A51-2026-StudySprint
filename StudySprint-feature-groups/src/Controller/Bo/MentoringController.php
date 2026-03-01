<?php

namespace App\Controller\Bo;

use App\Repository\GroupInvitationRepository;
use App\Repository\PostCommentRepository;
use App\Repository\PostLikeRepository;
use App\Repository\PostRatingRepository;
use App\Repository\StudyGroupRepository;
use App\Service\FormattingService;
use App\Service\Mock\BoMockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MentoringController extends AbstractController
{
    private const ACTIVITY_LIMIT = 80;

    public function __construct(
        private BoMockDataProvider $mockProvider,
        private StudyGroupRepository $groupRepository,
        private GroupInvitationRepository $invitationRepository,
        private PostRatingRepository $ratingRepository,
        private PostLikeRepository $likeRepository,
        private PostCommentRepository $commentRepository,
        private FormattingService $formattingService,
    ) {}

    #[Route('/admin/encadrement', name: 'admin_mentoring')]
    public function index(Request $request): Response
    {
        $state = $request->query->get('state', 'default');

        $data = $this->mockProvider->getMentoringData();
        $groupEntities = $this->groupRepository->findAllOrderByCreatedAt();
        $invitations = $this->invitationRepository->findAllOrderByInvitedAtDesc();
        $ratings = $this->ratingRepository->findAllOrderByCreatedAtDesc(self::ACTIVITY_LIMIT);
        $likes = $this->likeRepository->findAllOrderByCreatedAtDesc(self::ACTIVITY_LIMIT);
        $comments = $this->commentRepository->findAllOrderByCreatedAtDesc(self::ACTIVITY_LIMIT);

        $groups = [];
        foreach ($groupEntities as $group) {
            $members = $group->getMembers()->toArray();
            $groups[] = [
                'id' => $group->getId(),
                'name' => $group->getName(),
                'description' => $group->getDescription(),
                'subject' => $group->getSubject(),
                'privacy' => $group->getPrivacy(),
                'createdBy' => $group->getCreatedBy(),
                'members' => $this->formattingService->formatGroupMembers($members),
            ];
        }

        $feedbacks = [];

        foreach ($ratings as $rating) {
            $post = $rating->getPost();
            $author = $post->getAuthor();
            $group = $post->getGroup();
            $rater = $rating->getUser();
            $postExcerpt = $post->getTitle() ?: $this->formattingService->truncateText($post->getBody() ?? '', 80);
            $feedbacks[] = [
                'type' => 'rating',
                'sort_at' => $rating->getCreatedAt()->getTimestamp(),
                'id' => $rating->getId(),
                'from' => $this->formattingService->formatUserName($rater),
                'from_initials' => $this->formattingService->getInitials($this->formattingService->formatUserName($rater)),
                'to' => $this->formattingService->formatUserName($author),
                'group' => $group->getName(),
                'group_id' => $group->getId(),
                'post_id' => $post->getId(),
                'rating' => $rating->getRating(),
                'comment' => $postExcerpt,
                'date' => $this->formattingService->formatTimeAgo($rating->getCreatedAt()),
            ];
        }

        foreach ($likes as $like) {
            $post = $like->getPost();
            $author = $post->getAuthor();
            $group = $post->getGroup();
            $user = $like->getUser();
            $postExcerpt = $post->getTitle() ?: $this->formattingService->truncateText($post->getBody() ?? '', 80);
            $feedbacks[] = [
                'type' => 'like',
                'sort_at' => $like->getCreatedAt()->getTimestamp(),
                'id' => $like->getId(),
                'from' => $this->formattingService->formatUserName($user),
                'from_initials' => $this->formattingService->getInitials($this->formattingService->formatUserName($user)),
                'to' => $this->formattingService->formatUserName($author),
                'group' => $group->getName(),
                'group_id' => $group->getId(),
                'post_id' => $post->getId(),
                'comment' => $postExcerpt,
                'date' => $this->formattingService->formatTimeAgo($like->getCreatedAt()),
            ];
        }

        foreach ($comments as $comment) {
            $post = $comment->getPost();
            $postAuthor = $post->getAuthor();
            $group = $post->getGroup();
            $author = $comment->getAuthor();
            $feedbacks[] = [
                'type' => 'comment',
                'sort_at' => $comment->getCreatedAt()->getTimestamp(),
                'id' => $comment->getId(),
                'from' => $this->formattingService->formatUserName($author),
                'from_initials' => $this->formattingService->getInitials($this->formattingService->formatUserName($author)),
                'to' => $this->formattingService->formatUserName($postAuthor),
                'group' => $group->getName(),
                'group_id' => $group->getId(),
                'post_id' => $post->getId(),
                'body' => $this->formattingService->truncateText($comment->getBody() ?? '', 120),
                'date' => $this->formattingService->formatTimeAgo($comment->getCreatedAt()),
            ];
        }

        usort($feedbacks, fn ($a, $b) => $b['sort_at'] <=> $a['sort_at']);
        $feedbacks = array_slice($feedbacks, 0, self::ACTIVITY_LIMIT);

        $data['groups'] = $groups;
        $data['invitations'] = $invitations;
        $data['feedbacks'] = $feedbacks;
        $data['tabs'][0]['count'] = \count($groups);
        $data['tabs'][1]['count'] = \count($invitations);
        $data['tabs'][2]['count'] = \count($feedbacks);

        $viewModel = [
            'state' => $state,
            'data' => $data,
        ];

        return $this->render('bo/mentoring.html.twig', $viewModel);
    }
}
