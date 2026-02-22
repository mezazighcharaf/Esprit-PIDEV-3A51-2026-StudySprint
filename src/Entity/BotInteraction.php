<?php

namespace App\Entity;

use App\Repository\BotInteractionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BotInteractionRepository::class)]
#[ORM\Table(name: 'bot_interaction')]
#[ORM\Index(name: 'idx_bot_group_created', columns: ['group_id', 'created_at'])]
#[ORM\Index(name: 'idx_bot_triggered_by', columns: ['triggered_by_id'])]
#[ORM\Index(name: 'idx_bot_post', columns: ['post_id'])]
class BotInteraction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StudyGroup::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?StudyGroup $group = null;

    #[ORM\ManyToOne(targetEntity: GroupPost::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?GroupPost $post = null;

    #[ORM\ManyToOne(targetEntity: PostComment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PostComment $comment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $triggeredBy = null;

    #[ORM\Column(type: 'text')]
    private ?string $question = null;

    #[ORM\Column(type: 'text')]
    private ?string $response = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $tokensUsed = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $responseTimeMs = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $feedback = null;

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

    public function getGroup(): ?StudyGroup
    {
        return $this->group;
    }

    public function setGroup(StudyGroup $group): static
    {
        $this->group = $group;
        return $this;
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

    public function getComment(): ?PostComment
    {
        return $this->comment;
    }

    public function setComment(?PostComment $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function getTriggeredBy(): ?User
    {
        return $this->triggeredBy;
    }

    public function setTriggeredBy(User $triggeredBy): static
    {
        $this->triggeredBy = $triggeredBy;
        return $this;
    }

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(string $question): static
    {
        $this->question = $question;
        return $this;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(string $response): static
    {
        $this->response = $response;
        return $this;
    }

    public function getTokensUsed(): int
    {
        return $this->tokensUsed;
    }

    public function setTokensUsed(int $tokensUsed): static
    {
        $this->tokensUsed = $tokensUsed;
        return $this;
    }

    public function getResponseTimeMs(): ?int
    {
        return $this->responseTimeMs;
    }

    public function setResponseTimeMs(?int $responseTimeMs): static
    {
        $this->responseTimeMs = $responseTimeMs;
        return $this;
    }

    public function getFeedback(): ?string
    {
        return $this->feedback;
    }

    public function setFeedback(?string $feedback): static
    {
        $this->feedback = $feedback;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
