<?php

namespace App\Dto;

class BotGroupStatsDto
{
    public function __construct(
        public readonly int $totalInteractions,
        public readonly float $avgResponseTime,
        public readonly int $totalTokens,
        public readonly int $helpfulCount,
        public readonly int $notHelpfulCount
    ) {}
}
