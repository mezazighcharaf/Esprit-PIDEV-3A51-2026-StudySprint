<?php

namespace App\Service;

use App\Entity\GroupPost;
use App\Entity\PostComment;
use App\Entity\PostLike;
use App\Entity\PostRating;
use App\Entity\StudyGroup;
use App\Entity\User;
use App\Enum\GroupRole;
use App\Repository\GroupMemberRepository;
use App\Repository\PostCommentRepository;
use App\Repository\PostLikeRepository;
use App\Repository\PostRatingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PostInteractionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PostLikeRepository $likeRepository,
        private PostCommentRepository $commentRepository,
        private PostRatingRepository $ratingRepository,
        private GroupMemberRepository $memberRepository,
    ) {}

    /**
     * Toggle like on a post (like if not liked, unlike if already liked)
     */
    /**
     * @return array{liked: bool, likesCount: int}
     */
    public function toggleLike(GroupPost $post, User $user): array
    {
        $group = $post->getGroup(); if (!$group) throw new \RuntimeException('Groupe non trouvé'); $this->ensureMember($group, $user);

        $existingLike = $this->likeRepository->findByPostAndUser($post, $user);

        if ($existingLike) {
            // Unlike
            $this->entityManager->remove($existingLike);
            $this->entityManager->flush();
            
            return [
                'liked' => false,
                'likesCount' => $this->likeRepository->countByPost($post)
            ];
        } else {
            // Like
            $like = new PostLike();
            $like->setPost($post);
            $like->setUser($user);
            
            $this->entityManager->persist($like);
            $this->entityManager->flush();
            
            return [
                'liked' => true,
                'likesCount' => $this->likeRepository->countByPost($post)
            ];
        }
    }

    /**
     * Add a comment to a post or reply to a comment
     */
    public function addComment(GroupPost $post, User $author, string $body, ?PostComment $parent = null): PostComment
    {
        $group = $post->getGroup();
        if (!$group) throw new \RuntimeException('Groupe non trouvé');
        $this->ensureMember($group, $author);

        if (empty(trim($body))) {
            throw new \InvalidArgumentException('Le commentaire ne peut pas être vide.');
        }

        $comment = new PostComment();
        $comment->setPost($post);
        $comment->setAuthor($author);
        $comment->setBody(trim($body));
        
        if ($parent) {
            // Ensure parent belongs to same post
            $parentPost = $parent->getPost();
            if (!$parentPost || $parentPost->getId() !== $post->getId()) {
                throw new \InvalidArgumentException('Le commentaire parent n\'appartient pas à ce post.');
            }
            $comment->setParentComment($parent);
        }

        $groupToUpdate = $post->getGroup();
        if ($groupToUpdate) {
            $groupToUpdate->setLastActivity(new \DateTimeImmutable());
        }

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $comment;
    }

    /**
     * Delete a comment
     */
    public function deleteComment(PostComment $comment, User $user): void
    {
        if (!$this->canDeleteComment($comment, $user)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas la permission de supprimer ce commentaire.');
        }

        $this->entityManager->remove($comment);
        $this->entityManager->flush();
    }

    /**
     * Rate a post (1-5 stars)
     */
    /**
     * @return array{userRating: int, averageRating: float, ratingsCount: int}
     */
    public function ratePost(GroupPost $post, User $user, int $rating): array
    {
        $group = $post->getGroup(); if (!$group) throw new \RuntimeException('Groupe non trouvé'); $this->ensureMember($group, $user);

        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('La note doit être entre 1 et 5.');
        }

        $existingRating = $this->ratingRepository->findByPostAndUser($post, $user);

        if ($existingRating) {
            // Update existing rating
            $existingRating->setRating($rating);
        } else {
            // Create new rating
            $existingRating = new PostRating();
            $existingRating->setPost($post);
            $existingRating->setUser($user);
            $existingRating->setRating($rating);
            $this->entityManager->persist($existingRating);
        }

        $this->entityManager->flush();

        return [
            'userRating' => $rating,
            'averageRating' => $this->ratingRepository->getAverageRating($post),
            'ratingsCount' => $this->ratingRepository->countByPost($post)
        ];
    }

    /**
     * Get post statistics
     */
    /**
     * @return array{likesCount: int, commentsCount: int, averageRating: float, ratingsCount: int, userLiked: bool, userRating: int|null}
     */
    public function getPostStats(GroupPost $post, ?User $user = null): array
    {
        $stats = [
            'likesCount' => $this->likeRepository->countByPost($post),
            'commentsCount' => $this->commentRepository->countByPost($post),
            'averageRating' => $this->ratingRepository->getAverageRating($post),
            'ratingsCount' => $this->ratingRepository->countByPost($post),
            'userLiked' => false,
            'userRating' => null,
        ];

        if ($user) {
            $stats['userLiked'] = $this->likeRepository->hasUserLiked($post, $user);
            $stats['userRating'] = $this->ratingRepository->getUserRating($post, $user);
        }

        return $stats;
    }

    /**
     * Check if user can delete a comment
     */
    private function canDeleteComment(PostComment $comment, User $user): bool
    {
        // Author can delete their own comment
        $author = $comment->getAuthor();
        if ($author && $author->getId() === $user->getId()) {
            return true;
        }

        // Group admin can delete any comment
        $post = $comment->getPost();
        $group = $post ? $post->getGroup() : null;
        if (!$group) return false;

        $membership = $this->memberRepository->findOneBy([
            'group' => $group,
            'user' => $user
        ]);

        if (!$membership) {
            return false;
        }

        $role = GroupRole::tryFromString($membership->getMemberRole());
        return $role !== null && $role->canDeleteAnyComment();
    }

    /**
     * Ensure user is a member of the group
     */
    private function ensureMember(StudyGroup $group, User $user): void
    {
        $isMember = $this->memberRepository->findOneBy([
            'group' => $group,
            'user' => $user
        ]) !== null;

        if (!$isMember) {
            throw new AccessDeniedHttpException('Vous devez être membre du groupe pour effectuer cette action.');
        }
    }
}
