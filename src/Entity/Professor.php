<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Professor extends User
{
    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(groups: ['professor'])]
    #[Assert\Range(min: 16, groups: ['professor'], minMessage: "L'âge doit être supérieur à 15 ans")]
    private ?int $age = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\NotBlank(groups: ['professor'])]
    private ?string $sexe = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $etablissement = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(groups: ['professor'])]
    private ?string $specialite = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(groups: ['professor'])]
    private ?string $niveauEnseignement = null;


    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): static
    {
        $this->age = $age;
        return $this;
    }

    public function getSexe(): ?string
    {
        return $this->sexe;
    }

    public function setSexe(?string $sexe): static
    {
        $this->sexe = $sexe;
        return $this;
    }


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


    public function getEtablissement(): ?string
    {
        return $this->etablissement;
    }

    public function setEtablissement(?string $etablissement): static
    {
        $this->etablissement = $etablissement;

        return $this;
    }
}
