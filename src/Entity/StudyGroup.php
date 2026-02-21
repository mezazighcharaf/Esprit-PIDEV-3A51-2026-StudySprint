<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Repository\StudyGroupRepository::class)]
#[ORM\Table(name: 'study_group')]
#[ORM\Index(name: 'idx_group_privacy', columns: ['privacy'])]
#[ORM\Index(name: 'idx_group_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_group_activity', columns: ['last_activity'])]
#[ORM\Index(name: 'idx_group_subject', columns: ['subject'])]
class StudyGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Le nom du groupe est requis')]
    #[Assert\Length(
        min: 3,
        max: 120,
        minMessage: 'Le nom du groupe doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom du groupe ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $description = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank(message: 'La visibilité du groupe est requise')]
    #[Assert\Choice(choices: ['public', 'private'], message: 'La visibilité doit être "public" ou "private"')]
    private ?string $privacy = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $subject = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastActivity = null;

    #[ORM\OneToMany(targetEntity: GroupMember::class, mappedBy: 'group', orphanRemoval: true)]
    private Collection $members;

    #[ORM\OneToMany(targetEntity: GroupPost::class, mappedBy: 'group', orphanRemoval: true)]
    private Collection $posts;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lastActivity = $this->createdAt; // Initial activity is creation
        $this->members = new ArrayCollection();
        $this->posts = new ArrayCollection();
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

    public function getPrivacy(): ?string
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

    /**
     * @return Collection<int, GroupMember>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    /**
     * @return Collection<int, GroupPost>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }
}
