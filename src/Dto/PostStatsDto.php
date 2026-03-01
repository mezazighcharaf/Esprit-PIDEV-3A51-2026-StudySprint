<?php

namespace App\Dto;

class PostStatsDto
{
    public function __construct(
        public readonly int $postId,
        public readonly int $count
    ) {}
}
