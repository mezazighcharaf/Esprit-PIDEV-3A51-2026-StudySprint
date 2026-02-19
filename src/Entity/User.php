<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'This email is already used.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(type: Types::STRING)]
    private string $password;

    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $fullName;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\Choice(choices: ['STUDENT', 'TEACHER', 'ADMIN'])]
    private string $userType = 'STUDENT';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToOne(targetEntity: UserProfile::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?UserProfile $profile = null;

    /** @var Collection<int, Subject> */
    #[ORM\OneToMany(targetEntity: Subject::class, mappedBy: 'createdBy')]
    private Collection $subjects;

    /** @var Collection<int, Chapter> */
    #[ORM\OneToMany(targetEntity: Chapter::class, mappedBy: 'createdBy')]
    private Collection $chapters;

    /** @var Collection<int, StudyGroup> */
    #[ORM\OneToMany(targetEntity: StudyGroup::class, mappedBy: 'createdBy')]
    private Collection $studyGroups;

    /** @var Collection<int, Quiz> */
    #[ORM\OneToMany(targetEntity: Quiz::class, mappedBy: 'owner')]
    private Collection $quizzes;

    /** @var Collection<int, FlashcardDeck> */
    #[ORM\OneToMany(targetEntity: FlashcardDeck::class, mappedBy: 'owner')]
    private Collection $flashcardDecks;

    /** @var Collection<int, RevisionPlan> */
    #[ORM\OneToMany(targetEntity: RevisionPlan::class, mappedBy: 'user')]
    private Collection $revisionPlans;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->subjects = new ArrayCollection();
        $this->chapters = new ArrayCollection();
        $this->studyGroups = new ArrayCollection();
        $this->quizzes = new ArrayCollection();
        $this->flashcardDecks = new ArrayCollection();
        $this->revisionPlans = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getUserType(): string
    {
        return $this->userType;
    }

    public function setUserType(string $userType): static
    {
        $this->userType = $userType;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
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

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getProfile(): ?UserProfile
    {
        return $this->profile;
    }

    public function setProfile(?UserProfile $profile): static
    {
        if ($profile !== null && $profile->getUser() !== $this) {
            $profile->setUser($this);
        }
        $this->profile = $profile;
        return $this;
    }

    /** @return Collection<int, Subject> */
    public function getSubjects(): Collection
    {
        return $this->subjects;
    }

    /** @return Collection<int, Chapter> */
    public function getChapters(): Collection
    {
        return $this->chapters;
    }

    /** @return Collection<int, StudyGroup> */
    public function getStudyGroups(): Collection
    {
        return $this->studyGroups;
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

    public function isCertifiedTeacher(): bool
    {
        return $this->userType === 'TEACHER';
    }

    public function eraseCredentials(): void
    {
        // Clear temporary sensitive data if any
    }
}
