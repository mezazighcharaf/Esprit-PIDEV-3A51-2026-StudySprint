<?php

namespace App\Repository;

use App\Entity\StudyGroup;
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
class StudyGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudyGroup::class);
    }

    /**
     * Find groups for a specific user (where user is a member)
     */
    public function findByUser($user)
    {
        return $this->createQueryBuilder('g')
            ->join('Doctrine\ORM\Mapping\ClassMetadata', 'm', 'WITH', 'm.group = g')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all groups ordered by creation date
     */
    public function findAllOrderByCreatedAt()
    {
        return $this->createQueryBuilder('g')
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search groups by name or subject
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
            ->getQuery()
            ->getResult();
    }

    /**
     * Find public groups (not private)
     */
    public function findPublic(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.privacy != :privacy')
            ->setParameter('privacy', 'private')
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find groups by subject
     */
    public function findBySubject(string $subject): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.subject = :subject')
            ->setParameter('subject', $subject)
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent groups (created in last N days)
     */
    public function findRecent(int $days = 30): array
    {
        $date = new \DateTimeImmutable(sprintf('-%d days', $days));
        
        return $this->createQueryBuilder('g')
            ->where('g.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find most active groups (by member count)
     */
    public function findMostActive(int $limit = 10): array
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
