<?php

namespace App\Dto;

class PostRatingStatsDto
{
    public function __construct(
        public readonly int $postId,
        public readonly float $avgRating,
        public readonly int $ratingsCount
    ) {}
}
