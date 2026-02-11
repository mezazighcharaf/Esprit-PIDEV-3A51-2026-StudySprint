<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ProfileDTO
{
    #[Assert\NotBlank(message: "remplire le champ nom")]
    public ?string $nom = null;

    #[Assert\NotBlank(message: "remplire le champs prenom")]
    public ?string $prenom = null;

    #[Assert\NotBlank(message: "obligatoire")]
    #[Assert\Email(message: "L'email n'est pas valide")]
    public ?string $email = null;

    // Student specific
    #[Assert\NotBlank(groups: ['student'], message: "obligatoire")]
    #[Assert\Range(min: 16, groups: ['student'], minMessage: "age > 15")]
    public ?int $age = null;

    #[Assert\NotBlank(groups: ['student'], message: "obligatoire")]
    #[Assert\Choice(choices: ['H', 'F'], groups: ['student'])]
    public ?string $sexe = null;

    #[Assert\NotBlank(groups: ['student'], message: "obligatoire")]
    public ?string $etablissement = null;

    #[Assert\NotBlank(groups: ['student'], message: "obligatoire")]
    public ?string $niveau = null;

    // Professor specific
    #[Assert\NotBlank(groups: ['professor'], message: "obligatoire")]
    public ?string $specialite = null;

    #[Assert\NotBlank(groups: ['professor'], message: "obligatoire")]
    public ?string $niveauEnseignement = null;

    #[Assert\NotBlank(groups: ['professor'], message: "obligatoire")]
    #[Assert\PositiveOrZero(groups: ['professor'])]
    public ?int $anneesExperience = null;

    public ?string $etablissementProfesseur = null;

    // Common
    #[Assert\NotBlank(message: "obligatoire")]
    #[Assert\Country]
    public ?string $pays = null;
}
