<?php

namespace App\Entity;

use App\Repository\SubjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SubjectRepository::class)]
#[ORM\Table(name: 'subjects')]
class Subject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $code = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'subjects')]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Chapter> */
    #[ORM\OneToMany(targetEntity: Chapter::class, mappedBy: 'subject', cascade: ['persist', 'remove'])]
    private Collection $chapters;

    /** @var Collection<int, Quiz> */
    #[ORM\OneToMany(targetEntity: Quiz::class, mappedBy: 'subject')]
    private Collection $quizzes;

    /** @var Collection<int, FlashcardDeck> */
    #[ORM\OneToMany(targetEntity: FlashcardDeck::class, mappedBy: 'subject')]
    private Collection $flashcardDecks;

    /** @var Collection<int, RevisionPlan> */
    #[ORM\OneToMany(targetEntity: RevisionPlan::class, mappedBy: 'subject')]
    private Collection $revisionPlans;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->chapters = new ArrayCollection();
        $this->quizzes = new ArrayCollection();
        $this->flashcardDecks = new ArrayCollection();
        $this->revisionPlans = new ArrayCollection();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;
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

    /** @return Collection<int, Chapter> */
    public function getChapters(): Collection
    {
        return $this->chapters;
    }

    public function addChapter(Chapter $chapter): static
    {
        if (!$this->chapters->contains($chapter)) {
            $this->chapters->add($chapter);
            $chapter->setSubject($this);
        }
        return $this;
    }

    public function removeChapter(Chapter $chapter): static
    {
        $this->chapters->removeElement($chapter);
        return $this;
    }

    /** @return Collection<int, Quiz> */
    public function getQuizzes(): Collection
    {
        return $this->quizzes;
    }

    /** @return Collection<int, FlashcardDeck> */
    public function getFlashcardDecks(): Collection
    {
        return $this->flashcardDecks;
    }

    /** @return Collection<int, RevisionPlan> */
    public function getRevisionPlans(): Collection
    {
        return $this->revisionPlans;
    }
}
