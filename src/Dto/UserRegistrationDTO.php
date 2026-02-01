<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class UserRegistrationDTO
{
    #[Assert\NotBlank(message: "Le nom est obligatoire")]
    public ?string $nom = null;

    #[Assert\NotBlank(message: "Le prénom est obligatoire")]
    public ?string $prenom = null;

    #[Assert\NotBlank(message: "L'email est obligatoire")]
    #[Assert\Email(message: "L'email n'est pas valide")]
    public ?string $email = null;

    #[Assert\NotBlank(message: "Le mot de passe est obligatoire")]
    #[Assert\Length(min: 8, minMessage: "Le mot de passe doit faire au moins 8 caractères")]
    #[Assert\Regex(
        pattern: "/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/",
        message: "Le mot de passe doit contenir des lettres, des chiffres et au moins un caractère spécial (@$!%*#?&)"
    )]
    public ?string $motDePasse = null;

    #[Assert\NotBlank(message: "Le rôle est obligatoire")]
    #[Assert\Choice(choices: ['student', 'professor'], message: "Rôle invalide")]
    public ?string $role = null;

    // Student fields
    #[Assert\NotBlank(groups: ['student'], message: "L'âge est obligatoire")]
    #[Assert\Range(min: 10, max: 100, groups: ['student'])]
    public ?int $age = null;

    #[Assert\NotBlank(groups: ['student'], message: "Le sexe est obligatoire")]
    #[Assert\Choice(choices: ['H', 'F'], groups: ['student'])]
    public ?string $sexe = null;

    #[Assert\NotBlank(groups: ['student'], message: "Le pays est obligatoire")]
    #[Assert\Country(groups: ['student'])]
    public ?string $pays = null;

    #[Assert\NotBlank(groups: ['student'], message: "L'établissement est obligatoire")]
    public ?string $etablissement = null;

    #[Assert\NotBlank(groups: ['student'], message: "Le niveau est obligatoire")]
    public ?string $niveau = null;

    // Professor fields
    #[Assert\NotBlank(groups: ['professor'], message: "La spécialité est obligatoire")]
    public ?string $specialite = null;

    #[Assert\NotBlank(groups: ['professor'], message: "Le niveau enseigné est obligatoire")]
    public ?string $niveauEnseignement = null;

    #[Assert\NotBlank(groups: ['professor'], message: "Les années d'expérience sont obligatoires")]
    #[Assert\PositiveOrZero(groups: ['professor'])]
    public ?int $anneesExperience = null;
}
