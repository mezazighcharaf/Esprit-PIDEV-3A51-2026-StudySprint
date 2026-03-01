<?php

namespace App\Service;

use App\Entity\GroupMember;
use App\Entity\User;

/**
 * Service for formatting data for display in views.
 * Centralizes common formatting logic used across controllers.
 */
class FormattingService
{
    /**
     * Get initials from a name string (e.g., "John Doe" -> "JD")
     */
    public function getInitials(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return '';
        }

        $parts = explode(' ', trim($name));
        $initials = '';
        
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $initials .= mb_strtoupper(mb_substr($part, 0, 1));
                if (mb_strlen($initials) === 2) {
                    break;
                }
            }
        }
        
        return $initials;
    }

    /**
     * Format a date as a relative "time ago" string in French
     */
    public function formatTimeAgo(\DateTimeInterface $date): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $date->getTimestamp();

        // Handle future dates
        if ($diff < 0) {
            return $date->format('d F Y');
        }

        return match(true) {
            $diff < 60 => 'À l\'instant',
            $diff < 3600 => sprintf('Il y a %d minute%s', $m = intdiv($diff, 60), $m > 1 ? 's' : ''),
            $diff < 86400 => sprintf('Il y a %d heure%s', $h = intdiv($diff, 3600), $h > 1 ? 's' : ''),
            $diff < 604800 => sprintf('Il y a %d jour%s', $d = intdiv($diff, 86400), $d > 1 ? 's' : ''),
            $diff < 2592000 => sprintf('Il y a %d semaine%s', $w = intdiv($diff, 604800), $w > 1 ? 's' : ''),
            $diff < 31536000 => sprintf('Il y a %d mois', intdiv($diff, 2592000)),
            default => $date->format('d F Y'),
        };
    }

    /**
     * @param User|null $user
     * @return string
     */
    public function formatUserName(?User $user): string
    {
        if ($user === null) {
            return 'Utilisateur';
        }
        return trim($user->getPrenom() . ' ' . $user->getNom()) ?: 'Sans nom';
    }

    /**
     * Format a user for view (with initials)
     *
     * @return array<string, mixed>
     */
    public function formatUserForView(?User $user): array
    {
        if ($user === null) {
            return [
                'id' => null,
                'name' => 'Guest',
                'email' => null,
                'initials' => '',
            ];
        }

        $name = $this->formatUserName($user);
        
        return [
            'id' => $user->getId(),
            'name' => $name,
            'email' => $user->getEmail(),
            'initials' => $this->getInitials($name),
        ];
    }

    /**
     * Format group members for view
     *
     * @param GroupMember[] $memberships
     * @return list<array<string, mixed>>
     */
    public function formatGroupMembers(array $memberships): array
    {
        $members = [];
        foreach ($memberships as $membership) {
            $user = $membership->getUser();
            if ($user === null) {
                continue;
            }
            $name = $this->formatUserName($user);
            
            $members[] = [
                'id' => $membership->getId(),
                'user_id' => $user->getId(),
                'name' => $name,
                'email' => $user->getEmail(),
                'initials' => $this->getInitials($name),
                'role' => $membership->getMemberRole(),
            ];
        }
        return $members;
    }

    /**
     * Truncate text to a maximum length with ellipsis
     */
    public function truncateText(string $text, int $maxLength = 100, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - mb_strlen($suffix)) . $suffix;
    }

    /**
     * Format a file size in human-readable format
     */
    public function formatFileSize(int $bytes): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 1) . ' ' . $units[$unitIndex];
    }

    /**
     * Format a number with French locale
     */
    public function formatNumber(int|float $number, int $decimals = 0): string
    {
        return number_format($number, $decimals, ',', ' ');
    }

    /**
     * Pluralize a French word based on count
     */
    public function pluralize(int $count, string $singular, ?string $plural = null): string
    {
        if ($plural === null) {
            $plural = $singular . 's';
        }

        return $count === 1 ? $singular : $plural;
    }

    /**
     * Format count with label (e.g., "5 membres")
     */
    public function formatCount(int $count, string $singular, ?string $plural = null): string
    {
        return $count . ' ' . $this->pluralize($count, $singular, $plural);
    }
}
