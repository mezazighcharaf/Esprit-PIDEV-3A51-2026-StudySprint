<?php

namespace App\Repository;

use App\Entity\ReactivationRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReactivationRequest>
 */
class ReactivationRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReactivationRequest::class);
    }

    public function countPending(): int
    {
        return $this->count(['status' => 'pending']);
    }

    public function findPending(): array
    {
        return $this->findBy(['status' => 'pending'], ['createdAt' => 'DESC']);
    }
}
