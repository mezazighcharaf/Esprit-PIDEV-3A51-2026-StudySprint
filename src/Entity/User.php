<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Repository\UserRepository::class)]
#[ORM\InheritanceType("SINGLE_TABLE")]
#[ORM\DiscriminatorColumn(name: "discr", type: "string")]
#[ORM\DiscriminatorMap(["user" => User::class, "student" => Student::class, "professor" => Professor::class, "administrator" => Administrator::class])]
abstract class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $prenom = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $motDePasse = null;

    #[ORM\Column(length: 255)]
    private ?string $role = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = 'actif';

    #[ORM\Column]
    private ?\DateTimeImmutable $dateInscription = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resetTokenExpiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastActivityAt = null;
    #[ORM\Column(length: 2, nullable: true)]
    #[Assert\NotBlank(message: "Le pays est obligatoire")]
    #[Assert\Country]
    private ?string $pays = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    #[Assert\NotBlank(groups: ['professor'], message: "Les années d'expérience sont obligatoires")]
    private ?int $anneesExperience = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $demandeReactivation = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateDemandeReactivation = null;


    public function __construct()
    {
        $this->dateInscription = new \DateTimeImmutable();
    }

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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = strtolower(trim($email));

        return $this;
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

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDateInscription(): ?\DateTimeImmutable
    {
        return $this->dateInscription;
    }

    public function setDateInscription(\DateTimeImmutable $dateInscription): static
    {
        $this->dateInscription = $dateInscription;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = [$this->role ?? 'ROLE_USER'];
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->motDePasse;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

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

    public function getLastActivityAt(): ?\DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(?\DateTimeImmutable $lastActivityAt): static
    {
        $this->lastActivityAt = $lastActivityAt;
        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): static
    {
        $this->pays = $pays;
        return $this;
    }

    public function getAnneesExperience(): ?int
    {
        return $this->anneesExperience;
    }

    public function setAnneesExperience(?int $anneesExperience): static
    {
        $this->anneesExperience = $anneesExperience;
        return $this;
    }

    public function isDemandeReactivation(): ?bool
    {
        return $this->demandeReactivation;
    }

    public function setDemandeReactivation(bool $demandeReactivation): static
    {
        $this->demandeReactivation = $demandeReactivation;
        return $this;
    }

    public function getDateDemandeReactivation(): ?\DateTimeImmutable
    {
        return $this->dateDemandeReactivation;
    }

    public function setDateDemandeReactivation(?\DateTimeImmutable $dateDemandeReactivation): static
    {
        $this->dateDemandeReactivation = $dateDemandeReactivation;
        return $this;
    }

    /**
     * Get the full name of the user (prenom + nom)
     */
    public function getFullName(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    /**
     * Get the initials of the user (first letter of prenom + first letter of nom)
     */
    public function getInitials(): string
    {
        $prenom = $this->prenom ? strtoupper(substr($this->prenom, 0, 1)) : '';
        $nom = $this->nom ? strtoupper(substr($this->nom, 0, 1)) : '';
        return $prenom . $nom;
    }
}
