<?php

namespace App\Repository;

use App\Entity\Flashcard;
use App\Entity\FlashcardDeck;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Flashcard>
 */
class FlashcardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Flashcard::class);
    }

    /**
     * @return Flashcard[]
     */
    public function findByDeckOrdered(FlashcardDeck $deck): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.deck = :deck')
            ->setParameter('deck', $deck)
            ->orderBy('f.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
