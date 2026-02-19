<?php

namespace App\Entity;

use App\Repository\UserProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserProfileRepository::class)]
#[ORM\Table(name: 'user_profiles')]
class UserProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'profile')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $level = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $specialty = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    #[Assert\Url]
    private ?string $avatarUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiSuggestedBio = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiSuggestedGoals = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiSuggestedRoutine = null;

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

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(?string $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getSpecialty(): ?string
    {
        return $this->specialty;
    }

    public function setSpecialty(?string $specialty): static
    {
        $this->specialty = $specialty;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): static
    {
        $this->avatarUrl = $avatarUrl;
        return $this;
    }

    public function getAiSuggestedBio(): ?string
    {
        return $this->aiSuggestedBio;
    }

    public function setAiSuggestedBio(?string $aiSuggestedBio): static
    {
        $this->aiSuggestedBio = $aiSuggestedBio;
        return $this;
    }

    public function getAiSuggestedGoals(): ?string
    {
        return $this->aiSuggestedGoals;
    }

    public function setAiSuggestedGoals(?string $aiSuggestedGoals): static
    {
        $this->aiSuggestedGoals = $aiSuggestedGoals;
        return $this;
    }

    public function getAiSuggestedRoutine(): ?string
    {
        return $this->aiSuggestedRoutine;
    }

    public function setAiSuggestedRoutine(?string $aiSuggestedRoutine): static
    {
        $this->aiSuggestedRoutine = $aiSuggestedRoutine;
        return $this;
    }

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $currentStreak = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $longestStreak = 0;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastActivityDate = null;

    public function getCurrentStreak(): int { return $this->currentStreak; }
    public function setCurrentStreak(int $streak): static { $this->currentStreak = $streak; return $this; }
    public function getLongestStreak(): int { return $this->longestStreak; }
    public function setLongestStreak(int $streak): static { $this->longestStreak = $streak; return $this; }
    public function getLastActivityDate(): ?\DateTimeImmutable { return $this->lastActivityDate; }
    public function setLastActivityDate(?\DateTimeImmutable $date): static { $this->lastActivityDate = $date; return $this; }
}
