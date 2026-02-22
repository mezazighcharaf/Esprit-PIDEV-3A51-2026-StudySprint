<?php

namespace App\Entity;

use App\Repository\GroupPostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GroupPostRepository::class)]
#[ORM\Table(name: 'group_posts')]
#[ORM\Index(name: 'idx_gp_group', columns: ['group_id'])]
#[ORM\Index(name: 'idx_gp_author', columns: ['author_id'])]
class GroupPost
{
    public const TYPE_POST = 'POST';
    public const TYPE_COMMENT = 'COMMENT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StudyGroup::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private StudyGroup $group;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?GroupPost $parentPost = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\Choice(choices: ['POST', 'COMMENT'])]
    private string $postType = self::TYPE_POST;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $body;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $attachmentUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiSummary = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $aiCategory = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $aiTags = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parentPost', cascade: ['remove'])]
    private Collection $replies;

    /** @var Collection<int, PostLike> */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: PostLike::class, cascade: ['persist', 'remove'])]
    private Collection $likes;

    /** @var Collection<int, PostComment> */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: PostComment::class, cascade: ['persist', 'remove'])]
    private Collection $comments;

    /** @var Collection<int, PostRating> */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: PostRating::class, cascade: ['persist', 'remove'])]
    private Collection $ratings;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->replies   = new ArrayCollection();
        $this->likes     = new ArrayCollection();
        $this->comments  = new ArrayCollection();
        $this->ratings   = new ArrayCollection();
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

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function getParentPost(): ?GroupPost
    {
        return $this->parentPost;
    }

    public function setParentPost(?GroupPost $parentPost): static
    {
        $this->parentPost = $parentPost;
        return $this;
    }

    public function getPostType(): string
    {
        return $this->postType;
    }

    public function setPostType(string $postType): static
    {
        $this->postType = $postType;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;
        return $this;
    }

    public function getAttachmentUrl(): ?string
    {
        return $this->attachmentUrl;
    }

    public function setAttachmentUrl(?string $attachmentUrl): static
    {
        $this->attachmentUrl = $attachmentUrl;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, self> */
    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function getAiSummary(): ?string
    {
        return $this->aiSummary;
    }

    public function setAiSummary(?string $aiSummary): static
    {
        $this->aiSummary = $aiSummary;
        return $this;
    }

    public function getAiCategory(): ?string
    {
        return $this->aiCategory;
    }

    public function setAiCategory(?string $aiCategory): static
    {
        $this->aiCategory = $aiCategory;
        return $this;
    }

    public function getAiTags(): ?array
    {
        return $this->aiTags;
    }

    public function setAiTags(?array $aiTags): static
    {
        $this->aiTags = $aiTags;
        return $this;
    }

    /** @return Collection<int, PostLike> */
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    /** @return Collection<int, PostComment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /** @return Collection<int, PostRating> */
    public function getRatings(): Collection
    {
        return $this->ratings;
    }
}

