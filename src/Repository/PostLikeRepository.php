<?php

namespace App\Repository;

use App\Entity\GroupPost;
use App\Entity\PostLike;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PostLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostLike::class);
    }

    /**
     * Count total likes for a post
     */
    public function countByPost(GroupPost $post): int
    {
        return $this->count(['post' => $post]);
    }

    /**
     * Check if user has liked a post
     */
    public function hasUserLiked(GroupPost $post, User $user): bool
    {
        return $this->count(['post' => $post, 'user' => $user]) > 0;
    }

    /**
     * Find like by post and user
     */
    public function findByPostAndUser(GroupPost $post, User $user): ?PostLike
    {
        return $this->findOneBy(['post' => $post, 'user' => $user]);
    }
}
