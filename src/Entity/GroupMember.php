<?php

namespace App\Entity;

use App\Repository\GroupMemberRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GroupMemberRepository::class)]
#[ORM\Table(name: 'group_member')]
#[ORM\UniqueConstraint(name: 'uniq_group_user', columns: ['group_id', 'user_id'])]
#[ORM\Index(name: 'idx_member_group_role', columns: ['group_id', 'member_role'])]
#[ORM\Index(name: 'idx_member_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_member_joined', columns: ['joined_at'])]
class GroupMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StudyGroup::class, inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StudyGroup $group = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private ?string $memberRole = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $joinedAt = null;

    public function __construct()
    {
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroup(): ?StudyGroup
    {
        return $this->group;
    }

    public function setGroup(StudyGroup $group): static
    {
        $this->group = $group;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getMemberRole(): ?string
    {
        return $this->memberRole;
    }

    public function setMemberRole(string $memberRole): static
    {
        $this->memberRole = $memberRole;
        return $this;
    }

    public function getJoinedAt(): ?\DateTimeImmutable
    {
        return $this->joinedAt;
    }
}
