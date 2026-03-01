<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class UserRegistrationDTO
{
    #[Assert\NotBlank(message: "remplire le champ nom")]
    public ?string $nom = null;

    #[Assert\NotBlank(message: "remplire le champs prenom")]
    public ?string $prenom = null;

    #[Assert\NotBlank(message: "obligatoire")]
    #[Assert\Email(message: "L'email n'est pas valide")]
    public ?string $email = null;

    #[Assert\Regex(
        pattern: "/^(\+?\d{1,4})?\d{8,15}$/",
        message: "Format de numéro de téléphone invalide"
    )]
    public ?string $telephone = null;

    #[Assert\NotBlank(message: "obligatoire")]
    #[Assert\Length(min: 8, minMessage: "Le mot de passe doit faire au moins 8 caractères")]
    #[Assert\Regex(
        pattern: "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&\/\.\-_])[A-Za-z\d@$!%*?&\/\.\-_]{8,}$/",
        message: "Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial"
    )]
    public ?string $motDePasse = null;

    #[Assert\NotBlank(message: "Le rôle est obligatoire")]
    #[Assert\Choice(choices: ['student', 'professor'], message: "Rôle invalide")]
    public ?string $role = null;

    // Student fields
    #[Assert\NotBlank(groups: ['student', 'professor'], message: "obligatoire")]
    #[Assert\Range(min: 16, groups: ['student', 'professor'], minMessage: "age > 15")]
    public ?int $age = null;

    #[Assert\NotBlank(groups: ['student', 'professor'], message: "obligatoire")]
    #[Assert\Choice(choices: ['H', 'F'], groups: ['student', 'professor'])]
    public ?string $sexe = null;

    #[Assert\NotBlank(groups: ['student', 'professor'], message: "obligatoire")]
    #[Assert\Country(groups: ['student', 'professor'])]
    public ?string $pays = null;

    #[Assert\NotBlank(groups: ['student', 'professor'], message: "obligatoire")]
    public ?string $etablissement = null;

    #[Assert\NotBlank(groups: ['student'], message: "obligatoire")]
    public ?string $niveau = null;

    // Professor fields
    #[Assert\NotBlank(groups: ['professor'], message: "obligatoire")]
    public ?string $specialite = null;

    #[Assert\NotBlank(groups: ['professor'], message: "obligatoire")]
    public ?string $niveauEnseignement = null;

    #[Assert\NotBlank(groups: ['professor'], message: "obligatoire")]
    #[Assert\PositiveOrZero(groups: ['professor'])]
    public ?int $anneesExperience = null;

    #[Assert\NotBlank(groups: ['professor'], message: "obligatoire")]
    public ?string $etablissementProfesseur = null;

}
