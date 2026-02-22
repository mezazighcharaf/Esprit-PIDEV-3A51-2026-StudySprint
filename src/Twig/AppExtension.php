<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('timeAgo', [$this, 'timeAgo']),
            new TwigFilter('difficultyLabel', [$this, 'difficultyLabel']),
            new TwigFilter('starRating', [$this, 'starRating'], ['is_safe' => ['html']]),
            new TwigFilter('avatar_color', [$this, 'avatarColor']),
            new TwigFilter('initials', [$this, 'initials']),
        ];
    }

    public function avatarColor(string $name): string
    {
        $colors = ['blue', 'green', 'purple', 'orange', 'red', 'teal', 'pink', 'indigo'];
        return $colors[abs(crc32($name)) % count($colors)];
    }

    public function initials(string $name): string
    {
        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }

    public function timeAgo(\DateTimeInterface $date): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $date->getTimestamp();

        if ($diff < 60) {
            return 'À l\'instant';
        }
        if ($diff < 3600) {
            $m = (int) floor($diff / 60);
            return 'Il y a ' . $m . ' min';
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);
            return 'Il y a ' . $h . 'h';
        }
        if ($diff < 604800) {
            $d = (int) floor($diff / 86400);
            return 'Il y a ' . $d . 'j';
        }

        return $date->format('d/m/Y');
    }

    public function difficultyLabel(string $difficulty): string
    {
        return match (strtoupper($difficulty)) {
            'EASY' => 'Facile',
            'MEDIUM' => 'Moyen',
            'HARD' => 'Difficile',
            default => $difficulty,
        };
    }

    public function starRating(?float $score, int $max = 5): string
    {
        if ($score === null) {
            return '';
        }

        $html = '';
        for ($i = 1; $i <= $max; $i++) {
            $fill = $score >= $i ? '#f59e0b' : '#d1d5db';
            $html .= '<svg style="width:16px;height:16px;display:inline-block;" fill="' . $fill . '" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
        }

        return $html;
    }
}
