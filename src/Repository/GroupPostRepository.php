<?php

namespace App\Repository;

use App\Entity\GroupPost;
use App\Entity\StudyGroup;
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
}
