<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class PasswordResetDTO
{
    #[Assert\NotBlank(message: "Le code de vérification est obligatoire")]
    public ?string $verificationCode = null;

    #[Assert\NotBlank(message: "Le nouveau mot de passe est obligatoire")]
    #[Assert\Length(min: 8, minMessage: "Le mot de passe doit faire au moins 8 caractères")]
    #[Assert\Regex(
        pattern: "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/",
        message: "Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial"
    )]
    public ?string $newPassword = null;

    #[Assert\NotBlank(message: "La confirmation est obligatoire")]
    #[Assert\EqualTo(propertyPath: "newPassword", message: "Les mots de passe ne correspondent pas")]
    public ?string $confirmPassword = null;
}
