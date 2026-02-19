<?php

namespace App\Entity;

use App\Repository\QuizRatingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuizRatingRepository::class)]
#[ORM\Table(name: 'quiz_ratings')]
#[ORM\UniqueConstraint(name: 'unique_user_quiz_rating', columns: ['user_id', 'quiz_id'])]
class QuizRating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Quiz::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Quiz $quiz;

    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 5)]
    #[ORM\Column(type: Types::SMALLINT)]
    private int $score = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getQuiz(): Quiz { return $this->quiz; }
    public function setQuiz(Quiz $quiz): static { $this->quiz = $quiz; return $this; }

    public function getScore(): int { return $this->score; }
    public function setScore(int $score): static { $this->score = $score; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
