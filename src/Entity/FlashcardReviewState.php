<?php

namespace App\Entity;

use App\Repository\FlashcardReviewStateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FlashcardReviewStateRepository::class)]
#[ORM\Table(name: 'flashcard_review_states')]
#[ORM\UniqueConstraint(name: 'unique_user_flashcard', columns: ['user_id', 'flashcard_id'])]
#[ORM\Index(name: 'idx_frs_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_frs_flashcard', columns: ['flashcard_id'])]
class FlashcardReviewState
{
    public const MIN_EASE_FACTOR = 1.3;
    public const DEFAULT_EASE_FACTOR = 2.5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Flashcard::class, inversedBy: 'reviewStates')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Flashcard $flashcard;

    #[ORM\Column(type: Types::INTEGER)]
    private int $repetitions = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $intervalDays = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2)]
    private string $easeFactor = '2.50';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dueAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastReviewedAt = null;

    public function __construct()
    {
        $this->dueAt = new \DateTimeImmutable('today');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getFlashcard(): Flashcard
    {
        return $this->flashcard;
    }

    public function setFlashcard(Flashcard $flashcard): static
    {
        $this->flashcard = $flashcard;
        return $this;
    }

    public function getRepetitions(): int
    {
        return $this->repetitions;
    }

    public function setRepetitions(int $repetitions): static
    {
        $this->repetitions = $repetitions;
        return $this;
    }

    public function getIntervalDays(): int
    {
        return $this->intervalDays;
    }

    public function setIntervalDays(int $intervalDays): static
    {
        $this->intervalDays = $intervalDays;
        return $this;
    }

    public function getEaseFactor(): float
    {
        return (float) $this->easeFactor;
    }

    public function setEaseFactor(float $easeFactor): static
    {
        $this->easeFactor = (string) max(self::MIN_EASE_FACTOR, $easeFactor);
        return $this;
    }

    public function getDueAt(): \DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(\DateTimeImmutable $dueAt): static
    {
        $this->dueAt = $dueAt;
        return $this;
    }

    public function getLastReviewedAt(): ?\DateTimeImmutable
    {
        return $this->lastReviewedAt;
    }

    public function setLastReviewedAt(?\DateTimeImmutable $lastReviewedAt): static
    {
        $this->lastReviewedAt = $lastReviewedAt;
        return $this;
    }

    public function isDue(): bool
    {
        return $this->dueAt <= new \DateTimeImmutable('today');
    }
}
