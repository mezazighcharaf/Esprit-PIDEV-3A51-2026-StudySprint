<?php

namespace App\Entity;

use App\Repository\ChapterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChapterRepository::class)]
#[ORM\Table(name: 'chapters')]
#[ORM\UniqueConstraint(name: 'unique_subject_order', columns: ['subject_id', 'order_no'])]
#[UniqueEntity(fields: ['subject', 'orderNo'], message: 'Un chapitre avec cet ordre existe déjà pour cette matière.')]
class Chapter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Subject::class, inversedBy: 'chapters')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Subject $subject;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $title;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive]
    private int $orderNo = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiSummary = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $aiKeyPoints = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $aiTags = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'chapters')]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Quiz> */
    #[ORM\OneToMany(targetEntity: Quiz::class, mappedBy: 'chapter')]
    private Collection $quizzes;

    /** @var Collection<int, FlashcardDeck> */
    #[ORM\OneToMany(targetEntity: FlashcardDeck::class, mappedBy: 'chapter')]
    private Collection $flashcardDecks;

    /** @var Collection<int, RevisionPlan> */
    #[ORM\OneToMany(targetEntity: RevisionPlan::class, mappedBy: 'chapter')]
    private Collection $revisionPlans;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->quizzes = new ArrayCollection();
        $this->flashcardDecks = new ArrayCollection();
        $this->revisionPlans = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getOrderNo(): int
    {
        return $this->orderNo;
    }

    public function setOrderNo(int $orderNo): static
    {
        $this->orderNo = $orderNo;
        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Quiz> */
    public function getQuizzes(): Collection
    {
        return $this->quizzes;
    }

    /** @return Collection<int, FlashcardDeck> */
    public function getFlashcardDecks(): Collection
    {
        return $this->flashcardDecks;
    }

    /** @return Collection<int, RevisionPlan> */
    public function getRevisionPlans(): Collection
    {
        return $this->revisionPlans;
    }

    public function getAiSummary(): ?string
    {
        return $this->aiSummary;
    }

    public function setAiSummary(?string $aiSummary): static
    {
        $this->aiSummary = $aiSummary;
        return $this;
    }

    public function getAiKeyPoints(): ?array
    {
        return $this->aiKeyPoints;
    }

    public function setAiKeyPoints(?array $aiKeyPoints): static
    {
        $this->aiKeyPoints = $aiKeyPoints;
        return $this;
    }

    public function getAiTags(): ?array
    {
        return $this->aiTags;
    }

    public function setAiTags(?array $aiTags): static
    {
        $this->aiTags = $aiTags;
        return $this;
    }
}
