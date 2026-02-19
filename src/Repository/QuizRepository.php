<?php

namespace App\Repository;

use App\Entity\Quiz;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quiz>
 */
class QuizRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quiz::class);
    }

    public function searchPublishedQuery(?string $q = null, ?string $difficulty = null, ?int $subjectId = null, string $sort = 'newest'): QueryBuilder
    {
        $qb = $this->createQueryBuilder('quiz')
            ->leftJoin('quiz.subject', 's')
            ->addSelect('s')
            ->leftJoin('quiz.owner', 'o')
            ->addSelect('o')
            ->andWhere('quiz.isPublished = true');

        if ($q) {
            $qb->andWhere('quiz.title LIKE :q OR s.name LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        if ($difficulty) {
            $qb->andWhere('quiz.difficulty = :diff')
               ->setParameter('diff', $difficulty);
        }

        if ($subjectId) {
            $qb->andWhere('s.id = :sid')
               ->setParameter('sid', $subjectId);
        }

        match ($sort) {
            'oldest' => $qb->orderBy('quiz.createdAt', 'ASC'),
            'title' => $qb->orderBy('quiz.title', 'ASC'),
            default => $qb->orderBy('quiz.createdAt', 'DESC'),
        };

        return $qb;
    }
}
