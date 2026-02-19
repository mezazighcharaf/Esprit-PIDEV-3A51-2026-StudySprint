<?php

namespace App\Entity;

use App\Repository\QuizAttemptRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuizAttemptRepository::class)]
#[ORM\Table(name: 'quiz_attempts')]
#[ORM\Index(name: 'idx_qa_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_qa_quiz', columns: ['quiz_id'])]
class QuizAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Quiz::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Quiz $quiz;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $score = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $totalQuestions = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $correctCount = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $durationSeconds = null;

    /** @var Collection<int, QuizAttemptAnswer> */
    #[ORM\OneToMany(targetEntity: QuizAttemptAnswer::class, mappedBy: 'attempt', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $answers;

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
        $this->answers = new ArrayCollection();
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

    public function getQuiz(): Quiz
    {
        return $this->quiz;
    }

    public function setQuiz(Quiz $quiz): static
    {
        $this->quiz = $quiz;
        return $this;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getScore(): ?float
    {
        return $this->score !== null ? (float) $this->score : null;
    }

    public function setScore(?float $score): static
    {
        $this->score = $score !== null ? (string) $score : null;
        return $this;
    }

    public function getTotalQuestions(): int
    {
        return $this->totalQuestions;
    }

    public function setTotalQuestions(int $totalQuestions): static
    {
        $this->totalQuestions = $totalQuestions;
        return $this;
    }

    public function getCorrectCount(): int
    {
        return $this->correctCount;
    }

    public function setCorrectCount(int $correctCount): static
    {
        $this->correctCount = $correctCount;
        return $this;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(?int $durationSeconds): static
    {
        $this->durationSeconds = $durationSeconds;
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->completedAt !== null;
    }

    public function complete(): static
    {
        $this->completedAt = new \DateTimeImmutable();
        $this->durationSeconds = $this->completedAt->getTimestamp() - $this->startedAt->getTimestamp();
        return $this;
    }

    /** @return Collection<int, QuizAttemptAnswer> */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    public function addAnswer(QuizAttemptAnswer $answer): static
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
            $answer->setAttempt($this);
        }
        return $this;
    }
}
