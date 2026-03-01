<?php

namespace App\Dto;

class ProfessorExperienceDto
{
    public function __construct(
        public readonly string $label,
        public readonly int $value
    ) {}
}
