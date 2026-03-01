<?php

namespace App\Dto;

class IdCountDto
{
    public function __construct(
        public readonly int $id,
        public readonly int $count
    ) {}
}
