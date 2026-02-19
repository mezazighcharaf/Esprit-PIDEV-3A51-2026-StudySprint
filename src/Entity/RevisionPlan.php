<?php

namespace App\Entity;

use App\Repository\RevisionPlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RevisionPlanRepository::class)]
#[ORM\Table(name: 'revision_plans')]
class RevisionPlan
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_DONE = 'DONE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'revisionPlans')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Subject::class, inversedBy: 'revisionPlans')]
    #[ORM\JoinColumn(nullable: false)]
    private Subject $subject;

    #[ORM\ManyToOne(targetEntity: Chapter::class, inversedBy: 'revisionPlans')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Chapter $chapter = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $title;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $endDate;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\Choice(choices: ['DRAFT', 'ACTIVE', 'DONE'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $generatedByAi = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $aiMeta = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, PlanTask> */
    #[ORM\OneToMany(targetEntity: PlanTask::class, mappedBy: 'plan', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $tasks;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->tasks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSubject(): Subject
    {
        return $this->subject;
    }

    public function setSubject(Subject $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getChapter(): ?Chapter
    {
        return $this->chapter;
    }

    public function setChapter(?Chapter $chapter): static
    {
        $this->chapter = $chapter;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isGeneratedByAi(): bool
    {
        return $this->generatedByAi;
    }

    public function setGeneratedByAi(bool $generatedByAi): static
    {
        $this->generatedByAi = $generatedByAi;
        return $this;
    }

    public function getAiMeta(): ?array
    {
        return $this->aiMeta;
    }

    public function setAiMeta(?array $aiMeta): static
    {
        $this->aiMeta = $aiMeta;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, PlanTask> */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(PlanTask $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setPlan($this);
        }
        return $this;
    }

    public function removeTask(PlanTask $task): static
    {
        $this->tasks->removeElement($task);
        return $this;
    }
}
