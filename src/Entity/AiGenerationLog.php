<?php

namespace App\Entity;

use App\Repository\AiGenerationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiGenerationLogRepository::class)]
#[ORM\Table(name: 'ai_generation_logs')]
class AiGenerationLog
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    public const FEATURE_QUIZ = 'quiz';
    public const FEATURE_FLASHCARD = 'flashcard';
    public const FEATURE_REVISION_PLAN = 'revision_plan';
    public const FEATURE_SUMMARY = 'summary';
    public const FEATURE_PROFILE = 'profile';
    public const FEATURE_PLANNING_SUGGEST = 'planning_suggest';
    public const FEATURE_POST_SUMMARY = 'post_summary';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: AiModel::class, inversedBy: 'generationLogs')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AiModel $model = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $feature;

    #[ORM\Column(type: Types::JSON)]
    private array $inputJson = [];

    #[ORM\Column(type: Types::TEXT)]
    private string $prompt;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $outputJson = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $latencyMs = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $userFeedback = null;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $idempotencyKey = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getModel(): ?AiModel
    {
        return $this->model;
    }

    public function setModel(?AiModel $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function getFeature(): string
    {
        return $this->feature;
    }

    public function setFeature(string $feature): static
    {
        $this->feature = $feature;
        return $this;
    }

    public function getInputJson(): array
    {
        return $this->inputJson;
    }

    public function setInputJson(array $inputJson): static
    {
        $this->inputJson = $inputJson;
        return $this;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function setPrompt(string $prompt): static
    {
        $this->prompt = $prompt;
        return $this;
    }

    public function getOutputJson(): ?array
    {
        return $this->outputJson;
    }

    public function setOutputJson(?array $outputJson): static
    {
        $this->outputJson = $outputJson;
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

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getLatencyMs(): ?int
    {
        return $this->latencyMs;
    }

    public function setLatencyMs(?int $latencyMs): static
    {
        $this->latencyMs = $latencyMs;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUserFeedback(): ?int
    {
        return $this->userFeedback;
    }

    public function setUserFeedback(?int $userFeedback): static
    {
        $this->userFeedback = $userFeedback;
        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(?string $idempotencyKey): static
    {
        $this->idempotencyKey = $idempotencyKey;
        return $this;
    }
}
