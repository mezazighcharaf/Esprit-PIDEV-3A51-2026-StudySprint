<?php

namespace App\Dto;

class UserHierarchyDto
{
    public function __construct(
        public readonly string $pays,
        public readonly string $etablissement,
        public readonly int $count
    ) {}
}
