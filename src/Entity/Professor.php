<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Professor extends User
{
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(groups: ['professor'])]
    private ?string $specialite = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(groups: ['professor'])]
    private ?string $niveauEnseignement = null;

    #[ORM\Column]
    #[Assert\NotNull(groups: ['professor'])]
    #[Assert\PositiveOrZero(groups: ['professor'])]
    private ?int $anneesExperience = null;

    #[ORM\Column(length: 2)]
    #[Assert\NotBlank(groups: ['professor'])]
    #[Assert\Country(groups: ['professor'])]
    private ?string $pays = null;

    public function getSpecialite(): ?string
    {
        return $this->specialite;
    }

    public function setSpecialite(string $specialite): static
    {
        $this->specialite = $specialite;

        return $this;
    }

    public function getNiveauEnseignement(): ?string
    {
        return $this->niveauEnseignement;
    }

    public function setNiveauEnseignement(string $niveauEnseignement): static
    {
        $this->niveauEnseignement = $niveauEnseignement;

        return $this;
    }

    public function getAnneesExperience(): ?int
    {
        return $this->anneesExperience;
    }

    public function setAnneesExperience(int $anneesExperience): static
    {
        $this->anneesExperience = $anneesExperience;

        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(string $pays): static
    {
        $this->pays = $pays;

        return $this;
    }
}
