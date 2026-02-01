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

    #[Assert\Length(min: 6, minMessage: "Le mot de passe doit faire au moins 6 caractères")]
    public ?string $motDePasse = null;

    #[Assert\NotBlank(message: "Le rôle est obligatoire")]
    #[Assert\Choice(choices: ['ROLE_ADMIN', 'ROLE_STUDENT', 'ROLE_PROFESSOR'], message: "Rôle invalide")]
    public ?string $role = null;
}
