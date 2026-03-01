<?php

namespace App\Repository;

use App\Entity\GroupPost;
use App\Entity\PostRating;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PostRatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostRating::class);
    }

    /**
     * Calculate average rating for a post
     */
    public function getAverageRating(GroupPost $post): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) as avgRating')
            ->where('r.post = :post')
            ->setParameter('post', $post)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? round((float)$result, 1) : 0.0;
    }

    /**
     * Count total ratings for a post
     */
    public function countByPost(GroupPost $post): int
    {
        return $this->count(['post' => $post]);
    }

    /**
     * Get user's rating for a post
     */
    public function getUserRating(GroupPost $post, User $user): ?int
    {
        $rating = $this->findOneBy(['post' => $post, 'user' => $user]);
        return $rating ? $rating->getRating() : null;
    }

    /**
     * Find rating by post and user
     */
    public function findByPostAndUser(GroupPost $post, User $user): ?PostRating
    {
        return $this->findOneBy(['post' => $post, 'user' => $user]);
    }

    /**
     * Find all ratings for BO (feedbacks), ordered by most recent, with post/user/group loaded.
     */
    public function findAllOrderByCreatedAtDesc(int $limit = 100): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.post', 'p')
            ->addSelect('p')
            ->innerJoin('r.user', 'u')
            ->addSelect('u')
            ->innerJoin('p.group', 'g')
            ->addSelect('g')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find ratings on posts authored by a given user (feedback received), ordered by most recent.
     */
    public function findByPostAuthorOrderByCreatedAtDesc(User $author, int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.post', 'p')
            ->addSelect('p')
            ->innerJoin('r.user', 'u')
            ->addSelect('u')
            ->innerJoin('p.group', 'g')
            ->addSelect('g')
            ->where('p.author = :author')
            ->andWhere('r.user != :author')
            ->setParameter('author', $author)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
