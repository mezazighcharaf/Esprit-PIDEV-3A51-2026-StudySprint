<?php

namespace App\Entity;

use App\Repository\QuizRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuizRepository::class)]
#[ORM\Table(name: 'quizzes')]
#[ORM\Index(name: 'idx_quiz_subject', columns: ['subject_id'])]
#[ORM\Index(name: 'idx_quiz_owner', columns: ['owner_id'])]
#[ORM\HasLifecycleCallbacks]
class Quiz
{
    public const DIFFICULTY_EASY = 'EASY';
    public const DIFFICULTY_MEDIUM = 'MEDIUM';
    public const DIFFICULTY_HARD = 'HARD';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'quizzes')]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\ManyToOne(targetEntity: Subject::class, inversedBy: 'quizzes')]
    #[ORM\JoinColumn(nullable: false)]
    private Subject $subject;

    #[ORM\ManyToOne(targetEntity: Chapter::class, inversedBy: 'quizzes')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Chapter $chapter = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\Choice(choices: ['EASY', 'MEDIUM', 'HARD'])]
    private string $difficulty = self::DIFFICULTY_MEDIUM;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $templateKey = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull]
    private array $questions = [];

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isPublished = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $generatedByAi = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $aiMeta = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getDifficulty(): string
    {
        return $this->difficulty;
    }

    public function setDifficulty(string $difficulty): static
    {
        $this->difficulty = $difficulty;
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

    public function getQuestions(): array
    {
        return $this->questions;
    }

    public function setQuestions(array $questions): static
    {
        $this->questions = $questions;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
