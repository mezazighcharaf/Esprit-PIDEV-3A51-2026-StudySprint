<?php

namespace App\Entity;

use App\Repository\UserBadgeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserBadgeRepository::class)]
#[ORM\Table(name: 'user_badges')]
#[ORM\UniqueConstraint(name: 'unique_user_badge', columns: ['user_id', 'badge_id'])]
#[ORM\Index(name: 'idx_ub_user', columns: ['user_id'])]
class UserBadge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Badge::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Badge $badge;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $earnedAt;

    public function __construct()
    {
        $this->earnedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }
    public function getBadge(): Badge { return $this->badge; }
    public function setBadge(Badge $badge): static { $this->badge = $badge; return $this; }
    public function getEarnedAt(): \DateTimeImmutable { return $this->earnedAt; }
}
