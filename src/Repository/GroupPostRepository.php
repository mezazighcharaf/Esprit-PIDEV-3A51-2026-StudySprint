<?php

namespace App\Repository;

use App\Entity\GroupPost;
use App\Entity\StudyGroup;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupPost>
 *
 * @method GroupPost|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupPost|null findOneBy(array $criteria, array $orderBy = null)
 * @method GroupPost[]    findAll()
 * @method GroupPost[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupPost::class);
    }

    /**
     * Find all posts for a specific group, ordered by creation date (newest first)
     */
    public function findByGroupOrderByCreatedAt(StudyGroup $group)
    {
        return $this->createQueryBuilder('p')
            ->where('p.group = :group')
            ->andWhere('p.parentPost IS NULL')  // Only top-level posts
            ->setParameter('group', $group)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find posts for a group with replies included
     */
    public function findByGroupWithReplies(StudyGroup $group)
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->where('p.group = :group')
            ->setParameter('group', $group)
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
    /**
     * Find posts for a group with sorting
     */
    public function findAllByGroupSorted(StudyGroup $group, string $sort = 'date')
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->where('p.group = :group')
            ->andWhere('p.parentPost IS NULL') // Only top-level posts
            ->setParameter('group', $group);

        switch ($sort) {
            case 'likes':
                $qb->leftJoin('p.likes', 'l')
                   ->groupBy('p.id', 'a.id') // Group by post and author to avoid aggregation errors
                   ->orderBy('COUNT(l.id)', 'DESC')
                   ->addOrderBy('p.createdAt', 'DESC');
                break;
            
            case 'comments':
                $qb->leftJoin('p.comments', 'c')
                   ->groupBy('p.id', 'a.id')
                   ->orderBy('COUNT(c.id)', 'DESC')
                   ->addOrderBy('p.createdAt', 'DESC');
                break;
                
            case 'rating':
                $qb->leftJoin('p.ratings', 'r')
                   ->groupBy('p.id', 'a.id')
                   ->orderBy('AVG(r.rating)', 'DESC')
                   ->addOrderBy('p.createdAt', 'DESC');
                break;
                
            case 'date':
            default:
                $qb->orderBy('p.createdAt', 'DESC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find posts with all statistics in optimized queries (fixes N+1 problem)
     * Returns array of ['post' => GroupPost, 'likesCount' => int, 'commentsCount' => int, 'avgRating' => float]
     */
    public function findByGroupWithStats(StudyGroup $group, ?User $user = null, string $sort = 'date'): array
    {
        // First, get posts with authors
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->where('p.group = :group')
            ->andWhere('p.parentPost IS NULL')
            ->setParameter('group', $group);

        // Apply sorting
        switch ($sort) {
            case 'likes':
                $qb->leftJoin('p.likes', 'l')
                   ->groupBy('p.id', 'a.id')
                   ->orderBy('COUNT(l.id)', 'DESC')
                   ->addOrderBy('p.createdAt', 'DESC');
                break;
            case 'comments':
                $qb->leftJoin('p.comments', 'c')
                   ->groupBy('p.id', 'a.id')
                   ->orderBy('COUNT(c.id)', 'DESC')
                   ->addOrderBy('p.createdAt', 'DESC');
                break;
            case 'rating':
                $qb->leftJoin('p.ratings', 'r')
                   ->groupBy('p.id', 'a.id')
                   ->orderBy('AVG(r.rating)', 'DESC')
                   ->addOrderBy('p.createdAt', 'DESC');
                break;
            default:
                $qb->orderBy('p.createdAt', 'DESC');
        }

        $posts = $qb->getQuery()->getResult();

        if (empty($posts)) {
            return [];
        }

        $postIds = array_map(fn(GroupPost $p) => $p->getId(), $posts);

        // Batch query for likes count
        $likesCounts = $this->getEntityManager()
            ->createQuery('
                SELECT p.id as postId, COUNT(l.id) as likesCount
                FROM App\Entity\PostLike l
                JOIN l.post p
                WHERE p.id IN (:postIds)
                GROUP BY p.id
            ')
            ->setParameter('postIds', $postIds)
            ->getResult();

        $likesMap = [];
        foreach ($likesCounts as $row) {
            $likesMap[$row['postId']] = (int) $row['likesCount'];
        }

        // Batch query for comments count
        $commentsCounts = $this->getEntityManager()
            ->createQuery('
                SELECT p.id as postId, COUNT(c.id) as commentsCount
                FROM App\Entity\PostComment c
                JOIN c.post p
                WHERE p.id IN (:postIds)
                GROUP BY p.id
            ')
            ->setParameter('postIds', $postIds)
            ->getResult();

        $commentsMap = [];
        foreach ($commentsCounts as $row) {
            $commentsMap[$row['postId']] = (int) $row['commentsCount'];
        }

        // Batch query for average ratings
        $avgRatings = $this->getEntityManager()
            ->createQuery('
                SELECT p.id as postId, AVG(r.rating) as avgRating, COUNT(r.id) as ratingsCount
                FROM App\Entity\PostRating r
                JOIN r.post p
                WHERE p.id IN (:postIds)
                GROUP BY p.id
            ')
            ->setParameter('postIds', $postIds)
            ->getResult();

        $ratingsMap = [];
        foreach ($avgRatings as $row) {
            $ratingsMap[$row['postId']] = [
                'avg' => round((float) $row['avgRating'], 1),
                'count' => (int) $row['ratingsCount']
            ];
        }

        // User-specific data
        $userLikesMap = [];
        $userRatingsMap = [];
        
        if ($user) {
            // User's likes
            $userLikes = $this->getEntityManager()
                ->createQuery('
                    SELECT p.id as postId
                    FROM App\Entity\PostLike l
                    JOIN l.post p
                    WHERE p.id IN (:postIds) AND l.user = :user
                ')
                ->setParameter('postIds', $postIds)
                ->setParameter('user', $user)
                ->getResult();

            foreach ($userLikes as $row) {
                $userLikesMap[$row['postId']] = true;
            }

            // User's ratings
            $userRatings = $this->getEntityManager()
                ->createQuery('
                    SELECT p.id as postId, r.rating
                    FROM App\Entity\PostRating r
                    JOIN r.post p
                    WHERE p.id IN (:postIds) AND r.user = :user
                ')
                ->setParameter('postIds', $postIds)
                ->setParameter('user', $user)
                ->getResult();

            foreach ($userRatings as $row) {
                $userRatingsMap[$row['postId']] = (int) $row['rating'];
            }
        }

        // Build result array
        $result = [];
        foreach ($posts as $post) {
            $postId = $post->getId();
            $result[] = [
                'post' => $post,
                'likesCount' => $likesMap[$postId] ?? 0,
                'commentsCount' => $commentsMap[$postId] ?? 0,
                'avgRating' => $ratingsMap[$postId]['avg'] ?? 0.0,
                'ratingsCount' => $ratingsMap[$postId]['count'] ?? 0,
                'userLiked' => $userLikesMap[$postId] ?? false,
                'userRating' => $userRatingsMap[$postId] ?? null,
            ];
        }

        return $result;
    }
}
