<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: 'activity_logs')]
#[ORM\Index(name: 'idx_activity_entity', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'idx_activity_user', columns: ['user_id'])]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $action;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $entityType;

    #[ORM\Column(type: Types::INTEGER)]
    private int $entityId;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $entityLabel = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }
    public function getEntityType(): string { return $this->entityType; }
    public function setEntityType(string $entityType): static { $this->entityType = $entityType; return $this; }
    public function getEntityId(): int { return $this->entityId; }
    public function setEntityId(int $entityId): static { $this->entityId = $entityId; return $this; }
    public function getEntityLabel(): ?string { return $this->entityLabel; }
    public function setEntityLabel(?string $entityLabel): static { $this->entityLabel = $entityLabel; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
