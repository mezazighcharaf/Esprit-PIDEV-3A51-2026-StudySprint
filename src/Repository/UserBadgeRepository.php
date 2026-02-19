<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserBadge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserBadgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBadge::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('ub')
            ->join('ub.badge', 'b')
            ->addSelect('b')
            ->where('ub.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ub.earnedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasBadge(User $user, string $badgeCode): bool
    {
        return (bool) $this->createQueryBuilder('ub')
            ->select('COUNT(ub.id)')
            ->join('ub.badge', 'b')
            ->where('ub.user = :user')
            ->andWhere('b.code = :code')
            ->setParameter('user', $user)
            ->setParameter('code', $badgeCode)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
