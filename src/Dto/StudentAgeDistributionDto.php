<?php

namespace App\Dto;

class StudentAgeDistributionDto
{
    public function __construct(
        public readonly string $ageRange,
        public readonly string $sex,
        public readonly int $count
    ) {}
}
