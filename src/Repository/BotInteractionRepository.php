<?php

namespace App\Repository;

use App\Entity\BotInteraction;
use App\Entity\StudyGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BotInteractionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BotInteraction::class);
    }

    /**
     * Count interactions for a user in a given time window (for rate limiting)
     */
    public function countRecentByUser(int $userId, int $windowMinutes = 60): int
    {
        $since = new \DateTimeImmutable("-{$windowMinutes} minutes");

        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.triggeredBy = :userId')
            ->andWhere('b.createdAt >= :since')
            ->setParameter('userId', $userId)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get statistics for a group's chatbot
     */
    public function getGroupStats(StudyGroup $group): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select(
                'COUNT(b.id) as totalInteractions',
                'AVG(b.responseTimeMs) as avgResponseTime',
                'SUM(b.tokensUsed) as totalTokens',
                "SUM(CASE WHEN b.feedback = 'helpful' THEN 1 ELSE 0 END) as helpfulCount",
                "SUM(CASE WHEN b.feedback = 'not-helpful' THEN 1 ELSE 0 END) as notHelpfulCount"
            )
            ->where('b.group = :group')
            ->setParameter('group', $group)
            ->getQuery()
            ->getSingleResult();

        return $qb;
    }

    /**
     * Get recent interactions for a group
     */
    public function findRecentByGroup(StudyGroup $group, int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.group = :group')
            ->setParameter('group', $group)
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
