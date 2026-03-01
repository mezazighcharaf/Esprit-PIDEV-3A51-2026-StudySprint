<?php

namespace App\Dto;

class UserRegistrationYearDto
{
    public function __construct(
        public readonly string $year,
        public readonly int $count
    ) {}
}
