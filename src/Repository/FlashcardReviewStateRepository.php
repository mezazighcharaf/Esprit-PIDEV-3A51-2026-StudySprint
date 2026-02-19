<?php

namespace App\Repository;

use App\Entity\Flashcard;
use App\Entity\FlashcardDeck;
use App\Entity\FlashcardReviewState;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FlashcardReviewState>
 */
class FlashcardReviewStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FlashcardReviewState::class);
    }

    public function findByUserAndFlashcard(User $user, Flashcard $flashcard): ?FlashcardReviewState
    {
        return $this->findOneBy(['user' => $user, 'flashcard' => $flashcard]);
    }

    /**
     * @return FlashcardReviewState[]
     */
    public function findDueCardsForUserAndDeck(User $user, FlashcardDeck $deck, int $limit = 20): array
    {
        return $this->createQueryBuilder('rs')
            ->join('rs.flashcard', 'f')
            ->andWhere('rs.user = :user')
            ->andWhere('f.deck = :deck')
            ->andWhere('rs.dueAt <= :today')
            ->setParameter('user', $user)
            ->setParameter('deck', $deck)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('rs.dueAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countDueCardsForUserAndDeck(User $user, FlashcardDeck $deck): int
    {
        return (int) $this->createQueryBuilder('rs')
            ->select('COUNT(rs.id)')
            ->join('rs.flashcard', 'f')
            ->andWhere('rs.user = :user')
            ->andWhere('f.deck = :deck')
            ->andWhere('rs.dueAt <= :today')
            ->setParameter('user', $user)
            ->setParameter('deck', $deck)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get flashcards from deck that user hasn't reviewed yet
     * @return Flashcard[]
     */
    public function findNewCardsForUserAndDeck(User $user, FlashcardDeck $deck, int $limit = 20): array
    {
        $existingFlashcardIds = $this->createQueryBuilder('rs')
            ->select('IDENTITY(rs.flashcard)')
            ->andWhere('rs.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult();

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('f')
            ->from(Flashcard::class, 'f')
            ->andWhere('f.deck = :deck')
            ->setParameter('deck', $deck)
            ->orderBy('f.position', 'ASC')
            ->setMaxResults($limit);

        if (!empty($existingFlashcardIds)) {
            $qb->andWhere('f.id NOT IN (:existing)')
               ->setParameter('existing', $existingFlashcardIds);
        }

        return $qb->getQuery()->getResult();
    }
}
