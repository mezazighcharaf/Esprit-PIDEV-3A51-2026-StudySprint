<?php

namespace App\Entity;

use App\Repository\PlanTaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlanTaskRepository::class)]
#[ORM\Table(name: 'plan_tasks')]
class PlanTask
{
    public const TYPE_REVISION = 'REVISION';
    public const TYPE_QUIZ = 'QUIZ';
    public const TYPE_FLASHCARD = 'FLASHCARD';
    public const TYPE_CUSTOM = 'CUSTOM';

    public const STATUS_TODO = 'TODO';
    public const STATUS_DOING = 'DOING';
    public const STATUS_DONE = 'DONE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RevisionPlan::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RevisionPlan $plan;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\Choice(choices: ['REVISION', 'QUIZ', 'FLASHCARD', 'CUSTOM'])]
    private string $taskType = self::TYPE_REVISION;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $startAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $endAt;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\Choice(choices: ['TODO', 'DOING', 'DONE'])]
    private string $status = self::STATUS_TODO;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 3)]
    private int $priority = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlan(): RevisionPlan
    {
        return $this->plan;
    }

    public function setPlan(RevisionPlan $plan): static
    {
        $this->plan = $plan;
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

    public function getTaskType(): string
    {
        return $this->taskType;
    }

    public function setTaskType(string $taskType): static
    {
        $this->taskType = $taskType;
        return $this;
    }

    public function getStartAt(): \DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;
        return $this;
    }

    public function getEndAt(): \DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
