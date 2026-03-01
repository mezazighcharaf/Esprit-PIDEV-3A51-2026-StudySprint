<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class GroupCreateDTO
{
    #[Assert\NotBlank(message: "Le nom du groupe est obligatoire")]
    #[Assert\Length(
        min: 3,
        max: 120,
        minMessage: "Le nom doit contenir au moins 3 caractères",
        maxMessage: "Le nom ne peut pas dépasser 120 caractères"
    )]
    public ?string $name = null;

    #[Assert\Length(
        max: 500,
        maxMessage: "La description ne peut pas dépasser 500 caractères"
    )]
    public ?string $description = null;

    #[Assert\NotBlank(message: "Le type de confidentialité est obligatoire")]
    #[Assert\Choice(choices: ['public', 'private', 'by_invitation'], message: "Type de confidentialité invalide")]
    public ?string $privacy = 'public';

    #[Assert\Length(
        max: 100,
        maxMessage: "Le sujet ne peut pas dépasser 100 caractères"
    )]
    public ?string $subject = null;
}
