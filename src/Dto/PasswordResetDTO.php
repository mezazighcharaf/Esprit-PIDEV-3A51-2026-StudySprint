<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class PasswordResetDTO
{
    #[Assert\NotBlank(message: "Le code de vérification est obligatoire")]
    public ?string $verificationCode = null;

    #[Assert\NotBlank(message: "Le nouveau mot de passe est obligatoire")]
    #[Assert\Length(min: 6, minMessage: "Le mot de passe doit faire au moins 6 caractères")]
    public ?string $newPassword = null;

    #[Assert\NotBlank(message: "La confirmation est obligatoire")]
    #[Assert\EqualTo(propertyPath: "newPassword", message: "Les mots de passe ne correspondent pas")]
    public ?string $confirmPassword = null;
}
