<?php

namespace App\Repository;

use App\Entity\QuizRating;
use App\Entity\Quiz;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizRating>
 */
class QuizRatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizRating::class);
    }

    public function findByUserAndQuiz(User $user, Quiz $quiz): ?QuizRating
    {
        return $this->findOneBy(['user' => $user, 'quiz' => $quiz]);
    }

    public function getAverageScore(Quiz $quiz): ?float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.score) as avg_score')
            ->where('r.quiz = :quiz')
            ->setParameter('quiz', $quiz)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? round((float) $result, 1) : null;
    }

    public function getRatingCount(Quiz $quiz): int
    {
        return $this->count(['quiz' => $quiz]);
    }
}
