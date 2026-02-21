<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\User;
use App\Repository\GroupInvitationRepository;

#[ORM\Entity(repositoryClass: GroupInvitationRepository::class)]
#[ORM\Table(
    name: 'group_invitation',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_group_email',
            columns: ['group_id', 'email']
        )
    ]
)]
#[ORM\Index(name: 'idx_invitation_email', columns: ['email'])]
#[ORM\Index(name: 'idx_invitation_status', columns: ['status'])]
#[ORM\Index(name: 'idx_invitation_code', columns: ['code'])]
class GroupInvitation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StudyGroup::class)]
    #[ORM\JoinColumn(nullable: false)]
    private StudyGroup $group;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $invitedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $invitedAt;

    #[ORM\Column(length: 32, unique: true)]
    private string $code;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: ['pending', 'accepted', 'declined', 'cancelled'])]
    private string $status = 'pending';

    #[ORM\Column(length: 20)]
    private string $role = 'member';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    public function __construct()
    {
        $this->invitedAt = new \DateTimeImmutable();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getGroup(): StudyGroup
    {
        return $this->group;
    }

    public function setGroup(StudyGroup $group): void
    {
        $this->group = $group;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getInvitedBy(): ?User
    {
        return $this->invitedBy;
    }

    public function setInvitedBy(?User $invitedBy): void
    {
        $this->invitedBy = $invitedBy;
    }

    public function getInvitedAt(): \DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function setInvitedAt(\DateTimeImmutable $invitedAt): void
    {
        $this->invitedAt = $invitedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getRespondedAt(): ?\DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function setRespondedAt(?\DateTimeImmutable $respondedAt): void
    {
        $this->respondedAt = $respondedAt;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }
}
