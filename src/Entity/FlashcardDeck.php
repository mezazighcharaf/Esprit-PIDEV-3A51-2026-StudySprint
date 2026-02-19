<?php

namespace App\Entity;

use App\Repository\FlashcardDeckRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FlashcardDeckRepository::class)]
#[ORM\Table(name: 'flashcard_decks')]
#[ORM\Index(name: 'idx_deck_subject', columns: ['subject_id'])]
#[ORM\Index(name: 'idx_deck_owner', columns: ['owner_id'])]
class FlashcardDeck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /** @var Collection<int, Flashcard> */
    #[ORM\OneToMany(targetEntity: Flashcard::class, mappedBy: 'deck', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $flashcards;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'flashcardDecks')]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\ManyToOne(targetEntity: Subject::class, inversedBy: 'flashcardDecks')]
    #[ORM\JoinColumn(nullable: false)]
    private Subject $subject;

    #[ORM\ManyToOne(targetEntity: Chapter::class, inversedBy: 'flashcardDecks')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Chapter $chapter = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $templateKey = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull]
    private array $cards = [];

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isPublished = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $generatedByAi = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $aiMeta = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->flashcards = new ArrayCollection();
    }

    /** @return Collection<int, Flashcard> */
    public function getFlashcards(): Collection
    {
        return $this->flashcards;
    }

    public function addFlashcard(Flashcard $flashcard): static
    {
        if (!$this->flashcards->contains($flashcard)) {
            $this->flashcards->add($flashcard);
            $flashcard->setDeck($this);
        }
        return $this;
    }

    public function removeFlashcard(Flashcard $flashcard): static
    {
        $this->flashcards->removeElement($flashcard);
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    public function getSubject(): Subject
    {
        return $this->subject;
    }

    public function setSubject(Subject $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getChapter(): ?Chapter
    {
        return $this->chapter;
    }

    public function setChapter(?Chapter $chapter): static
    {
        $this->chapter = $chapter;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getTemplateKey(): ?string
    {
        return $this->templateKey;
    }

    public function setTemplateKey(?string $templateKey): static
    {
        $this->templateKey = $templateKey;
        return $this;
    }

    public function getCards(): array
    {
        return $this->cards;
    }

    public function setCards(array $cards): static
    {
        $this->cards = $cards;
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;
        return $this;
    }

    public function isGeneratedByAi(): bool
    {
        return $this->generatedByAi;
    }

    public function setGeneratedByAi(bool $generatedByAi): static
    {
        $this->generatedByAi = $generatedByAi;
        return $this;
    }

    public function getAiMeta(): ?array
    {
        return $this->aiMeta;
    }

    public function setAiMeta(?array $aiMeta): static
    {
        $this->aiMeta = $aiMeta;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
