<?php

namespace App\Entity;

use App\Repository\GroupMemberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupMemberRepository::class)]
#[ORM\Table(name: 'group_members')]
#[ORM\UniqueConstraint(name: 'unique_group_user', columns: ['group_id', 'user_id'])]
class GroupMember
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MEMBER = 'member';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StudyGroup::class, inversedBy: 'members')]
    #[ORM\JoinColumn(name: 'group_id', nullable: false, onDelete: 'CASCADE')]
    private StudyGroup $group;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $memberRole = self::ROLE_MEMBER;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $joinedAt;

    public function __construct()
    {
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroup(): StudyGroup
    {
        return $this->group;
    }

    public function setGroup(StudyGroup $group): static
    {
        $this->group = $group;
        return $this;
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

    public function getMemberRole(): string
    {
        return $this->memberRole;
    }

    public function setMemberRole(string $memberRole): static
    {
        $this->memberRole = $memberRole;
        return $this;
    }

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }
}
