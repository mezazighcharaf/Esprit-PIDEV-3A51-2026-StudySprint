<?php

namespace App\Entity;

use App\Repository\StudyGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StudyGroupRepository::class)]
#[ORM\Table(name: 'study_groups')]
#[ORM\Index(name: 'idx_group_privacy', columns: ['privacy'])]
#[ORM\Index(name: 'idx_group_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_group_activity', columns: ['last_activity'])]
class StudyGroup
{
    public const PRIVACY_PUBLIC = 'public';
    public const PRIVACY_PRIVATE = 'private';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Le nom du groupe est requis')]
    #[Assert\Length(
        min: 3,
        max: 120,
        minMessage: 'Le nom du groupe doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom du groupe ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\Choice(choices: ['public', 'private'])]
    private string $privacy = self::PRIVACY_PUBLIC;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $subject = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'studyGroups')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastActivity = null;

    /** @var Collection<int, GroupMember> */
    #[ORM\OneToMany(targetEntity: GroupMember::class, mappedBy: 'group', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $members;

    /** @var Collection<int, GroupPost> */
    #[ORM\OneToMany(targetEntity: GroupPost::class, mappedBy: 'group', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $posts;

    public function __construct()
    {
        $this->createdAt   = new \DateTimeImmutable();
        $this->lastActivity = $this->createdAt;
        $this->members     = new ArrayCollection();
        $this->posts       = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPrivacy(): string
    {
        return $this->privacy;
    }

    public function setPrivacy(string $privacy): static
    {
        $this->privacy = $privacy;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    /**
     * Alias used by some voters/controllers expecting getOwner().
     */
    public function getOwner(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getLastActivity(): ?\DateTimeImmutable
    {
        return $this->lastActivity;
    }

    public function setLastActivity(?\DateTimeImmutable $lastActivity): static
    {
        $this->lastActivity = $lastActivity;
        return $this;
    }

    /** @return Collection<int, GroupMember> */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(GroupMember $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $member->setGroup($this);
        }
        return $this;
    }

    public function removeMember(GroupMember $member): static
    {
        $this->members->removeElement($member);
        return $this;
    }

    /** @return Collection<int, GroupPost> */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(GroupPost $post): static
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setGroup($this);
        }
        return $this;
    }

    public function removePost(GroupPost $post): static
    {
        $this->posts->removeElement($post);
        return $this;
    }
}
