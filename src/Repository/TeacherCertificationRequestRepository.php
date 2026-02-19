<?php

namespace App\Repository;

use App\Entity\TeacherCertificationRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeacherCertificationRequest>
 */
class TeacherCertificationRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeacherCertificationRequest::class);
    }

    /**
     * Find the latest request for a user (any status).
     */
    public function findLatestByUser(User $user): ?TeacherCertificationRequest
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.requestedAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if user has a PENDING request.
     */
    public function hasPendingRequest(User $user): bool
    {
        $count = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.user = :user')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', TeacherCertificationRequest::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
