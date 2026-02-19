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
class StudyGroup
{
    public const PRIVACY_PUBLIC = 'PUBLIC';
    public const PRIVACY_PRIVATE = 'PRIVATE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\Choice(choices: ['PUBLIC', 'PRIVATE'])]
    private string $privacy = self::PRIVACY_PUBLIC;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'studyGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, GroupMember> */
    #[ORM\OneToMany(targetEntity: GroupMember::class, mappedBy: 'group', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $members;

    /** @var Collection<int, GroupPost> */
    #[ORM\OneToMany(targetEntity: GroupPost::class, mappedBy: 'group', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $posts;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->members = new ArrayCollection();
        $this->posts = new ArrayCollection();
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

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
