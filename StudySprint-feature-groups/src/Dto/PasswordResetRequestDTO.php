<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class PasswordResetRequestDTO
{
    #[Assert\NotBlank(message: "L'email est obligatoire", groups: ['email'])]
    #[Assert\Email(message: "L'email n'est pas valide", groups: ['email'])]
    public ?string $email = null;

    #[Assert\NotBlank(message: "Le numéro de téléphone est obligatoire", groups: ['telephone'])]
    #[Assert\Regex(
        pattern: "/^(\+?\d{1,4})?\d{8,15}$/",
        message: "Format de numéro de téléphone invalide"
    )]
    public ?string $telephone = null;

    #[Assert\NotBlank(message: "Veuillez choisir une méthode")]
    #[Assert\Choice(choices: ['email', 'telephone'])]
    public ?string $method = 'email';
}
