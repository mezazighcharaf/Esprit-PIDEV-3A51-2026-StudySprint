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


    #[ORM\Column(length: 255, nullable: true)]
    private ?string $etablissement = null;


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
