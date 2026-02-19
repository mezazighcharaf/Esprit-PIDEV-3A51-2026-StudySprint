<?php

namespace App\Repository;

use App\Entity\Quiz;
use App\Entity\QuizAttempt;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizAttempt>
 */
class QuizAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizAttempt::class);
    }

    /**
     * @return QuizAttempt[]
     */
    public function findByUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.quiz', 'q')
            ->addSelect('q')
            ->leftJoin('q.subject', 's')
            ->addSelect('s')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return QuizAttempt[]
     */
    public function findByQuiz(Quiz $quiz): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.quiz = :quiz')
            ->setParameter('quiz', $quiz)
            ->orderBy('a.startedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findIncompleteByUserAndQuiz(User $user, Quiz $quiz): ?QuizAttempt
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.quiz = :quiz')
            ->andWhere('a.completedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('quiz', $quiz)
            ->orderBy('a.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getAverageScoreForQuiz(Quiz $quiz): ?float
    {
        $result = $this->createQueryBuilder('a')
            ->select('AVG(a.score) as avgScore')
            ->andWhere('a.quiz = :quiz')
            ->andWhere('a.completedAt IS NOT NULL')
            ->setParameter('quiz', $quiz)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (float) $result : null;
    }

    public function getCompletedCountForQuiz(Quiz $quiz): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.quiz = :quiz')
            ->andWhere('a.completedAt IS NOT NULL')
            ->setParameter('quiz', $quiz)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
