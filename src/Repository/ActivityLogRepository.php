<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ActivityLog[]
     */
    public function findRecentByUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns activity counts per day for the last N days.
     * Result: [['day' => '2025-02-10', 'count' => 3], ...]
     */
    public function getDailyActivityForUser(User $user, int $days = 30): array
    {
        $since = new \DateTimeImmutable("-{$days} days");
        $conn  = $this->getEntityManager()->getConnection();

        $rows = $conn->executeQuery(
            'SELECT DATE(a.created_at) AS day, COUNT(a.id) AS count
             FROM activity_logs a
             WHERE a.user_id = :userId AND a.created_at >= :since
             GROUP BY DATE(a.created_at)
             ORDER BY day ASC',
            ['userId' => $user->getId(), 'since' => $since->format('Y-m-d H:i:s')]
        )->fetchAllAssociative();

        return $rows;
    }

    /**
     * Count total actions by a user.
     */
    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns global activity counts per day for the last 7 days (all users).
     * Result: [['day' => '2025-02-10', 'count' => 12], ...]
     */
    public function getDailyActivityForUser7Days(): array
    {
        $since = new \DateTimeImmutable('-7 days');
        $conn  = $this->getEntityManager()->getConnection();

        return $conn->executeQuery(
            'SELECT DATE(a.created_at) AS day, COUNT(a.id) AS count
             FROM activity_logs a
             WHERE a.created_at >= :since
             GROUP BY DATE(a.created_at)
             ORDER BY day ASC',
            ['since' => $since->format('Y-m-d H:i:s')]
        )->fetchAllAssociative();
    }
}
