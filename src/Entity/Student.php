<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Student extends User
{
    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(groups: ['student'])]
    #[Assert\Range(min: 16, groups: ['student'], minMessage: "L'âge doit être supérieur à 15 ans")]
    private ?int $age = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\NotBlank(groups: ['student'])]
    private ?string $sexe = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(groups: ['student'])]
    private ?string $etablissement = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\NotBlank(groups: ['student'])]
    private ?string $niveau = null;

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(int $age): static
    {
        $this->age = $age;
        return $this;
    }

    public function getSexe(): ?string
    {
        return $this->sexe;
    }

    public function setSexe(string $sexe): static
    {
        $this->sexe = $sexe;
        return $this;
    }

    public function getEtablissement(): ?string
    {
        return $this->etablissement;
    }

    public function setEtablissement(string $etablissement): static
    {
        $this->etablissement = $etablissement;
        return $this;
    }

    public function getNiveau(): ?string
    {
        return $this->niveau;
    }

    public function setNiveau(string $niveau): static
    {
        $this->niveau = $niveau;
        return $this;
    }
}
