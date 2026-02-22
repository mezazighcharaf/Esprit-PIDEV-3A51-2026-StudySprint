<?php

namespace App\Entity;

use App\Repository\PostCommentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PostCommentRepository::class)]
#[ORM\Table(name: 'post_comment')]
#[ORM\Index(name: 'idx_comment_post', columns: ['post_id'])]
#[ORM\Index(name: 'idx_comment_author', columns: ['author_id'])]
#[ORM\Index(name: 'idx_comment_parent', columns: ['parent_comment_id'])]
#[ORM\Index(name: 'idx_comment_created', columns: ['created_at'])]
class PostComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GroupPost::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?GroupPost $post = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?PostComment $parentComment = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $depth = 0;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Le commentaire ne peut pas être vide')]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $body = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isBot = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $botName = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPost(): ?GroupPost
    {
        return $this->post;
    }

    public function setPost(GroupPost $post): static
    {
        $this->post = $post;
        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(User $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function getParentComment(): ?self
    {
        return $this->parentComment;
    }

    public function setParentComment(?self $parentComment): static
    {
        $this->parentComment = $parentComment;
        
        // Auto-calculate depth from parent
        if ($parentComment === null) {
            $this->depth = 0;
        } else {
            $this->depth = $parentComment->getDepth() + 1;
        }
        
        return $this;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function setDepth(int $depth): static
    {
        $this->depth = max(0, min($depth, 3)); // Limit to 0-3
        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;
        return $this;
    }

    public function isBot(): bool
    {
        return $this->isBot;
    }

    public function setIsBot(bool $isBot): static
    {
        $this->isBot = $isBot;
        return $this;
    }

    public function getBotName(): ?string
    {
        return $this->botName;
    }

    public function setBotName(?string $botName): static
    {
        $this->botName = $botName;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
