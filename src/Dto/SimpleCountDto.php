<?php

namespace App\Dto;

class SimpleCountDto
{
    public function __construct(
        public readonly int $count
    ) {}
}
