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

    /**
     * Find all likes for BO, ordered by most recent, with post/user/group loaded.
     */
    public function findAllOrderByCreatedAtDesc(int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->innerJoin('l.post', 'p')
            ->addSelect('p')
            ->innerJoin('l.user', 'u')
            ->addSelect('u')
            ->innerJoin('p.group', 'g')
            ->addSelect('g')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find likes on posts authored by a given user (feedback received), ordered by most recent.
     */
    public function findByPostAuthorOrderByCreatedAtDesc(User $author, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->innerJoin('l.post', 'p')
            ->addSelect('p')
            ->innerJoin('l.user', 'u')
            ->addSelect('u')
            ->innerJoin('p.group', 'g')
            ->addSelect('g')
            ->where('p.author = :author')
            ->andWhere('l.user != :author')
            ->setParameter('author', $author)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
