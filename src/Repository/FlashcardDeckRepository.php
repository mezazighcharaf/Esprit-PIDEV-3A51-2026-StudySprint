<?php

namespace App\Repository;

use App\Entity\FlashcardDeck;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FlashcardDeck>
 */
class FlashcardDeckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FlashcardDeck::class);
    }

    public function searchPublishedQuery(?string $q = null, ?int $subjectId = null, string $sort = 'newest'): QueryBuilder
    {
        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.isPublished = true')
            ->leftJoin('d.subject', 's');

        if ($q) {
            $qb->andWhere('d.title LIKE :q OR s.name LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        if ($subjectId) {
            $qb->andWhere('s.id = :sid')
               ->setParameter('sid', $subjectId);
        }

        match ($sort) {
            'oldest' => $qb->orderBy('d.createdAt', 'ASC'),
            'title' => $qb->orderBy('d.title', 'ASC'),
            default => $qb->orderBy('d.createdAt', 'DESC'),
        };

        return $qb;
    }
}
