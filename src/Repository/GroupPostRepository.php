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
/**
 * @extends ServiceEntityRepository<GroupPost>
 */
class GroupPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupPost::class);
    }

    /**
     * Find all posts for a specific group, ordered by creation date (newest first)
     *
     * @return list<GroupPost>
     */
    public function findByGroupOrderByCreatedAt(StudyGroup $group): array
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
     *
     * @return list<GroupPost>
     */
    public function findByGroupWithReplies(StudyGroup $group): array
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
     *
     * @return list<GroupPost>
     */
    public function findAllByGroupSorted(StudyGroup $group, string $sort = 'date'): array
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
                
            default:
                $qb->orderBy('p.createdAt', 'DESC');
                break;
        }

        $qb->setMaxResults(50);

        return $qb->getQuery()->getResult();
    }

    /**
     * Find posts with all statistics in optimized queries (fixes N+1 problem)
     * 
     * @return list<array{post: GroupPost, likesCount: int, commentsCount: int, avgRating: float, ratingsCount: int, userLiked: bool, userRating: int|null}>
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
                SELECT NEW App\Dto\IdCountDto(p.id, COUNT(l.id))
                FROM App\Entity\PostLike l
                JOIN l.post p
                WHERE p.id IN (:postIds)
                GROUP BY p.id
            ')
            ->setParameter('postIds', $postIds)
            ->getResult();

        $likesMap = [];
        /** @var \App\Dto\IdCountDto[] $likesCounts */
        foreach ($likesCounts as $dto) {
            $likesMap[$dto->id] = $dto->count;
        }

        // Batch query for comments count
        $commentsCounts = $this->getEntityManager()
            ->createQuery('
                SELECT NEW App\Dto\IdCountDto(p.id, COUNT(c.id))
                FROM App\Entity\PostComment c
                JOIN c.post p
                WHERE p.id IN (:postIds)
                GROUP BY p.id
            ')
            ->setParameter('postIds', $postIds)
            ->getResult();

        $commentsMap = [];
        /** @var \App\Dto\IdCountDto[] $commentsCounts */
        foreach ($commentsCounts as $dto) {
            $commentsMap[$dto->id] = $dto->count;
        }

        // Batch query for average ratings
        $avgRatings = $this->getEntityManager()
            ->createQuery('
                SELECT NEW App\Dto\PostRatingStatsDto(p.id, AVG(r.rating), COUNT(r.id))
                FROM App\Entity\PostRating r
                JOIN r.post p
                WHERE p.id IN (:postIds)
                GROUP BY p.id
            ')
            ->setParameter('postIds', $postIds)
            ->getResult();

        $ratingsMap = [];
        /** @var \App\Dto\PostRatingStatsDto[] $avgRatings */
        foreach ($avgRatings as $dto) {
            $ratingsMap[$dto->postId] = [
                'avg' => round($dto->avgRating, 1),
                'count' => $dto->ratingsCount
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
