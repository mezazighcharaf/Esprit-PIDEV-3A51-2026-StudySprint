<?php

namespace App\Entity;

use App\Repository\BadgeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BadgeRepository::class)]
#[ORM\Table(name: 'badges')]
class Badge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $icon;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $color;

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }
    public function getIcon(): string { return $this->icon; }
    public function setIcon(string $icon): static { $this->icon = $icon; return $this; }
    public function getColor(): string { return $this->color; }
    public function setColor(string $color): static { $this->color = $color; return $this; }
}
