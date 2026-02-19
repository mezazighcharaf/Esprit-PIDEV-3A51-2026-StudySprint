<?php

namespace App\Entity;

use App\Repository\QuizAttemptAnswerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuizAttemptAnswerRepository::class)]
#[ORM\Table(name: 'quiz_attempt_answers')]
class QuizAttemptAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: QuizAttempt::class, inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private QuizAttempt $attempt;

    #[ORM\Column(type: Types::INTEGER)]
    private int $questionIndex;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $selectedChoiceKey;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isCorrect = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAttempt(): QuizAttempt
    {
        return $this->attempt;
    }

    public function setAttempt(QuizAttempt $attempt): static
    {
        $this->attempt = $attempt;
        return $this;
    }

    public function getQuestionIndex(): int
    {
        return $this->questionIndex;
    }

    public function setQuestionIndex(int $questionIndex): static
    {
        $this->questionIndex = $questionIndex;
        return $this;
    }

    public function getSelectedChoiceKey(): string
    {
        return $this->selectedChoiceKey;
    }

    public function setSelectedChoiceKey(string $selectedChoiceKey): static
    {
        $this->selectedChoiceKey = $selectedChoiceKey;
        return $this;
    }

    public function isCorrect(): bool
    {
        return $this->isCorrect;
    }

    public function setIsCorrect(bool $isCorrect): static
    {
        $this->isCorrect = $isCorrect;
        return $this;
    }
}
