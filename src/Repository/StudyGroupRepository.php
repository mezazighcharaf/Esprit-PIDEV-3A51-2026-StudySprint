<?php

namespace App\Repository;

use App\Entity\GroupMember;
use App\Entity\StudyGroup;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StudyGroup>
 *
 * @method StudyGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method StudyGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method StudyGroup[]    findAll()
 * @method StudyGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
/**
 * @extends ServiceEntityRepository<StudyGroup>
 */
class StudyGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudyGroup::class);
    }

    /**
     * Find groups for a specific user (where user is a member)
     *
     * @return list<StudyGroup>
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->innerJoin(GroupMember::class, 'm', 'WITH', 'm.group = g')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('g.lastActivity', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all groups ordered by creation date
     *
     * @return list<StudyGroup>
     */
    public function findAllOrderByCreatedAt(): array
    {
        return $this->createQueryBuilder('g')
            ->orderBy('g.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search groups by name or subject
     *
     * @return list<StudyGroup>
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('g')
            ->where(
                $this->getEntityManager()->getExpressionBuilder()->orX(
                    'LOWER(g.name) LIKE LOWER(:query)',
                    'LOWER(g.description) LIKE LOWER(:query)',
                    'LOWER(g.subject) LIKE LOWER(:query)'
                )
            )
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('g.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find public groups (not private)
     */
    /** @return list<StudyGroup> */ public function findPublic(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.privacy != :privacy')
            ->setParameter('privacy', 'private')
            ->orderBy('g.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find groups by subject
     */
    /** @return list<StudyGroup> */ public function findBySubject(string $subject): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.subject = :subject')
            ->setParameter('subject', $subject)
            ->orderBy('g.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent groups (created in last N days)
     *
     * @return list<StudyGroup>
     */
    public function findRecent(int $days = 30): array
    {
        $date = new \DateTimeImmutable(sprintf('-%d days', $days));
        
        return $this->createQueryBuilder('g')
            ->where('g.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('g.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find most active groups (by member count)
     */
    /** @return list<StudyGroup> */ public function findMostActive(int $limit = 10): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.members', 'm')
            ->groupBy('g.id')
            ->orderBy('COUNT(m.id)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
