<?php

namespace App\Dto;

class UserKpiRowDto
{
    public function __construct(
        public readonly string $role,
        public readonly string $status,
        public readonly int $count
    ) {}
}
