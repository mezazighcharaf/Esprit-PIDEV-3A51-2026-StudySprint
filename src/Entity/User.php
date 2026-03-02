<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\InheritanceType("SINGLE_TABLE")]
#[ORM\DiscriminatorColumn(name: "discr", type: "string")]
#[ORM\DiscriminatorMap(["user" => User::class, "student" => Student::class, "professor" => Professor::class, "administrator" => Administrator::class])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'This email is already used.')]
abstract class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $nom = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $prenom = '';

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    #[ORM\Column(length: 255)]
    private string $motDePasse = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(length: 50)]
    private string $statut = 'actif';

    #[ORM\Column]
    private \DateTimeImmutable $dateInscription;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Ignore]
    private ?string $resetToken = null;

    #[ORM\Column(nullable: true)]
    #[Ignore]
    private ?\DateTimeImmutable $resetTokenExpiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastActivityAt = null;

    #[ORM\Column(length: 2, nullable: true)]
    #[Assert\Country]
    private ?string $pays = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: "/^(\+?\d{1,4})?\d{8,15}$/",
        message: "Format de numéro de téléphone invalide"
    )]
    private ?string $telephone = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $anneesExperience = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $faceDescriptor = null;

    // ──── Our relations ────────────────────────────────────────

    #[ORM\OneToOne(targetEntity: UserProfile::class, mappedBy: 'user', cascade: ['persist'], orphanRemoval: true)]
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
        $this->dateInscription = new \DateTimeImmutable();
        $this->subjects        = new ArrayCollection();
        $this->chapters        = new ArrayCollection();
        $this->studyGroups     = new ArrayCollection();
        $this->quizzes         = new ArrayCollection();
        $this->flashcardDecks  = new ArrayCollection();
        $this->revisionPlans   = new ArrayCollection();
    }

    // ──── Identity ────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    public function setFullName(string $fullName): static
    {
        $parts = explode(' ', trim($fullName), 2);
        $this->prenom = $parts[0];
        $this->nom    = $parts[1] ?? $parts[0];
        return $this;
    }

    public function getInitials(): string
    {
        $prenom = $this->prenom ? strtoupper(substr($this->prenom, 0, 1)) : '';
        $nom    = $this->nom    ? strtoupper(substr($this->nom,    0, 1)) : '';
        return $prenom . $nom;
    }

    // ──── Email ───────────────────────────────────────────────

    public function getEmail(): ?string
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
        return (string) $this->email;
    }

    // ──── Password (Symfony interface maps to motDePasse) ─────

    public function getPassword(): string
    {
        return (string) $this->motDePasse;
    }

    public function getMotDePasse(): ?string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(string $motDePasse): static
    {
        $this->motDePasse = $motDePasse;
        return $this;
    }

    /**
     * Alias kept for compatibility with Symfony's PasswordUpgraderInterface callers.
     */
    public function setPassword(string $password): static
    {
        $this->motDePasse = $password;
        return $this;
    }

    // ──── Roles (Symfony interface — role string → array) ─────

    public function getRoles(): array
    {
        $roles = [$this->role ?? 'ROLE_USER'];
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    /** Alias kept for compatibility with code that calls setRoles(array). */
    public function setRoles(array $roles): static
    {
        $this->role = $roles[0] ?? 'ROLE_USER';
        return $this;
    }

    public function eraseCredentials(): void {}

    // ──── Status ──────────────────────────────────────────────

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    // ──── Dates ───────────────────────────────────────────────

    public function getDateInscription(): ?\DateTimeImmutable
    {
        return $this->dateInscription;
    }

    public function setDateInscription(\DateTimeImmutable $dateInscription): static
    {
        $this->dateInscription = $dateInscription;
        return $this;
    }

    /** Alias for code that references createdAt. */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->dateInscription;
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

    // ──── Reset token ─────────────────────────────────────────

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetTokenExpiresAt;
    }

    public function setResetTokenExpiresAt(?\DateTimeImmutable $resetTokenExpiresAt): static
    {
        $this->resetTokenExpiresAt = $resetTokenExpiresAt;
        return $this;
    }

    // ──── Activity ────────────────────────────────────────────

    public function getLastActivityAt(): ?\DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(?\DateTimeImmutable $lastActivityAt): static
    {
        $this->lastActivityAt = $lastActivityAt;
        return $this;
    }

    // ──── Location / Contact ──────────────────────────────────

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): static
    {
        $this->pays = $pays;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    // ──── Experience ──────────────────────────────────────────

    public function getAnneesExperience(): ?int
    {
        return $this->anneesExperience;
    }

    public function setAnneesExperience(?int $anneesExperience): static
    {
        $this->anneesExperience = $anneesExperience;
        return $this;
    }

    // ──── Face recognition ────────────────────────────────────

    public function getFaceDescriptor(): ?array
    {
        return $this->faceDescriptor;
    }

    public function setFaceDescriptor(?array $faceDescriptor): static
    {
        $this->faceDescriptor = $faceDescriptor;
        return $this;
    }

    // ──── Helpers ─────────────────────────────────────────────

    /**
     * Returns true when the user holds the teacher role.
     * Kept for backward-compatibility with our BO code.
     */
    public function isCertifiedTeacher(): bool
    {
        return $this->role === 'ROLE_TEACHER' || $this->role === 'ROLE_PROFESSOR';
    }

    /**
     * Backward-compat alias: code that used getUserType() now maps to role.
     */
    public function getUserType(): ?string
    {
        return $this->role;
    }

    // ──── Relations ───────────────────────────────────────────

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
}
