<?php

namespace App\Entity;

use App\Repository\AiModelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiModelRepository::class)]
#[ORM\Table(name: 'ai_models')]
#[ORM\UniqueConstraint(name: 'unique_model_url', columns: ['name', 'base_url'])]
class AiModel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $provider;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $baseUrl;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, AiGenerationLog> */
    #[ORM\OneToMany(targetEntity: AiGenerationLog::class, mappedBy: 'model')]
    private Collection $generationLogs;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->generationLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): static
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, AiGenerationLog> */
    public function getGenerationLogs(): Collection
    {
        return $this->generationLogs;
    }
}
