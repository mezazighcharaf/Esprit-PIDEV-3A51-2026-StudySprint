<?php

namespace App\Entity;

use App\Repository\FlashcardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FlashcardRepository::class)]
#[ORM\Table(name: 'flashcards')]
class Flashcard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FlashcardDeck::class, inversedBy: 'flashcards')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private FlashcardDeck $deck;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $front;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $back;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $hint = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $position = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, FlashcardReviewState> */
    #[ORM\OneToMany(targetEntity: FlashcardReviewState::class, mappedBy: 'flashcard', cascade: ['remove'])]
    private Collection $reviewStates;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->reviewStates = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeck(): FlashcardDeck
    {
        return $this->deck;
    }

    public function setDeck(FlashcardDeck $deck): static
    {
        $this->deck = $deck;
        return $this;
    }

    public function getFront(): string
    {
        return $this->front;
    }

    public function setFront(string $front): static
    {
        $this->front = $front;
        return $this;
    }

    public function getBack(): string
    {
        return $this->back;
    }

    public function setBack(string $back): static
    {
        $this->back = $back;
        return $this;
    }

    public function getHint(): ?string
    {
        return $this->hint;
    }

    public function setHint(?string $hint): static
    {
        $this->hint = $hint;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, FlashcardReviewState> */
    public function getReviewStates(): Collection
    {
        return $this->reviewStates;
    }
}
