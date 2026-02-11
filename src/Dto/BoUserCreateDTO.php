<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class BoUserCreateDTO
{
    #[Assert\NotBlank(message: "Le nom est obligatoire")]
    public ?string $nom = null;

    #[Assert\NotBlank(message: "Le prénom est obligatoire")]
    public ?string $prenom = null;

    #[Assert\NotBlank(message: "L'email est obligatoire")]
    #[Assert\Email(message: "L'email n'est pas valide")]
    public ?string $email = null;

    #[Assert\Length(min: 8, minMessage: "Le mot de passe doit faire au moins 8 caractères", groups: ['password_strength'])]
    #[Assert\Regex(
        pattern: "/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/",
        message: "Le mot de passe doit contenir des lettres, des chiffres et au moins un caractère spécial (@$!%*#?&)",
        groups: ['password_strength']
    )]
    public ?string $motDePasse = null;

    #[Assert\NotBlank(message: "Le rôle est obligatoire")]
    #[Assert\Choice(choices: ['ROLE_ADMIN', 'ROLE_STUDENT', 'ROLE_PROFESSOR'], message: "Rôle invalide")]
    public ?string $role = null;

    // Common/Specific fields
    #[Assert\NotBlank(message: "Le pays est obligatoire")]
    #[Assert\Country]
    public ?string $pays = null;

    // Student fields
    #[Assert\NotBlank(groups: ['student'], message: "L'âge est obligatoire")]
    #[Assert\Range(min: 16, groups: ['student'], minMessage: "L'âge doit être supérieur à 15 ans")]
    public ?int $age = null;

    #[Assert\NotBlank(groups: ['student'], message: "Le sexe est obligatoire")]
    #[Assert\Choice(choices: ['H', 'F'], groups: ['student'])]
    public ?string $sexe = null;

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

    public ?string $etablissementProfesseur = null;

    #[Assert\NotBlank(groups: ['creation'], message: "Le mot de passe est obligatoire pour les nouveaux utilisateurs")]
    public function getPlainPassword(): ?string
    {
        return $this->motDePasse;
    }
}
